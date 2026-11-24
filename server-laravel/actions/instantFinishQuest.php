<?php
declare(strict_types=1);

use HeroZero\GameTime;

/**
 * action: instantFinishQuest
 * O cliente nao manda quest_id: a missao ativa vem de character.active_quest_id.
 * No oficial isso zera o timer e devolve user/character/quest parciais.
 */

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $qBefore = $char->activeQuest();
    $cost = ((int)$qBefore['ts_complete'] > GameTime::now()) ? 1 : 0;
    $char->instantFinishQuest($cost);
    $char = Character::load($char->id());
    // Conclui JA a missao (ts_complete acabou de ir a 0) e devolve status +
    // fight_battle_id + battle na MESMA resposta. Antes so devolviamos
    // ts_complete=0: o cliente ficava a espera de um checkForQuestComplete que
    // so era disparado ao reabrir o painel -- dai a batalha da missao so aparecer
    // depois de mudar de seccao e voltar. Nunca enviar `battles[]` junto (ver
    // REGRA GERAL no fim deste ficheiro).
    $q = $char->checkActiveQuestComplete();
    $char = Character::load($char->id());

    $tpl = Live::template('instantFinishQuest');
    $boot = Live::template('autoLoginUser');
    $data = [
        'user' => $char->overlayUser($tpl['user'] ?? $boot['user'] ?? []),
        'character' => $char->overlayCharacter($tpl['character'] ?? $boot['character'] ?? []),
        'quest' => [
            'id' => (int)$q['id'],
            'ts_complete' => 0,
            'status' => (int)$q['status'],
            'fight_battle_id' => (int)$q['fight_battle_id'],
            // idem checkForQuestComplete: sem `rewards` o cliente mostrava o
            // premio cheio depois de perder a luta.
            'rewards' => (string)$q['rewards'],
        ],
    ];
    $battle = $char->battleData((int)$q['fight_battle_id']);
    if ($battle !== null) {
        $data['battle'] = $battle;
    }
    if ($cost > 0) {
        $data['current_goal_values'] = [
            'donuts_spent' => [
                'value' => $cost,
                'current_value' => $cost,
            ],
        ];
    }
    foreach (['server_time', 'time_correction'] as $k) {
        if (array_key_exists($k, $tpl)) $data[$k] = $tpl[$k];
    }
    return \HeroZero\DailyMilestones::attachAll($data, $char->id());
};
