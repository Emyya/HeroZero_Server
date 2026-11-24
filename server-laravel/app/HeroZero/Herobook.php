<?php
declare(strict_types=1);

namespace HeroZero;

/** Chamadas de emergência. Estado persistente e contrato DOHerobookObjective. */
final class Herobook
{
    public const SUPPORTED_TYPES = [4, 8, 10, 11, 12, 13, 16];

    public static function constants(): array
    {
        $src = __DIR__ . '/../../public/assets/data/constants_json.data';
        if (!is_file($src)) $src = __DIR__ . '/../../../assets/data/constants_json.data';
        return AssetCache::slice('herobook_v6', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            // Regra local: manter os pontos das chamadas em todos os temas da
            // rotação. beetle/bear têm zero nas constantes oficiais antigas.
            // A mesma fatia alimenta o cliente (Live) e o crédito no resgate.
            foreach (array_keys($all['seasons']) as $season) {
                $all['season_locations'][14][$season] = 1;
                $all['season_locations'][13][$season] = 5;
                $all['season_locations'][17][$season] = 1;
                $all['season_locations'][9][$season] = 1;
                $all['season_locations'][10][$season] = 1;
                $all['season_locations'][11][$season] = 1;
            }
            return array_filter($all, static fn(string $key): bool => str_starts_with($key, 'herobook_') || $key === 'season_locations', ARRAY_FILTER_USE_KEY);
        });
    }

    /** Limites à meia-noite e segunda-feira, usando o calendário do jogo. */
    public static function deadline(int $duration): int
    {
        $now = Live::gameNow();
        return ($duration === 1 ? $now->modify('tomorrow')->setTime(0, 0)
            : $now->modify('next monday')->setTime(0, 0))->getTimestamp();
    }

    private static function transaction(callable $fn): mixed
    {
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT herobook'); else $pdo->beginTransaction();
        try {
            $out = $fn();
            if ($nested) $pdo->exec('RELEASE SAVEPOINT herobook'); else $pdo->commit();
            return $out;
        } catch (\Throwable $e) {
            if ($nested) $pdo->exec('ROLLBACK TO SAVEPOINT herobook');
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function lockCharacter(int $cid): array
    {
        return Db::row('SELECT id, user_id, level FROM `character` WHERE id = ? FOR UPDATE', [$cid])
            ?? throw new GameError('errInvalidHerobookObjectiveId');
    }

    private static function create(array $char, int $duration, int $index, array $exclude = []): string
    {
        $constants = self::constants();
        $period = $duration === 1 ? 'daily' : 'weekly';
        $pool = array_filter($constants['herobook_objectives'], static fn(array $o, string $id): bool =>
            in_array((int)$o['type'], self::SUPPORTED_TYPES, true) && (int)$o[$period] > 0
            && (int)$o['min_level'] <= (int)$char['level'] && !in_array($id, $exclude, true), ARRAY_FILTER_USE_BOTH);
        if (!$pool) throw new \RuntimeException('Sem objetivos Herobook compatíveis.');
        $identifier = array_rand($pool);
        $definition = $pool[$identifier];
        $variance = (int)$definition['variance'];
        $target = max(1, (int)round((int)$definition[$period] * (100 + random_int(-$variance, $variance)) / 100));
        $reward = self::rollReward((int)$char['level'], $duration, (float)$definition['reward_factor']);
        if (isset($reward['_equipment_bonus'])) {
            $slots = ['mask','cape','suit','belt','boots','weapon','gadget'];
            $reward['item'] = Character::load((int)$char['id'])->createBlacksmithItem(
                $slots[array_rand($slots)], (int)$reward['_equipment_bonus'], 3);
            Db::exec('UPDATE items SET required_level = ? WHERE id = ? AND character_id = ?',
                [(int)$char['level'], (int)$reward['item'], (int)$char['id']]);
            unset($reward['_equipment_bonus']);
        }
        Db::exec('INSERT INTO herobook_objectives (character_id, identifier, type, duration_type,
            status, current_value, max_value, ts_end, objective_index, rewards) VALUES (?,?,?,?,1,0,?,?,?,?)',
            [(int)$char['id'], $identifier, (int)$definition['type'], $duration, $target,
                self::deadline($duration), $index, json_encode($reward, JSON_THROW_ON_ERROR)]);
        return $identifier;
    }

    public static function data(int $cid): array
    {
        return self::transaction(static function () use ($cid): array {
            $char = self::lockCharacter($cid);
            if ((int)$char['level'] < (int)self::constants()['herobook_min_level']) return [];
            // Manter concluídos expirados para resgate; informar o cliente dos abortados.
            foreach (Db::rows('SELECT rewards FROM herobook_objectives WHERE character_id = ? AND status = 1 AND ts_end <= ?', [$cid, GameTime::now()]) as $expired) {
                self::deletePreview($cid, json_decode($expired['rewards'], true) ?? []);
            }
            Db::exec('UPDATE herobook_objectives SET status = 4 WHERE character_id = ? AND status = 1 AND ts_end <= ?', [$cid, GameTime::now()]);
            $created = false;
            foreach ([1 => 3, 2 => 2] as $duration => $count) {
                $rows = Db::rows('SELECT identifier, objective_index FROM herobook_objectives
                    WHERE character_id = ? AND duration_type = ? AND ts_end > ? AND status <> 4', [$cid, $duration, GameTime::now()]);
                $indexes = array_map('intval', array_column($rows, 'objective_index'));
                $exclude = array_column($rows, 'identifier');
                for ($index = 0; $index < $count; $index++) {
                    if (in_array($index, $indexes, true)) continue;
                    $exclude[] = self::create($char, $duration, $index, $exclude);
                    $created = true;
                }
            }
            $rows = Db::rows('SELECT * FROM herobook_objectives WHERE character_id = ?
                AND (ts_end > ? OR status = 2 OR (status = 4 AND ts_end > ?)) ORDER BY id', [$cid, GameTime::now(), GameTime::now() - 7 * 86400]);
            $out = [];
            foreach ($rows as $row) {
                foreach (['id','character_id','type','duration_type','status','current_value','max_value','ts_end'] as $key) $row[$key] = (int)$row[$key];
                $row['index'] = (int)$row['objective_index'];
                unset($row['objective_index']);
                $out['objective_' . $row['id']] = $row;
            }
            if ($created) $out['newObjectivesCreated'] = true;
            return $out;
        });
    }

    /** Chamado apenas depois de uma atividade realmente aceite/concluída. */
    public static function record(int $cid, int $type, int $amount = 1): void
    {
        if ($amount < 1) return;
        // Não criar retroativamente objetivos para premiar uma ação já feita.
        Db::exec('UPDATE herobook_objectives SET
            status = IF(current_value + ? >= max_value, 2, 1),
            current_value = LEAST(max_value, current_value + ?)
            WHERE character_id = ? AND type = ? AND status = 1 AND ts_end > ?',
            [$amount, $amount, $cid, $type, GameTime::now()]);
    }

    public static function act(int $cid, int $id, string $action, bool $discard = false): void
    {
        if (!in_array($action, ['collectHerobookReward','instantFinishHerobookObjective','renewHerobookObjective'], true)) throw new GameError('errActionNotSupported');
        self::transaction(static function () use ($cid, $id, $action, $discard): void {
            $char = self::lockCharacter($cid);
            $row = Db::row('SELECT * FROM herobook_objectives WHERE id = ? AND character_id = ? FOR UPDATE', [$id, $cid]);
            if (!$row) throw new GameError('errInvalidHerobookObjectiveId');
            if ((int)$char['level'] < (int)self::constants()['herobook_min_level']) throw new GameError('errInvalidHerobookObjectiveId');
            if ($action === 'collectHerobookReward') {
                if ((int)$row['status'] !== 2) throw new GameError('errInvalidHerobookObjectiveId');
                $reward = json_decode($row['rewards'], true, 512, JSON_THROW_ON_ERROR);
                if (!$discard) {
                    self::deliver($cid, $reward);
                    $progress = Season::ensureProgress($cid);
                    if ((int)$progress['status'] === 2 && (int)$progress['ts_end'] > GameTime::now()) {
                        Season::givePoints($cid, self::seasonPoints((int)$row['duration_type'], (string)$progress['identifier']));
                    }
                } else self::deletePreview($cid, $reward);
                Db::exec('UPDATE `character` SET herobook_objectives_finished = herobook_objectives_finished + 1 WHERE id = ?', [$cid]);
                Db::exec('UPDATE herobook_objectives SET status = 3 WHERE id = ?', [$id]);
                return;
            }
            if ((int)$row['status'] !== 1 || (int)$row['ts_end'] <= GameTime::now()) throw new GameError('errInvalidHerobookObjectiveId');
            $constants = self::constants();
            $cost = (int)$constants[$action === 'renewHerobookObjective' ? 'herobook_objective_renew_amount'
                : ((int)$row['duration_type'] === 1 ? 'herobook_daily_objective_instant_finish_amount' : 'herobook_weekly_objective_instant_finish_amount')];
            if (Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                [$cost, (int)$char['user_id'], $cost]) !== 1) throw new GameError('errRemovePremiumCurrencyNotEnough');
            if ($action === 'instantFinishHerobookObjective') {
                Db::exec('UPDATE herobook_objectives SET status = 2, current_value = max_value WHERE id = ?', [$id]);
            } elseif ($action === 'renewHerobookObjective') {
                self::deletePreview($cid, json_decode($row['rewards'], true) ?? []);
                Db::exec('UPDATE herobook_objectives SET status = 4 WHERE id = ?', [$id]);
                self::create($char, (int)$row['duration_type'], (int)$row['objective_index'], [$row['identifier']]);
                Db::exec('UPDATE `character` SET herobook_objectives_renewed_today = herobook_objectives_renewed_today + 1 WHERE id = ?', [$cid]);
            } else throw new GameError('errActionNotSupported');
        });
    }

    public static function seasonPoints(int $duration, string $season): int
    {
        return max(0, (int)(self::constants()['season_locations'][$duration === 1 ? 14 : 13][$season] ?? 0));
    }

    /** Chances/quantidades fixas oficiais; escalas dependentes do nível são locais. */
    public static function rollReward(int $level, int $duration, float $objectiveFactor = 1.0, ?int $ticket = null): array
    {
        $pool = self::constants()[$duration === 1 ? 'herobook_daily_rewards' : 'herobook_weekly_rewards'];
        $total = array_sum(array_column($pool, 'chance'));
        $ticket ??= random_int(1, $total);
        if ($ticket < 1 || $ticket > $total) throw new \InvalidArgumentException('Ticket inválido');
        foreach ($pool as $definition) {
            $ticket -= (int)$definition['chance'];
            if ($ticket > 0) continue;
            $type = (int)$definition['type'];
            $key = [2=>'xp',3=>'premium',4=>'statPoints',5=>'_equipment_bonus',6=>'quest_energy',
                7=>'training_sessions',8=>'hideout_stone',9=>'hideout_glue',10=>'coins'][$type];
            if ($type === 5) return [$key => (int)$definition['item_bonus_level']];
            if (!(int)$definition['scaling']) return [$key => (int)$definition['amount']];
            // O cliente recebe os valores finais; não expõe a fórmula do servidor oficial.
            $factor = (float)$definition['reward_factor'] * max(0.1, $objectiveFactor);
            $base = match ($type) {
                2 => max(1, Character::xpForLevel($level + 1) - Character::xpForLevel($level)) / 100,
                10 => 113 * $level * max(1, ($level / 300) ** 2),
                default => max(1, $level),
            };
            return [$key => max(1, (int)round($base * $factor))];
        }
        throw new \RuntimeException('Catálogo de prémios vazio');
    }

    private static function deliver(int $cid, array $reward): void
    {
        if (!empty($reward['item'])) {
            $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
            $slot = null;
            foreach (range(1,18) as $i) if (isset($inv['bag_item'.$i.'_id']) && (int)$inv['bag_item'.$i.'_id'] === 0) { $slot = 'bag_item'.$i.'_id'; break; }
            if (!$slot) throw new GameError('errInventoryNoEmptySlot');
            if (!Db::row('SELECT id FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [(int)$reward['item'], $cid])) throw new GameError('errInvalidHerobookObjectiveId');
            Db::exec("UPDATE inventory SET `{$slot}` = ? WHERE character_id = ?", [(int)$reward['item'], $cid]);
        }
        if (!empty($reward['premium'])) {
            Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = (SELECT user_id FROM `character` WHERE id = ?)', [(int)$reward['premium'], $cid]);
        }
        Db::exec('UPDATE `character` SET xp = xp + ?, game_currency = game_currency + ?, stat_points_available = stat_points_available + ? WHERE id = ?',
            [(int)($reward['xp'] ?? 0),(int)($reward['coins'] ?? 0),(int)($reward['statPoints'] ?? 0),$cid]);
        foreach (UserVouchers::KEYS as $key) {
            if (!empty($reward[$key])) UserVouchers::create($cid, $key, (int)$reward[$key]);
        }
        if (!empty($reward['xp'])) Character::load($cid)->syncLevelFromXp();
    }

    /** Apagar apenas o prémio de preview que nunca chegou ao inventário/banco. */
    private static function deletePreview(int $cid, array $reward): void
    {
        $id = (int)($reward['item'] ?? 0);
        if ($id <= 0) return;
        $inventory = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
        $bank = Db::row('SELECT * FROM bank_inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
        foreach (array_merge($inventory, $bank) as $key => $value) {
            if (str_ends_with($key, '_id') && !in_array($key, ['character_id','sidekick_id'], true) && (int)$value === $id) return;
        }
        Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [$id, $cid]);
    }
}
