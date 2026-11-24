<?php
declare(strict_types=1);
use HeroZero\{Character,EventDraws,GameError,Live};
return function(array $params):array {
    $uid=(int)($params['user_id']??0);$cid=Character::loadByUser($uid)->id();
    switch($params['action']??'') {
        case 'assignDrawEvent':EventDraws::assign($cid,(string)($params['draw_event_id']??''));break;
        case 'drawEventReward':EventDraws::draw($cid);break;
        case 'claimDrawEventReward':EventDraws::claim($cid,(int)($params['reward_index']??-1),filter_var($params['discard_item']??false,FILTER_VALIDATE_BOOLEAN));break;
        default:throw new GameError('errRequestInvalidParameter');
    }
    return Live::accountState($uid);
};
