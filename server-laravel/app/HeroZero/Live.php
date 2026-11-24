<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Helpers para transformar capturas/HAR em contrato de resposta, sem servir estado
 * estatico de outra conta. O template define a shape; o banco define os valores vivos.
 */
final class Live
{
    public static function template(string $action): array
    {
        if (Replay::has($action)) {
            return self::withLocalConstants(self::timeKeys(Replay::data($action)));
        }

        $files = glob(__DIR__ . '/../../../tools/capture/*_' . $action . '.json') ?: [];
        sort($files, SORT_NATURAL);
        // Nem toda captura tem a resposta gravada (HAR truncado): usa a PRIMEIRA
        // com response.data nao-vazio; um template vazio faz o overlay devolver
        // objetos rasos e o cliente crasha em campo obrigatorio ausente.
        foreach ($files as $file) {
            $raw = json_decode((string)file_get_contents($file), true);
            $data = $raw['response']['data'] ?? null;
            if (is_array($data) && $data !== []) {
                return self::withLocalConstants(self::timeKeys($data));
            }
        }
        return self::withLocalConstants(self::timeKeys([]));
    }

    public static function withLocalConstants(array $data): array
    {
        if (!isset($data['constants']) || !is_array($data['constants'])) {
            $data['constants'] = [];
        }
        $boosters = self::localBoosters();
        $data['constants']['boosters'] = $boosters;
        // Alguns getters do client (ex.: Va.get_activeQuestBoosterAmount/
        // get_activeDuelBoosterAmount, usados pelo painel quest_progress) resolvem
        // o id ativo via Si.fromId(), cujo CONSTANTS_KEY e "guild_boosters" -- um
        // catalogo SEPARADO de "boosters" (Xd.fromId, usado pelos getters de
        // stats/work/league). Mesmo id (ex. booster_quest2) gravado pelo
        // buyBooster.php so existe em "boosters", entao esses getters especificos
        // leem null e crasham em get_amount() (mesma classe de bug do
        // [[booster-catalog-null-crash]]). Espelhamos o catalogo inteiro tambem em
        // guild_boosters pra qualquer id que emitirmos resolver nos dois lugares.
        $data['constants']['guild_boosters'] = $boosters;
        // Atribuicao direta (e não +=): os templates trazem estas chaves a null
        // e += nao sobrescreve chaves existentes.
        $data['constants']['tournament_rewards'] = Tournament::CLIENT_TOURNAMENT_REWARDS;
        $data['constants']['global_tournament_rewards'] = Tournament::CLIENT_GLOBAL_TOURNAMENT_REWARDS;
        // LIGAS: constantes oficiais + tabela league_divisions (13 divisoes) direto do
        // constants_json.data — os templates trazem league_divisions a null e NENHUMA
        // chave league_*, o que deixava o limite diario a 0, divisoes "unknown" e o
        // preview ELO quebrado. Mesmo padrao dos tournament_rewards acima.
        foreach (League::officialConstants() as $k => $v) {
            if ($v !== null && !array_key_exists($k, $data['constants'])) {
                $data['constants'][$k] = $v;
            }
        }
        if (!array_key_exists('league_divisions', $data['constants'])) {
            $data['constants']['league_divisions'] = League::divisions();
        }
        // SIDEKICKS: escalares sidekick_* + secoes sidekick_levels/sidekick_skills/
        // sidekick_data/battle_effects direto do constants_json.data — os templates
        // trazem as secoes a null e NENHUM escalar, e o cliente crasha
        // ("Trying to retrieve unknown constant!") ao abrir qualquer UI de
        // sidekicks. Atribuicao direta (mesmo padrao dos tournament_rewards).
        Sidekick::injectConstants($data['constants']);
        foreach (Herobook::constants() as $key => $value) $data['constants'][$key] = $value;
        foreach (HeroCon::constants() as $key => $value) $data['constants'][$key] = $value;
        // Valores do CDN (50/recarga, 200/dia) são os correctos — não sobrepor.
        // SEASON PASS: constante mínima para gj.exists(identifier) retornar true
        // e o botão da season ser renderizado. O cliente usa o identifier do
        // season_progress para fazer lookup aqui -- sem esta entrada o botão
        // nunca aparece independentemente do estado do progresso.
        if (!array_key_exists('seasons', $data['constants']) || $data['constants']['seasons'] === null) {
            $data['constants']['seasons'] = Season::constantsData();
        }
        $data['constants'] = array_replace($data['constants'], Casino::constants());
        // Remover constants null para não sobrescrever dados do CDN
        foreach ($data['constants'] as $k => $v) {
            if ($v === null) unset($data['constants'][$k]);
        }
        return $data;
    }

