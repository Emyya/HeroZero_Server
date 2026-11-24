<?php
declare(strict_types=1);

/**
 * action: unlockSeasonReward  (desbloquear recompensa sem pontos, paga skip_cost donuts)
 * Entrada: skip_cost (int) — custo em donuts calculado pelo cliente
 * Devolve season_rewards (array completo), NÃO season_reward singular.
 * O cliente chama claimSeasonReward a seguir para receber a recompensa.
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\Season;

return function (array $params): array {
    $userId   = (int)($params['user_id'] ?? 0);
    $skipCost = (int)($params['skip_cost'] ?? 0);

    $char = Character::loadByUser($userId);
    $cid  = $char->id();

    // Determinar o próximo reward FREE desbloqueável ANTES de debitar donuts.
    $progress = Season::ensureProgress($cid);
    $claimed  = json_decode((string)$progress['claimed_rewards'], true);
    $unlocked = json_decode((string)$progress['unlocked_rewards'], true);
    if (!is_array($claimed))  $claimed  = [];
    if (!is_array($unlocked)) $unlocked = [];

    $points = (int)$progress['season_points'];
    $nextId = null;
    foreach (Season::rewardCatalog((string)$progress['identifier']) as $r) {
        if ($r['premium']) continue;
        if (in_array($r['id'], $claimed, true))  continue;
        if (in_array($r['id'], $unlocked, true)) continue;
        if ($points >= $r['season_points'])       continue;
        $nextId = $r['id'];
        break;
    }

    if ($nextId === null) {
        // Nada para desbloquear — não debitar donuts.
        throw new GameError('errClaimSeasonRewardAlreadyClaimed');
    }

    if ($skipCost > 0) {
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$skipCost, $userId, $skipCost]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    }

    Season::unlockReward($cid, $nextId);

    $data = Live::accountState($userId);
    // O servidor oficial devolve season_rewards (array), não season_reward singular.
    $data['season_rewards'] = Season::rewardsForCharacter($cid);
    return $data;
};
