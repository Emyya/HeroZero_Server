<?php
declare(strict_types=1);

/**
 * action: openSeasonPanel  (abrir o painel da season, devolve track completo de recompensas)
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Season;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char   = Character::loadByUser($userId);
    $cid    = $char->id();

    $data = Live::accountState($userId);
    // O cliente precisa de season_rewards no openSeasonPanel para construir o track.
    $data['season_rewards'] = Season::rewardsForCharacter($cid);
    return $data;
};
