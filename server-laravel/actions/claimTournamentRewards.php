<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\Tournament;
use HeroZero\Db;
use HeroZero\Season;
use HeroZero\GameTime;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));

    return Tournament::rewardTransaction($char->id(), static function () use ($char): array {
        $char = Character::loadByUser($char->userId());
        if ((int)$char->field('pending_tournament_rewards') <= 0) {
            throw new GameError('errClaimTournamentRewardsNoRewards');
        }

        $reward = Db::row(
            "SELECT * FROM tournament_rewards WHERE character_id = ? AND claimed = 0 ORDER BY id ASC LIMIT 1 FOR UPDATE",
            [$char->id()]
        );

        if ($reward === null) {
            throw new GameError('errClaimTournamentRewardsNoRewards');
        }

        $rewards = Tournament::displayRewards(json_decode($reward['rewards'], true) ?? [], $char->level());

        $progress = Season::ensureProgress($char->id());
        if (!isset($rewards['season_reward'])) {
            $end = (int)Db::value('SELECT ts_end FROM tournaments WHERE id = ?', [(int)$reward['tournament_id']]);
            $rewards['season_reward'] = Tournament::seasonReward($rewards, $progress, $end);
        }
        $season = $rewards['season_reward'];

        $totalDonuts     = 0;
        $totalStatPoints = 0;
        $totalXp         = 0;

        // XP tournament (key "1"): donuts + stat_points
        if (isset($rewards['1'])) {
            $totalDonuts     += max(0, (int)($rewards['1']['donuts'] ?? 0));
            $totalStatPoints += max(0, (int)($rewards['1']['stat_points'] ?? 0));
        }

        // Stats tournament (key "2"): stat_points
        if (isset($rewards['2'])) {
            $totalStatPoints += max(0, (int)($rewards['2']['stat_points'] ?? 0));
        }

        // PvP tournament (key "4"): percentagem do XP do nível actual
        if (isset($rewards['4'])) {
            $totalXp = max(0, (int)($rewards['4']['xp_amount'] ?? 0));
        }

        if ($totalDonuts > 0) {
            Db::exec(
                "UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?",
                [$totalDonuts, $char->userId()]
            );
        }

        if ($totalStatPoints > 0) {
            Db::exec(
                "UPDATE `character` SET stat_points_available = stat_points_available + ? WHERE id = ?",
                [$totalStatPoints, $char->id()]
            );
        }

        if ($totalXp > 0) {
            Db::exec(
                "UPDATE `character` SET xp = xp + ? WHERE id = ?",
                [$totalXp, $char->id()]
            );
        }

        if ((int)$progress['status'] === 2 && (int)$progress['ts_end'] > GameTime::now()
            && (int)$season['progress_id'] === (int)$progress['id']
            && (int)$season['ts_start'] === (int)$progress['ts_start']
            && (string)$season['identifier'] === (string)$progress['identifier']) {
            Season::givePoints($char->id(), max(0, (int)$season['amount']));
        }

        Db::exec("UPDATE tournament_rewards SET claimed = 1 WHERE id = ?", [(int)$reward['id']]);
        Db::exec(
            "UPDATE `character` SET pending_tournament_rewards = GREATEST(0, pending_tournament_rewards - 1) WHERE id = ?",
            [$char->id()]
        );

        $char = Character::loadByUser($char->userId());
        $tpl = Live::template('autoLoginUser');
        $out = [];
        if (isset($tpl['user'])) {
            $out['user'] = $char->overlayUser($tpl['user']);
        }
        if (isset($tpl['character'])) {
            $out['character'] = $char->overlayCharacter($tpl['character']);
        }
        $out['season_progress'] = Season::progressForCharacter($char->id());
        $out['season_rewards'] = Season::rewardsForCharacter($char->id());
        $out['character']['active_season_progress_id'] = (int)$out['season_progress']['id'];
        return $out;
    });
};
