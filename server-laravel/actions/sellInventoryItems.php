<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $ids = preg_split('/[;,]/', (string)($params['item_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if ($ids === []) return Live::accountState($userId);
    \HeroZero\Casino::assertItemsAvailable($char->id(), $ids);

    $pdo = Db::pdo();
    $nested = $pdo->inTransaction();
    if ($nested) $pdo->exec('SAVEPOINT herocon_sell'); else $pdo->beginTransaction();
    try {
        Db::row('SELECT id FROM `character` WHERE id=? FOR UPDATE', [$char->id()]);
        $inventory = Db::row('SELECT * FROM `inventory` WHERE character_id = ? FOR UPDATE', [$char->id()]);
        if ($inventory === null) throw new GameError('errInventoryNotFound');

        $bagByItem = [];
        foreach ($inventory as $field => $value) {
            if (str_starts_with((string)$field, 'bag_item') && str_ends_with((string)$field, '_id')) {
                $bagByItem[(int)$value] = (string)$field;
            }
        }
        foreach ($ids as $id) {
            if (!isset($bagByItem[$id])) throw new GameError('errInventoryInvalidItem');
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $items = Db::rows(
            "SELECT id, quality, sell_price, ts_availability_start, ts_availability_end FROM `items` WHERE character_id = ? AND id IN ($marks) FOR UPDATE",
            array_merge([$char->id()], $ids)
        );
        if (count($items) !== count($ids)) throw new GameError('errInventoryInvalidItem');

        foreach ($items as $item) \HeroZero\HeroCon::sold($char->id(), $item);
        $sellPrice = array_sum(array_map(static fn(array $item): int => \HeroZero\ItemImprovement::sellPrice($char->id(), $item), $items));
        $sets = [];
        foreach ($ids as $id) $sets[] = '`' . $bagByItem[$id] . '` = 0';
        Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$char->id()]);
        foreach ($ids as $id) \HeroZero\ItemImprovement::deleteAttached($char->id(), $id);
        Db::exec("DELETE FROM `items` WHERE character_id = ? AND id IN ($marks)", array_merge([$char->id()], $ids));
        Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$sellPrice, $char->id()]);
        if ($nested) $pdo->exec('RELEASE SAVEPOINT herocon_sell'); else $pdo->commit();
    } catch (Throwable $e) {
        if ($nested) { $pdo->exec('ROLLBACK TO SAVEPOINT herocon_sell'); $pdo->exec('RELEASE SAVEPOINT herocon_sell'); } elseif ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return Live::accountState($userId);
};
