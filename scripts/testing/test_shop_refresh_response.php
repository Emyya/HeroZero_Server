<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\Router;

$hero = 'laliene';
foreach ($argv as $arg) if (str_starts_with($arg, '--hero=')) $hero = substr($arg, 7);
$row = Db::row('SELECT id, user_id FROM `character` WHERE name = ?', [$hero]);
if ($row === null) {
    fwrite(STDERR, "Herói não encontrado: {$hero}\n");
    exit(1);
}

$pdo = Db::pdo();
try {
    $pdo->beginTransaction();
    $result = Router::dispatch('refreshShopItems', [
        'action' => 'refreshShopItems', 'user_id' => (int)$row['user_id'],
        'shop_index' => 1, 'use_premium' => false,
    ]);
    $inventory = $result['inventory'] ?? [];
    $items = array_column($result['items'] ?? [], null, 'id');
    for ($slot = 1; $slot <= 9; $slot++) {
        $id = (int)($inventory['shop_item'.$slot.'_id'] ?? 0);
        if ($id <= 0 || !isset($items[$id])) {
            throw new RuntimeException("Slot {$slot} sem ID/item correspondente na resposta");
        }
    }
    if (!array_key_exists('premium_currency', $result['user'] ?? [])) {
        throw new RuntimeException('Resposta sem saldo de donuts');
    }
    $pdo->rollBack();
    echo "PASS MySQL: os nove slots renovados e os respetivos itens seguem na resposta; rollback.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
    exit(1);
}
