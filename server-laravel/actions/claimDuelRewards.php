<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $duel = \HeroZero\StoryDungeon::transaction($char->id(), function () use ($char): ?array {
        $char = Character::load($char->id());
        $duel = Db::row('SELECT * FROM `duel` WHERE character_a_id = ? AND character_a_status = 2 ORDER BY id DESC LIMIT 1', [$char->id()]);
        if ($duel) {
            $rewards = json_decode((string)$duel['character_a_rewards'], true);
            if (!is_array($rewards)) $rewards = [];
            // honor com piso 0: as recompensas passaram a ser calculadas (DuelRewards)
            // e o bloco do perdedor e negativo -- sem o GREATEST a honra podia ficar
            // negativa e o cliente desenha a barra/ranking com valores absurdos.
            Db::exec(
                'UPDATE `character` SET game_currency = game_currency + ?, honor = GREATEST(0, honor + ?), active_duel_id = 0 WHERE id = ?',
                [(int)($rewards['coins'] ?? 0), (int)($rewards['honor'] ?? 0), $char->id()]
            );
            Db::exec('UPDATE `duel` SET character_a_status = 3 WHERE id = ?', [(int)$duel['id']]);
            $duel['character_a_status'] = 3;
            \HeroZero\DailyMilestones::record($char->id(), 1, \HeroZero\DailyMilestones::DUEL);
            \HeroZero\Herobook::record($char->id(), 11);
            \HeroZero\StoryDungeon::record($char->id(), \HeroZero\StoryDungeon::DUEL);
            \HeroZero\EventQuests::record($char->id(), \HeroZero\EventQuests::DUEL);
            \HeroZero\HeroCon::record($char->id(), Db::value('SELECT winner FROM battle WHERE id = ?', [(int)$duel['battle_id']]) === 'a' ? 11 : 24);
            if (Db::value('SELECT winner FROM battle WHERE id = ?', [(int)$duel['battle_id']]) === 'a') {
                \HeroZero\StoryDungeon::record($char->id(), \HeroZero\StoryDungeon::DUEL_WON);
            }
            // Tutorial: ganhar o primeiro duelo fecha o tutorial (cliente le first_duel
            // via hasNewTutorialFlag para mostrar o dialogo de tutorial concluido).
            $char->setTutorialFlag('first_duel', true);
        }
        return $duel;
    });
    $char = Character::load($char->id());
    // Shape a partir do BOOT (autoLoginUser). NAO existe fixture
    // `data/claimDuelRewards.json`, logo `Live::template('claimDuelRewards')`
    // devolvia [] e os `isset()` abaixo eram sempre falsos -> a resposta saia SEM
    // `character`. O cliente nunca era informado de que `active_duel_id` passou a
    // 0, mantinha o duelo em memoria e, ao voltar ao painel de duelos,
    // `get_activeDuel()` ainda devolvia o duelo ja reivindicado e reexibia a
    // ultima luta. O `character` (com active_duel_id=0) e o que fecha o ciclo.
    $boot = Live::template('autoLoginUser');
    $data = [];
    $data['user'] = $char->overlayUser($boot['user'] ?? []);
    $data['character'] = $char->overlayCharacter($boot['character'] ?? []);
    if ($duel) {
        $data['duel'] = Live::shapeLike($boot['duel'] ?? [], $duel);
    }
    return \HeroZero\DailyMilestones::attachAll($data, $char->id());
};
