<?php
declare(strict_types=1);
use HeroZero\{Character,HeroCon,Live};
return function(array $params):array {
    $char=Character::loadByUser((int)($params['user_id']??0));$cid=$char->id();$action=(string)$params['action'];$extra=[];
    switch($action) {
        case 'retrieveGuildCompetitionTournamentLeaderboard':$extra=HeroCon::leaderboard($cid,false,$params);break;
        case 'retrieveSoloGuildCompetitionTournamentLeaderboard':$extra=HeroCon::leaderboard($cid,true,$params);break;
        case 'getGuildCompetitionTournamentReward':$extra['tournament_guild_competition_reward']=HeroCon::reward($cid,5);break;
        case 'getSoloGuildCompetitionTournamentReward':$extra['tournament_solo_guild_competition_reward']=HeroCon::reward($cid,6);break;
        case 'claimGuildCompetitionTournamentReward':HeroCon::reward($cid,5,true);break;
        case 'claimSoloGuildCompetitionTournamentReward':HeroCon::reward($cid,6,true,filter_var($params['discard_item']??false,FILTER_VALIDATE_BOOLEAN));break;
        case 'setNewGuildCompetitionViewed':HeroCon::viewed($cid);break;
        case 'retrieveGuildCompetitionTournamentLeaderboardGuildsAroundOwnGuild':break;
        default:throw new HeroZero\GameError('errActionNotSupported');
    }
    return HeroCon::attach(array_replace(Live::accountState($char->userId()),$extra),$cid);
};
