<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo = HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Executar migração fora de transação.');
    foreach (['ts_active_hideout_boost_started'=>'int NOT NULL DEFAULT 0', 'active_hideout_booster_id'=>"varchar(32) NOT NULL DEFAULT ''", 'ts_active_hideout_boost_expires'=>'int NOT NULL DEFAULT 0'] as $column=>$definition) {
        $exists = HeroZero\Db::value('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['character',$column]);
        if (!$exists) $pdo->exec("ALTER TABLE `character` ADD COLUMN `{$column}` {$definition}");
    }
    HeroZero\Db::exec("UPDATE `character` SET ts_active_hideout_boost_started=? WHERE active_hideout_booster_id<>'' AND ts_active_hideout_boost_started=0 AND (ts_active_hideout_boost_expires>? OR ts_active_hideout_boost_expires=-1)",[HeroZero\GameTime::now(),HeroZero\GameTime::now()]);
    foreach (['vouchers','voucher_redemptions'] as $table) {
        if (!HeroZero\Db::value('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',[$table])) throw new RuntimeException("Falta tabela {$table}; consultar docker/initdb/01_schema.sql.");
    }
    echo "BD pronta para cupões das mensagens (migração idempotente).\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