    /**
     * Catalogo OFICIAL de boosters (constants_json.data do CDN, chave "boosters"):
     * identifier => [type, amount, amount2, duration => [premium(bool), cost]].
     * Fonte unica dos constants servidos E da cobranca do buyBooster — o dialogo
     * do client oferece exatamente estas duracoes; duracao fora do catalogo era
     * o nosso convite a errRequestInvalidParameter (fatal no client).
     */
    public const BOOSTER_CATALOG = [
        'booster_quest1' => [1, 10, 0, [172800 => [false, 206]]],
        'booster_quest2' => [1, 25, 0, [345600 => [false, 2057]]],
        'booster_quest3' => [1, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'booster_stats1' => [2, 10, 0, [172800 => [false, 206]]],
        'booster_stats2' => [2, 25, 0, [345600 => [false, 2057]]],
        'booster_stats3' => [2, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'booster_work1' => [3, 10, 0, [172800 => [false, 206]]],
        'booster_work2' => [3, 25, 0, [345600 => [false, 2057]]],
        'booster_work3' => [3, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'sense_booster' => [4, 1, 0, [3600 => [true, 2], 7200 => [true, 4], 21600 => [true, 12]]],
        'booster_league1' => [5, 60, 0, [345600 => [false, 2057]]],
        'booster_league2' => [5, 120, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'multitasking_booster' => [6, 1, 0, [604800 => [true, 9], -1 => [true, 99]]],
        'training_sense_booster' => [7, 2, 3, [3600 => [true, 5], 7200 => [true, 9], 21600 => [true, 19], 1209600 => [true, 99]]],
        'booster_hideout1' => [8, 25, 0, [345600 => [true, 5]]],
        'booster_hideout2' => [8, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
    ];

    /** Catalogo OFICIAL de guild boosters (constants "guild_boosters", shape igual). */
    public const GUILD_BOOSTER_CATALOG = [
        'guild_booster_training1' => [1, 4, 0, [604800 => [false, 30000]]],
        'guild_booster_training2' => [1, 7, 0, [604800 => [true, 69], 1209600 => [true, 130], 2592000 => [true, 265]]],
        'guild_booster_quest1' => [2, 10, 0, [604800 => [false, 30000]]],
        'guild_booster_quest2' => [2, 20, 0, [604800 => [true, 69], 1209600 => [true, 130], 2592000 => [true, 265]]],
        'guild_booster_duel1' => [3, 10, 0, [604800 => [false, 30000]]],
        'guild_booster_duel2' => [3, 20, 0, [604800 => [true, 69], 1209600 => [true, 130], 2592000 => [true, 265]]],
    ];

    /**
     * Resolve custo de um booster: [premium(bool), custo] ou null se o par
     * id/duracao nao esta a venda.
     */
    public static function boosterCost(string $id, int $duration): ?array
    {
        $entry = self::BOOSTER_CATALOG[$id] ?? self::GUILD_BOOSTER_CATALOG[$id] ?? null;
        return $entry === null ? null : ($entry[3][$duration] ?? null);
    }

    /**
     * Monta a shape de constants a partir dos catalogos oficiais acima.
     * Os DOIS catalogos vao nas DUAS chaves (boosters e guild_boosters) porque o
     * client resolve ids por catalogos separados (Xd.fromId "boosters", Si.fromId
     * "guild_boosters") e qualquer id ativo sem entrada quebra tooltip/painel
     * (ver [[booster-catalog-null-crash]]).
     */
    private static function localBoosters(): array
    {
        $out = [];
        foreach (self::BOOSTER_CATALOG + self::GUILD_BOOSTER_CATALOG as $id => [$type, $amount, $amount2, $durations]) {
            $durMap = [];
            foreach ($durations as $dur => [$premium, $cost]) {
                $durMap[$dur] = ['boosterDuration' => $dur, 'premium' => $premium, 'cost' => $cost];
            }
            $out[$id] = [
                'identifier' => $id,
                'type' => $type,
                'amount' => $amount,
                'amount2' => $amount2,
                'default_duration' => array_key_first($durations),
                'duration' => $durMap,
            ];
        }
        return $out;
    }

    public static function timeKeys(array $data): array
    {
        $data = array_replace($data, Tournament::calendarTimestamps());
        $data['server_time'] = GameTime::now();
        $data['time_correction'] = 0;
        return $data;
    }

    /**
     * Fuso do RELÓGIO DE JOGO (slots de batalha de guilda, limites diarios,
     * etc.). O cliente constroi a hora do servidor como
     *   utcLocalTimestampOffset + server_timestamp_offset + timestampCorrection
     * e formata "hoje/amanha/HH:MM" no fuso do BROWSER -- com o valor capturado
     * do oficial (-10800 = UTC-3, Brasil) tudo ficava desfasado 4h para
     * utilizadores em Portugal: os slots marcavam para o dia errado e mostravam
     * hora diferente da escolhida. Com S = offset de Lisboa, utcLocal(-3600 em
     * WEST) + S(+3600) = 0 -> a hora do servidor coincide com a hora de Lisboa.
     */
    public static function gameTimezone(): \DateTimeZone
    {
        return new \DateTimeZone((string)(getenv('HZ_GAME_TZ') ?: 'Europe/Lisbon'));
    }

    /** Agora no relógio de jogo (baseado no uptime do servidor). */
    public static function gameNow(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . GameTime::now()))
            ->setTimezone(self::gameTimezone());
    }

    /**
     * Valor a enviar ao cliente em `server_timestamp_offset`: o offset (em
     * segundos) do fuso de jogo. O cliente soma-o ao utcLocalTimestampOffset
     * (que em UTC+1 vale -3600), logo o par tem de anular-se.
     */
    public static function gameTimestampOffset(): int
    {
        return (int)(self::gameNow()->getOffset());
    }

    public static function overlayAccount(array $data, int $userId): array
    {
        try {
            $char = Character::loadByUser($userId);
        } catch (GameError $e) {
            return $data;
        }
        if (isset($data['user']) && is_array($data['user'])) {
            $data['user'] = $char->overlayUser($data['user']);
        }
        if (isset($data['character']) && is_array($data['character'])) {
            $data['character'] = $char->overlayCharacter($data['character']);
        }
        if (isset($data['inventory']) && is_array($data['inventory'])) {
            $data['inventory'] = $char->inventoryData($data['inventory']);
        }
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = $char->itemsData($data['items'][0] ?? []);
        }
        $data['item_improvements'] = ItemImprovement::data($char->id());
        if (isset($data['quests']) && is_array($data['quests'])) {
            $data['quests'] = $char->questsData($data['quests'][0] ?? []);
        }
        $singularQuestId = (int)($data['_training_singular_quest_id'] ?? 0);
        unset($data['_training_singular_quest_id']);
        if (isset($data['trainings']) && is_array($data['trainings'])) {
            $data = self::attachTrainingState($data, $char, $singularQuestId);
        } elseif (array_key_exists('training_quests', $data)) {
            // template com training_quests de outra conta sem trainings: ids
            // estranhos no mapa do cliente (ou merge em get_training() null).
            unset($data['training_quests']);
        }
        return DailyMilestones::attachAll(UserVouchers::attach($data, $char->id()), $char->id());
    }

    /**
     * Anexa o bloco de treino CONSISTENTE (`trainings` + `training_quests`).
     * O cliente html5_257 recria o mapa de trainings com quests VAZIOS sempre que
     * `trainings` vem numa resposta (refreshTrainings) e so repopula os quests se
     * `training_quests` vier na MESMA resposta. `trainings` sem `training_quests`
     * com treino ativo deixa o pool apontando para quests inexistentes e o proximo
     * refresh do painel training_progress crasha (h[pool_id] -> undefined ->
     * jF.refresh: "reading 'get_isFightQuest' of undefined"). Inversamente,
     * `training_quests` sem treino ativo crasha o merge (get_training() null),
     * por isso a chave e removida quando nao ha treino.
     */
    public static function attachTrainingState(array $data, Character $char, int $singularQuestId = 0, bool $forcePlural = false): array
    {
        $tpl  = $data['trainings'][0] ?? (Replay::data('autoLoginUser')['trainings'][0] ?? []);
        $rows = $char->trainingsData(is_array($tpl) ? $tpl : []);

        $active = null;
        foreach ($rows as $row) {
            if ((int)($row['status'] ?? 0) === 2) { $active = $row; break; }
        }

        // Com treino ATIVO responde-se com `training` (SINGULAR), como o servidor
        // oficial faz em quase todas as actions (syncGame, getGuildLog, claim*, ...).
        // No cliente:
        //   refreshTrainings (plural)   -> this._trainings = new Ec  => APAGA os quests
        //   refreshTraining  (singular) -> createOrUpdateMap(...)    => atualiza no sitio
        // Mandar o plural em toda a resposta limpava a lista de quests a cada
        // syncGame; o loop que liga o `training_battle` (procura fightBattleId nos
        // trainingQuests) ficava sem nada, a batalha nunca era anexada e os labels
        // do painel ficavam no texto por defeito do layout ("LOC"). Ver BUG-TR-012.
        if (!$forcePlural && $active !== null) {
            if ($singularQuestId > 0) {
                // O cliente le training_quest_id em get_activeTrainingQuest().
                $active['training_quest_id'] = $singularQuestId;
            }
            $data['training'] = $active;
            unset($data['trainings']);

            // Modo batalha: NUNCA reenviar os quests — refreshTrainingQuests cria
            // objectos novos e quebra a referencia que o painel ja tem (BUG-TR-011).
            if ($singularQuestId > 0) {
                unset($data['training_quests']);
                return $data;
            }
            $quests = $char->trainingQuestsData();
            if ($quests !== []) {
                $data['training_quests'] = $quests;
            } else {
                unset($data['training_quests']);
            }
            return $data;
        }

        // Plural: boot (o mapa do cliente esta vazio) e refresh do pool de ofertas.
        $data['trainings'] = $rows;
        $quests = $char->trainingQuestsData();
        if ($quests !== []) {
            $data['training_quests'] = $quests;
        } else {
            unset($data['training_quests']);
        }
        return $data;
    }

    /** Substitui o evento da fixture por convite/progresso real da conta. */
    public static function attachEventQuestState(array $data): array
    {
        $cid=(int)($data['character']['id'] ?? 0);
        return $cid>0 ? EventQuests::attach($data,$cid) : $data;
    }

    /** Convite global/progresso real da Caça às Toupeiras; exclui fixtures antigas. */
    public static function attachTreasureEventState(array $data, Character $char): array
    {
        return TowerEvents::attach(EventTreasures::attach($data, $char->id()), $char->id());
    }

    /**
     * Dungeon de historia: devolve completed_story_dungeon_steps reais e, se ha
     * passo ativo, story_dungeon_lookup + story_dungeon_step (shape da captura).
     * No boot, chamar DEPOIS do esvaziamento de ACCOUNT_STATE_KEYS (que zera
     * completed_story_dungeon_steps) para o valor real prevalecer.
     */
    public static function attachStoryDungeonState(array $data, Character $char): array
    {
        $state = StoryDungeon::ensure($char->id());
        $data['completed_story_dungeon_steps'] = array_values($state['completed']);
        $active = $state['active'] ?? null;
        if (isset($data['character'])) $data['character']['active_story_dungeon_attack_id'] = (int)($active['battle_id'] ?? 0);
        if (is_array($active)) {
            $index = (int)$active['index'];
            $step = (int)$active['step'];
            $stepId = $char->storyDungeonStepId($index, $step);
            $data['story_dungeon_lookup'] = [
                'id' => $char->id(),
                'story_dungeon_step_ids' => json_encode([$stepId]),
                'story_dungeon_steps' => json_encode([(string)$step => 1]),
            ];
            $data['story_dungeon_step'] = [
                'id' => $stepId,
                'character_id' => $char->id(),
                'story_dungeon_index' => $index,
                'step_index' => $step,
                'status' => (int)$active['status'],
                'repeat' => false,
                'points_collected' => (int)($active['points'] ?? 0),
                'ts_complete' => (int)($active['ts_complete'] ?? 0),
                'ts_last_attack' => (int)($active['ts_last_attack'] ?? 0),
                'battle_ids' => empty($active['battle_ids']) ? '' : json_encode($active['battle_ids']),
                'rewards' => json_encode($active['rewards'] ?? []),
            ];
            $boot = self::template('autoLoginUser');
            // O cliente resolve reward.item antes de desenhar o diálogo, inclusive no login.
            $itemId = (int)($active['rewards']['item'] ?? 0);
            if ($itemId > 0 && !in_array($itemId, array_map('intval', array_column($data['items'] ?? [], 'id')), true)) {
                $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [$itemId, $char->id()]);
                if ($item) $data['items'][] = self::shapeLike($boot['items'][0] ?? [], $item);
            }
            $battleId = (int)($active['battle_id'] ?? 0);
            if ($battleId > 0) {
                $battle = Db::row('SELECT * FROM battle WHERE id = ?', [$battleId]);
                if ($battle) {
                    // Coleção no login e objeto singular nas ações; fundir sem apagar outras lutas.
                    $battle = self::shapeLike($boot['battle'] ?? [], $battle);
                    if (empty($data['battle']['id'])) $data['battle'] = $battle;
                    $data['battles'] = array_values(array_filter($data['battles'] ?? [],
                        static fn(array $row): bool => (int)$row['id'] !== $battleId));
                    $data['battles'][] = $battle;
                }
            }
        }
        return $data;
    }

    public static function currentUserId(array $params): int
    {
        return (int)($params['existing_user_id'] ?? $params['user_id'] ?? 0);
    }

    public static function accountState(int $userId, array $data = []): array
    {
        $data = array_replace($data, Tournament::timestamps());
        $char = Character::loadByUser($userId);
        $boot = self::template('autoLoginUser');

        // Resolucao de batalhas de guilda fora de hora TEM de correr ANTES dos
        // overlays: simula a luta (modelo oficial lazy) e grava o apontador
        // finished_guild_dungeon_battle_id no personagem — se corresse depois,
        // o bloco `character` saia com o valor velho (objeto ja carregado).
        $preGuildId = (int)Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$char->id()]);
        if ($preGuildId > 0 && GuildBattles::resolveDueDungeonBattle($preGuildId)) {
            $char = Character::load($char->id());
        }

