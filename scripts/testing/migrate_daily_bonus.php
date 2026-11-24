<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo = HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração exige ausência de transacção.');
    $sql = file_get_contents(dirname(__DIR__, 2).'/server-laravel/docker/initdb/05_daily_bonus.sql');
    foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
    echo "Login diário e eventos de bónus: migração idempotente concluída.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
