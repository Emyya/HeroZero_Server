<?php
declare(strict_types=1);

use HeroZero\{Character, Db, GameError, Tournament, Season};

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    return Tournament::rewardTransaction($char->id(), static function () use ($char): array {
        $char = Character::loadByUser($char->userId());
        $reward = Db::row('SELECT * FROM tournament_rewards WHERE character_id = ? AND claimed = 0 ORDER BY id ASC LIMIT 1 FOR UPDATE', [$char->id()]);
        if ((int)$char->field('pending_tournament_rewards') <= 0 || $reward === null) {
            throw new GameError('errTournamentLocked');
        }
        $rewards = Tournament::displayRewards(json_decode($reward['rewards'], true) ?? [], $char->level());
        if (!isset($rewards['season_reward'])) {
            $end = (int)Db::value('SELECT ts_end FROM tournaments WHERE id = ?', [(int)$reward['tournament_id']]);
            $rewards['season_reward'] = Tournament::seasonReward($rewards, Season::ensureProgress($char->id()), $end);
        }
        $json = json_encode($rewards, JSON_THROW_ON_ERROR);
        Db::exec('UPDATE tournament_rewards SET rewards = ? WHERE id = ?', [$json, (int)$reward['id']]);
        return ['tournament_rewards' => ['id' => (int)$reward['id'], 'week' => (int)$reward['week'], 'rewards' => $json]];
    });
};
