<?php
declare(strict_types=1);

use HeroZero\GameTime;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\DuelRewards;
use HeroZero\Fight;
use HeroZero\GameError;
use HeroZero\GuildArtifacts;
use HeroZero\Live;

/** Constants oficiais (CDN, chave "duel_*"): 1 stamina por ataque (max_duel_stamina
 *  observado e 10, um custo fixo de 20 -- resquicio do codigo antigo -- nunca cabia). */
const DUEL_STAMINA_COST_PER_ATTACK = 20;
const DUEL_SINGLE_ATTACK_PREMIUM_AMOUNT = 2;

$handle = function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $now = GameTime::now();
    // Credita a regeneracao pendente ANTES de validar o custo, senao um jogador
    // com stamina ja regenerada levava errRemoveDuelStaminaNotEnough (a coluna so
    // era atualizada quando o character era serializado).
    $char->syncDuelStamina();
    $row = Db::row('SELECT active_duel_id, duel_stamina, max_duel_stamina FROM `character` WHERE id = ?', [$char->id()]);
    // Sem essas travas o client conseguia empilhar duelos (o active_duel_id
    // anterior ficava orfao, nunca reivindicado) ou duelar com stamina 0 --
    // os codigos de erro (errStartDuelActiveDuelFound/errRemoveDuelStaminaNotEnough)
    // ja existem no client, so faltava o server checar.
    $activeDuelId = (int)($row['active_duel_id'] ?? 0);
    if ($activeDuelId !== 0) {
        // Se o duelo ativo ja foi reivindicado (status 3) ou nao existe mais,
        // zera active_duel_id p/ nao bloquear novos ataques (deixa o jogador
        // "preso num duelo" para sempre apos um reload/erro).
        $activeDuel = Db::row('SELECT * FROM `duel` WHERE id = ? AND character_a_id = ?', [$activeDuelId, $char->id()]);
        if ($activeDuel === null || (int)$activeDuel['character_a_status'] === 3) {
            // Duelo nao existe ou ja foi reivindicado: limpa e permite novo ataque.
            Db::exec('UPDATE `character` SET active_duel_id = 0 WHERE id = ?', [$char->id()]);
            $activeDuelId = 0;
        } elseif (in_array((int)$activeDuel['character_a_status'], [1, 2], true)) {
            // Duelo iniciado mas nunca reivindicado (cliente crashou/reload): retoma
            // o duelo existente para que o cliente possa chamar claimDuelRewards.
            $battleId = (int)$activeDuel['battle_id'];
            $opponentId = (int)$activeDuel['character_b_id'];
            $boot = Live::template('autoLoginUser');
            $char = Character::load($char->id());
            $data = [
                'user' => $char->overlayUser($boot['user'] ?? []),
                'character' => $char->overlayCharacter($boot['character'] ?? []),
            ];
            $data['duel'] = Live::shapeLike($boot['duel'] ?? [], $activeDuel);
            $battleRow = Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]) ?? [];
            $data['battle'] = Live::shapeLike($boot['battle'] ?? [], $battleRow);
            // Resposta parcial: nao enviar battles[] nem colecoes de missoes/treinos
            // do boot de outra conta, que substituiriam as ofertas reais do cliente.
            $data['opponent'] = Live::requestedCharacter($opponentId, $boot['opponent'] ?? []);
            $data['opponent_inventory'] = Live::inventoryForCharacter($opponentId, $boot['inventory'] ?? []);
            $data['opponent_inventory_items'] = Live::itemsForCharacter($opponentId, $boot['items'][0] ?? []);
            $data['inventory'] = $char->inventoryData($boot['inventory'] ?? []);
            $data['items'] = $char->itemsData($boot['items'][0] ?? []);
            $data['bank_inventory'] = \HeroZero\BankInventory::data($char->id());
            $data['server_time'] = GameTime::now();
            $data['time_correction'] = 0;
            return $data;
        }
    }
    if ($activeDuelId !== 0) {
        throw new GameError('errStartDuelActiveDuelFound');
    }

    // Custo efetivo de coragem: trofeus type 1 da guilda descontam 2 cada
    // (piso 10). Base 20 = constante oficial `duel_stamina_cost`.
    $staminaCostFull = GuildArtifacts::duelStaminaCost((int)$char->field('guild_id'));
    $useStamina = (int)($row['duel_stamina'] ?? 0) >= $staminaCostFull;
    if (!$useStamina) {
        // Sem stamina: o botao de ataque vira "pagar com premium" (use_premium=true,
        // mesmo custo fixo que o client mostra via get_duel_single_attack_premium_amount).
        // Sem isso, jogador sem stamina ficava travado ate regenerar, mesmo mandando
        // querendo pagar.
        $usePremium = filter_var($params['use_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$usePremium) {
            throw new GameError('errRemoveDuelStaminaNotEnough');
        }
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [DUEL_SINGLE_ATTACK_PREMIUM_AMOUNT, $char->userId(), DUEL_SINGLE_ATTACK_PREMIUM_AMOUNT]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    }

    $opponentId = (int)($params['character_id'] ?? 0);
    if ($opponentId > 0 && $opponentId !== $char->id()) {
        // Oponente tem de ser um JOGADOR REAL existente (user_id <> 0) e nao o
        // proprio. Antes exigia user_id = 0 (os NPCs do seed), o que rejeitava
        // qualquer conta real. Sem esta validacao criava-se um duelo orfao que
        // prendia o jogador ("ainda estou num duelo").
        $valid = (int)Db::value(
            'SELECT COUNT(*) FROM `character` WHERE id = ? AND user_id <> 0',
            [$opponentId]
        );
        if ($valid !== 1) {
            throw new GameError('errStartDuelInvalidEnemy');
        }
    } else {
        $opps = Character::duelOpponents($char->id(), [], 1);
        $opponentId = (int)($opps[0]['id'] ?? 0);
        if ($opponentId <= 0) {
            throw new GameError('errStartDuelInvalidEnemy');
        }
    }
    $opp = Character::load($opponentId);

    // Stats REAIS dos dois personagens (nao mais um array fixo nivel-1) -- batalha
    // tem de refletir o perfil de verdade (base+treinado+equipamento), igual o
    // proprio character overlay mostra. battleProfile('a') nunca aplica a
    // penalidade de -1 (essa e so pro caso sem oponente real, luta de missao).
    $profileA = $char->battleProfile('a');
    $profileB = $opp->battleProfile('a');
    $profileB['profile'] = 'b';

    // COMBATE SIMULADO: o vencedor sai dos atributos reais dos dois lados
    // (HeroZero\Fight, porte do Cls\Fight oficial). Antes o resultado era fixo em
    // 'a' com rounds fabricados -- o jogador ganhava sempre.
    // O bloco `rounds` TEM de incluir profile_a_appearance/profile_b_appearance
    // (Battle.calculateRounds le estas chaves para renderizar os lutadores; sem
    // elas o painel de batalha crasha -> "LOC"/reload/duelo preso).
    $fight = new Fight($profileA, $profileB);
    $winnerSide = $fight->fight() === 1 ? 'a' : 'b';
    $rounds = ['rounds' => $fight->rounds()];
    $rounds['profile_a_appearance'] = $char->battleAppearance();
    $rounds['profile_b_appearance'] = $opp->battleAppearance();

    Db::exec(
        "INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds) VALUES (?, ?, ?, ?, ?)",
        [$now, json_encode($profileA, JSON_UNESCAPED_SLASHES), json_encode($profileB, JSON_UNESCAPED_SLASHES), $winnerSide, json_encode($rounds, JSON_UNESCAPED_SLASHES)]
    );
    $battleId = (int)Db::pdo()->lastInsertId();
    \HeroZero\Missiles::consume($char->id(), $fight->missileUsage('a'));
    // Recompensas CALCULADAS (DuelRewards) e do lado CERTO: quem ganha leva
    // moedas + honra, quem perde leva a penalizacao de honra. Eram fixas
    // ('{"coins":5,"honor":10}') e sempre a favor de 'a'.
    // Multiplicador do VENCEDOR (booster de duelo da guilda dele): o tooltip da
    // lista de duelos (getDuelResultTooltip) promete o ganho com este booster
    // aplicado (d = 1 + activeDuelBoosterAmount/100); sem passar o mult aqui o
    // valor pago ficava abaixo do prometido.
    $winnerChar = $winnerSide === 'a' ? $char : $opp;
    // Trofeus type 4 do VENCEDOR (Duelo & Liga): +25% de recompensa cada
    // (get_activeDuelLeagueArtifactAmount no cliente aplica o mesmo fator).
    $duelMult = (1.0 + $winnerChar->guildBoosterAmount('duel') / 100.0)
        * GuildArtifacts::pvpRewardMultiplier((int)$winnerChar->field('guild_id'));
    $rewards = DuelRewards::forDuel(
        $char->honor(), $char->level(), $opp->honor(), $opp->level(), $winnerSide === 'a', $duelMult
    );
    Db::exec(
        "INSERT INTO `duel` (ts_creation, battle_id, character_a_id, character_b_id, character_a_status, character_b_status, character_a_rewards, character_b_rewards, unread)
         VALUES (?, ?, ?, ?, 1, 1, ?, ?, 'true')",
        [
            $now, $battleId, $char->id(), $opponentId,
            json_encode($rewards['a'], JSON_UNESCAPED_SLASHES),
            json_encode($rewards['b'], JSON_UNESCAPED_SLASHES),
        ]
    );
    // TEM de vir IMEDIATAMENTE apos o INSERT: um UPDATE pelo meio (o das
    // recompensas do defensor, abaixo) RESETA o lastInsertId para 0 -- com 0, o
    // SELECT do duelo falhava, a resposta levava o duelo/battle do TEMPLATE
    // (ids da captura) e o painel de batalha crashava (herois invisiveis, "LOC",
    // skip nao sai da animacao).
    $duelId = (int)Db::pdo()->lastInsertId();
    // Recompensas do DEFENSOR aplicadas IMEDIATAMENTE (1:1 com o oficial
    // startDuel.req.php: `$opponent->giveRewards($duel->character_b_rewards)`).
    // O painel de duelos perdidos e apenas INFORMATIVO (ver/apagar) -- se a
    // honra so fosse aplicada ao reclamar, o ranking e o perfil ficavam com a
    // honra velha enquanto o defensor nao clicasse no icone do soco.
    Db::exec(
        'UPDATE `character` SET game_currency = game_currency + ?, honor = GREATEST(0, honor + ?) WHERE id = ?',
        [(int)$rewards['b']['coins'], (int)$rewards['b']['honor'], $opponentId]
    );
    // active_duel_id PERSISTIDO (nao so na resposta): sem coluna real, o campo
    // vazava o valor da captura original (skelletonx, duelo de outra conta) em
    // toda resposta que reconstroi 'character' via accountState (praticamente
    // qualquer poll no meio do duelo) -- get_activeDuel() do client compara
    // _duel.get_id()==active_duel_id, entao o duelo "sumia" assim que o id errado
    // voltava. Mesmo padrao ja corrigido pra active_league_fight_id.
    $staminaCost = $useStamina ? $staminaCostFull : 0;
    // Se a barra estava CHEIA, o relogio de regeneracao so arranca agora (estava
    // parado). Se ja estava a encher, `ts_last_duel_stamina_change` e preservado
    // para nao perder o progresso parcial em curso. "Cheia" contra o maximo
    // EFETIVO (trofeus type 1 elevam a coragem maxima acima da coluna da BD).
    $wasFull = (int)($row['duel_stamina'] ?? 0) >= GuildArtifacts::effectiveMax(
        'duel_stamina',
        (int)($row['max_duel_stamina'] ?? 100),
        (int)$char->field('guild_id')
    );
    if ($staminaCost > 0 && $wasFull) {
        Db::exec('UPDATE `character` SET ts_last_duel_stamina_change = ? WHERE id = ?', [$now, $char->id()]);
    }
    Db::exec('UPDATE `character` SET duel_stamina = GREATEST(0, duel_stamina - ?), ts_last_duel = ?, active_duel_id = ? WHERE id = ?', [$staminaCost, $now, $duelId, $char->id()]);

    // O boot serve apenas de molde para os objetos enviados. Enviar o boot inteiro
    // substituia no cliente e nos bots as missoes/treinos reais pelas ofertas de
    // outra conta presentes na fixture. A resposta parcial conserva o contrato do
    // combate sem mexer nessas colecoes.
    $boot = Live::template('autoLoginUser');
    $char = Character::load($char->id());
    $data = [
        'user' => $char->overlayUser($boot['user'] ?? []),
        'character' => $char->overlayCharacter($boot['character'] ?? []),
    ];
    $data['duel'] = Live::shapeLike($boot['duel'] ?? [], Db::row('SELECT * FROM `duel` WHERE id = ?', [$duelId]) ?? []);
    $data['battle'] = Live::shapeLike($boot['battle'] ?? [], Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]) ?? []);
    $data['opponent'] = Live::requestedCharacter($opponentId, $boot['opponent'] ?? []);
    $data['opponent_inventory'] = Live::inventoryForCharacter($opponentId, $boot['inventory'] ?? []);
    $data['opponent_inventory_items'] = Live::itemsForCharacter($opponentId, $boot['items'][0] ?? []);
    $data['inventory'] = $char->inventoryData($boot['inventory'] ?? []);
    $data['items'] = $char->itemsData($boot['items'][0] ?? []);
    $data['bank_inventory'] = \HeroZero\BankInventory::data($char->id());
    $data['server_time'] = GameTime::now();
    $data['time_correction'] = 0;
    return $data;
};

return function (array $params) use ($handle): array {
    $cid = (int)Db::value('SELECT id FROM `character` WHERE user_id = ?', [(int)($params['user_id'] ?? 0)]);
    return \HeroZero\Missiles::transaction([$cid, (int)($params['character_id'] ?? 0)], fn() => $handle($params));
};
