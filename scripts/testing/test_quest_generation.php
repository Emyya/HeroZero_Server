<?php
declare(strict_types=1);

// Offline + PDO simulado: não lê nem altera a BD do jogo.
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameTime;
use HeroZero\QuestRewards as Q;

function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

final class QuestTestPDO extends PDO
{
    public array $quests = [];
    public int $stat = 50;
    public string $personal = 'booster_quest3';
    public string $team = 'guild_booster_quest2';
    public int $expires = 0;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new QuestTestStatement($this, preg_replace('/\s+/', ' ', trim($query))); }
    public function run(string $sql, array $p): array
    {
        if (str_contains($sql, "table_name='herocon_rewards'")) return [[0]];
        if ($sql === 'SELECT * FROM `character` WHERE id = ?') return [['id' => 1, 'user_id' => 10, 'guild_id' => 1, 'level' => 300]];
        if ($sql === 'SELECT * FROM `user` WHERE id = ?') return [['id' => 10]];
        if (str_contains($sql, 'g.stat_quest_xp_reward_boost AS xp')) return [['xp' => $this->stat, 'coins' => 25]];
        if (str_starts_with($sql, 'SELECT active_quest_booster_id,')) return [['active_quest_booster_id' => $this->personal, 'ts_active_quest_boost_expires' => $this->expires]];
        if (str_starts_with($sql, 'SELECT active_quest_booster_id AS id')) return [['id' => $this->team, 'expires' => $this->expires]];
        if (str_starts_with($sql, 'SELECT type FROM `quests`')) return array_map(static fn($q) => ['type' => $q['type']], array_values(array_filter($this->quests, static fn($q) => $q['stage'] === $p[1])));
        if (str_starts_with($sql, 'SELECT * FROM event_quest_progress')) return [];
        if (str_starts_with($sql, 'SELECT story_dungeon_state')) return [['story_dungeon_state' => '']];
        if (str_starts_with($sql, 'INSERT INTO `quests`')) {
            $this->quests[] = ['type' => $p[2], 'stage' => $p[3], 'raw' => $p[6], 'duration' => $p[7], 'energy' => $p[8], 'difficulty' => $p[9], 'npc' => $p[10], 'rewards' => json_decode($p[11], true)];
            return [];
        }
        throw new RuntimeException('SQL inesperado: ' . $sql);
    }
}
final class QuestTestStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private QuestTestPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->db->run($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { $row=array_shift($this->rows); return $row ? array_values($row)[$column] : false; }
    public function rowCount(): int { return 1; }
}

