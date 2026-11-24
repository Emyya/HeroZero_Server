<?php
declare(strict_types=1);
// Integração MySQL: ambos os logins e compras; todas as alterações têm rollback.
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, GameTime};
$hero = $argv[1] ?? 'PedroPipoca';
$pdo = Db::pdo();
function verifyBotLogin(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
try {
    $pdo->beginTransaction();
    $c = Db::row('SELECT id,user_id FROM `character` WHERE name=? FOR UPDATE', [$hero]);
    if (!$c) throw new RuntimeException('Herói não encontrado');
    $u = Db::row('SELECT id,email FROM `user` WHERE id=? FOR UPDATE', [$c['user_id']]);
    $password = bin2hex(random_bytes(16));
    Db::exec('UPDATE `user` SET password_hash=?,premium_currency=30 WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    Db::exec('DELETE FROM tournament_snapshots WHERE character_id=?', [$c['id']]);
    $root = dirname(__DIR__, 2).'/server-laravel/actions/';
    $login = require $root.'loginUser.php';
    $auto = require $root.'autoLoginUser.php';
    $buy = require $root.'buyBooster.php';
    $data = $login(['email'=>$u['email'], 'password'=>$password]);
    $snapshots = Db::rows('SELECT s.* FROM tournament_snapshots s JOIN tournaments t ON t.id=s.tournament_id WHERE s.character_id=? AND t.status=1 ORDER BY t.type', [$c['id']]);
    verifyBotLogin(count($snapshots) === 3, 'loginUser não inscreveu nos três torneios');
    verifyBotLogin((int)$data['user']['premium_currency'] === 30, 'Saldo ausente no login');
    foreach (['quest','stats','work'] as $kind) {
        $id = 'booster_'.$kind.'3';
        $offer = $data['constants']['boosters'][$id]['duration'][604800] ?? null;
        verifyBotLogin($offer !== null && $offer['premium'] && $offer['cost'] === 10, 'Catálogo incompatível com o bot');
        $result = $buy(['user_id'=>$u['id'],'id'=>$id,'duration'=>604800]);
        verifyBotLogin($result['character']['active_'.$kind.'_booster_id'] === $id, 'Compra não ativou energético');
        verifyBotLogin((int)$result['character']['ts_active_'.$kind.'_boost_expires'] > GameTime::now(), 'Expiração inválida');
    }
    verifyBotLogin((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?', [$u['id']]) === 0, 'Débito incorreto');
    $auto(['user_id'=>$u['id'], 'user_session_id'=>$data['user']['session_id']]);
    $again = Db::rows('SELECT s.* FROM tournament_snapshots s JOIN tournaments t ON t.id=s.tournament_id WHERE s.character_id=? AND t.status=1 ORDER BY t.type', [$c['id']]);
    verifyBotLogin($again === $snapshots, 'autoLoginUser alterou snapshots existentes');
    Db::exec('DELETE FROM tournament_snapshots WHERE character_id=?', [$c['id']]);
    $auto(['user_id'=>$u['id'], 'user_session_id'=>Db::value('SELECT session_id FROM `user` WHERE id=?', [$u['id']])]);
    verifyBotLogin((int)Db::value('SELECT COUNT(*) FROM tournament_snapshots s JOIN tournaments t ON t.id=s.tournament_id WHERE s.character_id=? AND t.status=1', [$c['id']]) === 3, 'autoLoginUser não inscreveu');
    verifyBotLogin($pdo->inTransaction(), 'Transação externa perdida');
    $pdo->rollBack();
    echo "OK: dois logins, três torneios, snapshots preservados, três compras e débito; rollback completo.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    exit(1);
}
