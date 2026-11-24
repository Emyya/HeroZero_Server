<?php
declare(strict_types=1);

/**
 * action: improveCharacterStat  (comprar +1 num atributo com game_currency)
 * Entrada: stat_type (1=stamina,2=strength,3=critical,4=dodge), skill_value
 * Efeito REAL no banco: debita moeda, incrementa o atributo, persiste.
 * Resposta: `character`/`user` vivos + current_goal_values (template).
 */

use HeroZero\Character;
use HeroZero\Live;
use HeroZero\Replay;

return function (array $params): array {
    $statType   = (int)($params['stat_type'] ?? 0);
    $skillValue = max(1, (int)($params['skill_value'] ?? 1));

    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $char->improveStat($statType, $skillValue);   // gratis primeiro; GameError se faltar moeda

    $data = Replay::data('improveCharacterStat');   // template (shape) + server_time
    // A shape do `character` vem do BOOT, nao desta captura: o improveCharacterStat
    // foi capturado de alguem a comprar FORCA, logo o template so tem
    // `stat_base_strength`/`stat_bought_strength`. Como o overlayCharacter apenas
    // preenche chaves JA presentes no template, comprar stamina/critico/esquiva
    // devolvia a resposta sem o respetivo stat_base_* e o valor da habilidade
    // basica nao atualizava no ecra (so a forca e que mexia).
    $boot = Live::template('autoLoginUser');
    $data['character'] = $char->overlayCharacter($boot['character'] ?? $data['character'] ?? []);
    if (isset($data['user']))      $data['user']      = $char->overlayUser($data['user']);
    unset($data['trainings']);
    return Live::attachTrainingState($data, $char);
};
