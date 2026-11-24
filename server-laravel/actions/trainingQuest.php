<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $action = (string)($params['action'] ?? '');
    $extra = [];

    if ($action === 'startTrainingQuest') {
        $result = $char->startTrainingQuest((int)($params['training_quest_id'] ?? 0));
        $extra['training_quest'] = $result['quest'];
        if ($result['battle'] !== null) {
            $extra['training_battle'] = $result['battle'];
        }
        // Sempre usar modo singular: training_quest_id na BD e INT(11) e overflowa para
        // 2147483647 com ids grandes (e.g. 25296041341). O _training_singular_quest_id
        // passa o id real pelo PHP e attachTrainingState sobrescreve o valor da BD na
        // resposta. Alem disso, o modo singular suprime training_quests (plural), que
        // recriaria os objectos no cliente e quebraria a referencia do painel aberto
        // (stat_quest ou battle) — igual ao que ja era feito so para combate. BUG-TR-025.
        $extra['_training_singular_quest_id'] = (int)$result['quest']['id'];
    } elseif ($action === 'claimTrainingQuestRewards') {
        // O oficial responde so com `training` + `training_quests` (pool novo);
        // NAO manda `training_quest` singular aqui.
        $char->claimTrainingQuestRewards();
    } elseif ($action === 'claimTrainingStar') {
        $char->claimTrainingStar();
    }

    // accountState ja anexa trainings+training_quests consistentes (attachTrainingState).
    return Live::accountState($userId, $extra);
};
