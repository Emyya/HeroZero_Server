<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\StoryDungeon;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $index = (int)($params['story_dungeon_index'] ?? 0);
    $step = (int)($params['step_index'] ?? 0);
    $bool = static fn(string $key): bool => filter_var($params[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    switch ((string)($params['action'] ?? '')) {
        case 'startNewStoryDungeonStep':
            StoryDungeon::start($char->id(), $index, $step);
            break;
        case 'startStoryDungeonBattle':
            StoryDungeon::finish($char->id(), $index, $step, false, $bool('finish_cooldown'));
            break;
        case 'instantFinishStoryDungeonStep':
            StoryDungeon::finish($char->id(), $index, $step, true);
            break;
        case 'claimStoryDungeonStepReward':
            StoryDungeon::claim($char->id(), $index, $step, $bool('discard_item'));
            break;
        default:
            throw new GameError('errRequestInvalidParameter');
    }
    return Live::accountState($userId);
};
