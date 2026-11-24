<?php
declare(strict_types=1);

use HeroZero\GameTime;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GuildBattles;
use HeroZero\Live;

/**
 * Gangues (Guild Dungeon NPC Team attacks).
 *
 * Fluxo:
 *   1. getGuildDungeonNPCTeam        — sorteia/exibe gangue atual, bloqueia para este jogador
 *   2. rerollGuildDungeonNPCTeam     — sorteia nova gangue (1.º grátis, seguintes custam premium da guilda)
 *   3. unlockGuildDungeonNPCTeamSelection — liberta o lock sem atacar
 *   4. attackGuildDungeonNPCTeam     — agenda o ataque (time=1..5 → horários fixos)
 *   5. joinGuildDungeonBattle        — outros membros juntam-se ao ataque pendente
 *   6. claimGuildDungeonBattleReward — levanta recompensa após a batalha ser resolvida
 *
 * Stats dos NPCs = média dos stats reais dos membros participantes × fator de dificuldade.
 * Dificuldade sorteada: easy 50% (×0.8), medium 30% (×1.2), hard 20% (×1.5).
 * Cada gangue tem 5 membros NPC com os mesmos stats escalados.
 */
return function (array $params): array {
    $action = (string)($params['action'] ?? '');
    $userId = (int)($params['user_id'] ?? 0);

    $char = Character::loadByUser($userId);
    $cid  = $char->id();

    $charRow = Db::row('SELECT guild_id, name, level FROM `character` WHERE id = ?', [$cid]);
    $guildId = (int)($charRow['guild_id'] ?? 0);
    if ($guildId === 0) {
        throw new GameError('errCharacterNoGuild');
    }

    // ---------- helpers ----------

    $loadDungeon = static function () use ($guildId): ?array {
        return Db::row('SELECT * FROM `guild_dungeon` WHERE guild_id = ?', [$guildId]);
    };

    $difficultyMultiplier = static function (int $diff): float {
        return match ($diff) { 2 => 1.2, 3 => 1.5, default => 0.8 };
    };

    $randomDifficulty = static function (): int {
        $r = mt_rand(1, 100);
        if ($r <= 50) return 1;  // easy  50%
        if ($r <= 80) return 2;  // medium 30%
        return 3;                // hard   20%
    };

    // Sorteia nova gangue e grava/actualiza guild_dungeon.
    $randomiseDungeon = static function (bool $resetReroll) use ($guildId, $loadDungeon, $randomDifficulty): array {
        $teamId    = mt_rand(1, 28);
        $difficulty = $randomDifficulty();
        $rewardFactor = match ($difficulty) { 2 => 1.2, 3 => 1.5, default => 0.8 };
        $settings  = json_encode([
            'difficulty'   => $difficulty,
            'reroll_count' => $resetReroll ? 0 : (int)(json_decode(
                (string)(Db::row('SELECT settings FROM `guild_dungeon` WHERE guild_id = ?', [$guildId])['settings'] ?? '{}'),
                true
            )['reroll_count'] ?? 0) + 1,
            'rewards'      => [0, $rewardFactor, $rewardFactor],
        ]);

        $existing = Db::row('SELECT id FROM `guild_dungeon` WHERE guild_id = ?', [$guildId]);
        $tsUnlock = GameTime::now() + 300; // 5 min de lock

        if ($existing === null) {
            Db::exec(
                'INSERT INTO `guild_dungeon` (guild_id, npc_team_identifier, npc_team_character_profiles, settings, ts_unlock, locking_character_name)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$guildId, "team{$teamId}", '[]', $settings, $tsUnlock, '']
            );
        } else {
            Db::exec(
                'UPDATE `guild_dungeon` SET npc_team_identifier=?, npc_team_character_profiles=?, settings=?, ts_unlock=?, locking_character_name=?
                 WHERE guild_id=?',
                ["team{$teamId}", '[]', $settings, $tsUnlock, '', $guildId]
            );
        }
        return Db::row('SELECT * FROM `guild_dungeon` WHERE guild_id = ?', [$guildId]);
    };

    $dungeonShape = static function (array $d) use ($difficultyMultiplier): array {
        $settings = json_decode((string)($d['settings'] ?? '{}'), true) ?? [];
        if (!isset($settings['rewards'])) {
            $f = $difficultyMultiplier((int)($settings['difficulty'] ?? 1));
            $settings['rewards'] = [0, $f, $f];
        }
        return [
            'id'                         => (int)$d['id'],
            'guild_id'                   => (int)$d['guild_id'],
            'npc_team_identifier'        => (string)$d['npc_team_identifier'],
            // STRING JSON (nao array): o cliente faz d.fromJsonString(...) /
            // JSON.parse sobre este campo — array rebenta com
            // "SyntaxError: expected property name or '}'".
            'npc_team_character_profiles'=> (string)($d['npc_team_character_profiles'] ?? '[]'),
            'settings'                   => json_encode($settings),
            'ts_unlock'                  => (int)$d['ts_unlock'],
            'locking_character_name'     => (string)$d['locking_character_name'],
        ];
    };

    // Resolucao da luta vivem em HeroZero\GuildBattles (partilhada com o
    // accountState, para qualquer login/sync resolver a batalha quando a hora
    // do ataque passa — modelo oficial lazy de GuildDungeonBattle::checkFight).

    $pendingBattle = static function () use ($guildId): ?array {
        return GuildBattles::pendingDungeonBattle($guildId);
    };

    $finishedBattle = static function (int $cid) use ($guildId): ?array {
        return Db::row(
            'SELECT b.* FROM `guild_dungeon_battle` b
              JOIN `guild_battle_rewards` r ON r.guild_battle_id=b.id AND r.character_id=? AND r.status=1 AND r.type=3
             WHERE b.guild_id=? AND b.status=3
             LIMIT 1',
            [$cid, $guildId]
        );
    };

    // ---------- actions ----------

    switch ($action) {

        case 'getGuildDungeonNPCTeam': {
            // Se há batalha pendente, resolve se já passou e devolve dungeon + pending juntos
            $pending = $pendingBattle();
            if ($pending !== null) {
                GuildBattles::resolveDueDungeonBattle($guildId);
                $pending = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id=?', [(int)$pending['id']]);
                $pSettings = json_decode((string)($pending['settings'] ?? '{}'), true) ?? [];
                $dungeon = $loadDungeon();
                // Actualiza lock para que o utilizador possa interagir (reroll, etc.)
                if ($dungeon !== null) {
                    $myName = (string)($charRow['name'] ?? '');
                    Db::exec(
                        'UPDATE `guild_dungeon` SET locking_character_name=?, ts_unlock=? WHERE guild_id=?',
                        [$myName, GameTime::now() + 300, $guildId]
                    );
                    $dungeon = $loadDungeon();
                }
                $result = ['pending_guild_dungeon_battle' => [
                    'id'                          => (int)$pending['id'],
                    'battle_time'                 => (int)$pending['battle_time'],
                    'ts_attack'                   => (int)$pending['ts_attack'],
                    'guild_id'                    => (int)$pending['guild_id'],
                    'npc_team_identifier'         => (string)$pending['npc_team_identifier'],
                    'npc_team_character_profiles' => GuildBattles::dungeonPreviewProfiles($pending),
                    'status'                      => (int)$pending['status'],
                    'settings'                    => json_encode($pSettings),
                    'character_ids'               => json_decode((string)($pending['character_ids'] ?? '[]'), true) ?? [],
                ]];
                if ($dungeon !== null) {
                    $result['guild_dungeon_battle'] = $dungeonShape($dungeon);
                }
                return Live::timeKeys($result);
            }
            $dungeon = $loadDungeon();
            if ($dungeon === null) {
                $dungeon = $randomiseDungeon(true);
            } else {
                // Gangue anterior foi DERROTADA: sorteia uma nova (a fixa só
                // se mantém enquanto não for derrotada).
                $dSettings = json_decode((string)($dungeon['settings'] ?? '{}'), true) ?? [];
                if (!empty($dSettings['defeated'])) {
                    $dungeon = $randomiseDungeon(true);
                }
            }
            // Lock — se já está bloqueado por outro jogador e ainda dentro do tempo, rejeita
            $lockName = (string)($dungeon['locking_character_name'] ?? '');
            $tsUnlock = (int)($dungeon['ts_unlock'] ?? 0);
            $myName   = (string)($charRow['name'] ?? '');
            if ($lockName !== '' && $lockName !== $myName && $tsUnlock > GameTime::now()) {
                throw new GameError("errGetGuildDungeonNPCTeamAlreadyLocked_{$lockName}");
            }
            // Actualiza lock para este jogador
            Db::exec(
                'UPDATE `guild_dungeon` SET locking_character_name=?, ts_unlock=? WHERE guild_id=?',
                [$myName, GameTime::now() + 300, $guildId]
            );
            $dungeon = $loadDungeon();
            return Live::timeKeys(['guild_dungeon_battle' => $dungeonShape($dungeon)]);
        }

        case 'rerollGuildDungeonNPCTeam': {
            $dungeon = $loadDungeon();
            if ($dungeon === null) throw new GameError('errCreateInvalidGuild');
            // Gangue já batalhada e viva: BLOQUEADO até ser derrotada.
            $dSettings = json_decode((string)($dungeon['settings'] ?? '{}'), true) ?? [];
            if (!empty($dSettings['reroll_blocked'])) {
                throw new GameError('errRerollGuildDungeonNPCTeamNotDefeated');
            }
            // Só quem tem o lock pode re-sortear
            $myName = (string)($charRow['name'] ?? '');
            if ((string)$dungeon['locking_character_name'] !== $myName || (int)$dungeon['ts_unlock'] <= GameTime::now()) {
                throw new GameError('errCreateInvalidGuild');
            }
            $settings = json_decode((string)($dungeon['settings'] ?? '{}'), true) ?? [];
            $rerollCount = (int)($settings['reroll_count'] ?? 0);
            if ($rerollCount > 0) {
                $cost = 10; // guild_dungeon_new_enemy_premium_amount
                $paid = Db::exec(
                    'UPDATE `guild` SET premium_currency = premium_currency - ? WHERE id=? AND premium_currency >= ?',
                    [$cost, $guildId, $cost]
                );
                if ($paid < 1) throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            $dungeon = $randomiseDungeon(false);
            // Mantém o lock no novo dungeon
            Db::exec(
                'UPDATE `guild_dungeon` SET locking_character_name=?, ts_unlock=? WHERE guild_id=?',
                [$myName, GameTime::now() + 300, $guildId]
            );
            $dungeon = $loadDungeon();
            $guild = Live::guildForUser($userId);
            return Live::timeKeys([
                'guild_dungeon_battle' => $dungeonShape($dungeon),
                'guild' => $guild !== null ? Live::shapeGuild($guild) : [],
            ]);
        }

        case 'unlockGuildDungeonNPCTeamSelection': {
            Db::exec(
                'UPDATE `guild_dungeon` SET locking_character_name=\'\', ts_unlock=0 WHERE guild_id=?',
                [$guildId]
            );
            return Live::timeKeys([]);
        }

        case 'attackGuildDungeonNPCTeam': {
            // Se há battle pendente com ataque no futuro, cancela (reagendamento)
            $existingPending = $pendingBattle();
            if ($existingPending !== null) {
                if ((int)$existingPending['ts_attack'] > GameTime::now()) {
                    Db::exec('DELETE FROM `guild_dungeon_battle` WHERE id=?', [(int)$existingPending['id']]);
                    Db::exec('DELETE FROM `guild_battle_rewards` WHERE guild_battle_id=?', [(int)$existingPending['id']]);
                } else {
                    throw new GameError('errJoinGuildBattleInvalidGuildBattle');
                }
            }

            $dungeonBattleId = (int)($params['guild_dungeon_battle_id'] ?? 0);
            $time = (int)($params['time'] ?? 0);
            if ($time < 1 || $time > 5) throw new GameError('errCreateInvalidGuild');

            $dungeon = Db::row('SELECT * FROM `guild_dungeon` WHERE guild_id=? AND id=?', [$guildId, $dungeonBattleId]);
            if ($dungeon === null) throw new GameError('errCreateInvalidGuild');

            // Custo em moedas da guilda (indexado pela percentagem de expansão truncada)
            $guild = Db::row('SELECT * FROM `guild` WHERE id=?', [$guildId]);
            if ($guild === null) throw new GameError('errCharacterNoGuild');
            $totalPct = (int)floor(
                ((int)$guild['stat_guild_capacity'] + (int)$guild['stat_character_base_stats_boost']
                + (int)$guild['stat_quest_xp_reward_boost'] + (int)$guild['stat_quest_game_currency_reward_boost'])
                / 175 * 100
            );
            // Tabela de custo: 0-6% grátis, depois escala (simplificado: pct * 500)
            $costTable = [0,0,0,0,0,0,0,100,100,250,250,250,500,500,500,1000,1000,1000,1500,1500,2000,2000,2000,2500,2500,3000,3000,3500,3500,4000,4500];
            $cost = $totalPct <= 30 ? ($costTable[$totalPct] ?? 4500) : (int)round($totalPct * 500);

            if ($cost > 0) {
                $paid = Db::exec(
                    'UPDATE `guild` SET game_currency = game_currency - ? WHERE id=? AND game_currency >= ?',
                    [$cost, $guildId, $cost]
                );
                if ($paid < 1) throw new GameError('errRemoveGameCurrencyNotEnough');
            }

            // Timestamp do ataque (horários fixos: 12/16/18/20/22h).
            // 1:1 com o cliente (GuildBattleAttackTimestamp): a regra
            // Slots na hora do RELÓGIO DE JOGO (Europe/Lisbon) — o mesmo fuso
            // que a topbar e os detalhes mostram. `guild_dungeon_preparation_time`
            // = 10 HORAS; dentro da janela só pode ser amanhã.
            $hours = [1 => 12, 2 => 16, 3 => 18, 4 => 20, 5 => 22];
            $h = $hours[$time];
            $prepHours = 10;
            $nowG = Live::gameNow();
            $tsAttack = $nowG->setTime($h, 0)->getTimestamp();
            if ((int)$nowG->format('G') >= $h - $prepHours) {
                $tsAttack = $nowG->modify('+1 day')->setTime($h, 0)->getTimestamp();
            }

            $settings  = json_decode((string)($dungeon['settings'] ?? '{}'), true) ?? [];

            Db::exec(
                'INSERT INTO `guild_dungeon_battle`
                 (status, battle_time, ts_attack, guild_id, npc_team_identifier, settings, character_ids,
                  joined_character_profiles, npc_team_character_profiles, rounds, rewards, initiator_character_id)
                 VALUES (2, ?, ?, ?, ?, ?, ?, ?, ?, \'\', \'\', ?)',
                [
                    $time, $tsAttack, $guildId,
                    (string)$dungeon['npc_team_identifier'],
                    json_encode($settings),
                    json_encode([$cid]),
                    json_encode([]),
                    (string)($dungeon['npc_team_character_profiles'] ?? '[]'),
                    $cid,
                ]
            );
            $battleId = (int)Db::pdo()->lastInsertId();

            Db::exec('UPDATE `guild` SET pending_guild_dungeon_battle_attack_id=? WHERE id=?', [$battleId, $guildId]);

            $battle = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id=?', [$battleId]);
            return Live::timeKeys([
                'character'                   => [],
                'guild'                       => Live::shapeGuild(Db::row('SELECT * FROM `guild` WHERE id=?', [$guildId])),
                'pending_guild_dungeon_battle'=> [
                    'id'                          => $battleId,
                    'battle_time'                 => $time,
                    'ts_attack'                   => $tsAttack,
                    'guild_id'                    => $guildId,
                    'npc_team_identifier'         => (string)$dungeon['npc_team_identifier'],
                    'npc_team_character_profiles' => (string)$dungeon['npc_team_character_profiles'],
                    'status'                      => 2,
                    'settings'                    => json_encode($settings),
                    'character_ids'               => [$cid],
                ],
            ]);
        }

        case 'joinGuildDungeonBattle': {
            $pending = $pendingBattle();
            if ($pending === null) throw new GameError('errJoinGuildBattleInvalidGuildBattle');

            // Verifica se já está na lista
            $ids = json_decode((string)($pending['character_ids'] ?? '[]'), true) ?? [];
            if (in_array($cid, $ids, true)) throw new GameError('errAddCharacterIdAlreadyJoined');

            $ids[] = $cid;
            Db::exec(
                'UPDATE `guild_dungeon_battle` SET character_ids=? WHERE id=?',
                [json_encode($ids), (int)$pending['id']]
            );

            // Verifica se já pode resolver (ts_attack passou enquanto aguardava)
            $pending['character_ids'] = json_encode($ids);
            GuildBattles::resolveDueDungeonBattle($guildId);

            $pending = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id=?', [(int)$pending['id']]);
            $settings = json_decode((string)($pending['settings'] ?? '{}'), true) ?? [];
            return Live::timeKeys([
                'pending_guild_dungeon_battle' => [
                    'id'                          => (int)$pending['id'],
                    'battle_time'                 => (int)$pending['battle_time'],
                    'ts_attack'                   => (int)$pending['ts_attack'],
                    'guild_id'                    => (int)$pending['guild_id'],
                    'npc_team_identifier'         => (string)$pending['npc_team_identifier'],
                    'npc_team_character_profiles' => GuildBattles::dungeonPreviewProfiles($pending),
                    'status'                      => (int)$pending['status'],
                    'settings'                    => json_encode($settings),
                    'character_ids'               => json_decode((string)$pending['character_ids'], true) ?? [],
                ],
            ]);
        }

        case 'claimGuildDungeonBattleReward': {
            $battleId = (int)($params['guild_dungeon_battle_id'] ?? 0);
            $discard  = !empty($params['discard_item']);

            // Resolve se ainda pendente
            $battle = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id=? AND guild_id=?', [$battleId, $guildId]);
            if ($battle !== null && (int)$battle['status'] === 2) {
                GuildBattles::resolveDueDungeonBattle($guildId);
                $battle = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id=?', [$battleId]);
            }

            $reward = Db::row(
                'SELECT * FROM `guild_battle_rewards` WHERE guild_battle_id=? AND character_id=? AND type=3 AND status=1',
                [$battleId, $cid]
            );
            if ($reward === null) throw new GameError('errClaimGuildBattleRewardInvalid');

            $coins = (int)$reward['game_currency'];
            $xp    = (int)$reward['xp'];
            if ($coins > 0 || $xp > 0) {
                Db::exec(
                    'UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ? WHERE id=?',
                    [$coins, $xp, $cid]
                );
            }

            Db::exec('DELETE FROM `guild_battle_rewards` WHERE id=?', [(int)$reward['id']]);
            Db::exec('UPDATE `character` SET finished_guild_dungeon_battle_id=0 WHERE id=?', [$cid]);

            return Live::accountState($userId);
        }
    }

    return Live::accountState($userId);
};
