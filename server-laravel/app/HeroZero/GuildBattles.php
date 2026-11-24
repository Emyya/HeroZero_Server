<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Batalhas de guilda — resolucao AUTOMATICA das lutas agendadas.
 *
 * Modelo oficial (class/Guild.php + GuildDungeonBattle.php): a luta e simulada
 * LAZILY no primeiro pedido apos ts_attack passar ("isFought"/checkFight no
 * construtor). Nao ha timer: qualquer login/sync de um membro depois da hora
 * resolve a batalha, e o resultado chega ao cliente via
 * `finished_guild_dungeon_battle` + `guild_dungeon_battle_rewards` (ver
 * Live::accountState).
 */
final class GuildBattles
{
    /**
     * Resolve a batalha de dungeon pendente da guilda se ts_attack ja passou.
     * Devolve true se resolveu agora.
     */
    public static function resolveDueDungeonBattle(int $guildId): bool
    {
        $pendingId = (int)(Db::value(
            'SELECT pending_guild_dungeon_battle_attack_id FROM `guild` WHERE id = ?',
            [$guildId]
        ) ?? 0);
        $battle = $pendingId > 0
            ? Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id = ? AND guild_id = ? AND status = 2', [$pendingId, $guildId])
            : Db::row('SELECT * FROM `guild_dungeon_battle` WHERE guild_id = ? AND status = 2 ORDER BY id DESC LIMIT 1', [$guildId]);
        if ($battle === null) {
            return false;
        }
        if ((int)$battle['status'] !== 2 || (int)$battle['ts_attack'] > GameTime::now()) {
            return false;
        }
        self::simulateDungeonBattle($battle);
        return true;
    }

    /** Luta pendente da guilda (status=2), por pending_id ou a mais recente. */
    public static function pendingDungeonBattle(int $guildId): ?array
    {
        $pendingId = (int)(Db::value(
            'SELECT pending_guild_dungeon_battle_attack_id FROM `guild` WHERE id = ?',
            [$guildId]
        ) ?? 0);
        if ($pendingId > 0) {
            $row = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id = ? AND guild_id = ? AND status = 2', [$pendingId, $guildId]);
            if ($row !== null) {
                return $row;
            }
        }
        return Db::row('SELECT * FROM `guild_dungeon_battle` WHERE guild_id = ? AND status = 2 ORDER BY id DESC LIMIT 1', [$guildId]);
    }

    /** Perfis do preview; recupera ataques antigos agendados sem o snapshot. */
    public static function dungeonPreviewProfiles(array $battle): string
    {
        $profiles = (string)($battle['npc_team_character_profiles'] ?? '[]');
        if ((int)($battle['status'] ?? 0) !== 2 || !empty(json_decode($profiles, true))) {
            return $profiles;
        }
        $dungeon = Db::row(
            'SELECT npc_team_character_profiles FROM `guild_dungeon` WHERE guild_id=? AND npc_team_identifier=?',
            [(int)$battle['guild_id'], (string)$battle['npc_team_identifier']]
        );
        return (string)($dungeon['npc_team_character_profiles'] ?? '[]');
    }

