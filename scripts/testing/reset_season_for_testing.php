<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\Season;

$name = $argv[1] ?? '';
$points = $argv[2] ?? 'keep';
$premium = $argv[3] ?? 'keep';
if ($name === '' || ($points !== 'keep' && (!ctype_digit($points) || strlen($points) > 10 || (int)$points > 2147483647))
    || !in_array($premium, ['keep', '0', '1'], true) || count($argv) > 4) {
    fwrite(STDERR, "Uso: php scripts/testing/reset_season_for_testing.php \"Herói\" [pontos ou keep] [premium: 0|1|keep]\n"
        . "Sem opções: limpa resgates e mantém pontos e premium.\n");
    exit(1);
}

$pdo = null;
try {
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo.');
    $cid = (int)$heroes[0]['id'];
    $progress = Db::row('SELECT * FROM season_progress WHERE character_id = ? FOR UPDATE', [$cid]);
    if (!$progress) throw new RuntimeException('Este herói ainda não tem progresso de season. Abre o painel da season no jogo primeiro.');
    $newPoints = $points === 'keep' ? (int)$progress['season_points'] : (int)$points;
    $newPremium = $premium === 'keep' ? (int)$progress['premium_unlocked'] : (int)$premium;
    $claimed = json_decode((string)$progress['claimed_rewards'], true);
    $claimedCount = is_array($claimed) ? count($claimed) : 0;

    // Guardar o estado anterior antes de limpar os resgates.
    $backupDir = dirname(__DIR__, 2) . '/server-laravel/storage/season-debug-backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0770, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Não foi possível criar a pasta de backup.');
    }
    $backup = $backupDir . '/character_' . $cid . '_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
    $json = json_encode(['character_name' => $heroes[0]['name'], 'saved_at' => gmdate('c'),
        'season_progress' => $progress], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($backup, $json, LOCK_EX) === false) throw new RuntimeException('Não foi possível guardar o backup.');

    // Renovar uma janela expirada usando a mesma lógica que o login do jogo.
    Season::ensureProgress($cid);
    Db::exec('UPDATE season_progress SET season_points = ?, premium_unlocked = ?,
        claimed_rewards = ?, unlocked_rewards = ?, restarted = 1, status = 2 WHERE id = ? AND character_id = ?',
        [$newPoints, $newPremium, '[]', '[]', (int)$progress['id'], $cid]);
    $pdo->commit();
    echo "Herói: {$heroes[0]['name']} (ID {$cid}); season: {$progress['identifier']}\n";
    echo "Resgates anteriores: {$claimedCount} → 0.\n";
    echo "Pontos: {$progress['season_points']} → {$newPoints}. Premium: " . ($newPremium ? 'ativo' : 'inativo') . ".\n";
    echo "Backup do progresso anterior: {$backup}\n";
    echo "Recarrega o jogo e abre a season para resgatar as recompensas desbloqueadas.\n";
    echo "Os objetos e moedas já recebidos foram mantidos. Não foram cobrados donuts.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