        // ADRENALINA de liga: sincronizacao LAZY em toda resposta (mesmo chokepoint
        // do ensureSeason la em baixo). O cliente le o maximo da barra direto do
        // campo character.max_league_stamina (ep.get_maxLeagueStamina) — sem isto,
        // comprar um energetico devolvia o maximo VELHO (20) e a barra so mudava
        // quando comecasse uma luta de liga.
        $leagueRow = Db::row(
            'SELECT id, guild_id, league_stamina, max_league_stamina, ts_last_league_stamina_change,
                    active_league_booster_id, ts_active_league_boost_expires,
                    league_fight_count, ts_last_league_fight
               FROM `character` WHERE id = ?',
            [$char->id()]
        );
        // Contador diario NA MESMA passagem: o cliente NAO olha datas para este
        // limite (bundle: get_leagueFightCountLeftToday = maxDaily - leagueFightCount;
        // ao contrario de washItem/shopRefresh, que usam isToday(ts)). Ecoar o
        // contador de ONTEM deixava o botao de ataque da liga desativado com
        // "atingiu o numero maximo" mesmo depois de virar o dia.
        if ($leagueRow !== null
            && League::syncDailyCount(League::syncStamina($leagueRow)) !== $leagueRow) {
            $char = Character::load($char->id()); // sync gravou em BD — reler para os overlays
        }

        $char->syncDailyReset();
        Casino::syncDaily($char->id());
        $data['user'] = $char->overlayUser($data['user'] ?? $boot['user'] ?? []);
        $data['character'] = $char->overlayCharacter($data['character'] ?? $boot['character'] ?? []);
        // SEASON PASS: progresso em toda resposta (cliente valida active_season_progress_id em
        // cada updateData para invalidar o cache de seasonProgress se o id não bater).
        $seasonProgress = Season::progressForCharacter($char->id());
        $data['season_progress'] = $seasonProgress;
        $data['season_rewards'] = Season::rewardsForCharacter($char->id());
        $data['herobook_objectives'] = Herobook::data($char->id());
        $data = UserVouchers::attach($data, $char->id());
        $data['character']['active_season_progress_id'] = $seasonProgress['id'];
        $data['bank_inventory'] = BankInventory::data($char->id());
        $data['inventory'] = $char->inventoryData($data['inventory'] ?? $boot['inventory'] ?? []);
        $data['items'] = $char->itemsData(($data['items'][0] ?? null) ?: ($boot['items'][0] ?? []));
        $data['item_improvements'] = ItemImprovement::data($char->id());
        $data['quests'] = $char->questsData(($data['quests'][0] ?? null) ?: ($boot['quests'][0] ?? []));
        $data['trainings'] = [($data['trainings'][0] ?? null) ?: ($boot['trainings'][0] ?? [])];
        // SIDEKICKS: lista do personagem em toda resposta (o cliente funde por id
        // via refreshSidekicks; sem a chave o painel nasce vazio no primeiro boot).
        $data['sidekicks'] = Sidekick::ownedByCharacter($char->id());
        // SIDEKICK GOALS: unicos contadores vivos do painel de metas. Parcial de
        // proposito — o cliente faz merge por chave em refreshGoalValues().
        $data['current_goal_values'] = Sidekick::goalValues($char->id());
        // O client so chama refreshNews() (que instancia character._news) quando a
        // resposta tem hasData("news"); sem isso _news fica null pra sempre e
        // qualquer refreshTitleBar (get_news().get_hasUnreadNews()) crasha.
        $data['news'] = $data['news'] ?? $boot['news'] ?? [];
        $singularQuestId = (int)($data['_training_singular_quest_id'] ?? 0);
        unset($data['_training_singular_quest_id']);
        $data = self::attachTrainingState($data, $char, $singularQuestId);
        $data = self::attachEventQuestState($data);
        $data = self::attachTreasureEventState($data, $char);
        $data = self::attachStoryDungeonState($data, $char);
        $data = \HeroZero\Worldboss::attach($data, $char->id());
        // HIDEOUT: estado completo em toda resposta (o cliente funde por campo;
        // sem hideout vai so missed_hideout_attacks=0, como no boot oficial).
        $data = Hideout::attachState($data, $char->id());