    /**
     * Simula o ataque da guilda a gangue NPC e grava o resultado:
     * status=3 + rounds + perfis; recompensas (guild_battle_rewards type=3) para
     * cada participante se a guilda venceu; liberta o pending na guilda.
 * Stats dos NPCs = escadinha do mais fraco para o mais forte: o ULTIMO NPC
 * tem a media dos stats REAIS dos participantes x fator de dificuldade
 * (settings.difficulty) e cada passo para tras reduz 5% da media. Quantidade:
 * facil 3-5, medio 3-6, dificil 4-8.
     *
     * GANGUES NAO DERROTADAS: se a gangue sobreviveu a uma batalha anterior,
     * o estado (stats + HP restante de cada NPC vivo) vem de
     * guild_dungeon.npc_team_character_profiles — os NPCs NÃO curam nem
     * renascem entre batalhas. Dentro da mesma batalha o HP do NPC também
     * persiste entre membros (quem morre é substituído pelo seguinte). Ao
     * perder, o estado é gravado de volta (settings.reroll_blocked=1 → gangue
     * fixa até morrer); ao ganhar, settings.defeated=1 → a próxima seleção
     * sorteia gangue nova.
     */
    public static function simulateDungeonBattle(array $battle): void
    {
        $guildId   = (int)$battle['guild_id'];
        $characterIds = json_decode((string)($battle['character_ids'] ?? '[]'), true) ?? [];
        $settings  = json_decode((string)($battle['settings'] ?? '{}'), true) ?? [];
        $difficulty = (int)($settings['difficulty'] ?? 1);

        // --- estado persistido da gangue (batalha anterior nao resolvida) ---
        // Fonte unica: guild_dungeon.npc_team_character_profiles (perfis com o
        // HP atual; '[]' = gangue fresca/derrotada). Cada perfil sobrevivente
        // traz start_hitpoints = hitpoints para a barra de HP do cliente
        // (DialogGuildDungeonBattlePreview) mostrar o estado DANIFICADO.
        $dungeonRow = Db::row('SELECT * FROM `guild_dungeon` WHERE guild_id = ?', [$guildId]);
        $dungeonSettings = json_decode((string)($dungeonRow['settings'] ?? '{}'), true) ?? [];
        $persistedNpcs = json_decode((string)($dungeonRow['npc_team_character_profiles'] ?? '[]'), true) ?? [];

        // --- perfis NPC ---
        $mult = match ($difficulty) { 2 => 1.2, 3 => 1.5, default => 0.8 };
        $npcTeamIdentifier = (string)($battle['npc_team_identifier'] ?? '');
        // Composicao REAL de cada gangue: extraida dos textos oficiais
        // (guild_dungeon_team/team<N>/<seccao>/<npcId>/name em
        // assets/i18n/pt_BR/text.data) para data/reference/guild_dungeon_teams.json.
        // O cliente resolve o nome de cada membro assim (LC.get_name /
        // LC.get_npcIdentifier sobre o campo `profile`):
        //     npcIdentifier = substr(entre "_" e "-")
        //     nome          = text("guild_dungeon_team/<team>/<pos "-" >/<npcId>/name")
        // Logo profile TEM de ser "<idx>_<npcId>-<seccao>" com seccao ∈
        // always|first_half|second_half|end — senao a chave de texto nao existe
        // e o cliente mostra o CAMINHO da chave como nome.
        $teamsFile = dirname(__DIR__, 2) . '/data/reference/guild_dungeon_teams.json';
        $teamDef = json_decode((string)file_get_contents($teamsFile), true)[$npcTeamIdentifier] ?? null;
        $idSection = [];   // npcId → seccao
        $pool = [];        // npcs do mais fraco para o mais forte (sem o "end")
        foreach (['first_half', 'second_half'] as $sec) {
            foreach (array_keys($teamDef['sections'][$sec] ?? []) as $id) {
                if (!isset($idSection[$id])) {
                    $idSection[$id] = $sec;
                    $pool[] = $id;
                }
            }
        }
        $endIds = array_keys($teamDef['sections']['end'] ?? []);
        // Fallback (gangue sem definicao nos textos): catalogo generico por hash.
        $fallbackNpc = 'npc_adventurer';
        $npcCatalog = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/data/reference/quest_templates.json'),
            true
        )['npcs'] ?? [];
        if ($npcCatalog !== []) {
            $fallbackNpc = $npcCatalog[crc32($npcTeamIdentifier) % count($npcCatalog)];
        }

