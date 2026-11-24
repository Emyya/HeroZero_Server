<?php
declare(strict_types=1);

// Preparação persistente: define o total de pontos do passe atual, sem limpar resgates.
$usage = "Uso: php scripts/testing/set_season_points.php \"Herói\" PONTOS\n"
    . "PONTOS: inteiro entre 0 e 2147483647; substitui o total, não soma.\n"
    . "Mantém resgates e desbloqueios; temporadas expiradas avançam pelo relógio do jogo.\n";
if (count($argv) === 2 && in_array($argv[1], ['--help', '-h'], true)) {
    echo $usage;
    exit(0);
}
$name = trim($argv[1] ?? '');
$raw = $argv[2] ?? '';
if (count($argv) !== 3 || $name === '' || !ctype_digit($raw) || strlen($raw) > 10 || (int)$raw > 2147483647) {
    fwrite(STDERR, $usage);
    exit(1);
}
$pdo = null;
try {
    require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
    require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
    $app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo = HeroZero\Db::pdo();
    $pdo->beginTransaction();
    $heroes = HeroZero\Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo.');
    $cid = (int)$heroes[0]['id'];
    $progress = HeroZero\Season::ensureProgress($cid);
    HeroZero\Db::exec('UPDATE season_progress SET season_points = ? WHERE id = ? AND character_id = ?',
        [(int)$raw, (int)$progress['id'], $cid]);
    $pdo->commit();
    echo "Herói: {$heroes[0]['name']}; temporada: {$progress['identifier']}\n";
    echo "Pontos: {$progress['season_points']} → ".(int)$raw."\n";
    echo "Recarrega o jogo para atualizar o passe.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
