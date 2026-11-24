<?php
declare(strict_types=1);

/**
 * Ponto único para todas as operações do hideout que não têm ficheiro próprio.
 * Roteia por $params['action'] (o router principal já isolou o grupo hideout*).
 *
 * Ações cobertas: collect, collectAll, produceAll, slot unlock×4, abort×3,
 * oponente×2, combate×5, histórico×4, leaderboard.
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Hideout;
use HeroZero\ItemImprovement;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $char = Character::loadByUser($userId);
    $cid = $char->id();
    $action = (string)($params['action'] ?? '');

    // ── Collect individual ─────────────────────────────────────────────────────
    if ($action === 'collectHideoutRoomActivityResult') {
        return \HeroZero\Missiles::transaction([$cid], function () use ($cid,$userId,$params,$char): array {
            $h = Hideout::requireRow($cid);
            $roomId = (int)($params['hideout_room_id'] ?? $params['hideoutRoomId'] ?? 0);
            $room = Hideout::roomById($cid, $roomId);
            if ($room === null) {
                throw new GameError('errCollectActivityResultInvalidStatus');
            }
            $allRooms = Hideout::roomsFor($cid);
            // Os max_* da linha `hideout` sao derivados das salas colocadas e so eram
            // recalculados em memoria no statePayload — a linha crua podia trazer um
            // limite velho (ex.: max_attacker_units=1 com a sala ja no nivel 2), e o
            // min() abaixo engolia a unidade recolhida. Recalcula ANTES de creditar.
            Hideout::applyCaps($h, $allRooms);
            $now = GameTime::now();
            $identifier = (string)$room['identifier'];
    
            if (Hideout::isAutoProductionRoom($identifier)) {
                // Auto: accrual até agora; continua PRODUCING.
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                $amount = Hideout::autoRoomProducedAmount($room, $now, $h, $allRooms);
                $gain = max(0, $amount);
                // Credita recurso
                match ($identifier) {
                    'main_building'  => Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$gain, $cid]),
                    'glue_production'=> ($h['current_resource_glue']  = min((int)$h['max_resource_glue'], (int)$h['current_resource_glue'] + $gain)),
                    'stone_production'=> ($h['current_resource_stone'] = min((int)$h['max_resource_stone'], (int)$h['current_resource_stone'] + $gain)),
                    'xp_production'  => Hideout::grantXp($cid, $gain),
                    default          => null,
                };
                $room['current_resource_amount'] = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
                if (in_array($identifier, ['glue_production', 'stone_production'], true)) {
                    Hideout::saveHideout($h);
                }
            } elseif (in_array($identifier, ['attacker_production', 'defender_production'], true)) {
                // Manual unidades: só recolhe quando produção terminou.
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                if ((int)$room['ts_activity_end'] > $now) {
                    throw new GameError('errCollectHideoutRoomActivityNotJetPossible');
                }
                $count = (int)$room['max_resource_amount'];
                if ($identifier === 'attacker_production') {
                    $h['current_attacker_units'] = min(
                        (int)$h['max_attacker_units'],
                        (int)$h['current_attacker_units'] + $count
                    );
                } else {
                    $h['current_defender_units'] = min(
                        (int)$h['max_defender_units'],
                        (int)$h['current_defender_units'] + $count
                    );
                }
                $room['status'] = Hideout::STATUS_IDLE;
                $room['current_resource_amount'] = 0;
                $room['max_resource_amount'] = 0;
                $room['ts_activity_end'] = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
                Hideout::saveHideout($h);
            } elseif ($identifier === 'exchange_room') {
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                if ((int)$room['ts_activity_end'] > $now) {
                    throw new GameError('errCollectHideoutRoomActivityNotJetPossible');
                }
                $isZeronit    = (int)$room['current_resource_amount'] === 1;
                $resultAmount = (int)$room['max_resource_amount'];
                if ($isZeronit) {
                    $h['current_resource_stone'] = min((int)$h['max_resource_stone'],
                        (int)$h['current_resource_stone'] + $resultAmount);
                } else {
                    $h['current_resource_glue'] = min((int)$h['max_resource_glue'],
                        (int)$h['current_resource_glue'] + $resultAmount);
                }
                $room['status']                  = Hideout::STATUS_IDLE;
                $room['current_resource_amount'] = 0;
                $room['max_resource_amount']     = 0;
                $room['ts_activity_end']         = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
                Hideout::saveHideout($h);
    
            } elseif ($identifier === 'gym') {
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                if ((int)$room['ts_activity_end'] > $now) {
                    throw new GameError('errCollectHideoutRoomActivityNotJetPossible');
                }
                // Gold já debitado no startProduction; apenas incrementa o stat base
                $statType  = max(1, min(4, (int)$room['max_resource_amount']));
                $statNames = ['', 'stamina', 'strength', 'critical_rating', 'dodge_rating'];
                $stat = $statNames[$statType];
                Db::exec("UPDATE `character` SET `stat_base_{$stat}` = `stat_base_{$stat}` + 1 WHERE id = ?", [$cid]);
                $room['status']                  = Hideout::STATUS_IDLE;
                $room['current_resource_amount'] = 0;
                $room['max_resource_amount']     = 0;
                $room['ts_activity_end']         = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
    
            } elseif ($identifier === 'blacksmith') {
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                if ((int)$room['ts_activity_end'] > $now) {
                    throw new GameError('errCollectHideoutRoomActivityNotJetPossible');
                }
                $av2      = json_decode((string)($room['additional_value_2'] ?? ''), true) ?: [];
                $slotType = (string)($av2['item_identifier'] ?? 'weapon');
                $plusLvl  = (int)($av2['plus_item_level'] ?? 0);
                $quality  = max(2, min(3, (int)($av2['quality'] ?? 2)));
                $itemId   = $char->createBlacksmithItem($slotType, $plusLvl, $quality);
                $room['status']                  = Hideout::STATUS_IDLE;
                $room['current_resource_amount'] = 0;
                $room['max_resource_amount']     = 0;
                $room['ts_activity_end']         = 0;
                $room['ts_last_resource_change'] = $now;
                $room['additional_value_1']      = (string)$itemId;
                Hideout::saveRoom($room);
    
            } elseif ($identifier === 'gem_production') {
                // A transação exterior já bloqueia o personagem e reverte toda a
                // recolha em caso de erro, incluindo a criação e entrega do item.
                $inventory = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
                $room = Db::row('SELECT * FROM hideout_rooms WHERE character_id = ? AND id = ? FOR UPDATE', [$cid, (int)$room['id']]);
                if ($room === null) throw new GameError('errInvalidHideoutRoom');
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                if ((int)$room['ts_activity_end'] > $now) {
                    throw new GameError('errCollectHideoutRoomActivityNotJetPossible');
                }
                $av2           = Hideout::improvementData($room);
                $room['additional_value_2'] = json_encode($av2);
                $slot          = (int)($av2['improvement_slot'] ?? 0);
                $rarityOrSlot  = (int)$room['max_resource_amount'];
    
                $freeSlot = null;
                for ($i = 1; $i <= 18; $i++) {
                    if ((int)($inventory['bag_item' . $i . '_id'] ?? 0) === 0) {
                        $freeSlot = 'bag_item' . $i . '_id';
                        break;
                    }
                }
                if ($freeSlot === null) throw new GameError('errInventoryNoEmptySlot');
    
                if ($slot === 13 || $slot === 14) {
                    $specialId = $slot === 13
                        ? (string)Hideout::c('hideout_add_improvement_slot_identifier', 'special_add_improvement_slot')
                        : (string)Hideout::c('hideout_add_sidekick_improvement_slot_identifier', 'special_add_sidekick_improvement_slot');
                    $createdItemId = $char->createSpecialItem($specialId, $slot);
                } else {
                    $rarityIndex = $rarityOrSlot;
                    $rarities    = (array)($av2['improvement_rarity'] ?? []);
                    $rarity      = $rarities[$rarityIndex] ?? null;
                    if (!is_array($rarity) || !in_array($slot, [1, 2, 3, 4, 5, 6, 7], true)) {
                        throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
                    }
                    $quality     = max(1, min(3, $rarityIndex + 1));
                    \HeroZero\HeroCon::record($cid, [1=>26,2=>27,3=>28][$quality]);
                    $createdItemId = $char->createImprovementItem($slot, $quality, [
                        'stat_stamina'        => (int)($rarity['stat_stamina'] ?? 0),
                        'stat_strength'       => (int)($rarity['stat_strength'] ?? 0),
                        'stat_critical_rating'=> (int)($rarity['stat_critical_rating'] ?? 0),
                        'stat_dodge_rating'   => (int)($rarity['stat_dodge_rating'] ?? 0),
                    ]);
                }
                Db::exec("UPDATE inventory SET `{$freeSlot}` = ? WHERE character_id = ?", [$createdItemId, $cid]);
                $room['status']                  = Hideout::STATUS_IDLE;
                $room['current_resource_amount'] = 0;
                $room['max_resource_amount']     = 0;
                $room['ts_activity_end']         = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
            } elseif (in_array($identifier, [
                'guild_auto_join_production', 'guild_missile_production',
                'guild_medikit_production', 'guild_weapon_oil_production',
            ], true)) {
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    throw new GameError('errCollectActivityResultInvalidStatus');
                }
                if ((int)$room['ts_activity_end'] > $now) {
                    throw new GameError('errCollectHideoutRoomActivityNotJetPossible');
                }
                // TODO Fase 7: creditar resultado no inventário de guilda
                $room['status']                  = Hideout::STATUS_IDLE;
                $room['current_resource_amount'] = 0;
                $room['max_resource_amount']     = 0;
                $room['ts_activity_end']         = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
    
            } else {
                throw new GameError('errCollectActivityResultInvalidStatus');
            }
            return Live::accountState($userId);
        });
    }

    // ── Collect all auto rooms ─────────────────────────────────────────────────
    if ($action === 'collectAllHideoutRoomResources') {
        return \HeroZero\Missiles::transaction([$cid], function () use ($cid,$userId,$params,$char): array {
            $h = Hideout::requireRow($cid);
            $rooms = Hideout::roomsFor($cid);
            $now = GameTime::now();
            $didCollect = false;
            foreach ($rooms as &$room) {
                if (!Hideout::isAutoProductionRoom($room['identifier'])) {
                    continue;
                }
                if ((int)$room['status'] !== Hideout::STATUS_PRODUCING) {
                    continue;
                }
                $amount = Hideout::autoRoomProducedAmount($room, $now, $h, $rooms);
                $gain = max(0, $amount);
                if ($gain <= 0) {
                    continue;
                }
                match ($room['identifier']) {
                    'main_building'  => Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$gain, $cid]),
                    'glue_production'=> ($h['current_resource_glue']  = min((int)$h['max_resource_glue'], (int)$h['current_resource_glue'] + $gain)),
                    'stone_production'=> ($h['current_resource_stone'] = min((int)$h['max_resource_stone'], (int)$h['current_resource_stone'] + $gain)),
                    'xp_production'  => Hideout::grantXp($cid, $gain),
                    default          => null,
                };
                $room['current_resource_amount'] = 0;
                $room['ts_last_resource_change'] = $now;
                Hideout::saveRoom($room);
                $didCollect = true;
            }
            unset($room);
            if ($didCollect) {
                Hideout::saveHideout($h);
            } else {
                throw new GameError('errCollectAllHideoutRoomResourcesNothingToCollect');
            }
            return Live::accountState($userId);
        });
    }

    // ── Produce all units ──────────────────────────────────────────────────────
    if ($action === 'produceAllHideoutUnits') {
        $h = Hideout::requireRow($cid);
        $rooms = Hideout::roomsFor($cid);
        $now = GameTime::now();
        $started = false;
        foreach ($rooms as &$room) {
            if (!in_array($room['identifier'], ['attacker_production', 'defender_production'], true)) {
                continue;
            }
            if ((int)$room['status'] !== Hideout::STATUS_IDLE) {
                continue;
            }
            $cfg = Hideout::roomLevelConfig($room['identifier'], (int)$room['level']);
            $maxCount = (int)($cfg['resource_production_max'] ?? 0);
            if ($maxCount <= 0) {
                continue;
            }
            $unitType = $room['identifier'] === 'attacker_production' ? 'attacker' : 'defender';
            $unitCfg = Hideout::unitConfig($unitType, Hideout::producedUnitLevel($h));
            $duration = (int)ceil((float)($unitCfg['build_time'] ?? 1) * $maxCount * 60);
            $room['status'] = Hideout::STATUS_PRODUCING;
            $room['max_resource_amount'] = $maxCount;
            $room['current_resource_amount'] = 0;
            $room['ts_activity_end'] = $now + $duration;
            $room['ts_last_resource_change'] = $now;
            Hideout::saveRoom($room);
            $started = true;
        }
        unset($room);
        if (!$started) {
            throw new GameError('errProduceAllHideoutUnitsNothingToProduce');
        }
        return Live::accountState($userId);
    }

    // ── Slot unlock ────────────────────────────────────────────────────────────
    if ($action === 'unlockHideoutRoomSlot') {
        $h = Hideout::requireRow($cid);
        $level = (int)($params['level'] ?? -1);
        $slot  = (int)($params['slot'] ?? -1);
        if ($level < 0 || $level >= Hideout::MAX_LEVELS || $slot < 0 || $slot >= Hideout::MAX_SLOTS) {
            throw new GameError('errUnlockRoomSlotInvalidStatus');
        }
        $key = Hideout::slotKey($level, $slot);
        $slotVal = (int)$h[$key];
        if ($slotVal !== -1) {
            // Já desbloqueado ou em processo.
            throw new GameError('errUnlockRoomSlotInvalidStatus');
        }
        if ((int)$h['idle_worker_count'] <= 0) {
            throw new GameError('errUnlockRoomSlotNoIdleWorker');
        }
        // Conta slots já desbloqueados (0 ou > 0 ou <= -2).
        $unlockedCount = 0;
        for ($l = 0; $l < Hideout::MAX_LEVELS; $l++) {
            for ($s = 0; $s < Hideout::MAX_SLOTS; $s++) {
                $v = (int)$h[Hideout::slotKey($l, $s)];
                if ($v !== -1) {
                    $unlockedCount++;
                }
            }
        }
        $cost = Hideout::slotExpansionCost($unlockedCount);
        $gold = Hideout::reducedGold($h, (float)($cost['gold'] ?? 0));
        Hideout::checkCosts($char, $h, $gold, (float)($cost['glue'] ?? 0), (float)($cost['stone'] ?? 0));
        Hideout::applyCosts($char, $h, $gold, (float)($cost['glue'] ?? 0), (float)($cost['stone'] ?? 0));
        $duration = Hideout::slotUnlockDuration($unlockedCount);
        if ($duration <= 0) {
            // Desbloqueio instantâneo (master data duration=0): não consome worker.
            $h[$key] = 0;
        } else {
            Hideout::takeWorker($h);
            $h[$key] = -(GameTime::now() + $duration);
        }
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }

    if ($action === 'checkUnlockHideoutRoomSlotFinished') {
        $h = Hideout::requireRow($cid);
        $level = (int)($params['level'] ?? -1);
        $slot  = (int)($params['slot'] ?? -1);
        if ($level < 0 || $level >= Hideout::MAX_LEVELS || $slot < 0 || $slot >= Hideout::MAX_SLOTS) {
            throw new GameError('errUnlockRoomSlotInvalidStatus');
        }
        $key = Hideout::slotKey($level, $slot);
        $slotVal = (int)$h[$key];
        if ($slotVal >= 0) {
            // Ja concluido (sweep auto-completou) — sincroniza sem erro.
            return Live::accountState($userId);
        }
        if ($slotVal === -1) {
            // Slot bloqueado (nunca em progresso) — invalido.
            throw new GameError('errUnlockRoomSlotInvalidStatus');
        }
        if (-$slotVal > GameTime::now()) {
            throw new GameError('errUnlockRoomSlotInvalidStatus');
        }
        $h[$key] = 0;
        Hideout::giveWorkerBack($h);
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }

    if ($action === 'instantFinishHideoutSlotUnlock') {
        $h = Hideout::requireRow($cid);
        $level = (int)($params['level'] ?? -1);
        $slot  = (int)($params['slot'] ?? -1);
        if ($level < 0 || $level >= Hideout::MAX_LEVELS || $slot < 0 || $slot >= Hideout::MAX_SLOTS) {
            throw new GameError('errUnlockRoomSlotInvalidStatus');
        }
        $key = Hideout::slotKey($level, $slot);
        $slotVal = (int)$h[$key];
        if ($slotVal >= 0) {
            // Ja desbloqueado — cliente com estado antigo, refrescar sem erro.
            return Live::accountState($userId);
        }
        if ($slotVal === -1) {
            throw new GameError('errInstantFinishActivityInvalidStatus');
        }
        $remaining = max(0, -$slotVal - GameTime::now());
        $cost = Hideout::instantFinishCost($remaining);
        Hideout::debitPremium($char, $cost);
        $h[$key] = 0;
        Hideout::giveWorkerBack($h);
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }

    if ($action === 'abortHideoutRoomSlotUnlocking') {
        $h = Hideout::requireRow($cid);
        $level = (int)($params['level'] ?? -1);
        $slot  = (int)($params['slot'] ?? -1);
        if ($level < 0 || $level >= Hideout::MAX_LEVELS || $slot < 0 || $slot >= Hideout::MAX_SLOTS) {
            throw new GameError('errAbortUnlockingInvalidStatus');
        }
        $key = Hideout::slotKey($level, $slot);
        $slotVal = (int)$h[$key];
        if ($slotVal > -2) {
            throw new GameError('errAbortUnlockingInvalidStatus');
        }
        $h[$key] = -1;
        Hideout::giveWorkerBack($h);
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }

    // ── Abort sala ────────────────────────────────────────────────────────────
    if ($action === 'abortHideoutRoomStoring' || $action === 'abortHideoutRoomUpgrading') {
        $h = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room = Hideout::roomById($cid, $roomId);
        $errKey = $action === 'abortHideoutRoomStoring' ? 'errAbortStoringInvalidStatus' : 'errAbortUpgradingInvalidStatus';
        $expectedStatus = $action === 'abortHideoutRoomStoring' ? Hideout::STATUS_STORING : Hideout::STATUS_UPGRADING;
        if ($room === null || (int)$room['status'] !== $expectedStatus) {
            throw new GameError($errKey);
        }
        $room['status'] = Hideout::STATUS_IDLE;
        $room['ts_activity_end'] = 0;
        Hideout::giveWorkerBack($h);
        Hideout::saveRoom($room);
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }

    // ── Oponente ──────────────────────────────────────────────────────────────
    if ($action === 'refreshHideoutOpponent') {
        $h = Hideout::requireRow($cid);
        $usePremium = (bool)($params['use_premium'] ?? false);
        $now = GameTime::now();
        $cooldown = (int)Hideout::c('hideout_opponent_refresh_free_cooldown', 10) * 60;
        if (!$usePremium && (int)$h['ts_last_opponent_refresh'] + $cooldown > $now) {
            throw new GameError('errRefreshHideoutOpponentNotYetAllowed');
        }
        if ($usePremium) {
            $cost = (int)Hideout::c('hideout_opponent_refresh_premium_amount', 1);
            Hideout::debitPremium($char, $cost);
        }
        $newOpponentCid = Hideout::selectOpponent($cid, (int)$h['hideout_points'], (int)$char->level(), (int)$h['current_opponent_id']);
        if ($newOpponentCid <= 0) {
            throw new GameError('errRefreshHideoutOpponentNoOpponents');
        }
        $h['current_opponent_id'] = $newOpponentCid;
        $h['ts_last_opponent_refresh'] = $now;
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }

    if ($action === 'getHideoutOpponent') {
        $h = Hideout::requireRow($cid);
        if ((int)$h['current_opponent_id'] <= 0) {
            $newCid = Hideout::selectOpponent($cid, (int)$h['hideout_points'], (int)$char->level());
            if ($newCid <= 0) {
                throw new GameError('errRefreshHideoutOpponentNoOpponents');
            }
            $h['current_opponent_id'] = $newCid;
            $h['ts_last_opponent_refresh'] = GameTime::now();
            Hideout::saveHideout($h);
        }
        return Live::accountState($userId);
    }

    // ── Combate ───────────────────────────────────────────────────────────────
    if ($action === 'startHideoutFight' || $action === 'startHideoutTutorialFight') {
        $initial = Hideout::requireRow($cid);
        $opponent = $action === 'startHideoutTutorialFight' ? 0 : (int)$initial['current_opponent_id'];
        return \HeroZero\Missiles::transaction([$cid,$opponent], function () use ($cid,$opponent,$action,$params,$char,$userId): array {
        $h = Hideout::requireRow($cid);
        $isTutorial = $action === 'startHideoutTutorialFight';
        if (!$isTutorial && (int)$h['current_opponent_id'] !== $opponent) throw new GameError('errStartHideoutFightNoOpponent');
        $attackerCount = (int)($params['attacker_count'] ?? 0);
        $now = GameTime::now();

        if ($isTutorial) {
            if ($char->getTutorialFlag('hideout_tutorial_fight_done')) {
                throw new GameError('errstartHideoutTutorialFightAlreadyFinished');
            }
        } else {
            if ((int)$h['active_battle_id'] > 0) {
                throw new GameError('errStartHideoutFightNotJetPossible');
            }
            $cooldown = (int)Hideout::c('hideout_battle_cooldown', 30) * 60;
            $usePremium = (bool)($params['use_premium'] ?? false);
            if (!$usePremium && (int)$h['ts_last_lost_attack'] + $cooldown > $now) {
                throw new GameError('errStartHideoutFightNotYetAllowed');
            }
            if ($usePremium && (int)$h['ts_last_lost_attack'] + $cooldown > $now) {
                $skipCost = (int)Hideout::c('hideout_battle_skip_cooldown_premium_amount', 5);
                Hideout::debitPremium($char, $skipCost);
            }
        }

        if ($attackerCount < 1 || $attackerCount > (int)$h['current_attacker_units']) {
            throw new GameError('errstartHideoutBattleInvalidAttackerCount');
        }

        // Defensor
        $defenderHideout = null;
        $defenderCid = 0;
        if ($isTutorial) {
            // Defensor fictício com stats da constante
            $defenderHideout = [
                'id'                    => -1,
                'current_defender_units'=> 0,
                'current_wall_level'    => 0,
                'current_attacker_units'=> 0,
                'current_resource_glue' => 0,
                'current_resource_stone'=> 0,
                'hideout_points'        => 0,
                'current_barracks_level'=> 0,
                'max_barracks_level'    => 0,
            ];
        } else {
            $opponentCid = (int)$h['current_opponent_id'];
            if ($opponentCid <= 0) {
                throw new GameError('errStartHideoutFightNoOpponent');
            }
            $defenderHideout = Hideout::rowFor($opponentCid);
            if ($defenderHideout === null) {
                throw new GameError('errStartHideoutFightInvalidTarget');
            }
            $defenderCid = $opponentCid;

            // Validação do loot previsto (tolerância 80%).
            $winsAgainst = Hideout::countWinsAgainst((int)$h['id'], (int)$defenderHideout['id']);
            $lootPreview = Hideout::opponentLoot($defenderHideout, $winsAgainst);
            $expectedGlue  = (int)($params['expacted_glue_reward'] ?? 0);
            $expectedStone = (int)($params['expacted_stone_reward'] ?? 0);
            $bonusRewards  = json_decode($lootPreview['bonus_rewards'] ?: '{}', true) ?: [];
            $totalExpected = $expectedGlue + $expectedStone;
            $totalActual   = $lootPreview['glue'] + $lootPreview['stone']
                           + (int)($bonusRewards['hideout_glue'] ?? 0)
                           + (int)($bonusRewards['hideout_stone'] ?? 0);
            if ($totalExpected > 0 && $totalActual < $totalExpected * 0.8) {
                throw new GameError('errStartHideoutFightRewardsChanged');
            }
        }

        // Simulação
        [$attackers, $defenders] = Hideout::buildBattleQueues($h, $defenderHideout, $attackerCount, $isTutorial);
        $result = Hideout::simulateBattle($attackers, $defenders);
        $attackerWon = $result['attacker_won'];

        // Elo
        $eloDelta = Hideout::pointsDelta(
            (int)$h['hideout_points'],
            (int)($defenderHideout['hideout_points'] ?? 0),
            $attackerWon
        );

        // Loot
        if ($isTutorial) {
            $loot = ['rewards' => '', 'bonus_rewards' => '', 'glue' => 0, 'stone' => 0];
        } else {
            $winsAgainst = Hideout::countWinsAgainst((int)$h['id'], (int)$defenderHideout['id']);
            $loot = Hideout::opponentLoot($defenderHideout, $winsAgainst, $eloDelta['attacker_delta']);
        }

        // Salvage do defensor
        $salvage = Hideout::defenderSalvage(
            $h, $defenderHideout,
            $result['destroyed_attacker'],
            $result['destroyed_defender']
        );

        // Cria batalha
        Db::exec(
            'INSERT INTO `hideout_battles`
             (attacker_hideout_id, defender_hideout_id, defender_server_id,
              attacker_status, defender_status, attacker_count, defender_count,
              attacker_profiles, defender_profiles, rounds,
              attacker_rewards, attacker_bonus_rewards, defender_rewards,
              destroyed_attacker, destroyed_defender,
              hideout_winner_id, server_winner_id,
              attacker_character_name, defender_character_name, ts_creation)
             VALUES (?,?,?,1,1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                (int)$h['id'],
                $isTutorial ? -1 : (int)$defenderHideout['id'],
                '',
                $attackerCount,
                count($defenders),
                json_encode($result['attacker_profiles']),
                json_encode($result['defender_profiles']),
                json_encode($result['rounds']),
                $loot['rewards'],
                $loot['bonus_rewards'],
                $salvage,
                $result['destroyed_attacker'],
                $result['destroyed_defender'],
                $attackerWon ? (int)$h['id'] : (int)($defenderHideout['id'] ?? 0),
                '',
                (string)$char->name(),
                $isTutorial ? '' : (string)(Db::row('SELECT name FROM `character` WHERE id = ?', [$defenderCid])['name'] ?? ''),
                $now,
            ]
        );
        $battleId = (int)Db::pdo()->lastInsertId();
        if (!$isTutorial) {
            \HeroZero\HeroCon::record($cid, 1, max(0,(int)$eloDelta['attacker_delta']));
            if ($defenderCid>0) \HeroZero\HeroCon::record($defenderCid, 1, max(0,(int)$eloDelta['defender_delta']));
        }

        // Atualiza atacante
        $h['current_attacker_units'] = max(0, (int)$h['current_attacker_units'] - $attackerCount);
        $h['hideout_points'] = max(0, (int)$h['hideout_points'] + $eloDelta['attacker_delta']);
        $h['active_battle_id'] = (int)$battleId;
        if (!$attackerWon) {
            $h['ts_last_lost_attack'] = $now;
        }
        Hideout::saveHideout($h);

        // Aplica ao defensor
        if (!$isTutorial && $defenderCid > 0) {
            $defH = $defenderHideout;
            $defH['current_defender_units'] = max(0, (int)$defH['current_defender_units'] - $result['destroyed_defender']);
            $defH['hideout_points'] = max(0, (int)$defH['hideout_points'] + $eloDelta['defender_delta']);
            $defH['missed_attacks'] = min(
                (int)Hideout::c('hideout_max_missed_attacks', 99),
                (int)$defH['missed_attacks'] + 1
            );
            if ($attackerWon) {
                $defH['current_resource_glue']  = max(0, (int)$defH['current_resource_glue'] - $loot['glue']);
                $defH['current_resource_stone'] = max(0, (int)$defH['current_resource_stone'] - $loot['stone']);
            }
            Hideout::saveHideout($defH);

            // Push socket ao defensor (fire-and-forget)
            @file_get_contents(
                'http://127.0.0.1:' . (getenv('HZ_SOCKET_PORT') ?: '8090') . '/push',
                false,
                stream_context_create(['http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nX-Push-Token: " . (getenv('HZ_SOCKET_TOKEN') ?: 'local-dev-token'),
                    'content' => json_encode(['user_id' => Db::row('SELECT user_id FROM `character` WHERE id = ?', [$defenderCid])['user_id'] ?? 0, 'type' => 'syncGameAndGuild']),
                    'timeout' => 1,
                ]])
            );
        }

        if ($isTutorial) {
            $char->setTutorialFlag('hideout_tutorial_fight_done', true);
        }

        return Live::accountState($userId);
        });
    }

    if ($action === 'finishHideoutTutorial') {
        if ($char->getTutorialFlag('hideout_tutorial_finished')) {
            throw new GameError('errFinishHideoutTutorialAlreadyFinished');
        }
        $char->setTutorialFlag('hideout_tutorial_finished', true);
        // Flag que o cliente verifica para activar o scroll e todas as funcionalidades.
        $char->setTutorialFlag('hideout_tutorial_completed', true);
        // Recompensa de conclusão do tutorial (constantes)
        $rewardGlue  = (int)Hideout::c('hideout_tutorial_reward_glue', 10);
        $rewardStone = (int)Hideout::c('hideout_tutorial_reward_stone', 10);
        if ($rewardGlue > 0 || $rewardStone > 0) {
            $h = Hideout::rowFor($cid);
            if ($h !== null) {
                $h['current_resource_glue']  = (int)$h['current_resource_glue'] + $rewardGlue;
                $h['current_resource_stone'] = (int)$h['current_resource_stone'] + $rewardStone;
                Hideout::saveHideout($h);
            }
        }
        return Live::accountState($userId);
    }

    if ($action === 'collectHideoutFightRewards') {
        return \HeroZero\Missiles::transaction([$cid], function () use ($cid,$userId,$char,$params): array {
            $h = Hideout::requireRow($cid);
            $battleId = (int)($params['hideout_battle_id'] ?? 0);
            $battle = Db::row('SELECT * FROM `hideout_battles` WHERE id = ? AND attacker_hideout_id = ? FOR UPDATE', [$battleId, (int)$h['id']]);
            if ($battle === null || (int)$battle['attacker_status'] >= 3) {
                // Silencioso (o cliente engole este erro).
                return Live::accountState($userId);
            }

            // Credita rewards
            $rewards = [];
            if ((string)$battle['attacker_rewards'] !== '') {
                $rewards = json_decode($battle['attacker_rewards'], true) ?: [];
            }
            $bonus = [];
            if ((string)$battle['attacker_bonus_rewards'] !== '') {
                $bonus = json_decode($battle['attacker_bonus_rewards'], true) ?: [];
            }
            $glue  = (int)($rewards['hideout_glue'] ?? 0) + (int)($bonus['hideout_glue'] ?? 0);
            $stone = (int)($rewards['hideout_stone'] ?? 0) + (int)($bonus['hideout_stone'] ?? 0);
            $points = (int)($rewards['hideout_points'] ?? 0);

            if ($glue > 0) {
                $h['current_resource_glue'] = min((int)$h['max_resource_glue'], (int)$h['current_resource_glue'] + $glue);
            }
            if ($stone > 0) {
                $h['current_resource_stone'] = min((int)$h['max_resource_stone'], (int)$h['current_resource_stone'] + $stone);
            }
            // Elo já aplicado no startHideoutFight; não soma de novo.

            $h['active_battle_id'] = 0;
            Hideout::saveHideout($h);
            Db::exec('UPDATE `hideout_battles` SET attacker_status = 3 WHERE id = ?', [$battleId]);

            // Só ataques a outros esconderijos: tutorial e defesas ficam excluídos.
            if ((int)$battle['defender_hideout_id'] > 0 && (int)$battle['defender_hideout_id'] !== (int)$h['id']) {
                \HeroZero\DailyMilestones::record($cid, 1, \HeroZero\DailyMilestones::HIDEOUT);
            }

            $char->setTutorialFlag('hideout_first_attack', true);
            Character::bumpGoalStatFor($cid, 'hideout_hideout_points_reached', max(0, (int)$h['hideout_points']));

            return Live::accountState($userId);
        });
    }

    // ── Histórico de batalhas (ataques RECEBIDOS) ─────────────────────────────
    if ($action === 'getHideoutBattleHistoryFights') {
        $h = Hideout::requireRow($cid);
        $battles = Db::rows(
            'SELECT hb.*, c.id AS character_id, c.name AS character_name, c.gender AS character_gender, c.ts_last_action
               FROM `hideout_battles` hb
               JOIN `character` c ON c.id = (
                   SELECT c2.id FROM `character` c2
                   JOIN `hideout` h2 ON h2.character_id = c2.id
                   WHERE h2.id = hb.attacker_hideout_id LIMIT 1
               )
              WHERE hb.defender_hideout_id = ?
              ORDER BY hb.ts_creation DESC LIMIT 50',
            [(int)$h['id']]
        );
        // Limpa missed_attacks
        $h['missed_attacks'] = 0;
        Hideout::saveHideout($h);

        $list = [];
        foreach ($battles as $b) {
            $list[] = Hideout::shapeHistoryBattle($b, $cid);
        }
        $data = array_merge(Live::accountState($userId), ['hideout_history_battles' => $list]);
        return $data;
    }

    if ($action === 'getHideoutBattleHistoryFight') {
        $h = Hideout::requireRow($cid);
        $battleId = (int)($params['id'] ?? 0);
        $battle = Db::row(
            'SELECT hb.*, c.id AS character_id, c.name AS character_name, c.gender AS character_gender, c.ts_last_action
               FROM `hideout_battles` hb
               JOIN `character` c ON c.id = (
                   SELECT c2.id FROM `character` c2
                   JOIN `hideout` h2 ON h2.character_id = c2.id
                   WHERE h2.id = hb.attacker_hideout_id LIMIT 1
               )
              WHERE hb.id = ? AND hb.defender_hideout_id = ?',
            [$battleId, (int)$h['id']]
        );
        if ($battle === null) {
            return Live::accountState($userId);
        }
        // Versão completa inclui rounds e profiles para o replay
        $shaped = Hideout::shapeHistoryBattle($battle, $cid);
        $shaped['rounds']            = (string)$battle['rounds'];
        $shaped['attacker_profiles'] = (string)$battle['attacker_profiles'];
        $shaped['defender_profiles'] = (string)$battle['defender_profiles'];
        return array_merge(Live::accountState($userId), ['hideout_history_battle' => $shaped]);
    }

    if ($action === 'deleteHideoutHistoryBattles') {
        $h = Hideout::requireRow($cid);
        $raw = (string)($params['battle_ids'] ?? '');
        $ids = array_filter(array_map('intval', explode(';', trim($raw, ';'))));
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            Db::exec(
                "DELETE FROM `hideout_battles` WHERE id IN ($in) AND defender_hideout_id = ?",
                array_merge($ids, [(int)$h['id']])
            );
        }
        return Live::accountState($userId);
    }

    if ($action === 'deleteAllHideoutHistoryBattles') {
        $h = Hideout::requireRow($cid);
        $maxId = (int)($params['max_battle_id'] ?? 0);
        if ($maxId > 0) {
            Db::exec(
                'DELETE FROM `hideout_battles` WHERE defender_hideout_id = ? AND id <= ?',
                [(int)$h['id'], $maxId]
            );
        }
        return Live::accountState($userId);
    }

    // ── Refresh Exchange Rate ──────────────────────────────────────────────────
    if ($action === 'refreshHideoutExchangeRate') {
        $h      = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'exchange_room') {
            throw new GameError('errInvalidHideoutRoom');
        }
        $free         = (bool)($params['free'] ?? false);
        $lastRefresh  = (int)($room['additional_value_1'] ?? 0);
        $today        = date('Y-m-d', GameTime::now());
        $refreshedToday = $lastRefresh > 0 && date('Y-m-d', $lastRefresh) === $today;
        if (!($free && !$refreshedToday)) {
            Hideout::debitPremium($char, (int)Hideout::c('hideout_exchange_room_refresh_cost', 1));
        }
        $av2 = [
            'is_zeronit'       => mt_rand(0, 1),
            'duration'         => mt_rand(5, 15),
            'resource_duration'=> mt_rand(120, 480),
            'exchange_rate'    => round(mt_rand(70, 150) / 100.0, 2),
        ];
        $room['additional_value_2'] = json_encode($av2);
        $room['additional_value_1'] = (string)GameTime::now();
        Hideout::saveRoom($room);
        return Live::accountState($userId);
    }

    // ── Refresh Gym Rate ───────────────────────────────────────────────────────
    if ($action === 'refreshHideoutGymRate') {
        $h      = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'gym') {
            throw new GameError('errInvalidHideoutRoom');
        }
        $free        = (bool)($params['free'] ?? false);
        $lastRefresh = (int)($room['additional_value_1'] ?? 0);
        $refreshedToday = $lastRefresh > 0 && date('Y-m-d', $lastRefresh) === date('Y-m-d', GameTime::now());
        if (!($free && !$refreshedToday)) {
            Hideout::debitPremium($char, (int)Hideout::c('hideout_gym_room_refresh_cost', 1));
        }
        $statType = mt_rand(1, 4);
        $duration = mt_rand(1, 4) * 3600; // 1-4 horas
        $av2 = ['stat_type' => $statType, 'duration' => $duration];
        $room['additional_value_2'] = json_encode($av2);
        $room['additional_value_1'] = (string)GameTime::now();
        Hideout::saveRoom($room);
        return Live::accountState($userId);
    }

    // ── Get / Refresh Blacksmith Item ──────────────────────────────────────────
    if ($action === 'getHideoutBlacksmithItem') {
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'blacksmith') {
            throw new GameError('errInvalidHideoutRoom');
        }
        $itemId = (int)($room['additional_value_1'] ?? 0);
        if ($itemId <= 0) {
            throw new GameError('errInvalidHideoutRoom');
        }
        $item = Db::row('SELECT * FROM `items` WHERE id = ? AND character_id = ?', [$itemId, $cid]);
        if ($item === null) {
            return Live::accountState($userId);
        }
        return array_merge(Live::accountState($userId), ['item' => $item]);
    }

    if ($action === 'refreshHideoutBlacksmithItem') {
        $h      = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'blacksmith') {
            throw new GameError('errInvalidHideoutRoom');
        }
        $free = (bool)($params['free'] ?? false);
        $av2  = json_decode((string)($room['additional_value_2'] ?? ''), true) ?: [];
        $lastItemRefresh = (int)($av2['ts_item_refresh'] ?? 0);
        $refreshedToday  = $lastItemRefresh > 0 && date('Y-m-d', $lastItemRefresh) === date('Y-m-d', GameTime::now());
        if (!($free && !$refreshedToday)) {
            Hideout::debitPremium($char, (int)Hideout::c('hideout_blacksmith_item_refresh_cost', 1));
        }
        // Sorteia um item do catálogo filtrado pelo nível da sala
        $roomLevel    = (int)$room['level'];
        $catalog      = Hideout::md()['hideout_room_blacksmith_items'] ?? [];
        $eligible     = [];
        $totalChance  = 0;
        foreach ($catalog as $entry) {
            if ((int)($entry['min_blacksmith_level'] ?? 0) <= $roomLevel) {
                $w = max(1, (int)($entry['chance'] ?? 1));
                $eligible[]   = ['entry' => $entry, 'w' => $w];
                $totalChance += $w;
            }
        }
        $pick = null;
        if ($eligible !== [] && $totalChance > 0) {
            $r = mt_rand(1, $totalChance);
            $cum = 0;
            foreach ($eligible as $e) {
                $cum += $e['w'];
                if ($r <= $cum) { $pick = $e['entry']; break; }
            }
        }
        $slotType = 'weapon';
        $plusLvl  = 0;
        $quality  = 2;
        if (is_array($pick)) {
            $rawId   = (string)($pick['item_identifier'] ?? 'weapon');
            $slotType = explode(';', $rawId)[0]; // pode ser lista separada por ";"
            $plusLvl = (int)($pick['plus_item_level'] ?? 0);
            // Blacksmith dá itens raros a épicos: qualidade por nível da sala
            $quality = $roomLevel >= 5 ? 3 : 2;
        }
        $av2['item_identifier']  = $slotType;
        $av2['plus_item_level']  = $plusLvl;
        $av2['quality']          = $quality;
        $av2['ts_item_refresh']  = GameTime::now();
        $room['additional_value_2'] = json_encode($av2);
        $room['additional_value_1'] = '0'; // reset: item criado no collect
        Hideout::saveRoom($room);
        return Live::accountState($userId);
    }

    // ── Refresh Blacksmith Cost ────────────────────────────────────────────────
    if ($action === 'refreshHideoutBlacksmithCost') {
        $h      = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'blacksmith') {
            throw new GameError('errInvalidHideoutRoom');
        }
        $free = (bool)($params['free'] ?? false);
        $av2  = json_decode((string)($room['additional_value_2'] ?? ''), true) ?: [];
        $lastCostRefresh = (int)($av2['ts_cost_refresh'] ?? 0);
        $refreshedToday  = $lastCostRefresh > 0 && date('Y-m-d', $lastCostRefresh) === date('Y-m-d', GameTime::now());
        if (!($free && !$refreshedToday)) {
            Hideout::debitPremium($char, (int)Hideout::c('hideout_blacksmith_cost_refresh_cost', 1));
        }
        $costType = mt_rand(1, 3); // 1=gold, 2=glue, 3=stone
        $charLevel = max(1, $char->level());
        $costAmt = match($costType) {
            1 => max(10, (int)round($charLevel * 8 * 1.5)),    // gold
            2 => max(20, (int)round($charLevel * 5)),           // glue
            3 => max(20, (int)round($charLevel * 5)),           // stone
        };
        $av2['cost_type']    = $costType;
        $av2['cost_amount']  = $costAmt;
        $av2['ts_cost_refresh'] = GameTime::now();
        $room['additional_value_2'] = json_encode($av2);
        Hideout::saveRoom($room);
        return Live::accountState($userId);
    }

    // ── Refresh Item Improvement Slot ──────────────────────────────────────────
    if ($action === 'refreshHideoutItemImprovementSlot') {
        $h      = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'gem_production') {
            throw new GameError('errInvalidHideoutRoom');
        }
        if ((int)$room['status'] !== Hideout::STATUS_IDLE) {
            throw new GameError(Hideout::pendingActivityError('StartProduction', (int)$room['status']));
        }
        $free = filter_var($params['free'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $av2  = Hideout::improvementData($room);
        $lastSlotRefresh = (int)($av2['ts_slot_refresh'] ?? 0);
        $refreshedToday  = $lastSlotRefresh > 0 && date('Y-m-d', $lastSlotRefresh) === date('Y-m-d', GameTime::now());
        if ($free && $refreshedToday) throw new GameError('errRefreshHideoutItemImprovementSlotNotFree');
        if (!$free) {
            $cost = (int)Hideout::c('hideout_item_improvement_refresh_cost', 1);
            Hideout::debitPremium($char, $cost);
        }
        // Slots: 1=mask,2=cape,3=suit,4=belt,5=boots,6=weapon,7=gadget,13=add_slot,14=add_sidekick_slot
        $possibleSlots = [1, 2, 3, 4, 5, 6, 7, 13, 14];
        $slot = $possibleSlots[array_rand($possibleSlots)];
        $av2['improvement_slot'] = $slot;
        $av2['ts_slot_refresh'] = GameTime::now();
        // Reset rarity se o slot mudou
        unset($av2['improvement_rarity'], $av2['ts_rarity_refresh']);
        $room['additional_value_2'] = json_encode($av2);
        Hideout::saveRoom($room);
        return Live::accountState($userId);
    }

    // ── Refresh Item Improvement Rarity ───────────────────────────────────────
    if ($action === 'refreshHideoutItemImprovementRarity') {
        $h      = Hideout::requireRow($cid);
        $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
        $room   = Hideout::roomById($cid, $roomId);
        if ($room === null || (string)$room['identifier'] !== 'gem_production') {
            throw new GameError('errInvalidHideoutRoom');
        }
        if ((int)$room['status'] !== Hideout::STATUS_IDLE) {
            throw new GameError(Hideout::pendingActivityError('StartProduction', (int)$room['status']));
        }
        $free = filter_var($params['free'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $av2  = Hideout::improvementData($room);
        if (!in_array((int)($av2['improvement_slot'] ?? 0), [1, 2, 3, 4, 5, 6, 7], true)) {
            throw new GameError('errStartHideoutRoomProductionInvalidProductionCount');
        }
        $lastRarityRefresh = (int)($av2['ts_rarity_refresh'] ?? 0);
        $refreshedToday    = $lastRarityRefresh > 0 && date('Y-m-d', $lastRarityRefresh) === date('Y-m-d', GameTime::now());
        if ($free && $refreshedToday) throw new GameError('errRefreshHideoutItemImprovementRarityNotFree');
        if (!$free) {
            $cost = (int)Hideout::c('hideout_item_improvement_refresh_cost', 1);
            Hideout::debitPremium($char, $cost);
        }
        $guildData = ItemImprovement::workshopGuildData($cid);
        $maxStat = ItemImprovement::workshopMaxStats(
            max(1, $char->level()), (int)$room['level'], $guildData['level'], $guildData['members']
        );
        $rarities = ItemImprovement::workshopRarities($maxStat);
        $av2['improvement_rarity'] = $rarities;
        $av2['ts_rarity_refresh']  = GameTime::now();
        $av2 = Hideout::improvementData(['additional_value_2' => json_encode($av2)]);
        $room['additional_value_2'] = json_encode($av2);
        Hideout::saveRoom($room);
        return Live::accountState($userId);
    }

    // ── Guild Bonus para Item Improvement ─────────────────────────────────────
    if ($action === 'getHideoutItemImprovementGuildBonus') {
        $guildData = ItemImprovement::workshopGuildData($cid);
        return array_merge(Live::accountState($userId), ['guild_gem_production_level' => $guildData['level']]);
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────
    if ($action === 'retrieveHideoutLeaderboard') {
        $pointsSort = ($params['points_sort'] ?? 'true') !== 'false';
        $searchName = isset($params['character_name']) ? (string)$params['character_name'] : null;
        $searchRank = isset($params['rank']) ? (int)$params['rank'] : null;
        $now = GameTime::now();
        $orderBy = $pointsSort ? 'h.hideout_points DESC, h.id ASC' : 'h.current_level DESC, h.id ASC';

        $total = (int)(Db::row('SELECT COUNT(*) AS n FROM `hideout`')['n'] ?? 0);

        // Determina o rank central
        $centeredRank = 1;
        if ($searchName !== null) {
            $row = Db::row(
                "SELECT COUNT(*) AS n FROM `hideout` h
                  JOIN `character` c ON c.id = h.character_id
                 WHERE " . ($pointsSort ? 'h.hideout_points' : 'h.current_level') . " > (
                     SELECT " . ($pointsSort ? 'h2.hideout_points' : 'h2.current_level') . "
                       FROM `hideout` h2 JOIN `character` c2 ON c2.id = h2.character_id
                      WHERE c2.name LIKE ? LIMIT 1
                 )",
                ['%' . $searchName . '%']
            );
            $centeredRank = (int)($row['n'] ?? 0) + 1;
        } elseif ($searchRank !== null) {
            $centeredRank = max(1, min($total, $searchRank));
        }

        $offset = max(0, $centeredRank - 25);
        $rows = Db::rows(
            "SELECT h.hideout_points, h.current_level,
                    c.id, c.name, c.gender, c.guild_id, c.ts_last_action,
                    COALESCE(g.name, '') AS guild_name,
                    COALESCE(g.emblem_background_shape, 0)        AS emblem_background_shape,
                    COALESCE(g.emblem_background_color, 0)        AS emblem_background_color,
                    COALESCE(g.emblem_background_border_color, 0) AS emblem_background_border_color,
                    COALESCE(g.emblem_icon_shape, 0)              AS emblem_icon_shape,
                    COALESCE(g.emblem_icon_color, 0)              AS emblem_icon_color,
                    COALESCE(g.emblem_icon_size, 0)               AS emblem_icon_size,
                    ROW_NUMBER() OVER (ORDER BY $orderBy) AS rank_pos
               FROM `hideout` h
               JOIN `character` c ON c.id = h.character_id
               LEFT JOIN `guild` g ON g.id = c.guild_id
              ORDER BY $orderBy LIMIT 50 OFFSET $offset",
            []
        );

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id'                             => (int)$r['id'],
                'character_id'                   => (int)$r['id'],
                'name'                           => (string)$r['name'],
                'gender'                         => (string)$r['gender'],
                'guild_id'                       => (int)$r['guild_id'],
                'guild_name'                     => (string)$r['guild_name'],
                'emblem_background_shape'        => (int)$r['emblem_background_shape'],
                'emblem_background_color'        => (int)$r['emblem_background_color'],
                'emblem_background_border_color' => (int)$r['emblem_background_border_color'],
                'emblem_icon_shape'              => (int)$r['emblem_icon_shape'],
                'emblem_icon_color'              => (int)$r['emblem_icon_color'],
                'emblem_icon_size'               => (int)$r['emblem_icon_size'],
                'online_status'                  => ($now - (int)$r['ts_last_action'] < 60) ? 1 : 2,
                'hideout_points'                 => (int)$r['hideout_points'],
                'hideout_level'                  => (int)$r['current_level'],
                'rank'                           => (int)$r['rank_pos'],
            ];
        }

        return array_merge(Live::accountState($userId), [
            'leaderboard_hideouts' => $list,
            'max_hideouts'         => $total,
            'centered_rank'        => $centeredRank,
        ]);
    }

    // ── Fallback (ação desconhecida — devolve estado atual) ───────────────────
    return Live::accountState($userId);
};