try {
    foreach ([0 => 900, 1 => 918, 25 => 1350, 50 => 1800] as $stat => $xp) verify(Q::missionXp(600, $stat, false, 1) === $xp, 'Escala guilda');
    verify(Q::missionXp(600, 50, true, 1) === 2520, 'Fator focada 1,4');
    foreach ([Q::DIFF_NONE => 1.0, Q::DIFF_EASY => 1.0, Q::DIFF_MEDIUM => 1.2, Q::DIFF_HARD => 1.3] as $difficulty => $factor) {
        verify(Q::missionXp(600, 50, false, 1, $difficulty) === (int)round(1800 * $factor), 'XP equilibrado por dificuldade');
        verify(Q::missionXp(600, 50, true, 1, $difficulty) === (int)round(2520 * $factor), 'XP focado por dificuldade');
        verify(Q::expectedXpRange(50, 10, true, $difficulty) === [(int)round(2520 * .8 * $factor), (int)round(2520 * 1.25 * $factor)], 'Limites incluem dificuldade');
    }
    $difficulties = [1 => 0, 2 => 0, 3 => 0];
    for ($i = 0; $i < 20000; $i++) $difficulties[Q::rollDifficulty()]++;
    foreach ([1 => .5, 2 => .3, 3 => .2] as $difficulty => $probability) verify(abs($difficulties[$difficulty] / 20000 - $probability) < .025, 'Distribuição dificuldade 50/30/20');
    echo "OK dificuldade: fácil ×1, média ×1.2, difícil ×1.3; distribuição 50/30/20.\n";

    verify(Q::boostedCosts(600, 10, 50, 20) === ['energy' => 5, 'duration' => 180], 'Pessoal + equipa, energia só pessoal');
    verify(Q::boostedCosts(600, 10, 25, 10) === ['energy' => 8, 'duration' => 390], 'Arredondamento 25%');
    verify(Q::poolPlan([1, 1, 2], 0) === [], 'Pool cheio não acrescenta vagas');
    foreach ([[1, 1], [2], [1]] as $existing) {
        $plan = Q::poolPlan($existing, 3 - count($existing));
        $types = array_count_values(array_merge($existing, array_column($plan, 'type')));
        verify($types[1] === 2 && $types[2] === 1, 'Reposição respeita missão mantida');
    }
    $buckets = [0, 0, 0]; $sum = 0; $focusedFights = 0;
    for ($i = 0; $i < 20000; $i++) {
        $m = Q::rollXpMultiplier();
        verify($m >= .8 && $m <= 1.25, 'Limites variação');
        $buckets[$m < .9 ? 1 : ($m > 1.1 ? 2 : 0)]++; $sum += $m;
        $plan = Q::poolPlan([], 3); $types = array_count_values(array_column($plan, 'type'));
        verify($types[1] === 2 && $types[2] === 1, 'Composição 2 tempo + 1 luta');
        $focused = array_values(array_filter($plan, static fn($s) => $s['focused']));
        verify(count($focused) === 1, 'Uma focada por pool completo');
        if ($focused[0]['type'] === 2) $focusedFights++;
    }
    foreach ([.80, .15, .05] as $i => $target) verify(abs($buckets[$i] / 20000 - $target) < .025, 'Distribuição 80/15/5');
    verify(abs($focusedFights / 20000 - .65) < .025, 'Luta focada 65%');
    verify(abs($sum / 20000 - .98625) < .01, 'Média multiplicador');
    echo 'OK offline: fórmula, energéticos, reposição e 20 000 sorteios; luta focada ' . round(100 * $focusedFights / 20000, 2) . "%\n";

    $bandCounts = [];
    for ($roll = 1; $roll <= 100; $roll++) {
        $band = Q::fightEnergyBand($roll);
        $key = implode('-', $band);
        $bandCounts[$key] = ($bandCounts[$key] ?? 0) + 1;
    }
    verify($bandCounts === ['3-6' => 50, '1-3' => 30, '6-9' => 20], 'Pesos exatos das faixas 50/30/20');
    $energyCounts = array_fill(1, 9, 0);
    for ($i = 0; $i < 20000; $i++) {
        $minutes = Q::rollFightDurationMinutes();
        verify($minutes >= 2 && $minutes <= 18 && $minutes % 2 === 0, 'Combate: base 2–18, energia inteira a 50%');
        $costs = Q::boostedCosts($minutes * 60, $minutes, 50, 20);
        verify($costs['energy'] === intdiv($minutes, 2), 'Desconto pessoal aplicado uma vez');
        $energyCounts[$costs['energy']]++;
    }
    // 3 e 6 pertencem a duas faixas: verificar a distribuição final com essa soma.
    foreach ([1 => .1, 2 => .1, 3 => .225, 4 => .125, 5 => .125, 6 => .175, 7 => .05, 8 => .05, 9 => .05] as $energy => $probability) {
        verify(abs($energyCounts[$energy] / 20000 - $probability) < .02, 'Distribuição final de energia de combate');
    }
    echo "OK combate: faixas 50/30/20 e 20 000 custos finais com energético pessoal 50%.\n";

    $db = new QuestTestPDO();
    (new ReflectionProperty(Db::class, 'pdo'))->setValue(null, $db);
    $insert = new ReflectionMethod(Character::class, 'insertQuestPool');
    foreach ([25, 50] as $stat) foreach (['booster_quest2' => 25, 'booster_quest3' => 50] as $personal => $p) foreach (['' => 0, 'guild_booster_quest1' => 10, 'guild_booster_quest2' => 20] as $team => $t) {
        $db->quests = []; $db->stat = $stat; $db->personal = $personal; $db->team = $team;
        $insert->invoke(null, 1, 300, 3, 1);
        verify(count($db->quests) === 3, 'Inserção três missões');
        foreach ($db->quests as $q) {
            $costs = Q::boostedCosts($q['raw'], intdiv($q['raw'], 60), $p, $t);
            verify($q['duration'] === $costs['duration'] && $q['energy'] === $costs['energy'], 'Custos persistidos');
            verify(($q['type'] === 2) === ($q['npc'] !== '') && ($q['type'] === 2) === ($q['difficulty'] > 0), 'Luta recebe NPC e dificuldade');
            $xp = $q['rewards']['xp'];
            verify($xp >= Q::missionXp($q['raw'], $stat, false, .8, $q['difficulty']) && $xp <= Q::missionXp($q['raw'], $stat, true, 1.25, $q['difficulty']), 'Prémio persistido dentro do intervalo');
        }
        $db->quests = [array_values(array_filter($db->quests, static fn($q) => $q['type'] === 2))[0]];
        $kept = $db->quests[0]; $insert->invoke(null, 1, 300, 2, 1);
        verify($db->quests[0] === $kept, 'Preserva luta existente');
        verify(array_count_values(array_column($db->quests, 'type')) === [2 => 1, 1 => 2], 'Completa apenas vagas de tempo');
    }
    $db->quests = []; $db->expires = GameTime::now() - 1;
    $insert->invoke(null, 1, 300, 3, 1);
    foreach ($db->quests as $q) verify($q['duration'] === $q['raw'] && $q['energy'] === intdiv($q['raw'], 60), 'Energéticos expirados ignorados');
    echo "OK PDO simulado: geração real, 12 combinações, prémios, missões mantidas e expiração. Sem alterações na BD.\n";
    // Opcional: integra com MySQL e faz rollback de toda a renovação.
    if (in_array('--mysql', $argv, true)) {
        $hero = null;
        foreach ($argv as $arg) if (str_starts_with($arg, '--hero=')) $hero = substr($arg, 7);
        if (!$hero) throw new RuntimeException('Usar --mysql --hero=NomeDoHerói. Renova missões apenas dentro de uma transação revertida.');
        (new ReflectionProperty(Db::class, 'pdo'))->setValue(null, null);
        $app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $row = Db::row('SELECT * FROM `character` WHERE name = ? FOR UPDATE', [$hero]);
            verify($row !== null, 'Herói não encontrado');
            $cid = (int)$row['id'];
            // Reproduz o HAR: 14 zonas desbloqueadas, seleção numa zona inferior.
            // Toda a preparação é revertida no finally, incluindo a missão ativa original.
            Db::exec('UPDATE `character` SET max_quest_stage=14,current_quest_stage=14,active_quest_id=0 WHERE id=?', [$cid]);
            Character::load($cid)->regenerateQuests(true);
            $active = Db::row('SELECT * FROM quests WHERE character_id=? AND stage=14 ORDER BY id LIMIT 1', [$cid]);
            verify($active !== null, 'Missão para preservar');
            Db::exec('UPDATE quests SET status=2 WHERE id=?', [(int)$active['id']]);
            Db::exec('UPDATE `character` SET active_quest_id=? WHERE id=?', [(int)$active['id'], $cid]);
            $active = Db::row('SELECT * FROM quests WHERE id=?', [(int)$active['id']]);
            foreach ([3, 8, 14] as $selected) {
                Db::exec('UPDATE `character` SET current_quest_stage=? WHERE id=?', [$selected, $cid]);
                Character::load($cid)->regenerateQuests(true);
                $rows = Db::rows('SELECT * FROM quests WHERE character_id=? ORDER BY stage,id', [$cid]);
                $stages = [];
                foreach ($rows as $q) $stages[(int)$q['stage']][] = (int)$q['type'];
                verify(array_keys($stages) === range(1, 14), 'Todas as 14 zonas, seleção na zona ' . $selected);
                verify(count($rows) === 42, '42 missões incluindo a ativa preservada');
                foreach ($stages as $types) {
                    $counts = array_count_values($types);
                    verify(count($types) === 3 && ($counts[1] ?? 0) === 2 && ($counts[2] ?? 0) === 1, 'Composição MySQL por zona');
                }
                verify(Db::row('SELECT * FROM quests WHERE id=?', [(int)$active['id']]) === $active, 'Missão ativa e prémios preservados');
            }
            Db::exec('UPDATE `character` SET current_quest_stage=3 WHERE id=?', [$cid]);
            $otherZones = Db::rows('SELECT * FROM quests WHERE character_id=? AND stage<>3 ORDER BY id', [$cid]);
            $oldZoneIds = array_column(Db::rows('SELECT id FROM quests WHERE character_id=? AND stage=3', [$cid]), 'id');
            Character::load($cid)->regenerateQuests(false);
            verify(Db::rows('SELECT * FROM quests WHERE character_id=? AND stage<>3 ORDER BY id', [$cid]) === $otherZones, 'Renovação local preserva outras zonas');
            $newZoneIds = array_column(Db::rows('SELECT id FROM quests WHERE character_id=? AND stage=3', [$cid]), 'id');
            verify(count($newZoneIds) === 3 && !array_intersect($oldZoneIds, $newZoneIds), 'Renovação local troca apenas o pool selecionado');
            echo "OK MySQL: 14 zonas com seleção em 3/8/14, missão ativa preservada e renovação local isolada; rollback obrigatório.\n";
        } finally { $pdo->rollBack(); }
    }

} catch (Throwable $e) { fwrite(STDERR, 'FALHOU: ' . $e->getMessage() . "\n"); exit(1); }
