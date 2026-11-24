<?php
declare(strict_types=1);

// Invocado pelo reset_server.sh, com serviços de jogo/admin/socket parados.
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;

if (!in_array('--execute', $argv, true)) {
    fwrite(STDERR, "Operação destrutiva. Usar bash reset_server.sh.\n");
    exit(1);
}
try {
    $pdo = Db::pdo();
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== 'herozero') {
        throw new RuntimeException('Reset limitado à BD herozero.');
    }
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
          AND TABLE_NAME <> 'migrations'")->fetchAll(PDO::FETCH_COLUMN);
    // TRUNCATE confirma implicitamente: não declarar uma transação fictícia.
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($tables as $table) {
            $pdo->exec('TRUNCATE TABLE `'.str_replace('`', '``', $table).'`');
        }
        // Linhas técnicas de serialização, sem calendários ou contas de exemplo.
        foreach (['casino_control', 'tournament_calendar_lock', 'herocon_control',
            'event_quest_control', 'event_treasure_control', 'event_draw_control',
            'tower_event_control'] as $table) {
            if (in_array($table, $tables, true)) $pdo->exec('INSERT INTO `'.$table.'` (id) VALUES (1)');
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    echo count($tables)." tabelas limpas; schema preservado.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Reset interrompido: '.$e->getMessage()."\n");
    exit(1);
}
