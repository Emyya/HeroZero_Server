<?php
declare(strict_types=1);

/**
 * action: upgradeHideoutRoom — subir o nivel de uma sala colocada.
 * Porte do handler flash. Params: hideoutRoomId | hideout_room_id,
 * ignoreResource (auto-producao com recursos dentro), synergyUpgrade (Fase 6).
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Hideout;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $char = Character::loadByUser($userId);
    $h = Hideout::requireRow($char->id());

    $roomId = (int)($params['hideoutRoomId'] ?? $params['hideout_room_id'] ?? 0);
    $ignoreResource = in_array($params['ignoreResource'] ?? null, [true, 'true', '1', 1], true);

    $room = Hideout::roomById($char->id(), $roomId);
    if ($room === null || !Hideout::isRoomPlaced($h, $roomId)) {
        throw new GameError('errUpgradingHideoutRoomNotJetPossible');
    }
    $status = (int)$room['status'];
    if ($status === Hideout::STATUS_PRODUCING) {
        if (Hideout::isManualProductionRoom($room['identifier'])) {
            throw new GameError('errUpgradePendingActivityProducing');
        }
        if (!$ignoreResource && (int)$room['current_resource_amount'] > 0) {
            throw new GameError('errUpgradeResourceLeftInRoom');
        }
    } elseif ($status !== Hideout::STATUS_IDLE) {
        throw new GameError(Hideout::pendingActivityError('Upgrade', $status));
    }

    $targetLevel = (int)$room['level'] + 1;
    $targetCfg = Hideout::roomLevelConfig($room['identifier'], $targetLevel);
    if ($targetCfg === null) {
        throw new GameError('errUpgradingHideoutRoomNotJetPossible');
    }
    if ($char->level() < Hideout::getUpgradeCharacterMinLevel($targetLevel)) {
        throw new GameError('errUpgradeRoomCharacterLevelToLow');
    }
    if ((int)$h['idle_worker_count'] <= 0) {
        throw new GameError('errUpgradeRoomNoIdleWorker');
    }

    // Ouro com desconto do broker; cola/pedra sem desconto.
    $gold = Hideout::reducedGold($h, (float)($targetCfg['price_gold'] ?? 0));
    $glue = (float)($targetCfg['price_glue'] ?? 0);
    $stone = (float)($targetCfg['price_stone'] ?? 0);
    Hideout::checkCosts($char, $h, $gold, $glue, $stone);

    Hideout::applyCosts($char, $h, $gold, $glue, $stone);
    Hideout::takeWorker($h);
    $now = GameTime::now();
    $room['status'] = Hideout::STATUS_UPGRADING;
    $room['ts_activity_end'] = $now + Hideout::upgradeDuration($room['identifier'], (int)$room['level'], $h);
    if ($ignoreResource) {
        $room['current_resource_amount'] = 0;
    }

    Db::exec('UPDATE `hideout_rooms` SET status = ?, ts_activity_end = ?, current_resource_amount = ? WHERE id = ?',
        [$room['status'], $room['ts_activity_end'], $room['current_resource_amount'], $roomId]);
    Hideout::saveHideout($h);

    // Tutorial: primeira melhoria do main building.
    if ($room['identifier'] === 'main_building' && $targetLevel >= 2) {
        $char->setTutorialFlag('hideout_first_upgrade', true);
    }

    return Live::accountState($userId);
};
