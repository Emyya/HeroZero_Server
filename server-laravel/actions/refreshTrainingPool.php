<?php
declare(strict_types=1);

/**
 * action: refreshTrainingPool
 * params: use_free (bool), use_premium (bool)
 *
 * Erros que o cliente trata:
 *   errGenerateNewTrainingsActiveTrainingFound  — ha treino em curso
 *   errGenerateNewTrainingsNotYetAllowed        — cooldown livre ainda nao expirou
 *   errGenerateNewTrainingsFreeNotAllowed       — refrescagem livre nao disponivel
 *   errGenerateNewTrainingsPremiumNotAllowed    — refrescagem premium nao disponivel
 *   errRemovePremiumCurrencyNotEnough           — donuts insuficientes
 *
 * Cooldown livre: training_cooldown = 600 s (ver balancing_constants.json).
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Live;

return function (array $params): array {
    $userId     = (int)($params['user_id'] ?? 0);
    $useFree    = filter_var($params['use_free']    ?? false, FILTER_VALIDATE_BOOLEAN);
    $usePremium = filter_var($params['use_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $char = Character::loadByUser($userId);

    // Nao pode refrescar com treino activo.
    $active = Db::row(
        "SELECT id FROM `training` WHERE character_id = ? AND status = 2 LIMIT 1",
        [$char->id()]
    );
    if ($active !== null) {
        throw new GameError('errGenerateNewTrainingsActiveTrainingFound');
    }

    $cooldown = 600; // training_cooldown da constants oficial
    $lastRefresh = (int)($char->field('ts_last_training') ?? 0);
    $nextFree = $lastRefresh + $cooldown;

    if ($usePremium) {
        // Custa 1 donut (training_refresh_offers_cost = 1).
        $affected = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - 1 WHERE id = ? AND premium_currency >= 1',
            [$userId]
        );
        if ($affected !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    } elseif ($useFree) {
        if (GameTime::now() < $nextFree) {
            throw new GameError('errGenerateNewTrainingsNotYetAllowed');
        }
    } else {
        throw new GameError('errGenerateNewTrainingsFreeNotAllowed');
    }

    // Actualiza o timestamp de refresh (= ts_last_training no nosso schema).
    Db::exec(
        'UPDATE `character` SET ts_last_training = ?, ts_last_action = ? WHERE id = ?',
        [GameTime::now(), GameTime::now(), $char->id()]
    );

    return Live::accountState($userId);
};
