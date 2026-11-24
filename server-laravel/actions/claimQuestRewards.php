<?php
declare(strict_types=1);

/**
 * action: claimQuestRewards
 * Aplica coins/xp da missao concluida, limpa active_quest_id e gera novo pool.
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Sidekick;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->claimQuestRewards();
    $char = Character::load($char->id());

    $tpl = Live::template('claimQuestRewards');
    $boot = Live::template('autoLoginUser');
    $data = [
        'user' => $char->overlayUser($tpl['user'] ?? $boot['user'] ?? []),
        'character' => $char->overlayCharacter($tpl['character'] ?? $boot['character'] ?? []),
        'quests' => $char->questsData($tpl['quests'][0] ?? $boot['quests'][0] ?? []),
        // SIDEKICK: o XP partilhado (giveXpToActive) pode subir o nivel do pet
        // NESTA resposta. Sem `sidekicks` o modelo do cliente fica velho e o
        // dialogo de level-up/barra so aparecem no sync seguinte (~60s).
        // O cliente faz merge por id em qualquer resposta (refreshSidekicks).
        'sidekicks' => Sidekick::ownedByCharacter($char->id()),
    ];
    foreach (['server_time', 'time_correction'] as $k) {
        if (array_key_exists($k, $tpl)) $data[$k] = $tpl[$k];
    }
    return \HeroZero\DailyMilestones::attachAll($data, $char->id());
};