        if ($persistedNpcs !== []) {
            // Gangue ja batalhada: stats/HP EXATOS da ultima batalha (sem cura).
            $npcProfiles = array_values($persistedNpcs);
        } else {
            $npcProfiles = [];
            {
                $guildMemberRows = Db::rows('SELECT id FROM `character` WHERE guild_id = ?', [$guildId]);
                $members = [];
                foreach ($guildMemberRows as $r) {
                    $members[] = Character::load((int)$r['id'])->battleProfile('a');
                }
                if ($members !== []) {
                    // Escadinha OFICIAL: NPCs ordenados do MAIS FRACO para o MAIS
                    // FORTE (o ultimo enfrenta-se por ultimo). O mais forte tem a
                    // media de stats da equipa x fator de dificuldade; cada passo
                    // para baixo reduz 5% (relativo a media, nao ao anterior).
                    //   facil  (1): 3-5 NPCs,  top = media x 0.8
                    //   medio  (2): 3-6 NPCs,  top = media x 1.2
                    //   dificil(3): 4-8 NPCs,  top = media x 1.5
                    $count = match ($difficulty) {
                        2 => random_int(3, 6),
                        3 => random_int(4, 8),
                        default => random_int(3, 5),
                    };
                    $top = $mult;
                    $n = count($members);
                    $avg = static fn(string $k): int => (int)round(array_sum(array_column($members, $k)) / $n);
                    $lvl = $avg('level');
                    for ($i = 0; $i < $count; $i++) {
                        $f   = round($top - 0.05 * (($count - 1) - $i), 2);
                        $sta = max(1, (int)round($avg('stamina') * $f));
                        $str = max(1, (int)round($avg('strength') * $f));
                        // Identidade: o ULTIMO NPC (mais forte) usa a seccao "end";
                        // os restantes distribuem-se por first_half/second_half.
                        if ($endIds !== [] && $i === $count - 1) {
                            $npcId  = $endIds[($count - 1) % count($endIds)];
                            $sec    = 'end';
                        } elseif ($pool !== []) {
                            $idx   = (int)floor($i * count($pool) / max(1, $count - 1));
                            $npcId = $pool[min($idx, count($pool) - 1)];
                            $sec   = $idSection[$npcId];
                        } else {
                            $npcId = $fallbackNpc;
                            $sec   = 'always';
                        }
                        $npcProfiles[] = [
                            // profile = "<idx>_<npcId>-<seccao>" — formato oficial
                            // (ver comentario acima); parseInt do prefixo da o id
                            // numerico do lutador.
                            'profile'        => $i . '_' . $npcId . '-' . $sec,
                            'level'          => $lvl,
                            'stamina'        => $sta,
                            'strength'       => $str,
                            'criticalrating' => max(1, (int)round($avg('criticalrating') * $f)),
                            'dodgerating'    => max(1, (int)round($avg('dodgerating') * $f)),
                            'weapondamage'   => max(0, (int)round($avg('weapondamage') * $f)),
                            'hitpoints'      => $sta * Fight::HP_SCALE,
                            'damage_normal'  => $str + (int)round($avg('weapondamage') * $f),
                            'damage_bonus'   => $str + (int)round($avg('weapondamage') * $f),
                            'chance_critical'=> 0.1,
                            'chance_dodge'   => 0.1,
                        ];
                    }
                }
            }
        }

        // --- luta: cada atacante enfrenta os NPCs em sequencia ---
        // Formato OFICIAL (GuildDungeonBattle::checkFight): os lutadores sao
        // identificados pelo ID DE PERFIL (character id / indice do NPC) e o
        // HP persiste entre golpes/trocas de membro. Quem morre e substituido
        // pelo seguinte; ganha a guilda se eliminar TODOS os NPCs.
        // NPCs ja mortos em batalhas anteriores nao ressuscitam.
        $aliveNpcIdx = [];
        foreach ($npcProfiles as $i => $np) {
            if ((int)($np['hitpoints'] ?? 0) > 0) {
                $aliveNpcIdx[] = $i;
            }
        }

        // Ordem de entrada OFICIAL vs gangues: SEMPRE nivel menor -> maior
        // ("Na sequencia"/order1 fixo, nao configuravel); empate resolvido pelos
        // pontos de habilidade (base+comprados+treino dos 4 stats). Ordenado AQUI
        // porque o joinedProfiles numera `position` pela ordem destas rows.
        $attackerRows = $characterIds !== [] ? Db::rows(
            'SELECT id,
                    (stat_base_stamina + stat_bought_stamina + stat_trained_stamina
                   + stat_base_strength + stat_bought_strength + stat_trained_strength
                   + stat_base_critical_rating + stat_bought_critical_rating + stat_trained_critical_rating
                   + stat_base_dodge_rating + stat_bought_dodge_rating + stat_trained_dodge_rating) AS skill_points
               FROM `character`
              WHERE id IN (' . implode(',', array_map('intval', $characterIds)) . ')
              ORDER BY level ASC, skill_points ASC, id ASC'
        ) : [];

        $attProfiles = [];
        foreach ($attackerRows as $ar) {
            $p = Character::load((int)$ar['id'])->battleProfile('a');
            $p['profile'] = (string)$ar['id']; // label dos rounds = character id
            $attProfiles[] = $p;
        }

