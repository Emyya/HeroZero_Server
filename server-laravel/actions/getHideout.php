<?php
declare(strict_types=1);

/**
 * action: getHideout — visitar o hideout de OUTRO jogador.
 *
 * O hideout PROPRIO nunca pede esta acao por HTTP: o cliente compara o id
 * pedido com o seu DOHideout.get_id() e mostra o painel local (loadHideout).
 * Visita chega com {hideout_id, server_id} em que hideout_id e o id da TABELA
 * `hideout` (character.hideout_id dos outros); a resposta traz as chaves
 * requested_* que o cliente converte num DOHideout proprio (Hideout.fromData)
 * + showCustomHideout.
 */

use HeroZero\Character;
use HeroZero\Hideout;
use HeroZero\Live;

return function (array $params): array {
    $userId = Live::currentUserId($params);
    $char = Character::loadByUser($userId);
    $requestedId = (int)($params['hideout_id'] ?? 0);

    $own = Hideout::rowFor($char->id());
    // O cliente envia hideout_id como character_id (campo `id` do leaderboard).
    // Resolve para a linha `hideout` pelo character_id.
    $targetHideout = Hideout::rowFor($requestedId);
    if ($own === null || $requestedId <= 0 || $targetHideout === null) {
        return Live::accountState($userId);
    }
    if ((int)$targetHideout['id'] === (int)$own['id']) {
        // Próprio hideout: painel local já tem tudo.
        return Live::accountState($userId);
    }

    return Live::accountState($userId, Hideout::visitedPayload((int)$targetHideout['id']));
};
