<?php
declare(strict_types=1);
use HeroZero\{LocalPayments,Live,Character};
return function(array $params): array {
    $uid=Live::currentUserId($params);
    return Live::overlayAccount(LocalPayments::catalog(Character::loadByUser($uid)->id()),$uid);
};
