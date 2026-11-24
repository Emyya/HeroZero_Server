<?php
declare(strict_types=1);
// Persistente: schema, backup e transição dos ciclos ativos antigos para três calendários.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, Tournament};
try {
    $pdo=Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração não pode correr numa transação de jogo.');
    $backup=[];
    foreach(['tournaments','tournament_snapshots','tournament_rewards'] as $table) $backup[$table]=Db::rows('SELECT * FROM `'.$table.'`');
    $backup['pending_counters']=Db::rows('SELECT id, pending_tournament_rewards FROM `character`');
    $dir=dirname(__DIR__,2).'/server-laravel/storage/backups';
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Falha ao criar pasta de backups.');
    $file=$dir.'/tournament_calendar_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3)).'.json';
    if (file_put_contents($file,json_encode($backup,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))===false) throw new RuntimeException('Falha ao guardar backup.');
    chmod($file,0600);
    echo 'Backup: '.$file."\n";
    $pdo->exec(file_get_contents(dirname(__DIR__,2).'/server-laravel/docker/initdb/06_tournament_calendar.sql'));
    if (!Db::value("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tournaments' AND COLUMN_NAME='type'")) {
        $pdo->exec('ALTER TABLE tournaments ADD COLUMN type TINYINT NULL DEFAULT NULL AFTER id');
    }
    foreach ([['tournaments','uq_type_start','type, ts_start'],['tournament_snapshots','uq_snapshot_character','tournament_id, character_id'],['tournament_rewards','uq_reward_character','tournament_id, character_id']] as [$table,$index,$cols]) {
        if (!Db::value('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index])) {
            if (Db::row('SELECT '.$cols.', COUNT(*) AS n FROM '.$table.' GROUP BY '.$cols.' HAVING n>1 LIMIT 1')) throw new RuntimeException('Existem duplicados em '.$table.'; backup guardado, sem apagar registos automaticamente.');
            $pdo->exec('ALTER TABLE '.$table.' ADD UNIQUE KEY '.$index.' ('.$cols.')');
        }
    }
    $cycles=Tournament::synchronize();
    foreach ($cycles as $type=>$cycle) echo 'Tipo '.$type.': '.(new DateTimeImmutable('@'.$cycle['ts_end']))->setTimezone(HeroZero\Live::gameTimezone())->format('Y-m-d H:i:s T')."\n";
    echo "PASS: schema idempotente e calendários separados; prémios anteriores preservados.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
