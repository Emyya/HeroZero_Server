<?php
declare(strict_types=1);

/**
 * action: checkHideoutRoomActivityFinished — o cliente chama quando o timer da
 * atividade (build/upgrade/place/store) chega ao fim. Porte do handler flash.
 * Params: hideout_room_id | hideoutRoomId.
 * Erros *NotJetPossible [sic] → retry automático do cliente após 1 s.
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

    $roomId = (int)($params['hideout_room_id'] ?? $params['hideoutRoomId'] ?? 0);
    $room = Hideout::roomById($char->id(), $roomId);
    if ($room === null || (int)$room['status'] === Hideout::STATUS_IDLE) {
        throw new GameError('errCheckHideoutRoomActivityFinishedInvalidStatus');
    }
    if ((int)$room['ts_activity_end'] > GameTime::now()) {
        throw new GameError('errCheckHideoutRoomActivityNotJetPossible');
    }

    $rooms = Hideout::roomsFor($char->id());
    foreach ($rooms as &$r) {
        if ((int)$r['id'] === $roomId) {
            $r = $room;
        }
    }
    unset($r);
    Hideout::completeRoomActivity($char, $h, $room, $rooms);

    Db::exec('UPDATE `hideout_rooms` SET status = ?, level = ?, current_resource_amount = ?, max_resource_amount = ?, ts_last_resource_change = ?, ts_activity_end = ? WHERE id = ?', [
        $room['status'], $room['level'], $room['current_resource_amount'], $room['max_resource_amount'],
        $room['ts_last_resource_change'], $room['ts_activity_end'], $roomId,
    ]);
    foreach ($rooms as $r2) {
        if ((int)$r2['id'] !== $roomId) {
            Hideout::saveRoom($r2);
        }
    }
    Hideout::saveHideout($h);

    return Live::accountState($userId);
};
