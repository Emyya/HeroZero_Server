<?php
declare(strict_types=1);

/**
 * action: startHideoutRoomProduction — iniciar produção MANUAL numa sala.
 *
 * Semântica de productionCount por tipo de sala:
 *   attacker_production / defender_production → quantidade de unidades
 *   exchange_room / gym / blacksmith          → deve ser 1 (ignorado)
 *   gem_production (slot 1..7)                → índice de raridade 0..2
 *   gem_production (slot 13/14)               → deve ser 1
 *   guild_auto_join_production / _missile     → quantidade
 *   guild_medikit_production / _weapon_oil    → item_id a consumir
 *
 * Custos debitados NESTE handler (exceto unidades, que não têm custo):
 *   exchange_room → glue ou stone (calculado do rate e resource_duration)
 *   gym           → gold (calcStatCost com desconto passiv_bonus_amount_1)
 *   blacksmith    → cost_type/cost_amount de additional_value_2
 *   guild_auto_join → optical-change currency (stub — Fase 7)
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Hideout;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $char   = Character::loadByUser($userId);
    $cid    = $char->id();
    $h      = Hideout::requireRow($cid);

    $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
    $count  = (int)($params['productionCount'] ?? 0);

    $room = Hideout::roomById($cid, $roomId);
    if ($room === null || !Hideout::isManualProductionRoom((string)$room['identifier'])) {
        throw new GameError('errInvalidHideoutRoom');
    }
    if ((int)$room['status'] !== Hideout::STATUS_IDLE) {
        throw new GameError(Hideout::pendingActivityError('StartProduction', (int)$room['status']));
    }

    $identifier = (string)$room['identifier'];
    $now        = GameTime::now();
    $duration   = 60;
    $room['current_resource_amount'] = 0;
    $room['max_resource_amount']     = 0;

    // ── Unidades (attacker / defender) ──────────────────────────────────────
    if (in_array($identifier, ['attacker_production', 'defender_production'], true)) {
        $cfg      = Hideout::roomLevelConfig($identifier, (int)$room['level']);
        $maxCount = (int)($cfg['resource_production_max'] ?? 0);
        if ($count < 1 || $count > $maxCount) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $unitType = $identifier === 'attacker_production' ? 'attacker' : 'defender';
        $unitCfg  = Hideout::unitConfig($unitType, Hideout::producedUnitLevel($h));
        $duration = (int)ceil((float)($unitCfg['build_time'] ?? 1) * $count * 60);
        $room['max_resource_amount'] = $count;

        if ($unitType === 'attacker') {
            $char->setTutorialFlag('hideout_build_attacker_units', true);
        }

    // ── Exchange room ────────────────────────────────────────────────────────
    } elseif ($identifier === 'exchange_room') {
        if ($count !== 1) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $av2            = json_decode((string)($room['additional_value_2'] ?? ''), true) ?: [];
        $isZeronit      = (bool)($av2['is_zeronit'] ?? false);
        $exchDuration   = max(1, (int)($av2['duration'] ?? 10));
        $resourceDuration = (int)($av2['resource_duration'] ?? (int)Hideout::c('hideout_exchange_room_max_resource_duration_start', 480));

        // Resultado baseado na sala de produção correspondente
        $prodId   = $isZeronit ? 'glue_production' : 'stone_production';
        $allRooms = Hideout::roomsFor($cid);
        $prodRoom = null;
        foreach ($allRooms as $r) {
            if ((string)$r['identifier'] === $prodId) { $prodRoom = $r; break; }
        }
        $prodCfg = $prodRoom ? Hideout::roomLevelConfig($prodId, (int)$prodRoom['level']) : null;
        $prodMax = max(1, (int)($prodCfg['resource_production_max'] ?? 100));
        $prodMin = max(1, (int)($prodCfg['min_till_max_resource'] ?? 480));
        $resultAmount = max(1, (int)($resourceDuration * ($prodMax / $prodMin)));

        $baseRatio    = $isZeronit
            ? (float)Hideout::c('hideout_exchange_room_glue_to_stone_ratio', 0.5)
            : (float)Hideout::c('hideout_exchange_room_stone_to_glue_ratio', 2.0);
        $exchangeRate = max(0.01, (float)($av2['exchange_rate'] ?? 1.0));
        $cost         = max(1, (int)round($baseRatio / $exchangeRate * $resultAmount));

        if ($isZeronit) {
            if ((int)$h['current_resource_glue'] < $cost) {
                throw new GameError('errRemoveGlueNotEnough');
            }
            $h['current_resource_glue'] -= $cost;
        } else {
            if ((int)$h['current_resource_stone'] < $cost) {
                throw new GameError('errRemoveStoneNotEnough');
            }
            $h['current_resource_stone'] -= $cost;
        }
        Hideout::saveHideout($h);
        $duration = $exchDuration * 60;
        $room['max_resource_amount']     = $resultAmount;
        $room['current_resource_amount'] = $isZeronit ? 1 : 0; // flag para o collect

    // ── Gym ──────────────────────────────────────────────────────────────────
    } elseif ($identifier === 'gym') {
        if ($count !== 1) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $av2      = json_decode((string)($room['additional_value_2'] ?? ''), true) ?: [];
        $statType = max(1, min(4, (int)($av2['stat_type'] ?? 1)));
        $duration = max(60, (int)($av2['duration'] ?? 3600));
        $levelCfg = Hideout::roomLevelConfig($identifier, (int)$room['level']);
        $discount = max(0, min(100, (int)($levelCfg['passiv_bonus_amount_1'] ?? 0)));
        $statNames = ['', 'stamina', 'strength', 'critical_rating', 'dodge_rating'];
        $stat      = $statNames[$statType];
        $bought    = $char->statBought($stat);
        $rawCost   = $char->calcStatCost($bought, 1);
        $goldCost  = (int)round($rawCost * (1.0 - $discount / 100.0));

        if ($char->gameCurrency() < $goldCost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        if ($goldCost > 0) {
            Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$goldCost, $cid]);
        }
        $room['max_resource_amount'] = $statType; // guardado para o collect

    // ── Blacksmith ───────────────────────────────────────────────────────────
    } elseif ($identifier === 'blacksmith') {
        if ($count !== 1) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $av2     = json_decode((string)($room['additional_value_2'] ?? ''), true) ?: [];
        $costType = (int)($av2['cost_type'] ?? 1);
        $costAmt  = (int)($av2['cost_amount'] ?? 0);
        if ($costAmt <= 0) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        switch ($costType) {
            case 1: // gold
                if ($char->gameCurrency() < $costAmt) throw new GameError('errRemoveGameCurrencyNotEnough');
                Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$costAmt, $cid]);
                break;
            case 2: // glue
                if ((int)$h['current_resource_glue'] < $costAmt) throw new GameError('errRemoveGlueNotEnough');
                $h['current_resource_glue'] -= $costAmt;
                Hideout::saveHideout($h);
                break;
            case 3: // stone
                if ((int)$h['current_resource_stone'] < $costAmt) throw new GameError('errRemoveStoneNotEnough');
                $h['current_resource_stone'] -= $costAmt;
                Hideout::saveHideout($h);
                break;
        }
        $cfg      = Hideout::roomLevelConfig($identifier, (int)$room['level']);
        $duration = max(60, (int)($cfg['min_till_max_resource'] ?? 60) * 60);

    // ── Gem production (melhoria de item) ────────────────────────────────────
    } elseif ($identifier === 'gem_production') {
        $av2      = Hideout::improvementData($room);
        $slot     = (int)($av2['improvement_slot'] ?? 0);
        $duration = max(60, (int)Hideout::c('item_improvement_duration', 43200));
        if (!in_array($slot, [1, 2, 3, 4, 5, 6, 7, 13, 14], true)) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $room['additional_value_2'] = json_encode($av2);

        if ($slot === 13 || $slot === 14) {
            if ($count !== 1) {
                throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
            }
            $room['max_resource_amount'] = $slot;
        } else {
            if ($count < 0 || $count > 2) {
                throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
            }
            $rarities = (array)($av2['improvement_rarity'] ?? []);
            $rarity = $rarities[$count] ?? null;
            if (!is_array($rarity)) {
                throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
            }
            $room['max_resource_amount'] = $count; // índice de raridade
        }

    // ── Salas de guilda ──────────────────────────────────────────────────────
    } elseif (in_array($identifier, [
        'guild_auto_join_production', 'guild_missile_production',
        'guild_medikit_production', 'guild_weapon_oil_production',
    ], true)) {
        if ($count < 1) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $durationConsts = [
            'guild_auto_join_production'  => 'hideout_guild_auto_join_room_duration',
            'guild_missile_production'    => 'hideout_guild_missile_room_duration',
            'guild_medikit_production'    => 'hideout_guild_medikit_room_duration_common',
            'guild_weapon_oil_production' => 'hideout_guild_weapon_oil_room_duration_common',
        ];
        $dConst  = (float)Hideout::c($durationConsts[$identifier] ?? '', 60);
        $genFactor = Hideout::generatorDurationFactor($room);
        $duration = max(1, (int)ceil($dConst * 60 / $genFactor));
        $room['max_resource_amount'] = $count;

    } else {
        throw new GameError('errInvalidHideoutRoom');
    }

    $room['status']                  = Hideout::STATUS_PRODUCING;
    $room['ts_activity_end']         = $now + $duration;
    $room['ts_last_resource_change'] = $now;

    Db::exec(
        'UPDATE `hideout_rooms` SET status = ?, max_resource_amount = ?, current_resource_amount = ?, ts_activity_end = ?, ts_last_resource_change = ?, additional_value_2 = ? WHERE id = ?',
        [
            $room['status'],
            $room['max_resource_amount'],
            $room['current_resource_amount'],
            $room['ts_activity_end'],
            $now,
            (string)($room['additional_value_2'] ?? ''),
            $roomId,
        ]
    );

    return Live::accountState($userId);
};
