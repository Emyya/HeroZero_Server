<?php
declare(strict_types=1);

namespace HeroZero;

/** Histórias das zonas: catálogo oficial, progresso e resgates atómicos. */
final class StoryDungeon
{
    // Mapeamento confirmado nos textos story_dungeon/<id>/<step>/progress.
    public const TIME_QUEST = 1, ENERGY = 4, DUEL = 5, DUEL_WON = 6,
        QUEST_ITEM = 10, WASH = 11, SHOP_REFRESH = 12, TRAINING_UNITS = 14,
        SPECIAL_DUNGEON = 15, FIGHT_QUEST = 16;

    private static ?array $catalog = null;

    public static function constants(): array
    {
        if (self::$catalog !== null) return self::$catalog;
        $src = __DIR__ . '/../../public/assets/data/constants_json.data';
        if (!is_file($src)) $src = dirname(__DIR__, 3) . '/assets/data/constants_json.data';
        return self::$catalog = AssetCache::slice('story_dungeon_v2', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            $slice = array_intersect_key($all, array_flip(['story_dungeons', 'story_dungeon_items',
                'story_dungeon_attack_cooldown', 'story_dungeon_attack_premium_currency']));
            $identifiers = [];
            foreach ($slice['story_dungeons'] as $dungeon) {
                foreach ($dungeon['step_index'] as $step) {
                    $identifiers[] = $step['reward_item_identifier'];
                    $identifiers[] = $step['reward_item_identifier_repeat'];
                }
            }
            $slice['item_templates'] = array_intersect_key($all['item_templates'], array_flip($identifiers));
            return $slice;
        });
    }

    public static function definition(int $index, int $step): array
    {
        return self::constants()['story_dungeons'][$index]['step_index'][$step]
            ?? throw new GameError('errRequestInvalidParameter');
    }

    /** Serializa operações do mesmo herói; savepoints preservam transações exteriores. */
    public static function transaction(int $cid, callable $fn): mixed
    {
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        static $serial = 0;
        $savepoint = 'story_dungeon_' . ++$serial;
        if ($nested) $pdo->exec('SAVEPOINT ' . $savepoint); else $pdo->beginTransaction();
        try {
            $char = Db::row('SELECT * FROM `character` WHERE id = ? FOR UPDATE', [$cid])
                ?? throw new GameError('errCharacterNotFound');
            // Mesma ordem de locks no progresso, preparação e resgate.
            Db::row('SELECT id FROM `user` WHERE id = ? FOR UPDATE', [(int)$char['user_id']]);
            $result = $fn($char);
            if ($nested) $pdo->exec('RELEASE SAVEPOINT ' . $savepoint); else $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($nested) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            } elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function decode(array $char): array
    {
        $state = json_decode((string)($char['story_dungeon_state'] ?? ''), true);
        return (is_array($state) ? $state : []) + ['active' => null, 'completed' => []];
    }

    private static function save(int $cid, array $state): void
    {
        Db::exec('UPDATE `character` SET story_dungeon_state = ? WHERE id = ?',
            [json_encode($state, JSON_THROW_ON_ERROR), $cid]);
    }

    /** Corrige o estado antigo sem apagar etapas já resgatadas nem compensar prémios antigos. */
    private static function prepare(array $char, array &$state): void
    {
        $a = &$state['active'];
        if (!is_array($a)) return;
        $definition = self::definition((int)$a['index'], (int)$a['step']);
        $key = $a['index'] . '_' . $a['step'];
        if (!isset($a['rewards'])) {
            if ((int)$a['status'] === 4 && !in_array($key, $state['completed'], true)) $a['status'] = 3;
            $a += ['points' => (int)$a['status'] === 3 ? (int)$definition['unlock_points_needed'] : 0,
                'ts_complete' => (int)$a['status'] === 3 ? GameTime::now() : 0,
                'ts_last_attack' => 0, 'battle_ids' => [], 'battle_id' => 0];
            $a['rewards'] = ['xp' => (int)$definition['reward_xp']];
            if ((int)$a['status'] !== 4 && !empty($definition['reward_item_identifier'])) {
                $a['rewards']['item'] = Character::load((int)$char['id'])->createStoryDungeonItem($definition);
            }
        }
    }

    public static function ensure(int $cid): array
    {
        return self::transaction($cid, static function (array $char) use ($cid): array {
            $state = self::decode($char);
            $before = $state;
            self::prepare($char, $state);
            if ($state !== $before) self::save($cid, $state);
            return $state;
        });
    }

    public static function start(int $cid, int $index, int $step): void
    {
        $definition = self::definition($index, $step);
        self::transaction($cid, static function (array $char) use ($cid, $index, $step, $definition): void {
            $state = self::decode($char);
            self::prepare($char, $state);
            $active = $state['active'];
            if (is_array($active) && (int)$active['status'] !== 4) {
                throw new GameError((int)$active['index'] === $index && (int)$active['step'] === $step
                    ? 'errStartStoryDungeonStepAlreadyStarted' : 'errStartStoryDungeonStepAnotherActive');
            }
            $template = self::constants()['story_dungeons'][$index];
            if ((int)$char['level'] < (int)$template['min_level']) throw new GameError('errStartStoryDungeonStepInvalidLevel');
            if (in_array($index . '_' . $step, $state['completed'], true)) throw new GameError('errStartStoryDungeonStepAlreadyCompleted');
            if ($step > 1 && !in_array($index . '_' . ($step - 1), $state['completed'], true)) {
                throw new GameError('errStartStoryDungeonStepLastStepNotFinished');
            }
            $previous = (int)$template['after_story_dungeon'];
            if ($previous > 0 && !in_array($previous . '_5', $state['completed'], true)) {
                throw new GameError('errStartStoryDungeonStepLastStepNotFinished');
            }
            $state['active'] = ['index' => $index, 'step' => $step, 'status' => 2];
            self::prepare($char, $state);
            self::save($cid, $state);
            // As missões já oferecidas também precisam de mostrar os objetos da nova etapa.
            foreach (Db::rows('SELECT id, identifier, rewards FROM quests WHERE character_id = ? AND status = 1', [$cid]) as $quest) {
                $reward = json_decode($quest['rewards'], true) ?? [];
                unset($reward['story_dungeon_item']);
                $reward += self::questReward($cid, (string)$quest['identifier']);
                Db::exec('UPDATE quests SET rewards = ? WHERE id = ? AND character_id = ?',
                    [json_encode($reward, JSON_THROW_ON_ERROR), (int)$quest['id'], $cid]);
            }
        });
    }

    /** Fórmula copiada de StoryDungeonStep.get_instantFinishCost no cliente. */
    public static function skipCost(array $active, array $definition): int
    {
        $needed = (int)$definition['unlock_points_needed'];
        $progress = (int)$active['step'] === 5 ? 0 : (int)floor(min($needed, (int)($active['points'] ?? 0)) / max(1, $needed) * 100);
        return (int)ceil((100 - $progress) / 100 * (int)$definition['skip_price']);
    }

    private static function debit(int $uid, int $amount): void
    {
        if ($amount > 0 && Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$amount, $uid, $amount]) !== 1) throw new GameError('errRemovePremiumCurrencyNotEnough');
    }

    private static function validateActive(array $state, int $index, int $step, string $error): array
    {
        $active = $state['active'];
        if (!is_array($active) || (int)$active['index'] !== $index || (int)$active['step'] !== $step) throw new GameError($error);
        return $active;
    }

    public static function finish(int $cid, int $index, int $step, bool $instant, bool $finishCooldown = false): void
    {
        self::transaction($cid, static function (array $char) use ($cid, $index, $step, $instant, $finishCooldown): void {
            $state = self::decode($char);
            self::prepare($char, $state);
            $a = self::validateActive($state, $index, $step, 'errStartStoryDungeonBattleInvalidStoryDungeon');
            if ((int)$a['status'] !== 2 || !empty($a['battle_id'])) throw new GameError('errClaimStoryDungeonRewardInvalidStatus');
            $definition = self::definition($index, $step);
            if (!$instant && $step !== 5) throw new GameError('errStartStoryDungeonBattleInvalidStoryDungeon');
            if ($instant) self::debit((int)$char['user_id'], self::skipCost($a, $definition));
            if ($step === 5) {
                foreach (['active_quest_id' => 'Quest', 'active_duel_id' => 'Duel', 'active_worldboss_attack_id' => 'WorldbossAttack'] as $field => $name) {
                    if ((int)($char[$field] ?? 0) > 0) throw new GameError('errStartStoryDungeonBattleActive' . $name . 'Found');
                }
                $constants = self::constants();
                if (!$instant && (int)$a['ts_last_attack'] > 0 && GameTime::now() - (int)$a['ts_last_attack'] < (int)$constants['story_dungeon_attack_cooldown']) {
                    if (!$finishCooldown) throw new GameError('errStartStoryDungeonBattleActiveCooldown');
                    self::debit((int)$char['user_id'], (int)$constants['story_dungeon_attack_premium_currency']);
                }
                $player = Character::load($cid);
                $profileA = $player->battleProfile('a');
                $profileB = self::npcProfile($definition);
                $profileA['chance_critical'] = Fight::criticalChance((int)$profileA['criticalrating'], (int)$profileB['criticalrating']);
                $profileA['chance_dodge'] = Fight::dodgeChance((int)$profileA['dodgerating'], (int)$profileB['dodgerating']);
                $profileB['chance_critical'] = Fight::criticalChance((int)$profileB['criticalrating'], (int)$profileA['criticalrating']);
                $profileB['chance_dodge'] = Fight::dodgeChance((int)$profileB['dodgerating'], (int)$profileA['dodgerating']);
                // A conclusão premium garante vitória; o combate normal usa o motor existente.
                if ($instant) {
                    $winner = 'a';
                    $rounds = [['a' => 'a', 'd' => 'b', 'r' => 2, 'v' => $profileB['hitpoints']]];
                } else {
                    $fight = new Fight($profileA, $profileB);
                    $winner = $fight->fight() === 1 ? 'a' : 'b';
                    $rounds = $fight->rounds();
                }
                Db::exec('INSERT INTO battle (ts_creation, profile_a_stats, profile_b_stats, winner, rounds) VALUES (?, ?, ?, ?, ?)',
                    [GameTime::now(), json_encode($profileA), json_encode($profileB), $winner, json_encode(['rounds' => $rounds])]);
                $a['battle_id'] = (int)Db::pdo()->lastInsertId();
                $a['battle_ids'][] = $a['battle_id'];
                $a['ts_last_attack'] = GameTime::now();
                $a['status'] = $winner === 'a' ? 3 : 2;
            } else {
                $a['points'] = (int)$definition['unlock_points_needed'];
                $a['status'] = 3;
            }
            if ($a['status'] === 3) $a['ts_complete'] = GameTime::now();
            $state['active'] = $a;
            self::save($cid, $state);
        });
    }

    public static function npcProfile(array $definition): array
    {
        $strength = (int)$definition['npc_stat_strength'];
        $weapon = (int)$definition['npc_stat_weapon_damage'];
        return ['profile' => 'b', 'level' => (int)$definition['npc_level'],
            'stamina' => (int)$definition['npc_stat_stamina'], 'strength' => $strength,
            'criticalrating' => (int)$definition['npc_stat_critical_rating'],
            'dodgerating' => (int)$definition['npc_stat_dodge_rating'], 'weapondamage' => $weapon,
            'hitpoints' => (int)$definition['npc_stat_stamina'] * Fight::HP_SCALE,
            'damage_normal' => $strength + $weapon, 'damage_bonus' => $strength + $weapon];
    }

    public static function claim(int $cid, int $index, int $step, bool $discard): void
    {
        self::transaction($cid, static function (array $char) use ($cid, $index, $step, $discard): void {
            $state = self::decode($char);
            self::prepare($char, $state);
            $a = self::validateActive($state, $index, $step, 'errClaimStoryDungeonStepRewardInvalidStoryDungeonStep');
            // Depois de uma derrota o cliente fecha o diálogo pela mesma ação.
            if ($step === 5 && (int)$a['status'] === 2 && !empty($a['battle_id'])) {
                $state['active']['battle_id'] = 0;
                self::save($cid, $state);
                return;
            }
            if ((int)$a['status'] !== 3) throw new GameError('errClaimStoryDungeonRewardInvalidStatus');
            $reward = $a['rewards'];
            if (!empty($reward['item'])) {
                $itemId = (int)$reward['item'];
                if (!Db::row('SELECT id FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [$itemId, $cid])) {
                    throw new GameError('errClaimStoryDungeonStepRewardInvalidStoryDungeonStep');
                }
                if ($discard) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [$itemId, $cid]);
                else {
                    $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
                    $slot = null;
                    for ($i = 1; $i <= 18; $i++) {
                        $key = 'bag_item' . $i . '_id';
                        if (isset($inv[$key]) && (int)$inv[$key] === 0) { $slot = $key; break; }
                    }
                    if (!$slot) throw new GameError('errInventoryNoEmptySlot');
                    Db::exec("UPDATE inventory SET `{$slot}` = ? WHERE character_id = ?", [$itemId, $cid]);
                }
            }
            // Descartar refere-se apenas ao equipamento, nunca ao XP.
            Db::exec('UPDATE `character` SET xp = xp + ? WHERE id = ?', [(int)($reward['xp'] ?? 0), $cid]);
            $state['completed'][] = $index . '_' . $step;
            $state['completed'] = array_values(array_unique($state['completed']));
            $state['active']['status'] = 4;
            $state['active']['battle_id'] = 0;
            self::save($cid, $state);
            Character::load($cid)->syncLevelFromXp();
        });
    }

    public static function record(int $cid, int $type, int $amount = 1, string $reference = ''): void
    {
        if ($amount < 1) return;
        self::transaction($cid, static function (array $char) use ($cid, $type, $amount, $reference): void {
            $state = self::decode($char);
            $a = $state['active'];
            if (!is_array($a) || (int)$a['status'] !== 2 || (int)$a['step'] === 5) return;
            $definition = self::definition((int)$a['index'], (int)$a['step']);
            if ((int)$definition['type'] !== $type) return;
            if (in_array($type, [self::QUEST_ITEM, self::SPECIAL_DUNGEON], true) && $reference !== $definition['reference']) return;
            self::prepare($char, $state);
            $a = $state['active'];
            $a['points'] = min((int)$definition['unlock_points_needed'], (int)$a['points'] + $amount);
            if ($a['points'] >= (int)$definition['unlock_points_needed']) {
                $a['status'] = 3;
                $a['ts_complete'] = GameTime::now();
            }
            $state['active'] = $a;
            self::save($cid, $state);
        });
    }

    /** Prémio apresentado na missão; só é contado quando a missão é resgatada. */
    public static function questReward(int $cid, string $identifier, ?float $roll = null): array
    {
        $char = Db::row('SELECT story_dungeon_state FROM `character` WHERE id = ?', [$cid]) ?? [];
        $active = self::decode($char)['active'];
        if (!is_array($active) || (int)$active['status'] !== 2) return [];
        $definition = self::definition((int)$active['index'], (int)$active['step']);
        if ((int)$definition['type'] !== self::QUEST_ITEM) return [];
        $reference = (string)$definition['reference'];
        $item = self::constants()['story_dungeon_items'][$reference] ?? null;
        if (!$item || !self::matchesQuest($identifier, (string)$item['reward_reference'])) return [];
        $roll ??= random_int(0, 999999) / 1000000;
        return $roll < (float)$item['reward_chance'] ? ['story_dungeon_item' => $reference] : [];
    }

    public static function matchesQuest(string $identifier, string $reference): bool
    {
        // As referências oficiais são *stageN_ ou *stageN (substring do identifier).
        return $reference !== '' && str_contains($identifier, ltrim($reference, '*'));
    }
}
