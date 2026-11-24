<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\StoryDungeon;

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: {$message}\n"; }
function rejects(callable $fn, string $error): void {
    try { $fn(); } catch (GameError $e) { check($e->getMessage() === $error, $error); return; }
    throw new RuntimeException('Esperado erro: ' . $error);
}

$pdo = null;
try {
    $catalog = StoryDungeon::constants();
    $text = json_decode(gzuncompress(file_get_contents(dirname(__DIR__, 2) . '/server-laravel/public/assets/i18n/pt_BR/text.data')), true, 512, JSON_THROW_ON_ERROR);
    check(count($catalog['story_dungeons']) === 14, '14 histórias oficiais');
    $types = [];
    foreach ($catalog['story_dungeons'] as $index => $dungeon) {
        check(count($dungeon['step_index']) === 5, "História {$index}: cinco etapas");
        foreach ($dungeon['step_index'] as $step => $definition) {
            check($definition['reward_xp'] > 0 && ($definition['reward_item_identifier'] === 'random_epic'
                || isset($catalog['item_templates'][$definition['reward_item_identifier']])), "História {$index}/{$step}: prémios existentes");
            if ($step !== 5) {
                $types[(int)$definition['type']] = true;
                check(isset($text["story_dungeon/{$dungeon['identifier']}/{$step}/progress"])
                    && $definition['unlock_points_needed'] > 0, "História {$index}/{$step}: texto e meta existentes");
                check(StoryDungeon::skipCost(['step' => $step, 'points' => 0], $definition) === (int)$definition['skip_price']
                    && StoryDungeon::skipCost(['step' => $step, 'points' => $definition['unlock_points_needed']], $definition) === 0,
                    "História {$index}/{$step}: preço inicial e final");
            } else {
                check(StoryDungeon::npcProfile($definition)['hitpoints'] === (int)$definition['npc_stat_stamina'] * HeroZero\Fight::HP_SCALE,
                    "História {$index}: NPC usa atributos do catálogo");
            }
        }
    }
    $expected = [1, 4, 5, 6, 10, 11, 12, 14, 15, 16];
    $found = array_keys($types); sort($found);
    check($found === $expected, 'Todos os dez tipos de objetivo identificados');
    check(StoryDungeon::matchesQuest('quest_stage1_time4', '*stage1_')
        && !StoryDungeon::matchesQuest('quest_stage10_time4', '*stage1_')
        && StoryDungeon::matchesQuest('quest_stage4_time1', '*stage4'), 'Referências oficiais com e sem underscore');
    if (in_array('--offline', $argv, true)) { echo "PASS: validação offline do catálogo e fórmulas.\n"; exit(0); }

    $app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $hero = null;
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--hero=')) $hero = substr($arg, 7);
        else throw new InvalidArgumentException('Uso: php scripts/testing/test_story_dungeon.php [--offline | --hero="Nome do herói"]');
    }
    $pdo = Db::pdo();
    $row = $hero !== null ? Db::row('SELECT * FROM `character` WHERE name = ?', [$hero])
        : Db::row('SELECT * FROM `character` WHERE level >= 9 ORDER BY id LIMIT 1');
    if (!$row || (int)$row['level'] < 9) throw new RuntimeException('É necessário um herói existente de nível 9+. Use --hero="Nome".');
    $cid = (int)$row['id']; $uid = (int)$row['user_id'];
    echo "Integração MySQL: herói {$row['name']}; preparação, prémios e consumos apenas dentro de transação com rollback.\n";
    $pdo->beginTransaction();
    Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid]);
    $before = Db::row('SELECT story_dungeon_state, xp FROM `character` WHERE id = ?', [$cid]);
    Db::exec('UPDATE `character` SET story_dungeon_state = ?, active_quest_id = 0, active_duel_id = 0, active_worldboss_attack_id = 0 WHERE id = ?', ['{"active":null,"completed":[]}', $cid]);
    Db::exec('UPDATE `user` SET premium_currency = 100 WHERE id = ?', [$uid]);
    Character::load($cid)->ensureShopItems();
    Db::exec('UPDATE inventory SET bag_item1_id = 0 WHERE character_id = ?', [$cid]);
    StoryDungeon::start($cid, 1, 1);
    rejects(fn() => StoryDungeon::start($cid, 1, 2), 'errStartStoryDungeonStepAnotherActive');
    rejects(fn() => StoryDungeon::claim($cid, 1, 1, false), 'errClaimStoryDungeonRewardInvalidStatus');
    StoryDungeon::record($cid, StoryDungeon::TIME_QUEST, 5);
    $state = StoryDungeon::ensure($cid);
    $item = $state['active']['rewards']['item'];
    $xp = (int)Db::value('SELECT xp FROM `character` WHERE id = ?', [$cid]);
    $response = Live::attachStoryDungeonState(['character' => ['id' => $cid], 'items' => []], Character::load($cid));
    check($response['story_dungeon_step']['status'] === 3 && $response['story_dungeon_step']['points_collected'] === 5
        && in_array($item, array_map('intval', array_column($response['items'], 'id')), true), 'Progresso e preview chegam ao cliente');
    StoryDungeon::claim($cid, 1, 1, false);
    check((int)Db::value('SELECT xp FROM `character` WHERE id = ?', [$cid]) === $xp + 3000, 'Resgate entrega 3000 XP');
    check((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?', [$cid]) === $item, 'Resgate entrega preview à mochila');
    rejects(fn() => StoryDungeon::claim($cid, 1, 1, false), 'errClaimStoryDungeonRewardInvalidStatus');
    StoryDungeon::start($cid, 1, 2);
    StoryDungeon::finish($cid, 1, 2, true);
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?', [$uid]) === 91, 'Conclusão premium custa 9 donuts');
    StoryDungeon::claim($cid, 1, 2, true);
    check($pdo->inTransaction(), 'Operações preservam transação exterior');
    $pdo->rollBack();
    check(Db::row('SELECT story_dungeon_state, xp FROM `character` WHERE id = ?', [$cid]) === $before, 'Rollback restaura história e XP');
    echo "PASS: integração MySQL com rollback.\n";
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . get_class($e) . ' — ' . ($e instanceof PDOException ? 'MySQL indisponível ou operação SQL falhou (código ' . $e->getCode() . ')' : $e->getMessage()) . "\n");
    exit(1);
}
