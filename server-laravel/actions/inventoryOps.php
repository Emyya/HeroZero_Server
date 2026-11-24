<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Operacoes de inventario/banco/cinto de missils.
 *
 * Mapa de slots do cliente html5_257 (moveInventoryItem):
 *   1..8  = equipamento (mask, cape, suit, belt, boots, weapon, gadget, missiles)
 *   9..26 = mochila (bag_item1..bag_item18)
 * Banco: bank_item1..bank_item450 (tabela bank_inventory, max_bank_index).
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();
    \HeroZero\HeroCon::assertItemsAvailable($cid, [$params['item_id'] ?? 0,$params['target_item_id'] ?? 0]);
    \HeroZero\Casino::assertItemsAvailable($cid, [$params['item_id'] ?? 0,$params['target_item_id'] ?? 0]);

    $slotField = static function (int $slot): ?string {
        $equip = [1 => 'mask_item_id', 2 => 'cape_item_id', 3 => 'suit_item_id', 4 => 'belt_item_id',
                  5 => 'boots_item_id', 6 => 'weapon_item_id', 7 => 'gadget_item_id', 8 => 'missiles_item_id'];
        if (isset($equip[$slot])) return $equip[$slot];
        if ($slot >= 9 && $slot <= 26) return 'bag_item' . ($slot - 8) . '_id';
        return null;
    };

    $findSlotOf = static function (array $inv, int $itemId, string $prefix = ''): ?string {
        foreach ($inv as $field => $value) {
            if ((int)$value !== $itemId) continue;
            $f = (string)$field;
            if (!str_ends_with($f, '_item_id') && !preg_match('/^(bag|bank|shop)/', $f) ) continue;
            if ($prefix !== '' && !str_starts_with($f, $prefix)) continue;
            if (str_starts_with($f, 'shop')) continue;
            return $f;
        }
        return null;
    };

    $deleteItem = static function (int $cid, int $itemId): void {
        \HeroZero\ItemImprovement::deleteAttached($cid, $itemId);
        Db::exec('DELETE FROM `items` WHERE character_id = ? AND id = ?', [$cid, $itemId]);
    };

    /** Primeira posicao livre da mochila (bag_item1..18), ou null se estiver cheia. */
    $freeBagSlot = static function (array $inv): ?string {
        for ($i = 1; $i <= 18; $i++) {
            $f = 'bag_item' . $i . '_id';
            if (array_key_exists($f, $inv) && (int)$inv[$f] <= 0) return $f;
        }
        return null;
    };

    switch ($action) {
        case 'moveInventoryItem': {
            $missileId = (int)($params['item_id'] ?? 0);
            $missileInv = Db::row('SELECT * FROM inventory WHERE character_id = ?', [$cid]) ?? [];
            $missileSource = false;
            foreach (['missiles_item_id','missiles1_item_id','missiles2_item_id','missiles3_item_id','missiles4_item_id'] as $field) {
                if ($missileId > 0 && (int)($missileInv[$field] ?? 0) === $missileId) $missileSource = true;
            }
            $missileType = (int)Db::value('SELECT type FROM items WHERE id = ? AND character_id = ?', [$missileId, $cid]);
            if ((int)($params['target_slot'] ?? 0) === 8 || $missileSource || $missileType === 8) {
                \HeroZero\Missiles::operate($cid, $action, $params);
                break;
            }

            $itemId = (int)($params['item_id'] ?? 0);
            $target = $slotField((int)($params['target_slot'] ?? 0));
            if ($itemId > 0 && $target !== null) {
                if (in_array($itemId, array_map('intval', array_column(\HeroZero\ItemImprovement::data($cid), 'id')), true)) {
                    throw new GameError('errInventoryInvalidItem');
                }
                $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                $source = $findSlotOf($inv, $itemId);
                $displaced = (int)($inv[$target] ?? 0);
                $sets = ["`{$target}` = " . $itemId];
                if ($source !== null && $source !== $target) {
                    // swap: o item que estava no destino volta pro slot de origem
                    $sets[] = "`{$source}` = " . ($displaced > 0 ? $displaced : 0);
                } elseif ($source === null && $displaced > 0) {
                    // O item arrastado NAO estava no inventario (veio da loja), logo
                    // nao ha slot de origem para onde devolver o que estava equipado.
                    // Sem isto o item substituido ficava orfao — nenhum slot o
                    // referenciava e desaparecia como se tivesse sido vendido.
                    $bag = $freeBagSlot($inv);
                    if ($bag === null) {
                        throw new GameError('errInventoryNoEmptySlot');
                    }
                    $sets[] = "`{$bag}` = " . $displaced;
                }
                Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$cid]);
            }
            break;
        }

        case 'sellInventoryItem': {
            $id = (int)($params['item_id'] ?? 0);
            if ((int)Db::value('SELECT type FROM items WHERE id = ? AND character_id = ?', [$id, $cid]) === 8) {
                \HeroZero\Missiles::operate($cid, $action, $params);
                break;
            }

            \HeroZero\StoryDungeon::transaction($cid, function () use ($params, $cid, $findSlotOf, $deleteItem): void {
                $itemId = (int)($params['item_id'] ?? 0);
                if ($itemId > 0) {
                    $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
                    $slot = $findSlotOf($inv, $itemId);
                    $item = Db::row('SELECT id, quality, sell_price, ts_availability_start, ts_availability_end FROM `items` WHERE character_id = ? AND id = ? FOR UPDATE', [$cid, $itemId]);
                    if ($slot !== null && $item !== null) {
                        \HeroZero\HeroCon::sold($cid, $item);
                        $sellPrice = \HeroZero\ItemImprovement::sellPrice($cid, $item);
                        Db::exec("UPDATE `inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                        $deleteItem($cid, $itemId);
                        Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$sellPrice, $cid]);
                    }
                }
            });
            break;
        }

        case 'useInventoryItem': {
            $itemId = (int)($params['item_id'] ?? 0);
            if ($itemId > 0) {
                $item = Db::row('SELECT id, identifier, type, charges FROM `items` WHERE character_id = ? AND id = ?', [$cid, $itemId]);
                if ($item !== null) {
                    if ((int)$item['type'] === 8) throw new GameError('errInventoryInvalidItem');
                    if (isset(\HeroZero\HeroConBox::BOXES[$item['identifier']])) {
                        $rewards=\HeroZero\HeroConBox::open($cid,$itemId);
                        $state=Live::accountState($userId);
                        // Router aplica este filtro depois dos sistemas que atualizam items.
                        $state['_herocon_box_reward_ids']=array_map('intval',array_column($rewards,'id'));
                        return $state;
                    }
                    if (in_array((int)$item['type'], [12, 13, 14], true)) {
                        $sidekickTarget = (int)($params['target_sidekick_id'] ?? 0);
                        if ($sidekickTarget > 0 && (int)($params['target_item_id'] ?? 0) > 0) throw new GameError('errInventoryInvalidItem');
                        \HeroZero\ItemImprovement::useItem($cid, $itemId, $sidekickTarget > 0 ? $sidekickTarget : (int)($params['target_item_id'] ?? 0),
                            filter_var($params['replace_existing'] ?? false, FILTER_VALIDATE_BOOLEAN), $sidekickTarget > 0);
                    } elseif ($item['identifier'] === 'special_reskill') {
                        // Redistribuicao de stats: o cliente envia os novos valores desejados.
                        $newStamina  = (int)($params['stamina']         ?? 0);
                        $newStrength = (int)($params['strength']        ?? 0);
                        $newCritical = (int)($params['critical_rating'] ?? 0);
                        $newDodge    = (int)($params['dodge_rating']    ?? 0);
                        $cur = Db::row(
                            'SELECT stat_base_stamina, stat_base_strength,
                                    stat_base_critical_rating, stat_base_dodge_rating,
                                    stat_points_available
                               FROM `character` WHERE id = ?', [$cid]
                        ) ?? [];
                        $oldTotal = (int)$cur['stat_base_stamina']
                                  + (int)$cur['stat_base_strength']
                                  + (int)$cur['stat_base_critical_rating']
                                  + (int)$cur['stat_base_dodge_rating']
                                  + (int)$cur['stat_points_available'];
                        $newTotal = $newStamina + $newStrength + $newCritical + $newDodge;
                        if ($newTotal !== $oldTotal
                            || $newStamina < 10 || $newStrength < 10
                            || $newCritical < 10 || $newDodge < 10) {
                            throw new GameError('errReskillInvalidStats');
                        }
                        // Consome o item
                        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                        $slot = $findSlotOf($inv, $itemId);
                        if ($slot !== null) {
                            Db::exec("UPDATE `inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                        }
                        $deleteItem($cid, $itemId);
                        // Redistribui os bases, preservando o histórico stat_bought e o custo futuro.
                        Db::exec(
                            'UPDATE `character`
                                SET stat_base_stamina           = ?,
                                    stat_base_strength          = ?,
                                    stat_base_critical_rating   = ?,
                                    stat_base_dodge_rating      = ?,
                                    stat_points_available       = 0
                              WHERE id = ?',
                            [$newStamina, $newStrength, $newCritical, $newDodge, $cid]
                        );
                    } else {
                        $charges = (int)$item['charges'] - 1;
                        if ($charges > 0) {
                            Db::exec('UPDATE `items` SET charges = ? WHERE character_id = ? AND id = ?', [$charges, $cid, $itemId]);
                        } else {
                            $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                            $slot = $findSlotOf($inv, $itemId);
                            if ($slot !== null) {
                                Db::exec("UPDATE `inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                            }
                            $deleteItem($cid, $itemId);
                        }
                    }
                }
            }
            break;
        }

        case 'washInventoryItem': {
            $itemId     = (int)($params['item_id'] ?? 0);
            $usePremium = !empty($params['use_premium']);
            $char->washItem($itemId, $usePremium);
            break;
        }

        case 'addInventoryItemToBank':
        case 'removeBankItemToInventory':
        case 'moveBankInventoryItem':
        case 'sellBankInventoryItem':
        case 'sellAllBankInventoryItems':
        case 'upgradeBankInventory':
        case 'lockBankItem':
        case 'unlockBankItem':
            \HeroZero\BankInventory::operate($cid, $action, $params);
            break;

        case 'moveAmmoBeltItem':
        case 'upgradeAmmoBelt':
            \HeroZero\Missiles::operate($cid, $action, $params);
            break;

        // sewInventoryItem:
        // sem coluna persistida propria — o eco do accountState mantem o cliente
        // consistente com o que o banco realmente tem.
        default:
            break;
    }

    return Live::accountState($userId);
};
