<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\GameTime;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->refreshShopItems((int)($params['shop_index'] ?? 1), filter_var($params['use_premium'] ?? false, FILTER_VALIDATE_BOOLEAN));
    $boot = Live::template('autoLoginUser');

    return [
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'inventory' => $char->inventoryData($boot['inventory'] ?? []),
        'items' => $char->itemsData($boot['items'][0] ?? []),
        'server_time' => GameTime::now(),
        'time_correction' => 0,
    ];
};
