<?php
declare(strict_types=1);

/**
 * action: claimSeasonReward  (reivindicar uma recompensa da season pass)
 * Entrada: season_reward_id (int), discard_item (bool, opcional)
 * Devolve season_reward (singular, minimal) + season_rewards (array completo).
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Season;

return function (array $params): array {
    $userId   = (int)($params['user_id'] ?? 0);
    $rewardId = (int)($params['season_reward_id'] ?? 0);
    $discard  = in_array($params['discard_item'] ?? false, [true, 1, '1', 'true'], true);

    $char = Character::loadByUser($userId);
    $cid  = $char->id();

    // claimReward entrega a recompensa e devolve shape mínima com status=2.
    $rewardShape = Season::claimReward($cid, $rewardId, $discard);

    $data = Live::accountState($userId);
    $data['season_reward']  = $rewardShape;
    $data['season_rewards'] = Season::rewardsForCharacter($cid);
    return $data;
};
