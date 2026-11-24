<?php
declare(strict_types=1);

/**
 * Teste dos EFEITOS dos trofeus — TUDO dentro de transacao com ROLLBACK.
 * Cenarios:
 *  A) effects()/custo/multiplicador/maximos efetivos
 *  B) overlayCharacter emite max_* com bonus + custo de duelo
 *  C) syncDuelStamina respeita teto acima da coluna base
 *  D) energyTraining.buyDuelStamina enche ate ao maximo EFETIVO
 *  E) penalizacao de skills na batalha guilda-vs-guilda (por lado)
 */

require (dirname(__DIR__, 3)) . '/server-laravel/vendor/autoload.php';
require_once (dirname(__DIR__, 3)) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require (dirname(__DIR__, 3)) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameTime;
use HeroZero\GuildArtifacts;

function check(string $what, $got, $want): void
{
    $ok = $got == $want;
    echo ($ok ? "  OK " : "  FAIL") . " {$what}: got=" . json_encode($got) . " esperado=" . json_encode($want) . "\n";
    if (!$ok) { $GLOBALS['fails'] = ($GLOBALS['fails'] ?? 0) + 1; }
}

$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    // --- setup: ids fixos e conhecidos ---
    // guild 1 (sonnet46): [4(type1),11(type2),21(type3),41(type4)]
    //   n1=n2=n3=n4=1; reduction = .07+.10+.15+.12 = 0.44 -> fator skills 0.56
    // guild 2 (oxAlpha): [32(type2)] -> n2=1; reduction .10 -> fator 0.90
    Db::exec("UPDATE `guild` SET artifact_ids='[4,11,21,41]', artifacts_owned_current=4 WHERE id = 1");
    Db::exec("UPDATE `guild` SET artifact_ids='[32]', artifacts_owned_current=1 WHERE id = 2");

    echo "=== A) efeitos agregados ===\n";
    check('effects g1', GuildArtifacts::effects(1), ['n1' => 1, 'n2' => 1, 'n3' => 1, 'n4' => 1, 'reduction' => 0.44]);
    check('effects g2', GuildArtifacts::effects(2), ['n1' => 0, 'n2' => 1, 'n3' => 0, 'n4' => 0, 'reduction' => 0.10]);
    check('duelStaminaCost g1', GuildArtifacts::duelStaminaCost(1), 18);
    check('pvpRewardMultiplier g1', round(GuildArtifacts::pvpRewardMultiplier(1), 2), 1.25);
    check('max quest_energy g1 (100+20)', GuildArtifacts::effectiveMax('quest_energy', 100, 1), 120);
    check('max training_energy g1 (30+5)', GuildArtifacts::effectiveMax('training_energy', 30, 1), 35);
    check('max duel_stamina g1 (100+20)', GuildArtifacts::effectiveMax('duel_stamina', 100, 1), 120);
    check('max league_stamina g1 (20+2)', GuildArtifacts::effectiveMax('league_stamina', 20, 1), 22);

    $leaderG1 = Db::row("SELECT id,user_id FROM `character` WHERE guild_id = 1 AND guild_rank = 1 ORDER BY id LIMIT 1");
    echo "\n=== B) overlayCharacter (char {$leaderG1['id']}, guilda 1) ===\n";
    // Base REAL do char na BD (pode diferir do template): bonus soma por cima.
    $rowC = Db::row('SELECT max_quest_energy, max_training_energy, max_duel_stamina, max_league_stamina FROM `character` WHERE id = ?', [$leaderG1['id']]);
    $tpl = HeroZero\Live::template('autoLoginUser');
    $over = Character::load((int)$leaderG1['id'])->overlayCharacter($tpl['character']);
    check('max_quest_energy', $over['max_quest_energy'], (int)$rowC['max_quest_energy'] + 20);
    check('max_training_energy', $over['max_training_energy'], (int)$rowC['max_training_energy'] + 5);
    check('max_duel_stamina', $over['max_duel_stamina'], (int)$rowC['max_duel_stamina'] + 20);
    check('max_league_stamina', $over['max_league_stamina'], (int)$rowC['max_league_stamina'] + 2);
    check('duel_stamina_cost', $over['duel_stamina_cost'], 18);
    $overG2 = Character::load(25297)->overlayCharacter($tpl['character']);
    check('g2 max_quest_energy (só energia)', $overG2['max_quest_energy'], 120);
    check('g2 duel_stamina_cost (sem type1)', $overG2['duel_stamina_cost'], 20);

    echo "\n=== C) syncDuelStamina com stamina entre base(100) e efetivo(120) ===\n";
    Db::exec("UPDATE `character` SET duel_stamina = 115, ts_last_duel_stamina_change = ? WHERE id = ?", [GameTime::now(), $leaderG1['id']]);
    $c = Character::load((int)$leaderG1['id']);
    $c->syncDuelStamina();
    check('duel_stamina mantida (nao clamp a 100)', (int)Db::value('SELECT duel_stamina FROM `character` WHERE id = ?', [$leaderG1['id']]), 115);
    Db::exec("UPDATE `character` SET duel_stamina = 99, ts_last_duel_stamina_change = ? - 3600*24 WHERE id = ?", [GameTime::now(), $leaderG1['id']]);
    Character::load((int)$leaderG1['id'])->syncDuelStamina();
    check('regen enche ate 120', (int)Db::value('SELECT duel_stamina FROM `character` WHERE id = ?', [$leaderG1['id']]), 120);

    echo "\n=== D) energyTraining.buyDuelStamina ate maximo EFETIVO ===\n";
    $fn = require (dirname(__DIR__, 3)) . '/server-laravel/actions/energyTraining.php';
    Db::exec("UPDATE `character` SET duel_stamina = 5, ts_last_duel_stamina_change = ? WHERE id = ?", [GameTime::now(), $leaderG1['id']]);
    Db::exec("UPDATE `user` SET premium_currency = 100 WHERE id = ?", [$leaderG1['user_id']]);
    $resp = $fn(['user_id' => (int)$leaderG1['user_id'], 'action' => 'buyDuelStamina']);
    $after = array_values(array_filter($resp['characters'] ?? [], fn($x) => (int)($x['id'] ?? 0) === (int)$leaderG1['id']));
    $val = $after !== [] ? (int)$after[0]['duel_stamina'] : (int)Db::value('SELECT duel_stamina FROM `character` WHERE id = ?', [$leaderG1['id']]);
    check('duel_stamina apos refill', $val, 120);

    echo "\n=== E) penalizacao de skills na batalha entre teams ===\n";
    Db::exec("UPDATE `character` SET finished_guild_battle_attack_id = 0, finished_guild_battle_defense_id = 0 WHERE id IN (25295,25296,25297)");
    $ts = GameTime::now();
    Db::exec(
        'INSERT INTO `guild_battle`
         (status, battle_time, ts_attack, guild_attacker_id, guild_defender_id,
          attacker_character_ids, defender_character_ids, guild_winner_id,
          attacker_character_profiles, defender_character_profiles, rounds,
          attacker_rewards, defender_rewards, initiator_character_id)
         VALUES (1, 0, ?, 1, 2, "[25295]", "[25297]", 0, "", "", "", "", "", 25295)',
        [$ts]
    );
    $battleId = (int)$pdo->lastInsertId();
    HeroZero\GuildBattles::simulateGuildBattle($battleId);
    $b = Db::row('SELECT attacker_character_profiles, defender_character_profiles, guild_winner_id FROM `guild_battle` WHERE id = ?', [$battleId]);
    // guildSideProfiles grava MAPA keyed por char_id ({"25295":{...}}), nao lista.
    $ap = json_decode((string)$b['attacker_character_profiles'], true) ?: [];
    $dp = json_decode((string)$b['defender_character_profiles'], true) ?: [];
    $rawA = Character::load(25295)->battleProfile('a');
    $rawD = Character::load(25297)->battleProfile('a');
    foreach ([['atacante(g1 fator 0.56)', $ap['25295'] ?? null, $rawA, 0.56], ['defensor(g2 fator 0.90)', $dp['25297'] ?? null, $rawD, 0.90]] as [$tag, $got, $raw, $factor]) {
        if ($got === null) { check("$tag perfil", null, 'presente'); continue; }
        foreach (['stamina', 'strength', 'criticalrating', 'dodgerating'] as $k) {
            $want = (int)round((int)($raw[$k] ?? 0) * $factor);
            check("$tag $k", (int)($got[$k] ?? -1), $want);
        }
    }

    // F) invariante HP: a barra do cliente = stamina escalada x Fight::HP_SCALE,
    // logo hitpoints gravado tem de ser 0 (derrotado) ou o pool cheio derivado
    // da propria stamina — nada entre os dois (sem carryover entre lutas).
    echo "\n=== F) hitpoints dos perfis = pool cheio da stamina ESCALADA ===\n";
    foreach ([['atacante', $ap['25295'] ?? null], ['defensor', $dp['25297'] ?? null]] as [$tag, $p]) {
        if (!is_array($p)) { continue; }
        $hp = (int)($p['hitpoints'] ?? -1);
        $full = (int)$p['stamina'] * HeroZero\Fight::HP_SCALE;
        if ($hp === 0) {
            check("$tag derrotado (hitpoints=0)", 0, 0);
            continue;
        }
        check("$tag hitpoints=stamina x " . HeroZero\Fight::HP_SCALE . " ($full)", $hp, $full);
    }
    echo "\nbatalha #{$battleId}: vencedor_guilda={$b['guild_winner_id']} (so confere que correu sem erro)\n";
} finally {
    $pdo->rollBack();
}
echo "\n*** ROLLBACK feito — estado real intacto ***\n";
echo "FALHAS: " . ($GLOBALS['fails'] ?? 0) . "\n";
