<?php
declare(strict_types=1);

/**
 * action: getDailyBonusRewardData
 * Config capturada dos outros bónus; patamares de energia e conta são vivos.
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Replay;

return function (array $params): array {
    $data = Replay::data('getDailyBonusRewardData');   // template completo + server_time sincronizado
    try {
        $char = Character::loadByUser((int)($params['user_id'] ?? 0));
        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
        if (isset($data['user']))      $data['user']      = $char->overlayUser($data['user']);
        unset($data['trainings']);
        $data = Live::attachTrainingState($data, $char);
        $data = \HeroZero\DailyMilestones::attachAll($data, $char->id());
    } catch (\HeroZero\GameError $e) {
        // sem personagem no banco -> devolve o template puro (nao trava o boot).
    }
    return $data;
};
