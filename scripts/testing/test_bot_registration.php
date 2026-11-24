<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\Router;

$pdo = Db::pdo();
$email = 'bot_registration_test_'.bin2hex(random_bytes(6)).'@example.invalid';
$name = 'BotTeste'.bin2hex(random_bytes(4));
try {
    $pdo->beginTransaction();
    $result = Router::dispatch('registerBotAccount', [
        'action' => 'registerBotAccount', 'email' => $email,
        'password' => '123456', 'name' => $name, 'gender' => 'm',
    ]);
    $userId = (int)($result['user']['id'] ?? 0);
    $charId = (int)($result['character']['id'] ?? 0);
    if ($userId <= 0 || $charId <= 0 || ($result['character']['name'] ?? '') !== $name) {
        throw new RuntimeException('Registo não devolveu conta e personagem com nome');
    }
    if ((int)Db::value('SELECT COUNT(*) FROM `character` WHERE id = ? AND user_id = ? AND name = ?',
        [$charId, $userId, $name]) !== 1) {
        throw new RuntimeException('Personagem não foi criada na transação');
    }
    try {
        Router::dispatch('registerBotAccount', [
            'action' => 'registerBotAccount', 'email' => $email,
            'password' => '123456', 'name' => $name, 'gender' => 'm',
        ]);
        throw new RuntimeException('Aceitou email duplicado');
    } catch (HeroZero\GameError $e) {
        if ($e->getMessage() !== 'errRegisterEmailAlreadyExists') throw $e;
    }
    $pdo->rollBack();
    if (Db::value('SELECT id FROM `user` WHERE email = ?', [$email]) !== null) {
        throw new RuntimeException('Rollback deixou a conta de teste na BD');
    }
    echo "PASS MySQL: registo atómico, nome presente, email duplicado rejeitado e rollback.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
    exit(1);
}
