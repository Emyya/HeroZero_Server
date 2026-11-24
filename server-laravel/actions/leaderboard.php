<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameTime;
use HeroZero\League;
use HeroZero\Live;
use HeroZero\Tournament;

return function (array $params): array {
    $action = (string)($params['action'] ?? '');

    if ($action === 'retrieveGuildLeaderboard') {
        $userId    = (int)($params['user_id'] ?? 0);
        $sortByHonor = ((int)($params['sort_type'] ?? 1)) === 1;
        $guildName = (string)($params['guild_name'] ?? '');
        $sortRank  = (int)($params['rank'] ?? 0);

        // Divisor OFICIAL do cliente: Va.totalImprovementPercentage usa a constante
        // `guild_percentage_total_real` = 180 (balancing_constants.json) para a
        // percentagem mostrada nas ESTATISTICAS da team; usar 175 (base) fazia o
        // ranking divergir das estatisticas.
        $maxGuildBasePercentage = 180;
        $tpExpr = "((`g`.`stat_guild_capacity`+`g`.`stat_character_base_stats_boost`+`g`.`stat_quest_xp_reward_boost`+`g`.`stat_quest_game_currency_reward_boost`)/{$maxGuildBasePercentage})";
        // MySQL nao permite usar o alias `tp` da mesma SELECT no ORDER BY de
        // ROW_NUMBER(). Repetir a expressao em todos os calculos de rank.
        $sortBy  = $sortByHonor ? 'g.`honor`' : $tpExpr;

        $maxGuilds = (int)(Db::row('SELECT COUNT(*) as cnt FROM `guild` WHERE `status` != 2')['cnt'] ?? 0);

        if ($sortRank > 0) {
            $centerRank = $sortRank;
        } elseif ($guildName !== '') {
            $row = Db::row(
                "SELECT `g`.`rank` FROM
                    (SELECT ROW_NUMBER() OVER (ORDER BY {$sortBy} DESC) as `rank`, `id`, `name`, {$tpExpr} as tp FROM `guild` g WHERE `status` != 2) as g
                 WHERE `g`.`name` = ?",
                [$guildName]
            );
            $centerRank = $row ? (int)$row['rank'] : 0;
            if ($centerRank === 0) {
                throw new \HeroZero\GameError('errRetrieveGuildLeaderboardInvalidGuild');
            }
        } else {
            $myGuild = Live::guildForUser($userId);
            $guildId = $myGuild ? (int)$myGuild['id'] : 0;
            if ($guildId > 0) {
                $row = Db::row(
                    "SELECT `g`.`rank` FROM
                        (SELECT ROW_NUMBER() OVER (ORDER BY {$sortBy} DESC) as `rank`, `id`, {$tpExpr} as tp FROM `guild` g WHERE `status` != 2) as g
                     WHERE `g`.`id` = ?",
                    [$guildId]
                );
                $centerRank = $row ? (int)$row['rank'] : 1;
            } else {
                $centerRank = 1;
            }
        }

        $columns = "ROW_NUMBER() OVER (ORDER BY {$sortBy} DESC) as r, g.`name` as n, g.`id` as id, g.`honor` as h, {$tpExpr} as tp,
            g.`emblem_background_shape` as ebs, g.`emblem_background_color` as ebc,
            g.`emblem_background_border_color` as ebbc, g.`emblem_icon_shape` as eis,
            g.`emblem_icon_color` as eic, g.`emblem_icon_size` as eiz,
            g.`artifact_ids` as ga, COALESCE(gb.id,0) as gbd";

        $offset = max(0, $centerRank - 49);
        $lb = Db::rows(
            "SELECT h.* FROM
                (SELECT {$columns} FROM `guild` g
                 LEFT JOIN `guild_battle` gb ON gb.`guild_defender_id`=g.`id` AND gb.`status`=1
                 WHERE g.`status`=1) as h
             WHERE h.r > ? ORDER BY h.r ASC LIMIT 50",
            [$offset]
        );

        foreach ($lb as &$row) {
            foreach ($row as $k => &$v) {
                if (is_numeric($v)) {
                    $v = ((int)$v == $v) ? (int)$v : (float)$v;
                }
            }
        }
        unset($row, $v);

        return Live::timeKeys([
            'leaderboard_guilds' => $lb,
            'max_guilds'         => $maxGuilds,
            'centered_rank'      => $centerRank,
        ]);
    }

    // Leaderboard de torneio local (mesmo servidor) — dados reais da DB.
    if (in_array($action, ['retrieveCharacterTournamentLeaderboard', 'retrieveGlobalCharacterTournamentLeaderboard'], true)) {
        // Global leaderboard nao envia type -> default honor (PvP); local envia type explicito
        $defaultType = $action === 'retrieveGlobalCharacterTournamentLeaderboard'
            ? Tournament::TYPE_PVP
            : Tournament::TYPE_XP;
        $type = max(1, min(3, (int)($params['type'] ?? $defaultType)));
        $charName  = ($params['character_name'] ?? null) ?: null;
        $sortRank  = (int)($params['rank'] ?? 0);
        $charId    = 0;
        try {
            $char   = Character::loadByUser((int)($params['user_id'] ?? 0));
            $charId = $char->id();
        } catch (\HeroZero\GameError $e) {}

        $result = Tournament::leaderboard($type, $charId, $charName, $sortRank);
        if (isset($result['error'])) {
            throw new \HeroZero\GameError($result['error']);
        }
        return Live::timeKeys($result);
    }

    if ($action === 'retrieveGuildLeaderboard') {
        $userId   = (int)($params['user_id'] ?? 0);
        $sortType = (int)($params['sort_type'] ?? 1); // 1=honor, 2=total_percentage
        $rank     = (int)($params['rank'] ?? 0);
        $guildName = (string)($params['guild_name'] ?? '');
        $limit    = 10;

        $orderBy = $sortType === 2 ? 'honor DESC' : 'honor DESC';

        if ($guildName !== '') {
            $guilds = Db::rows(
                "SELECT * FROM `guild` WHERE name LIKE ? ORDER BY {$orderBy} LIMIT ?",
                ['%' . $guildName . '%', $limit]
            );
        } elseif ($rank > 0) {
            $guilds = Db::rows(
                "SELECT * FROM `guild` ORDER BY {$orderBy} LIMIT ? OFFSET ?",
                [$limit, max(0, $rank - 1)]
            );
        } else {
            $guilds = Db::rows(
                "SELECT * FROM `guild` ORDER BY {$orderBy} LIMIT ?",
                [$limit]
            );
        }

        $total = (int)(Db::row('SELECT COUNT(*) as cnt FROM `guild`')['cnt'] ?? 0);

        $myGuild = Live::guildForUser($userId);
        $myGuildId = $myGuild ? (int)$myGuild['id'] : 0;

        $rankOffset = ($rank > 0) ? $rank : 1;
        $list = [];
        foreach ($guilds as $i => $g) {
            $list[] = [
                'id'    => (int)$g['id'],
                'n'     => (string)$g['name'],
                'h'     => (int)$g['honor'],
                'tp'    => 0.0,
                'rank'  => $rankOffset + $i,
                'am'    => (bool)$g['accept_members'],
                'ao'    => true,
                'l'     => 'pt_BR',
                'ga'    => '',
                'gbd'   => 0,
                'ebs'   => (int)($g['emblem_background_shape'] ?? 1),
                'ebc'   => (int)($g['emblem_background_color'] ?? 2),
                'ebbc'  => (int)($g['emblem_background_border_color'] ?? 1),
                'eis'   => (int)($g['emblem_icon_shape'] ?? 1),
                'eic'   => (int)($g['emblem_icon_color'] ?? 4),
                'eiz'   => (int)($g['emblem_icon_size'] ?? 100),
                'server_id' => '',
                'is_my_guild' => ((int)$g['id'] === $myGuildId),
            ];
        }

        return Live::timeKeys([
            'leaderboard_guilds' => $list,
            'max_guilds'         => $total,
            'centered_rank'      => $rankOffset,
        ]);
    }

    // SEM captura real desta resposta no HAR: em vez de deixar o fallback generico
    // (abaixo) sintetizar leaderboard_characters (shape errada p/ este painel de
    // guildas), devolvemos direto uma lista de guildas vazia + a guilda do jogador.
    if ($action === 'retrieveGuildCompetitionTournamentLeaderboardGuildsAroundOwnGuild') {
        $data = ['leaderboard_guilds' => [], 'max_guilds' => 0, 'centered_rank' => 1];
        $guild = Live::guildForUser((int)($params['user_id'] ?? 0));
        if ($guild !== null) {
            $entry = Live::shapeGuild($guild);
            $entry['rank'] = 1;
            $data['leaderboard_guilds'] = [$entry];
            $data['max_guilds'] = 1;
        }
        return Live::timeKeys($data);
    }

    // Ranking da LIGA: shape PROPRIA (linhas cruas com league_points/guild_name/
    // emblem_*) -- o painel de liga nao le a shape curta do ranking geral. Porte
    // do retrieveLeagueGroupLeaderboard.req.php oficial: janela de 50 entradas
    // (rank central -49). O LIKE '%grupo%' do oficial virou igualdade exata
    // ('%100000%' apanhava league_group_id de divisoes altas, 1100000 etc).
    if ($action === 'retrieveLeagueGroupLeaderboard') {
        $userId = (int)($params['user_id'] ?? 0);
        $characterName = trim((string)($params['character_name'] ?? ''));
        $sortRank = (int)($params['rank'] ?? 0);
        $groupId = (int)($params['league_group_id'] ?? 0);

        League::ensureSeason();
        $totalCharacters = (int)(Db::value("SELECT COUNT(*) FROM `character` WHERE name <> ''") ?? 0);
        if ($sortRank < 0 || $sortRank > $totalCharacters) {
            throw new \HeroZero\GameError('errRetrieveLeaderboardInvalidRank');
        }

        if ($sortRank > 0) {
            $centerRank = $sortRank;
            // groupId pode vir como parâmetro; se não, fica 0 (sem filtro de grupo)
        } elseif ($characterName !== '') {
            $found = Db::row(
                "SELECT id, league_group_id FROM `character` WHERE `name` = ? LIMIT 1",
                [$characterName]
            );
            if (!$found) {
                throw new \HeroZero\GameError('errRetrieveLeaderboardInvalidCharacter');
            }
            $groupId = (int)$found['league_group_id'];
            $rankRow = Db::row(
                "SELECT `rank` FROM
                    (SELECT ROW_NUMBER() OVER (ORDER BY league_points DESC, id ASC) as `rank`, id FROM `character`
                      WHERE league_group_id = ? AND name <> '') ch
                  WHERE ch.`id` = ?",
                [$groupId, (int)$found['id']]
            );
            $centerRank = $rankRow ? (int)$rankRow['rank'] : 1;
        } else {
            if ($groupId <= 0) {
                $me = Character::loadByUser($userId);
                $groupId = (int)(Db::value('SELECT league_group_id FROM `character` WHERE id = ?', [$me->id()]) ?? 0);
            }
            // Centro = MELHOR colocado do meu grupo
            $centerRank = 1;
        }

        $maxCharacters = $groupId > 0
            ? (int)(Db::value("SELECT COUNT(*) FROM `character` WHERE league_group_id = ? AND name <> ''", [$groupId]) ?? 0)
            : (int)(Db::value("SELECT COUNT(*) FROM `character` WHERE name <> ''") ?? 0);

        $groupFilter = $groupId > 0 ? 'WHERE ch.`name` <> \'\' AND ch.`league_group_id` = ' . $groupId : "WHERE ch.`name` <> ''";

        $rows = Db::rows(
            "SELECT h.* FROM
                (SELECT ROW_NUMBER() OVER (ORDER BY ch.`league_points` DESC, ch.`id` ASC) as `rank`,
                        ch.`id`, ch.`name`, ch.`guild_id`, ch.`gender`, ch.`level`,
                        ch.`ts_last_action`, ch.`league_points`, ch.`league_group_id`, ch.`honor`,
                        IF((" . GameTime::now() . "-ch.`ts_last_action`)<60, 1, 2) as `online_status`,
                        COALESCE(g.`name`,'') as `guild_name`,
                        g.`emblem_background_shape`, g.`emblem_background_color`, g.`emblem_background_border_color`,
                        g.`emblem_icon_shape`, g.`emblem_icon_color`, g.`emblem_icon_size`
                   FROM `character` ch LEFT JOIN `guild` g ON g.`id` = ch.`guild_id`
                  {$groupFilter}) h
             WHERE h.`rank` > ?
             ORDER BY h.`rank` ASC
             LIMIT 50",
            [max(0, $centerRank - 49)]
        );

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'rank'                          => (int)$r['rank'],
                'id'                            => (int)$r['id'],
                'name'                          => (string)$r['name'],
                'guild_id'                      => (int)$r['guild_id'],
                'gender'                        => (string)$r['gender'],
                'level'                         => (int)$r['level'],
                'ts_last_action'                => (int)$r['ts_last_action'],
                'league_points'                 => (int)$r['league_points'],
                'online_status'                 => (int)$r['online_status'],
                'league_group_id'               => (int)$r['league_group_id'],
                'honor'                         => (int)$r['honor'],
                'guild_name'                    => (string)$r['guild_name'],
                'emblem_background_shape'       => (int)($r['emblem_background_shape'] ?? 0),
                'emblem_background_color'       => (int)($r['emblem_background_color'] ?? 0),
                'emblem_background_border_color'=> (int)($r['emblem_background_border_color'] ?? 0),
                'emblem_icon_shape'             => (int)($r['emblem_icon_shape'] ?? 0),
                'emblem_icon_color'             => (int)($r['emblem_icon_color'] ?? 0),
                'emblem_icon_size'              => (int)($r['emblem_icon_size'] ?? 0),
            ];
        }

        return Live::timeKeys([
            'character'               => [],
            'centered_rank'           => $centerRank,
            'leaderboard_characters'  => $list,
            'max_characters'          => $maxCharacters,
        ]);
    }

    $data = Live::template($action);
    $sort = match ($action) {
        'retrieveLeagueGroupLeaderboard' => 'league',
        default => ((string)($params['level_sort'] ?? '') === 'true' ? 'level' : 'honor'),
    };
    $tpl = $data['leaderboard_characters'][0] ?? $data['leaderboard_hideouts'][0] ?? [];
    $list = Live::leaderboardCharacters($tpl, $sort);
    if (array_key_exists('leaderboard_characters', $data)) {
        $data['leaderboard_characters'] = $list;
        $data['max_characters'] = count($list);
    }
    if (!array_key_exists('leaderboard_characters', $data) && !array_key_exists('leaderboard_hideouts', $data) && !array_key_exists('leaderboard_guilds', $data)) {
        $data['leaderboard_characters'] = $list;
        $data['max_characters'] = count($list);
    }
    if (array_key_exists('leaderboard_hideouts', $data)) {
        $data['leaderboard_hideouts'] = $list;
        $data['max_hideouts'] = count($list);
    }
    if (array_key_exists('leaderboard_guilds', $data)) {
        $data['leaderboard_guilds'] = [];
        $data['max_guilds'] = 0;
    }
    $data['centered_rank'] = 1;
    try {
        $char = Character::loadByUser((int)($params['user_id'] ?? 0));
        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
    } catch (\HeroZero\GameError $e) {}
    return $data;
};
