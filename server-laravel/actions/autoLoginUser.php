<?php
declare(strict_types=1);

/**
 * action: autoLoginUser  (relogin com sessao salva)
 * Monta o boot a partir do banco:
 *  - `character`: TODOS os campos que sao coluna vem do banco (identidade inclusa);
 *  - `user`: id/premium do banco;
 *  - estruturas de CONTA (inventario, itens, quests, duelos, metas...) sao esvaziadas
 *    preservando a shape -> conta nova nasce limpa e nao herda dados de outra conta.
 * A config GLOBAL (constants, items, campanhas, ...) permanece do template.
 * TODO(RE): quando as tabelas de inventario/quests forem populadas, ler do banco em vez de esvaziar.
 */

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\GuildBattles;
use HeroZero\Live;
use HeroZero\Replay;
use HeroZero\Session;
use HeroZero\Tournament;

/**
 * Estruturas de conta que ainda NAO tem tabela mapeada -> zeradas preservando a shape
 * (conta nova nasce limpa e nao herda nada de outra conta).
 * `inventory`, `items` e `quests` NAO estao aqui: sao lidos do banco (ver abaixo).
 */
const ACCOUNT_STATE_KEYS = [
    'owned_items', 'battle', 'battles', 'duel', 'opponent',
    'opponent_inventory', 'opponent_inventory_items',
    'daily_bonus_lookup', 'current_goal_values', 'collected_goals',
    'current_item_pattern_values', 'collected_item_pattern',
    'collected_optical_changes', 'missed_duels', 'missed_league_fights',
    'missed_hideout_attacks', 'new_guild_log_entries',
    'character_guild_inventory', 'completed_story_dungeon_steps',
    'dungeon_hardmode_emblems', 'season_progress',
];

