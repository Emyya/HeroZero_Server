<?php
declare(strict_types=1);

/**
 * action: buyTrainingEnergy  (+15 training_energy, custo 1 premium (donut))
 * constants: training_energy_refill_amount = 15.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);

    $char = Character::loadByUser($userId);
    $char->buyTrainingEnergy(1);
    return Live::accountState($userId);
};
