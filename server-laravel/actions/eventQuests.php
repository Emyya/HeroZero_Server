<?php
declare(strict_types=1);
use HeroZero\{Character,EventQuests,GameError,Live};
return function(array $params):array {
    $userId=(int)($params['user_id']??0);$char=Character::loadByUser($userId);
    $discard=filter_var($params['discard_item']??false,FILTER_VALIDATE_BOOLEAN);
    switch($params['action']??'') {
        case 'claimEventQuestObjectiveRewards':
            EventQuests::claimObjective($char->id(),(string)($params['objective_identifier']??''),$discard);break;
        case 'instantFinishEventQuestObjective':
            EventQuests::claimObjective($char->id(),(string)($params['objective_identifier']??''),false,true);break;
        case 'claimEventQuestRewards':
            EventQuests::claimFinal($char->id(),(int)($params['reward_item_id']??0),$discard);break;
        default:throw new GameError('errRequestInvalidParameter');
    }
    return Live::accountState($userId);
};
