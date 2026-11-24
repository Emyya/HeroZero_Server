<?php
declare(strict_types=1);

/**
 * action: placeHideoutRoom — posicionar uma sala que esta guardada (ou recem
 * construida sem lugar). Porte do placeHideoutRoom.req.php flash.
 * Params: hideoutRoomId | hideout_room_id, level (piso), slot.
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
    $targetLevel = (int)($params['level'] ?? 0);
    $targetSlot = (int)($params['slot'] ?? 0);

    if ($targetLevel < 0 || $targetLevel >= Hideout::MAX_LEVELS || $targetSlot < 0 || $targetSlot >= Hideout::MAX_SLOTS) {
        throw new GameError('errAssignRoomNoSpace');
    }
    $room = Hideout::roomById($char->id(), $roomId);
    if ($room === null) {
        throw new GameError('errInvalidHideoutRoom');
    }
    if (Hideout::isRoomPlaced($h, $roomId)) {
        // Sala ja posicionada (cliente enviou duplo) — sincroniza.
        return Live::accountState($userId);
    }
    if ((int)$room['status'] !== Hideout::STATUS_IDLE) {
        // Sala a meio de outra atividade: familia errPlacePendingActivity*.
        throw new GameError(Hideout::pendingActivityError('Place', (int)$room['status']));
    }
    $size = Hideout::getRoomSize($room['identifier']);
    if (!Hideout::isSlotAreaFree($h, $targetLevel, $targetSlot, $size)) {
        throw new GameError('errAssignRoomNoSpace');
    }
    if ((int)$h['idle_worker_count'] <= 0) {
        throw new GameError('errPlaceRoomNoIdleWorker');
    }

    $duration = Hideout::placeDuration($room, $h);
    $now = GameTime::now();
    Hideout::assignRoomToSlots($h, $targetLevel, $targetSlot, $size, $roomId);
    Hideout::takeWorker($h);
    $room['status'] = Hideout::STATUS_PLACING;
    $room['ts_activity_end'] = $now + $duration;
    $room['ts_last_resource_change'] = $now;

    Db::exec('UPDATE `hideout_rooms` SET status = ?, ts_activity_end = ?, ts_last_resource_change = ? WHERE id = ?',
        [$room['status'], $room['ts_activity_end'], $room['ts_last_resource_change'], $roomId]);
    Hideout::saveHideout($h);

    return Live::accountState($userId);
};
