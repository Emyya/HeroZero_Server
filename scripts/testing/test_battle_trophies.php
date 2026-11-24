<?php
declare(strict_types=1);

/**
 * Teste ponta-a-ponta do gancho de trofeus em GuildBattles::simulateGuildBattle.
 * Cria uma guild_battle FICTICIA dentro de transacao e resolve-a; ROLLBACK no fim.
 */

require (dirname(__DIR__, 3)) . '/server-laravel/vendor/autoload.php';
require_once (dirname(__DIR__, 3)) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require (dirname(__DIR__, 3)) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\GameTime;
use HeroZero\GuildBattles;

function show(string $tag): void
{
    foreach ([1, 2] as $gid) {
        $g = Db::row('SELECT name,artifact_ids,artifacts_owned_current,artifacts_owned_max,artifacts_won,artifacts_lost FROM `guild` WHERE id=?', [$gid]);
        echo "  [$tag] {$g['name']}: ids={$g['artifact_ids']} cur={$g['artifacts_owned_current']} max={$g['artifacts_owned_max']} won={$g['artifacts_won']} lost={$g['artifacts_lost']}\n";
    }
}

$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    // Cenario: atacante sonnet46 forte com SLOTS LIVRES (so 2 trofeus de 5);
    // defensor oxAlpha com 1 trofeu. Vitoria do atacante → roubo esperado.
    Db::exec("UPDATE `guild` SET artifact_ids = '[41,4]', artifacts_owned_current = 2 WHERE id = 1");
    Db::exec("UPDATE `guild` SET stat_guild_capacity = 200 WHERE id = 2");
    Db::exec("UPDATE `character` SET finished_guild_battle_attack_id = 0, finished_guild_battle_defense_id = 0 WHERE id IN (25295, 25296, 25297)");

    $ts = GameTime::now();
    Db::exec(
        'INSERT INTO `guild_battle`
         (status, battle_time, ts_attack, guild_attacker_id, guild_defender_id,
          attacker_character_ids, defender_character_ids, guild_winner_id,
          attacker_character_profiles, defender_character_profiles, rounds,
          attacker_rewards, defender_rewards, initiator_character_id)
         VALUES (1, 0, ?, 1, 2, "[25295,25296]", "[25297]", 0, "", "", "", "", "", 25295)',
        [$ts]
    );
    $battleId = (int)$pdo->lastInsertId();
    echo "batalha ficticia #{$battleId}: atacante=sonnet46 [25295,25296] vs defensor=oxAlpha [Debug]\n";
    show("antes");

    GuildBattles::simulateGuildBattle($battleId);

    $b = Db::row('SELECT * FROM `guild_battle` WHERE id = ?', [$battleId]);
    echo "\nresultado: status={$b['status']} winner_guild={$b['guild_winner_id']}\n";
    echo "attacker_rewards: {$b['attacker_rewards']}\n";
    echo "defender_rewards: {$b['defender_rewards']}\n";
    echo "rounds bytes: " . strlen((string)$b['rounds']) . "\n";
    $fin = Db::row('SELECT finished_guild_battle_attack_id AS fa, finished_guild_battle_defense_id AS fd FROM `character` WHERE id = 25295');
    echo "char 25295 finished_attack={$fin['fa']} (devia ={$battleId})\n";
    show("depois");
} finally {
    $pdo->rollBack();
    echo "\n*** ROLLBACK — estado real intacto ***\n";
}
