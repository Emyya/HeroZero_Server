<?php
declare(strict_types=1);
use HeroZero\{Character,Live,TowerEvents};
return static function(array $params): array {
    $uid=(int)($params['user_id']??0);$char=Character::loadByUser($uid);
    $result=TowerEvents::action($char->id(),$params);
    return array_replace(Live::accountState($uid),$result);
};
