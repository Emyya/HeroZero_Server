<?php
declare(strict_types=1);

/** Cria uma única conta passiva de nível 1 para servir de adversário ao tutorial. */
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameTime;

$dryRun = ($argv[1] ?? '') === '--dry-run';
if (count($argv) > 2 || (isset($argv[1]) && !$dryRun)) {
    fwrite(STDERR, "Uso: php scripts/setup/ensure_tutorial_player.php [--dry-run]\n");
    exit(1);
}
$pdo = null;
$locked = false;
try {
    $pdo = Db::pdo();
    $locked = (int)Db::value("SELECT GET_LOCK('hz_tutorial_player_seed', 10)") === 1;
    if (!$locked) throw new RuntimeException('Não foi possível obter o lock de preparação.');
    $pdo->beginTransaction();
    $email = 'tutorial-opponent@seed.invalid';
    $source = 'local_tutorial_opponent_v1';
    $users = Db::rows('SELECT id, registration_source FROM `user` WHERE email = ? FOR UPDATE', [$email]);
    if (count($users) > 1 || ($users && $users[0]['registration_source'] !== $source)) {
        throw new RuntimeException('Identificador reservado já utilizado por outra conta; nenhuma alteração aplicada.');
    }
    if (!$users) {
        if (Db::value('SELECT id FROM `character` WHERE name = ?', ['AjudanteTutorial'])) {
            throw new RuntimeException('Nome AjudanteTutorial já utilizado; nenhuma alteração aplicada.');
        }
        $now = GameTime::now();
        Db::exec('INSERT INTO `user`
            (registration_source, ts_creation, email, password_hash, last_login_ip,
             login_count, ts_last_login, session_id, session_id_cache1, session_id_cache2,
             session_id_cache3, session_id_cache4, session_id_cache5, premium_currency,
             locale, network, geo_country_code, geo_country_code3, geo_country_name,
             geo_continent_code, ts_banned, confirmed, trusted)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, 0,
                     ?, ?, ?, ?, ?, ?, 0, 0, 0)',
            [$source, $now, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
             '', bin2hex(random_bytes(15)), '', '', '', '', '',
             'pt_BR', 'local', 'PT', 'PRT', 'Portugal', 'EU']);
        $uid = (int)$pdo->lastInsertId();
    } else {
        $uid = (int)$users[0]['id'];
    }
    $characters = Db::rows('SELECT id, name, level FROM `character` WHERE user_id = ? FOR UPDATE', [$uid]);
    if (count($characters) > 1) throw new RuntimeException('Conta de preparação com personagens duplicadas.');
    $created = !$characters;
    if ($created) {
        if (Db::value('SELECT id FROM `character` WHERE name = ?', ['AjudanteTutorial'])) {
            throw new RuntimeException('Nome reservado já utilizado por outra personagem.');
        }
        $hero = Character::createNew($uid, 'AjudanteTutorial');
        $cid = $hero->id();
    } else {
        $cid = (int)$characters[0]['id'];
    }
    $row = Db::row('SELECT name, level FROM `character` WHERE id = ?', [$cid]);
    if ($created && (int)$row['level'] !== 1) throw new RuntimeException('Nível inicial inesperado.');
    if ($dryRun) $pdo->rollBack();
    else $pdo->commit();
    echo ($dryRun ? '[ROLLBACK] ' : '') . ($created ? 'Criado' : 'Já existe')
        . ": {$row['name']}, nível {$row['level']} (personagem {$cid}).\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    // Não imprimir mensagens SQL que possam conter credenciais geradas.
    fwrite(STDERR, 'Falha na preparação do adversário: '
        . ($e instanceof PDOException ? 'erro de ligação/SQL; verificar configuração e schema.' : $e->getMessage()) . "\n");
    exit(1);
} finally {
    if ($locked) Db::value("SELECT RELEASE_LOCK('hz_tutorial_player_seed')");
}
