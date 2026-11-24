<?php
declare(strict_types=1);
namespace HeroZero;

/** Vilões: catálogo oficial; escalas de dano e prémios monetários locais. */
final class Worldboss
{
    private static ?array $catalog = null;

    public static function constants(): array
    {
        if (self::$catalog !== null) return self::$catalog;
        $src = __DIR__.'/../../public/assets/data/constants_json.data';
        return self::$catalog = AssetCache::slice('worldboss_v2', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            return array_filter($all, static fn($key) => str_starts_with($key, 'worldboss_')
                || in_array($key, ['item_templates', 'item_pattern', 'npcs', 'quest_duration_short', 'quest_level_full_duration']), ARRAY_FILTER_USE_KEY);
        });
    }

    public static function canMultiAttack(int $current, int $total): bool
    {
        return $current >= (int)ceil($total * self::constants()['worldboss_event_multi_attack_min_hp']);
    }

    public static function duration(int $level, int $personal = 0, int $guild = 0): int
    {
        $c = self::constants();
        $seconds = (int)$c['quest_duration_short'] * min(1, $level / (int)$c['quest_level_full_duration']);
        $seconds = (int)floor($seconds * (1 - ($personal + $guild) / 100) + 0.5);
        return max(60, (int)(floor(($seconds + 30) / 60) * 60));
    }

    public static function poolReward(int $rank, int $participants): array
    {
        if ($rank < 1 || $participants < 1) return ['quest_energy'=>0, 'training_sessions'=>0];
        // Regra local: posição / participantes. A fórmula do servidor oficial não está no cliente.
        foreach (self::constants()['worldboss_event_rewards'] as $r) {
            if ($rank / $participants <= $r['max_pool_percentage']) {
                $out = ['quest_energy'=>0, 'training_sessions'=>0];
                foreach ([1, 2] as $n) $out[$r['reward'.$n.'_type'] === 1 ? 'quest_energy' : 'training_sessions'] = (int)$r['reward'.$n.'_value'];
                return $out;
            }
        }
        return ['quest_energy'=>0, 'training_sessions'=>0];
    }

    /** Famílias oficiais; seleção e probabilidades são regras locais. */
    public static function equipmentPool(string $identifier, bool $top): array
    {
        $c = self::constants(); $items = [];
        foreach ($c['item_pattern'] as $pattern => $row) {
            foreach ($row['sources'] ?? [] as $source => $ids) {
                if ($source === 'worldboss_event_'.$identifier
                    || ($source === 'worldboss_event_general' && $pattern === ($top ? 'ironman1' : 'frogman1'))) {
                    if ($source === 'worldboss_event_general' && !str_starts_with($identifier, 'default_event_stage')) continue;
                    foreach ($ids as $id) $items[] = $row['nonSetToSet'][$id] ?? $id;
                }
            }
        }
        // Festivos sem associação worldboss: equipamento do tema nas fontes oficiais da loja.
        if (!$items) {
            $theme = self::eventTheme($identifier);
            if ($theme !== null) foreach ($c['item_pattern'] as $row) {
                foreach ($row['sources']['shop_'.$theme] ?? [] as $id) {
                    $id = $row['nonSetToSet'][$id] ?? $id;
                    if (in_array((int)($c['item_templates'][$id]['type'] ?? 0), [1,2,3,4,5], true)) $items[] = $id;
                }
            }
        }
        if (!$items) return self::equipmentPool('default_event_stage1', $top);
        return array_values(array_unique($items));
    }

    private static function eventTheme(string $identifier): ?string
    {
        return match ($identifier) {
            'xmas_event_stage2'=>'xmas', 'easter_event_stage2'=>'easter',
            'halloween_event_stage2'=>'halloween', 'carnival_stage2'=>'carnival',
            'sci_fi_week_stage2'=>'sci_fi_week', 'video_games_week_stage2'=>'video_games_week',
            'olympia_event_stage2'=>'olympia', 'worldcup_event_stage2'=>'worldcup',
            'environmental_week_stage2'=>'environmental_week', default=>null,
        };
    }

    public static function petPool(string $identifier): array
    {
        // Regra pedida: nenhum pet em vilões dos arcos associados ao passe.
        if (str_ends_with($identifier, '_season_worldboss')) return [];
        $normal = str_starts_with($identifier, 'default_event_stage');
        $theme = self::eventTheme($identifier); $out = [];
        foreach (Sidekick::official()['templates'] as $id => $tpl) {
            if ((int)($tpl['quality'] ?? 0) >= 4 || str_contains($id, 'season') || str_starts_with($id, 'sidekick_ld_')) continue;
            $sources = Sidekick::official()['sources'][$id] ?? [];
            if (array_filter($sources, static fn($src) => str_contains($src, 'season') || str_starts_with($src, 'legendary_dungeon_'))) continue;
            if ($normal || ($theme !== null && array_filter($sources, static fn($src) => str_contains($src, $theme)))) $out[] = $id;
        }
        return $out;
    }

    public static function defaultRewards(string $identifier): array
    {
        $rank = self::equipmentPool($identifier, true);
        $pool = self::equipmentPool($identifier, false);
        return ['rank'=>$rank[array_rand($rank)], 'pool'=>$pool[array_rand($pool)]];
    }

    /** Roll 1–100 permite verificar o limite de pet sem depender do acaso. */
    public static function rewardPlan(string $identifier, int $rank, int $participants, bool $lastHit, bool $won, int $roll): array
    {
        $eligible = $won && $rank > 0 && ($rank <= 10 || $lastHit);
        $pets = $eligible ? self::petPool($identifier) : [];
        return ['eligible'=>$eligible, 'top'=>$rank === 1 || $lastHit,
            'extra'=>$eligible ? self::poolReward($rank, $participants) : ['quest_energy'=>0,'training_sessions'=>0],
            'pet'=>$roll <= 50 && $pets ? $pets[array_rand($pets)] : null];
    }

    public static function create(string $identifier, int $stage, int $hp, int $seconds, int $min = 1, int $max = 999): int
    {
        $template = self::constants()['worldboss_event_templates'][$identifier] ?? null;
        if (!$template || $stage < 1 || $stage > 14 || $hp < 1 || $hp > 2000000000
            || $seconds < 60 || $seconds > 604800 || $min < 1 || $max < $min) {
            throw new \InvalidArgumentException('Template, zona, vida, duração ou níveis inválidos.');
        }
        $rewards = self::defaultRewards($identifier);
        Db::exec('INSERT INTO worldboss_event (identifier, status, stage, min_level, max_level, npc_identifier,
            npc_hitpoints_total, npc_hitpoints_current, ts_end, reward_top_rank_item_identifier, reward_top_pool_item_identifier,
            npc_strength_percent, npc_critical_percent, npc_dodge_percent)
            VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$identifier, $stage, $min, $max, $template['npc_identifier'], $hp, $hp, GameTime::now()+$seconds, $rewards['rank'], $rewards['pool'], random_int(95,120), random_int(95,120), random_int(95,120)]);
        return (int)Db::pdo()->lastInsertId();
    }

    private static function event(int $id): array
    {
        $event = Db::row('SELECT * FROM worldboss_event WHERE id = ? FOR UPDATE', [$id])
            ?? throw new GameError('errStartWorldbossAttackInvalidWorldbossEvent');
        if ((int)$event['status'] === 1 && ((int)$event['ts_end'] <= GameTime::now() || (int)$event['npc_hitpoints_current'] <= 0)) {
            Db::exec('UPDATE worldboss_event SET status = 4 WHERE id = ?', [$id]);
            $event['status'] = 4;
        }
        // Eventos ativos anteriores à migração recebem fatores uma única vez sob lock.
        if ((int)$event['status'] === 1) {
            $updates = []; $values = [];
            foreach (['npc_strength_percent','npc_critical_percent','npc_dodge_percent'] as $field) {
                if ($event[$field] === null) {
                    $event[$field] = random_int(95,120); $updates[] = "$field = ?"; $values[] = $event[$field];
                }
            }
            if ($updates) { $values[] = $id; Db::exec('UPDATE worldboss_event SET '.implode(', ', $updates).' WHERE id = ?', $values); }
        }
        return $event;
    }

    private static function eligible(array $char, array $event): void
    {
        if ((int)$char['level'] < (int)$event['min_level'] || (int)$char['level'] > (int)$event['max_level']
            || (int)$char['max_quest_stage'] < (int)$event['stage']) throw new GameError('errStartWorldbossAttackInvalidWorldbossEvent');
    }

    private static function assign(array $char, array $event): void
    {
        self::eligible($char, $event);
        if ((int)$char['worldboss_event_id'] === (int)$event['id']) return;
        if ((int)$char['active_worldboss_attack_id'] > 0) throw new GameError('errStartWorldbossAttackActiveWorldbossAttackFound');
        if ((int)$char['worldboss_event_id'] > 0) {
            $old = Db::row('SELECT status FROM worldboss_event WHERE id = ?', [(int)$char['worldboss_event_id']]);
            $claimed = Db::value('SELECT claimed FROM worldboss_reward WHERE worldboss_event_id = ? AND character_id = ?', [(int)$char['worldboss_event_id'], (int)$char['id']]);
            if ($old && (self::ownCount((int)$char['worldboss_event_id'], (int)$char['id']) > 0 || (int)$old['status'] !== 1) && (int)$claimed !== 1) throw new GameError('errAssignWorldbossEventInvalidId');
        }
        Db::exec('UPDATE `character` SET worldboss_event_id = ?, worldboss_event_attack_count = ? WHERE id = ?',
            [(int)$event['id'], self::ownCount((int)$event['id'], (int)$char['id']), (int)$char['id']]);
    }

    private static function ownCount(int $eid, int $cid): int
    {
        return (int)Db::value('SELECT COALESCE(SUM(CASE WHEN status = 1 THEN JSON_LENGTH(battle_ids) ELSE iterations END),0) FROM worldboss_attack WHERE worldboss_event_id = ? AND character_id = ? AND status IN (1,3,4)', [$eid, $cid]);
    }

    private static function ranking(int $eid): array
    {
        // Leitura corrente sob o lock do evento; não bloquear outras personagens.
        $attacks = Db::rows('SELECT * FROM worldboss_attack WHERE worldboss_event_id = ? AND status IN (1,3,4) ORDER BY id FOR UPDATE', [$eid]);
        $rows = [];
        foreach ($attacks as $a) {
            if ((int)$a['status'] === 1) {
                $a['iterations'] = count(json_decode((string)$a['battle_ids'], true) ?? []);
                if (!$a['iterations']) continue;
            }
            $cid = (int)$a['character_id'];
            $rows[$cid] ??= ['character_id'=>$cid, 'name'=>(string)Db::value('SELECT name FROM `character` WHERE id = ?', [$cid]),
                'attacks'=>0, 'coins'=>0, 'xp'=>0, 'first'=>(int)$a['id']];
            $rows[$cid]['attacks'] += (int)$a['iterations'];
            $rows[$cid]['coins'] += (int)$a['coin_reward'];
            $rows[$cid]['xp'] += (int)$a['xp_reward'];
        }
        $rows = array_values($rows);
        usort($rows, static fn($a,$b) => ($b['attacks'] <=> $a['attacks']) ?: ($a['first'] <=> $b['first']));
        return $rows;
    }

    private static function debit(int $uid, int $amount): void
    {
        if (Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?', [$amount, $uid, $amount]) !== 1)
            throw new GameError('errRemovePremiumCurrencyNotEnough');
    }

    public static function action(int $cid, string $action, int $eid = 0, int $iterations = 1, bool $discard = false): array
    {
        if (!in_array($action, ['assignWorldbossEvent','startWorldbossAttack','abortWorldbossAttack',
            'instantFinishWorldbossAttack','checkForWorldbossAttackComplete','finishWorldbossAttack','claimWorldbossEventRewards'], true))
            throw new GameError('errRequestInvalidParameter');
        $resolvingAid = 0;
        do {
            // Cada combate confirma o seu dano e liberta os locks antes do seguinte.
            $result = StoryDungeon::transaction($cid, static function (array $char) use ($cid, $action, $eid, $iterations, $discard, &$resolvingAid): ?array {
                if ($action === 'assignWorldbossEvent' || $action === 'startWorldbossAttack') {
                    $event = self::event($eid);
                    if ((int)$event['status'] !== 1) throw new GameError('errStartWorldbossAttackInvalidWorldbossEvent');
                    self::assign($char, $event);
                    if ($action === 'assignWorldbossEvent') return [];
                    foreach (['active_worldboss_attack_id'=>'WorldbossAttack', 'active_duel_id'=>'Duel', 'active_league_fight_id'=>'LeagueFight',
                        'active_dungeon_quest_id'=>'DungeonQuest'] as $field=>$label) {
                        if ((int)($char[$field] ?? 0) > 0) throw new GameError('errStartWorldbossAttackActive'.$label.'Found');
                    }
                    if (Db::value('SELECT id FROM work WHERE character_id = ? AND status = 1 LIMIT 1', [$cid]))
                        throw new GameError('errStartWorldbossAttackActiveWorkFound');
                    if ((int)($char['active_quest_id'] ?? 0) > 0 && (int)($char['ts_active_multitasking_boost_expires'] ?? 0) <= GameTime::now())
                        throw new GameError('errStartWorldbossAttackActiveQuestFound');
                    $c = self::constants();
                    if (!in_array($iterations, [1, (int)$c['worldboss_event_multi_attack_iterations']], true)) throw new GameError('errRequestInvalidParameter');
                    if ($iterations > 1) {
                        if (!self::canMultiAttack((int)$event['npc_hitpoints_current'], (int)$event['npc_hitpoints_total']))
                            throw new GameError('errCreateWorldbossEventHitpointsToLow_'.$event['npc_hitpoints_current']);
                        self::debit((int)$char['user_id'], (int)$c['worldboss_event_multi_attack_premium_amount']);
                    }
                    $player = Character::load($cid);
                    $duration = self::duration((int)$char['level'], $player->boosterAmount('quest'), $player->guildBoosterAmount('quest'));
                    Db::exec('INSERT INTO worldboss_attack (worldboss_event_id, character_id, status, duration, duration_raw, ts_complete, iterations, battle_ids)
                        VALUES (?, ?, 1, ?, ?, ?, ?, ?)', [$eid, $cid, $duration*$iterations, $duration*$iterations, GameTime::now()+$duration*$iterations, $iterations, '[]']);
                    Db::exec('UPDATE `character` SET active_worldboss_attack_id = ? WHERE id = ?', [(int)Db::pdo()->lastInsertId(), $cid]);
                    return [];
                }
                if ($action === 'claimWorldbossEventRewards') {
                    $event = self::event($eid);
                    if ((int)$event['status'] !== 4 || (int)$char['worldboss_event_id'] !== $eid || (int)$char['active_worldboss_attack_id'] > 0)
                        throw new GameError('errClaimWorldbossEventRewardsInvalidStatus');
                    $reward = self::ensureReward($char, $event);
                    if ((int)$reward['claimed'] !== 0) throw new GameError('errClaimWorldbossEventRewardsInvalidStatus');
                    self::deliver($char, $reward, $discard);
                    Db::exec('UPDATE worldboss_reward SET claimed = 1 WHERE id = ?', [(int)$reward['id']]);
                    if ((int)$event['npc_hitpoints_current'] === 0 && self::ownCount($eid, $cid) > 0) {
                        EventQuests::record($cid, EventQuests::WORLDBOSS, 1, (string)$event['identifier']);
                        HeroCon::record($cid, 9);
                    }
                    Db::exec('UPDATE `character` SET worldboss_event_id = 0, worldboss_event_attack_count = 0 WHERE id = ?', [$cid]);
                    return [];
                }
                $aid = $resolvingAid ?: (int)$char['active_worldboss_attack_id'];
                $attack = Db::row('SELECT * FROM worldboss_attack WHERE id = ? AND character_id = ?', [$aid, $cid]);
                if (!$attack) throw new GameError(match ($action) {
                    'abortWorldbossAttack'=>'errAbortWorldbossAttackNoActiveWorldbossAttack',
                    'instantFinishWorldbossAttack'=>'errInstantFinishWorldbossAttackNoActiveWorldbossAttack',
                    'checkForWorldbossAttackComplete'=>'errCheckForWorldbossAttackCompleteNoActiveWorldbossAttack',
                    default=>'errFinishInvalidWorldbossAttackStatus',
                });
                if ($resolvingAid && (int)$attack['status'] === 4) return self::attackData($attack);
                $resolvingAid = $aid;
                $event = self::event((int)$attack['worldboss_event_id']);
                if ($action === 'abortWorldbossAttack') {
                    if ((int)$attack['status'] !== 1 || (json_decode((string)$attack['battle_ids'], true) ?? [])) throw new GameError('errFinishInvalidWorldbossAttackStatus');
                    Db::exec('UPDATE worldboss_attack SET status = 5 WHERE id = ?', [$aid]);
                    Db::exec('UPDATE `character` SET active_worldboss_attack_id = 0 WHERE id = ?', [$cid]);
                    return [];
                }
                if ($action === 'finishWorldbossAttack' && $eid !== (int)$attack['worldboss_event_id']) throw new GameError('errFinishInvalidWorldbossAttackStatus');
                if ((int)$attack['status'] === 1) {
                    if ((int)$event['status'] === 1) {
                        if ($action === 'instantFinishWorldbossAttack' && (int)$attack['ts_complete'] > GameTime::now())
                            self::debit((int)$char['user_id'], (int)self::constants()['worldboss_event_instant_finish_premium_amount']*(int)$attack['iterations']);
                        elseif ((int)$attack['ts_complete'] > GameTime::now()) throw new GameError('errFinishNotYetCompleted');
                        self::fight($char, $attack, $event);
                    } elseif (json_decode((string)$attack['battle_ids'], true) ?? []) {
                        // Preservar os combates já confirmados se outro herói matou o vilão.
                        Db::exec('UPDATE worldboss_attack SET status = 3, iterations = ?, ts_complete = ? WHERE id = ?',
                            [count(json_decode((string)$attack['battle_ids'], true)), GameTime::now(), $aid]);
                    } else {
                        // Evento terminado por outro jogador ou por timeout: libertar sem dano nem novos prémios.
                        Db::exec('UPDATE worldboss_attack SET status = 5 WHERE id = ?', [$aid]);
                        Db::exec('UPDATE `character` SET active_worldboss_attack_id = 0 WHERE id = ?', [$cid]);
                        return [];
                    }
                    $attack = Db::row('SELECT * FROM worldboss_attack WHERE id = ?', [$aid]);
                    if ((int)$attack['status'] === 1) return null;
                }
                if ((int)$attack['status'] !== 3) throw new GameError('errFinishInvalidWorldbossAttackStatus');
                $data = self::attackData($attack);
                if ($action === 'finishWorldbossAttack') {
                    HeroCon::record($cid, 8, (int)$attack['iterations']);
                    Db::exec('UPDATE worldboss_attack SET status = 4 WHERE id = ?', [$aid]);
                    Db::exec('UPDATE `character` SET active_worldboss_attack_id = 0 WHERE id = ?', [$cid]);
                }
                return $data;
            });
        } while ($result === null);
        return $result;
    }

    /** Vida global atual e fatores fixos do evento aplicados aos atributos do atacante. */
    public static function npcProfile(array $hero, array $event, int $hp): array
    {
        $strength = max(1, (int)round($hero['strength'] * (int)$event['npc_strength_percent'] / 100));
        $critical = max(1, (int)round($hero['criticalrating'] * (int)$event['npc_critical_percent'] / 100));
        $dodge = max(1, (int)round($hero['dodgerating'] * (int)$event['npc_dodge_percent'] / 100));
        return ['profile'=>'b', 'level'=>(int)$hero['level'], 'stamina'=>(int)ceil($hp/10),
            'strength'=>$strength, 'criticalrating'=>$critical, 'dodgerating'=>$dodge,
            'weapondamage'=>0, 'hitpoints'=>$hp, 'damage_normal'=>$strength, 'damage_bonus'=>0,
            'chance_critical'=>Fight::criticalChance($critical, (int)$hero['criticalrating']),
            'chance_dodge'=>Fight::dodgeChance($dodge, (int)$hero['dodgerating'])];
    }

    /** Balanceamento local: metade do valor final anterior por combate. */
    public static function attackRewards(int $level): array
    {
        return ['coins'=>intdiv(max(10,$level*20),2), 'xp'=>intdiv(max(5,$level*10),2)];
    }

    private static function fight(array $char, array $attack, array $event): void
    {
        $cid = (int)$char['id']; $eid = (int)$event['id']; $player = Character::load($cid);
        $hp = (int)$event['npc_hitpoints_current']; $total = (int)$attack['total_damage'];
        $ids = json_decode((string)$attack['battle_ids'], true) ?? [];
        // Balanceamento local: NPC adaptado ao herói; vida global mantém-se partilhada.
        if (count($ids) < (int)$attack['iterations'] && $hp > 0) {
            $a = $player->battleProfile('a');
            $b = self::npcProfile($a, $event, $hp);
            $a['chance_critical'] = Fight::criticalChance((int)$a['criticalrating'], (int)$b['criticalrating']);
            $a['chance_dodge'] = Fight::dodgeChance((int)$a['dodgerating'], (int)$b['dodgerating']);
            $fight = new Fight($a, $b);
            $winner = $fight->fight() === 1 ? 'a' : 'b';
            $rounds = $fight->rounds();
            $damage = min($hp, max(0, $hp - $fight->hitpointsB()));
            $hp -= $damage; $total += $damage;
            Db::exec('INSERT INTO battle (ts_creation, profile_a_stats, profile_b_stats, winner, rounds) VALUES (?, ?, ?, ?, ?)',
                [GameTime::now(), json_encode($a), json_encode($b), $winner, json_encode(['rounds'=>$rounds,
                    'profile_a_appearance'=>$player->battleAppearance(), 'profile_b_appearance'=>['npc_identifier'=>$event['npc_identifier']]])]);
            $ids[] = (int)Db::pdo()->lastInsertId();
            Missiles::consume($cid, $fight->missileUsage('a'));
        }
        $count = count($ids);
        $rewards = self::attackRewards((int)$char['level']);
        Db::exec('UPDATE worldboss_attack SET status = ?, ts_complete = ?, iterations = ?, battle_ids = ?, battle_id = ?, total_damage = ?, coin_reward = ?, xp_reward = ? WHERE id = ?',
            [$hp === 0 || $count >= (int)$attack['iterations'] ? 3 : 1, GameTime::now(), $hp === 0 ? $count : (int)$attack['iterations'], json_encode($ids), $ids[0] ?? 0, $total, $count*$rewards['coins'], $count*$rewards['xp'], (int)$attack['id']]);
        $ranking = self::ranking($eid); $top = $ranking[0] ?? ['character_id'=>0,'name'=>'','attacks'=>0];
        $winnerName = $event['winning_attacker_name'];
        if ($hp === 0) {
            $winnerName = $char['name'];

        }
        Db::exec('UPDATE worldboss_event SET npc_hitpoints_current = ?, attack_count = attack_count + ?, status = ?,
            top_attacker_character_id = ?, top_attacker_name = ?, top_attacker_count = ?, winning_attacker_name = ? WHERE id = ?',
            [$hp, 1, $hp === 0 ? 4 : 1, (int)$top['character_id'], $top['name'], (int)$top['attacks'], $winnerName, $eid]);
        Db::exec('UPDATE `character` SET worldboss_event_attack_count = ? WHERE id = ?', [self::ownCount($eid, $cid), $cid]);
    }

    private static function makeItem(int $cid, string $identifier): int
    {
        $template = self::constants()['item_templates'][$identifier] ?? null;
        if ($identifier !== 'random_epic' && !$template) throw new GameError('errRequestInvalidParameter');
        $type = $template ? (int)$template['type'] : random_int(1,7);
        return Character::load($cid)->createWorldbossRewardItem($identifier, $type, $type === 9 ? (int)$template['quality'] : 3);
    }

    private static function ensureReward(array $char, array $event): array
    {
        $eid = (int)$event['id']; $cid = (int)$char['id'];
        $reward = Db::row('SELECT * FROM worldboss_reward WHERE worldboss_event_id = ? AND character_id = ? FOR UPDATE', [$eid, $cid]);
        if ($reward) return $reward;
        $ranking = self::ranking($eid); $rank = 0; $own = ['coins'=>0,'xp'=>0];
        foreach ($ranking as $i=>$r) if ((int)$r['character_id'] === $cid) { $rank = $i+1; $own = $r; break; }
        $won = (int)$event['npc_hitpoints_current'] === 0;
        $plan = self::rewardPlan((string)$event['identifier'], $rank, count($ranking),
            $char['name'] === (string)$event['winning_attacker_name'], $won, random_int(1,100));
        $extra = $plan['extra']; $item = 0; $pet = 0;
        if ($plan['eligible']) {
            // Usar a peça anunciada na criação do evento; validar contra a família atual.
            $pool = self::equipmentPool((string)$event['identifier'], $plan['top']);
            $identifier = (string)$event[$plan['top'] ? 'reward_top_rank_item_identifier' : 'reward_top_pool_item_identifier'];
            if (!in_array($identifier, $pool, true)) $identifier = $pool[array_rand($pool)];
            $item = self::makeItem($cid, $identifier);
            if ($plan['pet'] !== null) $pet = self::makeItem($cid, $plan['pet']);
        }
        Db::exec('INSERT INTO worldboss_reward (worldboss_event_id, character_id, game_currency, xp, item_id, sidekick_item_id, quest_energy, training_sessions, rewards, claimed)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)', [$eid, $cid, (int)$own['coins'], (int)$own['xp'], $item, $pet,
                $extra['quest_energy'], $extra['training_sessions'], json_encode($extra)]);
        return Db::row('SELECT * FROM worldboss_reward WHERE id = ?', [(int)Db::pdo()->lastInsertId()]);
    }

    private static function deliver(array $char, array $r, bool $discard): void
    {
        $cid = (int)$char['id'];
        if ((int)$r['item_id'] > 0) {
            $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [(int)$r['item_id'], $cid]);
            if (!$item) throw new GameError('errClaimWorldbossEventRewardsInvalidStatus');
            if ($discard) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [(int)$item['id'], $cid]);
            else {
                $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]); $slot = null;
                for ($i=1; $i<=18; $i++) if (isset($inv['bag_item'.$i.'_id']) && (int)$inv['bag_item'.$i.'_id'] === 0) { $slot='bag_item'.$i.'_id'; break; }
                if (!$slot) throw new GameError('errInventoryNoEmptySlot');
                Db::exec("UPDATE inventory SET `$slot` = ? WHERE character_id = ?", [(int)$item['id'], $cid]);
            }
        }
        if ((int)$r['sidekick_item_id'] > 0) {
            $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [(int)$r['sidekick_item_id'], $cid]);
            if (!$item) throw new GameError('errClaimWorldbossEventRewardsInvalidStatus');
            $bases = [];
            foreach (['stamina','strength','critical_rating','dodge_rating'] as $stat)
                $bases['stat_base_'.$stat] = (int)$item['stat_'.$stat];
            // Previews antigos de pets tinham todos os atributos a zero.
            if (array_sum($bases) === 0) $bases = Character::load($cid)->randomSidekickBases((int)$item['quality']);
            Sidekick::create($cid, (string)$item['identifier'], (int)$item['quality'], $bases);
            Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [(int)$item['id'], $cid]);
        }
        Db::exec('UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ? WHERE id = ?', [(int)$r['game_currency'], (int)$r['xp'], $cid]);
        foreach (['quest_energy','training_sessions'] as $key) if ((int)$r[$key] > 0) UserVouchers::create($cid, $key, (int)$r[$key]);
        Character::load($cid)->syncLevelFromXp();
    }

    private static function attackData(array $attack): array
    {
        $data = ['worldboss_attack'=>self::cast($attack), 'battles'=>[], 'worldboss_previous_attackers'=>[]];
        foreach (json_decode((string)$attack['battle_ids'], true) ?? [] as $id) {
            $battle = Db::row('SELECT * FROM battle WHERE id = ?', [(int)$id]);
            if ($battle) $data['battles'][] = self::cast($battle);
        }
        $data['worldboss_previous_attackers'] = Db::column('SELECT c.name FROM worldboss_attack a JOIN `character` c ON c.id = a.character_id
            WHERE a.worldboss_event_id = ? AND a.id < ? AND a.status IN (3,4) ORDER BY a.id DESC LIMIT 10', [(int)$attack['worldboss_event_id'], (int)$attack['id']]);
        return $data;
    }

    private static function cast(array $row): array
    {
        foreach ($row as $key=>$v) if (is_string($v) && preg_match('/^-?[0-9]+$/D', $v) && !in_array($key,['identifier','name','top_attacker_name','winning_attacker_name'])) $row[$key]=(int)$v;
        return $row;
    }

    public static function attach(array $data, int $cid): array
    {
        return StoryDungeon::transaction($cid, static function (array $char) use ($data, $cid): array {
            $eid = (int)$char['worldboss_event_id'];
            $events = Db::rows('SELECT * FROM worldboss_event WHERE (status = 1 AND min_level <= ? AND max_level >= ? AND stage <= ?) OR id = ? ORDER BY id',
                [(int)$char['level'], (int)$char['level'], (int)$char['max_quest_stage'], $eid]);
            $data['worldboss_events'] = []; $data['worldboss_rewards'] = []; $data['worldboss_event_character_data'] = [];
            foreach ($events as $event) {
                $event = self::event((int)$event['id']); $id = (int)$event['id']; $rank = 0;
                $own = ['coins'=>0,'xp'=>0,'attacks'=>0]; $ranking = self::ranking($id);
                foreach ($ranking as $i=>$r) if ((int)$r['character_id'] === $cid) { $rank=$i+1; $own=$r; break; }
                if ($id === $eid && (int)$event['status'] === 4 && (int)$char['active_worldboss_attack_id'] === 0) {
                    $reward = self::ensureReward($char, $event);
                    if (!(int)$reward['claimed']) $data['worldboss_rewards'][] = self::cast($reward);
                }
                // Estado pendente por personagem até fechar o ataque; não anunciar prémios ainda ausentes.
                if ($id === $eid && (int)$event['status'] === 4 && (int)$char['active_worldboss_attack_id'] > 0) $event['status'] = 2;
                $data['worldboss_events'][] = self::cast($event) + ['ranking'=>$rank, 'coin_reward_total'=>(int)$own['coins'],
                    'xp_reward_total'=>(int)$own['xp'], 'coin_reward_next_attack'=>self::attackRewards((int)$char['level'])['coins'], 'xp_reward_next_attack'=>self::attackRewards((int)$char['level'])['xp']];
            }
            if ((int)$char['active_worldboss_attack_id'] > 0) {
                $attack = Db::row('SELECT * FROM worldboss_attack WHERE id = ? AND character_id = ?', [(int)$char['active_worldboss_attack_id'], $cid]);
                if ($attack) {
                    $extra = self::attackData($attack);
                    $extra['battles'] = array_merge($data['battles'] ?? [], $extra['battles']);
                    $data = array_replace($data, $extra);
                }
            }
            if (isset($data['character'])) {
                $data['character']['active_worldboss_attack_id'] = (int)$char['active_worldboss_attack_id'];
                $data['character']['worldboss_event_id'] = $eid;
                $data['character']['worldboss_event_attack_count'] = $eid ? self::ownCount($eid,$cid) : 0;
            }
            // Pré-carregar previews criados acima, também no login e nas respostas parciais.
            $known = array_map('intval', array_column($data['items'] ?? [], 'id'));
            foreach ($data['worldboss_rewards'] as $r) foreach (['item_id','sidekick_item_id'] as $field) {
                $id=(int)$r[$field];
                if ($id > 0 && !in_array($id,$known,true)) {
                    $item=Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [$id,$cid]);
                    if ($item) { $data['items'][]=self::cast($item); $known[]=$id; }
                }
            }
            return $data;
        });
    }
}