return function (array $params): array {
    $userId = (int)($params['existing_user_id'] ?? $params['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new GameError('errLoginInvalidSession');
    }

    // Boot só é permitido com a sessão correta desta conta (emitida no login/registro).
    // No-op p/ usuário sem sessão no banco ou com HZ_STRICT_SESSION=0 (debug).
    Session::assertMatches($params);

    // Rotaciona a sessão (como no jogo oficial): este boot adota a sessão NOVA
    // (o cliente lê user.session_id da resposta); qualquer outro navegador na
    // mesma conta cai no próximo poll com errLoginInvalidSession.
    Session::rotate($userId);

    $data = Live::withLocalConstants(Replay::data('autoLoginUser'));   // template de boot (config global) + server_time

    // templates de tipo (antes de qualquer esvaziamento) p/ tipar o que vem do banco.
    $tplInv   = $data['inventory'] ?? [];
    $tplItem  = $data['items'][0]  ?? [];
    $tplQuest = $data['quests'][0] ?? [];
    $tplTraining = $data['trainings'][0] ?? [];

    try {
        $char = Character::loadByUser($userId);

        // Resolver batalha de guilda pendente (modelo lazy) ANTES dos overlays
        $preGuildId = (int)\HeroZero\Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$char->id()]);
        if ($preGuildId > 0 && GuildBattles::resolveDueDungeonBattle($preGuildId)) {
            $char = Character::load($char->id());
        }

        // Contador diario de lutas de liga: sync LAZY tambem no boot (este ficheiro
        // nao passa pelo accountState). O cliente NAO olha datas
        // (get_leagueFightCountLeftToday = maxDaily - leagueFightCount no bundle) --
        // ecoar o contador de ontem deixava o botao de ataque desativado com
        // "atingiu o numero maximo" mesmo apos virar o dia.
        $leagueRow = \HeroZero\Db::row(
            'SELECT id, league_fight_count, ts_last_league_fight FROM `character` WHERE id = ?',
            [$char->id()]
        );
        if ($leagueRow !== null && \HeroZero\League::syncDailyCount($leagueRow) !== $leagueRow) {
            $char = Character::load($char->id()); // contador zerado em BD — reler para os overlays
        }

        $char->syncDailyReset();
        \HeroZero\Casino::syncDaily($char->id());
        // Criar previews antes de montar items: o cliente resolve recompensas
        // de equipamento por ID já na primeira abertura das chamadas.
        $data['herobook_objectives'] = \HeroZero\Herobook::data($char->id());

        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
        if (isset($data['user']))      $data['user']      = $char->overlayUser($data['user']);
        $data = \HeroZero\UserVouchers::attach($data, $char->id());

        // estado real do banco (conta nova = vazio; skelletonx = semeado).
        $data['bank_inventory'] = \HeroZero\BankInventory::data($char->id());
        $data['inventory'] = $char->inventoryData($tplInv);
        $data['items']     = $char->itemsData($tplItem);
        // O boot usa um caminho próprio: incluir também as gemas aplicadas,
        // senão get_itemImprovement() devolve null ao renderizar equipamento.
        $data['item_improvements'] = \HeroZero\ItemImprovement::data($char->id());
        $data['quests']    = $char->questsData($tplQuest);
        $data['trainings'] = [$tplTraining];
        // sidekicks TEM tabela (sidekicks): DOs completos do personagem — o
        // cliente monta character._sidekicks a partir desta chave no boot.
        $data['sidekicks'] = \HeroZero\Sidekick::ownedByCharacter($char->id());
        $data = Live::attachTrainingState($data, $char, 0, true);
        $data = Live::attachEventQuestState($data);
        $data = Live::attachTreasureEventState($data, $char);
        $guild = Live::guildForUser($userId);
        if ($guild !== null) {
            $gid = (int)$guild['id'];
            $data['guild'] = Live::shapeGuild($guild);
            $data['guild_members'] = Live::guildMembers($gid);
            $data['sync_states'] = ['guild' . $gid => \HeroZero\GuildChat::version($gid)];
            $data = Live::attachGuildBattleState($data, $guild, $char);
        }

        // demais estruturas de conta ainda sem tabela: zeradas preservando a shape.
        foreach (ACCOUNT_STATE_KEYS as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = Character::emptyLike($data[$k]);
            }
        }

        // missed_duels/missed_league_fights TEM tabela (lado do defensor): o
        // esvaziamento acima zera as chaves do template; repor os valores REAIS,
        // senao o cliente nunca mostra o painel de duelos perdidos e as
        // character_b_rewards (honra do defensor) ficam presas para sempre.
        foreach (Live::missedCounters($char->id()) as $k => $v) {
            $data[$k] = $v;
        }

        // SIDEKICK GOALS: os unicos contadores vivos das metas — repor por cima
        // do esvaziado acima (merge parcial; o resto fica a zero).
        if (array_key_exists('current_goal_values', $data)) {
            $data['current_goal_values'] = array_merge(
                $data['current_goal_values'],
                \HeroZero\Sidekick::goalValues($char->id())
            );
        }

        // battles TEM tabela: o cliente no boot itera quests de luta e procura
        // cada fight_battle_id no array `battles` (plural). Se `battles` for []
        // mas a quest tiver fight_battle_id, o lookup falha e getInt crashava.
        $pendingBattles = [];
        foreach ($data['quests'] as $q) {
            $fbid = (int)($q['fight_battle_id'] ?? 0);
            if ((int)($q['status'] ?? 0) >= 3 && $fbid > 0) {
                $b = $char->battleData($fbid);
                if ($b !== null) $pendingBattles[] = $b;
            }
        }
        if ($pendingBattles !== []) {
            $data['battles'] = $pendingBattles;
            $data['battle'] = $pendingBattles[0];
        }

        // Depois do esvaziamento: progresso real da dungeon de historia
        // (completed_story_dungeon_steps esta em ACCOUNT_STATE_KEYS).
        $data = Live::attachStoryDungeonState($data, $char);
        $data = \HeroZero\Worldboss::attach($data, $char->id());
        $data = \HeroZero\HeroCon::attach($data, $char->id());
        $data = \HeroZero\Hideout::attachState($data, $char->id());

        // season_progress TEM tabela: devolver progresso real (cria se necessário).
        // active_season_progress_id no character DEVE bater com season_progress.id
        // — o cliente anula _seasonProgress quando não batem, o que impede o botão
        // da season de aparecer. O template traz um id antigo (33011) que nunca bate.
        $seasonProgress = \HeroZero\Season::progressForCharacter($char->id());
        $data['season_progress'] = $seasonProgress;
        // O painel desenha-se antes de openSeasonPanel responder. Pré-carregar
        // os estados evita cadeados falsos na primeira abertura após o login.
        $data['season_rewards'] = \HeroZero\Season::rewardsForCharacter($char->id());
        if (isset($data['character'])) {
            $data['character']['active_season_progress_id'] = $seasonProgress['id'];
        }
        // `season` (string) é a estação do ano usada pelo cliente para skins de UI
        // (spine dos backgrounds das cidades). NÃO é o identifier da season pass.
        // Estações: spring (Mar-Mai), summer (Jun-Ago), autumn (Set-Nov), winter (Dez-Fev).
        $month = (int)date('n');
        $yearSeason = match(true) {
            $month >= 3 && $month <= 5  => 'spring',
            $month >= 6 && $month <= 8  => 'summer',
            $month >= 9 && $month <= 11 => 'autumn',
            default                     => 'winter',
        };
        $data['season'] = $yearSeason;

        // O cliente de optical changes assume que available_chests decodifica para
        // array e chama .length nele. String vazia vira null e quebra a loja de donuts.
        if (array_key_exists('current_optical_changes', $data) && is_array($data['current_optical_changes'])) {
            $data['current_optical_changes'] = Character::emptyLike($data['current_optical_changes']);
            $data['current_optical_changes']['id'] = $char->id();
            $data['current_optical_changes']['character_id'] = $char->id();
            $data['current_optical_changes']['available_chests'] = '[]';
            $data['current_optical_changes']['active_options'] = '[]';
            $data['current_optical_changes']['unlocked_options'] = '{}';
            $data['current_optical_changes']['use_for_character'] = false;
            $data['current_optical_changes']['use_for_quest'] = false;
            $data['current_optical_changes']['use_for_duel'] = false;
            $data['current_optical_changes']['use_for_league'] = false;
        }

        // Garante snapshot do personagem no torneio ativo (cria o torneio se necessario)
        // e actualiza o timestamp de fim no payload de boot para o cliente mostrar o timer.
        // stat_base_* acumula todos os pontos gastos (gratis, pagos e treino)
        Tournament::ensureCharacterSnapshot($char);
        $data = array_replace($data, Tournament::timestamps());
        $char = Character::loadByUser($userId);
        $data['character'] = $char->overlayCharacter($data['character']);

        // OBS: nao omitimos `quests` vazio. Toda conta tem quests iniciais reais
        // (Character::seedStarterQuests), entao a lista nunca e vazia e o cliente monta
        // `_quests` corretamente. quests=[] quebraria o boot; quests ausente deixaria
        // `_quests` null e quebraria o painel de Quests. A shape valida e ter >=1 quest.
    } catch (GameError $e) {
        // usuario ainda nao existe no banco -> template puro (nao trava debug).
    }
    $data['bonus_info'] = \HeroZero\BonusEvents::payload();
    return isset($char) ? \HeroZero\DailyMilestones::attachAll(\HeroZero\Casino::attach(\HeroZero\DailyLogin::attach($data, $char->id()), $char->id()), $char->id()) : $data;
};
