<?php
declare(strict_types=1);

/**
 * action: storeHideoutRoom — guardar uma sala (tira-a da grelha).
 * Porte do storeHideoutRoom.req.php flash.
 * Params: hideoutRoomId | hideout_room_id, ignoreResource.
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
    if ($room === null) {
        throw new GameError('errStoreHideoutRoomNotJetPossible');
    }
    $status = (int)$room['status'];
    if ($status === Hideout::STATUS_PRODUCING) {
        if (Hideout::isManualProductionRoom($room['identifier'])) {
            throw new GameError('errStorePendingActivityProducing');
        }
        // Auto-producao com recursos dentro: exige confirmacao (perde-os).
        if (!$ignoreResource && (int)$room['current_resource_amount'] > 0) {
            throw new GameError('errStoreResourceLeftInRoom');
        }
    } elseif ($status === Hideout::STATUS_STORING) {
        // Duplo envio / estado antigo: sala ja esta a ser armazenada — sincroniza.
        return Live::accountState($userId);
    } elseif ($status !== Hideout::STATUS_IDLE) {
        throw new GameError(Hideout::pendingActivityError('Store', $status));
    }
    if ((int)$h['idle_worker_count'] <= 0) {
        throw new GameError('errStoreRoomNoIdleWorker');
    }

    $now = GameTime::now();
    Hideout::takeWorker($h);
    $room['status'] = Hideout::STATUS_STORING;
    $room['ts_activity_end'] = $now + Hideout::storeDuration($room, $h);
    if ($ignoreResource) {
        $room['current_resource_amount'] = 0;
    }

    Db::exec('UPDATE `hideout_rooms` SET status = ?, ts_activity_end = ?, current_resource_amount = ? WHERE id = ?',
        [$room['status'], $room['ts_activity_end'], $room['current_resource_amount'], $roomId]);
    Hideout::saveHideout($h);

    return Live::accountState($userId);
};
