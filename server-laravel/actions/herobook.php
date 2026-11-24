<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Herobook;
use HeroZero\Live;

return function (array $params): array {
    $uid = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($uid);
    Herobook::act($char->id(), (int)($params['id'] ?? 0), (string)$params['action'],
        in_array($params['discard_item'] ?? false, [true, 1, '1', 'true'], true));
    return Live::accountState($uid);
};
