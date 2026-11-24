<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\StoryDungeon;

/** PDO em memória: queries desconhecidas falham, sem ligação à BD. */
final class StoryPDO extends PDO
{
    public array $char;
    public array $user = ['id' => 10, 'premium_currency' => 100];
    public array $items = [];
    public array $quests = [];
    public array $battles = [];
    public array $inventory;
    public bool $failSave = false;
    public int $locks = 0;
    private bool $active = false;
    private array $snapshots = [];
    private int $lastId = 100;
    public int $affected = 1;

    public function __construct()
    {
        $this->char = ['id' => 1, 'user_id' => 10, 'name' => 'Teste', 'level' => 9,
            'xp' => 20000, 'stat_points_available' => 0, 'max_quest_stage' => 1,
            'received_sidekick' => 1, 'story_dungeon_state' => '', 'quest_energy' => 100,
            'active_quest_id' => 0, 'active_duel_id' => 0, 'stat_base_stamina' => 1, 'stat_base_strength' => 1,
            'stat_base_critical_rating' => 1, 'stat_base_dodge_rating' => 1, 'game_currency' => 0];
        $this->inventory = ['character_id' => 1];
        for ($i = 1; $i <= 18; $i++) $this->inventory['bag_item' . $i . '_id'] = 0;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new StoryStatement($this, preg_replace('/\s+/', ' ', trim($query))); }
    public function inTransaction(): bool { return $this->active; }
    private function snapshot(): array { return [$this->char, $this->user, $this->items, $this->inventory, $this->quests, $this->battles]; }
    private function restore(array $s): void { [$this->char, $this->user, $this->items, $this->inventory, $this->quests, $this->battles] = $s; }
    public function beginTransaction(): bool { $this->snapshots['outer'] = $this->snapshot(); $this->active = true; return true; }
    public function commit(): bool { $this->active = false; return true; }
    public function rollBack(): bool { $this->restore($this->snapshots['outer']); $this->active = false; return true; }
    public function lastInsertId(?string $name = null): string|false { return (string)$this->lastId; }
    public function exec(string $statement): int|false
    {
        if (str_starts_with($statement, 'SAVEPOINT ')) $this->snapshots[substr($statement, 10)] = $this->snapshot();
        elseif (str_starts_with($statement, 'ROLLBACK TO SAVEPOINT ')) $this->restore($this->snapshots[substr($statement, 22)]);
        elseif (str_starts_with($statement, 'RELEASE SAVEPOINT ')) unset($this->snapshots[substr($statement, 18)]);
        else throw new RuntimeException('SQL inesperado: ' . $statement);
        return 0;
    }
    public function run(string $sql, array $p): array
    {
        $this->affected = 1;
        if ($sql === 'SELECT * FROM `character` WHERE id = ? FOR UPDATE') {
            $this->locks++;
            return (int)$p[0] === 1 ? [$this->char] : [];
        }
        if ($sql === 'SELECT * FROM `character` WHERE id = ?') return (int)$p[0] === 1 ? [$this->char] : [];
        if (str_starts_with($sql, 'SELECT * FROM `character` WHERE user_id = ?')) return (int)$p[0] === 10 ? [$this->char] : [];
        if ($sql === 'SELECT story_dungeon_state FROM `character` WHERE id = ?') return [['story_dungeon_state' => $this->char['story_dungeon_state']]];
        if (str_starts_with($sql, 'SELECT id FROM `user`') || str_starts_with($sql, 'SELECT * FROM `user`')) return [$this->user];
        if ($sql === 'UPDATE `character` SET story_dungeon_state = ? WHERE id = ?') {
            if ($this->failSave) throw new RuntimeException('Falha de escrita simulada');
            $this->char['story_dungeon_state'] = $p[0]; return [];
        }
        if (str_starts_with($sql, 'UPDATE `user` SET premium_currency = premium_currency - ?')) {
            if ($this->user['premium_currency'] < $p[2]) $this->affected = 0;
            else $this->user['premium_currency'] -= $p[0];
            return [];
        }
        if (str_starts_with($sql, 'INSERT INTO `items`')) {
            preg_match('/\(([^)]+)\) VALUES/', $sql, $m);
            $fields = array_map(static fn($v) => trim($v, ' `'), explode(',', $m[1]));
            $id = ++$this->lastId;
            $this->items[$id] = array_combine($fields, $p) + ['id' => $id];
            return [];
        }
        if (str_starts_with($sql, 'UPDATE items SET required_level = ?')) {
            $this->items[$p[2]]['required_level'] = $p[0]; $this->items[$p[2]]['charges'] = $p[1]; return [];
        }
        if (str_starts_with($sql, 'UPDATE items SET identifier = ?')) {
            $this->items[$p[2]]['identifier'] = $p[0]; $this->items[$p[2]]['type'] = $p[1]; return [];
        }
        if (str_starts_with($sql, 'SELECT id FROM items') || str_starts_with($sql, 'SELECT * FROM items WHERE id')) {
            return isset($this->items[$p[0]]) && (int)$this->items[$p[0]]['character_id'] === (int)$p[1] ? [$this->items[$p[0]]] : [];
        }
        if (str_starts_with($sql, 'DELETE FROM items')) { unset($this->items[$p[0]]); return []; }
        if (str_starts_with($sql, 'SELECT * FROM inventory WHERE character_id')) return [$this->inventory];
        if ($sql === 'SELECT * FROM `inventory` WHERE character_id = ?') return [$this->inventory];
        if ($sql === 'SELECT sidekick_id FROM `inventory` WHERE character_id = ?') return [['sidekick_id' => 0]];
        if (str_starts_with($sql, 'SELECT hr.id, hr.level FROM `hideout_rooms`')) return [];
        if (str_starts_with($sql, 'INSERT INTO battle ')) {
            $id = ++$this->lastId;
            $this->battles[$id] = array_combine(['ts_creation', 'profile_a_stats', 'profile_b_stats', 'winner', 'rounds'], $p) + ['id' => $id];
            return [];
        }
        if ($sql === 'SELECT * FROM battle WHERE id = ?') return isset($this->battles[$p[0]]) ? [$this->battles[$p[0]]] : [];
        if ($sql === 'SELECT winner FROM battle WHERE id = ?') return isset($this->battles[$p[0]]) ? [['winner' => $this->battles[$p[0]]['winner']]] : [];
        if (str_starts_with($sql, 'UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ?')) {
            $this->char['game_currency'] += $p[0]; $this->char['xp'] += $p[1]; $this->char['active_quest_id'] = 0; return [];
        }
        if (str_starts_with($sql, 'UPDATE `character` SET tutorial_flags = ?')) { $this->char['tutorial_flags'] = $p[0]; return []; }
        if (str_starts_with($sql, 'DELETE FROM `quests` WHERE character_id = ? AND stage = ?')) {
            $this->quests = array_filter($this->quests, static fn($q) => ($q['stage'] ?? 1) !== $p[1]); return [];
        }
        if (str_starts_with($sql, 'SELECT g.stat_quest_xp_reward_boost AS xp')) return [['xp' => 0, 'coins' => 0]];
        if (str_starts_with($sql, 'SELECT active_quest_booster_id, ts_active_quest_boost_expires')) return [['active_quest_booster_id' => '', 'ts_active_quest_boost_expires' => 0]];
        if ($sql === 'SELECT type FROM `quests` WHERE character_id = ? AND stage = ?') {
            return array_map(static fn($q) => ['type' => $q['type']], array_values(array_filter($this->quests, static fn($q) => ($q['stage'] ?? 1) === $p[1])));
        }
        if (str_starts_with($sql, 'INSERT INTO `quests`')) {
            $id = ++$this->lastId;
            $fields = ['character_id', 'identifier', 'type', 'stage', 'level', 'duration_type', 'duration_raw', 'duration', 'energy_cost', 'fight_difficulty', 'fight_npc_identifier', 'rewards'];
            $this->quests[$id] = array_combine($fields, $p) + ['id' => $id, 'status' => 1, 'fight_battle_id' => 0]; return [];
        }

        if (str_starts_with($sql, 'UPDATE inventory SET `bag_item')) {
            preg_match('/`(bag_item\d+_id)`/', $sql, $m); $this->inventory[$m[1]] = $p[0]; return [];
        }
        if ($sql === 'UPDATE `character` SET xp = xp + ? WHERE id = ?') { $this->char['xp'] += $p[0]; return []; }
        if (str_starts_with($sql, 'UPDATE `character` SET level = ?')) {
            $this->char['level'] = $p[0]; $this->char['score_level'] = $p[1]; $this->char['stat_points_available'] += $p[2]; return [];
        }
        if ($sql === 'UPDATE `character` SET max_quest_stage = ? WHERE id = ?') { $this->char['max_quest_stage'] = $p[0]; return []; }
        if ($sql === 'SELECT id, identifier, rewards FROM quests WHERE character_id = ? AND status = 1') return array_values(array_filter($this->quests, static fn($q) => $q['status'] === 1));
        if (str_starts_with($sql, 'UPDATE quests SET rewards = ?')) { $this->quests[$p[1]]['rewards'] = $p[0]; return []; }
        if ($sql === 'SELECT * FROM `quests` WHERE id = ? AND character_id = ?') return isset($this->quests[$p[0]]) && (int)$p[1] === 1 ? [$this->quests[$p[0]]] : [];
        if ($sql === 'UPDATE `quests` SET status = 2, ts_complete = ? WHERE id = ?') {
            $this->quests[$p[1]]['status'] = 2; $this->quests[$p[1]]['ts_complete'] = $p[0]; return [];
        }
        if (str_starts_with($sql, 'UPDATE `character` SET active_quest_id = ?, quest_energy')) {
            $this->char['active_quest_id'] = $p[0]; $this->char['quest_energy'] -= $p[1]; return [];
        }
        if (str_starts_with($sql, 'UPDATE herobook_objectives SET')) return [];
        throw new RuntimeException('SQL inesperado: ' . $sql);
    }
}
final class StoryStatement extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;
    public function __construct(private StoryPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->db->run($this->sql, $params ?? []); $this->affected = $this->db->affected; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { $r = array_shift($this->rows); return $r ? array_values($r)[$column] : false; }
    public function rowCount(): int { return $this->affected; }
}
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: {$message}\n"; }
function rejects(callable $fn, string $error): void {
    try { $fn(); } catch (GameError $e) { check($e->getMessage() === $error, $error); return; }
    throw new RuntimeException('Esperado erro: ' . $error);
}
function active(StoryPDO $db): array { return StoryDungeon::decode($db->char)['active']; }
function fixture(StoryPDO $db, int $index, int $step, int $points = 0, int $status = 2): void {
    $item = 501;
    $db->items[$item] = ['id' => $item, 'character_id' => 1, 'identifier' => 'cape_captain_planet', 'type' => 2, 'quality' => 3];
    $db->char['story_dungeon_state'] = json_encode(['active' => ['index' => $index, 'step' => $step, 'status' => $status,
        'points' => $points, 'ts_complete' => 0, 'ts_last_attack' => 0, 'battle_ids' => [], 'battle_id' => 0,
        'rewards' => ['xp' => 3000, 'item' => $item]], 'completed' => []]);
}

