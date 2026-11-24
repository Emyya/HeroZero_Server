<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\League;
use HeroZero\Live;

/**
 * Entrar na liga. league_group_id codifica divisao E grupo: o client deriva a divisao
 * como floor(league_group_id / 100000) (Dd.getLeagueDivisionId). Divisao 1 = 100000 --
 * setar 1 aqui deixava a divisao em 0 e o client achava que nunca tinha entrado
 * (botao "Ingressar agora!" reenviando pra sempre). Honra minima pela constante
 * oficial `league_min_honor_points` (500 no asset atual).
 *
 * Pontos iniciais = 0 (decisao, regra oficial): o seed antigo de 100 pontos foi
 * removido -- com entry_points>0 o cliente considerava a divisao ja vencida.
 *
 * Resposta via accountState() (monta 'character' do banco; template de captura para
 * essa action nao existe) + league_opponents pela MESMA fonte da tela da liga
 * (Live::leagueOpponents, tambem usada em getLeagueOpponents/refreshLeagueOpponents),
 * que persiste a lista em character.league_opponents.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);

    League::ensureSeason();
    $row = Db::row('SELECT league_group_id, honor FROM `character` WHERE id = ?', [$char->id()]) ?? [];
    // So conta como inscrito um group id VALIDO: divisao = floor(id/100000)
    // tem de existir na tabela oficial. Valores sujos (ex.: edit manual "301",
    // que da divisao 0 no cliente E no servidor) deixavam o heroi meio-presos:
    // nao re-ingressavam (id != 0), mas nao tinham divisao nem bonus nenhum.
    $currentDiv = League::divisionFromGroup((int)($row['league_group_id'] ?? 0));
    if ($currentDiv === 0 || !isset(League::divisions()[$currentDiv])) {
        if ((int)($row['honor'] ?? 0) < (int)League::constant('league_min_honor_points')) {
            throw new GameError('errEnterLeagueDivisionMinHonorNotReached');
        }
        Db::exec('UPDATE `character` SET league_group_id = ?, league_points = 0 WHERE id = ?',
            [League::GROUP_FACTOR * 1 + 1, $char->id()]);
    }

    $opponents = Live::leagueOpponents($char->id());
    $data = Live::accountState($userId);
    $data['league_opponents'] = $opponents;
    return $data;
};
