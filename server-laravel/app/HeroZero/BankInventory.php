<?php
declare(strict_types=1);
namespace HeroZero;

final class BankInventory
{
    public static function data(int $cid): array
    {
        $row = Db::row('SELECT * FROM bank_inventory WHERE character_id = ?', [$cid]);
        $data = ['id' => 0, 'character_id' => $cid, 'max_bank_index' => 0, 'locked_item_ids' => '[]'];
        for ($i = 1; $i <= 450; $i++) $data['bank_item'.$i.'_id'] = 0;
        if ($row !== null) foreach ($row as $k => $v) $data[$k] = $k === 'locked_item_ids' ? (string)$v : (int)$v;
        return $data;
    }

    public static function operate(int $cid, string $action, array $params): void
    {
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT bank_operation'); else $pdo->beginTransaction();
        try {
            Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid]);
            $bank = Db::row('SELECT * FROM bank_inventory WHERE character_id = ? FOR UPDATE', [$cid]);
            if ($bank === null) {
                Db::exec('INSERT INTO bank_inventory (character_id, max_bank_index) VALUES (?, 0)', [$cid]);
                $bank = self::data($cid);
            }
            $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
            $id = (int)($params['item_id'] ?? 0);
            $find = static function(array $row, string $prefix, int $limit, int $id): ?string {
                if ($id <= 0) return null;
                for ($i=1; $i<=$limit; $i++) if ((int)($row[$prefix.$i.'_id'] ?? 0) === $id) return $prefix.$i.'_id';
                return null;
            };
            $sourceBank = $find($bank, 'bank_item', 450, $id);
            $sourceBag = $find($inv, 'bag_item', 18, $id);
            $locked = json_decode($bank['locked_item_ids'] ?? '[]', true) ?: [];
            if ($action === 'upgradeBankInventory') {
                $next = (int)$bank['max_bank_index'] + 1;
                if ($next > 24) throw new GameError('errAddItemFromInventoryInvalidTargetSlot');
                $cost = $next === 1 ? 25 : 50;
                if (Db::exec('UPDATE `user` u JOIN `character` c ON c.user_id = u.id SET u.premium_currency = u.premium_currency - ? WHERE c.id = ? AND u.premium_currency >= ?', [$cost,$cid,$cost]) !== 1) throw new GameError('errRemovePremiumCurrencyNotEnough');
                Db::exec('UPDATE bank_inventory SET max_bank_index = ? WHERE character_id = ?', [$next,$cid]);
            } elseif (in_array($action, ['lockBankItem','unlockBankItem'], true)) {
                if ($sourceBank === null) throw new GameError('errInventoryInvalidItem');
                $locked = array_values(array_filter($locked, static fn($v) => (int)$v !== $id));
                if ($action === 'lockBankItem') $locked[] = $id;
                Db::exec('UPDATE bank_inventory SET locked_item_ids = ? WHERE character_id = ?', [json_encode($locked),$cid]);
            } elseif (in_array($action, ['sellBankInventoryItem','sellAllBankInventoryItems'], true)) {
                $ids = [$id];
                if ($action === 'sellAllBankInventoryItems') {
                    $page = (int)($params['bank_index'] ?? -1);
                    if ($page < 0 || $page > (int)$bank['max_bank_index'] || $page > 24) throw new GameError('errInventoryInvalidItem');
                    $ids = [];
                    for ($i=1;$i<=18;$i++) {
                        $candidate = (int)($bank['bank_item'.(18*$page+$i).'_id'] ?? 0);
                        if ($candidate > 0 && !in_array($candidate,array_map('intval',$locked),true)) $ids[] = $candidate;
                    }
                }
                foreach (array_unique($ids) as $sellId) {
                    $slot = $find($bank,'bank_item',450,$sellId);
                    if ($slot === null) throw new GameError('errInventoryInvalidItem');
                    if (in_array($sellId,array_map('intval',$locked),true)) throw new GameError('errSellItemItemIsLocked');
                    $item = Db::row('SELECT * FROM items WHERE character_id = ? AND id = ? FOR UPDATE',[$cid,$sellId]);
                    if ($item === null) throw new GameError('errInventoryInvalidItem');
                    $price = ItemImprovement::sellPrice($cid,$item);
                    ItemImprovement::deleteAttached($cid,$sellId);
                    Db::exec('DELETE FROM items WHERE character_id = ? AND id = ?',[$cid,$sellId]);
                    Db::exec("UPDATE bank_inventory SET `$slot` = 0 WHERE character_id = ?",[$cid]);
                    Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?',[$price,$cid]);
                }
            } else {
                if (!Db::value('SELECT id FROM items WHERE character_id = ? AND id = ? FOR UPDATE',[$cid,$id])) throw new GameError('errInventoryInvalidItem');
                if ($action === 'removeBankItemToInventory') {
                    if ($sourceBank === null) throw new GameError('errInventoryInvalidItem');
                    $target = (int)($params['target_slot'] ?? 0);
                    if ($target === 0) for ($i=1;$i<=18;$i++) if ((int)($inv['bag_item'.$i.'_id'] ?? 0) === 0) {$target=$i+8;break;}
                    if ($target < 9 || $target > 26) throw new GameError('errInventoryNoEmptySlot');
                    $field = 'bag_item'.($target-8).'_id';
                    $displaced = (int)$inv[$field];
                    Db::exec("UPDATE inventory SET `$field` = ? WHERE character_id = ?",[$id,$cid]);
                    Db::exec("UPDATE bank_inventory SET `$sourceBank` = ? WHERE character_id = ?",[max(0,$displaced),$cid]);
                } else {
                    $page = (int)($params['bank_index'] ?? -1);
                    $slot = (int)($params['target_slot_index'] ?? -1);
                    if ($page < 0 || $page > (int)$bank['max_bank_index'] || $page > 24 || $slot < 0 || $slot > 17) throw new GameError('errAddItemFromInventoryInvalidTargetSlot');
                    $target = 'bank_item'.(18*$page+$slot+1).'_id';
                    $source = $action === 'addInventoryItemToBank' ? $sourceBag : $sourceBank;
                    if ($source === null) throw new GameError('errInventoryInvalidItem');
                    $displaced = (int)($bank[$target] ?? 0);
                    $table = $action === 'addInventoryItemToBank' ? 'inventory' : 'bank_inventory';
                    if ($table !== 'bank_inventory' || $source !== $target) {
                        Db::exec("UPDATE `$table` SET `$source` = ? WHERE character_id = ?",[max(0,$displaced),$cid]);
                        Db::exec("UPDATE bank_inventory SET `$target` = ? WHERE character_id = ?",[$id,$cid]);
                    }
                }
            }
            if ($nested) $pdo->exec('RELEASE SAVEPOINT bank_operation'); else $pdo->commit();
        } catch (\Throwable $e) {
            if ($nested) { $pdo->exec('ROLLBACK TO SAVEPOINT bank_operation'); $pdo->exec('RELEASE SAVEPOINT bank_operation'); }
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
