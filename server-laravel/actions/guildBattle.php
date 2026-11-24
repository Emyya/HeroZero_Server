<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Live;

/**
 * Batalhas guilda-vs-guilda (guild_battle) — modelo do servidor antigo:
 *  - attackGuild: agenda (status=1) + pendentes nas DUAS guildas;
 *  - joinGuildBattle: membro regista-se (param attack=true/false);
 *  - claimGuildBattleReward: paga moedas ao membro e apaga a linha.
 * A resolucao LAZY vive em GuildBattles::resolveDueGuildBattles(), chamada
 * por Live::attachGuildBattleState.
 */
return function (array $params): array {
    $action = (string)($params['action'] ?? '');
    $userId = (int)($params['user_id'] ?? 0);
    $char   = Character::loadByUser($userId);
    $cid    = $char->id();

    $guildId = (int)(Db::row('SELECT guild_id FROM `character` WHERE id = ?', [$cid])['guild_id'] ?? 0);
    if ($guildId === 0) {
        throw new GameError('errCharacterNoGuild');
    }

    switch ($action) {

        case 'attackGuild': {
            $defenderId = (int)($params['guild_id'] ?? 0);
            $time       = (int)($params['time'] ?? 0);
            if ($time < 1 || $time > 5) throw new GameError('errCreateInvalidGuild');

            $defender = Db::row('SELECT * FROM `guild` WHERE id = ? AND status != 2', [$defenderId]);
            if ($defender === null) throw new GameError('errCreateInvalidGuild');
            if ($defenderId === $guildId) throw new GameError('errCreateAlreadyFought');

            // Validacoes do checkAttackGuild antigo: alvo ja esta a ser atacado,
            // ou ja nos atacou / esta a atacar-nos.
            if ((int)(Db::value('SELECT pending_guild_battle_defense_id FROM `guild` WHERE id = ?', [$defenderId]) ?? 0) > 0) {
                throw new GameError('errCreateAlreadyAttacked');
            }
            if ((int)(Db::value('SELECT pending_guild_battle_attack_id FROM `guild` WHERE id = ?', [$defenderId]) ?? 0) > 0) {
                throw new GameError('errCreateAlreadyAttacking');
            }
            if ((int)(Db::value('SELECT pending_guild_battle_attack_id FROM `guild` WHERE id = ?', [$guildId]) ?? 0) > 0) {
                throw new GameError('errCreateAlreadyAttacking');
            }

            // Slots na hora do RELÓGIO DE JOGO (Europe/Lisbon — o mesmo fuso
            // que a topbar e os detalhes mostram ao jogador). Janela de
            // preparação: 10h -> dentro dela só pode ser amanhã.
            $hours = [1 => 12, 2 => 16, 3 => 18, 4 => 20, 5 => 22];
            $h   = $hours[$time];
            $nowG = Live::gameNow();
            $tsAttack = $nowG->setTime($h, 0)->getTimestamp();
            if ((int)$nowG->format('G') >= $h - 10) {
                $tsAttack = $nowG->modify('+1 day')->setTime($h, 0)->getTimestamp();
            }

            Db::exec(
                "INSERT INTO `guild_battle`
                 (status, battle_time, ts_attack, guild_attacker_id, guild_defender_id,
                  attacker_character_ids, defender_character_ids,
                  attacker_character_profiles, defender_character_profiles,
                  rounds, attacker_rewards, defender_rewards, initiator_character_id)
                 VALUES (1, ?, ?, ?, ?, ?, '[]', '[]', '[]', '', '', '', ?)",
                [$time, $tsAttack, $guildId, $defenderId, json_encode([$cid]), $cid]
            );
            $battleId = (int)Db::pdo()->lastInsertId();

            Db::exec('UPDATE `guild` SET pending_guild_battle_attack_id = ? WHERE id = ?', [$battleId, $guildId]);
            Db::exec('UPDATE `guild` SET pending_guild_battle_defense_id = ? WHERE id = ?', [$battleId, $defenderId]);

            return Live::accountState($userId);
        }

        case 'joinGuildBattle': {
            // Param OFICIAL: attack=true|false (string bool do cliente).
            $attack = (($params['attack'] ?? 'true') === true || ($params['attack'] ?? '') === 'true');
            if ($attack) {
                $battleId = (int)(Db::value('SELECT pending_guild_battle_attack_id FROM `guild` WHERE id = ?', [$guildId]) ?? 0);
                $idsKey   = 'attacker_character_ids';
            } else {
                $battleId = (int)(Db::value('SELECT pending_guild_battle_defense_id FROM `guild` WHERE id = ?', [$guildId]) ?? 0);
                $idsKey   = 'defender_character_ids';
            }
            if ($battleId <= 0) throw new GameError('errInvalidStateToPendingBattle');

            $battle = Db::row('SELECT * FROM `guild_battle` WHERE id = ? AND status = 1', [$battleId]);
            if ($battle === null) throw new GameError('errJoinGuildBattleInvalidGuildBattle');

            // Ja tem recompensa desta batalha => ja lutou.
            if (Db::row('SELECT id FROM `guild_battle_rewards` WHERE guild_battle_id = ? AND character_id = ?', [$battleId, $cid]) !== null) {
                throw new GameError('errJoinGuildBattleAlreadyFought');
            }

            $ids   = json_decode((string)($battle[$idsKey] ?? '[]'), true) ?: [];
            $other = json_decode((string)($battle[$attack ? 'defender_character_ids' : 'attacker_character_ids'] ?? '[]'), true) ?: [];
            if (in_array($cid, array_map('intval', $other), true)) {
                throw new GameError('errAddCharacterIdAlreadyJoined');
            }
            if (!in_array($cid, array_map('intval', $ids), true)) {
                $ids[] = $cid;
                Db::exec("UPDATE `guild_battle` SET {$idsKey} = ? WHERE id = ?", [json_encode(array_values(array_map('intval', $ids))), $battleId]);
            }
            return Live::accountState($userId);
        }

        case 'claimGuildBattleReward': {
            $battleId = (int)($params['guild_battle_id'] ?? 0);
            $reward = Db::row(
                'SELECT * FROM `guild_battle_rewards` WHERE guild_battle_id = ? AND character_id = ?',
                [$battleId, $cid]
            );
            if ($reward === null) throw new GameError('errClaimGuildBattleRewardInvalid');

            $coins = (int)$reward['game_currency'];
            if ($coins > 0) {
                Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$coins, $cid]);
            }
            Db::exec('DELETE FROM `guild_battle_rewards` WHERE id = ?', [(int)$reward['id']]);
            // Limpa o ponteiro que faz o cliente abrir o diálogo de resultado
            // (type 1 = atacante → finished_guild_battle_attack_id).
            if ((int)$reward['type'] === 1) {
                Db::exec('UPDATE `character` SET finished_guild_battle_attack_id = 0 WHERE id = ?', [$cid]);
            } elseif ((int)$reward['type'] === 2) {
                Db::exec('UPDATE `character` SET finished_guild_battle_defense_id = 0 WHERE id = ?', [$cid]);
            }

            return Live::accountState($userId);
        }
    }

    return Live::accountState($userId);
};
