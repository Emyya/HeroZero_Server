<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GuildChat;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? $params['existing_user_id'] ?? 0);
    $data = Live::accountState($userId);

    if (($params['action'] ?? '') === 'getGuildBattleList') {
        // O cliente itera data.guild_battle_entries (DOGuildBattleListEntry) e
        // ele mesmo remove a guild do proprio jogador da lista.
        $rows = Db::rows(
            'SELECT g.id, g.name, g.honor, g.artifact_ids,
                    g.emblem_background_shape, g.emblem_background_color,
                    g.emblem_background_border_color, g.emblem_icon_shape,
                    g.emblem_icon_color, g.emblem_icon_size,
                    COUNT(c.id) AS member_count,
                    COALESCE(ROUND(AVG(c.level)), 0) AS average_level
               FROM `guild` g
               LEFT JOIN `character` c ON c.guild_id = g.id
              WHERE g.status = 1
              GROUP BY g.id
              ORDER BY g.honor DESC'
        );
        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'honor' => (int)$row['honor'],
                'member_count' => (int)$row['member_count'],
                'average_level' => (int)$row['average_level'],
                'artifact_ids' => (string)$row['artifact_ids'],
                'emblem_background_shape' => (int)$row['emblem_background_shape'],
                'emblem_background_color' => (int)$row['emblem_background_color'],
                'emblem_background_border_color' => (int)$row['emblem_background_border_color'],
                'emblem_icon_shape' => (int)$row['emblem_icon_shape'],
                'emblem_icon_color' => (int)$row['emblem_icon_color'],
                'emblem_icon_size' => (int)$row['emblem_icon_size'],
                'locale' => 'pt_BR',
            ];
        }
        $data['guild_battle_entries'] = $entries;
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuild') {
        // O cliente le data.requested_guild (DOGuild) e, se presente,
        // data.requested_guild_members (lista de DOGuildMember).
        $guildId = (int)($params['guild_id'] ?? 0);
        $guild = $guildId > 0 ? Db::row('SELECT * FROM `guild` WHERE id = ? LIMIT 1', [$guildId]) : null;
        if ($guild !== null) {
            $data['requested_guild'] = Live::shapeGuild($guild);
            if (!empty($params['refresh_members'])) {
                $data['requested_guild_members'] = Live::guildMembers((int)$guild['id']);
            }
        }
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuildList') {
        // Cliente oficial: 50. Os bots podem pedir uma amostra maior para não
        // ignorar vagas quando a população já tiver dezenas de equipas.
        $listLimit = max(50, min(500, (int)($params['limit'] ?? 50)));
        // Lista de guildas para o painel de "procurar guilda" (so as que aceitam membros).
        $rows = Db::rows(
            'SELECT g.id, g.name, g.description, g.honor, g.stat_guild_capacity,
                    g.min_apply_level, g.min_apply_honor, g.accept_members,
                    g.stat_character_base_stats_boost, g.stat_quest_xp_reward_boost,
                    g.stat_quest_game_currency_reward_boost,
                    g.emblem_background_shape, g.emblem_background_color,
                    g.emblem_background_border_color, g.emblem_icon_shape,
                    g.emblem_icon_color, g.emblem_icon_size,
                    COUNT(c.id) AS member_count,
                    COALESCE(ROUND(AVG(c.level)), 0) AS average_level
               FROM `guild` g
               LEFT JOIN `character` c ON c.guild_id = g.id
              WHERE g.status = 1 AND g.accept_members = 1
              GROUP BY g.id
              ORDER BY g.honor DESC
              LIMIT ?',
            [$listLimit]
        );
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'description' => (string)$row['description'],
                'honor' => (int)$row['honor'],
                'member_count' => (int)$row['member_count'],
                'stat_guild_capacity' => (int)$row['stat_guild_capacity'],
                'average_level' => (int)$row['average_level'],
                'min_apply_level' => (int)$row['min_apply_level'],
                'min_apply_honor' => (int)$row['min_apply_honor'],
                'accept_members' => (bool)$row['accept_members'],
                'emblem_background_shape' => (int)$row['emblem_background_shape'],
                'emblem_background_color' => (int)$row['emblem_background_color'],
                'emblem_background_border_color' => (int)$row['emblem_background_border_color'],
                'emblem_icon_shape' => (int)$row['emblem_icon_shape'],
                'emblem_icon_color' => (int)$row['emblem_icon_color'],
                'emblem_icon_size' => (int)$row['emblem_icon_size'],
                'locale' => 'pt_BR',
                // Cliente crasha (DataObject: unknown field total_percentage) sem isso.
                // get_fullTotalPercentage() faz Math.round(100*tp): FRACAO 0-1, com o
                // MESMO divisor das estatisticas da team (Va.totalImprovementPercentage
                // usa `guild_percentage_total_real` = 180) e do ranking — assim os tres
                // ecras mostram o mesmo valor (antes era ocupacao membros/capacidade em
                // % inteira: o cliente multiplicava por 100 e mostrava 600%/900%).
                'total_percentage' => min(
                    1,
                    ((int)$row['stat_guild_capacity']
                        + (int)$row['stat_character_base_stats_boost']
                        + (int)$row['stat_quest_xp_reward_boost']
                        + (int)$row['stat_quest_game_currency_reward_boost']) / 180
                ),
            ];
        }
        $data['guild_entries'] = $list;
        return $data;
    }

    // ------------------------------------------------------------------
    // Historico de lutas da team (DialogGuildBattleHistory). Formato OFICIAL
    // (DOGuildHistoryBattle): o cliente le type/id/battle_timestamp/enemy/
    // joined_character_ids/rewards/enemy_name/emblema por entrada; "won" TEM
    // de ser booleano JSON (getBoolean faz cast — 1/0 devolve sempre false)
    // e "rewards" e STRING JSON donde sai get_honorReward() -> chave "honor".
    // ------------------------------------------------------------------
    if (($params['action'] ?? '') === 'getGuildBattleHistoryFights') {
        $char = Character::loadByUser($userId);
        $guildId = (int)(Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$char->id()]) ?? 0);
        if ($guildId <= 0) {
            throw new GameError('errNotInGuild');
        }

        $battles = [];

        // Guilda-vs-guilda resolvidas (status=3) em que participamos.
        // Honra do nosso lado vem de attacker/defender_rewards ({"honor": N},
        // ja com sinal — vencedor positivo, perdedor negativo).
        $rows = Db::rows(
            'SELECT gb.id, gb.ts_attack, gb.guild_attacker_id, gb.guild_defender_id,
                    gb.guild_winner_id, gb.attacker_character_ids, gb.defender_character_ids,
                    gb.attacker_rewards, gb.defender_rewards,
                    ga.name AS attacker_name,
                    ga.emblem_background_shape AS a_ebs, ga.emblem_background_color AS a_ebc,
                    ga.emblem_background_border_color AS a_ebbc, ga.emblem_icon_shape AS a_eis,
                    ga.emblem_icon_color AS a_eic, ga.emblem_icon_size AS a_eiz,
                    gd.name AS defender_name,
                    gd.emblem_background_shape AS d_ebs, gd.emblem_background_color AS d_ebc,
                    gd.emblem_background_border_color AS d_ebbc, gd.emblem_icon_shape AS d_eis,
                    gd.emblem_icon_color AS d_eic, gd.emblem_icon_size AS d_eiz
               FROM `guild_battle` gb
               LEFT JOIN `guild` ga ON ga.id = gb.guild_attacker_id
               LEFT JOIN `guild` gd ON gd.id = gb.guild_defender_id
              WHERE gb.status = 3 AND (gb.guild_attacker_id = ? OR gb.guild_defender_id = ?)
              ORDER BY gb.ts_attack DESC LIMIT 50',
            [$guildId, $guildId]
        );
        foreach ($rows as $row) {
            $isAttacker = (int)$row['guild_attacker_id'] === $guildId;
            $prefix = $isAttacker ? 'd_' : 'a_'; // colunas do EMBLEMA do inimigo
            $sideRewards = json_decode((string)($isAttacker ? $row['attacker_rewards'] : $row['defender_rewards']), true);
            $battles[] = [
                'id'                   => (int)$row['id'],
                'type'                 => $isAttacker ? 1 : 2,
                'enemy'                => (int)($isAttacker ? $row['guild_defender_id'] : $row['guild_attacker_id']),
                'battle_timestamp'     => (int)$row['ts_attack'],
                'won'                  => (int)$row['guild_winner_id'] === $guildId,
                'joined_character_ids' => (string)($isAttacker ? $row['attacker_character_ids'] : $row['defender_character_ids']),
                'rewards'              => is_array($sideRewards) ? json_encode($sideRewards) : '',
                'enemy_name'           => (string)($isAttacker ? ($row['defender_name'] ?? '') : ($row['attacker_name'] ?? '')),
                'ebs'                  => (int)$row[$prefix . 'ebs'],
                'ebc'                  => (int)$row[$prefix . 'ebc'],
                'ebbc'                 => (int)$row[$prefix . 'ebbc'],
                'eis'                  => (int)$row[$prefix . 'eis'],
                'eic'                  => (int)$row[$prefix . 'eic'],
                'eiz'                  => (int)$row[$prefix . 'eiz'],
            ];
        }

        // Batalhas de dungeon resolvidas (type 3): "enemy" e o IDENTIFICADOR
        // da gangue NPC (string — o cliente resolve o nome via chave de texto
        // guild_dungeon_team/<identificador>/name) e "won" = rewards pagos.
        $dungeonRows = Db::rows(
            'SELECT id, ts_attack, npc_team_identifier, character_ids, rewards
               FROM `guild_dungeon_battle`
              WHERE guild_id = ? AND status = 3
              ORDER BY ts_attack DESC LIMIT 50',
            [$guildId]
        );
        foreach ($dungeonRows as $row) {
            $rewards = json_decode((string)$row['rewards'], true);
            $battles[] = [
                'id'                   => (int)$row['id'],
                'type'                 => 3,
                'enemy'                => (string)$row['npc_team_identifier'],
                'battle_timestamp'     => (int)$row['ts_attack'],
                'won'                  => is_array($rewards) && (int)($rewards['game_currency'] ?? 0) > 0,
                'joined_character_ids' => (string)$row['character_ids'],
                'rewards'              => (string)$row['rewards'],
                'enemy_name'           => '',
                'ebs'                  => 0,
                'ebc'                  => 0,
                'ebbc'                 => 0,
                'eis'                  => 0,
                'eic'                  => 0,
                'eiz'                  => 0,
            ];
        }

        usort($battles, static fn(array $a, array $b): int => $b['battle_timestamp'] <=> $a['battle_timestamp']);
        $data['guild_history_battles'] = array_slice($battles, 0, 50);
        return $data;
    }

    if (($params['action'] ?? '') === 'getGuildBattleHistoryFight') {
        // Detalhe de uma luta do historico (param OFICIAL type=1|2|3 + id).
        // type 3 = batalha de dungeon; 1/2 = luta guilda-vs-guilda (nosso lado
        // determinado pela guilda do jogador). Com "rounds" guardados o cliente
        // abre o replay normal da batalha (review mode).
        $char = Character::loadByUser($userId);
        $guildId = (int)(Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$char->id()]) ?? 0);
        if ($guildId <= 0) {
            throw new GameError('errNotInGuild');
        }
        $type = (int)($params['type'] ?? 1);
        $fightId = (int)($params['id'] ?? 0);

        if ($type === 3) {
            // Mesmo shape do finished_guild_dungeon_battle (funciona no cliente).
            $row = Db::row(
                'SELECT * FROM `guild_dungeon_battle` WHERE id = ? AND guild_id = ? AND status = 3',
                [$fightId, $guildId]
            );
            if ($row === null) {
                throw new GameError('errGetGuildBattleHistoryFightNotFound');
            }
            $data['guild_history_battle'] = [
                'id'                          => (int)$row['id'],
                'guild_id'                    => (int)$row['guild_id'],
                'battle_time'                 => (int)$row['battle_time'],
                'ts_attack'                   => (int)$row['ts_attack'],
                'npc_team_identifier'         => (string)$row['npc_team_identifier'],
                'status'                      => (int)$row['status'],
                'settings'                    => (string)$row['settings'],
                'character_ids'               => (string)($row['character_ids'] ?? '[]'),
                'joined_character_profiles'   => (string)($row['joined_character_profiles'] ?? '[]'),
                'npc_team_character_profiles' => (string)($row['npc_team_character_profiles'] ?? '[]'),
                'rounds'                      => (string)($row['rounds'] ?? ''),
                'rewards'                     => (string)($row['rewards'] ?? ''),
            ];
            return $data;
        }

        $row = Db::row(
            'SELECT * FROM `guild_battle` WHERE id = ? AND status = 3 AND (guild_attacker_id = ? OR guild_defender_id = ?)',
            [$fightId, $guildId, $guildId]
        );
        if ($row === null) {
            throw new GameError('errGetGuildBattleHistoryFightNotFound');
        }
        $isAttacker = (int)$row['guild_attacker_id'] === $guildId;

        // Shape completo do DOGuildBattle (igual ao finished_guild_battle_*),
        // com character_ids = os do NOSSO lado (o cliente conta membros por ele).
        $battle = [
            'id'                           => (int)$row['id'],
            'status'                       => (int)$row['status'],
            'ts_attack'                    => (int)$row['ts_attack'],
            'battle_time'                  => (int)$row['battle_time'],
            'guild_attacker_id'            => (int)$row['guild_attacker_id'],
            'guild_defender_id'            => (int)$row['guild_defender_id'],
            'character_ids'                => (string)($isAttacker ? $row['attacker_character_ids'] : $row['defender_character_ids']),
            'guild_winner_id'              => (int)($row['guild_winner_id'] ?? 0),
            'attacker_character_profiles'  => \HeroZero\GuildBattles::clientProfiles((string)($row['attacker_character_profiles'] ?? '')),
            'defender_character_profiles'  => \HeroZero\GuildBattles::clientProfiles((string)($row['defender_character_profiles'] ?? '')),
            'rounds'                       => (string)($row['rounds'] ?? ''),
            'attacker_rewards'             => (string)($row['attacker_rewards'] ?? ''),
            'defender_rewards'             => (string)($row['defender_rewards'] ?? ''),
        ];
        $data['guild_history_battle'] = $battle;

        // Guilda adversaria: o cliente resolve get_attacker()/get_defender()
        // da luta a partir desta lista (Va.getGuildBattleGuild).
        $opp = Db::row(
            'SELECT * FROM `guild` WHERE id = ?',
            [(int)($isAttacker ? $row['guild_defender_id'] : $row['guild_attacker_id'])]
        );
        if ($opp !== null) {
            $data['guild_battle_guilds'][] = Live::shapeGuild($opp);
        }
        return $data;
    }

    if (($params['action'] ?? '') !== 'getGuildLog') {
        return $data;
    }

    $char = Character::loadByUser($userId);
    $member = Db::row('SELECT guild_id, guild_rank FROM `character` WHERE id = ? LIMIT 1', [$char->id()]);
    $guildId = (int)($member['guild_id'] ?? 0);
    $rank = (int)($member['guild_rank'] ?? 3);
    if ($guildId <= 0) {
        throw new GameError('errSyncGuildNoGuild');
    }

    $rows = Db::rows(
        'SELECT id, guild_id, character_id, character_name, type, value1, value2, value3, timestamp
           FROM `guild_logs`
          WHERE guild_id = ?
          ORDER BY id ASC
          LIMIT 100',
        [$guildId]
    );
    $log = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $log[(string)$id] = [
            'id' => $id,
            'guild_id' => (int)$row['guild_id'],
            'character_id' => (int)$row['character_id'],
            'character_name' => (string)$row['character_name'],
            'type' => (int)$row['type'],
            'value1' => (string)$row['value1'],
            'value2' => (string)$row['value2'],
            'value3' => (string)$row['value3'],
            'timestamp' => (int)$row['timestamp'],
            'message' => '',
            'is_private' => false,
            'is_officer' => false,
            'read' => true,
        ];
    }

    // Mensagens de chat entram no mesmo guild_log: o cliente distingue evento
    // (tem "type") de mensagem (tem "message"). Ids de mensagem levam offset
    // p/ nao colidir com os de guild_logs (ver GuildChat).
    foreach (GuildChat::visibleMessages($guildId, $char->id(), $rank) as $entry) {
        $log[(string)$entry['id']] = $entry;
    }

    $data['guild_log'] = $log;
    // O cliente guarda chaves que comecam com "guild" e prefixa getguildlog_/syncgame_ sozinho.
    $data['sync_states'] = ['guild' . $guildId => GuildChat::version($guildId)];
    return $data;
};
