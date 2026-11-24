<?php
declare(strict_types=1);

/**
 * Cassino (slot machine). Ciclo real do client (dn=PanelCasino, Tt=DialogCasinoPossibleRewards,
 * eD=DialogCasinoReward, engenharia reversa do HeroZero.min.js):
 *   spinCasinoMachine {multi_spin} -> resultado JA vem na resposta (slots/reward_type/
 *   reward_quality/rewards) E fica "active" ate o dialog fechar -> applyCasinoReward
 *   {discard_item} credita (ou descarta) e limpa o active. getCasinoReward reobtem o
 *   active pendente (ex.: client recarregou antes de fechar o dialog).
 * getCasinoPossibleRewards preenche o dialog de premios possiveis (secoes); sem isso
 * o painel abria vazio (nunca tinha handler real, so stateEcho).
 */

use HeroZero\Character;
use HeroZero\Casino;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);

    $extra = [];
    switch ($action) {
        case 'addUserToCasinoRoom':
        case 'removeUserFromCasinoRoom':
            Casino::room($char->id(), $action === 'addUserToCasinoRoom');
            break;

        case 'getCasinoPossibleRewards':
            $extra['sections'] = Character::casinoPossibleRewardSections($char->id());
            break;

        case 'getLastCasinoWins':
            $extra['messages'] = Casino::wins($char->id());
            break;

        case 'spinCasinoMachine':
            $raw = $params['multi_spin'] ?? 1;
            if ((!is_int($raw) && !is_string($raw)) || !in_array((string)$raw, ['1','5','10'], true)) throw new GameError('errRequestInvalidParameter');
            $multiSpin = (int)$raw;
            $active = $char->spinCasino($multiSpin);
            $extra['casino_slot1'] = $active['slots'][0];
            $extra['casino_slot2'] = $active['slots'][1];
            $extra['casino_slot3'] = $active['slots'][2];
            $extra['casino_reward_type'] = $active['reward_type'];
            $extra['casino_reward_quality'] = $active['reward_quality'];
            $extra['rewards'] = Casino::clientRewards($active['rewards']);
            break;

        case 'getCasinoReward':
            $active = $char->activeCasinoSpin();
            $extra['casino_slot1'] = $active['slots'][0];
            $extra['casino_slot2'] = $active['slots'][1];
            $extra['casino_slot3'] = $active['slots'][2];
            $extra['casino_reward_type'] = $active['reward_type'];
            $extra['casino_reward_quality'] = $active['reward_quality'];
            $extra['rewards'] = Casino::clientRewards($active['rewards']);
            break;

        case 'applyCasinoReward':
            // (bool)"false" == true em PHP -- o client manda string form-urlencoded,
            // tem de validar de verdade (mesmo padrao usado no resto do projeto),
            // senao TODA recompensa era tratada como descarte e nunca creditava.
            $char->applyCasinoReward(filter_var($params['discard_item'] ?? false, FILTER_VALIDATE_BOOLEAN));
            break;

        default:
            throw new GameError('errRequestInvalidParameter');
    }

    // accountState recarrega o character (moeda/xp ja refletem o UPDATE).
    return Live::accountState($userId, $extra);
};
