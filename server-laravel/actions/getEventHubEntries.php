<?php
declare(strict_types=1);

use HeroZero\Live;

/**
 * action: getEventHubEntries
 * As capturas do HAR vieram com response nula, entao nao ha template real.
 * O cliente EXIGE a chave `event_hub_entries` (array): sem ela o EventHub do
 * personagem fica null e o painel crasha em get_entries() (ver last_error.json).
 * Casino agendado e evento de coleção atribuído entram no hub.
 */
return function (array $params): array {
    $userId = Live::currentUserId($params);
    $data = $userId > 0 ? Live::accountState($userId) : Live::timeKeys([]);
    $cid=(int)($data['character']['id'] ?? 0);
    $data['event_hub_entries'] = array_merge(\HeroZero\Casino::hub(), $cid>0 ? array_merge(\HeroZero\EventQuests::hub($cid), \HeroZero\EventTreasures::hub($cid), \HeroZero\EventDraws::hub($cid), \HeroZero\TowerEvents::hub($cid)) : []);
    return $data;
};
