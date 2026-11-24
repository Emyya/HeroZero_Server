<?php
declare(strict_types=1);

/**
 * action: generateNewQuests
 * Botao "novas missoes" do painel de quests.
 *
 * all_stages=false (default) — regenera so a cidade atual, custa 2 donuts.
 * all_stages=true            — regenera todas as cidades, custa 9 donuts.
 *
 * A quest ativa e sempre preservada. O cliente faz updateData(data), por isso
 * devolvemos user/character/quests como em claimQuestRewards.
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId    = (int)($params['user_id'] ?? 0);
    $allStages = filter_var($params['all_stages'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $cost      = $allStages ? 9 : 2;

    $affected = Db::exec(
        'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
        [$cost, $userId, $cost]
    );
    if ($affected !== 1) {
        throw new GameError('errRemovePremiumCurrencyNotEnough');
    }

    $char = Character::loadByUser($userId);
    $char->regenerateQuests($allStages);
    $char = Character::load($char->id());

    $boot = Live::template('autoLoginUser');
    return [
        'user'      => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
        'quests'    => $char->questsData($boot['quests'][0] ?? []),
    ];
};