        $allRounds = [];
        $aIdx = 0;
        $pos = 0; // posicao na lista de NPCs VIVOS
        while ($aIdx < count($attProfiles) && $pos < count($aliveNpcIdx)) {
            $npcKey = $aliveNpcIdx[$pos];
            $fight = new Fight($attProfiles[$aIdx], $npcProfiles[$npcKey], true);
            $result = $fight->fight();
            $allRounds = array_merge($allRounds, $fight->rounds());
            if ($result === 1) {
                // NPC derrotado; o atacante segue com o HP restante
                $attProfiles[$aIdx]['hitpoints'] = max(1, $fight->hitpointsA());
                $npcProfiles[$npcKey]['hitpoints'] = 0;
                $pos++;
            } else {
                // atacante derrotado: o NPC fica ferido (HP persiste para o
                // proximo membro e para batalhas futuras) e entra o seguinte
                $npcProfiles[$npcKey]['hitpoints'] = max(0, $fight->hitpointsB());
                $aIdx++;
            }
        }
        $winner = ($aliveNpcIdx !== [] && $pos >= count($aliveNpcIdx)) ? 1 : 2;

        // --- write-back do estado da gangue no guild_dungeon ---
        if ($dungeonRow !== null) {
            if ($winner === 1) {
                // Gangue eliminada: proxima selecao sorteia gangue nova,
                // reroll desbloqueado.
                unset($dungeonSettings['reroll_blocked']);
                $dungeonSettings['defeated'] = 1;
                Db::exec(
                    'UPDATE `guild_dungeon` SET settings=?, npc_team_character_profiles=\'[]\', locking_character_name=\'\', ts_unlock=0 WHERE guild_id=?',
                    [json_encode($dungeonSettings), $guildId]
                );
            } else {
                // Gangue sobreviveu: persiste apenas os NPCs VIVOS com o HP
                // restante; reroll bloqueado ate a gangue morrer.
                // start_hitpoints = HP atual: sem "rounds" na selecao, o cliente
                // (Oe.endHitPoints) usa start_hitpoints como HP corrente — assim
                // a barra de HP do preview nasce DANIFICADA, nao cheia.
                $survivors = array_values(array_filter(
                    $npcProfiles,
                    static fn(array $np): bool => (int)($np['hitpoints'] ?? 0) > 0
                ));
                foreach ($survivors as &$sv) {
                    $sv['start_hitpoints'] = (int)$sv['hitpoints'];
                }
                unset($sv);
                $dungeonSettings['reroll_blocked'] = 1;
                unset($dungeonSettings['defeated']);
                Db::exec(
                    'UPDATE `guild_dungeon` SET settings=?, npc_team_character_profiles=? WHERE guild_id=?',
                    [json_encode($dungeonSettings), json_encode($survivors), $guildId]
                );
            }
        }

        // Formato OFICIAL (DOGuildDungeonBattleReward): o cliente faz getInt()
        // de TODAS estas chaves — chave ausente lança "unknown field" e rebenta
        // o DialogGuildDungeonBattleResults (janela da luta nunca fecha).
        $rewardsJson = ($winner === 1)
            ? json_encode([
                'guild_dungeon_battle_id' => (int)$battle['id'],
                'game_currency'           => 1,
                'xp'                      => 0,
                'item_id'                 => 0,
                'training'                => 0,
                'energy'                  => 0,
            ])
            : '';

        // --- recompensas por participante (so se a guilda venceu) ---
        foreach ($attackerRows as $ar) {
            $attackerId = (int)$ar['id'];
            $lvl = (int)(Character::load($attackerId)->battleProfile('a')['level'] ?? 1);
            $coins   = ($winner === 1) ? (int)floor(pow($lvl + 10, 2) * $mult) : 0;
            $xpPct   = match ($difficulty) { 2 => 0.25, 3 => 0.50, default => 0.15 };
            $xpStep  = max(0, Character::xpForLevel($lvl + 1) - Character::xpForLevel($lvl));
            $xp      = ($winner === 1) ? (int)floor($xpStep * $xpPct) : 0;
            $exists = Db::row(
                'SELECT id FROM `guild_battle_rewards` WHERE guild_battle_id = ? AND character_id = ? AND type = 3',
                [(int)$battle['id'], $attackerId]
            );
            if ($exists === null) {
                Db::exec(
                    'INSERT INTO `guild_battle_rewards` (guild_battle_id, character_id, game_currency, xp, item_id, status, type)
                     VALUES (?, ?, ?, ?, 0, 1, 3)',
                    [(int)$battle['id'], $attackerId, $coins, $xp]
                );
            }
            // Aponta a luta resolvida no personagem: o cliente SO abre o dialogo
            // da luta se finished_guild_dungeon_battle.id ==
            // character.finished_guild_dungeon_battle_id && status==3
            // (Y.get_finishedGuildDungeonBattle). Sem isto os membros nunca viam
            // a luta ao entrar na team. O claim volta a po-lo a 0.
            Db::exec(
                'UPDATE `character` SET finished_guild_dungeon_battle_id = ? WHERE id = ?',
                [(int)$battle['id'], $attackerId]
            );
        }

