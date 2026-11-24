<?php
declare(strict_types=1);
use HeroZero\Character;
use HeroZero\Worldboss;
use HeroZero\Live;
return function (array $params): array {
    $uid = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($uid);
    $extra = Worldboss::action($char->id(), (string)($params['action'] ?? ''),
        (int)($params['worldboss_event_id'] ?? 0), (int)($params['iterations'] ?? 1),
        filter_var($params['discard_item'] ?? false, FILTER_VALIDATE_BOOLEAN));
    return Live::accountState($uid, $extra);
};
