<?php
declare(strict_types=1);

/**
 * Distribuicao inicial de trofeus pelas guildas ("server criado, dropam-se X
 * trofeus pelas teams aleatoriamente" — docs/trofeus/implementacao.md).
 *
 * Regras:
 *  - N ids aleatorios do registo oficial (sem repeticao, um por slot);
 *  - cada guilda recebe no MAXIMO os slots desbloqueados pela sua expansao;
 *  - guildas sem guilda... obviamente so guildas existentes recebem;
 *  - atualiza artifacts_owned_current/max por guilda.
 *
 * Uso:  php tools/distribute_trophies.php [N]      (default: 20)
 */

require __DIR__ . '/../server-laravel/vendor/autoload.php';
$app = require __DIR__ . '/../server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\GuildArtifacts;

$n = (int)($argv[1] ?? 20);
if ($n <= 0) {
    fwrite(STDERR, "N tem de ser > 0 (php tools/distribute_trophies.php 20)\n");
    exit(1);
}

$registry = GuildArtifacts::registry();
if ($registry === []) {
    fwrite(STDERR, "Registo de artefactos vazio — constants_json.data nao encontrado.\n");
    exit(1);
}

$guilds = Db::rows('SELECT * FROM `guild` ORDER BY id');
if ($guilds === []) {
    fwrite(STDERR, "Sem guildas no servidor.\n");
    exit(1);
}

// Capacidade total: soma dos slots; se N exceder, limitamos ao que cabe.
$caps = [];
foreach ($guilds as $g) {
    $caps[(int)$g['id']] = max(0, GuildArtifacts::unlockedSlots($g) - count(GuildArtifacts::ownedIds($g)));
}
$totalCap = array_sum($caps);
$n = min($n, $totalCap, GuildArtifacts::MAX_SERVER_ARTIFACTS);
if ($n <= 0) {
    echo "Todos os slots ja estao ocupados (capacidade livre: {$totalCap}). Nada a fazer.\n";
    exit(0);
}

echo "Registo: " . count($registry) . " trofeus | guildas: " . count($guilds)
   . " | capacidade livre total: {$totalCap} | a distribuir: {$n}\n";

// Escolhe N guildas aleatoriamente (ponderado pelos slots livres: entra uma vez
// por slot na lista) e atribui 1 trofeu por escolha.
$bag = [];
foreach ($caps as $gid => $free) {
    for ($i = 0; $i < $free; $i++) {
        $bag[] = $gid;
    }
}

$available = GuildArtifacts::availableIds();
shuffle($available);
shuffle($bag);

$given = [];
for ($i = 0; $i < $n; $i++) {
    $gid = $bag[$i];
    $artifactId = (int)array_pop($available); // ids unicos em todo o servidor
    if ($artifactId <= 0) {
        break;
    }
    GuildArtifacts::grant($gid, $artifactId);
    $given[$gid][] = $artifactId;
}

foreach ($given as $gid => $ids) {
    $name = (string)(Db::value('SELECT name FROM `guild` WHERE id = ?', [$gid]) ?? "?");
    echo "  {$name}: +" . count($ids) . " → [" . implode(',', $ids) . "]\n";
}
echo "Concluido: " . array_sum(array_map('count', $given)) . " trofeus distribuidos. "
   . "A circular agora: " . GuildArtifacts::circulatingCount() . "/" . GuildArtifacts::MAX_SERVER_ARTIFACTS . "\n";