        Db::exec(
            'UPDATE `guild_dungeon_battle` SET status=3, rounds=?, joined_character_profiles=?, npc_team_character_profiles=?, rewards=? WHERE id=?',
            [
                json_encode($allRounds),
                json_encode(self::joinedProfiles($attackerRows)),
                json_encode($npcProfiles),
                $rewardsJson,
                (int)$battle['id'],
            ]
        );
        Db::exec('UPDATE `guild` SET pending_guild_dungeon_battle_attack_id = 0 WHERE id = ?', [$guildId]);
    }

    /**
     * Perfil dos atacantes para o ecra de batalha da gangue. Formato OFICIAL
     * (GuildDungeonBattle::characterAppearance): MAPA indexado pelo ID do
     * personagem, cada entrada com `profile` = id (o cliente faz parseInt e
     * usa-o como identificador do lutador), position (1-based), stats totais e
     * aparencia. Uma lista crua de aparencias crasha o cliente
     * ("c is undefined" em get_joinedCharacterProfiles).
     *
     * @return array<string,array<string,mixed>>
     */
    private static function joinedProfiles(array $attackerRows): array
    {
        $out = [];
        $pos = 1;
        foreach ($attackerRows as $ar) {
            $cid = (int)$ar['id'];
            $attacker = Character::load($cid);
            $p   = $attacker->battleProfile('a');
            $app = $attacker->battleAppearance();
            $app['profile']        = (string)$cid;
            $app['level']          = (int)($p['level'] ?? 1);
            $app['position']       = $pos++;
            $app['stamina']        = (int)($p['stamina'] ?? 0);
            $app['total_stamina']  = (int)($p['stamina'] ?? 0);
            $app['strength']       = (int)($p['strength'] ?? 0);
            $app['criticalrating'] = (int)($p['criticalrating'] ?? 0);
            $app['dodgerating']    = (int)($p['dodgerating'] ?? 0);
            $app['weapondamage']   = (int)($p['weapondamage'] ?? 0);
            // O tooltip de batalha de guilda (pc.getStatGuildBattleTooltip) le
            // damage_bonus com getInt — chave ausente LANCA excecao no
            // DataObject do cliente ("unknown field"). = forca + dano de arma.
            $app['damage_bonus']   = (int)($p['strength'] ?? 0) + (int)($p['weapondamage'] ?? 0);
            $out[(string)$cid] = $app;
        }
        return $out;
    }

    // =====================================================================
    // BATALHAS GUILDA-vs-GUILDA (guild_battle) — modelo do servidor antigo
    // (class/GuildsBattle.php): status 1 = agendada, 3 = resolvida.
    // Resolucao LAZY no primeiro pedido apos ts_attack. Os membros entram
    // via joinGuildBattle (attack=true/false); quem NAO se registou nao luta.
    // =====================================================================

    /** Resolve todas as batalhas guilda-vs-guilda cuja hora ja passou. */
    public static function resolveDueGuildBattles(): void
    {
        $ids = Db::column(
            'SELECT id FROM `guild_battle` WHERE status = 1 AND ts_attack <= ?',
            [GameTime::now()]
        );
        foreach ($ids as $id) {
            self::simulateGuildBattle((int)$id);
        }
    }

    /**
     * Simula a luta entre as duas guildas: cada lado apresenta os membros
     * REGISTADOS na FORMACAO configurada pela propria guilda
     * (guild_battle_tactics_{attack,defense}_order: 1 menor nivel primeiro,
     * 3 maior nivel primeiro, 5 aleatoria); o HP persiste entre lutas;
     * perde a guilda cujo roster esvaziar. Recompensas: honra para as guildas
     * (win/lost x10 do duelo), moedas para os membros vencedores.
     */
    public static function simulateGuildBattle(int $battleId): void
    {
        $battle = Db::row('SELECT * FROM `guild_battle` WHERE id = ?', [$battleId]);
        if ($battle === null || (int)$battle['status'] !== 1) {
            return;
        }

        // --- rosters (apenas os que se registaram), ordenados por stat_total ---
        // Penalizacao de skills em lutas ENTRE TEAMS (oficial "x% less skill
        // points"): cada lado luta com fator 1 - soma dos attribute_reduction dos
        // trofeus da SUA guilda (ex.: 1 trofeu type 4 -> -12% nos 4 atributos).
        // So aqui: duelo/liga 1v1 e dungeons vs NPC ficam sem penalizacao.
        $skillFactor = static fn(int $guildId): float =>
            max(0.0, 1.0 - \HeroZero\GuildArtifacts::effects($guildId)['reduction']);
        $loadSide = static function (array $ids, float $factor, int $formation): array {
            $out = [];
            $ids = array_map('intval', $ids);
            if ($ids === []) return $out;
            $rows = Db::rows('SELECT id,
                                     (stat_base_stamina + stat_bought_stamina + stat_trained_stamina
                                    + stat_base_strength + stat_bought_strength + stat_trained_strength
                                    + stat_base_critical_rating + stat_bought_critical_rating + stat_trained_critical_rating
                                    + stat_base_dodge_rating + stat_bought_dodge_rating + stat_trained_dodge_rating) AS skill_points
                                FROM `character` WHERE id IN (' . implode(',', $ids) . ')');
            foreach ($rows as $r) {
                $char = Character::load((int)$r['id']);
                // battleAppearance traz NAME/gender/aparencia/equipamento —
                // o visualizador da batalha (cv/Gl) le getString("name") e
                // crasha sem ele.
                $p = array_merge($char->battleProfile('a'), $char->battleAppearance());
                foreach (['stamina', 'strength', 'criticalrating', 'dodgerating'] as $statKey) {
                    $p[$statKey] = (int)round((int)($p[$statKey] ?? 0) * $factor);
                }
                // A barra de HP do cliente = Q.getHitPoints(stamina DO PERFIL)
                // (battle_hp_scale x stamina). O hitpoints vem do battleProfile
                // calculado com a stamina BRUTA — tem de seguir a escalada,
                // senao o servidor deixa o lado penalizado viver ALEM do zero
                // visivel ("lutou com 0 hp" — bug da batalha sonnet46 vs oxAlpha).
                $p['hitpoints'] = (int)$p['stamina'] * Fight::HP_SCALE;
                $p['profile']   = (string)$r['id'];
                $p['_cid']      = (int)$r['id'];
                $p['_skill']    = (int)$r['skill_points'];
                $out[] = $p;
            }
            // Formacao OFICIAL (constants "guild_battle_tactics", dialogo de
            // taticas da guilda): 1="Na sequencia" nivel menor primeiro;
            // 3="Fortaleza" nivel maior primeiro; 5="Confusao" aleatoria.
            // Empates de nivel seguem os pontos de habilidade, na direcao da
            // formacao. O guildSideProfiles numera position por esta ordem.
            if ($formation === 5) {
                shuffle($out);
            } elseif ($formation === 3) {
                usort($out, static fn(array $a, array $b): int =>
                    [(int)($b['level'] ?? 0), $b['_skill']] <=> [(int)($a['level'] ?? 0), $a['_skill']]);
            } else {
                usort($out, static fn(array $a, array $b): int =>
                    [(int)($a['level'] ?? 0), $a['_skill']] <=> [(int)($b['level'] ?? 0), $b['_skill']]);
            }
            return $out;
        };

        $attackers = $loadSide(
            json_decode((string)$battle['attacker_character_ids'], true) ?: [],
            $skillFactor((int)$battle['guild_attacker_id']),
            (int)(Db::value('SELECT guild_battle_tactics_attack_order FROM `guild` WHERE id = ?', [(int)$battle['guild_attacker_id']]) ?? 1)
        );
        $defenders = $loadSide(
            json_decode((string)$battle['defender_character_ids'], true) ?: [],
            $skillFactor((int)$battle['guild_defender_id']),
            (int)(Db::value('SELECT guild_battle_tactics_defense_order FROM `guild` WHERE id = ?', [(int)$battle['guild_defender_id']]) ?? 1)
        );

        // --- lutas sequenciais (cada confronto comeca com os DOIS a vida cheia) ---
        // O cliente reinicia as barras ao total a cada matchup (_barB.init(
        // get_totalHitPoints())) e reproduz os rounds somando o dano gravado:
        // HP carregado de uma luta anterior nao tem representacao visual e
        // fazia a barra passar do zero (o "lutou com 0 hp" da batalha #15).
        $rounds = [];
        $ai = 0; $di = 0;
        $winner = 0; // 1 = atacante, 2 = defensor
        while ($winner === 0) {
            if ($ai >= count($attackers)) { $winner = 2; break; }
            if ($di >= count($defenders)) { $winner = 1; break; }
            $fight = new Fight($attackers[$ai], $defenders[$di], true);
            $result = $fight->fight();
            $rounds = array_merge($rounds, $fight->rounds());
            if ($result === 1) {
                $defenders[$di]['hitpoints'] = 0;
                $di++;
            } else {
                $attackers[$ai]['hitpoints'] = 0;
                $ai++;
            }
        }

        $gA = (int)$battle['guild_attacker_id'];
        $gD = (int)$battle['guild_defender_id'];

        // --- honra das guildas (formulas do servidor antigo, x10) ---
        $honorA = (int)(Db::value('SELECT honor FROM `guild` WHERE id = ?', [$gA]) ?? 0);
        $honorD = (int)(Db::value('SELECT honor FROM `guild` WHERE id = ?', [$gD]) ?? 0);
        [$winHonor, $loseHonor] = self::guildBattleHonorRewards(
            $winner === 1 ? $honorA : $honorD,
            $winner === 1 ? $honorD : $honorA
        );
        if ($winner === 1) {
            Db::exec('UPDATE `guild` SET honor = GREATEST(0, honor + ?) WHERE id = ?', [$winHonor, $gA]);
            Db::exec('UPDATE `guild` SET honor = GREATEST(0, honor + ?) WHERE id = ?', [$loseHonor, $gD]);
        } else {
            Db::exec('UPDATE `guild` SET honor = GREATEST(0, honor + ?) WHERE id = ?', [$winHonor, $gD]);
            Db::exec('UPDATE `guild` SET honor = GREATEST(0, honor + ?) WHERE id = ?', [$loseHonor, $gA]);
        }

        // --- recompensas por membro (type 1 = atacante, 2 = defensor) ---
        $memberReward = static function (array $side, bool $won, int $type) use ($battle): void {
            foreach ($side as $p) {
                $coins = 0;
                if ($won) {
                    $lvl = (int)($p['level'] ?? 1);
                    $coins = (int)(floor((mt_rand(70, 80) / 100) * pow($lvl + 10, 2) * 10 / 10) / 10) * 10;
                }
                $exists = Db::row(
                    'SELECT id FROM `guild_battle_rewards` WHERE guild_battle_id = ? AND character_id = ? AND type = ?',
                    [(int)$battle['id'], $p['_cid'], $type]
                );
                if ($exists === null) {
                    Db::exec(
                        'INSERT INTO `guild_battle_rewards` (guild_battle_id, character_id, game_currency, item_id, status, type)
                         VALUES (?, ?, ?, 0, 1, ?)',
                        [(int)$battle['id'], $p['_cid'], $coins, $type]
                    );
                }
            }
        };
        $memberReward($attackers, $winner === 1, 1);
        $memberReward($defenders, $winner === 2, 2);

        // Aponta a batalha terminada no personagem: o cliente SO abre o dialogo
        // de resultado se character.finished_guild_battle_attack_id /
        // _defense_id == battle.id (xf.checkForFinishedGuildBattles).
        foreach ($attackers as $p) {
            Db::exec('UPDATE `character` SET finished_guild_battle_attack_id = ? WHERE id = ?', [$battleId, $p['_cid']]);
        }
        foreach ($defenders as $p) {
            Db::exec('UPDATE `character` SET finished_guild_battle_defense_id = ? WHERE id = ?', [$battleId, $p['_cid']]);
        }

        // --- trofeus: roubo/emissao no fim da batalha (docs/trofeus/implementacao.md) ---
        // O cliente le artifact_id/artifact_stolen das rewards do LADO correspondente
        // (se.get_winnerReward/get_loserReward → new ec(json)): lado vencedor mostra
        // "ganhamos trofeu" (DF), lado perdedor com id≠0 mostra "perdemos" (CF).
        // getInt tem default 0 no cliente, logo chaves ausentes sao inofensivas.
        $artifactEvent = \HeroZero\GuildArtifacts::resolveBattleTransfer(
            $winner === 1 ? $gA : $gD,
            $winner === 1 ? $gD : $gA,
            $winner === 1
        );
        $attackerRewards = ['honor' => $winner === 1 ? $winHonor : $loseHonor];
        $defenderRewards = ['honor' => $winner === 2 ? $winHonor : $loseHonor];
        if ($artifactEvent !== null) {
            $winnerSide = &$attackerRewards;
            $loserSide  = &$defenderRewards;
            if ($winner === 2) {
                $winnerSide = &$defenderRewards;
                $loserSide  = &$attackerRewards;
            }
            $winnerSide['artifact_id'] = $artifactEvent['artifact_id'];
            $winnerSide['artifact_stolen'] = $artifactEvent['stolen'];
            if ($artifactEvent['stolen']) {
                $loserSide['artifact_id'] = $artifactEvent['artifact_id'];
            }
            unset($winnerSide, $loserSide);
        }

        // --- grava resultado ---
        Db::exec(
            'UPDATE `guild_battle` SET status = 3, rounds = ?, attacker_character_profiles = ?,
             defender_character_profiles = ?, guild_winner_id = ?, attacker_rewards = ?, defender_rewards = ?
             WHERE id = ?',
            [
                json_encode($rounds),
                json_encode(self::guildSideProfiles($attackers)),
                json_encode(self::guildSideProfiles($defenders)),
                $winner === 1 ? $gA : $gD,
                json_encode($attackerRewards),
                json_encode($defenderRewards),
                $battleId,
            ]
        );
        Db::exec('UPDATE `guild` SET pending_guild_battle_attack_id = 0 WHERE id = ?', [$gA]);
        Db::exec('UPDATE `guild` SET pending_guild_battle_defense_id = 0 WHERE id = ?', [$gD]);
    }

    /**
     * Honra de guild battle: win = duelHonorWinReward x10, lose =
     * duelHonorLostReward x10 (negativo). constants pvp_honor_* oficiais.
     * @return array{0:int,1:int} [honraVencedor, honraPerdedor(negativa)]
     */
    private static function guildBattleHonorRewards(int $honorWinner, int $honorLoser): array
    {
        $expBetter = 3.0; // pvp_honor_win_exp_better
        $expWorse  = 3.0; // pvp_honor_win_exp_worse
        if ($honorWinner > $honorLoser) {
            $f = 1 - pow($honorLoser / max(1, $honorWinner), $expWorse);
        } elseif ($honorLoser == 0) {
            $f = 1.0;
        } else {
            $f = -1 * (1 - pow($honorWinner / $honorLoser, $expBetter));
        }
        $win = (int)round((100 - ($f * 100)) * 10);
        // lose: min(0.75 * win, 0.05 * honorLoser)
        $loss = (int)round(min(0.75 * $win, 0.05 * $honorLoser));
        return [$win, -$loss];
    }

    /** Mapa char_id → aparecimento para o ecra de batalha (formato oficial). */
    private static function guildSideProfiles(array $side): array
    {
        $out = [];
        $pos = 1;
        foreach ($side as $p) {
            $p = self::clientAppearance($p);
            unset($p['_skill']);
            $p['position'] = $pos++;
            // damage_bonus obrigatorio (tooltip le getInt e lanca se faltar).
            $p['damage_bonus'] = (int)($p['strength'] ?? 0) + (int)($p['weapondamage'] ?? 0);
            $out[(string)$p['_cid']] = $p;
            unset($out[(string)$p['_cid']]['_cid']);
        }
        return $out;
    }

    /** A aparência usa identificadores; a lista de munições pertence ao simulador. */
    private static function clientAppearance(array $profile): array
    {
        foreach (['missiles', 'missiles1', 'missiles2', 'missiles3', 'missiles4'] as $field) {
            if (array_key_exists($field, $profile)
                && (!is_string($profile[$field]) || $profile[$field] === '')) {
                $profile[$field] = null;
            }
        }
        return $profile;
    }

    /** Normaliza também batalhas antigas ao servir login, sync e histórico. */
    public static function clientProfiles(string $json): string
    {
        $profiles = json_decode($json, true);
        if (!is_array($profiles) || $profiles === []) return $json;
        foreach ($profiles as &$profile) {
            if (is_array($profile)) $profile = self::clientAppearance($profile);
        }
        unset($profile);
        return json_encode($profiles, JSON_THROW_ON_ERROR);
    }
}
