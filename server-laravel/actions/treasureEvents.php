<?php
declare(strict_types=1);
use HeroZero\{Character,EventTreasures,GameError,Live};
return function(array $params):array {
    $uid=(int)($params['user_id']??0);$cid=Character::loadByUser($uid)->id();
    $discard=filter_var($params['discard_item']??false,FILTER_VALIDATE_BOOLEAN);
    switch($params['action']??'') {
        case 'assignTreasureEvent':EventTreasures::assign($cid,(string)($params['treasure_event_id']??''));break;
        case 'createNextTreasureEventLevel':EventTreasures::next($cid);break;
        case 'openTreasureCell':EventTreasures::open($cid,(int)($params['level']??0),(int)($params['x']??-1),(int)($params['y']??-1),filter_var($params['premium']??false,FILTER_VALIDATE_BOOLEAN));break;
        case 'collectTreasureCellReward':EventTreasures::collect($cid,(int)($params['level']??0),(int)($params['x']??-1),(int)($params['y']??-1),$discard);break;
        case 'claimTreasureEventReward':EventTreasures::claim($cid,(int)($params['reward_index']??-1),$discard);break;
        case 'claimFreeTreasureRevealItems':EventTreasures::free($cid);break;
        case 'resetTreasureEventProgress':EventTreasures::reset($cid);break;
        case 'skipTreasureEventLevelUnlock':EventTreasures::skip($cid,(int)($params['level']??0));break;
        default:throw new GameError('errRequestInvalidParameter');
    }
    return Live::accountState($uid);
};
