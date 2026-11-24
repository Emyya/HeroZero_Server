<?php
declare(strict_types=1);

/**
 * action: resetSeasonProgress  (reiniciar progresso da season, custa donuts)
 * Erro: errRemovePremiumCurrencyNotEnough
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\Season;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char   = Character::loadByUser($userId);
    $cost   = Season::resetCost();

    if ($cost > 0) {
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $userId, $cost]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    }

    Season::resetProgress($char->id());

    return Live::accountState($userId);
};
