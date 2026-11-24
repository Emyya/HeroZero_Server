<?php
declare(strict_types=1);

use HeroZero\GameTime;

/**
 * action: buyShopItem
 * Compra um item da loja, debita o preço e respeita o destino (mochila/equipamento).
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Sidekick;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $wasSidekick = $char->buyShopItem((int)($params['item_id'] ?? 0), (int)($params['target_slot'] ?? 0));

    $boot = Live::template('autoLoginUser');
    $response = [
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'inventory' => $char->inventoryData($boot['inventory'] ?? []),
        'items' => $char->itemsData($boot['items'][0] ?? []),
        'current_goal_values' => [
            'items_bought' => 1,
        ],
        'server_time' => GameTime::now(),
        'time_correction' => 0,
    ];
    if ($wasSidekick) {
        $cid = $char->id();
        $response['sidekicks']     = Sidekick::ownedByCharacter($cid);
        $response['all_sidekicks'] = Sidekick::catalogEntries($cid);
        $response['current_goal_values'] = Sidekick::goalValues($cid);
    }
    return $response;
};