        $guild = self::guildForUser($userId);
        if ($guild !== null) {
            $gid = (int)$guild['id'];

            $data['guild'] = self::shapeGuild($guild);
            $data['guild_members'] = self::guildMembers($gid);
            // Quando a versao muda, o cliente dispara getGuildLog no proximo poll
            // (e assim o chat chega aos outros membros).
            $data['sync_states'] = ['guild' . (int)$guild['id'] => GuildChat::version((int)$guild['id'])];

            $data = self::attachGuildBattleState($data, $guild, $char);
        }

        $optical = Character::emptyLike($boot['current_optical_changes'] ?? []);
        $optical['id'] = $char->id();
        $optical['character_id'] = $char->id();
        $optical['available_chests'] = '[]';
        $optical['active_options'] = '[]';
        $optical['unlocked_options'] = '{}';
        $optical['use_for_character'] = false;
        $optical['use_for_quest'] = false;
        $optical['use_for_duel'] = false;
        $optical['use_for_league'] = false;
        $data['current_optical_changes'] = $optical;

        // Duelo em curso: o cliente precisa de `duel` + `battle` + `opponent` para
        // retomar o painel de batalha depois de um sync/reload. get_activeDuel()
        // compara _duel.get_id() com character.active_duel_id -- sem o bloco `duel`
        // o _duel fica null, o duelo "some" e o dialogo de recompensa nunca abre.
        // `battles[]` NUNCA pode acompanhar: o cliente prefere
        // getBattleFromId(battles, battle_id) e devolve null (o id nao existe no
        // array do template) -> refreshBattle(null) -> get_profileB() undefined.
        // Duelos/lutas de liga PENDENTES no lado do defensor ("missed duels"): o
        // cliente so mostra a bolha/notificacao e o painel de duelos perdidos se
        // estes contadores forem > 0. Sem eles as character_b_rewards nunca eram
        // reclamadas (claimMissedDuelsRewards) e a honra do defensor ficava
        // congelada -- ganhava/perdia sem mexer.
        foreach (self::missedCounters($char->id()) as $k => $v) {
            $data[$k] = $v;
        }

