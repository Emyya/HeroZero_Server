<?php
declare(strict_types=1);

namespace HeroZero;

/** Client contract: ts_availability_end = 1 (empty slot), -id (attached improvement). */
final class ItemImprovement
{
    /** Fonte local existente; o agregado oficial não foi confirmado em capturas. */
    public static function workshopGuildData(int $characterId): array
    {
        $guildId = (int)Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$characterId]);
        if ($guildId <= 0) return ['level' => 0, 'members' => 0];
        // SELECT * permite instalações sem a coluna opcional, sem ocultar erros de BD.
        $guild = Db::row('SELECT * FROM `guild` WHERE id = ?', [$guildId]);
        if ($guild === null) return ['level' => 0, 'members' => 0];
        return [
            'level' => max(0, (int)($guild['gem_production_level'] ?? 0)),
            'members' => (int)Db::value('SELECT COUNT(*) FROM `character` WHERE guild_id = ?', [$guildId]),
        ];
    }

    /** Cf.get_guildBonus: o campo de protocolo é um valor bruto, não uma percentagem. */
    public static function workshopGuildBonus(int $guildLevel, int $memberCount): float
    {
        return League::jsRound($guildLevel / (20 * max($memberCount, 5)) * 10000) / 100;
    }

    /** Máximo anunciado por Cf.updateBonusInfo; não define o sorteio oficial. */
    public static function workshopMaxStats(int $heroLevel, int $roomLevel, int $guildLevel = 0, int $memberCount = 0): int
    {
        $cfg = Hideout::roomLevelConfig('gem_production', $roomLevel);
        $ownBonus = (float)($cfg['passiv_bonus_amount_1'] ?? 0);
        $guildBonus = self::workshopGuildBonus($guildLevel, $memberCount);
        return League::jsRound($heroLevel * ($ownBonus / 100 + $guildBonus / 100 + 1) / 2
            * (float)Hideout::c('item_improvement_epic_stat_increase', 1.6));
    }

    /** Balanceamento local preservado: 1/3, 2/3 e total, em até 1/2/3 atributos. */
    public static function workshopRarities(int $maxStats): array
    {
        $statKeys = ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating'];
        $rarities = [];
        for ($index = 0; $index < 3; $index++) {
            $budget = max(0, League::jsRound($maxStats * (($index + 1) / 3.0)));
            $stats = array_fill_keys($statKeys, 0);
            $keys = $statKeys;
            shuffle($keys);
            $count = min($index + 1, $budget);
            $remaining = $budget;
            for ($i = 0; $i < $count; $i++) {
                $value = $i === $count - 1 ? $remaining : min(
                    $remaining - ($count - $i - 1),
                    max(1, League::jsRound($remaining * (0.4 + lcg_value() * 0.2)))
                );
                $stats[$keys[$i]] = $value;
                $remaining -= $value;
            }
            $rarities[(string)$index] = array_merge(['quality' => $index + 1], $stats);
        }
        return $rarities;
    }

    private static ?bool $sidekickSchema = null;
    public static function hasSidekickSchema(): bool
    {
        return self::$sidekickSchema ??= (bool)Db::value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sidekicks' AND COLUMN_NAME = 'item_improvement_id'");
    }

    public static function migrateSidekicks(): void
    {
        if (!self::hasSidekickSchema()) Db::exec('ALTER TABLE sidekicks ADD COLUMN item_improvement_id INT NOT NULL DEFAULT 0');
        self::$sidekickSchema = true;
    }

    public static function catalog(): array
    {
        $src = __DIR__ . '/../../public/assets/data/constants_json.data';
        if (!is_file($src)) $src = __DIR__ . '/../../../assets/data/constants_json.data';
        return AssetCache::slice('item_improvements', $src, static function () use ($src): array {
            $data = json_decode(gzuncompress(file_get_contents($src)), true);
            return $data['item_improvements'] ?? [];
        });
    }

    public static function data(int $characterId): array
    {
        $sidekick = self::hasSidekickSchema() ? ' OR EXISTS (SELECT 1 FROM sidekicks s
            WHERE s.character_id = m.character_id AND s.item_improvement_id = -m.id)' : '';
        return Db::rows('SELECT m.id, m.identifier, m.quality, m.stat_stamina,
            m.stat_strength, m.stat_critical_rating, m.stat_dodge_rating
            FROM items m WHERE m.character_id = ? AND (EXISTS (SELECT 1 FROM items equipment
            WHERE equipment.ts_availability_end = -m.id AND equipment.character_id = m.character_id)'
            . $sidekick . ')', [$characterId]);
    }

    public static function useItem(int $characterId, int $itemId, int $targetId, bool $replace, bool $sidekick = false): void
    {
        if ($sidekick && !self::hasSidekickSchema()) throw new GameError('errActionNotSupported');
        $table = $sidekick ? 'sidekicks' : 'items';
        $link = $sidekick ? 'item_improvement_id' : 'ts_availability_end';
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT item_improvement');
        else $pdo->beginTransaction();
        try {
            $character = Db::row('SELECT level FROM `character` WHERE id = ? FOR UPDATE', [$characterId]);
            $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$characterId]) ?? [];
            $bank = Db::row('SELECT * FROM bank_inventory WHERE character_id = ? FOR UPDATE', [$characterId]) ?? [];
            $item = Db::row('SELECT * FROM items WHERE character_id = ? AND id = ? FOR UPDATE', [$characterId, $itemId]);
            $target = Db::row("SELECT * FROM {$table} WHERE character_id = ? AND id = ? FOR UPDATE", [$characterId, $targetId]);
            $source = null;
            foreach ($inv as $field => $value) {
                if (preg_match('/^bag_item\d+_id$/', $field) && (int)$value === $itemId) $source = $field;
            }
            $owned = false;
            foreach (array_merge($inv, $bank) as $field => $value) {
                if (preg_match('/^(mask|cape|suit|belt|boots|weapon|gadget)_item_id$|^(bag|bank)_item\d+_id$/', $field)
                    && (int)$value === $targetId) $owned = true;
            }
            if ($sidekick) $owned = $target && (int)$target['status'] === 1;
            if (!$item || !$target || !$source || !$owned || (!$sidekick && $itemId === $targetId)
                || (!$sidekick && !in_array((int)$target['type'], range(1, 7), true))) throw new GameError('errInventoryInvalidItem');
            if ((int)$item['required_level'] > (int)$character['level']) throw new GameError('errUseItemItemReqLevel');
            $old = (int)$target[$link];
            if ((int)$item['type'] === ($sidekick ? 14 : 13)) {
                if ($old === 1 || $old < 0) throw new GameError('errInventoryInvalidItem');
                Db::exec("UPDATE {$table} SET {$link} = 1 WHERE id = ? AND character_id = ?", [$targetId, $characterId]);
                Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [$itemId, $characterId]);
            } else {
                $definition = self::catalog()[$item['identifier']] ?? null;
                if ((int)$item['type'] !== 12 || !$definition || (int)$definition['type'] !== ($sidekick ? 9 : (int)$target['type'])
                    || !($old === 1 || $old < 0) || ($old < 0 && !$replace)) throw new GameError('errInventoryInvalidItem');
                Db::exec("UPDATE {$table} SET {$link} = ? WHERE id = ? AND character_id = ?", [-$itemId, $targetId, $characterId]);
                if (!$sidekick) HeroCon::record($characterId, [1=>26,2=>27,3=>28][(int)$item['quality']] ?? 0);
                if ($old < 0) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [-$old, $characterId]);
            }
            Db::exec("UPDATE inventory SET `{$source}` = 0 WHERE character_id = ?", [$characterId]);
            if ($nested) $pdo->exec('RELEASE SAVEPOINT item_improvement');
            else $pdo->commit();
        } catch (\Throwable $e) {
            if ($nested) $pdo->exec('ROLLBACK TO SAVEPOINT item_improvement');
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteAttached(int $characterId, int $itemId): void
    {
        $id = (int)Db::value('SELECT ts_availability_end FROM items WHERE character_id = ? AND id = ?', [$characterId, $itemId]);
        if ($id < 0) Db::exec('DELETE FROM items WHERE character_id = ? AND id = ? AND type = 12', [$characterId, -$id]);
    }

    public static function sellPrice(int $characterId, array $item): int
    {
        $price = max(0, (int)$item['sell_price']);
        $id = (int)($item['ts_availability_end'] ?? 0);
        if ($id < 0) {
            $quality = (int)Db::value('SELECT quality FROM items WHERE character_id = ? AND id = ? AND type = 12', [$characterId, -$id]);
            // Constants oficiais, também usados por Item.get_sellPrice no cliente.
            $price += (int)round($price * ([1 => 0.25, 2 => 0.5, 3 => 1, 4 => 1.5][$quality] ?? 0));
        }
        return $price;
    }
}
