<?php
declare(strict_types=1);

/**
 * action: buildHideoutRoom — comprar e posicionar uma sala NOVA
 * (nasce STATUS_BUILDING ja no sitio). Porte do handler flash com as
 * adaptacoes 257:
 *   - grelha 12 pisos x 5 slots;
 *   - `unlock_with_mainbuilding` e VETOR indexado pela copia atual (0-based);
 *   - desconto do broker no preco em ouro.
 * Params: identifier, level (piso), slot (inicial da largura da sala).
 */

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Hideout;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $char = Character::loadByUser($userId);
    $h = Hideout::requireRow($char->id());

    $identifier = (string)($params['identifier'] ?? '');
    $level = (int)($params['level'] ?? 0);
    $startSlot = (int)($params['slot'] ?? 0);

    if ($level < 0 || $level >= Hideout::MAX_LEVELS || $startSlot < 0 || $startSlot >= Hideout::MAX_SLOTS) {
        throw new GameError('errAssignRoomNoSpace');
    }
    if ((int)$h['idle_worker_count'] <= 0) {
        throw new GameError('errBuildRoomNoIdleWorker');
    }
    $config = Hideout::roomConfig($identifier);
    if ($config === null) {
        throw new GameError('errInvalidHideoutRoom');
    }
    $size = Hideout::getRoomSize($identifier);
    if (!Hideout::isSlotAreaFree($h, $level, $startSlot, $size)) {
        throw new GameError('errAssignRoomNoSpace');
    }

    // Limite de copias conta COLOCADAS + EM STORE (cliente: getRoomCountWithIdentifier).
    $rooms = Hideout::roomsFor($char->id());
    $copies = 0;
    foreach ($rooms as $existing) {
        if ($existing['identifier'] === $identifier) {
            $copies++;
        }
    }
    if ($copies >= Hideout::getRoomLimit($identifier)) {
        throw new GameError('errBuildRoomLimitReached');
    }

    // Requisito de main building: vetor indexado pela copia que esta a nascer.
    $vector = is_array($config['unlock_with_mainbuilding'] ?? null) ? $config['unlock_with_mainbuilding'] : [];
    $requiredMain = (int)($vector[$copies] ?? 0);
    if ($requiredMain > 0) {
        $mainLevel = 0;
        foreach ($rooms as $existing) {
            if ($existing['identifier'] === 'main_building' && !in_array((int)$existing['status'], [Hideout::STATUS_STORING], true)) {
                $mainLevel = max($mainLevel, (int)$existing['level']);
            }
        }
        if ($mainLevel < $requiredMain) {
            throw new GameError('errBuildRoomMainbuildingToLow');
        }
    }

    $level1 = Hideout::roomLevelConfig($identifier, 1);
    if ($level1 === null) {
        throw new GameError('errInvalidHideoutRoom');
    }
    // Ouro passa pelo desconto do broker (cliente: getReducedGameCurrencyValue).
    $gold = Hideout::reducedGold($h, (float)($level1['price_gold'] ?? 0));
    $glue = (float)($level1['price_glue'] ?? 0);
    $stone = (float)($level1['price_stone'] ?? 0);
    Hideout::checkCosts($char, $h, $gold, $glue, $stone);

    Hideout::applyCosts($char, $h, $gold, $glue, $stone);
    $room = Hideout::createRoom(
        $char->id(),
        (int)$h['id'],
        $identifier,
        (int)round((float)($level1['resource_production_max'] ?? 0)),
        GameTime::now() + Hideout::buildDuration($identifier, $h)
    );
    Hideout::takeWorker($h);
    Hideout::assignRoomToSlots($h, $level, $startSlot, $size, (int)$room['id']);
    Hideout::saveHideout($h);

    return Live::accountState($userId);
};
