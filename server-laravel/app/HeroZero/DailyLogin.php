<?php
declare(strict_types=1);
namespace HeroZero;

/** Prémio diário persistente; catálogo oficial, escalas locais documentadas. */
final class DailyLogin
{
    public static function catalog(): array
    {
        $src = dirname(__DIR__, 3).'/assets/data/constants_json.data';
        if (!is_file($src)) $src = __DIR__.'/../../public/assets/data/constants_json.data';
        return AssetCache::slice('daily_login_v1', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            return $all['daily_login_rewards'];
        });
    }

    public static function rewards(int $day, int $level): array
    {
        $definition = self::catalog()[(($day - 1) % 30) + 1];
        $reward = [];
        foreach ([1, 2] as $slot) {
            $type = (int)$definition['reward'.$slot.'_type'];
            $value = (int)$definition['reward'.$slot.'_value'];
            // Quantidades finais destas escalas não existem no cliente: balanceamento local.
            $level = max(1, $level);
            switch ($type) {
                case -1: break;
                case 0: $reward['premium'] = $value; break;
                case 1: $reward['coins'] = $value * 50 * $level; break;
                case 2: $reward['xp'] = $value * 100 * $level; break;
                case 5: $reward['stat_points'] = $value; break;
                case 11: $reward['_item_offset'] = $value; break;
                case 12: $reward['hideout_glue'] = $value; break;
                case 13: $reward['hideout_stone'] = $value; break;
                default: throw new \LogicException('Tipo de login não suportado: '.$type);
            }
        }
        return $reward;
    }

    public static function pending(int $cid): ?array
    {
        return Missiles::transaction([$cid], function () use ($cid): ?array {
            $char = Db::row('SELECT * FROM `character` WHERE id = ?', [$cid]);
            if (!$char) throw new GameError('errLoginInvalidSession');
            $last = (int)$char['ts_last_daily_login_bonus'];
            if ($last > 0 && BonusEvents::dateOf($last) >= BonusEvents::today()) return null;
            $existing = Db::row('SELECT * FROM daily_login_rewards WHERE character_id = ? AND reward_date = ?',
                [$cid, BonusEvents::today()]);
            if ($existing) return (int)$existing['status'] === 0 ? $existing : null;

            // Prémios ignorados expiram sem avançar o contador; previews não ficam órfãos.
            foreach (Db::rows('SELECT id, item_id FROM daily_login_rewards WHERE character_id = ? AND status = 0', [$cid]) as $old) {
                if ((int)$old['item_id'] > 0) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [(int)$old['item_id'], $cid]);
                Db::exec('UPDATE daily_login_rewards SET status = 2, item_id = 0 WHERE id = ?', [(int)$old['id']]);
            }
            $day = $last > 0 ? max(1, (int)$char['daily_login_bonus_day'] + 1) : 1;
            $reward = self::rewards($day, (int)$char['level']);
            $itemId = 0;
            if (isset($reward['_item_offset'])) {
                $itemId = Character::load($cid)->createStoryDungeonItem([
                    'reward_item_identifier' => 'random_epic', 'reward_item_level_offset' => $reward['_item_offset']]);
                unset($reward['_item_offset']);
                $reward['item'] = $itemId;
            }
            Db::exec('INSERT INTO daily_login_rewards (character_id, reward_date, login_day, rewards, item_id, status)
                VALUES (?, ?, ?, ?, ?, 0)', [$cid, BonusEvents::today(), $day, json_encode($reward), $itemId]);
            return Db::row('SELECT * FROM daily_login_rewards WHERE id = ?', [(int)Db::pdo()->lastInsertId()]);
        });
    }

    public static function attach(array $data, int $cid): array
    {
        unset($data['daily_login_bonus_day'], $data['daily_login_bonus_rewards']);
        $pending = self::pending($cid);
        if (!$pending) return $data;
        $data['daily_login_bonus_day'] = (int)$pending['login_day'];
        $data['daily_login_bonus_rewards'] = ['id' => (int)$pending['id'], 'rewards' => $pending['rewards']];
        if ((int)$pending['item_id'] > 0) {
            $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [(int)$pending['item_id'], $cid]);
            if (!$item) throw new \RuntimeException('Preview de login inexistente.');
            $ids = array_column($data['items'] ?? [], 'id');
            if (!in_array((int)$item['id'], array_map('intval', $ids), true)) {
                $template = Live::template('autoLoginUser')['items'][0] ?? [];
                $data['items'][] = Live::shapeLike($template, $item);
            }
        }
        return $data;
    }

    public static function claim(int $cid, int $id, bool $discard): void
    {
        Missiles::transaction([$cid], function () use ($cid, $id, $discard): void {
            $pending = Db::row('SELECT * FROM daily_login_rewards WHERE id = ? AND character_id = ? FOR UPDATE', [$id, $cid]);
            $char = Db::row('SELECT * FROM `character` WHERE id = ?', [$cid]);
            if (!$pending || (int)$pending['status'] !== 0 || $pending['reward_date'] !== BonusEvents::today()
                || ((int)$char['ts_last_daily_login_bonus'] > 0 && BonusEvents::dateOf((int)$char['ts_last_daily_login_bonus']) >= BonusEvents::today())) {
                throw new GameError('errCollectDailyLoginRewardInvalidStatus');
            }
            $reward = json_decode($pending['rewards'], true, 512, JSON_THROW_ON_ERROR);
            $itemId = (int)$pending['item_id'];
            if ($itemId > 0) {
                $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [$itemId, $cid]);
                if (!$item) throw new GameError('errCollectDailyLoginRewardInvalidStatus');
                if ($discard) {
                    $reward['coins'] = (int)($reward['coins'] ?? 0) + (int)$item['sell_price'];
                    Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [$itemId, $cid]);
                } else {
                    $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]);
                    $free = null;
                    for ($i = 1; $i <= 18; $i++) if ($inv && (int)$inv['bag_item'.$i.'_id'] === 0) { $free = 'bag_item'.$i.'_id'; break; }
                    if (!$free) throw new GameError('errInventoryNoEmptySlot');
                    Db::exec("UPDATE inventory SET `$free` = ? WHERE character_id = ?", [$itemId, $cid]);
                }
            }
            Db::exec('UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ?,
                stat_points_available = stat_points_available + ?, ts_last_daily_login_bonus = ?, daily_login_bonus_day = ? WHERE id = ?',
                [(int)($reward['coins'] ?? 0), (int)($reward['xp'] ?? 0), (int)($reward['stat_points'] ?? 0),
                    GameTime::now(), (int)$pending['login_day'], $cid]);
            if (!empty($reward['premium'])) Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?',
                [(int)$reward['premium'], (int)$char['user_id']]);
            // Cupões evitam perder recursos ao atingir a capacidade do esconderijo.
            foreach (['hideout_glue', 'hideout_stone'] as $key) if (!empty($reward[$key])) UserVouchers::create($cid, $key, (int)$reward[$key]);
            Db::exec('UPDATE daily_login_rewards SET status = 1 WHERE id = ?', [$id]);
            Character::load($cid)->syncLevelFromXp();
        });
    }
}
