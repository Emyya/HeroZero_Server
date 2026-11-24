<?php
declare(strict_types=1);

/**
 * action: instantFinishHideoutRoomActivity — pular a espera da atividade da
 * sala com premium. Porte do handler flash.
 * Params: hideout_room_id | hideoutRoomId.
 * Custo: curva oficial {short,medium,long,very_long} com teto 499
 * (Hideout::instantFinishCost — igual à fórmula do cliente).
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
    if ($room === null) {
        throw new GameError('errInstantFinishActivityInvalidStatus');
    }
    // Cliente com estado antigo: sala ja concluida — sincroniza sem erro nem custo.
    if ((int)$room['status'] === Hideout::STATUS_IDLE) {
        return Live::accountState($userId);
    }

    $now = GameTime::now();
    $remaining = max(0, (int)$room['ts_activity_end'] - $now);
    if ($remaining <= 0) {
        // Temporizador ja expirou — completa gratis sem cobrar premium.
        $rooms = Hideout::roomsFor($char->id());
        foreach ($rooms as &$r) {
            if ((int)$r['id'] === $roomId) {
                $r = $room;
            }
        }
        unset($r);
        Hideout::completeRoomActivity($char, $h, $room, $rooms);
        if ($room['identifier'] === 'attacker_production') {
            $char->setTutorialFlag('hideout_collect_attacker_units', true);
        }
        Db::exec('UPDATE `hideout_rooms` SET status = ?, level = ?, current_resource_amount = ?, max_resource_amount = ?, ts_last_resource_change = ?, ts_activity_end = ?, current_generator_level = ? WHERE id = ?', [
            $room['status'], $room['level'], $room['current_resource_amount'], $room['max_resource_amount'],
            $room['ts_last_resource_change'], $room['ts_activity_end'], $room['current_generator_level'], $roomId,
        ]);
        foreach ($rooms as $r2) {
            if ((int)$r2['id'] !== $roomId) {
                Hideout::saveRoom($r2);
            }
        }
        Hideout::saveHideout($h);
        return Live::accountState($userId);
    }
    Hideout::debitPremium($char, Hideout::instantFinishCost($remaining));

    $rooms = Hideout::roomsFor($char->id());
    foreach ($rooms as &$r) {
        if ((int)$r['id'] === $roomId) {
            $r = $room;
        }
    }
    unset($r);
    Hideout::completeRoomActivity($char, $h, $room, $rooms);

    if ($room['identifier'] === 'attacker_production') {
        $char->setTutorialFlag('hideout_collect_attacker_units', true);
    }

    Db::exec('UPDATE `hideout_rooms` SET status = ?, level = ?, current_resource_amount = ?, max_resource_amount = ?, ts_last_resource_change = ?, ts_activity_end = ?, current_generator_level = ? WHERE id = ?', [
        $room['status'], $room['level'], $room['current_resource_amount'], $room['max_resource_amount'],
        $room['ts_last_resource_change'], $room['ts_activity_end'], $room['current_generator_level'], $roomId,
    ]);
    foreach ($rooms as $r2) {
        if ((int)$r2['id'] !== $roomId) {
            Hideout::saveRoom($r2); // gerador replicado / caps mudados
        }
    }
    Hideout::saveHideout($h);

    return Live::accountState($userId);
};
