<?php
declare(strict_types=1);

/**
 * action: unlockHideout — desbloquear o esconderijo (primeiro acesso ao painel).
 * Params: {}. Requisito: character.level >= hideout_unlock_min_level (8 no 257).
 * Erros do cliente sao reportError genericos, qualquer codigo serve.
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Hideout;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $char = Character::loadByUser($userId);

    \HeroZero\StoryDungeon::transaction($char->id(), static function(array $locked) use ($char):void {
        if (Hideout::rowFor($char->id()) !== null) return;
        if ((int)$locked['level'] < (int)Hideout::c('hideout_unlock_min_level', 8)) {
            throw new GameError('errUnlockHideoutNotYetAllowed');
        }
        $h=Hideout::createRow($char->id());
        // Um robô inicial; o segundo exige a compra local hideout_worker.
        Db::exec('UPDATE hideout SET current_resource_glue=?, current_resource_stone=?,
            max_worker_count=1, idle_worker_count=1,
            room_slot_1_0=0, room_slot_1_1=0, room_slot_1_2=0, room_slot_1_3=0, room_slot_1_4=0
            WHERE id=?', [(int)Hideout::c('hideout_tutorial_start_glue',200),
                (int)Hideout::c('hideout_tutorial_start_stone',400),(int)$h['id']]);
    });
    return Live::accountState($userId);
};
