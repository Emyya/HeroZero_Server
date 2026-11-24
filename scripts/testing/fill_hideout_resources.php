<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\Hideout;

$name = $argv[1] ?? '';
if ($name === '') {
    fwrite(STDERR, "Uso: php scripts/testing/fill_hideout_resources.php \"Nome do herói\"\n");
    exit(1);
}
$pdo = null;
try {
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo.');
    $cid = (int)$heroes[0]['id'];
    $hideout = Db::row('SELECT * FROM hideout WHERE character_id = ? FOR UPDATE', [$cid]);
    if (!$hideout) throw new RuntimeException('Este herói ainda não tem esconderijo.');
    $rooms = Db::rows('SELECT * FROM hideout_rooms WHERE character_id = ? FOR UPDATE', [$cid]);
    $oldStone = (int)$hideout['current_resource_stone'];
    $oldGlue = (int)$hideout['current_resource_glue'];
    Hideout::applyCaps($hideout, $rooms);
    $maxStone = (int)$hideout['max_resource_stone'];
    $maxGlue = (int)$hideout['max_resource_glue'];
    if ($maxStone <= 0 || $maxGlue <= 0) throw new RuntimeException('Sem capacidade de depósito: verifica se a central está construída e colocada.');
    Db::exec('UPDATE hideout SET current_resource_stone = ?, current_resource_glue = ?,
        max_resource_stone = ?, max_resource_glue = ? WHERE character_id = ?',
        [$maxStone, $maxGlue, $maxStone, $maxGlue, $cid]);
    $pdo->commit();
    echo "Herói: {$heroes[0]['name']} (ID {$cid})\n";
    echo "Zénite: {$oldStone} → {$maxStone}/{$maxStone}\n";
    echo "Supercola: {$oldGlue} → {$maxGlue}/{$maxGlue}\n";
    echo "Depósitos cheios e guardados na BD. Recarrega o jogo.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
