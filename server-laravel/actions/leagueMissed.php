<?php
declare(strict_types=1);

use HeroZero\GameTime;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Fight;
use HeroZero\GameError;
use HeroZero\GuildArtifacts;
use HeroZero\League;
use HeroZero\Live;

/**
 * Liga (espelha o fluxo de duelo na tabela league_fight) e duelos/lutas perdidas.
 *
 * Contrato do cliente (bundle html5_257):
 *  - league_opponents: lista de { opponent, opponent_inventory, opponent_inventory_items }
 *  - startLeagueFight: chaves league_fight + battle + opponent(+inventory/items)
 *  - missed_duels / missed_league_fights: inteiros (contadores)
 *
 * Regras da liga (docs/implementar_leagues.md): stamina propria (20, custo 20,
 * 4 donuts sem stamina), limite diario (20 + trofeus Coragem), ELO oficial do
 * cliente (K=11/multi=1200/+5+bonus), bonus de atributos e moedas por divisao,
 * mudanca de divisao com histerese devolvida em `league_division_change`.
 */
$handle = function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    /** Linha fresca das colunas usadas pela liga. */
    $leagueRow = static fn(int $id): array => Db::row(
        'SELECT id, user_id, guild_id, honor, level, active_league_fight_id,
                league_group_id, league_points, league_opponents, league_stamina,
                max_league_stamina, ts_last_league_stamina_change, ts_last_league_fight,
                league_fight_count, active_league_booster_id, ts_active_league_boost_expires,
                ts_last_league_opponents_refresh
           FROM `character` WHERE id = ?',
        [$id]
    ) ?? [];

    /** Mesmo dia (no RELOGIO DE JOGO) de um timestamp e de agora. */
    $sameGameDay = static function (int $ts): bool {
        $tz = Live::gameTimezone();
        return (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d')
            === (new \DateTimeImmutable('@' . GameTime::now()))->setTimezone($tz)->format('Y-m-d');
    };

    /** Debita donuts do USER (mesmo padrao do startDuel). */
    $spendPremium = static function (Character $c, int $cost): void {
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $c->userId(), $cost]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    };

    /**
     * Resumo do oponente (shape `Gl` lida pelo cliente nos paineis de duelos
     * perdidos): id/name/gender/level/honor/online_status + stat_total_* +
     * stat_weapon_damage + league_group_id.
     */
    $opponentSummary = static function (int $oppId): array {
        $r = Db::row('SELECT * FROM `character` WHERE id = ?', [$oppId]);
        if ($r === null) {
            return [];
        }
        $profile = Character::load($oppId)->battleProfile('a');
        return [
            'id'                         => (int)$r['id'],
            'name'                       => (string)$r['name'],
            'gender'                     => (string)$r['gender'],
            'level'                      => (int)$r['level'],
            'honor'                      => (int)$r['honor'],
            'online_status'              => (\HeroZero\GameTime::now() - (int)($r['ts_last_action'] ?? 0)) < 300 ? 1 : 2,
            'stat_total_stamina'         => (int)$profile['stamina'],
            'stat_total_strength'        => (int)$profile['strength'],
            'stat_total_critical_rating' => (int)$profile['criticalrating'],
            'stat_total_dodge_rating'    => (int)$profile['dodgerating'],
            'stat_weapon_damage'         => (int)$profile['weapondamage'],
            'league_group_id'            => 0,
        ];
    };

    switch ($action) {
        case 'getLeagueOpponents': {
            League::ensureSeason();
            // Serve a lista PERSISTIDA: reabrir o painel mostra os MESMOS 3 alvos;
            // so sorteia se ainda nao houver lista. Antes re-sorteava sempre --
            // refresh gratis infinito (oficial: 1x/dia gratis, depois 2 donuts).
            $ids = Live::savedLeagueOpponentIds($cid);
            $data = Live::accountState($userId);
            $data['league_opponents'] = $ids === [] ? Live::leagueOpponents($cid) : Live::renderLeagueOpponents($ids);
            return $data;
        }

        case 'refreshLeagueOpponents': {
            League::ensureSeason();
            $row = $leagueRow($cid);
            // Gratis apenas UMA vez por dia (relogio de jogo); nas outras, so com
            // use_premium -- sem ele o cliente espera errRefreshLeagueOpponentsNotYetAllowed.
            if ($sameGameDay((int)($row['ts_last_league_opponents_refresh'] ?? 0))) {
                $usePremium = filter_var($params['use_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (!$usePremium) {
                    throw new GameError('errRefreshLeagueOpponentsNotYetAllowed');
                }
                $spendPremium($char, (int)League::constant('league_change_opponents_premium_currency_amount'));
            }
            Db::exec('UPDATE `character` SET ts_last_league_opponents_refresh = ? WHERE id = ?', [GameTime::now(), $cid]);
            // Sorteia ANTES do accountState: a lista persiste em
            // character.league_opponents e o char ecoado ja sai com ela.
            $data = Live::accountState($userId);
            $data['league_opponents'] = Live::leagueOpponents($cid);
            return $data;
        }

        case 'startLeagueFight': {
            League::ensureSeason();
            $now = GameTime::now();
            $row = $leagueRow($cid);

            // Caminho de RETOMA, paridade com startDuel: luta em aberto (status 1/2)
            // volta a ser servida; status 3 ou fight desaparecida limpa o ponteiro.
            // Sem retoma, reload no meio da luta deixava active_league_fight_id preso
            // (errStartLeagueFightActiveLeagueFightFound eterno).
            $resumeFight = null;
            $activeFightId = (int)($row['active_league_fight_id'] ?? 0);
            if ($activeFightId !== 0) {
                $active = Db::row('SELECT * FROM `league_fight` WHERE id = ? AND character_a_id = ?', [$activeFightId, $cid]);
                if ($active === null || (int)$active['character_a_status'] === 3) {
                    Db::exec('UPDATE `character` SET active_league_fight_id = 0 WHERE id = ?', [$cid]);
                    $row['active_league_fight_id'] = 0;
                } elseif (in_array((int)$active['character_a_status'], [1, 2], true)) {
                    $resumeFight = $active;
                }
            }

            $boot = Live::template('autoLoginUser');
            if ($resumeFight !== null) {
                $opponentId = (int)$resumeFight['character_b_id'];
                $data = Live::accountState($userId);
                $data['league_fight'] = $resumeFight;
                $data['battle'] = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$resumeFight['battle_id']]) ?? [];
                unset($data['battles']); // battles[] do boot faz getBattleFromId falhar -> refreshBattle(null) -> crash
                $data['opponent'] = Live::requestedCharacter($opponentId, $boot['character'] ?? []);
                $data['opponent_inventory'] = Live::inventoryForCharacter($opponentId, $boot['inventory'] ?? []);
                $data['opponent_inventory_items'] = Live::itemsForCharacter($opponentId, $boot['items'][0] ?? []);
                return $data;
            }

            // Portas de entrada: inscrito + honra minima (codigos existem no client).
            if ((int)($row['league_group_id'] ?? 0) === 0) {
                throw new GameError('errLeagueNotJoined');
            }
            if ((int)($row['honor'] ?? 0) < (int)League::constant('league_min_honor_points')) {
                throw new GameError('errStartLeagueFightMinHonorNotReached');
            }

            // Stamina/contador sincronizados ANTES de validar custo (mesmo motivo
            // do syncDuelStamina no duelo: coluna so atualiza quando sincronizada).
            $row = League::syncStamina($row);
            $row = League::syncDailyCount($row);
            if ((int)($row['league_fight_count'] ?? 0) >= League::maxDailyFights((int)($row['guild_id'] ?? 0))) {
                // Sem codigo dedicado no bundle (o cliente desativa o ataque com
                // countLeftToday==0); rede defensiva -> reportError generico.
                throw new GameError('errStartLeagueFightDailyLimitReached');
            }

            // Custo: 20 de stamina de liga OU 4 donuts (sem stamina o cliente
            // oferece pagar; recusando sem stamina, erro dedicado).
            $cost = (int)League::constant('league_stamina_cost');
            $useStamina = (int)($row['league_stamina'] ?? 0) >= $cost;
            if (!$useStamina) {
                $usePremium = filter_var($params['use_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (!$usePremium) {
                    throw new GameError('errRemoveDuelStaminaNotEnough');
                }
                $spendPremium($char, (int)League::constant('league_attack_opponent_premium_currency_amount'));
            }

            // O alvo tem de estar na lista OFERECIDA (o cliente so permite atacar
            // os 3; antes aceitava qualquer id -> farm de alvos faceis).
            $opponentId = (int)($params['character_id'] ?? 0);
            $offeredIds = Live::savedLeagueOpponentIds($cid);
            if ($offeredIds === []) {
                Live::leagueOpponents($cid); // conta antiga sem lista: sorteia agora
                $offeredIds = Live::savedLeagueOpponentIds($cid);
            }
            if (!in_array($opponentId, $offeredIds, true)) {
                throw new GameError('errStartLeagueFightInvalidLeagueOpponent');
            }

            // Stats REAIS dos dois lados; o bonus de atributos da propria divisao
            // JA vem dentro de battleProfile (Character::statMultiplier soma o
            // fator da liga ao booster/guilda, valendo em todos os combates).
            // NAO reaplicar aqui sobre o perfil pronto: seria contado duas vezes.
            $oppChar = Character::load($opponentId);
            $myDiv = League::divisionFromGroup((int)$row['league_group_id']);
            $oppDivRow = Db::row('SELECT league_group_id, league_points, level, guild_id FROM `character` WHERE id = ?', [$opponentId]) ?? [];
            $oppDiv = League::divisionFromGroup((int)($oppDivRow['league_group_id'] ?? 0));
            $profileA = $char->battleProfile('a');
            $profileB = $oppChar->battleProfile('a');
            $profileB['profile'] = 'b';

            // COMBATE SIMULADO real (idem duelo): rounds + aparencias obrigatorias,
            // senao o painel de batalha crasha ("LOC"/reload).
            $fight = new Fight($profileA, $profileB);
            $winnerSide = $fight->fight() === 1 ? 'a' : 'b';
            $rounds = ['rounds' => $fight->rounds()];
            $rounds['profile_a_appearance'] = $char->battleAppearance();
            $rounds['profile_b_appearance'] = $oppChar->battleAppearance();

            Db::exec(
                "INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds) VALUES (?, ?, ?, ?, ?)",
                [$now, json_encode($profileA, JSON_UNESCAPED_SLASHES), json_encode($profileB, JSON_UNESCAPED_SLASHES), $winnerSide, json_encode($rounds, JSON_UNESCAPED_SLASHES)]
            );
            $battleId = (int)Db::pdo()->lastInsertId(); // BUG-000037: capturar JA
            \HeroZero\Missiles::consume($cid, $fight->missileUsage('a'));

            // MESMA TEAM: ninguem ganha nem perde pontos de liga (regra oficial;
            // bundle getLeagueFightResultTooltip so chama os calculos ELO quando
            // !opponent.get_isMyGuildMember(), i18n "screen/league_fight/
            // tooltip_same_team"). Moedas mantem-se em qualquer caso.
            $sameTeam = (int)($row['guild_id'] ?? 0) > 0
                && (int)($row['guild_id'] ?? 0) === (int)($oppDivRow['guild_id'] ?? 0);

            // ELO oficial do cliente. Multiplicadores de QUEM GANHA (booster de
            // duelo da guilda dele x trofeus type 4) -- pontos de vitoria levam
            // mult (tooltip promete), derrota NUNCA. Moedas: DuelRewards(nivel do
            // VENCIDO) x mults x bonus de divisao do VENCEDOR (fator 1+bonus).
            $myPts = (int)($row['league_points'] ?? 0);
            $oppPts = (int)($oppDivRow['league_points'] ?? 0);
            if ($winnerSide === 'a') {
                [$m1, $m2] = League::guildMultipliers($char->guildBoosterAmount('duel'), (int)($row['guild_id'] ?? 0));
                $aRewards = [
                    'coins'         => League::coinsWin($myDiv, (int)($oppDivRow['level'] ?? 1), $m1, $m2),
                    'league_points' => $sameTeam ? 0 : League::eloWin($myPts, $oppPts, $m1, $m2),
                ];
                $bRewards = ['coins' => 0, 'league_points' => $sameTeam ? 0 : League::eloLoss($oppPts, $myPts)];
            } else {
                [$m1, $m2] = League::guildMultipliers($oppChar->guildBoosterAmount('duel'), (int)$oppChar->field('guild_id'));
                $bRewards = [
                    'coins'         => League::coinsWin($oppDiv, (int)$char->field('level'), $m1, $m2),
                    'league_points' => $sameTeam ? 0 : League::eloWin($oppPts, $myPts, $m1, $m2),
                ];
                $aRewards = ['coins' => 0, 'league_points' => $sameTeam ? 0 : League::eloLoss($myPts, $oppPts)];
            }

            Db::exec("INSERT INTO `league_fight` (ts_creation, battle_id, character_a_id, character_b_id, character_a_status, character_b_status, character_a_rewards, character_b_rewards, unread)
                      VALUES (?, ?, ?, ?, 1, 1, ?, ?, 'true')",
                [$now, $battleId, $cid, $opponentId,
                 json_encode($aRewards, JSON_UNESCAPED_SLASHES),
                 json_encode($bRewards, JSON_UNESCAPED_SLASHES)]);
            $fightId = (int)Db::pdo()->lastInsertId(); // TEM de vir ANTES dos UPDATEs (BUG-000037)

            // Recompensas do DEFENSOR aplicadas IMEDIATAMENTE (1:1 com o oficial
            // startLeagueFight.req.php), pelo mesmo motivo dos duelos: ranking/
            // perfil refletem sem esperar que o defensor reclame.
            Db::exec('UPDATE `character` SET game_currency = game_currency + ?, league_points = GREATEST(0, league_points + ?) WHERE id = ?',
                [(int)($bRewards['coins'] ?? 0), (int)($bRewards['league_points'] ?? 0), $opponentId]);

            // Atacante: consome stamina (se for o caso), marca o dia, empilha a
            // luta e prende o ponteiro active_league_fight_id (persistido!).
            // Barra CHEIA: o relogio de regeneracao so arranca agora (estava parado
            // -- syncStamina acabou de por ts=now nesse caso); parcial: preserva.
            $wasFull = (int)($row['league_stamina'] ?? 0) >= GuildArtifacts::effectiveMax(
                'league_stamina',
                (int)($row['max_league_stamina'] ?? 20),
                (int)($row['guild_id'] ?? 0)
            );
            $spendSql = $useStamina
                ? ($wasFull
                    ? 'league_stamina = GREATEST(0, league_stamina - ?), ts_last_league_stamina_change = ?,'
                    : 'league_stamina = GREATEST(0, league_stamina - ?),')
                : '';
            $spendArgs = $useStamina ? ($wasFull ? [$cost, $now] : [$cost]) : [];
            Db::exec("UPDATE `character` SET {$spendSql} active_league_fight_id = ?, ts_last_league_fight = ?, league_fight_count = league_fight_count + 1 WHERE id = ?",
                [...$spendArgs, $fightId, $now, $cid]);

            // O cliente manda refresh_opponents=true: novos 3 alvos para a proxima
            // (ANTES do accountState, mesma razao do getLeagueOpponents).
            $refreshed = !empty($params['refresh_opponents']) ? Live::leagueOpponents($cid) : null;

            $data = Live::accountState($userId);
            $data['league_fight'] = Db::row('SELECT * FROM `league_fight` WHERE id = ?', [$fightId]) ?? [];
            $data['battle'] = Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]) ?? [];
            unset($data['battles']); // battles[] do boot faz getBattleFromId falhar -> refreshBattle(null) -> crash
            // Templates do boot: template raso ([]) deixaria o opponent sem
            // name/gender/level e a visualizacao da luta crasha (aviso do getCharacter.php).
            $data['opponent'] = Live::requestedCharacter($opponentId, $boot['character'] ?? []);
            $data['opponent_inventory'] = Live::inventoryForCharacter($opponentId, $boot['inventory'] ?? []);
            $data['opponent_inventory_items'] = Live::itemsForCharacter($opponentId, $boot['items'][0] ?? []);
            if ($refreshed !== null) {
                $data['league_opponents'] = $refreshed;
            }
            return $data;
        }

        case 'checkForLeagueFightComplete': {
            League::ensureSeason();
            $fight = Db::row('SELECT * FROM `league_fight` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$cid]);
            $data = Live::accountState($userId);
            if ($fight) {
                Db::exec('UPDATE `league_fight` SET character_a_status = 2 WHERE id = ?', [(int)$fight['id']]);
                $fight['character_a_status'] = 2;
                $data['league_fight'] = $fight;
                $data['battle'] = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$fight['battle_id']]) ?? [];
            }
            return $data;
        }

        case 'claimLeagueFightRewards': {
            League::ensureSeason();
            // Sem luta pendente o cliente espera este erro (botao so existe com
            // luta ativa; chamada duplicada/Reload chega aqui).
            $fight = Db::row('SELECT * FROM `league_fight` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$cid]);
            if ($fight === null) {
                throw new GameError('errClaimLeagueFightRewardsNoActiveLeagueFight');
            }
            $rewards = json_decode((string)$fight['character_a_rewards'], true) ?: [];
            Db::exec('UPDATE `character`
                         SET game_currency = game_currency + ?,
                             league_points = GREATEST(0, league_points + ?),
                             active_league_fight_id = 0
                       WHERE id = ?',
                [(int)($rewards['coins'] ?? 0), (int)($rewards['league_points'] ?? 0), $cid]);
            Db::exec('UPDATE `league_fight` SET character_a_status = 3 WHERE id = ?', [(int)$fight['id']]);
            \HeroZero\DailyMilestones::record($cid, 1, \HeroZero\DailyMilestones::LEAGUE);
            // Mudanca de divisao DEPOIS de creditar (histerese oficial): "X_Y"
            // dispara o dialogo de subida quando X > Y. Linha FRESCA pos-credito.
            $fresh = Db::row('SELECT league_group_id, league_points FROM `character` WHERE id = ?', [$cid]) ?? [];
            $divisionChange = League::applyDivisionChange($fresh, $cid);
            $data = Live::accountState($userId);
            if ($divisionChange !== null) {
                $data['league_division_change'] = $divisionChange;
            }
            return $data;
        }

        case 'deleteMissedDuels':
        case 'deleteAllMissedDuels':
            Db::exec('UPDATE `duel` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            break;

        case 'deleteMissedLeagueFights':
        case 'deleteAllMissedLeagueFights':
            Db::exec('UPDATE `league_fight` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            break;

        case 'claimMissedDuelsRewards': {
            // NAO aplica recompensas: as character_b_rewards ja sao creditadas
            // AO DEFENSOR no momento do ataque (igual ao oficial
            // startDuel.req.php), para a honra do ranking/perfil atualizar sem
            // depender de o defensor reclamar. Aqui so fecha as entradas.
            Db::exec('UPDATE `duel` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            break;
        }

        case 'claimMissedLeagueFightsRewards': {
            // Idem aos duelos: ja aplicado no momento da luta; so fecha.
            Db::exec('UPDATE `league_fight` SET character_b_status = 3 WHERE character_b_id = ? AND character_b_status IN (1,2)', [$cid]);
            break;
        }

        // getMissedDuel/getMissedDuelsNew + irmas da liga: o cliente so abre o
        // painel de duelos perdidos se missed_duels > 0 (contadores do
        // accountState/boot) e depois disto puxa a lista e o detalhe aqui.

        case 'getMissedDuelsNew': {
            // Lista (shape `sw`): id, opponent_id, ts_creation, unread, winner,
            // character_b_rewards. get_isWon() do cliente = winner=='b' (o
            // defensor e o lado b na linha crua) -- servir SEM troca.
            $rows = Db::rows(
                'SELECT d.*, b.winner AS battle_winner FROM `duel` d
                   JOIN `battle` b ON b.id = d.battle_id
                  WHERE d.character_b_id = ? AND d.character_b_status IN (1,2)
                  ORDER BY d.id DESC',
                [$cid]
            );
            $entries = [];
            $opponents = [];
            $seen = [];
            foreach ($rows as $d) {
                $entries[] = [
                    'id'                  => (int)$d['id'],
                    'opponent_id'         => (int)$d['character_a_id'],
                    'ts_creation'         => (int)$d['ts_creation'],
                    'unread'              => true,
                    'winner'              => (string)$d['battle_winner'],
                    'character_b_rewards' => (string)$d['character_b_rewards'],
                ];
                $aid = (int)$d['character_a_id'];
                if (!isset($seen[$aid])) {
                    $seen[$aid] = true;
                    $opp = $opponentSummary($aid);
                    if ($opp !== []) {
                        $opponents[] = $opp;
                    }
                }
            }
            $data = Live::accountState($userId);
            $data['missed_duel_data'] = $entries;
            $data['missed_duel_opponents'] = $opponents;
            return $data;
        }

        case 'getMissedDuel': {
            // Detalhe (duel_id): missed_duel (shape `Yy`: id/battle_id/
            // character_a_rewards/character_a_status) + missed_duel_battle.
            // A batalha vai CRUA (profile_a=atacante, profile_b=defensor): o
            // cliente (En.get_selfCharacter = profileB + branches
            // get_isMissedDuel no painel de batalha) inverte os lados ele
            // proprio -- espectador fica a ESQUERDA com os stats dele e o
            // atacante a DIREITA. get_isWon()=winner=='a' dai sai correto
            // ('a' ganhou -> espectador perdeu). Pre-trocar lados duplicava a
            // inversao e corrompia os rounds (sem chave 'd') -> BUG-000073.
            // decrease_counter=1 faz o cliente baixar a bolha localmente.
            $duelId = (int)($params['duel_id'] ?? 0);
            $d = Db::row('SELECT * FROM `duel` WHERE id = ? AND character_b_id = ?', [$duelId, $cid]);
            if ($d === null) {
                return Live::accountState($userId);
            }
            $battle = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$d['battle_id']]);
            if ($battle === null) {
                return Live::accountState($userId);
            }
            $boot = Live::template('autoLoginUser');
            $data = Live::accountState($userId);
            $data['missed_duel'] = [
                'id'                  => (int)$d['id'],
                'battle_id'           => (int)$d['battle_id'],
                'character_a_rewards' => (string)$d['character_b_rewards'],
                'character_a_status'  => (int)$d['character_b_status'],
            ];
            $data['missed_duel_battle'] = Live::shapeLike($boot['battle'] ?? [], $battle);
            $data['decrease_counter'] = 1;
            return $data;
        }

        case 'getMissedLeagueFights': {
            // Mesma shape `sw`/`kga` dos duelos, sobre league_fight.
            $rows = Db::rows(
                'SELECT f.*, b.winner AS battle_winner FROM `league_fight` f
                   JOIN `battle` b ON b.id = f.battle_id
                  WHERE f.character_b_id = ? AND f.character_b_status IN (1,2)
                  ORDER BY f.id DESC',
                [$cid]
            );
            $entries = [];
            $opponents = [];
            $seen = [];
            foreach ($rows as $f) {
                $entries[] = [
                    'id'                  => (int)$f['id'],
                    'opponent_id'         => (int)$f['character_a_id'],
                    'ts_creation'         => (int)$f['ts_creation'],
                    'unread'              => true,
                    'winner'              => (string)$f['battle_winner'],
                    'character_b_rewards' => (string)$f['character_b_rewards'],
                ];
                $aid = (int)$f['character_a_id'];
                if (!isset($seen[$aid])) {
                    $seen[$aid] = true;
                    $opp = $opponentSummary($aid);
                    if ($opp !== []) {
                        $opponents[] = $opp;
                    }
                }
            }
            $data = Live::accountState($userId);
            $data['missed_league_fight_data'] = $entries;
            $data['missed_league_fight_opponents'] = $opponents;
            return $data;
        }

        case 'getMissedLeagueFight': {
            // Detalhe (league_fight_id): missed_league_fight +
            // missed_league_fight_battle. Batalha CRUA, mesmo raciocinio do
            // getMissedDuel (BUG-000073).
            $fightId = (int)($params['league_fight_id'] ?? 0);
            $f = Db::row('SELECT * FROM `league_fight` WHERE id = ? AND character_b_id = ?', [$fightId, $cid]);
            if ($f === null) {
                return Live::accountState($userId);
            }
            $battle = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$f['battle_id']]);
            if ($battle === null) {
                return Live::accountState($userId);
            }
            $boot = Live::template('autoLoginUser');
            $data = Live::accountState($userId);
            $data['missed_league_fight'] = [
                'id'                  => (int)$f['id'],
                'battle_id'           => (int)$f['battle_id'],
                'character_a_rewards' => (string)$f['character_b_rewards'],
                'character_a_status'  => (int)$f['character_b_status'],
            ];
            $data['missed_league_fight_battle'] = Live::shapeLike($boot['battle'] ?? [], $battle);
            $data['decrease_counter'] = 1;
            return $data;
        }

        // getMissedDuel / getMissedDuelsNew / getMissedLeagueFight(s): os
        // contadores missed_* ja saem no character do accountState; a lista
        // detalhada nao e persistida em formato proprio — eco.
        default:
            break;
    }

    return Live::accountState($userId);
};

return function (array $params) use ($handle): array {
    if (($params['action'] ?? '') !== 'startLeagueFight') return $handle($params);
    $cid = (int)Db::value('SELECT id FROM `character` WHERE user_id = ?', [(int)($params['user_id'] ?? 0)]);
    return \HeroZero\Missiles::transaction([$cid, (int)($params['character_id'] ?? 0)], fn() => $handle($params));
};
