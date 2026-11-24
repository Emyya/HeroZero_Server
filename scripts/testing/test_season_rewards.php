<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\Season;

function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: {$label}\n";
}
function reject(callable $fn, string $error): void {
    try { $fn(); } catch (GameError $e) {
        check($e->getMessage() === $error, $error);
        return;
    }
    throw new RuntimeException('Pedido devia falhar: ' . $error);
}

// Parte sem BD: atributos não nulos e soma coerente em cada raridade/nível.
$statsMethod = new ReflectionMethod(Season::class, 'improvementStats');
foreach ([1, 50, 335] as $level) {
    foreach (range(1, 4) as $quality) {
        $stats = $statsMethod->invoke(null, $level, $quality, 10);
        $budget = max(4, (int)ceil($level * [1 => 0.5, 2 => 0.65, 3 => 0.8, 4 => 1.2][$quality]));
        check(count($stats) === 4 && min($stats) >= 1 && array_sum($stats) === $budget,
            "Atributos da gema: nível {$level}, qualidade {$quality}");
    }
}
if (($argv[1] ?? '') === '--stats-only') exit(0);

$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    $hero = $argv[1] ?? null;
    $cid = $hero !== null ? (int)Db::value('SELECT id FROM `character` WHERE name = ?', [$hero])
        : (int)Db::value('SELECT i.character_id FROM inventory i JOIN `character` c ON c.id = i.character_id ORDER BY c.level DESC LIMIT 1');
    if (!$cid) throw new RuntimeException('Necessário um personagem com inventário.');
    Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid]);
    // Estes IDs e quantidades pertencem ao catálogo gods_1. Calendário só nesta transacção.
    Db::exec('UPDATE season_calendar SET ts_anchor=? WHERE id=1', [HeroZero\GameTime::now()]);
    $progress = Season::ensureProgress($cid);
    Db::exec('UPDATE season_progress SET season_points = 1000, premium_unlocked = 1,
        claimed_rewards = ?, unlocked_rewards = ? WHERE character_id = ?', ['[]', '[]', $cid]);
    $beforeType3 = (int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]);
    $beforeDonuts = (int)Db::value('SELECT premium_currency FROM `user` WHERE id = (SELECT user_id FROM `character` WHERE id = ?)', [$cid]);
    Season::claimReward($cid, 14, false); // HAR debug_pontos_stats_season: tipo 3, 10 pontos.
    check((int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]) === $beforeType3 + 10,
        'Tipo 3 entrega 10 pontos gratuitos no contador de atributos');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = (SELECT user_id FROM `character` WHERE id = ?)', [$cid]) === $beforeDonuts,
        'Tipo 3 não entrega donuts');
    reject(fn() => Season::claimReward($cid, 14, false), 'errClaimSeasonRewardAlreadyClaimed');
    $beforeCoins = (int)Db::value('SELECT game_currency FROM `character` WHERE id = ?', [$cid]);
    Character::load($cid)->improveStat(1, 1);
    check((int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]) === $beforeType3 + 9,
        'Botão de atributos consome um dos pontos da season');
    check((int)Db::value('SELECT game_currency FROM `character` WHERE id = ?', [$cid]) === $beforeCoins,
        'Gastar ponto gratuito não cobra moedas');
    $before = (int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]);
    Season::claimReward($cid, 53, false); // tipo 3: 12 pontos livres, tier gratuito.
    check((int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]) === $before + 12,
        'Resgate entrega 12 pontos livres');
    reject(fn() => Season::claimReward($cid, 53, false), 'errClaimSeasonRewardAlreadyClaimed');
    check((int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]) === $before + 12,
        'Repetir resgate não duplica pontos');

    // Equipamento recebe borda +15; a recompensa 9 é munição tipo 8.
    foreach ([1, 6, 9, 16, 21, 30, 37, 46] as $rewardId) {
        Db::exec('UPDATE inventory SET bag_item1_id = 0 WHERE character_id = ?', [$cid]);
        Season::claimReward($cid, $rewardId, false);
        $equipmentId = (int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?', [$cid]);
        if ($rewardId === 9) {
            $missile = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [$equipmentId, $cid]);
            check((int)$missile['type'] === 8 && (int)$missile['charges'] === HeroZero\Missiles::PACKAGE_CHARGES,
                'Recompensa 9 entrega um pacote de arremesso');
            check((int)$missile['ts_availability_start'] === 0 && (int)$missile['ts_availability_end'] === 0
                && (int)$missile['stat_weapon_damage'] === 0 && (int)$missile['stat_strength'] === 0,
                'Munição não recebe atributos nem modificação de arma');
            reject(fn() => Season::claimReward($cid, 9, false), 'errClaimSeasonRewardAlreadyClaimed');
            continue;
        }

        check((int)Db::value('SELECT ts_availability_start FROM items WHERE id = ? AND character_id = ?', [$equipmentId, $cid]) === 15,
            "Equipamento da recompensa {$rewardId} tem borda dourada (+15)");
        check((int)Db::value('SELECT ts_availability_end FROM items WHERE id = ? AND character_id = ?', [$equipmentId, $cid]) === 1,
            "Equipamento da recompensa {$rewardId} tem espaço de modificação vazio");
        $equipment = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [$equipmentId, $cid]);
        $equipmentStats = array_map(static fn(string $key): int => (int)$equipment[$key],
            ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating']);
        $level = (int)$equipment['item_level'];
        $expectedBudget = (int)ceil(($level * 1.6 + 2 + $level ** 2 * 0.004) * 2.5)
            + HeroZero\Hideout::kyberneticBonuses($cid)['item_flat'];
        check(array_sum($equipmentStats) === $expectedBudget && in_array(count(array_filter($equipmentStats)), [3, 4], true),
            "Equipamento {$rewardId}: fórmula do brechó com multiplicador 2.5 e 3/4 atributos");
    }

    Season::claimReward($cid, 64, false);
    check((int)Db::value('SELECT item_improvement_id FROM sidekicks WHERE character_id = ? AND identifier = ? ORDER BY id DESC LIMIT 1',
        [$cid, 'sidekick_season_gods_1']) === 1, 'Pet do passe tem espaço de modificação vazio');

    Db::exec('UPDATE inventory SET bag_item1_id = 0 WHERE character_id = ?', [$cid]);
    Season::claimReward($cid, 2, false); // Gema de botas épica.
    $id = (int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?', [$cid]);
    $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [$id, $cid]);
    check($item && (int)$item['type'] === 12 && (int)$item['quality'] === 3 && $item['identifier'] === 'improvement_boots_epic',
        'Gema correta entregue na mochila como tipo 12');
    check((int)$item['ts_availability_start'] === 0, 'Modificações não recebem melhoria +15');
    check((int)$item['ts_availability_end'] === 0, 'Gemas não recebem espaço de modificação');
    check(min(array_map(static fn(string $key): int => (int)$item[$key],
        ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating'])) > 0, 'Gema tem atributos utilizáveis');

    $sets = [];
    foreach (range(1, 18) as $i) $sets[] = '`bag_item' . $i . '_id` = ' . $id;
    Db::exec('UPDATE inventory SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$cid]);
    $countBefore = (int)Db::value('SELECT COUNT(*) FROM items WHERE character_id = ?', [$cid]);
    reject(fn() => Season::claimReward($cid, 5, false), 'errInventoryNoEmptySlot');
    $claimed = json_decode((string)Db::value('SELECT claimed_rewards FROM season_progress WHERE character_id = ?', [$cid]), true);
    check(!in_array(5, $claimed, true), 'Mochila cheia mantém recompensa por resgatar');
    check((int)Db::value('SELECT COUNT(*) FROM items WHERE character_id = ?', [$cid]) === $countBefore, 'Falha não cria gema órfã');
    Db::exec('UPDATE inventory SET bag_item1_id = 0 WHERE character_id = ?', [$cid]);
    Season::claimReward($cid, 5, false);
    check((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?', [$cid]) > 0, 'Pode repetir após libertar espaço');

    $beforeDiscard = (int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]);
    Season::claimReward($cid, 12, true);
    check((int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?', [$cid]) === $beforeDiscard,
        'Descartar explicitamente não entrega atributos');
} finally {
    $pdo->rollBack();
}
echo "Testes concluídos; alterações revertidas.\n";