try {
    $db = new StoryPDO();
    (new ReflectionProperty(Db::class, 'pdo'))->setValue(null, $db);
    rejects(fn() => StoryDungeon::start(1, 1, 2), 'errStartStoryDungeonStepLastStepNotFinished');
    $db->char['level'] = 8;
    rejects(fn() => StoryDungeon::start(1, 1, 1), 'errStartStoryDungeonStepInvalidLevel');
    $db->char['level'] = 9;
    StoryDungeon::start(1, 1, 1);
    $preview = active($db)['rewards']['item'];
    check(isset($db->items[$preview]) && $db->items[$preview]['quality'] === 3 && $db->items[$preview]['required_level'] === 9,
        'Iniciar cria preview épico utilizável pelo herói');
    check(!in_array($preview, $db->inventory, true), 'Preview ainda não é entregue à mochila');
    rejects(fn() => StoryDungeon::start(1, 1, 2), 'errStartStoryDungeonStepAnotherActive');
    rejects(fn() => StoryDungeon::claim(1, 1, 1, false), 'errClaimStoryDungeonRewardInvalidStatus');
    StoryDungeon::record(1, StoryDungeon::DUEL);
    check(active($db)['points'] === 0, 'Atividade incompatível não avança');
    StoryDungeon::record(1, StoryDungeon::TIME_QUEST, 4);
    check(active($db)['points'] === 4 && active($db)['status'] === 2, 'Progresso persistente 4/5');
    check(StoryDungeon::skipCost(active($db), StoryDungeon::definition(1, 1)) === 1, 'Preço proporcional aos pontos restantes');
    StoryDungeon::record(1, StoryDungeon::TIME_QUEST, 10);
    check(active($db)['points'] === 5 && active($db)['status'] === 3, 'Conclusão usa status 3 e limita à meta');
    $response = Live::attachStoryDungeonState(['character' => ['id' => 1], 'items' => []], Character::load(1));
    check($response['story_dungeon_step']['points_collected'] === 5 && $response['story_dungeon_step']['status'] === 3
        && (int)$response['items'][0]['id'] === $preview, 'Resposta parcial inclui progresso e item referenciado');
    $xp = $db->char['xp'];
    foreach ($db->inventory as $k => &$v) if (str_starts_with($k, 'bag_item')) $v = 99;
    unset($v);
    rejects(fn() => StoryDungeon::claim(1, 1, 1, false), 'errInventoryNoEmptySlot');
    check($db->char['xp'] === $xp && active($db)['status'] === 3, 'Mochila cheia não consome resgate nem entrega XP');
    $db->inventory['bag_item1_id'] = 0;
    StoryDungeon::claim(1, 1, 1, false);
    check($db->char['xp'] === $xp + 3000 && $db->inventory['bag_item1_id'] === $preview && active($db)['status'] === 4,
        'Entrega XP e item, marca status 4');
    rejects(fn() => StoryDungeon::claim(1, 1, 1, false), 'errClaimStoryDungeonRewardInvalidStatus');
    check($db->char['xp'] === $xp + 3000, 'Repetição não duplica XP');
    StoryDungeon::start(1, 1, 2);
    check(active($db)['step'] === 2, 'Resgate permite iniciar etapa seguinte');
    rejects(fn() => StoryDungeon::finish(1, 1, 1, true), 'errStartStoryDungeonBattleInvalidStoryDungeon');
    $db->user['premium_currency'] = 0;
    $before = $db->char['story_dungeon_state'];
    rejects(fn() => StoryDungeon::finish(1, 1, 2, true), 'errRemovePremiumCurrencyNotEnough');
    check($db->char['story_dungeon_state'] === $before, 'Saldo insuficiente não altera etapa');
    $db->user['premium_currency'] = 100;
    StoryDungeon::finish(1, 1, 2, true);
    check($db->user['premium_currency'] === 91 && active($db)['status'] === 3, 'Conclusão cobra preço oficial de 9 donuts');
    $xp = $db->char['xp']; $item = active($db)['rewards']['item'];
    StoryDungeon::claim(1, 1, 2, true);
    check($db->char['xp'] === $xp + 3000 && !isset($db->items[$item]), 'Descartar equipamento mantém XP');
    fixture($db, 1, 2);
    StoryDungeon::record(1, StoryDungeon::DUEL);
    check(active($db)['points'] === 0, 'Duelos disputados não contam como duelos vencidos');
    StoryDungeon::record(1, StoryDungeon::DUEL_WON);
    check(active($db)['points'] === 1, 'Vitória avança meta de duelos vencidos');
    fixture($db, 1, 4);
    StoryDungeon::record(1, StoryDungeon::QUEST_ITEM, 1, 'storydungeon2_2_apple');
    check(active($db)['points'] === 0, 'Objeto de outra história não conta');
    StoryDungeon::record(1, StoryDungeon::QUEST_ITEM, 1, 'storydungeon1_4_laundry');
    check(active($db)['points'] === 1, 'Objeto certo avança meta');
    check(StoryDungeon::questReward(1, 'quest_stage1_time1', 0.199999) === ['story_dungeon_item' => 'storydungeon1_4_laundry']
        && StoryDungeon::questReward(1, 'quest_stage1_time1', 0.2) === []
        && StoryDungeon::questReward(1, 'quest_stage2_time1', 0) === [], 'Sorteio respeita chance e zona oficiais');
    fixture($db, 2, 3);
    $db->quests[7] = ['id' => 7, 'character_id' => 1, 'status' => 1, 'duration' => 60, 'energy_cost' => 8];
    Character::load(1)->startQuest(7);
    check(active($db)['points'] === 8 && $db->char['quest_energy'] === 92, 'Iniciar missão debita energia e avança história na mesma transação');
    Character::load(1)->startQuest(7);
    check(active($db)['points'] === 8 && $db->char['quest_energy'] === 92, 'Retry não repete energia nem progresso');
    $db->char['active_quest_id'] = 0; $db->quests[7]['status'] = 1;
    $before = $db->char; $db->failSave = true;
    try { Character::load(1)->startQuest(7); throw new LogicException('Devia falhar'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'Falha de escrita simulada', 'Falha injetada no contador'); }
    $db->failSave = false;
    check($db->char === $before && $db->quests[7]['status'] === 1, 'Falha no progresso reverte consumo de energia e início da missão');
    fixture($db, 1, 1);
    $db->beginTransaction();
    StoryDungeon::record(1, StoryDungeon::TIME_QUEST, 2);
    check($db->inTransaction() && active($db)['points'] === 2, 'Savepoint preserva transação exterior');
    $db->rollBack();
    check(active($db)['points'] === 0, 'Rollback exterior reverte progresso');
    fixture($db, 1, 1);
    $db->char['active_quest_id'] = 8;
    $db->quests[8] = ['id' => 8, 'character_id' => 1, 'stage' => 1, 'status' => 4, 'type' => 1,
        'fight_battle_id' => 0, 'rewards' => '{"coins":25,"xp":100}'];
    Character::load(1)->claimQuestRewards();
    check(active($db)['points'] === 1 && $db->char['active_quest_id'] === 0, 'Resgate real da missão de tempo avança história');
    rejects(fn() => Character::load(1)->claimQuestRewards(), 'errClaimQuestRewardsNoActiveQuest');
    check(active($db)['points'] === 1, 'Resgate repetido da missão não avança contador');
    fixture($db, 1, 4);
    $db->char['active_quest_id'] = 9;
    $db->quests[9] = ['id' => 9, 'character_id' => 1, 'stage' => 1, 'status' => 4, 'type' => 1,
        'fight_battle_id' => 0, 'rewards' => '{"coins":25,"xp":100,"story_dungeon_item":"storydungeon1_4_laundry"}'];
    Character::load(1)->claimQuestRewards();
    check(active($db)['points'] === 1, 'Resgate real da missão entrega objeto da história');
    fixture($db, 1, 5);
    StoryDungeon::finish(1, 1, 5, false);
    $battle = active($db)['battle_id'];
    check(active($db)['status'] === 2 && $db->battles[$battle]['winner'] === 'b', 'NPC usa combate real e permite derrota');
    $response = Live::attachStoryDungeonState(['character' => ['id' => 1]], Character::load(1));
    check($response['character']['active_story_dungeon_attack_id'] === $battle && (int)$response['battle']['id'] === $battle
        && $response['story_dungeon_step']['battle_ids'] === json_encode([$battle]), 'Resposta e login incluem batalha referenciada');
    rejects(fn() => StoryDungeon::finish(1, 1, 5, false), 'errClaimStoryDungeonRewardInvalidStatus');
    $xp = $db->char['xp'];
    StoryDungeon::claim(1, 1, 5, false);
    check(active($db)['battle_id'] === 0 && $db->char['xp'] === $xp, 'Fechar derrota não entrega prémio');
    rejects(fn() => StoryDungeon::finish(1, 1, 5, false), 'errStartStoryDungeonBattleActiveCooldown');
    $balance = $db->user['premium_currency'];
    StoryDungeon::finish(1, 1, 5, false, true);
    check($db->user['premium_currency'] === $balance - 2, 'Saltar cooldown cobra 2 donuts oficiais');
    StoryDungeon::claim(1, 1, 5, false);
    StoryDungeon::finish(1, 1, 5, true);
    check(active($db)['status'] === 3 && $db->battles[active($db)['battle_id']]['winner'] === 'a', 'Conclusão premium do chefe produz vitória e batalha válidas');
    fixture($db, 1, 3);
    StoryDungeon::record(1, StoryDungeon::SHOP_REFRESH, 3);
    check(active($db)['status'] === 3, 'Renovações do brechó concluem meta');
    fixture($db, 3, 2);
    StoryDungeon::record(1, StoryDungeon::WASH, 10);
    check(active($db)['status'] === 3, 'Lavagens concluem meta');
    fixture($db, 3, 4);
    StoryDungeon::record(1, StoryDungeon::TRAINING_UNITS, 4);
    check(active($db)['points'] === 4, 'Treinos contam unidades consumidas');
    fixture($db, 6, 3);
    StoryDungeon::record(1, StoryDungeon::FIGHT_QUEST);
    check(active($db)['points'] === 1, 'Missões de luta contam no tipo correto');
    $db->char['story_dungeon_state'] = '{"active":{"index":1,"step":1,"status":4},"completed":[]}';
    StoryDungeon::ensure(1);
    check(active($db)['status'] === 3 && isset(active($db)['rewards']['item']), 'Etapa antiga concluída recebe estado e preview válidos');
    check($db->locks > 0 && !$db->inTransaction(), 'Operações usaram locks e fecharam transações');
    echo "PASS: testes com PDO simulado (não substituem integração MySQL).\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1); }