        $activeDuelId = (int)($data['character']['active_duel_id'] ?? 0);
        if ($activeDuelId > 0) {
            $duelRow = Db::row('SELECT * FROM `duel` WHERE id = ? AND character_a_id = ?', [$activeDuelId, $char->id()]);
            if ($duelRow !== null) {
                $data['duel'] = self::shapeLike($boot['duel'] ?? [], $duelRow);
                $battleRow = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$duelRow['battle_id']]);
                if ($battleRow !== null) {
                    $data['battle'] = self::shapeLike($boot['battle'] ?? [], $battleRow);
                }
                $data['opponent'] = self::requestedCharacter((int)$duelRow['character_b_id'], $boot['opponent'] ?? []);
                unset($data['battles']);
            }
        }

        // EPOCA da liga: o template traz o timestamp FIXO da captura oficial (data
        // passada). Servimos o fim da epoca real (proxima segunda-feira no relogio
        // de jogo) e rodamos a epoca aqui se ja expirou — o accountState e o choke
        // point de praticamente todas as respostas, logo o rolamento corre no
        // primeiro acesso apos o fim de semana sem precisar de cron.
        if (array_key_exists('league_season_end_timestamp', $boot)) {
            $data['league_season_end_timestamp'] = League::ensureSeason();
            $data['league_locked'] = false;
        }

        $data['server_time'] = GameTime::now();
        $data['time_correction'] = 0;
        $data['bonus_info'] = BonusEvents::payload();
        $data = EventTreasures::attach(EventQuests::attach(DailyMilestones::attachAll(Casino::attach(DailyLogin::attach($data, $char->id()), $char->id()), $char->id()), $char->id()), $char->id());
        return TowerEvents::attach(HeroCon::attach(EventDraws::attach($data, $char->id()), $char->id()), $char->id());
    }

    /**
     * Contadores de duelos/lutas de liga perdidas pendentes do DEFENSOR
     * (character_b_status IN (1,2)). Usados no accountState e no boot.
     *
     * @return array{missed_duels:int, missed_league_fights:int}
     */
    public static function missedCounters(int $characterId): array
    {
        return [
            'missed_duels' => (int)Db::value(
                'SELECT COUNT(*) FROM `duel` WHERE character_b_id = ? AND character_b_status IN (1,2)',
                [$characterId]
            ),
            'missed_league_fights' => (int)Db::value(
                'SELECT COUNT(*) FROM `league_fight` WHERE character_b_id = ? AND character_b_status IN (1,2)',
                [$characterId]
            ),
        ];
    }

    public static function guildForUser(int $userId): ?array
    {
        return Db::row(
            'SELECT g.* FROM `guild` g
              JOIN `character` c ON c.guild_id = g.id
             WHERE c.user_id = ?
             LIMIT 1',
            [$userId]
        );
    }

    public static function shapeGuild(array $guild): array
    {
        foreach ($guild as $key => $value) {
            if (is_numeric($value) && $key !== 'name' && $key !== 'description' && $key !== 'note' && $key !== 'forum_page' && !str_ends_with($key, '_id')) {
                $guild[$key] = (int)$value;
            }
        }
        $guild['id'] = (int)$guild['id'];
        $guild['initiator_character_id'] = (int)$guild['initiator_character_id'];
        $guild['leader_character_id'] = (int)$guild['leader_character_id'];
        $guild['accept_members'] = (bool)$guild['accept_members'];
        $guild['auto_joins'] = (int)$guild['auto_joins'];
        $guild['use_missiles_attack'] = (bool)$guild['use_missiles_attack'];
        $guild['use_missiles_defense'] = (bool)$guild['use_missiles_defense'];
        $guild['use_missiles_dungeon'] = (bool)$guild['use_missiles_dungeon'];
        $guild['use_auto_joins_attack'] = (bool)$guild['use_auto_joins_attack'];
        $guild['use_auto_joins_defense'] = (bool)$guild['use_auto_joins_defense'];
        $guild['use_auto_joins_dungeon'] = (bool)$guild['use_auto_joins_dungeon'];
        $guild['locale'] = $guild['locale'] ?? 'pt_BR';
        $guild['ts_last_synergy_calculation'] = (int)($guild['ts_last_synergy_calculation'] ?? 0);
        $guild['ts_next_synergy_calculation'] = (int)($guild['ts_next_synergy_calculation'] ?? 0);
        // The HTML5 client reads this complete application/tactics block whenever
        // the guild panel opens, even when no recruitment rules were configured.
        $guild['pending_leader_vote_id'] = (int)($guild['pending_leader_vote_id'] ?? 0);
        $guild['min_apply_level'] = (int)($guild['min_apply_level'] ?? 0);
        $guild['min_apply_honor'] = (int)($guild['min_apply_honor'] ?? 0);
        $guild['min_apply_hideout_level'] = (int)($guild['min_apply_hideout_level'] ?? 0);
        $guild['min_apply_gem_level'] = (int)($guild['min_apply_gem_level'] ?? 0);
        $guild['apply_open'] = (bool)($guild['apply_open'] ?? true);
        $guild['apply_open_mail'] = (bool)($guild['apply_open_mail'] ?? true);
        $guild['guild_battle_tactics_attack_order'] = (int)($guild['guild_battle_tactics_attack_order'] ?? 0);
        $guild['guild_battle_tactics_attack_tactic'] = (int)($guild['guild_battle_tactics_attack_tactic'] ?? 0);
        $guild['guild_battle_tactics_defense_order'] = (int)($guild['guild_battle_tactics_defense_order'] ?? 0);
        $guild['guild_battle_tactics_defense_tactic'] = (int)($guild['guild_battle_tactics_defense_tactic'] ?? 0);
        $guild['officer_note'] = (string)($guild['officer_note'] ?? '');
        $guild['pending_guild_battle_attack_id'] = (int)($guild['pending_guild_battle_attack_id'] ?? 0);
        $guild['pending_guild_battle_defense_id'] = (int)($guild['pending_guild_battle_defense_id'] ?? 0);
        $guild['pending_guild_dungeon_battle_attack_id'] = (int)($guild['pending_guild_dungeon_battle_attack_id'] ?? 0);
        $guild['battles_fought'] = (int)($guild['battles_fought'] ?? 0);
        $guild['unlocked_rewards'] = (string)($guild['unlocked_rewards'] ?? '[]');
        $guild['guild_competition_reward_boost_factor'] = (int)($guild['guild_competition_reward_boost_factor'] ?? 0);
        $guild['ts_guild_competition_reward_boost_expires'] = (int)($guild['ts_guild_competition_reward_boost_expires'] ?? 0);
        $guild['guildbook_objectives_renewed_today'] = (int)($guild['guildbook_objectives_renewed_today'] ?? 0);
        $guild['synergy_energy'] = (int)($guild['synergy_energy'] ?? 0);
        $guild['synergy_energy_storage'] = (int)($guild['synergy_energy_storage'] ?? 0);
        $guild['synergy_energy_storage_temp'] = (int)($guild['synergy_energy_storage_temp'] ?? 0);
        $guild['synergy_energy_storage_max'] = (int)($guild['synergy_energy_storage_max'] ?? 0);
        $guild['active_synergy_energy'] = (int)($guild['active_synergy_energy'] ?? 0);
        $guild['synergy_upgrades'] = (string)($guild['synergy_upgrades'] ?? '{}');
        // Pontos de stat da guilda são DERIVADOS do honor (não um pool armazenado):
        // total ganho = honor / HONOR_PER_STAT_POINT; disponível = total - já gastos.
        // Sem isto, toda guilda ficava travada em 0 (improveGuildStat sempre falhava).
        $guild['stat_points_available'] = self::guildStatPointsAvailable($guild);

        // Contadores de batalha calculados AO VIVO a partir das batalhas resolvidas:
        // as colunas da tabela `guild` nunca são incrementadas e as ESTATÍSTICAS da
        // team (DialogGuildStatistics: battles_attacked/defended/won/lost,
        // dungeon_battles_fought/won) ficavam todas a 0, apesar do historico
        // (getGuildBattleHistoryFights) mostrar as lutas. Mesmo critério de "won"
        // do historico: dungeon paga moedas.
        $gid = (int)$guild['id'];
        $attacked = (int)Db::value('SELECT COUNT(*) FROM `guild_battle` WHERE status = 3 AND guild_attacker_id = ?', [$gid]);
        $defended = (int)Db::value('SELECT COUNT(*) FROM `guild_battle` WHERE status = 3 AND guild_defender_id = ?', [$gid]);
        $won      = (int)Db::value('SELECT COUNT(*) FROM `guild_battle` WHERE status = 3 AND guild_winner_id = ?', [$gid]);
        $dungeonFought = (int)Db::value('SELECT COUNT(*) FROM `guild_dungeon_battle` WHERE status = 3 AND guild_id = ?', [$gid]);
        $dungeonWon    = (int)Db::value("SELECT COUNT(*) FROM `guild_dungeon_battle` WHERE status = 3 AND guild_id = ? AND JSON_VALID(rewards) AND JSON_EXTRACT(rewards, '$.game_currency') > 0", [$gid]);
        $guild['battles_attacked']  = $attacked;
        $guild['battles_defended']  = $defended;
        $guild['battles_fought']    = $attacked + $defended;
        $guild['battles_won']       = $won;
        $guild['battles_lost']      = ($attacked + $defended) - $won;
        $guild['dungeon_battles_fought'] = $dungeonFought;
        $guild['dungeon_battles_won']    = $dungeonWon;
        // Trofeus: current/max ao vivo (mesma filosofia dos contadores acima) —
        // max = slots desbloqueados pela expansao, para o dialogo "atuais/max".
        // won/lost ficam das colunas, mantidas por GuildArtifacts nas transferencias.
        $guild['artifacts_owned_current'] = count(\HeroZero\GuildArtifacts::ownedIds($guild));
        $guild['artifacts_owned_max']     = \HeroZero\GuildArtifacts::unlockedSlots($guild);
        return $guild;
    }

    /** Valores-base das 4 stats de guilda (estado de uma guilda recém-criada). */
    public const GUILD_STAT_BASES = [
        'stat_guild_capacity'                   => 10,
        'stat_character_base_stats_boost'       => 1,
        'stat_quest_xp_reward_boost'            => 1,
        'stat_quest_game_currency_reward_boost' => 1,
    ];

    /** Honor necessário por ponto de stat de guilda (curva simples, até termos a da CDN). */
    public const GUILD_HONOR_PER_STAT_POINT = 1000;

    /** Total de pontos que a guilda já ganhou, a partir do seu honor acumulado. */
    public static function guildStatPointsFromHonor(int $honor): int
    {
        return intdiv(max(0, $honor), self::GUILD_HONOR_PER_STAT_POINT);
    }

    /** Pontos já gastos = soma dos incrementos acima do valor-base de cada stat. */
    public static function guildStatPointsSpent(array $guild): int
    {
        $spent = 0;
        foreach (self::GUILD_STAT_BASES as $col => $base) {
            $spent += max(0, (int)($guild[$col] ?? $base) - $base);
        }
        return $spent;
    }

    /** Pontos disponíveis para gastar agora = ganhos (do honor) - já gastos. */
    public static function guildStatPointsAvailable(array $guild): int
    {
        $total = self::guildStatPointsFromHonor((int)($guild['honor'] ?? 0));
        return max(0, $total - self::guildStatPointsSpent($guild));
    }

    public static function guildMembers(int $guildId): array
    {
        $heroconCycle = HeroCon::sync();
        $rows = Db::rows(
            'SELECT id, user_id, name, gender, level, guild_rank, ts_guild_joined,
                    ts_last_action, guild_donated_game_currency, guild_donated_premium_currency,
                    stat_base_stamina, stat_trained_stamina,
                    stat_base_strength, stat_trained_strength,
                    stat_base_critical_rating, stat_trained_critical_rating,
                    stat_base_dodge_rating, stat_trained_dodge_rating
               FROM `character`
              WHERE guild_id = ?
              ORDER BY guild_rank DESC, level DESC, id ASC',
            [$guildId]
        );
        $members = [];
        foreach ($rows as $row) {
            // Totais de atributos REAIS (base + treino + equipamento + bonus de
            // booster/guilda), os mesmos que o perfil mostra. Antes era so
            // base+treino e as MEDIAS de habs nas estatisticas da team (que o
            // cliente calcula somando estes campos e dividindo pelos membros,
            // Va.get_averageStrength etc.) ficavam muito abaixo do real.
            $profile = Character::load((int)$row['id'])->battleProfile('a');
            $herocon = $heroconCycle ? HeroCon::contribution((int)$heroconCycle['id'], (int)$row['id'], $guildId) : ['score'=>0,'fraction'=>0];
            $members[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'server_id' => 'local',
                'name' => (string)$row['name'],
                'gender' => (string)$row['gender'],
                'level' => (int)$row['level'],
                'guild_rank' => (int)$row['guild_rank'],
                'ts_guild_joined' => (int)$row['ts_guild_joined'],
                'ts_last_online' => (int)$row['ts_last_action'],
                'last_action' => (int)floor((GameTime::now() - (int)$row['ts_last_action']) / 60),
                'online_status' => (GameTime::now() - (int)$row['ts_last_action']) < 300 ? 1 : 2,
                'game_currency_donation' => (int)$row['guild_donated_game_currency'],
                'premium_currency_donation' => (int)$row['guild_donated_premium_currency'],
                'guild_competition_points_gathered' => $herocon['score'],
                'guild_competition_point_fractions_gathered' => $herocon['fraction'],
                'stat_total_stamina' => (int)$profile['stamina'],
                'stat_total_strength' => (int)$profile['strength'],
                'stat_total_critical_rating' => (int)$profile['criticalrating'],
                'stat_total_dodge_rating' => (int)$profile['dodgerating'],
                'auto_joins' => 0,
                'use_auto_joins_attack' => true,
                'use_auto_joins_defense' => true,
                'use_auto_joins_dungeon' => true,
                'missiles' => 0,
                'use_missiles_attack' => true,
                'use_missiles_defense' => true,
                'use_missiles_dungeon' => true,
                'medikits' => 0,
                'use_medikits_attack' => false,
                'use_medikits_defense' => false,
                'use_medikits_dungeon' => false,
                'weapon_oil' => 0,
                'use_weapon_oil_attack' => false,
                'use_weapon_oil_defense' => false,
                'use_weapon_oil_dungeon' => false,
                'hideout_rooms' => [],
            ];
        }
        return $members;
    }

    public static function leaderboardCharacters(array $tpl, string $sort = 'honor', int $limit = 0): array
    {
        $sortSql = match ($sort) {
            'level' => 'c.`level` DESC, c.`xp` DESC',
            'league' => 'c.`league_points` DESC, c.`honor` DESC',
            default => 'c.`honor` DESC, c.`level` DESC',
        };
        if ($limit <= 0) {
            $limit = (int)(Db::value("SELECT COUNT(*) FROM `character` WHERE user_id <> 0 AND name <> ''") ?? 0);
        }
        $rows = Db::rows(
            "SELECT c.*, COALESCE(g.`name`, '') AS leaderboard_guild_name,
                    g.`emblem_background_shape`, g.`emblem_background_color`,
                    g.`emblem_background_border_color`, g.`emblem_icon_shape`,
                    g.`emblem_icon_color`, g.`emblem_icon_size`
               FROM `character` c
               LEFT JOIN `guild` g ON g.`id` = c.`guild_id` AND g.`status` = 1
              WHERE c.`user_id` <> 0 AND c.`name` <> ''
              ORDER BY {$sortSql}, c.`id` LIMIT " . (int)$limit
        );
        $out = [];
        $rank = 1;
        foreach ($rows as $r) {
            $value = $sort === 'level' ? (int)$r['level'] : ($sort === 'league' ? (int)$r['league_points'] : (int)$r['honor']);
            $item = [
                'server_id' => 'local',
                'rank' => $rank,
                'r' => $rank,
                'id' => (int)$r['id'],
                'character_id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'locale' => 'pt_BR',
                'guild_id' => (int)$r['guild_id'],
                'guild_name' => (string)$r['leaderboard_guild_name'],
                'emblem_background_shape' => (int)($r['emblem_background_shape'] ?? 0),
                'emblem_background_color' => (int)($r['emblem_background_color'] ?? 0),
                'emblem_background_border_color' => (int)($r['emblem_background_border_color'] ?? 0),
                'emblem_icon_shape' => (int)($r['emblem_icon_shape'] ?? 0),
                'emblem_icon_color' => (int)($r['emblem_icon_color'] ?? 0),
                'emblem_icon_size' => (int)($r['emblem_icon_size'] ?? 0),
                'gender' => (string)$r['gender'],
                'level' => (int)$r['level'],
                'league_points' => (int)$r['league_points'],
                'league_group_id' => (int)$r['league_group_id'],
                'honor' => (int)$r['honor'],
                'value' => $value,
                // Verde de "online": mesmo criterio do guildMembers (ativo nos
                // ultimos 5 min no RELOGIO DE JOGO). Hardcoded 2 = sempre offline.
                'online_status' => (GameTime::now() - (int)($r['ts_last_action'] ?? 0)) < 300 ? 1 : 2,
                'ts_last_online' => (int)($r['ts_last_action'] ?? 0),
                'last_action' => (int)floor((GameTime::now() - (int)($r['ts_last_action'] ?? 0)) / 60),
                'hideout_level' => 1,
                'hideout_points' => 0,
                'dungeon_hardmode_emblems' => 0,
                'attacked_count' => 0,
                'max_attack_count' => 3,
            ];
            $out[] = self::shapeLike($tpl, $item);
            $rank++;
        }
        return $out;
    }

    /**
     * Sorteia 3 oponentes de liga entre contas REAIS, preferindo quem esta NA
     * MESMA divisao e com nivel proximo (antes era RAND() global puro — alvos
     * sem sentido). Persiste a lista em character.league_opponents (+ ts do
     * refresh) e devolve a shape do cliente. Fonte unica usada pelo
     * refreshLeagueOpponents/startLeagueFight/enterLeagueDivision — a lista
     * salva no char e a ecoada na resposta nunca divergem.
     */
    public static function leagueOpponents(int $cid): array
    {
        $me = Db::row('SELECT level, league_group_id FROM `character` WHERE id = ?', [$cid]) ?? [];
        $level = max(1, (int)($me['level'] ?? 1));
        $group = (int)($me['league_group_id'] ?? 0);
        $myDiv = League::divisionFromGroup($group);

        $ids = [];
        $exclude = [$cid];

        $pickOne = static function (int $div) use (&$ids, &$exclude, $cid): bool {
            $groupId = $div * League::GROUP_FACTOR + 1;
            $row = Db::row(
                'SELECT id FROM `character`
                  WHERE user_id <> 0 AND id <> ? AND league_group_id = ?
                    AND id NOT IN (' . implode(',', $exclude ?: [0]) . ')
                  ORDER BY RAND() LIMIT 1',
                [$cid, $groupId]
            );
            if ($row) {
                $id = (int)$row['id'];
                $ids[] = $id;
                $exclude[] = $id;
                return true;
            }
            return false;
        };

        // 3 oponentes: 90% mesma divisão, 10% divisão abaixo
        for ($i = 0; $i < 3 && $myDiv > 0; $i++) {
            $roll = mt_rand(1, 100);
            if ($roll <= 90 || $myDiv <= 1) {
                if (!$pickOne($myDiv) && $myDiv > 1) {
                    $pickOne($myDiv - 1);
                }
            } else {
                if (!$pickOne($myDiv - 1)) {
                    $pickOne($myDiv);
                }
            }
        }

        $ids = array_slice($ids, 0, 3);

        // So persiste a LISTA: ts_last_league_opponents_refresh e do botao manual
        // (refreshLeagueOpponents) — rerolls automaticos (nova luta, entrada na
        // liga) NAO podem queimar o refresh gratis diario.
        // Formato JSON ARRAY ("[1,2,3]"), nao "1,2,3": get_leagueOpponentIds do
        // cliente faz JSON.parse da coluna — string sem colchetes devolve lista
        // vazia -> canDoLeagueFights=false -> painel fica em "nao inscrito" para
        // sempre e o botao Ingressar "nao faz nada".
        Db::exec('UPDATE `character` SET league_opponents = ? WHERE id = ?',
            ['[' . implode(',', $ids) . ']', $cid]);
        return self::renderLeagueOpponents($ids);
    }

    /**
     * Ids da lista de oponentes PERSISTIDA (so os que ainda existem e nao sao o
     * proprio). Formato na coluna: JSON ARRAY "[1,2,3]" (o cliente faz JSON.parse);
     * aceita tambem o legado "1,2,3" por seguranca.
     */
    public static function savedLeagueOpponentIds(int $cid): array
    {
        $raw = (string)(Db::value('SELECT league_opponents FROM `character` WHERE id = ?', [$cid]) ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        $parts = is_array($decoded) ? $decoded : explode(',', $raw);
        $out = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id <= 0 || $id === $cid || in_array($id, $out, true)) {
                continue;
            }
            $exists = (int)Db::value('SELECT COUNT(*) FROM `character` WHERE id = ? AND user_id <> 0', [$id]);
            if ($exists === 1) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Shape { opponent, opponent_inventory, opponent_inventory_items } dos ids
     * dados. Template raso ([]) = opponent sem name/gender/level -> tela da liga
     * com cards vazios e risco de crash (mesmo aviso do getCharacter.php).
     */
    public static function renderLeagueOpponents(array $ids): array
    {
        $boot = self::template('autoLoginUser');
        $tplChar = $boot['character'] ?? [];
        $tplInv  = $boot['inventory'] ?? [];
        $tplItem = $boot['items'][0] ?? [];
        $list = [];
        foreach ($ids as $oid) {
            $list[] = [
                'opponent' => self::requestedCharacter((int)$oid, $tplChar),
                'opponent_inventory' => self::inventoryForCharacter((int)$oid, $tplInv),
                'opponent_inventory_items' => self::itemsForCharacter((int)$oid, $tplItem),
            ];
        }
        return $list;
    }

    public static function requestedCharacter(int $characterId, array $tplChar): array
    {
        $char = Character::load($characterId);
        $data = $char->overlayCharacter($tplChar);
        $data['server_id'] = 'local';
        $data['online_status'] = (GameTime::now() - (int)($char->field('ts_last_action') ?? 0)) < 300 ? 1 : 2;
        return $data;
    }

    public static function inventoryForCharacter(int $characterId, array $tplInv): array
    {
        return Character::load($characterId)->inventoryData($tplInv);
    }

    public static function itemsForCharacter(int $characterId, array $tplItem): array
    {
        return Character::load($characterId)->itemsData($tplItem);
    }

    public static function shapeLike(array $tpl, array $values): array
    {
        $out = $tpl;
        foreach ($values as $k => $v) {
            if (array_key_exists($k, $out)) {
                $out[$k] = self::castLike($out[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function castLike($like, $val)
    {
        if (is_bool($like)) return (bool)$val;
        if (is_int($like)) return (int)$val;
        if (is_float($like)) return (float)$val;
        return $val === null ? '' : (string)$val;
    }

    public static function attachGuildBattleState(array $data, array $guild, Character $char): array
    {
        $gid = (int)$guild['id'];

        $pendingDungeonId = (int)($guild['pending_guild_dungeon_battle_attack_id'] ?? 0);
        if ($pendingDungeonId > 0) {
            $pb = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id=? AND guild_id=? AND status=2', [$pendingDungeonId, $gid]);
            if ($pb !== null) {
                $pSettings = json_decode((string)($pb['settings'] ?? '{}'), true) ?? [];
                $data['pending_guild_dungeon_battle'] = [
                    'id'                          => (int)$pb['id'],
                    'battle_time'                 => (int)$pb['battle_time'],
                    'ts_attack'                   => (int)$pb['ts_attack'],
                    'guild_id'                    => $gid,
                    'npc_team_identifier'         => (string)$pb['npc_team_identifier'],
                    // STRING JSON: o cliente faz fromJsonString/JSON.parse sobre
                    // este campo — array rebenta com SyntaxError no cliente.
                    'npc_team_character_profiles' => GuildBattles::dungeonPreviewProfiles($pb),
                    'status'                      => (int)$pb['status'],
                    'settings'                    => json_encode($pSettings),
                    // STRING JSON: o cliente faz fromJsonString/JSON.parse
                    'character_ids'               => json_decode((string)($pb['character_ids'] ?? '[]'), true) ?? [],
                ];
            }
        }

        // --- guilda-vs-guilda: resolve as que chegaram à hora (lazy, como nas gangs)
        GuildBattles::resolveDueGuildBattles();

        $pendingAttackId = (int)($guild['pending_guild_battle_attack_id'] ?? 0);
        if ($pendingAttackId > 0) {
            $pb = Db::row('SELECT * FROM `guild_battle` WHERE id=? AND status=1', [$pendingAttackId]);
            if ($pb !== null) {
                $data['pending_guild_battle_attack'] = self::guildBattleShape($pb, true);
                // O cliente resolve get_attacker()/get_defender() a partir desta
                // lista — sem ela o jogo crasha no login
                // ("get_pendingDefenseGuildBattle().get_attacker() is undefined").
                $opp = Db::row('SELECT * FROM `guild` WHERE id = ?', [(int)$pb['guild_defender_id']]);
                if ($opp !== null) {
                    $data['guild_battle_guilds'][] = self::shapeGuild($opp);
                }
            }
        }

        $pendingDefenseId = (int)($guild['pending_guild_battle_defense_id'] ?? 0);
        if ($pendingDefenseId > 0) {
            $pb = Db::row('SELECT * FROM `guild_battle` WHERE id=? AND status=1', [$pendingDefenseId]);
            if ($pb !== null) {
                $data['pending_guild_battle_defense'] = self::guildBattleShape($pb, false);
                $opp = Db::row('SELECT * FROM `guild` WHERE id = ?', [(int)$pb['guild_attacker_id']]);
                if ($opp !== null) {
                    $data['guild_battle_guilds'][] = self::shapeGuild($opp);
                }
            }
        }

        // Batalhas terminadas com recompensa por levantar:
        // type 1 = membro do ATACANTE → finished_guild_battle_attack
        // type 2 = membro do DEFENSOR → finished_guild_battle_defense
        // A recompensa TEM de ser da MESMA batalha apontada em
        // character.finished_guild_battle_*_id — o cliente so abre o diálogo
        // se os ids coincidirem (senao fica em loop de sync sem mostrar nada).
        foreach ([[1, 'finished_guild_battle_attack', 'finished_guild_battle_attack_id'], [2, 'finished_guild_battle_defense', 'finished_guild_battle_defense_id']] as [$rtype, $key, $charField]) {
            $refId = (int)($char->field($charField) ?? 0);
            if ($refId <= 0) continue;
            $rw = Db::row(
                'SELECT * FROM `guild_battle_rewards` WHERE character_id = ? AND type = ? AND guild_battle_id = ? AND status = 1 LIMIT 1',
                [$char->id(), $rtype, $refId]
            );
            if ($rw === null) continue;
            $fb = Db::row('SELECT * FROM `guild_battle` WHERE id = ? AND status = 3', [$refId]);
            if ($fb === null) continue;
            // Walkover: batalha sem rondas (nenhum defensor registado).
            // Processa o prémio silenciosamente em vez de mostrar o diálogo de batalha.
            if (($fb['rounds'] ?? '[]') === '[]') {
                $coins = (int)$rw['game_currency'];
                if ($coins > 0) {
                    Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$coins, $char->id()]);
                }
                Db::exec('DELETE FROM `guild_battle_rewards` WHERE id = ?', [(int)$rw['id']]);
                Db::exec("UPDATE `character` SET {$charField} = 0 WHERE id = ?", [$char->id()]);
                continue;
            }
            $shape = self::guildBattleShape($fb, $rtype === 1);
            $shape['rounds'] = (string)($fb['rounds'] ?? '');
            $shape['attacker_character_profiles'] = \HeroZero\GuildBattles::clientProfiles((string)($fb['attacker_character_profiles'] ?? ''));
            $shape['defender_character_profiles'] = \HeroZero\GuildBattles::clientProfiles((string)($fb['defender_character_profiles'] ?? ''));
            $shape['guild_winner_id'] = (int)($fb['guild_winner_id'] ?? 0);
            $shape['attacker_rewards'] = (string)($fb['attacker_rewards'] ?? '');
            $shape['defender_rewards'] = (string)($fb['defender_rewards'] ?? '');
            $data[$key] = $shape;
            // Recompensas por levantar (DOGuildBattleReward: o cliente usa esta
            // lista para o prémio individual do diálogo de resultado).
            $data['guild_battle_rewards'] = [[
                'id'              => (int)$rw['id'],
                'guild_battle_id' => (int)$rw['guild_battle_id'],
                'character_id'    => (int)$rw['character_id'],
                'game_currency'   => (int)$rw['game_currency'],
                'item_id'         => (int)$rw['item_id'],
                'status'          => (int)$rw['status'],
                'type'            => (int)$rw['type'],
            ]];
            // Guilda adversária para o cliente resolver nomes/emblemas.
            $oppId = $rtype === 1 ? (int)$fb['guild_defender_id'] : (int)$fb['guild_attacker_id'];
            $opp = Db::row('SELECT * FROM `guild` WHERE id = ?', [$oppId]);
            if ($opp !== null) {
                $data['guild_battle_guilds'][] = self::shapeGuild($opp);
            }
        }

        $myReward = Db::row(
            'SELECT * FROM `guild_battle_rewards` WHERE character_id = ? AND type = 3 AND status = 1 LIMIT 1',
            [$char->id()]
        );
        if ($myReward !== null) {
            $fb = Db::row('SELECT * FROM `guild_dungeon_battle` WHERE id = ?', [(int)$myReward['guild_battle_id']]);
            if ($fb !== null) {
                $data['finished_guild_dungeon_battle'] = [
                    'id'                          => (int)$fb['id'],
                    'guild_id'                    => (int)$fb['guild_id'],
                    'battle_time'                 => (int)$fb['battle_time'],
                    'ts_attack'                   => (int)$fb['ts_attack'],
                    'npc_team_identifier'         => (string)$fb['npc_team_identifier'],
                    'status'                      => (int)$fb['status'],
                    'settings'                    => (string)$fb['settings'],
                    'character_ids'               => (string)($fb['character_ids'] ?? '[]'),
                    'joined_character_profiles'   => (string)($fb['joined_character_profiles'] ?? '[]'),
                    'npc_team_character_profiles' => (string)($fb['npc_team_character_profiles'] ?? '[]'),
                    'rounds'                      => (string)($fb['rounds'] ?? ''),
                    'rewards'                     => (string)($fb['rewards'] ?? ''),
                ];
                $data['guild_dungeon_battle_rewards'] = [[
                    'id'                     => (int)$myReward['id'],
                    'guild_dungeon_battle_id'=> (int)$myReward['guild_battle_id'],
                    'character_id'           => (int)$myReward['character_id'],
                    // Chaves obrigatorias do DOGuildDungeonBattleReward — se
                    // faltarem, getInt("xp") lanca "unknown field" no cliente.
                    'game_currency'          => (int)$myReward['game_currency'],
                    'xp'                     => (int)($myReward['xp'] ?? 0),
                    'item_id'                => (int)$myReward['item_id'],
                    'training'               => 0,
                    'energy'                 => 0,
                    'status'                 => (int)$myReward['status'],
                    'type'                   => (int)$myReward['type'],
                ]];
            }
        }

        return $data;
    }
    /**
     * Shape de uma linha guild_battle para o DOGuildBattle do cliente.
     * Pendente (status=1): campos limitados + character_ids (STRING JSON),
     * igual a getDataForAttacker/Defender do servidor antigo. Terminada:
     * todos os campos.
     */
    private static function guildBattleShape(array $b, bool $attackerView): array
    {
        $idsKey = $attackerView ? 'attacker_character_ids' : 'defender_character_ids';
        $base = [
            'id'                  => (int)$b['id'],
            'status'              => (int)$b['status'],
            'ts_attack'           => (int)$b['ts_attack'],
            'battle_time'         => (int)$b['battle_time'],
            'guild_attacker_id'   => (int)$b['guild_attacker_id'],
            'guild_defender_id'   => (int)$b['guild_defender_id'],
            // STRING JSON — o cliente faz getFromJson(getString("character_ids"))
            'character_ids'       => (string)($b[$idsKey] ?? '[]'),
        ];
        if ((int)$b['status'] !== 1) {
            $base['guild_winner_id']              = (int)($b['guild_winner_id'] ?? 0);
            $base['attacker_character_profiles']  = \HeroZero\GuildBattles::clientProfiles((string)($b['attacker_character_profiles'] ?? ''));
            $base['defender_character_profiles']  = \HeroZero\GuildBattles::clientProfiles((string)($b['defender_character_profiles'] ?? ''));
            $base['rounds']                       = (string)($b['rounds'] ?? '');
            $base['attacker_rewards']             = (string)($b['attacker_rewards'] ?? '');
            $base['defender_rewards']             = (string)($b['defender_rewards'] ?? '');
        }
        return $base;
    }
}
