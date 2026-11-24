<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Modelo do personagem apoiado no banco (tabela `character` + `user`).
 *
 * Estrategia "DB + template": as respostas do jogo tem dezenas de campos, muitos
 * constantes/derivados. Em vez de reconstruir tudo, carregamos o objeto capturado
 * como TEMPLATE e sobrescrevemos ("overlay") apenas os campos que o nosso banco
 * controla e que mudam (moeda, atributos, energias, honra...). Assim a resposta
 * fica completa e ao mesmo tempo reflete o estado real e persistente do banco.
 */
final class Character
{
    /** stat_type do cliente -> sufixo da coluna (apenas os 4 base; tiers usam trainingStatType()). */
    public const STAT_MAP = [
        1 => 'stamina',
        2 => 'strength',
        3 => 'critical_rating',
        4 => 'dodge_rating',
    ];

    /**
     * Custo de treino (unidades): valor aleatorio de 1 a 9 por slot.
     * Formulas confirmadas do servidor oficial (HAR lv=15-17):
     *   duration      = cost * 300  (segundos)
     *   needed_energy = cost * 50 + 300 + (cost >= 4 ? 50 : 0)
     * Exemplos: cost=1 → 5min/350e, cost=3 → 15min/450e, cost=9 → 45min/800e.
     */
    private const STATS = ['stamina', 'strength', 'critical_rating', 'dodge_rating'];
    private const ITEM_COLUMNS = [
        'identifier', 'type', 'quality', 'required_level', 'charges', 'item_level',
        'ts_availability_start', 'ts_availability_end', 'premium_item', 'buy_price',
        'sell_price', 'stat_stamina', 'stat_strength', 'stat_critical_rating',
        'stat_dodge_rating', 'stat_weapon_damage',
    ];
    private const SHOP_SLOTS = [
        'shop_item1_id', 'shop_item2_id', 'shop_item3_id', 'shop_item4_id', 'shop_item5_id',
        'shop_item6_id', 'shop_item7_id', 'shop_item8_id', 'shop_item9_id',
        'shop2_item1_id', 'shop2_item2_id', 'shop2_item3_id', 'shop2_item4_id', 'shop2_item5_id',
        'shop2_item6_id', 'shop2_item7_id', 'shop2_item8_id', 'shop2_item9_id',
    ];
    private const SHOP1_SLOTS = [
        'shop_item1_id', 'shop_item2_id', 'shop_item3_id', 'shop_item4_id', 'shop_item5_id',
        'shop_item6_id', 'shop_item7_id', 'shop_item8_id', 'shop_item9_id',
    ];
    private const SHOP2_SLOTS = [
        'shop2_item1_id', 'shop2_item2_id', 'shop2_item3_id', 'shop2_item4_id', 'shop2_item5_id',
        'shop2_item6_id', 'shop2_item7_id', 'shop2_item8_id', 'shop2_item9_id',
    ];
    private const EQUIPMENT_SLOT_BY_TYPE = [
        1 => 'mask_item_id',
        2 => 'cape_item_id',
        3 => 'suit_item_id',
        4 => 'belt_item_id',
        5 => 'boots_item_id',
        6 => 'weapon_item_id',
        7 => 'gadget_item_id',
        8 => 'missiles_item_id',
    ];
    /**
     * `training_ratings` — copia EXATA de constants_json.data (o cliente le a mesma
     * tabela em nk/CTrainingRating). O progresso e uma PERCENTAGEM 0-100:
     *   get_progressValue() = floor(clamp(energy / needed_energy * 100, 0, 100))
     *   get_claimableStar() = maior `stars` cujo `needed_progress` ja foi atingido
     * As recompensas de cada estrela sao a recompensa BASE * reward_factor
     * (confirmado no HAR: coins 1335/3338/6676 -> racios 0.2/0.5/1.0 exatos).
     * `special_factor = 1` na 3.a estrela = premio extra (item).
     */
    private const TRAINING_RATINGS = [
        1 => ['needed_progress' => 10,  'reward_factor' => 0.2, 'special_factor' => 0],
        2 => ['needed_progress' => 40,  'reward_factor' => 0.5, 'special_factor' => 0],
        3 => ['needed_progress' => 100, 'reward_factor' => 1.0, 'special_factor' => 1],
    ];

    /**
     * Pontos do atributo TREINADO por estrela, escalados com o custo do treino.
     * Formula dos constants: stat_point_reward_base = cost × 0.25
     *   star1/2 = max(1, round(cost × 0.25))
     *   star3   = max(1, round(cost × 0.50))
     * Confirmado no HAR (herozone, cost=4): stat_points_star = 1/1/2.
     */
    private static function trainingStatPoints(int $cost, int $star): int
    {
        return match ($star) {
            1, 2    => max(1, (int)round($cost * 0.25)),
            3       => max(1, (int)round($cost * 0.50)),
            default => 1,
        };
    }

    /**
     * Moedas da recompensa de uma quest de treino.
     * Calibrado ao HAR (herozone.fun lv347, custo=4, needed_energy=550):
     *   rate = coins_per_time_scale × level^level_exp = 0.01 × level^1.55
     *   base_mult = needed_energy / 7.76  (~70.8 a needed_energy=550)
     *   base_coins = rate × base_mult        (~6132 a lv347, custo=4)
     * Tiers discretos × base, distribuídos por dificuldade:
     *   diff=0 (instant/fácil): [×1.0, ×1.25, ×1.5, ×2.0]
     *   diff=1 (médio):         [×1.25, ×1.5, ×2.0, ×2.5]
     *   diff=2 (difícil):       [×1.5, ×2.5, ×4.0, ×5.0]
     */
    private function trainingQuestCoins(int $level, int $neededEnergy, int $diff, int $seed): int
    {
        $levelRate = 0.01 * ($level ** 1.55);
        $baseMult  = $neededEnergy / 7.76;
        $tierSeed  = ($seed >> 16) & 0x3F;
        // diff: 0=instant(sem dificuldade), 1=fácil, 2=médio, 3=difícil (HAR confirma 1/2/3)
        $tiers = [
            0 => [1.0, 1.25, 1.5, 2.0],   // instant
            1 => [1.0, 1.25, 1.5, 2.0],   // fácil
            2 => [1.25, 1.5, 2.0, 2.5],   // médio
            3 => [1.5, 2.5, 4.0, 5.0],    // difícil
        ];
        $t = $tiers[min(3, max(0, $diff))];
        return (int)round($levelRate * $baseMult * $t[$tierSeed % count($t)]);
    }

    /**
     * XP da recompensa de uma quest de treino.
     * Rácio xp/coins observado no HAR: 0.80–1.15.
     */
    private static function trainingQuestXp(int $coins, int $seed): int
    {
        // quest_reward_xp_scale_balanced = 1 (constants) → xp ≈ coins
        // Ajuste servidor: ×0.5 (decisão do operador)
        $r = 0.80 + (($seed >> 22) & 0xFF) % 36 / 100.0;
        return (int)round($coins * $r * 0.5);
    }

    /**
     * bonus_roll (coluna TINYINT) <-> nome do bonus da 3.a estrela.
     * Tabela oficial `training_special_rewards`:
     *   type=1 chance=50 item=random_epic factor=9  -> 'item'      (item epico + 9x coins/xp)
     *   type=2 chance=1  item=null        factor=1  -> 'stat_point' (ponto de atributo livre)
     *   type=3 chance=34 item=null        factor=1  -> 'none'       (recompensa base)
     *   type=3 chance=15 item=null        factor=2  -> 'coins_2x'   (2x coins/xp)
     */
    private const BONUS_ROLL_NAMES = [0 => 'none', 1 => 'stat_point', 2 => 'item', 3 => 'coins_2x'];

    /**
     * Factor de moedas/XP da 3.a estrela por tipo de bonus (training_special_rewards.factor).
     *   item      -> 1x  (type=1, factor=9): o factor=9 da tabela e o "peso" do item,
     *                     nao um multiplicador de moedas — o item epico IS a recompensa.
     *   stat_point-> 1x  (type=2, factor=1)
     *   coins_2x  -> 2x  (type=3, factor=2)
     *   none      -> 1x  (type=3, factor=1)
     */
    private const BONUS_REWARD_FACTOR = ['item' => 1, 'stat_point' => 1, 'coins_2x' => 2, 'none' => 1];

    /**
     * Bonus extra da 3.a estrela — probabilidades da tabela oficial training_special_rewards:
     *   50% item epico (+ 9x coins/xp)
     *    1% ponto de atributo livre
     *   34% sem bonus extra
     *   15% 2x coins/xp
     *
     * Sorteado JA NA OFERTA (o painel mostra o icone antes de comecar) e PERSISTIDO
     * em `training.bonus_roll` no startTraining — a seed usa ts_last_training que
     * muda ao comecar, portanto sem persistir o sorteio mudava a meio.
     * md5 em vez de crc32: ids sequenciais com crc32 agrupam os valores.
     *
     * @return 'item'|'stat_point'|'coins_2x'|'none'
     */
    private function trainingOfferBonus(int $offerId): string
    {
        $seed = 'training_bonus|' . $offerId . '|' . (int)($this->row['ts_last_training'] ?? 0);
        $roll = (int)hexdec(substr(md5($seed), 0, 8)) % 100;
        if ($roll < 50) return 'item';        // 50% — type=1, factor=9
        if ($roll < 51) return 'stat_point';  //  1% — type=2, factor=1
        if ($roll < 85) return 'none';        // 34% — type=3, factor=1
        return 'coins_2x';                    // 15% — type=3, factor=2
    }

    /** Bonus persistido de um treino em curso. */
    private static function trainingBonusRoll(array $active): string
    {
        return self::BONUS_ROLL_NAMES[(int)($active['bonus_roll'] ?? 0)] ?? 'none';
    }

    /**
     * Poe um item numa slot livre da mochila (18 slots).
     *
     * Existir na tabela `items` com o character_id NAO chega: o jogador so tem o
     * item quando ele esta numa slot `bag_itemN_id` do `inventory`. Sem isto o
     * icone aparecia na recompensa (o cliente resolve o id em `items`) mas o item
     * nunca chegava a mochila.
     *
     * @return bool false se a mochila estiver cheia
     */
    private function putItemInBag(int $itemId): bool
    {
        if ($itemId <= 0) return false;
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($inv === null) return false;
        for ($i = 1; $i <= 18; $i++) {
            $f = 'bag_item' . $i . '_id';
            if (array_key_exists($f, $inv) && (int)$inv[$f] === $itemId) {
                return true;  // ja la esta (reclaim repetido)
            }
        }
        for ($i = 1; $i <= 18; $i++) {
            $f = 'bag_item' . $i . '_id';
            if (array_key_exists($f, $inv) && (int)$inv[$f] <= 0) {
                Db::exec("UPDATE `inventory` SET `$f` = ? WHERE character_id = ?", [$itemId, $this->id()]);
                return true;
            }
        }
        return false;
    }

    /**
     * Itens de recompensa da pool actual: [offerId => itemId].
     *
     * O cliente resolve o item da recompensa na coleccao do PROPRIO jogador
     * (`ec.get_item()` -> `character.getItemById(id)`), logo um id inventado nao
     * mostra icone nenhum. O oficial pre-gera os itens junto com a pool (ids
     * sequenciais 694162-694165 no HAR, ja no inventario antes de comecar).
     *
     * Regenerados quando ts_last_training muda (refresh ou arranque de treino). Os
     * da pool anterior sao apagados — excepto os que ficaram presos a um treino
     * (reward_item_id), que ja foram conquistados.
     *
     * @return array<int,int>
     */
    private function trainingPoolRewardItems(): array
    {
        $ts     = (int)($this->row['ts_last_training'] ?? 0);
        $stored = json_decode((string)($this->row['training_pool_items'] ?? ''), true);
        if (is_array($stored) && (int)($stored['ts'] ?? -1) === $ts && isset($stored['map'])) {
            return array_map('intval', (array)$stored['map']);
        }

        // Apaga os itens da pool anterior que ninguem chegou a ganhar.
        $old = is_array($stored) ? array_map('intval', (array)($stored['map'] ?? [])) : [];
        if ($old !== []) {
            $keep = Db::rows('SELECT reward_item_id FROM `training` WHERE character_id = ? AND reward_item_id > 0', [$this->id()]);
            $keepIds = array_map(static fn ($r) => (int)$r['reward_item_id'], $keep);
            // Nunca apagar um item que esteja EQUIPADO ou na mochila — deixaria a
            // slot do inventario a apontar para um item inexistente.
            $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
            if ($inv !== null) {
                foreach ($inv as $col => $val) {
                    if (str_ends_with((string)$col, '_item_id') || str_ends_with((string)$col, '_id')) {
                        $keepIds[] = (int)$val;
                    }
                }
            }
            foreach ($old as $itemId) {
                if ($itemId > 0 && !in_array($itemId, $keepIds, true)) {
                    Db::exec('DELETE FROM `items` WHERE id = ? AND character_id = ?', [$itemId, $this->id()]);
                }
            }
        }

        $map = [];
        foreach ($this->currentTrainingPool() as $offerId) {
            if ($this->trainingOfferBonus($offerId) === 'item') {
                $map[$offerId] = $this->insertItemFromTemplate($this->generateShopItem(false, 3));
            }
        }
        $json = json_encode(['ts' => $ts, 'map' => $map], JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET training_pool_items = ? WHERE id = ?', [$json, $this->id()]);
        $this->row['training_pool_items'] = $json;
        return $map;
    }

    /**
     * Recompensa de uma estrela = recompensa BASE do treino * reward_factor.
     * A base escala com o custo do treino (proxy do nivel/duracao) — o oficial
     * escala tambem com o nivel do heroi (formula ainda desconhecida).
     *
     * Todas as estrelas dao moedas, XP e pontos do atributo treinado (1/2/3).
     * A 3.a acrescenta o bonus sorteado em trainingBonusRoll().
     */
    private function trainingStarReward(int $cost, int $star, string $bonus = 'none', int $itemId = 0): array
    {
        $cfg          = self::TRAINING_RATINGS[$star] ?? self::TRAINING_RATINGS[3];
        $rewardFactor = (float)$cfg['reward_factor'];
        // Na 3.a estrela (special_factor=1), o bonus multiplica as moedas/XP pelo
        // factor da tabela training_special_rewards (coins_2x=2x, resto=1x).
        $specialFactor = ((int)$cfg['special_factor'] === 1)
            ? (self::BONUS_REWARD_FACTOR[$bonus] ?? 1)
            : 1;

        // Scaling por nivel: calibrado ao HAR oficial (lv 15-17 -> 6676 moedas na
        // 3.a estrela de custo 2, reward_factor=1.0 -> 22.25x a base de 150*custo).
        // Componente quadratica suave (level/5000) para que o ganho por nivel suba
        // gradualmente em vez de ser flat (lv1: +188/nível, lv800: +247/nível para custo=9 star3).
        $level       = max(1, (int)($this->row['level'] ?? 1));
        $levelFactor = max(1.0, $level * (22.25 / 16.0) * (1 + $level / 5000.0));

        $reward = [
            'coins' => (int)round(150 * $cost * $rewardFactor * $specialFactor * $levelFactor * 0.10),
            'xp'    => (int)round(50  * $cost * $rewardFactor * $specialFactor * $levelFactor * 0.10),
        ];
        if ((int)$cfg['special_factor'] === 1) {
            // `item` leva o ID REAL do item (como no oficial: "item":694163).
            if ($bonus === 'item')       $reward['item'] = $itemId > 0 ? $itemId : 1;
            if ($bonus === 'stat_point') $reward['stat_points'] = 1;
        }
        return $reward;
    }

    /** stat base 1-4 -> setting (textura/tooltip do cliente). */
    private const TRAINING_SETTINGS = [
        1 => 'stamina_indoor',
        2 => 'strength_indoor',
        3 => 'critical_rating_indoor',
        4 => 'dodge_rating_indoor',
    ];
    private const VALID_BOOSTERS = [
        'active_quest_booster_id' => ['booster_quest1', 'booster_quest2', 'booster_quest3'],
        'active_stats_booster_id' => ['booster_stats1', 'booster_stats2', 'booster_stats3'],
        'active_work_booster_id' => ['booster_work1', 'booster_work2', 'booster_work3'],
        'active_league_booster_id' => ['booster_league1', 'booster_league2'],
    ];
    private const BOOSTER_EXPIRES = [
        'active_quest_booster_id' => 'ts_active_quest_boost_expires',
        'active_stats_booster_id' => 'ts_active_stats_boost_expires',
        'active_work_booster_id' => 'ts_active_work_boost_expires',
        'active_league_booster_id' => 'ts_active_league_boost_expires',
    ];
    private const DEFAULT_APPEARANCE = [
        'm' => [
            'appearance_skin_color' => 1,
            'appearance_hair_color' => 1,
            'appearance_hair_type' => 51,
            'appearance_head_type' => 4,
            'appearance_eyes_type' => 8,
            'appearance_eyebrows_type' => 7,
            'appearance_nose_type' => 4,
            'appearance_mouth_type' => 1,
            'appearance_facial_hair_type' => 0,
            'appearance_decoration_type' => 0,
            'show_mask' => 1,
        ],
        'f' => [
            'appearance_skin_color' => 1,
            'appearance_hair_color' => 1,
            'appearance_hair_type' => 25,
            'appearance_head_type' => 5,
            'appearance_eyes_type' => 9,
            'appearance_eyebrows_type' => 5,
            'appearance_nose_type' => 4,
            'appearance_mouth_type' => 24,
            'appearance_facial_hair_type' => 0,
            'appearance_decoration_type' => 0,
            'show_mask' => 1,
        ],
    ];
    private const TUTORIAL_DONE_FLAGS = [
        'ts_prb_start' => -1,
        'shop2_football_generic' => true,
        'itemImprovementsUpdate' => true,
        'first_visit' => true,
        'mission_shown' => true,
        'first_mission_opened' => true,
        'first_mission_started' => true,
        'first_mission' => true,
        'stats_spent' => true,
        'shop_shown' => true,
        'first_item' => true,
        'duel_shown' => true,
        'first_duel' => true,
        'tutorial_finished' => true,
        'training_new' => true,
        'dungeons' => true,
    ];

    /**
     * Flags de PROGRESSO do tutorial do esconderijo (257). FORA da lista acima
     * de proposito: syncTutorialForLevel() marca TUTORIAL_DONE_FLAGS como feitas
     * ao nivel 3, e o tutorial do hideout so comeca no 8 (hideout_unlock_min_level)
     * — marca-las aqui rebentava a sequencia. O servidor deriva estas flags das
     * acoes (build/upgrade/collect), tal como as de missao.
     */
    private const HIDEOUT_TUTORIAL_FLAGS = [
        'hideout_first_building'        => true, // main building construido
        'hideout_build_stone'           => true,
        'hideout_build_glue'            => true,
        'hideout_build_attacker'        => true,
        'hideout_build_generator'       => true,
        'hideout_room_upgraded'         => true,
        'hideout_first_upgrade'         => true, // main building nivel 2+
        'hideout_first_collect'         => true,
        'hideout_collect_stone'         => true,
        'hideout_collect_attacker_units'=> true,
        // Conclusao do tutorial do esconderijo. `hideout_tutorial_completed` e a
        // flag que o CLIENTE le (get_hasHideoutTutorialCompleted) para libertar o
        // scroll entre pisos, os botoes de abortar e o resto das funcionalidades.
        'hideout_tutorial_finished'     => true,
        'hideout_tutorial_completed'    => true,
        // Flags de tutorial do painel do esconderijo que o cliente tambem grava.
        'hideout_tutorial_show_attacker'=> true,
        'hideout_build_attacker_units'  => true,
        'hideout_first_attack'          => true,
    ];

    private array $row;   // linha da tabela character
    private array $user;  // linha da tabela user

    private function __construct(array $row, array $user)
    {
        $this->row  = $row;
        $this->user = $user;
    }

    /** A atividade e os seus contadores partilham o mesmo lock e rollback. */
    private function withStoryActivity(callable $fn): void
    {
        $oldRow = $this->row;
        $oldUser = $this->user;
        try {
            StoryDungeon::transaction($this->id(), function (array $row) use ($fn): void {
                $this->row = $row;
                $this->equipBonusCache = null;
                $this->guildBoosts = null;
                $this->user = Db::row('SELECT * FROM `user` WHERE id = ?', [$this->userId()]) ?? [];
                $fn();
                $fresh = self::load($this->id());
                $this->row = $fresh->row;
                $this->user = $fresh->user;
            });
        } catch (\Throwable $e) {
            $this->row = $oldRow;
            $this->user = $oldUser;
            throw $e;
        }
    }

    /**
     * Cria um personagem NOVO e limpo (nivel 1, zerado) para uma conta recem-criada.
     * Sem boosters, sem itens, sem tutorial, energias cheias. Persiste e retorna.
     * Este e o caminho "conta do zero": nada aqui vem de captura/skelletonx.
     */
    public static function createNew(int $userId, string $name, string $gender = 'm'): self
    {
        Db::exec(
            "INSERT INTO `character`
                (user_id, name, gender, level, xp, honor, game_currency,
                 stat_base_stamina, stat_base_strength, stat_base_critical_rating, stat_base_dodge_rating,
                 quest_energy, max_quest_energy, current_quest_stage, max_quest_stage,
                 duel_stamina, max_duel_stamina,
                 training_count, max_training_count,
                 training_energy, max_training_energy, ts_last_training_energy_change,
                 league_stamina, max_league_stamina, league_stamina_cost,
                 active_quest_booster_id, active_stats_booster_id, active_work_booster_id, active_league_booster_id,
                 tutorial_flags, new_user_voucher_ids)
             VALUES
                -- init_honor = 100 nas constantes oficiais (nascia a 0, e com honra 0
                -- dos dois lados a formula de duelo devolve 0 ganho / 0 perdido).
                (?, ?, ?, 1, 0, 100, 300,
                 -- init_base_stat_value = 10 nas constantes oficiais: os QUATRO
                 -- atributos comecam a 10 (critico e esquiva estavam a 0).
                 10, 10, 10, 10,
                 100, 100, 1, 1,
                 100, 100,
                 0, 3,
                 -- Energia de treino: base 30/30, distinta da energia de missões.
                 30, 30, ?,
                 -- stamina de LIGA: oficial comeca 20/20 (league_stamina_cost = 20
                 -- nunca cabia numa barra de 10).
                 20, 20, 20,
                 '', '', '', '',
                 '', '[]')",
            [$userId, $name, $gender, GameTime::now()]
        );
        $charId = (int)Db::pdo()->lastInsertId();
        $char = self::load($charId);
        $char->syncAppearanceDefaults();
        // inventario vazio (todos os slots zerados) p/ a conta nova ter shape valida.
        Db::exec("INSERT INTO `inventory` (character_id, item_set_data, sidekick_data) VALUES (?, '', '')", [$charId]);
        // quests iniciais: SEM elas o cliente quebra. Conta com quests=[] estoura no boot
        // (get_quests().iterator() de null) e, se omitirmos a chave, `this._quests` fica null
        // e o painel de Quests estoura em getQuestById (`_quests.h`). A unica shape valida e
        // ter >=1 quest real (como o skelletonx tem 3 de stage 1).
        self::seedStarterQuests($charId, 1);
        return self::load($charId);
    }

    /** Sobrescreve a aparencia (chamado por createCharacter, com os valores escolhidos no cliente). */
    public function setAppearance(array $appearance): void
    {
        $cols = [
            'hair_color' => 'appearance_hair_color', 'skin_color' => 'appearance_skin_color',
            'hair_type' => 'appearance_hair_type', 'head_type' => 'appearance_head_type',
            'eyes_type' => 'appearance_eyes_type', 'eyebrows_type' => 'appearance_eyebrows_type',
            'nose_type' => 'appearance_nose_type', 'mouth_type' => 'appearance_mouth_type',
            'facial_hair_type' => 'appearance_facial_hair_type', 'decoration_type' => 'appearance_decoration_type',
        ];
        $sets = [];
        $values = [];
        foreach ($cols as $param => $col) {
            if (!array_key_exists($param, $appearance)) continue;
            $sets[] = "`$col` = ?";
            $values[] = (int)$appearance[$param];
        }
        if ($sets === []) return;
        $values[] = $this->id();
        Db::exec('UPDATE `character` SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);
        foreach ($cols as $param => $col) {
            if (array_key_exists($param, $appearance)) $this->row[$col] = (int)$appearance[$param];
        }
    }

    /** Define o nome do personagem (tela de criacao / troca de nome). */
    public function setName(string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 32) {
            throw new GameError('errRequestInvalidParameter');
        }
        Db::exec('UPDATE `character` SET name = ? WHERE id = ?', [$name, $this->id()]);
        $this->row['name'] = $name;
    }

    /**
     * Semeia as quests de STAGE 1 (as mesmas do skelletonx) para um personagem novo,
     * no nivel informado. Idempotente: nao insere se o personagem ja tiver quests.
     */
    public static function seedStarterQuests(int $charId, int $level = 1): void
    {
        // Por STAGE (nao por personagem): agora cada cidade tem o seu pool proprio.
        $has = (int)Db::value('SELECT COUNT(*) FROM `quests` WHERE character_id = ? AND stage = 1', [$charId]);
        if ($has > 0) return;
        self::insertQuestPool($charId, $level, 3);
    }

    /**
     * Catalogo OFICIAL de identificadores de missao, por stage e tipo, extraido de
     * `constants.quest_templates` (data/reference/quest_templates.json — 454
     * identificadores em 14 stages, mais 107 NPCs de luta). O identifier TEM de
     * existir na localizacao (`quest/<id>/briefing`), senao o texto da missao
     * aparece vazio no cliente — por isso nunca inventar nomes.
     *
     * @var array{quests: array<string, array{fight: string[], time: string[]}>, npcs: string[]}|null
     */
    private static ?array $questCatalog = null;

    private static function questCatalog(): array
    {
        if (self::$questCatalog === null) {
            $path = __DIR__ . '/../../data/reference/quest_templates.json';
            $raw = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
            self::$questCatalog = is_array($raw) && isset($raw['quests'])
                ? $raw
                : ['quests' => [], 'npcs' => []];
        }
        return self::$questCatalog;
    }

    /** Insere $count quests novas (status=1) para o personagem, no nivel e stage dados. */
    private static function insertQuestPool(int $charId, int $level, int $count, int $stage = 1): void
    {
        $catalog = self::questCatalog();
        // Expansões da equipa determinam prémios fixados na geração.
        $mult   = self::questRewardMultipliers($charId);

        // Energetico pessoal do jogador aplicado JA na geracao: o energy_cost
        // gravado na BD e o custo real que sera debitado ao iniciar a missao.
        $charBoosterRow = Db::row(
            'SELECT active_quest_booster_id, ts_active_quest_boost_expires FROM `character` WHERE id = ?',
            [$charId]
        );
        $boosterId      = (string)($charBoosterRow['active_quest_booster_id'] ?? '');
        $boosterExpires = (int)($charBoosterRow['ts_active_quest_boost_expires'] ?? 0);
        $personalBoost  = 0;
        if ($boosterId !== '' && ($boosterExpires <= 0 || $boosterExpires > GameTime::now())) {
            $personalBoost = (int)(Live::BOOSTER_CATALOG[$boosterId][1] ?? 0);
        }
        $teamBoost = self::load($charId)->guildBoosterAmount('quest');
        $existingTypes = array_map(static fn(array $q): int => (int)$q['type'],
            Db::rows('SELECT type FROM `quests` WHERE character_id = ? AND stage = ?', [$charId, $stage]));

        $stages = $catalog['quests'];
        // Stage sem catalogo: cai no maior stage conhecido (o identifier tem de
        // existir na localizacao).
        $key = isset($stages[(string)$stage]) ? (string)$stage : (string)max(array_map('intval', array_keys($stages) ?: [1]));
        $fight = $stages[$key]['fight'] ?? [];
        $time  = $stages[$key]['time'] ?? [];
        shuffle($fight);
        shuffle($time);

        foreach (QuestRewards::poolPlan($existingTypes, $count) as $i => $slot) {
            $type = $slot['type'];
            $list = $type === 2 ? $fight : $time;
            if ($list === []) throw new \RuntimeException('Catálogo de missões incompleto para a zona ' . $stage);
            $identifier = EventQuests::themedQuest($charId, $type, $list[$i % count($list)]);
            $best = self::rollQuest($type, $level, $mult, $slot['focused']);
            HeroCon::sync();
            $heroconBoost = HeroCon::boost((int)(self::load($charId)->row['guild_id'] ?? 0));
            $best['coins'] = (int)round($best['coins'] * $heroconBoost);
            $best['xp'] = (int)round($best['xp'] * $heroconBoost);
            $costs = QuestRewards::boostedCosts($best['duration'], $best['energy'], $personalBoost, $teamBoost);
            $best['energy'] = $costs['energy'];
            $best['duration'] = $costs['duration'];
            // duration_raw conserva a duração base; prémios ficam fixos na geração.

            Db::exec(
                "INSERT INTO `quests`
                    (character_id, identifier, type, stage, level, status,
                     duration_type, duration_raw, duration, ts_complete, energy_cost,
                     fight_difficulty, fight_npc_identifier, fight_battle_id, used_resources, rewards)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, 0, ?, ?, ?, 0, 0, ?)",
                [
                    $charId, $identifier, $type, $stage, $level,
                    1,   // duration_type: sempre 1 no oficial
                    $best['duration_raw'], $best['duration'], $best['energy'],
                    $best['difficulty'],
                    $type === 2 ? QuestNpcs::identifier($identifier, QuestNpcs::identifier($list[$i % count($list)])) : '',
                    json_encode(['coins' => $best['coins'], 'xp' => $best['xp']] + StoryDungeon::questReward($charId, $identifier) + EventQuests::questReward($charId, $identifier) + HeroCon::questReward($charId, $identifier), JSON_UNESCAPED_SLASHES),
                ]
            );
        }
    }

    /** Duração base define energia base; dificuldade afeta XP, moedas e combate. */
    private static function rollQuest(int $type, int $level, array $mult, bool $focused = false): array
    {
        $difficulty = $type === 2 ? QuestRewards::rollDifficulty() : QuestRewards::DIFF_NONE;
        $minutes = $type === 2 ? QuestRewards::rollFightDurationMinutes() : QuestRewards::rollDurationMinutes($level);
        $duration = $minutes * 60;
        return [
            'duration_raw' => $duration,
            'duration' => $duration,
            'energy' => $minutes,
            'difficulty' => $difficulty,
            'xp' => QuestRewards::missionXp($duration, $mult['xp_stat'], $focused, QuestRewards::rollXpMultiplier(), $difficulty),
            'coins' => QuestRewards::rollCoinsByGuildStat($mult['coins_stat'], $minutes, $difficulty, $level),
            'xp_focused' => $focused,
        ];
    }

    /**
     * action generateNewQuests: troca o pool de missoes. Remove as quests ainda nao
     * iniciadas e insere um pool novo; a quest ativa (em andamento/concluida) e mantida
     * para nao invalidar active_quest_id. Custo premium: 0 por enquanto (as constantes
     * quest_refresh_* vem do asset de constants do CDN, que nao hospedamos).
     */
    public function regenerateQuests(bool $allStages = false): void
    {
        $activeId = (int)($this->row['active_quest_id'] ?? 0);
        // active_quest_id pode estar pendurado (a linha ja foi deletada por claim);
        // nesse caso zeramos e geramos o pool cheio.
        if ($activeId > 0) {
            $exists = (int)Db::value(
                'SELECT COUNT(*) FROM `quests` WHERE id = ? AND character_id = ?',
                [$activeId, $this->id()]
            );
            if ($exists === 0) {
                Db::exec('UPDATE `character` SET active_quest_id = 0 WHERE id = ?', [$this->id()]);
                $this->row['active_quest_id'] = 0;
                $activeId = 0;
            }
        }

        $level = (int)$this->row['level'];

        if ($allStages) {
            // Apaga todas as quests de todos os stages exceto a ativa.
            Db::exec(
                'DELETE FROM `quests` WHERE character_id = ? AND id <> ?',
                [$this->id(), $activeId]
            );
            // Regenera 3 quests por cada stage que o personagem desbloqueou.
            $maxStage = max(1, (int)($this->row['max_quest_stage'] ?? 1));
            for ($s = 1; $s <= $maxStage; $s++) {
                $kept = (int)Db::value('SELECT COUNT(*) FROM `quests` WHERE character_id = ? AND stage = ?', [$this->id(), $s]);
                self::insertQuestPool($this->id(), $level, max(0, 3 - $kept), $s);
            }
        } else {
            // SO o stage atual.
            $stage = max(1, (int)($this->row['current_quest_stage'] ?? 1));
            Db::exec(
                'DELETE FROM `quests` WHERE character_id = ? AND stage = ? AND id <> ?',
                [$this->id(), $stage, $activeId]
            );
            $kept = (int)Db::value(
                'SELECT COUNT(*) FROM `quests` WHERE character_id = ? AND stage = ?',
                [$this->id(), $stage]
            );
            self::insertQuestPool($this->id(), $level, max(0, 3 - $kept), $stage);
        }
    }

    /**
     * Garante que a cidade (stage) tem o seu pool, SEM mexer no que ja la esta.
     * As missoes de cada cidade sao persistentes: saltar de cidade em cidade (ou
     * voltar a uma anterior) tem de mostrar exatamente as mesmas missoes. So sao
     * trocadas ao CONCLUIR uma missao dessa cidade ou no refresh explicito
     * (generateNewQuests). Antes, `setCharacterStage` chamava regenerateQuests()
     * a cada viagem e o pool era sorteado de novo sempre.
     */
    public function ensureQuestPool(int $stage): void
    {
        $stage = max(1, $stage);
        $have = (int)Db::value(
            'SELECT COUNT(*) FROM `quests` WHERE character_id = ? AND stage = ?',
            [$this->id(), $stage]
        );
        if ($have >= 3) return;
        self::insertQuestPool($this->id(), (int)$this->row['level'], 3 - $have, $stage);
    }

    public static function load(int $characterId): self
    {
        $row = Db::row('SELECT * FROM `character` WHERE id = ?', [$characterId]);
        if ($row === null) {
            throw new GameError('errCharacterNotFound');
        }
        $user = Db::row('SELECT * FROM `user` WHERE id = ?', [(int)$row['user_id']]) ?? [];
        return new self($row, $user);
    }

    public static function loadByUser(int $userId): self
    {
        $row = Db::row('SELECT * FROM `character` WHERE user_id = ? ORDER BY id LIMIT 1', [$userId]);
        if ($row === null) {
            throw new GameError('errCharacterNotFound');
        }
        $user = Db::row('SELECT * FROM `user` WHERE id = ?', [$userId]) ?? [];
        return new self($row, $user);
    }

    public function id(): int      { return (int)$this->row['id']; }
    public function userId(): int  { return (int)$this->row['user_id']; }
    public function name(): string { return (string)$this->row['name']; }
    public function level(): int   { return (int)$this->row['level']; }
    public function statBought(string $stat): int { return (int)($this->row['stat_bought_' . $stat] ?? 0); }
    public function honor(): int   { return (int)$this->row['honor']; }
    public function gameCurrency(): int { return (int)$this->row['game_currency']; }
    public function premiumCurrency(): int { return (int)($this->user['premium_currency'] ?? 0); }

    /** Leitura generica de um campo da linha `character` ($row e privado). */
    public function field(string $key): mixed
    {
        return $this->row[$key] ?? null;
    }

    /** @var array<int,int>|null tabela oficial nivel => xp total minimo (data/levels_xp.json). */
    private static ?array $xpTable = null;

    /**
     * Tabela OFICIAL de niveis, extraida do constants do CDN do jogo
     * (hz-static .../assets/data/constants_json.data -> constants.levels).
     * O cliente usa exatamente essa tabela p/ desenhar a barra de XP; qualquer
     * outra curva no servidor faz a barra mostrar progresso negativo.
     */
    /** XP total minimo exigido por um nivel (tabela oficial). */
    public static function xpForLevel(int $level): int
    {
        $table = self::xpTable();
        return (int)($table[$level] ?? 547);
    }

    private static function xpTable(): array
    {
        if (self::$xpTable === null) {
            $raw = json_decode((string)@file_get_contents(__DIR__ . '/../../data/levels_xp.json'), true);
            self::$xpTable = [];
            if (is_array($raw)) {
                foreach ($raw as $lvl => $xp) {
                    self::$xpTable[(int)$lvl] = (int)$xp;
                }
            }
        }
        return self::$xpTable;
    }

    /** XP total minima para cada nivel (tabela oficial; fallback: curva antiga). */
    public static function levelForXp(int $xp): int
    {
        $table = self::xpTable();
        if ($table === []) {
            // fallback antigo, so se o arquivo sumir
            $level = 1;
            while ($level < 400 && $xp >= (int)round(30 * $level ** 1.7)) {
                $level++;
            }
            return $level;
        }
        $level = 1;
        foreach ($table as $lvl => $required) {
            if ($xp >= $required) {
                $level = max($level, $lvl);
            } else {
                break;
            }
        }
        return $level;
    }

    /** Pontos de habilidade concedidos por nivel subido (jogo original: 2 no nivel 2). */
    private const STAT_POINTS_PER_LEVEL = 2;

    /**
     * Nivel minimo oficial de cada zona (constants "stages" do CDN do jogo:
     * hz-static .../constants_json.data -> stages[stage].min_level). O cliente
     * mostra a zona trancada com "desbloqueia ao nivel X" lendo exatamente esta
     * tabela (Q.getQuestStageUnlockLevel), logo o servidor tem de abrir a zona
     * quando o nivel chega ai -- nao pelo claim da story dungeon.
     */
    private const STAGE_UNLOCK_LEVELS = [
        1 => 0,   2 => 10,  3 => 20,  4 => 30,  5 => 40,  6 => 50,  7 => 70,
        8 => 90,  9 => 110, 10 => 130, 11 => 160, 12 => 200, 13 => 250, 14 => 300,
    ];

    /** Zona mais alta cujo min_level oficial ja foi alcancado pelo nivel. */
    private static function maxStageForLevel(int $level): int
    {
        $max = 1;
        foreach (self::STAGE_UNLOCK_LEVELS as $stage => $minLevel) {
            if ($level >= $minLevel && $stage > $max) {
                $max = $stage;
            }
        }
        return $max;
    }

    /**
     * Recalcula level/score_level pelo XP atual, inclusive para contas antigas.
     * Concede stat points por nivel ganho e abre as zonas desbloqueadas pelo nivel.
     */
    public function syncLevelFromXp(): void
    {
        $xp = (int)$this->row['xp'];
        $level = self::levelForXp($xp);
        $oldLevel = (int)$this->row['level'];
        if ($level !== $oldLevel) {
            $gainedPoints = $level > $oldLevel ? ($level - $oldLevel) * self::STAT_POINTS_PER_LEVEL : 0;
            Db::exec(
                'UPDATE `character` SET level = ?, score_level = ?, stat_points_available = stat_points_available + ? WHERE id = ?',
                [$level, ($level * 100000000) + $xp, $gainedPoints, $this->id()]
            );
            $this->row['level'] = $level;
            $this->row['score_level'] = ($level * 100000000) + $xp;
            $this->row['stat_points_available'] = (int)$this->row['stat_points_available'] + $gainedPoints;
        }

        // Zonas abrem pelo NIVEL (stages[stage].min_level). Corre mesmo sem subida
        // de nivel p/ corrigir personagens que ficaram com max_quest_stage atrasado.
        // GREATEST nunca baixa zonas ja abertas (ex.: claim da story dungeon).
        $unlocked = self::maxStageForLevel($level);
        if ($unlocked > (int)($this->row['max_quest_stage'] ?? 1)) {
            Db::exec('UPDATE `character` SET max_quest_stage = ? WHERE id = ?', [$unlocked, $this->id()]);
            $this->row['max_quest_stage'] = $unlocked;
        }

        // SIDEKICK: ao atingir o nivel minimo, concede o primeiro sidekick
        // (flag received_sidekick torna a concessao unica).
        Sidekick::grantFirstIfEligible($this);
    }

    /**
     * Personagem que ja passou do inicio do jogo nao deve ficar preso no tutorial.
     * NAO usar nivel 2: no jogo original, ao chegar no nivel 2 o tutorial da LOJA
     * (brecho) ainda esta por vir (shop_shown/first_item ainda devem ficar false).
     * So a partir do nivel 3 (ja passou da loja) e seguro marcar tudo como visto.
     */
    public function syncTutorialForLevel(): void
    {
        if ((int)$this->row['level'] < 3) {
            return;
        }

        $flags = json_decode((string)($this->row['tutorial_flags'] ?? ''), true);
        if (!is_array($flags)) $flags = [];

        $changed = false;
        foreach (self::TUTORIAL_DONE_FLAGS as $key => $value) {
            if (!array_key_exists($key, $flags) || $flags[$key] !== $value) {
                $flags[$key] = $value;
                $changed = true;
            }
        }
        if (!$changed) {
            return;
        }

        $json = json_encode($flags, JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET tutorial_flags = ? WHERE id = ?', [$json, $this->id()]);
        $this->row['tutorial_flags'] = $json;
    }

    /**
     * Marca UMA flag de tutorial no personagem (persistida e em memória).
     * Flags de PROGRESSO do tutorial (first_mission, stats_spent, first_item,
     * first_duel) NAO sao enviadas pelo cliente via setTutorialFlags — sao o
     * servidor que as deriva da acao do jogador. O cliente as lê via
     * hasTutorialFlag/hasNewTutorialFlag para avancar a sequencia
     * missao -> loja -> duelo.
     */
    public function setTutorialFlag(string $key, $value = true): void
    {
        if (!array_key_exists($key, self::TUTORIAL_DONE_FLAGS)
            && !array_key_exists($key, self::HIDEOUT_TUTORIAL_FLAGS)) {
            return;
        }
        $flags = json_decode((string)($this->row['tutorial_flags'] ?? ''), true);
        if (!is_array($flags)) $flags = [];
        $cur = $flags[$key] ?? null;
        if ($cur === $value || (is_bool($value) && (bool)$cur === $value)) {
            return;
        }
        $flags[$key] = $value;
        $json = json_encode($flags, JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET tutorial_flags = ? WHERE id = ?', [$json, $this->id()]);
        $this->row['tutorial_flags'] = $json;
    }

    public function getTutorialFlag(string $key): mixed
    {
        $flags = json_decode((string)($this->row['tutorial_flags'] ?? ''), true);
        return is_array($flags) ? ($flags[$key] ?? null) : null;
    }

    /** Corrige contas antigas criadas com partes de rosto zeradas. */
    public function syncAppearanceDefaults(): void
    {
        $gender = (string)($this->row['gender'] ?? 'm');
        $defaults = self::DEFAULT_APPEARANCE[$gender] ?? self::DEFAULT_APPEARANCE['m'];
        $required = [
            'appearance_skin_color',
            'appearance_hair_color',
            'appearance_hair_type',
            'appearance_head_type',
            'appearance_eyes_type',
            'appearance_eyebrows_type',
            'appearance_nose_type',
            'appearance_mouth_type',
        ];

        $needsFix = false;
        foreach ($required as $col) {
            if ((int)($this->row[$col] ?? 0) <= 0) {
                $needsFix = true;
                break;
            }
        }
        if (!$needsFix) {
            return;
        }

        $sets = [];
        $values = [];
        foreach ($defaults as $col => $value) {
            $sets[] = "`$col` = ?";
            $values[] = $value;
            $this->row[$col] = $value;
        }
        $values[] = $this->id();
        Db::exec('UPDATE `character` SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);
    }

    /** Garante sessoes de treino para personagens que ja sairam do tutorial inicial. */
    /**
     * stat_type interno (cost*10+base) -> stat base 1-4.
     * Encoding: stat_type = cost*10 + base, cost=1..9, base=1..4.
     */
    private static function trainingStatType(int $statType): int
    {
        return $statType % 10;  // base 1-4
    }

    /** stat_type interno -> custo em unidades de treino (1-9). */
    private static function trainingCost(int $statType): int
    {
        return intdiv($statType, 10);
    }

    /** Duracao em segundos dado o custo. */
    private static function trainingDuration(int $cost): int
    {
        return $cost * 300;
    }

    /** Energia necessaria para completar o treino dado o custo. */
    private static function trainingNeededEnergy(int $cost): int
    {
        return $cost * 50 + 300 + ($cost >= 4 ? 50 : 0);
    }

    /**
     * 4 IDs do pool atual (um por stat, custo 1-9 sorteado por seed = char_id|ts_last_training).
     * Muda quando ts_last_training muda (refresh ou inicio de treino).
     * Encoding do ID: char_id*1000 + stat_type, onde stat_type = cost*10 + base (base=1..4, cost=1..9).
     */
    private function currentTrainingPool(): array
    {
        $units = (int)($this->row['training_count'] ?? 0);

        // SEM unidades e sem treino a decorrer: pool VAZIA. O cliente mostra entao o
        // ecra "No momento, nao ha nenhum treino para voce escolher" com o texto
        // `info_no_training_sessions` e a contagem ate amanha (get_hasTrainingOffers()
        // = training_pool != ""). Gerar ofertas impossiveis de comprar era o bug.
        // Com treino ativo a pool TEM de existir, senao o treino em curso nao vem em
        // `trainings` e o get_training() do cliente fica null (ver BUG-TR-008).
        if ($units <= 0 && $this->activeTrainingRow() === null) {
            return [];
        }

        $seed = crc32($this->id() . '|' . (string)($this->row['ts_last_training'] ?? 0));

        // Nunca gerar ofertas que o jogador nao possa pagar: o custo e limitado as
        // unidades que ainda tem hoje (com 3 restantes so saem ofertas de 1 a 3).
        $maxCost = max(1, min(9, $units));

        $ids = [];
        foreach ([1, 2, 3, 4] as $base) {
            $cost     = (int)(abs(($seed >> (($base - 1) * 8)) & 0xFF) % $maxCost) + 1;
            $statType = $cost * 10 + $base;
            $ids[]    = ($this->id() * 1000) + $statType;
        }
        return $ids;
    }

    /**
     * Garante que max_training_count reflecte 10 + bonus de trofeus da guilda,
     * e repoe training_count ao maximo quando o relogio cruzou as 00:00 do dia atual.
     * Base: 10 unidades. Trofeus tipo 3 (Motivacao): +5 por unidade.
     */
    public function syncTrainingForLevel(): void
    {
        if ((int)$this->row['level'] < 2) {
            return;
        }
        $gid = (int)($this->row['guild_id'] ?? 0);
        // Base 10 + trofeus tipo 3 (Motivacao, +5 cada).
        $effectiveMax = GuildArtifacts::effectiveMax('training_count', 10, $gid);
        // + guild booster de treino ativo (guild_booster_training1=+4, _training2=+7).
        if ($gid > 0) {
            $gRow = Db::row(
                'SELECT active_training_booster_id, ts_active_training_boost_expires FROM `guild` WHERE id = ?',
                [$gid]
            );
            if ($gRow && $gRow['active_training_booster_id'] !== '') {
                $exp = (int)$gRow['ts_active_training_boost_expires'];
                if ($exp === 0 || $exp > GameTime::now()) {
                    $entry = Live::GUILD_BOOSTER_CATALOG[$gRow['active_training_booster_id']] ?? null;
                    if ($entry !== null && (int)$entry[0] === 1) {  // type 1 = training
                        $effectiveMax += (int)$entry[1];            // amount (+4 ou +7)
                    }
                }
            }
        }

        Missiles::transaction([$this->id()], function () use ($effectiveMax): void {
            $fresh = Db::row('SELECT * FROM `character` WHERE id = ?', [$this->id()]);
            Db::exec('INSERT IGNORE INTO daily_bonus_resources (character_id) VALUES (?)', [$this->id()]);
            $state = Db::row('SELECT * FROM daily_bonus_resources WHERE character_id = ? FOR UPDATE', [$this->id()]);
            $factor = BonusEvents::factor(5);
            $today = BonusEvents::today();
            $previousDate = $state['training_date'] ?? BonusEvents::dateOf((int)($fresh['ts_last_training'] ?? 0));
            $count = (int)$fresh['training_count'];
            if ($previousDate < $today) $count = $effectiveMax * $factor;
            elseif ($previousDate === $today) $count += max(0, $factor - (int)$state['training_factor']) * $effectiveMax;
            // O máximo limita a reposição diária, não os prémios de cupões.
            // Preservar o excedente nas sincronizações do mesmo dia.
            Db::exec('UPDATE `character` SET training_count = ?, max_training_count = ? WHERE id = ?',
                [$count, $effectiveMax, $this->id()]);
            Db::exec('UPDATE daily_bonus_resources SET training_date = ?, training_factor = ? WHERE character_id = ?',
                [$today, $previousDate === $today ? max($factor, (int)$state['training_factor']) : $factor, $this->id()]);
            $this->row['training_count'] = $count;
            $this->row['max_training_count'] = $effectiveMax;
        });
    }

    /**
     * Reset diario a 00:00 do relogio interno (GameTime::now()).
     * - Energia sobrante vai para current_energy_storage (cap 100, inclui trofeus da guilda).
     * - quest_energy reposto ao maximo efectivo.
     * - quest_energy_refill_amount_today zerado.
     * Casino tem reset diário independente em Casino::syncDaily().
     * Chamado em autoLoginUser, antes dos overlays.
     */
    public function syncDailyReset(): void
    {
        Missiles::transaction([$this->id()], function (): void {
            $fresh = Db::row('SELECT * FROM `character` WHERE id = ?', [$this->id()]);
            Db::exec('INSERT IGNORE INTO daily_bonus_resources (character_id) VALUES (?)', [$this->id()]);
            $state = Db::row('SELECT * FROM daily_bonus_resources WHERE character_id = ? FOR UPDATE', [$this->id()]);
            $today = BonusEvents::today();
            $factor = BonusEvents::factor(1);
            $previousDate = $state['quest_date'] ?? BonusEvents::dateOf((int)$fresh['ts_last_quest_energy_refill']);
            $baseMax = GuildArtifacts::effectiveMax('quest_energy', (int)$fresh['max_quest_energy'], (int)$fresh['guild_id']);
            $energy = (int)$fresh['quest_energy'];
            $storage = (int)$fresh['current_energy_storage'];
            if ($previousDate < $today) {
                $storage = min(100, $storage + $energy);
                $energy = $baseMax * $factor;
                Db::exec('UPDATE `character` SET quest_energy_refill_amount_today = 0,
                    ts_last_quest_energy_refill = ? WHERE id = ?', [GameTime::now(), $this->id()]);
                $this->row['quest_energy_refill_amount_today'] = 0;
                $this->row['ts_last_quest_energy_refill'] = GameTime::now();
            } elseif ($previousDate === $today) {
                $energy += max(0, $factor - (int)$state['quest_factor']) * $baseMax;
            }
            // Cupões podem exceder o máximo diário; sincronizar não os consome.
            Db::exec('UPDATE `character` SET quest_energy = ?, current_energy_storage = ? WHERE id = ?',
                [$energy, $storage, $this->id()]);
            Db::exec('UPDATE daily_bonus_resources SET quest_date = ?, quest_factor = ? WHERE character_id = ?',
                [$today, $previousDate === $today ? max($factor, (int)$state['quest_factor']) : $factor, $this->id()]);
            $this->row['quest_energy'] = $energy;
            $this->row['current_energy_storage'] = $storage;
        });
    }

    /**
     * Bonus de equipamento por atributo (itens equipados), constante enquanto o
     * inventario nao muda. Derivado de UMA fonte completa e autoritativa: o character
     * da fixture de boot autoLoginUser (unico objeto capturado com base E total dos 4).
     *   equip_X = fixture.stat_total_X - fixture.stat_base_X
     * Como a base da fixture == base semeada no banco, total_vivo = base_do_banco + equip
     * continua correto depois de comprar/treinar pontos.
     * TODO(RE): calcular a partir do inventario equipado quando ele vier do banco.
     */
    /**
     * Boosts da guilda ("team"), lidos das colunas `stat_*` da tabela `guild`.
     * Sao as instalacoes que o grupo evolui de 0 a 50 (max_stat_* nas constantes
     * oficiais). Nada disto estava a ser aplicado no servidor.
     *
     * @return array{stats:int, xp:int, coins:int} niveis brutos (0..50)
     */
    private function guildBoosts(): array
    {
        if ($this->guildBoosts === null) {
            $gid = (int)($this->row['guild_id'] ?? 0);
            $row = $gid > 0
                ? Db::row('SELECT stat_character_base_stats_boost, stat_quest_xp_reward_boost, stat_quest_game_currency_reward_boost FROM `guild` WHERE id = ?', [$gid])
                : null;
            $this->guildBoosts = [
                'stats' => (int)($row['stat_character_base_stats_boost'] ?? 0),
                'xp'    => (int)($row['stat_quest_xp_reward_boost'] ?? 0),
                'coins' => (int)($row['stat_quest_game_currency_reward_boost'] ?? 0),
            ];
        }
        return $this->guildBoosts;
    }
    private ?array $guildBoosts = null;

    /**
     * Bonus de atributo dado pela guilda. Formula do cliente
     * (`Y.get_guildBoosterStamina` e irmas):
     *     ceil(stat_base_X * stat_character_base_stats_boost / 100)
     * Ou seja, o nivel da instalacao E a percentagem: 50 -> +50% da base.
     * Repare no CEIL (nao round) e em incidir so sobre a BASE, sem treino/equipamento.
     */
    /**
     * Multiplicador de atributos: booster de stats + instalacao da guilda + divisao
     * da LIGA somam num UNICO fator, exatamente como o `calculateStats()` do servidor
     * oficial:
     *     $boosterVal = 1 + booster.amount/100 + guild.stat_character_base_stats_boost/100 (+ bonus da divisao)
     *     stat_total_X = ceil(stat_base_X * $boosterVal)   // equipamento vem DEPOIS
     * Somar tudo num so fator (em vez de ceils separados) importa: com booster 25,
     * guilda 50 e divisao bronze3 (+3%) o oficial da ceil(base*1.78), nao tres ceils.
     * O termo da liga e o `base_attribute_bonus` da divisao (+3%..+39%). O jogo
     * original aplica-o a TODOS os combates (missoes, duelos, guilda, gangues...) --
     * ao contrario do bonus de MOEDAS da divisao, que so vale em lutas de liga.
     */
    public function statMultiplier(): float
    {
        return 1.0 + $this->boosterAmount('stats') / 100 + $this->guildBoosts()['stats'] / 100
            + Hideout::kyberneticBonuses($this->id())['stats_pct'] / 100
            + League::attributeFactor($this->leagueDivision()) - 1.0;
    }

    /** Divisao da liga deste personagem (floor(league_group_id/100000)); 0 = nao inscrito. */
    public function leagueDivision(): int
    {
        return League::divisionFromGroup((int)($this->row['league_group_id'] ?? 0));
    }

    /**
     * Bonus passivo do sidekick ATIVO sobre as 4 stats
     * (docs/sidekicks/extract/03 §3.1: ceil(base_do_heroi * value1_da_skill)
     * por skill desbloqueada, grupos battle_effects 105-109).
     * Base = as colunas stat_base_* cruas: e o MESMO campo que o cliente le no
     * DO para calcular a linha dele (stat_trained_* nao existe no protocolo).
     *
     * Soma AINDA as stat_* proprias do sidekick (§3.1: o contributo total e
     * `sidekick.get_statX() + bonus_das_skills`).
     */
    private function sidekickBonus(): array
    {
        $bases = [
            'stamina'         => (int)($this->row['stat_base_stamina'] ?? 0),
            'strength'        => (int)($this->row['stat_base_strength'] ?? 0),
            'critical_rating' => (int)($this->row['stat_base_critical_rating'] ?? 0),
            'dodge_rating'    => (int)($this->row['stat_base_dodge_rating'] ?? 0),
        ];
        $row = Sidekick::activeRow($this->id());
        $out = Sidekick::passiveStatBonus($bases, $row);
        if ($row !== null && (int)($row['status'] ?? 0) === 1) {
            foreach (array_keys($bases) as $stat) {
                $out[$stat] += (int)($row['stat_' . $stat] ?? 0);
            }
        }
        return $out;
    }

    /**
     * Parte do total que vem do multiplicador (booster + guilda).
     *
     * DIVERGENCIA DE SCHEMA face ao servidor oficial: la, `claimTrainingRewards`
     * soma o treino a `stat_trained_X` E a `stat_base_X` (o trained e so um
     * contador para o racio de treino), por isso o oficial multiplica apenas
     * `stat_base_X`. Aqui `improveStat`/treino so mexem em `stat_trained_X`
     * (ver ~L1464), logo a base efetiva e `base + trained` — somar os dois aqui
     * NAO duplica nada neste schema, mas duplicaria se algum dia o treino passar
     * a escrever tambem em stat_base_*.
     */
    public function statBoostBonus(string $stat): int
    {
        $base = (int)($this->row["stat_base_$stat"] ?? 0) + (int)($this->row["stat_trained_$stat"] ?? 0);
        return (int)ceil($base * $this->statMultiplier()) - $base;
    }

    /**
     * Fatores de recompensa de missao. Formula do cliente
     * (`get_guildBonusQuestWorkGameCurrencyRewardFactor`): nivel/50, exibido como
     * `100 * fator` por cento. Logo cada nivel vale +2%, e no maximo (50) e +100%.
     * ATENCAO: escala diferente da dos atributos (que e nivel/100).
     */
    public function guildQuestXpFactor(): float
    {
        return 1.0 + $this->guildBoosts()['xp'] / 50;
    }
    public function guildQuestCoinFactor(): float
    {
        return 1.0 + $this->guildBoosts()['coins'] / 50;
    }

    /**
     * Multiplicadores de recompensa de missao:
     *     1 + bonus_do_time + bonus_de_energetico
     * - Time: `stat_quest_xp_reward_boost` (Popularidade) e
     *   `stat_quest_game_currency_reward_boost` (Motivacao), ambos *2/100 —
     *   no maximo (50) valem +100% cada.
     * - Energetico de missao: +10/25/50% conforme booster_quest1/2/3.
     * "Sentido Heroico" NAO entra aqui: nao muda valores, so faz a lista ser
     * gerada com as melhores opcoes (ver insertQuestPool).
     *
     * @return array{xp: float, coins: float}
     */
    private static function questRewardMultipliers(int $charId): array
    {
        $row = Db::row(
            'SELECT g.stat_quest_xp_reward_boost AS xp, g.stat_quest_game_currency_reward_boost AS coins
               FROM `character` c LEFT JOIN `guild` g ON g.id = c.guild_id WHERE c.id = ?',
            [$charId]
        );
        return [
            'xp_stat'    => (int)($row['xp']    ?? 0),   // 0-50, usado em rollXpByGuildStat
            'coins_stat' => (int)($row['coins'] ?? 0),   // 0-50, usado em rollCoinsByGuildStat
        ];
    }

    /**
     * Percentagem de um booster ATIVO do personagem, 0 se nao houver/expirou.
     * `$kind` = quest | stats | work. As colunas sao
     * `active_<kind>_booster_id` + `ts_active_<kind>_boost_expires`
     * (expires <= 0 significa permanente, como no jogo oficial).
     */
    public function boosterAmount(string $kind): int
    {
        $id = (string)($this->row["active_{$kind}_booster_id"] ?? '');
        if ($id === '') return 0;
        $expires = (int)($this->row["ts_active_{$kind}_boost_expires"] ?? 0);
        if ($expires > 0 && $expires <= GameTime::now()) return 0;
        return (int)(Live::BOOSTER_CATALOG[$id][1] ?? 0);
    }

    /** Idem para o booster da guilda (catalogo `guild_boosters`). */
    public function guildBoosterAmount(string $kind): int
    {
        $gid = (int)($this->row['guild_id'] ?? 0);
        if ($gid <= 0) return 0;
        $g = Db::row('SELECT active_' . $kind . '_booster_id AS id, ts_active_' . $kind . '_boost_expires AS expires FROM `guild` WHERE id = ?', [$gid]);
        $id = (string)($g['id'] ?? '');
        if ($id === '') return 0;
        $expires = (int)($g['expires'] ?? 0);
        if ($expires > 0 && $expires <= GameTime::now()) return 0;
        return (int)(Live::GUILD_BOOSTER_CATALOG[$id][1] ?? 0);
    }

    /** Slots equipados que contam para os atributos (`Item::$TYPE` do oficial). */
    private const EQUIP_SLOTS = [
        'mask_item_id', 'cape_item_id', 'suit_item_id', 'belt_item_id',
        'boots_item_id', 'weapon_item_id', 'gadget_item_id', 'missiles_item_id',
    ];

    private ?array $equipBonusCache = null;

    /** Catalogo de conjuntos de itens (data/item_sets.php); ver comentario la em cima. */
    private static ?array $itemSetCatalog = null;

    private static function itemSetCatalog(): array
    {
        return self::$itemSetCatalog ??= require __DIR__ . '/../../data/item_sets.php';
    }

    /** Map stat do banco -> tipo de bonus de conjunto (data/item_sets.php). 5 = todas as stats. */
    private const SET_STAT_TYPE = [
        'stamina' => 1, 'strength' => 2, 'critical_rating' => 3, 'dodge_rating' => 4,
    ];

    /**
     * Bonus dos CONJUNTOS equipados para um stat: soma ceil(stat_base_X * valor)
     * de todos os limiares alcancados (2/3/4/5... pecas) que afetem o stat.
     *
     * A base e a COLUNA stat_base_X e nao base+treino como em statBoostBonus:
     * o cliente calcula esta linha com get_statBaseStamina() = campo
     * `stat_base_*` da resposta (o tooltip de atributos mostra "+X do conjunto"
     * como linha propria), logo usar base+treino aqui fazia as lutas baterem
     * com um numero diferente do painel. Tipo 5 ("todos os atributos") conta
     * para as quatro stats; tipo 6 (sorte no casino) nao entra em combate.
     */
    public function itemSetBonus(string $stat): int
    {
        $type = self::SET_STAT_TYPE[$stat] ?? 0;
        if ($type === 0) return 0;
        $base = (int)($this->row["stat_base_$stat"] ?? 0);
        if ($base <= 0) return 0;

        $catalogSets = self::itemSetCatalog()['sets'];
        $total = 0;
        foreach ($this->equipBonus()['sets'] ?? [] as $setId => $count) {
            $bonuses = $catalogSets[$setId]['bonus'] ?? [];
            foreach ($bonuses as $pieces => $b) {
                if ((int)$pieces > $count) continue;
                if ((int)$b['type'] === $type || (int)$b['type'] === 5) {
                    $total += (int)ceil($base * (float)$b['value']);
                }
            }
        }
        return $total;
    }

    /**
     * Bonus dos itens EQUIPADOS deste personagem, somando as colunas `stat_*` da
     * tabela `items` dos ids nos slots do `inventory`. Aproveita a mesma query
     * para contar pecas por CONJUNTO (`sets[set_id] = n`), fonte unica usada pelo
     * bonus de combate (itemSetBonus) e pelo `item_set_data` do inventario.
     *
     * Antes isto era um valor FIXO derivado da fixture de boot
     * (`stat_total_X - stat_base_X` do skelletonx), estatico e igual para toda a
     * gente: equipar ou tirar equipamento nao mexia nos atributos. O TODO original
     * ("calcular a partir do inventario equipado quando ele vier do banco") ja
     * podia ser feito — o inventario e a tabela `items` estao no banco.
     *
     * Como no `Player::calculateStats()` oficial, o equipamento e SOMADO depois do
     * multiplicador (booster + guilda), nunca multiplicado por ele.
     */
    private function equipBonus(): array
    {
        if ($this->equipBonusCache !== null) return $this->equipBonusCache;

        $bonus = array_fill_keys(self::STATS, 0);
        $bonus['weapon_damage'] = 0;

        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$this->id()]);
        if ($inv !== null) {
            $ids = [];
            foreach (self::EQUIP_SLOTS as $slot) {
                $id = (int)($inv[$slot] ?? 0);
                if ($id > 0) $ids[] = $id;
            }
            if ($ids !== []) {
                $in = implode(',', array_map('intval', $ids));
                $rows = Db::rows(
                    "SELECT identifier, ts_availability_end, stat_stamina, stat_strength, stat_critical_rating, stat_dodge_rating, stat_weapon_damage
                       FROM `items` WHERE id IN ($in) AND character_id = ?",
                    [$this->id()]
                );
                $catalogItems = self::itemSetCatalog()['items'];
                foreach ($rows as $r) {
                    if ((int)$r['ts_availability_end'] < 0) {
                        $improvement = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? AND type = 12',
                            [-(int)$r['ts_availability_end'], $this->id()]);
                        foreach (self::STATS as $stat) $r['stat_' . $stat] += (int)($improvement['stat_' . $stat] ?? 0);
                    }
                    $bonus['stamina']         += (int)$r['stat_stamina'];
                    $bonus['strength']        += (int)$r['stat_strength'];
                    $bonus['critical_rating'] += (int)$r['stat_critical_rating'];
                    $bonus['dodge_rating']    += (int)$r['stat_dodge_rating'];
                    $bonus['weapon_damage']   += (int)$r['stat_weapon_damage'];
                    // Pecas de conjunto equipadas: identifier -> set_id do catalogo oficial.
                    $setId = $catalogItems[(string)$r['identifier']] ?? null;
                    if ($setId !== null) {
                        $bonus['sets'][$setId] = ($bonus['sets'][$setId] ?? 0) + 1;
                    }
                }
            }
        }
        return $this->equipBonusCache = $bonus;
    }

    /**
     * Sobrescreve no objeto `character` do template TODOS os campos que existem como
     * coluna no banco (identidade inclusa: name, gender, aparencia, boosters,
     * tutorial_flags, quest_pool, ...), preservando o TIPO do template (int/bool/string).
     * Assim skelletonx (semeado no banco) mantem tudo dele e uma conta nova (colunas
     * vazias/zero) nasce limpa, sem caso especial. Os ~26 campos "so-de-resposta" que nao
     * sao coluna (constantes/derivados do sistema) permanecem do template, sem risco de boot.
     * Por cima, recomputa stat_total_* (base + trained + equipamento).
     */
    /** Custo em stamina de um duelo (campo `duel_stamina_cost` do template: 20 = 1/5 da barra). */
    public const DUEL_STAMINA_COST = 20;

    /**
     * Segundos por ponto de stamina de duelo. Valores derivados das constantes
     * oficiais, exatamente como o cliente os calcula em
     * `get_totalSecondsNeededForDuelStaminaProgress()`:
     *     round(1 / duel_stamina_refresh_amount_per_minute * 60)
     *     0.333334/min -> 180s por ponto   (ritmo normal)
     *     2/min        -> 30s  por ponto   (enquanto falta para o 1o duelo)
     * O cliente troca de ritmo com `get_isFirstDuel()` = stamina < custo. Ou seja,
     * de 0 a 20 enche em 10 min; de 20 a 100 leva 4h. Estes numeros TEM de bater
     * com os do cliente: ele extrapola o valor mostrado a partir de
     * `duel_stamina` + `ts_last_duel_stamina_change` com estes mesmos intervalos.
     */
    private const DUEL_STAMINA_SECONDS_PER_POINT            = 180;
    private const DUEL_STAMINA_SECONDS_PER_POINT_FIRST_DUEL = 30;

    /**
     * Regenera a stamina de duelo com base no tempo decorrido (lazy, a cada leitura
     * do character). Sem isto `ts_last_duel_stamina_change` ficava em 0 (epoch) e o
     * cliente via um atraso de decadas: `get_secondsToNextDuelStaminaRefill()` soma
     * +1 ponto por tick do timer enquanto tenta recuperar o atraso, o que enchia a
     * barra a olhos vistos (varios pontos por segundo) em vez do ritmo real.
     *
     * O `ts` avanca pelos intervalos JA consumidos (nao salta para `now`), senao a
     * fracao de progresso em curso era descartada a cada request.
     */
    public function syncDuelStamina(): void
    {
        $now = GameTime::now();
        $sta = (int)($this->row['duel_stamina'] ?? 0);
        // Trofeus type 1 da guilda (Coragem): +20 de coragem maxima cada unidade.
        $max = GuildArtifacts::effectiveMax(
            'duel_stamina',
            (int)($this->row['max_duel_stamina'] ?? 100),
            (int)($this->row['guild_id'] ?? 0)
        ) + Hideout::personalArenaBonuses($this->id())['duel_stamina'];
        $ts  = (int)($this->row['ts_last_duel_stamina_change'] ?? 0);

        if ($sta >= $max) {
            // Barra cheia: nao ha progresso pendente, o relogio fica parado em `now`.
            if ($ts !== $now) {
                Db::exec('UPDATE `character` SET ts_last_duel_stamina_change = ? WHERE id = ?', [$now, $this->id()]);
                $this->row['ts_last_duel_stamina_change'] = $now;
            }
            return;
        }
        if ($ts <= 0) {
            // Conta antiga/nova sem marca de tempo: comeca a contar agora, em vez de
            // creditar decadas de regeneracao de uma vez.
            $ts = $now;
        }

        while ($sta < $max) {
            $step = ($sta < self::DUEL_STAMINA_COST)
                ? self::DUEL_STAMINA_SECONDS_PER_POINT_FIRST_DUEL
                : self::DUEL_STAMINA_SECONDS_PER_POINT;
            if ($now - $ts < $step) break;
            $sta++;
            $ts += $step;
        }
        if ($sta >= $max) {
            $sta = $max;
            $ts = $now;
        }

        if ($sta !== (int)($this->row['duel_stamina'] ?? 0) || $ts !== (int)($this->row['ts_last_duel_stamina_change'] ?? 0)) {
            Db::exec(
                'UPDATE `character` SET duel_stamina = ?, ts_last_duel_stamina_change = ? WHERE id = ?',
                [$sta, $ts, $this->id()]
            );
            $this->row['duel_stamina'] = $sta;
            $this->row['ts_last_duel_stamina_change'] = $ts;
        }
    }

    public function overlayCharacter(array $tplChar): array
    {
        $this->syncLevelFromXp();
        $this->syncTutorialForLevel();
        $this->syncAppearanceDefaults();
        $this->syncTrainingForLevel();
        $this->syncDuelStamina();
        $out = $tplChar;

        // 1) Overlay de toda coluna do banco presente no template, castando pelo tipo do template.
        foreach ($this->row as $col => $val) {
            if (!array_key_exists($col, $tplChar)) continue;   // preserva a shape do template
            $out[$col] = self::castLike($tplChar[$col], $val);
        }
        $out['id'] = $this->id();

        // league_group_id codifica divisao E grupo: divisao = floor(league_group_id / 100000)
        // (Dd.getLeagueDivisionId no client; Y.get_leagueDivisionId deriva dai). Ou seja,
        // "estar na divisao 1" = league_group_id >= 100000, nunca 1. `leaguedivision` e a
        // divisao ja derivada (lida por xi.getInt("leaguedivision") na visualizacao de batalha).
        $out['leaguedivision'] = intdiv((int)($this->row['league_group_id'] ?? 0), 100000);

        // O cliente so aceita boosters que existem em constants.boosters.
        // Preservamos compras validas e zeramos apenas ids desconhecidos.
        foreach (self::VALID_BOOSTERS as $field => $validIds) {
            $expiresField = self::BOOSTER_EXPIRES[$field] ?? null;
            if (array_key_exists($field, $out) && $out[$field] !== '' && !in_array($out[$field], $validIds, true)) {
                $out[$field] = '';
                if ($expiresField !== null && array_key_exists($expiresField, $out)) {
                    $out[$expiresField] = 0;
                }
            }
        }

        // 1b) Trofeus da guilda: os maximus de recursos e o custo de coragem por
        // duelo sao lidos PELO CLIENTE direto destes campos -> emitimos ja com o
        // bonus aplicado (ex.: 2 trofeus de Energia -> max_quest_energy 100->140).
        $gid = (int)($this->row['guild_id'] ?? 0);
        $arenaBonuses = Hideout::personalArenaBonuses($this->id());
        foreach ([
            'max_quest_energy'    => 'quest_energy',
            'max_training_energy' => 'training_energy',
            'max_duel_stamina'    => 'duel_stamina',
            'max_league_stamina'  => 'league_stamina',
        ] as $key => $resource) {
            if (array_key_exists($key, $out)) {
                $out[$key] = GuildArtifacts::effectiveMax($resource, (int)$out[$key], $gid);
            }
        }
        if (array_key_exists('max_duel_stamina', $out)) {
            $out['max_duel_stamina'] += $arenaBonuses['duel_stamina'];
        }
        if (array_key_exists('max_league_stamina', $out)) {
            $out['max_league_stamina'] += $arenaBonuses['league_stamina'];
        }
        if (array_key_exists('duel_stamina_cost', $out)) {
            $out['duel_stamina_cost'] = GuildArtifacts::duelStaminaCost($gid);
        }

        // 2) Totais de atributo computados (nao sao coluna): base + trained +
        //    equipamento + bonus de conjunto. O cliente soma as MESMAS linhas no
        //    painel de atributos (get_statBase* + get_item* + get_itemSet* + ...),
        //    logo omitir o conjunto aqui fazia o total do perfil ficar atras da
        //    decomposicao que ele proprio recalcula.
        $equip = $this->equipBonus();
        // SIDEKICK: o cliente soma a linha do sidekick (get_sidekick*) quando
        // recomputa os totais (Outfits @1427651) — o servidor tem de incluir a
        // mesma parcela, senao o total fica atras da decomposicao dele.
        $sidekickBonus = $this->sidekickBonus();
        $sumTotal = 0;
        foreach (self::STATS as $s) {
            $total = (int)$this->row["stat_base_$s"] + (int)($this->row["stat_trained_$s"] ?? 0)
                + ($equip[$s] ?? 0) + $this->statBoostBonus($s) + $this->itemSetBonus($s)
                + ($sidekickBonus[$s] ?? 0);
            $out["stat_total_$s"] = $total;
            $sumTotal += $total;
        }
        if (array_key_exists('stat_total', $tplChar)) {
            $out['stat_total'] = $sumTotal;
        }
        // Nao e coluna do `character` (vem da arma equipada), logo o overlay das
        // colunas nao lhe toca e ficava sempre no 0 do template.
        if (array_key_exists('stat_weapon_damage', $tplChar)) {
            $out['stat_weapon_damage'] = (int)($equip['weapon_damage'] ?? 0);
        }

        // 3) quest_pool: NAO e coluna, entao o overlay acima nao o toca e o valor do
        // template (ids do skelletonx) vazaria. O painel de Quests le
        // get_questPool() -> {stage:[quest_id,...]} e faz getQuestById(id) em _quests.
        // Se os ids nao forem os das quests REAIS deste personagem, os botoes ficam
        // vazios (nenhuma missao aparece). Recomputamos do banco.
        if (array_key_exists('quest_pool', $tplChar)) {
            $out['quest_pool'] = $this->questPoolJson();
        }
        // Ids de evento que NAO sao coluna: recomputa do estado real. Se ficar o
        // valor do template (id do jogador da captura), o cliente procura um
        // objeto que nao existe na colecao e crasha em get_id() (painel quests).
        if (array_key_exists('treasure_event_id', $tplChar)) {
            $out['treasure_event_id'] = (int)$this->ensureTreasureEventState()['id'];
        }
        if (array_key_exists('draw_event_id', $tplChar)) {
            $out['draw_event_id'] = (int)EventDraws::state($this->id())['id'];
        }
        foreach (['hidden_object_event_quest_id'] as $evField) {
            if (array_key_exists($evField, $tplChar)) {
                $out[$evField] = 0;   // eventos ainda nao emulados
            }
        }

        // Sempre presente: pontos gratis de level-up. Sem a chave na resposta o
        // cliente mantem o valor antigo e os pontos "gratis" nunca baixam
        // (upgrade infinito no painel de stats).
        $out['stat_points_available'] = (int)($this->row['stat_points_available'] ?? 0);

        // Mostra o botao de redistribuicao de stats (martelo) sempre; o custo
        // e 1 item special_reskill da mochila ou 25 donuts (charMisc reskillCharacterStats).
        $out['reskill_enabled'] = true;

        // Sempre presente, mesmo se o template nao tiver a chave (ex.: template de
        // getDailyBonusRewardData): o cliente faz merge por chave e manteria um
        // active_training_id velho apontando para um treino sem quests no mapa.
        $activeTraining = $this->activeTrainingRow();
        $out['active_training_id'] = $activeTraining === null
            ? 0
            : ($this->id() * 1000) + (int)$activeTraining['stat_type'];
        if (array_key_exists('training_pool', $tplChar)) {
            // String VAZIA (nao '[]') quando nao ha ofertas: o cliente testa
            // get_hasTrainingOffers() = training_pool != "". Com '[]' julgava haver
            // ofertas e rebentava em offer1.refresh(undefined).
            $pool = $this->currentTrainingPool();
            $out['training_pool'] = $pool === [] ? '' : json_encode($pool, JSON_UNESCAPED_SLASHES);
        }
        if (array_key_exists('ts_last_training_refresh', $tplChar)) {
            $out['ts_last_training_refresh'] = (int)($this->row['ts_last_training'] ?? GameTime::now());
        }
        if (array_key_exists('ts_last_training_finished', $tplChar)) {
            $out['ts_last_training_finished'] = (int)($this->row['ts_last_training_finished'] ?? 0);
        }
        // training_energy/max_training_energy/ts_last_training_energy_change agora sao
        // colunas reais (ver liveTrainingEnergy()) -- ja vem certas do loop de overlay acima.
        // Antes eram fixadas em max_training_energy/agora aqui, entao a barra nunca baixava
        // (startTrainingQuest nunca debitava porque nao existia onde persistir o gasto).
        return $out;
    }

    /**
     * Monta o quest_pool REAL do personagem: JSON string {"<stage>":[quest_id,...]}.
     * E o que o cliente usa p/ decidir quais quests exibir no painel. Vazio -> "{}".
     */
    public function questPoolJson(): string
    {
        $rows = Db::rows('SELECT id, stage FROM `quests` WHERE character_id = ? ORDER BY stage, id', [$this->id()]);
        $pool = [];
        foreach ($rows as $r) {
            $pool[(string)(int)$r['stage']][] = (int)$r['id'];
        }
        return $pool ? json_encode($pool) : '{}';
    }

    /** Converte $val para o mesmo tipo do valor de referencia $like (do template). */
    private static function castLike($like, $val)
    {
        if (is_bool($like)) return (bool)$val;
        if (is_int($like))  return (int)$val;
        if (is_float($like))return (float)$val;
        return $val === null ? '' : (string)$val;
    }

    /**
     * "Esvazia" uma estrutura preservando a shape: lista -> [], objeto -> mesmas chaves
     * com valores zerados (0 / '' / false / recursao). Usado p/ zerar as estruturas de
     * conta (inventario, quests, itens...) numa conta nova, sem vazar dados de outra conta
     * e sem quebrar o formato que o cliente espera.
     */
    public static function emptyLike($v)
    {
        if (is_array($v)) {
            // lista (chaves 0..n) -> vazia; mapa associativo -> zera cada valor.
            if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
                return [];
            }
            $out = [];
            foreach ($v as $k => $val) {
                $out[$k] = self::emptyLike($val);
            }
            return $out;
        }
        if (is_bool($v))  return false;
        if (is_int($v))   return 0;
        if (is_float($v)) return 0.0;
        return '';
    }

    /** Objeto `user` reduzido (id, session_id, email, premium...) usado em varias respostas. */
    public function overlayUser(array $tplUser): array
    {
        $out = $tplUser;
        foreach ($this->user as $key => $value) {
            if (array_key_exists($key, $out)) {
                $out[$key] = self::castLike($out[$key], $value);
            }
        }

        $out['id'] = $this->userId();
        if (array_key_exists('premium_currency', $tplUser)) {
            $out['premium_currency'] = (int)($this->user['premium_currency'] ?? 0);
        }
        // blocked_premium_currency = donuts bloqueados por disputa de pagamento.
        // Num servidor privado nunca ha disputas: forcamos sempre 0.
        if (array_key_exists('blocked_premium_currency', $tplUser)) {
            $out['blocked_premium_currency'] = 0;
        }
        // trusted=true destrava a doação de donuts à guilda (o cliente bloqueia
        // contas não verificadas de fazerem doações).
        if (array_key_exists('trusted', $tplUser)) {
            $out['trusted'] = true;
        }
        // CRITICO: o cliente ADOTA user.session_id da resposta de boot
        // (rf.set_userSessionId(this._user.get_sessionId())) e o usa em TODAS as
        // requisicoes seguintes. Se vazar a sessao do template (skelletonx), a conta
        // real manda a sessao errada e o guard rejeita (errLoginInvalidSession).
        // Por isso devolvemos a sessao REAL da conta (coluna user.session_id).
        if (array_key_exists('session_id', $tplUser)) {
            $out['session_id'] = (string)($this->user['session_id'] ?? '');
        }
        // Identidade coerente (cosmetico, mas evita confundir): email/locale do banco.
        if (array_key_exists('email', $tplUser) && isset($this->user['email'])) {
            $out['email'] = (string)$this->user['email'];
        }
        if (array_key_exists('locale', $tplUser) && isset($this->user['locale'])) {
            $out['locale'] = (string)$this->user['locale'];
        }
        return $out;
    }

    /** Objeto `inventory` (slots de equipamento/loja) do banco, tipado pelo template. */
    public function inventoryData(array $tplInv): array
    {
        $this->ensureShopItems();
        $out = self::emptyLike($tplInv);          // shape completa, tudo zerado
        $row = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($row) {
            foreach ($row as $k => $v) {
                if (array_key_exists($k, $tplInv)) $out[$k] = self::castLike($tplInv[$k], $v);
            }
        }
        // item_set_data: mapa {set_id: pecas_equipadas} em JSON (zf.encode do cliente
        // e JSON.stringify; getEquippedItemSetCount le exatamente este campo). Sem ele
        // o cliente ve 0 pecas em todos os conjuntos: nem tooltip de bonus, nem a linha
        // "+X do conjunto" no painel de atributos. Calculado AO VIVO dos slots
        // equipados (mesma fonte do bonus de combate) -- nunca divergem.
        if (array_key_exists('item_set_data', $out)) {
            $sets = $this->equipBonus()['sets'] ?? [];
            ksort($sets);
            // Sem conjuntos: '{}' e nao '[]' -- o cliente faz zf.decode (= JSON.parse)
            // sobre a string e constroi um mapa {set_id: n}; um array quebraria isso.
            $out['item_set_data'] = $sets === [] ? '{}' : json_encode($sets);
        }
        return $out;
    }

    /** Lista `items` (instancias de item do personagem) do banco, tipada pelo template. */
    public function itemsData(array $tplItem): array
    {
        $this->ensureShopItems();
        $rows = Db::rows('SELECT i.* FROM `items` i WHERE i.character_id = ?
            AND NOT EXISTS (SELECT 1 FROM items e WHERE e.character_id = i.character_id AND e.ts_availability_end = -i.id)
            ORDER BY i.id', [$this->id()]);
        $attached = array_map('intval', array_column(ItemImprovement::data($this->id()), 'id'));
        $rows = array_values(array_filter($rows, static fn(array $r): bool => !in_array((int)$r['id'], $attached, true)));
        return array_map(fn(array $r) => self::castRowLike($tplItem, $r), $rows);
    }

    /**
     * Gera uma loja inicial real para personagens sem itens de loja. A fixture fornece
     * apenas o catalogo/base; os IDs novos pertencem ao personagem atual.
     */
    public function ensureShopItems(): void
    {
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($inv === null) {
            Db::exec("INSERT INTO `inventory` (character_id, item_set_data, sidekick_data) VALUES (?, '', '')", [$this->id()]);
            $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        }
        if ($inv === null) {
            return;
        }

        $shopIds = [];
        foreach (self::SHOP_SLOTS as $slot) {
            $id = (int)($inv[$slot] ?? 0);
            if ($id > 0) $shopIds[] = $id;
        }
        if ($shopIds !== []) {
            $placeholders = implode(',', array_fill(0, count($shopIds), '?'));
            $count = (int)Db::value(
                "SELECT COUNT(*) FROM `items` WHERE character_id = ? AND id IN ($placeholders)",
                array_merge([$this->id()], $shopIds)
            );
            if ($count > 0) {
                return;
            }
        }

        // Loja inicial GERADA no nivel do personagem (antes vinha do fixture: 22
        // itens nivel 1 fixos -> "brecho sempre igual" mesmo em nivel alto).
        $newSlotIds = [];
        foreach (self::SHOP_SLOTS as $slot) {
            $newSlotIds[$slot] = $this->insertItemFromTemplate($this->generateShopItem());
        }

        $sets = [];
        $values = [];
        foreach ($newSlotIds as $slot => $id) {
            $sets[] = "`$slot` = ?";
            $values[] = $id;
        }
        $values[] = $this->id();
        Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', $values);
    }

    /** Substitui os itens da loja selecionada por novas instancias persistidas. */
    public function refreshShopItems(int $shopIndex, bool $usePremium): void
    {
        $this->withStoryActivity(fn() => $this->refreshShopItemsLocked($shopIndex, $usePremium));
    }

    private function refreshShopItemsLocked(int $shopIndex, bool $usePremium): void
    {
        $this->ensureShopItems();
        $slots = $shopIndex === 2 ? self::SHOP2_SLOTS : self::SHOP1_SLOTS;

        if ($usePremium) {
            if ($this->premiumCurrency() < 1) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - 1 WHERE id = ?', [$this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - 1;
        }

        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($inv === null) {
            throw new GameError('errInventoryNotFound');
        }

        $oldIds = [];
        foreach ($slots as $slot) {
            $id = (int)($inv[$slot] ?? 0);
            if ($id > 0) $oldIds[] = $id;
        }
        if ($oldIds !== []) {
            $placeholders = implode(',', array_fill(0, count($oldIds), '?'));
            Db::exec(
                "DELETE FROM `items` WHERE character_id = ? AND id IN ($placeholders)",
                array_merge([$this->id()], $oldIds)
            );
        }

        $sets = [];
        $values = [];
        // Loja 1 (donuts): 5% de chance de aparecer o martelo de redistribuicao
        // de stats (special_reskill) num slot aleatorio. Preco: 79 donuts.
        $hammerSlot = null;
        if ($shopIndex === 1 && random_int(1, 100) <= 5) {
            $hammerSlot = $slots[array_rand($slots)];
        }
        $usedIdentifiers = [];
        foreach ($slots as $slot) {
            if ($slot === $hammerSlot) {
                $tpl = [
                    'identifier'           => 'special_reskill',
                    'type'                 => 11,
                    'quality'              => 3,
                    'required_level'       => 1,
                    'charges'              => 1,
                    'item_level'           => 1,
                    'ts_availability_start'=> 0,
                    'ts_availability_end'  => 0,
                    'premium_item'         => true,
                    'buy_price'            => 79,
                    'sell_price'           => 0,
                    'stat_stamina'         => 0,
                    'stat_strength'        => 0,
                    'stat_critical_rating' => 0,
                    'stat_dodge_rating'    => 0,
                    'stat_weapon_damage'   => 0,
                ];
            } else {
                $tpl = null;
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    if ($shopIndex === 1) {
                        $candidate = $this->generateShopSidekick() ?? $this->generateShopItem(true);
                    } else {
                        $candidate = $this->generateShopSportsSidekick() ?? $this->generateShopItem(false);
                    }
                    if (!in_array($candidate['identifier'], $usedIdentifiers, true)) {
                        $tpl = $candidate;
                        break;
                    }
                }
                $tpl ??= ($shopIndex === 1 ? $this->generateShopItem(true) : $this->generateShopItem(false));
            }
            $usedIdentifiers[] = $tpl['identifier'];
            $newId = $this->insertItemFromTemplate($tpl);
            $sets[] = "`$slot` = ?";
            $values[] = $newId;
        }
        $sets[] = '`ts_last_shop_refresh` = ?';
        $values[] = GameTime::now();
        $sets[] = '`shop_refreshes` = `shop_refreshes` + 1';
        $values[] = $this->id();
        Db::exec('UPDATE `inventory` SET ' . implode(', ', array_slice($sets, 0, count($sets) - 2)) . ' WHERE character_id = ?', array_merge(array_slice($values, 0, count($values) - 2), [$this->id()]));
        Db::exec(
            'UPDATE `character` SET ts_last_shop_refresh = ?, shop_refreshes = shop_refreshes + 1 WHERE id = ?',
            [GameTime::now(), $this->id()]
        );
        $this->row['ts_last_shop_refresh'] = GameTime::now();
        $this->row['shop_refreshes'] = (int)($this->row['shop_refreshes'] ?? 0) + 1;
        Herobook::record($this->id(), 8);
        if ($shopIndex === 1) StoryDungeon::record($this->id(), StoryDungeon::SHOP_REFRESH);
        EventQuests::record($this->id(), EventQuests::SHOP);
    }

    private const SIDEKICK_SPORTS    = [
        'sidekick_worldcup_tucan','sidekick_worldcup_tiger',
        'sidekick_worldcup_fox','sidekick_worldcup_bear',
        'sidekick_olympia_2020_tokyo_tanuki','sidekick_olympia_2020_tokyo_monkey',
        'sidekick_rio2016','sidekick_xp_tournament',
    ];
    /**
     * Referência do scaling local por nível do herói e qualidade.
     * Soma: nível × 1.2 (comum/default), 2.5 (épico) ou 4.2 (lendário).
     * Ratios: condição 28%, força 43%, cérebro 18%, intuição 11%.
     * A geração da oferta/prémio aplica ±15% independentemente por atributo.
     *
     * @return array{stamina:int, strength:int, critical_rating:int, dodge_rating:int}
     */
    private function sidekickScaledBases(int $quality): array
    {
        $heroLevel = $this->level();
        $sum = (float)match ($quality) {
            4       => $heroLevel * 4.2,
            3       => $heroLevel * 2.5,
            default => $heroLevel * 1.2,
        };
        return [
            'stamina'         => (int)round($sum * 0.28),
            'strength'        => (int)round($sum * 0.43),
            'critical_rating' => (int)round($sum * 0.18),
            'dodge_rating'    => (int)round($sum * 0.11),
        ];
    }

    /** Bases na geração da oferta/prémio: nível do herói, qualidade e ±15%. */
    public function randomSidekickBases(int $quality): array
    {
        $bases = [];
        foreach ($this->sidekickScaledBases($quality) as $stat => $mid) {
            $lo = max(1, (int)round($mid * 0.85));
            $hi = (int)round($mid * 1.15);
            $bases['stat_base_' . $stat] = random_int($lo, max($lo, $hi));
        }
        return $bases;
    }

    /** Atributos sorteados ao preparar o item; representam as bases do pet ao nível 1. */
    private function randomSidekickItemStats(int $quality): array
    {
        $stats = ['stat_weapon_damage'=>0];
        foreach ($this->randomSidekickBases($quality) as $key => $value)
            $stats[str_replace('stat_base_', 'stat_', $key)] = $value;
        return $stats;
    }

    /** Restrição atual: apenas pets cuja fonte é o brechó em época normal. */
    public static function normalShopSidekickPool(int $quality): array
    {
        $catalog = Sidekick::official();
        $pool = [];
        foreach ($catalog['templates'] as $identifier => $template) {
            if ((int)$template['quality'] === $quality
                && in_array('shop_general', $catalog['sources'][$identifier] ?? [], true)) {
                $pool[] = $identifier;
            }
        }
        return $pool;
    }

    private function generateShopSportsSidekick(): ?array
    {
        $pool = array_values(array_intersect(self::SIDEKICK_SPORTS, self::normalShopSidekickPool(3)));
        if (!$pool || random_int(1, 1000) > 5) return null;
        $id = $pool[array_rand($pool)];
        return array_merge([
            'identifier'            => $id,
            'type'                  => 9,
            'quality'               => 3,
            'required_level'        => 1,
            'charges'               => 1,
            'item_level'            => 1,
            'ts_availability_start' => 0,
            'ts_availability_end'   => 0,
            'premium_item'          => true,
            'buy_price'             => 19,
            'sell_price'            => 0,
        ], $this->randomSidekickItemStats(3));
    }

    /** Gera template de pet para a loja 1, ou null se não sair nenhum. */
    private function generateShopSidekick(?int $roll = null): ?array
    {
        $roll ??= random_int(1, 1000);
        if ($roll <= 5) {           // 0.5% lendário
            $quality = 4;
            $isPremium = true;
            $buy = 300;
        } elseif ($roll <= 25) {    // 2% épico
            $quality = 3;
            $isPremium = true;
            $buy = 19;
        } elseif ($roll <= 35) {    // 1% comum
            $quality = 1;
            $isPremium = false;
            $buy = max(8, (int)round(8 * ($this->level() ** 1.5)));
        } else {
            return null;
        }
        $pool = self::normalShopSidekickPool($quality);
        if (!$pool) return null; // Sem promover um sorteio lendário/evento para outra raridade.
        $id = $pool[array_rand($pool)];
        $sell = $isPremium ? 0 : max(4, intdiv($buy, 2));
        return array_merge([
            'identifier'            => $id,
            'type'                  => 9,
            'quality'               => $quality,
            'required_level'        => 1,
            'charges'               => 1,
            'item_level'            => 1,
            'ts_availability_start' => 0,
            'ts_availability_end'   => 0,
            'premium_item'          => $isPremium,
            'buy_price'             => $buy,
            'sell_price'            => $sell,
        ], $this->randomSidekickItemStats($quality));
    }

    /** Identifiers validos por tipo (gerado do i18n oficial do CDN; ~3700 itens). */
    private static ?array $shopIdentifierCatalog = null;
    /** Subconjunto desportivo/mundial por tipo (shop 2 = brecho desportivo). */
    private static ?array $shopSportsCatalog = null;
    /** Catalogo geral sem itens desportivos/mundiais (loja 1). */
    private static ?array $shopPremiumCatalog = null;

    private static function shopIdentifierCatalog(): array
    {
        return self::$shopIdentifierCatalog ??= (require __DIR__ . '/../../data/shop_item_identifiers.php');
    }

    private static function shopPremiumCatalog(): array
    {
        if (self::$shopPremiumCatalog !== null) return self::$shopPremiumCatalog;
        $keywords = ['worldcup', 'olympia', 'olympic', 'football', 'soccer', 'xp_tournament', 'winterolympics'];
        $result = [];
        foreach (self::shopIdentifierCatalog() as $type => $ids) {
            $filtered = array_values(array_filter($ids, function (string $id) use ($keywords): bool {
                foreach ($keywords as $kw) {
                    if (str_contains($id, $kw)) return false;
                }
                return true;
            }));
            if ($filtered !== []) $result[$type] = $filtered;
        }
        return self::$shopPremiumCatalog = $result;
    }

    private static function shopSportsCatalog(): array
    {
        if (self::$shopSportsCatalog !== null) return self::$shopSportsCatalog;
        $keywords = ['worldcup', 'olympia', 'olympic', 'football', 'soccer', 'xp_tournament', 'winterolympics'];
        $result = [];
        foreach (self::shopIdentifierCatalog() as $type => $ids) {
            $filtered = array_values(array_filter($ids, function (string $id) use ($keywords): bool {
                foreach ($keywords as $kw) {
                    if (str_contains($id, $kw)) return true;
                }
                return false;
            }));
            if ($filtered !== []) $result[$type] = $filtered;
        }
        return self::$shopSportsCatalog = $result;
    }

    /**
     * Gera um item de loja ALEATORIO escalado ao nivel do personagem.
     * shop_index 1 (donuts): catalogo geral, qualidade premium (quality 2-3).
     * shop_index 2 (desportivo): so identifiers mundiais/olimpicos, qualidade normal.
     */
    /** Balanceamento local: a borda é independente da raridade. */
    public static function shopItemMultiplier(int $quality, bool $goldBorder = false): float
    {
        return match ($quality) {
            1 => 1.0, 2 => 1.7, 3 => $goldBorder ? 2.7 : 2.3,
            4 => $goldBorder ? 3.5 : 3.0,
            default => throw new \InvalidArgumentException('Qualidade de equipamento inválida.'),
        };
    }

    /** Fórmula partilhada de atributos da loja e dos prémios de vilão. */
    public static function shopItemStats(int $reqLevel, int $quality, int $kyberFlat = 0, bool $goldBorder = false): array
    {
        $qualityMult = self::shopItemMultiplier($quality, $goldBorder);

        // Orcamento de stats e numero de atributos por raridade (docs/formulas/items_raridade.md):
        //   Comum:       1-2 atributos — concentrado num so ou dividido em 2
        //   Raro:        2-3 atributos — equilibrio entre 2 ou 3
        //   Epico/Set:   3-4 atributos — maximo de atributos possiveis
        $budget = (int)ceil(($reqLevel * 1.6 + 2 + $reqLevel ** 2 * 0.004) * $qualityMult) + $kyberFlat;
        $stats = ['stat_stamina' => 0, 'stat_strength' => 0, 'stat_critical_rating' => 0, 'stat_dodge_rating' => 0];
        $keys = array_keys($stats);
        shuffle($keys);

        if ($quality === 1) {
            // Comum: 1 atributo (55%) ou 2 atributos (45%)
            if (random_int(1, 100) <= 45) {
                $first = random_int((int)ceil($budget * 0.5), (int)ceil($budget * 0.75));
                $stats[$keys[0]] = $first;
                $stats[$keys[1]] = max(1, $budget - $first);
            } else {
                $stats[$keys[0]] = $budget;
            }
        } elseif ($quality === 2) {
            // Raro: 2 atributos (40%) ou 3 atributos (60%)
            if (random_int(1, 100) <= 40) {
                $first = random_int((int)ceil($budget * 0.4), (int)ceil($budget * 0.65));
                $stats[$keys[0]] = $first;
                $stats[$keys[1]] = max(1, $budget - $first);
            } else {
                $a = random_int((int)ceil($budget * 0.35), (int)ceil($budget * 0.5));
                $b = random_int((int)ceil(($budget - $a) * 0.4), (int)ceil(($budget - $a) * 0.7));
                $stats[$keys[0]] = $a;
                $stats[$keys[1]] = $b;
                $stats[$keys[2]] = max(1, $budget - $a - $b);
            }
        } else {
            // Epico/Set: 3 atributos (40%) ou 4 atributos (60%)
            if (random_int(1, 100) <= 40) {
                $a = random_int((int)ceil($budget * 0.3), (int)ceil($budget * 0.45));
                $b = random_int((int)ceil(($budget - $a) * 0.4), (int)ceil(($budget - $a) * 0.65));
                $stats[$keys[0]] = $a;
                $stats[$keys[1]] = $b;
                $stats[$keys[2]] = max(1, $budget - $a - $b);
            } else {
                $a = random_int((int)ceil($budget * 0.25), (int)ceil($budget * 0.4));
                $b = random_int((int)ceil($budget * 0.2), (int)ceil($budget * 0.35));
                $remaining = $budget - $a - $b;
                $c = random_int(min($remaining - 1, (int)ceil($remaining * 0.4)), min($remaining - 1, (int)ceil($remaining * 0.7)));
                $stats[$keys[0]] = $a;
                $stats[$keys[1]] = $b;
                $stats[$keys[2]] = $c;
                $stats[$keys[3]] = max(1, $budget - $a - $b - $c);
            }
        }
        return $stats;
    }

    private function generateShopItem(bool $premiumShop = false, int $forceQuality = 0): array
    {
        $catalog = $premiumShop ? self::shopPremiumCatalog() : self::shopSportsCatalog();
        $type = random_int(1, 7);
        if ($this->level() >= Missiles::constants()['item_missile_min_required_level'] && random_int(1, 8) === 8) {
            $templates = array_filter(Missiles::constants()['templates'], fn($r) => (int)$r['required_level'] <= $this->level() && empty($r['item_set_identifier']) && empty($r['is_event']) && empty($r['ts_unlock']) && empty($r['ts_availability_start']) && empty($r['premium_only']));
            $templates = array_filter($templates, static function ($r, $identifier) use ($premiumShop): bool {
                $sports = false;
                foreach (['worldcup','olympia','olympic','football','soccer','xp_tournament','winterolympics'] as $keyword) {
                    if (str_contains($identifier, $keyword)) $sports = true;
                }
                return $premiumShop ? !$sports : $sports;
            }, ARRAY_FILTER_USE_BOTH);
            if ($templates !== []) {
                $identifier = array_rand($templates);
                $buy = max(8, (int)round(8 * ($this->level() ** 1.5)));
                return Missiles::template($identifier, $this->level(), $buy, intdiv($buy, 2));
            }
        }
        $ids = $catalog[$type] ?? [];
        if ($ids === []) {
            // fallback: primeiro tipo disponivel no catalogo
            foreach ($catalog as $t => $list) { $type = $t; $ids = $list; break; }
        }
        $identifier = $ids[random_int(0, count($ids) - 1)];

        $reqLevel = max(1, (int)($this->row['level'] ?? 1) - random_int(0, 4));
        if ($forceQuality >= 1 && $forceQuality <= 3) {
            $quality = $forceQuality;
        } elseif ($premiumShop) {
            // Loja normal (donuts): 40% comum, 30% raro, 30% epico
            $roll = random_int(1, 100);
            $quality = $roll <= 40 ? 1 : ($roll <= 70 ? 2 : 3);
        } else {
            $roll = random_int(1, 100);
            $quality = $roll <= 70 ? 1 : ($roll <= 95 ? 2 : 3);
        }
        $stats = self::shopItemStats($reqLevel, $quality, Hideout::kyberneticBonuses($this->id())['item_flat']);
        $weaponDamage = $type === 6 ? (int)ceil($reqLevel * 1.035) + 2 : 0;

        $isPremium = $premiumShop && $quality === 3 && random_int(1, 100) <= 10;
        if ($isPremium) {
            $buy = 9;                                                     // donuts
            $sell = (int)round(4 * ($reqLevel ** 1.5));                  // vende por moeda
        } else {
            $buy = max(8, (int)round(8 * ($reqLevel ** 1.5) * (1 + 0.3 * ($quality - 1))));
            $sell = max(4, intdiv($buy, 2));
        }

        return [
            'identifier' => $identifier,
            'type' => $type,
            'quality' => $quality,
            'required_level' => $reqLevel,
            'charges' => 0,
            'item_level' => $reqLevel,
            'ts_availability_start' => 0,
            'ts_availability_end' => 0,
            'premium_item' => $isPremium,
            'buy_price' => $buy,
            'sell_price' => $sell,
            'stat_stamina' => $stats['stat_stamina'],
            'stat_strength' => $stats['stat_strength'],
            'stat_critical_rating' => $stats['stat_critical_rating'],
            'stat_dodge_rating' => $stats['stat_dodge_rating'],
            'stat_weapon_damage' => $weaponDamage,
        ];
    }

    private function insertItemFromTemplate(array $item): int
    {
        $cols = ['character_id'];
        $values = [$this->id()];
        foreach (self::ITEM_COLUMNS as $col) {
            $cols[] = "`$col`";
            $values[] = $item[$col] ?? 0;
        }
        $sql = 'INSERT INTO `items` (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
        Db::exec($sql, $values);
        return (int)Db::pdo()->lastInsertId();
    }

    /** Cria item de ferraria (blacksmith) no inventário e devolve o item_id. */
    /** Gera o preview uma vez, sem compra nem ocupação de slots da loja. */
    public function createWorldbossRewardItem(string $identifier, int $type, int $quality, bool $goldBorder = false): int
    {
        $level = $type === 9 ? 1 : max(1, $this->level() - random_int(0, 4));
        if ($type === 9) {
            $stats = $this->randomSidekickItemStats($quality);
        } else {
            $stats = self::shopItemStats($level, $quality, Hideout::kyberneticBonuses($this->id())['item_flat'], $goldBorder);
        }
        $buy = max(8, (int)round(8 * ($level ** 1.5) * (1 + 0.3 * ($quality - 1))));
        return $this->insertItemFromTemplate(array_merge([
            'identifier'=>$identifier, 'type'=>$type, 'quality'=>$quality,
            'required_level'=>$level, 'item_level'=>$level, 'charges'=>$type === 9 ? 1 : 0,
            'ts_availability_start'=>0, 'ts_availability_end'=>0, 'premium_item'=>0,
            'buy_price'=>0, 'sell_price'=>$type === 9 ? 0 : max(4, intdiv($buy, 2)),
            'stat_weapon_damage'=>$type === 6 ? (int)ceil($level * 1.035) + 2 : 0,
        ], $stats));
    }

    /** Prémios de equipamento da Pirâmide e dos Tesouros: loja épica, borda e espaço gratuitos. */
    public function createEventRewardItem(string $identifier, int $type, int $quality): int
    {
        // Pets mantêm as próprias bases; gemas são criadas pelo gerador de modificações.
        $equipment = $type >= 1 && $type <= 8;
        if ($type === 8) {
            $template = Missiles::template($identifier, $this->level());
            $template['quality'] = 3;
            $id = $this->insertItemFromTemplate($template);
        } else {
            $id = $this->createWorldbossRewardItem($identifier, $type, $equipment ? 3 : $quality, $equipment);
        }
        if ($equipment) {
            Db::exec('UPDATE items SET ts_availability_start=15, ts_availability_end=? WHERE id=? AND character_id=?',
                [$type <= 7 ? 1 : 0, $id, $this->id()]);
        }
        return $id;
    }

    /** Academia: equipamento lendário dourado; pets conservam a fórmula própria. */
    public function createTowerRewardItem(string $identifier, int $type, int $quality): int
    {
        if ($type === 9) return $this->createWorldbossRewardItem($identifier, $type, $quality);
        if ($type === 8) {
            $template = Missiles::template($identifier, $this->level());
            $template['quality'] = 4;
            $template['ts_availability_start'] = 15;
            $template['ts_availability_end'] = 0;
            return $this->insertItemFromTemplate($template);
        }
        if ($type < 1 || $type > 7) throw new GameError('errRequestInvalidParameter');
        $level = max(1, $this->level() - random_int(0, 4));
        return $this->insertItemFromTemplate(array_merge([
            'identifier'=>$identifier, 'type'=>$type, 'quality'=>4,
            'required_level'=>$level, 'item_level'=>$level, 'charges'=>0,
            'ts_availability_start'=>15, 'ts_availability_end'=>1, 'premium_item'=>0,
            'buy_price'=>0, 'sell_price'=>max(4, (int)round(4 * $level ** 1.5)),
            'stat_weapon_damage'=>$type === 6 ? (int)ceil($level * 1.035) + 2 : 0,
        ], self::shopItemStats($level, 4, Hideout::kyberneticBonuses($this->id())['item_flat'], true)));
    }

    public function createBlacksmithItem(string $slotType, int $plusLevel, int $quality): int
    {
        static $typeMap = ['mask' => 1, 'cape' => 2, 'suit' => 3, 'belt' => 4, 'boots' => 5, 'weapon' => 6, 'gadget' => 7];
        $type = $typeMap[$slotType] ?? 6;
        $catalog = self::shopIdentifierCatalog();
        $ids = $catalog[$type] ?? ($catalog[6] ?? []);
        $identifier = $ids[array_rand($ids)];
        $reqLevel = max(1, (int)($this->row['level'] ?? 1) + $plusLevel);
        $qualityMult = 1.0 + 0.25 * ($quality - 1);
        $budget = (int)ceil(($reqLevel * 1.6 + 2 + $reqLevel ** 2 * 0.004) * $qualityMult);
        $stats = ['stat_stamina' => 0, 'stat_strength' => 0, 'stat_critical_rating' => 0, 'stat_dodge_rating' => 0];
        $keys = array_keys($stats);
        shuffle($keys);
        if ($quality <= 2) {
            $lo = (int)ceil($budget * 0.4);
            $hi = max($lo, (int)ceil($budget * 0.65));
            $a = random_int($lo, $hi);
            $stats[$keys[0]] = $a;
            $stats[$keys[1]] = max(1, $budget - $a);
        } else {
            $lo = (int)ceil($budget * 0.3);
            $hi = max($lo, (int)ceil($budget * 0.45));
            $a = random_int($lo, $hi);
            $lo2 = (int)ceil(($budget - $a) * 0.4);
            $hi2 = max($lo2, (int)ceil(($budget - $a) * 0.65));
            $b = random_int($lo2, $hi2);
            $stats[$keys[0]] = $a;
            $stats[$keys[1]] = $b;
            $stats[$keys[2]] = max(1, $budget - $a - $b);
        }
        $buy = max(8, (int)round(8 * ($reqLevel ** 1.5) * (1 + 0.3 * ($quality - 1))));
        return $this->insertItemFromTemplate([
            'identifier'            => $identifier,
            'type'                  => $type,
            'quality'               => $quality,
            'required_level'        => $reqLevel,
            'charges'               => 0,
            'item_level'            => $reqLevel,
            'ts_availability_start' => 0,
            'ts_availability_end'   => 0,
            'premium_item'          => 0,
            'buy_price'             => $buy,
            'sell_price'            => max(4, intdiv($buy, 2)),
            'stat_stamina'          => $stats['stat_stamina'],
            'stat_strength'         => $stats['stat_strength'],
            'stat_critical_rating'  => $stats['stat_critical_rating'],
            'stat_dodge_rating'     => $stats['stat_dodge_rating'],
            'stat_weapon_damage'    => $type === 6 ? (int)ceil($reqLevel * 1.035) + 2 : 0,
        ]);
    }

    public function createStoryDungeonItem(array $definition): int
    {
        $identifier = (string)$definition['reward_item_identifier'];
        $templates = StoryDungeon::constants()['item_templates'];
        $fixed = $identifier !== 'random_epic';
        $type = $fixed ? (int)($templates[$identifier]['type'] ?? 0) : random_int(1, 7);
        if ($type < 1 || $type > 8) throw new GameError('errRequestInvalidParameter');
        $slots = [1 => 'mask', 2 => 'cape', 3 => 'suit', 4 => 'belt', 5 => 'boots', 6 => 'weapon', 7 => 'gadget'];
        $id = $this->createBlacksmithItem($slots[$type] ?? 'weapon', (int)$definition['reward_item_level_offset'], 3);
        if ($fixed) Db::exec('UPDATE items SET identifier = ?, type = ? WHERE id = ? AND character_id = ?', [$identifier, $type, $id, $this->id()]);
        if ($type === 8) {
            $itemLevel = max(1, $this->level() + (int)$definition['reward_item_level_offset']);
            Db::exec('UPDATE items SET stat_stamina = 0, stat_strength = 0, stat_critical_rating = 0, stat_dodge_rating = 0, stat_weapon_damage = ? WHERE id = ? AND character_id = ?',
                [(int)ceil($itemLevel * 3.9), $id, $this->id()]);
        }
        // O offset aumenta o poder; o prémio fica utilizável ao nível do herói.
        Db::exec('UPDATE items SET required_level = ?, charges = ? WHERE id = ? AND character_id = ?',
            [$this->level(), $type === 8 ? 100 : 0, $id, $this->id()]);
        return $id;
    }

    /** Cria item de melhoria (gem_production) no inventário e devolve o item_id. */
    public function createImprovementItem(int $slotType, int $quality, array $stats): int
    {
        static $slotNames    = [1 => 'mask', 2 => 'cape', 3 => 'suit', 4 => 'belt', 5 => 'boots', 6 => 'weapon', 7 => 'gadget', 9 => 'sidekick'];
        static $qualityNames = [1 => 'common', 2 => 'rare', 3 => 'epic', 4 => 'legendary'];
        if (!isset($slotNames[$slotType], $qualityNames[$quality])) throw new GameError('errInventoryInvalidItem');
        $sn = $slotNames[$slotType];
        $qn = $qualityNames[$quality];
        $reqLevel = max(1, (int)($this->row['level'] ?? 1));
        $buy = 3 + intdiv($reqLevel, 5) + $quality * 2;
        return $this->insertItemFromTemplate([
            'identifier'            => "improvement_{$sn}_{$qn}",
            'type'                  => 12,
            'quality'               => $quality,
            'required_level'        => $reqLevel,
            'charges'               => 0,
            'item_level'            => $reqLevel,
            'ts_availability_start' => 0,
            'ts_availability_end'   => 0,
            'premium_item'          => 1,
            'buy_price'             => $buy,
            'sell_price'            => max(1, intdiv($buy, 2)),
            'stat_stamina'          => (int)($stats['stat_stamina'] ?? 0),
            'stat_strength'         => (int)($stats['stat_strength'] ?? 0),
            'stat_critical_rating'  => (int)($stats['stat_critical_rating'] ?? 0),
            'stat_dodge_rating'     => (int)($stats['stat_dodge_rating'] ?? 0),
            'stat_weapon_damage'    => 0,
        ]);
    }

    /** Cria item especial (slot extra de melhoria, etc.) no inventário e devolve o item_id. */
    public function createSpecialItem(string $identifier, int $type = 0): int
    {
        $type = match ($identifier) {
            'special_add_improvement_slot' => 13,
            'special_add_sidekick_improvement_slot' => 14,
            default => $type,
        };
        return $this->insertItemFromTemplate([
            'identifier'            => $identifier,
            'type'                  => $type,
            'quality'               => 3,
            'required_level'        => 1,
            'charges'               => in_array($type, [13, 14], true) ? 1 : 0,
            'item_level'            => 1,
            'ts_availability_start' => 0,
            'ts_availability_end'   => 0,
            'premium_item'          => 0,
            'buy_price'             => 0,
            'sell_price'            => 0,
            'stat_stamina'          => 0,
            'stat_strength'         => 0,
            'stat_critical_rating'  => 0,
            'stat_dodge_rating'     => 0,
            'stat_weapon_damage'    => 0,
        ]);
    }

    /** Compra para o destino do cliente, com débito e transferência atómicos. */
    public function buyShopItem(int $itemId, int $targetSlot): bool
    {
        $wasSidekick = false;
        $this->withStoryActivity(function () use ($itemId, $targetSlot, &$wasSidekick): void {
            $wasSidekick = $this->buyShopItemLocked($itemId, $targetSlot);
        });
        return $wasSidekick;
    }

    private function buyShopItemLocked(int $itemId, int $targetSlot): bool
    {
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? FOR UPDATE', [$this->id()]);
        $item = Db::row('SELECT * FROM `items` WHERE id = ? AND character_id = ? FOR UPDATE', [$itemId, $this->id()]);
        if ($inv === null || $item === null) throw new GameError('errInventoryInvalidItem');
        $shopSlot = null;
        foreach (self::SHOP_SLOTS as $slot) {
            if ((int)($inv[$slot] ?? 0) === $itemId) { $shopSlot = $slot; break; }
        }
        if ($shopSlot === null) throw new GameError('errInventoryInvalidItem');
        $isSidekick = (int)$item['type'] === 9;
        $isConsumable = (int)$item['type'] === 11;
        $isPremium = (int)$item['premium_item'] > 0;
        $price = max(0, (int)$item['buy_price']);
        if ($isPremium && $this->premiumCurrency() < $price) throw new GameError('errRemovePremiumCurrencyNotEnough');
        if (!$isPremium && $this->gameCurrency() < $price) throw new GameError('errRemoveGameCurrencyNotEnough');

        // Sidekicks (type=9): entrega as bases persistidas e mostradas na oferta.
        if ($isSidekick) {
            $quality  = (int)$item['quality'];
            $bases = [];
            foreach (['stamina','strength','critical_rating','dodge_rating'] as $stat)
                $bases['stat_base_'.$stat] = (int)$item['stat_'.$stat];
            // Ofertas antigas sem atributos: manter uma via de compatibilidade.
            if (array_sum($bases) === 0) $bases = $this->randomSidekickBases($quality);
            $skills = Sidekick::skillsForIdentifier((string)$item['identifier']);
            $newSidekickId = Sidekick::create($this->id(), (string)$item['identifier'], $quality, $bases, 1, 0, $skills);
            $orders = Sidekick::ordersData($this->id());
            $orders['orders'][] = $newSidekickId;
            Sidekick::saveOrders($this->id(), $orders['orders'], $orders['favorit_orders']);
            Db::exec("UPDATE `inventory` SET `{$shopSlot}` = 0 WHERE character_id = ?", [$this->id()]);
            Db::exec('DELETE FROM `items` WHERE id = ? AND character_id = ?', [$itemId, $this->id()]);
            if ($isPremium) {
                $debited = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$price, $this->userId(), $price]
                );
                if ($price > 0 && $debited !== 1) {
                    throw new GameError('errRemovePremiumCurrencyNotEnough');
                }
                $this->user['premium_currency'] = $this->premiumCurrency() - $price;
            } else {
                $debited = Db::exec(
                    'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                    [$price, $this->id(), $price]
                );
                if ($price > 0 && $debited !== 1) {
                    throw new GameError('errRemoveGameCurrencyNotEnough');
                }
                $this->row['game_currency'] = $this->gameCurrency() - $price;
            }
            $this->bumpGoalStat('first_sidekick_collected');
            $this->bumpGoalStat('sidekick_collected');
            return true;
        }

        if ($targetSlot === 101) {
            if ((int)$item['type'] !== 8 || (int)$item['charges'] <= 0 || $this->level() < max((int)$item['required_level'], (int)Missiles::constants()['ammo_belt_min_required_level'])) throw new GameError('errInventoryInvalidItem');
            $targetSlot = 0;
            for ($i=1;$i<=4;$i++) if ((int)($inv['missiles'.$i.'_item_id'] ?? -1) === 0) { $slotCol = 'missiles'.$i.'_item_id'; break; }
            if (!isset($slotCol)) throw new GameError('errInventoryNoEmptySlot');
        }
        // 1–8 equipamento; 9–26 mochila. Sem fallback pelo tipo do item.
        if (isset($slotCol)) {
            // Compra direta para a primeira vaga desbloqueada do cinto.
        } elseif ($targetSlot >= 9 && $targetSlot <= 26) {
            $slotCol = 'bag_item' . ($targetSlot - 8) . '_id';
        } elseif ($isConsumable && $targetSlot === 0) {
            // Compra de consumível sem destino: primeira posição livre.
            $slotCol = null;
            for ($i = 1; $i <= 18; $i++) {
                $f = 'bag_item' . $i . '_id';
                if (array_key_exists($f, $inv) && (int)$inv[$f] <= 0) { $slotCol = $f; break; }
            }
            if ($slotCol === null) throw new GameError('errInventoryNoEmptySlot');
        } else {
            $slotCol = self::EQUIPMENT_SLOT_BY_TYPE[$targetSlot] ?? null;
            if ($slotCol === null || $isConsumable || $targetSlot !== (int)$item['type']) {
                throw new GameError('errRequestInvalidParameter');
            }
            if ((int)($item['required_level'] ?? 0) > $this->level()) throw new GameError('errInventoryInvalidItem');
            if ($targetSlot === 8 && ((int)$item['charges'] <= 0 || $this->level() < Missiles::constants()['item_missile_min_required_level'])) throw new GameError('errInventoryInvalidItem');
        }
        if (!array_key_exists($slotCol, $inv)) throw new GameError('errRequestInvalidParameter');
        $displaced = (int)$inv[$slotCol];
        $bagSlot = null;
        if ($displaced > 0) {
            for ($i = 1; $i <= 18; $i++) {
                $f = 'bag_item' . $i . '_id';
                if ($f !== $slotCol && array_key_exists($f, $inv) && (int)$inv[$f] <= 0) { $bagSlot = $f; break; }
            }
            if ($bagSlot === null) throw new GameError('errInventoryNoEmptySlot');
        }
        $sets = "`$slotCol` = ?, `$shopSlot` = 0";
        $args = [$itemId];
        if ($bagSlot !== null) { $sets .= ", `$bagSlot` = ?"; $args[] = $displaced; }
        $args[] = $this->id();
        Db::exec("UPDATE `inventory` SET $sets WHERE character_id = ?", $args);
        if ($isPremium) {
            $debited = Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?', [$price, $this->userId(), $price]);
            if ($price > 0 && $debited !== 1) throw new GameError('errRemovePremiumCurrencyNotEnough');
            $this->user['premium_currency'] = $this->premiumCurrency() - $price;
        } else {
            $debited = Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?', [$price, $this->id(), $price]);
            if ($price > 0 && $debited !== 1) throw new GameError('errRemoveGameCurrencyNotEnough');
            $this->row['game_currency'] = $this->gameCurrency() - $price;
        }
        $this->setTutorialFlag('first_item', true);
        return false;
    }

    /** Lista `quests` do personagem do banco, tipada pelo template. */
    public function questsData(array $tplQuest): array
    {
        $stage = max(1, (int)($this->row['current_quest_stage'] ?? 1));
        $this->ensureQuestPool($stage);
        $rows = Db::rows('SELECT * FROM `quests` WHERE character_id = ? ORDER BY id', [$this->id()]);
        return array_map(fn(array $r) => self::castRowLike($tplQuest, QuestNpcs::repairOffer($this->id(), $r)), $rows);
    }

    /**
     * Ofertas de treino para o painel (4 entradas — uma por stat, tier sorteado).
     * O pool muda quando ts_last_training muda (refresh ou arranque de treino).
     */
    public function trainingsData(array $tplTraining): array
    {
        $this->syncTrainingForLevel();
        if ($tplTraining === []) {
            $boot = Replay::data('autoLoginUser');
            $tplTraining = $boot['trainings'][0] ?? [];
        }
        $active  = $this->activeTrainingRow();
        $poolIds = $this->currentTrainingPool();  // 4 IDs: char_id*1000 + cost*10 + base

        // Se ha treino ativo, garante que o slot do stat base correto mostra o stat_type
        // ativo — o pool pode ter mudado (ts_last_training atualizado em startTraining).
        if ($active !== null) {
            $activeStatType = (int)$active['stat_type'];  // cost*10+base
            $activeBase     = self::trainingStatType($activeStatType);  // 1-4
            foreach ($poolIds as $i => $pid) {
                if (self::trainingStatType($pid % 1000) === $activeBase) {
                    $poolIds[$i] = ($this->id() * 1000) + $activeStatType;
                    break;
                }
            }
        }

        // Itens de recompensa ja criados e na posse do jogador — sem isto o cliente
        // nao consegue resolver o id e o 3.o slot fica sem icone.
        $poolItems = $this->trainingPoolRewardItems();

        $out = [];
        foreach ($poolIds as $id) {
            $statType     = $id % 1000;  // cost*10 + base
            $cost         = self::trainingCost($statType);
            $base         = self::trainingStatType($statType);  // 1-4
            $settingBase  = self::TRAINING_SETTINGS[$base] ?? 'stamina_indoor';
            $duration     = self::trainingDuration($cost);
            $neededEnergy = self::trainingNeededEnergy($cost);
            $rMult        = $cost / 2;
            $values   = [
                'id'                  => $id,
                'character_id'        => $this->id(),
                'setting'             => $settingBase,
                'status'              => 1,
                'training_cost'       => $cost,
                'energy'              => 0,
                'needed_energy'       => $neededEnergy,
                'stat_type'           => $base,  // 1-4 para o cliente
                'duration'            => $duration,
                'ts_end'              => 0,
                'training_quest_id'   => 0,
                'training_quest_pool' => '',
                'claimed_stars'       => 0,
                // Bonus da 3.a estrela: o painel de ofertas MOSTRA o icone no 3.o slot
                // antes de comecar, portanto o sorteio tem de existir ja na oferta.
                // Treino em curso -> valor persistido (a oferta prometeu, cumpre-se).
                'rewards_star_1'      => json_encode($this->trainingStarReward($cost, 1)),
                'rewards_star_2'      => json_encode($this->trainingStarReward($cost, 2)),
                'rewards_star_3'      => json_encode(
                    ($active !== null && (int)$active['stat_type'] === $statType)
                        ? $this->trainingStarReward($cost, 3, self::trainingBonusRoll($active), (int)($active['reward_item_id'] ?? 0))
                        : $this->trainingStarReward($cost, 3, $this->trainingOfferBonus($id), $poolItems[$id] ?? 0)
                ),
                // Pontos do atributo TREINADO por estrela: escalados com o custo do treino.
                'stat_points_star_1'  => self::trainingStatPoints($cost, 1),
                'stat_points_star_2'  => self::trainingStatPoints($cost, 2),
                'stat_points_star_3'  => self::trainingStatPoints($cost, 3),
            ];
            if ($active !== null && (int)$active['stat_type'] === $statType) {
                $tsEnd  = (int)($active['ts_end'] ?? 0);
                $expired = $tsEnd > 0 && $tsEnd <= GameTime::now();

                // Tempo esgotado: barra a 100% para as 3 estrelas ficarem DISPONIVEIS.
                // NAO mexer em `iterations` — e o contador de estrelas ja RECLAMADAS.
                // Po-lo a 3 fazia claimTrainingStar() lancar errClaimTrainingStarAlreadyClaimed,
                // o status nunca chegava a 4 e o treino ficava ativo para sempre: o
                // startTraining seguinte dava errStartTrainingAlreadyActive e a energia
                // nunca era reposta. Ver BUG-TR-014.
                if ($expired && (int)$active['used_resources'] < $neededEnergy) {
                    Db::exec(
                        'UPDATE `training` SET used_resources = ? WHERE id = ? AND used_resources < ?',
                        [$neededEnergy, (int)$active['id'], $neededEnergy]
                    );
                    $active['used_resources'] = $neededEnergy;
                }

                $values['status']              = (int)$active['status'];
                // used_resources é agora absoluto — envia directamente ao cliente
                $values['energy']              = min($neededEnergy, (int)$active['used_resources']);
                $values['ts_end']              = $tsEnd;
                // Quest em curso (0 = nenhuma). O cliente usa isto para saber que a
                // quest acabou e fechar o painel da batalha.
                $values['training_quest_id']   = (int)($active['training_quest_id'] ?? 0);
                $round = (int)$active['used_resources'];
                $values['training_quest_pool'] = json_encode([
                    ($id * 1000) + ($round * 10) + 1,
                    ($id * 1000) + ($round * 10) + 2,
                    ($id * 1000) + ($round * 10) + 3,
                ]);
                $values['claimed_stars']       = min(3, (int)$active['iterations']);
            }
            $out[] = $tplTraining ? self::castRowLike($tplTraining, $values) : $values;
        }
        return $out;
    }

    private const STAT_TYPE_NAMES = [1 => 'stamina', 2 => 'strength', 3 => 'critical_rating', 4 => 'dodge_rating'];

    // NPCs oficiais para quests de luta indoor (confirmado em HAR do servidor oficial)
    private const FIGHT_NPCS = ['npc_racoon', 'npc_vending_machine', 'npc_meterman'];

    /** Quest offers for the currently open training — 3 quests, one of each type (instant/fight/stat). */
    public function trainingQuestsData(): array
    {
        $active = $this->activeTrainingRow();
        if ($active === null) return [];

        $trainingId = ($this->id() * 1000) + (int)$active['stat_type'];
        $baseStat   = self::trainingStatType((int)$active['stat_type']); // 1-4
        $statName   = self::STAT_TYPE_NAMES[$baseStat] ?? 'stamina';

        // O servidor oficial devolve um POOL NOVO de 3 quests a cada
        // claimTrainingQuestRewards (ids novos, status=1). A seed inclui portanto o
        // progresso (used_resources), que so avanca no claim — durante a quest em
        // curso a pool mantem-se estavel, e roda quando a recompensa e reclamada.
        $round = (int)($active['used_resources'] ?? 0);
        $seedBase = $this->id() . '|' . (int)($active['ts_creation'] ?? 0) . '|' . $round;
        $seed = abs(crc32($seedBase));

        // Permuta os 3 tipos (1=instant, 2=fight, 3=stat) de forma seeded
        $typeOrder = [1, 2, 3];
        $a = $seed % 3;
        $b = ($seed >> 4) % 3;
        [$typeOrder[$a], $typeOrder[$b]] = [$typeOrder[$b], $typeOrder[$a]];

        $out = [];
        foreach ([0, 1, 2] as $i) {
            $slot        = $i + 1;
            $type        = $typeOrder[$i];
            $variantSeed = abs(crc32($seedBase . '|' . $slot));

            switch ($type) {
                case 2: // fight — abre painel "battle" no cliente
                    $variant    = ($variantSeed % 3) + 1;
                    $identifier = 'indoor_fight' . $variant;
                    $npc        = self::FIGHT_NPCS[$variant - 1];
                    $stat       = 0;
                    $diff       = (($variantSeed >> 8) % 3) + 1; // 1=fácil 2=médio 3=difícil
                    break;
                case 3: // stat — abre painel "stat_quest" no cliente
                    $variant    = ($variantSeed % 3) + 1;
                    $identifier = $statName . '_indoor' . $variant;
                    $npc        = '';
                    $stat       = $baseStat;
                    $diff       = (($variantSeed >> 8) % 3) + 1;
                    break;
                default: // 1 — instant, resolve imediatamente no cliente
                    $variant    = ($variantSeed % 5) + 1;
                    $identifier = 'indoor_instant' . $variant;
                    $npc        = '';
                    $stat       = 0;
                    $diff       = 0; // instant: sem dificuldade (HAR confirma 0)
                    break;
            }

            // Custo variável por tarefa de treino: 1–9.
            $energyCost = 1 + (($variantSeed >> 24) & 0xFF) % 9;

            // Progresso da barra por quest: 45–180 (média ~110, barra cheia em ~5 quests)
            $trainingProgress = 45 + (($variantSeed >> 16) & 0xFF) % 136;

            $neededEnergy = self::trainingNeededEnergy(self::trainingCost((int)$active['stat_type']));
            $level        = (int)($this->row['level'] ?? 1);
            $questCoins   = $this->trainingQuestCoins($level, $neededEnergy, $diff, $variantSeed);
            $questXp      = self::trainingQuestXp($questCoins, $variantSeed);

            $questId = ($trainingId * 1000) + ($round * 10) + $slot;
            // Se esta quest e a que esta ativa (training_quest_id != 0), marcar
            // status=2 para o cliente reconhecer que esta em curso apos um refresh.
            // Sem isto o autoLoginUser devolvia status=1 para a quest ativa e o
            // painel stat_quest ficava num estado inconsistente (skip desativado).
            $activeQuestId = (int)($active['training_quest_id'] ?? 0);
            $questStatus = ($activeQuestId > 0 && $questId === $activeQuestId) ? 2 : 1;
            $out[] = [
                // Id NOVO por ronda (o oficial nunca reutiliza ids entre pools).
                'id'                   => $questId,
                'identifier'           => $identifier,
                'type'                 => $type,
                'stat'                 => $stat,
                'status'               => $questStatus,
                'energy_cost'          => $energyCost,
                'fight_difficulty'     => $diff,
                'fight_battle_id'      => 0,
                'fight_npc_identifier' => $npc,
                'won'                  => true,
                'rewards'              => json_encode([
                    'coins'             => $questCoins,
                    'xp'                => $questXp,
                    'training_progress' => $trainingProgress,
                ]),
            ];
        }
        return $out;
    }

    /**
     * training_energy regenera com o tempo (1/min, ver constants oficiais do CDN
     * `training_energy_refresh_amount_per_minute`), igual o client calcula em
     * `get_trainingEnergy()` (base persistida + minutos desde ts_last_training_energy_change,
     * clampado em max_training_energy). Servidor precisa da MESMA formula pra nao rejeitar
     * energia que o client acha que existe.
     */
    private function liveTrainingEnergy(): int
    {
        $stored = (int)($this->row['training_energy'] ?? 0);
        // Trofeus type 3 da guilda (Motivacao): +5 unidades de treino cada unidade.
        $max = GuildArtifacts::effectiveMax(
            'training_energy',
            (int)($this->row['max_training_energy'] ?? 30),
            (int)($this->row['guild_id'] ?? 0)
        ) * BonusEvents::factor(10);
        $last = (int)($this->row['ts_last_training_energy_change'] ?? GameTime::now());
        $elapsedMinutes = max(0, intdiv(GameTime::now() - $last, 60));
        return max(0, min($max, $stored + $elapsedMinutes));
    }

    public function startTrainingQuest(int $questId): array
    {
        return Missiles::transaction([$this->id()], function () use ($questId): array {
            $fresh = self::load($this->id());
            return $fresh->startTrainingQuestLocked($questId);
        });
    }

    private function startTrainingQuestLocked(int $questId): array
    {
        $active = $this->activeTrainingRow();
        if ($active === null || (int)$active['status'] !== 2) {
            throw new GameError('errStartTrainingQuestNoActiveTraining');
        }
        if ((int)($active['training_quest_id'] ?? 0) > 0) throw new GameError('errStartTrainingQuestInvalidQuest');
        $quests = $this->trainingQuestsData();
        $quest = null;
        foreach ($quests as $candidate) {
            if ((int)$candidate['id'] === $questId) $quest = $candidate;
        }
        if ($quest === null) throw new GameError('errStartTrainingQuestInvalidQuest');

        // Custo variável da oferta gerada em trainingQuestsData() (1–9).
        $cost = max(0, (int)($quest['energy_cost'] ?? 1));
        $energyNow = $this->liveTrainingEnergy();
        if ($energyNow < $cost) {
            throw new GameError('errRemoveTrainingQuestEnergyNotEnough');
        }
        Db::exec(
            'UPDATE `character` SET training_energy = ?, ts_last_training_energy_change = ? WHERE id = ?',
            [$energyNow - $cost, GameTime::now(), $this->id()]
        );
        $this->row['training_energy'] = $energyNow - $cost;
        $this->row['ts_last_training_energy_change'] = GameTime::now();

        // O progresso da barra NAO avanca aqui — o oficial so o soma no
        // claimTrainingQuestRewards. Avancar no start rodava a pool (a seed usa
        // used_resources) a meio da quest em curso. Marca-se apenas a quest ativa.
        Db::exec('UPDATE `training` SET training_quest_id = ?, ts_complete = ? WHERE id = ?',
            [(int)$quest['id'], GameTime::now(), (int)$active['id']]);
        // status=2 = quest em curso (o oficial usa 2 aqui, nao 3).
        $quest['status'] = 2;

        // Para quests de luta (type=2): simula o combate contra o NPC, grava na
        // tabela `battle` e devolve junto com a quest para o cliente animar a luta.
        $battleRow = null;
        if ((int)($quest['type'] ?? 1) === 2) {
            $profileA = $this->battleProfile('a');
            $profileB = $this->questNpcProfile((int)($quest['fight_difficulty'] ?? 1));
            $fight    = new Fight($profileA, $profileB);
            $winner   = $fight->fight() === 1 ? 'a' : 'b';
            $rounds   = ['rounds' => $fight->rounds()];
            Db::exec(
                "INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    GameTime::now(),
                    json_encode($profileA, JSON_UNESCAPED_SLASHES),
                    json_encode($profileB, JSON_UNESCAPED_SLASHES),
                    $winner,
                    json_encode($rounds, JSON_UNESCAPED_SLASHES),
                ]
            );
            $battleId = (int)Db::pdo()->lastInsertId();
            $quest['fight_battle_id'] = $battleId;
            // `won` e o RESULTADO real da luta (o oficial devolve-o aqui); estava
            // fixo a true, portanto o cliente nunca via uma derrota.
            $quest['won'] = ($winner === 'a');
            // Linha COMPLETA (inclui profile_a_stats/profile_b_stats) — o cliente le
            // nivel/vida/atributos desses blocos. Sem eles todas as labels do painel
            // de batalha caem para LOC (ver missoes normais: usam battleData()).
            $battleRow = $this->battleData($battleId);
        }

        return ['quest' => $quest, 'battle' => $battleRow];
    }

    /**
     * Idempotente contra double-submit: o client as vezes dispara essa action 2x seguidas
     * (clique duplo / retry de rede, visto em client.log com 200ms de intervalo). A 1a
     * chamada zera ts_complete; sem essa tolerancia a 2a batia no guard e virava dialog de
     * erro. Seguro porque essa action so devolve o preview da recompensa (nao credita
     * moeda/xp nem stat) -- quem credita de verdade e claimTrainingStar, que tem sua propria
     * trava (iterations) contra reclaim duplo.
     */
    public function claimTrainingQuestRewards(): void
    {
        $this->withStoryActivity(fn() => $this->claimTrainingQuestRewardsLocked());
    }

    private function claimTrainingQuestRewardsLocked(): void
    {
        $active = $this->activeTrainingRow();
        if ($active === null) throw new GameError('errClaimTrainingQuestRewardsNoActiveTraining');
        // Idempotente contra duplo-submit: o cliente dispara esta action 2x seguidas
        // (clique duplo / retry de rede). A 1a limpa training_quest_id; sem esta
        // tolerancia a 2a rebentava e virava dialogo de erro. Seguro porque quem
        // credita moeda/xp/stat e o claimTrainingStar, com trava propria.
        $questId = (int)($active['training_quest_id'] ?? 0);
        if ($questId <= 0) {
            return;
        }

        // Avanca a barra AQUI (o oficial soma o training_progress da quest no claim)
        // e limpa training_quest_id -> o cliente ve a quest fechada e sai do painel
        // da batalha. Como a seed da pool usa used_resources, isto tambem gera as
        // 3 ofertas NOVAS que o oficial devolve nesta resposta.
        // used_resources é agora ABSOLUTO (0..needed_energy); encontramos o progresso
        // da quest ativa dentro da pool gerada deterministicamente.
        $neededEnergy = self::trainingNeededEnergy(self::trainingCost((int)$active['stat_type']));
        $questProgress = 0;
        foreach ($this->trainingQuestsData() as $q) {
            if ((int)$q['id'] === $questId) {
                $decoded       = json_decode((string)$q['rewards'], true);
                $questProgress = (int)($decoded['training_progress'] ?? 0);
                break;
            }
        }
        if ($questProgress <= 0) $questProgress = 45; // fallback mínimo
        $progress = min($neededEnergy, (int)$active['used_resources'] + $questProgress);
        Db::exec(
            'UPDATE `training` SET used_resources = ?, training_quest_id = 0, ts_complete = 0 WHERE id = ?',
            [$progress, (int)$active['id']]
        );
        HeroCon::record($this->id(), 30);
    }

    public function claimTrainingStar(): void
    {
        $this->withStoryActivity(fn() => $this->claimTrainingStarLocked());
    }

    private function claimTrainingStarLocked(): void
    {
        $active = $this->activeTrainingRow();
        if ($active === null) throw new GameError('errClaimTrainingStarNoActiveTraining');
        $claimed = (int)$active['iterations'];
        if ($claimed >= 3) throw new GameError('errClaimTrainingStarAlreadyClaimed');
        // Limiares OFICIAIS de `training_ratings` (10/40/100), nao 34/67/100 — sao
        // os mesmos que o cliente usa em get_claimableStar() para abrir o dialogo
        // da recompensa. Divergir fazia o servidor recusar uma estrela que o
        // cliente ja mostrava como disponivel.
        $activeStatType = (int)$active['stat_type'];
        $activeCost     = self::trainingCost($activeStatType);
        $neededEnergy   = self::trainingNeededEnergy($activeCost);
        // Limiar ABSOLUTO: percentagem oficial × needed_energy / 100
        $neededPct   = self::TRAINING_RATINGS[$claimed + 1]['needed_progress']; // 10/40/100
        $neededAbs   = (int)round($neededPct * $neededEnergy / 100);
        $tsEnd   = (int)($active['ts_end'] ?? 0);
        $expired = $tsEnd > 0 && $tsEnd <= GameTime::now();
        if (!$expired && (int)$active['used_resources'] < $neededAbs) {
            throw new GameError('errClaimTrainingStarNotAvailable');
        }
        $newClaimed = $claimed + 1;
        $statColumn = self::STAT_MAP[self::trainingStatType($activeStatType)] ?? null;
        if ($statColumn === null) throw new GameError('errTrainingInvalidTraining');
        // O status FICA a 2 mesmo na 3.a estrela (confirmado no HAR oficial). Poe-lo
        // a 4 aqui tirava o treino do activeTrainingRow(), o active_training_id ia a
        // 0, get_training() devolvia null no cliente e o dialogo "Treino Concluido"
        // nunca abria (showTrainingCompleteDialog exige o objecto do treino).
        // Quem fecha o treino e o finishTraining, disparado pelo botao OK do dialogo.
        Db::exec('UPDATE `training` SET iterations = ? WHERE id = ?', [$newClaimed, (int)$active['id']]);
        if ($newClaimed === 3) HeroCon::record($this->id(), 31);
        // Pontos do atributo TREINADO: escalados com o custo (star1/2=cost×0.25, star3=cost×0.5)
        $statPoints = self::trainingStatPoints($activeCost, $newClaimed);
        Db::exec(
            "UPDATE `character` SET stat_trained_$statColumn = stat_trained_$statColumn + ?,
                                    stat_base_$statColumn    = stat_base_$statColumn + ?
              WHERE id = ?",
            [$statPoints, $statPoints, $this->id()]
        );
        $this->row["stat_trained_$statColumn"] = (int)$this->row["stat_trained_$statColumn"] + $statPoints;
        $this->row["stat_base_$statColumn"]    = (int)$this->row["stat_base_$statColumn"] + $statPoints;

        // MESMO helper (e MESMO sorteio) que preenche rewards_star_N em
        // trainingsData() — anunciar um valor e creditar outro faria o dialogo mentir.
        $bonus  = self::trainingBonusRoll($active);
        $reward = $this->trainingStarReward(self::trainingCost($activeStatType), $newClaimed, $bonus);
        Db::exec(
            'UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ? WHERE id = ?',
            [$reward['coins'], $reward['xp'], $this->id()]
        );
        $this->row['game_currency'] = (int)$this->row['game_currency'] + $reward['coins'];
        $this->row['xp']            = (int)$this->row['xp'] + $reward['xp'];
        // SIDEKICK: parte do XP do heroi partilha com o sidekick ativo (2%).
        Sidekick::giveXpToActive($this->id(), (int)round($reward['xp'] * 0.02));
        $this->syncLevelFromXp();

        // Bonus da 3.a estrela (sorteio estavel): 30% ponto livre / 30% item / 40% nada.
        if ($newClaimed === 3) {
            if ($bonus === 'stat_point') {
                Db::exec('UPDATE `character` SET stat_points_available = stat_points_available + 1 WHERE id = ?', [$this->id()]);
                $this->row['stat_points_available'] = (int)($this->row['stat_points_available'] ?? 0) + 1;
            } elseif ($bonus === 'item') {
                // O item ja existe (criado com a pool, para o cliente poder resolver
                // o id e mostrar o icone), mas so agora vai PARA A MOCHILA — antes
                // ficava so na tabela `items` e nunca chegava ao inventario.
                $this->putItemInBag((int)($active['reward_item_id'] ?? 0));
            }
        }

        // 3.a estrela: regista ts_last_training_finished — e o que faz o cliente
        // sair do ecra de progresso e abrir o dialogo "Treino Concluido".
        if ($newClaimed === 3) {
            Db::exec('UPDATE `character` SET ts_last_training_finished = ? WHERE id = ?', [GameTime::now(), $this->id()]);
            $this->row['ts_last_training_finished'] = GameTime::now();
        }
    }

    private function activeTrainingRow(bool $includeComplete = false): ?array
    {
        $statuses = $includeComplete ? '2,4' : '2';
        return Db::row("SELECT * FROM `training` WHERE character_id = ? AND status IN ($statuses) ORDER BY id DESC LIMIT 1", [$this->id()]);
    }

    public function startTraining(int $trainingId): void
    {
        $this->withStoryActivity(fn() => $this->startTrainingLocked($trainingId));
    }

    private function startTrainingLocked(int $trainingId): void
    {
        $this->syncTrainingForLevel();
        $statType     = $trainingId % 1000;  // cost*10 + base
        $base         = self::trainingStatType($statType);  // 1-4
        $cost         = self::trainingCost($statType);       // 1-9
        $col = self::STAT_MAP[$base] ?? null;
        if ($col === null || $cost < 1 || $cost > 9) {
            throw new GameError('errTrainingInvalidTraining');
        }
        if ((int)$this->row['training_count'] < $cost) {
            throw new GameError('errRemoveTrainingNotEnough');
        }
        // Um treino cujo tempo ja acabou NAO bloqueia o proximo: fecha-se aqui.
        // Sem isto ficava preso em status=2 para sempre, o startTraining rebentava
        // com errStartTrainingAlreadyActive e a energia nunca era reposta a 30/30.
        $current = $this->activeTrainingRow();
        if ($current !== null) {
            $tsEnd = (int)($current['ts_end'] ?? 0);
            if ($tsEnd > 0 && $tsEnd <= GameTime::now()) {
                Db::exec('UPDATE `training` SET status = 4 WHERE id = ?', [(int)$current['id']]);
            } else {
                throw new GameError('errStartTrainingAlreadyActive');
            }
        }
        $now      = GameTime::now();
        $duration = self::trainingDuration($cost);
        // Sorteio do bonus da 3.a estrela ANTES de mexer em ts_last_training: e esse
        // timestamp que entra na seed da oferta. Persistido para o treino entregar
        // exatamente o que a oferta mostrou no painel.
        $bonusName = $this->trainingOfferBonus($trainingId);
        $bonus     = array_search($bonusName, self::BONUS_ROLL_NAMES, true);
        // O item ja foi criado com a pool — fica preso a este treino para nao ser
        // apagado no proximo refresh (ver trainingPoolRewardItems()).
        $rewardItemId = $bonusName === 'item'
            ? (int)($this->trainingPoolRewardItems()[$trainingId] ?? 0)
            : 0;
        Db::exec(
            'INSERT INTO `training` (character_id, status, stat_type, ts_creation, ts_complete, ts_end, iterations, used_resources, bonus_roll, reward_item_id) VALUES (?, 2, ?, ?, 0, ?, 0, 0, ?, ?)',
            [$this->id(), $statType, $now, $now + $duration, (int)$bonus, $rewardItemId]
        );
        // Barra de energia de treino: 30/30 fixo. Os trofeus tipo 3 (Motivacao) dao
        // bonus a training_count (unidades), NAO a esta barra.
        $maxEnergy = (int)($this->row['max_training_energy'] ?? 30) * BonusEvents::factor(10);
        Db::exec(
            "UPDATE `character`
                SET training_count = training_count - ?,
                    training_progress_value_$col = 0,
                    training_progress_end_$col = 3,
                    ts_last_training = ?,
                    training_energy = ?,
                    ts_last_training_energy_change = ?
              WHERE id = ?",
            [$cost, GameTime::now(), $maxEnergy, GameTime::now(), $this->id()]
        );
        $this->row['training_count']                 = (int)$this->row['training_count'] - $cost;
        $this->row['ts_last_training']               = GameTime::now();
        $this->row['training_energy']                = $maxEnergy;
        $this->row['ts_last_training_energy_change'] = GameTime::now();
        StoryDungeon::record($this->id(), StoryDungeon::TRAINING_UNITS, $cost);
        EventQuests::record($this->id(), EventQuests::TRAINING, $cost);
    }

    /** Missao ativa do personagem, ou erro de protocolo se nao houver uma. */
    public function activeQuest(): array
    {
        $questId = (int)($this->row['active_quest_id'] ?? 0);
        if ($questId <= 0) {
            throw new GameError('errNoActiveQuest');
        }
        $q = Db::row('SELECT * FROM `quests` WHERE id = ? AND character_id = ?', [$questId, $this->id()]);
        if ($q === null) {
            throw new GameError('errNoActiveQuest');
        }
        return $q;
    }

    /** Finaliza instantaneamente a missao ativa. O cliente chama sem quest_id. */
    public function instantFinishQuest(int $premiumCost = 0): void
    {
        $q = $this->activeQuest();
        if ((int)$q['status'] !== 2) {
            return;
        }
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        Db::exec('UPDATE `quests` SET ts_complete = 0 WHERE id = ?', [(int)$q['id']]);
        if ($premiumCost > 0) {
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ?', [$premiumCost, $this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
        }
    }

    /**
     * Marca a missao ativa como concluida quando o tempo acabou.
     * Fight quests recebem um battle_id simples para o cliente abrir a tela de luta.
     */
    public function checkActiveQuestComplete(): array
    {
        return Missiles::transaction([$this->id()], function (): array {
            $fresh = self::load($this->id());
            return $fresh->checkActiveQuestCompleteLocked();
        });
    }

    private function checkActiveQuestCompleteLocked(): array
    {
        $q = $this->activeQuest();
        if ((int)$q['status'] >= 3) {
            return $q;
        }
        if ((int)$q['ts_complete'] > GameTime::now()) {
            return $q;
        }

        $battleId = 0;
        if ((int)$q['type'] === 2) {
            // COMBATE SIMULADO tambem nas missoes de luta: o resultado estava fixo
            // em 'a' com um unico round de 10 de dano, logo o jogador nunca perdia.
            // O NPC escala com a dificuldade da missao (fight_difficulty 1..3).
            $profileA = $this->battleProfile('a');
            $profileB = $this->questNpcProfile((int)$q['fight_difficulty']);
            $fight = new Fight($profileA, $profileB);
            $winner = $fight->fight() === 1 ? 'a' : 'b';
            // Sem profile_*_appearance aqui: nas missoes o cliente desenha o
            // inimigo a partir de `quest.fight_npc_identifier`, nao do bloco de
            // aparencia (esse e do duelo, ver BUG-000005).
            $rounds = ['rounds' => $fight->rounds()];
            Db::exec(
                "INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    GameTime::now(),
                    json_encode($profileA, JSON_UNESCAPED_SLASHES),
                    json_encode($profileB, JSON_UNESCAPED_SLASHES),
                    $winner,
                    json_encode($rounds, JSON_UNESCAPED_SLASHES),
                ]
            );
            $battleId = (int)Db::pdo()->lastInsertId();

            // Perder a luta corta a recompensa para 10%
            // (constants.fight_quest_reward_lost_xp / _lost_coin = 0.1), como no
            // checkForQuestComplete.req.php oficial. Sem isto perder nao custava nada.
            if ($winner === 'b') {
                $rewards = json_decode((string)$q['rewards'], true);
                if (is_array($rewards)) {
                    unset($rewards['story_dungeon_item']);
                    foreach (['coins', 'xp'] as $k) {
                        if (isset($rewards[$k])) $rewards[$k] = max(1, (int)round($rewards[$k] * 0.1));
                    }
                    Db::exec('UPDATE `quests` SET rewards = ? WHERE id = ?',
                        [json_encode($rewards, JSON_UNESCAPED_SLASHES), (int)$q['id']]);
                }
            }
        }

        Db::exec('UPDATE `quests` SET status = 4, fight_battle_id = ? WHERE id = ?', [$battleId, (int)$q['id']]);
        return Db::row('SELECT * FROM `quests` WHERE id = ?', [(int)$q['id']]) ?? $q;
    }

    public function battleData(int $battleId): ?array
    {
        if ($battleId <= 0) return null;
        return Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]);
    }

    /** Aplica recompensa da missao concluida e gera um novo pool simples para o stage atual. */
    public function claimQuestRewards(): void
    {
        $this->withStoryActivity(fn() => $this->claimQuestRewardsLocked());
    }

    private function claimQuestRewardsLocked(): void
    {
        try {
            $q = $this->activeQuest();
        } catch (GameError $e) {
            throw new GameError('errClaimQuestRewardsNoActiveQuest');
        }
        if ((int)$q['status'] < 3) {
            throw new GameError('errClaimQuestRewardsInvalidStatus');
        }
        $rewards = json_decode((string)$q['rewards'], true);
        if (!is_array($rewards)) $rewards = [];
        $coins = (int)round((int)($rewards['coins'] ?? 0) * BonusEvents::factor(8));
        // Apenas missões concluídas e resgatadas contam nos patamares diários.
        DailyEnergy::record($this->id(), (int)$q['energy_cost']);
        $xp = (int)round((int)($rewards['xp'] ?? 0) * BonusEvents::factor(7));

        Db::exec(
            'UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ?, active_quest_id = 0 WHERE id = ?',
            [$coins, $xp, $this->id()]
        );
        $this->row['xp'] = (int)$this->row['xp'] + $xp;
        // SIDEKICK: parte do XP da missao partilha com o sidekick ativo (2%).
        Sidekick::giveXpToActive($this->id(), (int)round($xp * 0.02));
        $this->syncLevelFromXp();   // atualiza level/score_level e concede stat points de nivel
        $this->syncTutorialForLevel();
        // Progrcao do tutorial: a primeira missao concluida desbloqueia o passo a seguir
        // (na navegacao do cliente, `first_mission` desliga a seta na missao e liga a
        // seta na loja/mercado). O cliente NAO envia esta flag; e o servidor que a deriva.
        $this->setTutorialFlag('first_mission', true);
        Herobook::record($this->id(), 13);
        if ((int)$q['type'] === 1) Herobook::record($this->id(), 12);
        $won = (int)$q['type'] === 1 || Db::value('SELECT winner FROM battle WHERE id = ?', [(int)$q['fight_battle_id']]) === 'a';
        if ($won) HeroCon::record($this->id(), 18);
        if ((int)$q['type'] === 2) HeroCon::record($this->id(), $won ? 20 : 21);
        if ($won) {
            EventQuests::record($this->id(), (int)$q['type'] === 1 ? EventQuests::TIME : EventQuests::FIGHT_WON, 1, (string)$q['identifier']);
            EventQuests::record($this->id(), EventQuests::QUEST, 1, (string)$q['identifier']);
            EventQuests::collectQuestItem($this->id(), $rewards);
            HeroCon::collectItem($this->id(), $rewards);
            StoryDungeon::record($this->id(), (int)$q['type'] === 1 ? StoryDungeon::TIME_QUEST : StoryDungeon::FIGHT_QUEST);
            if (!empty($rewards['story_dungeon_item'])) {
                StoryDungeon::record($this->id(), StoryDungeon::QUEST_ITEM, 1, (string)$rewards['story_dungeon_item']);
            }
        }

        // O tutorial desbloqueia a loja após a primeira missão, garantindo nível 2.
        if ((int)$this->row['level'] < 2) {
            $table = self::xpTable();
            $xpL2 = (int)($table[2] ?? 547);
            if ($xpL2 > (int)$this->row['xp']) {
                Db::exec('UPDATE `character` SET xp = ? WHERE id = ?', [$xpL2, $this->id()]);
                $this->row['xp'] = $xpL2;
                $this->syncLevelFromXp();   // sobe a nivel 2 e concede os stat points
            }
        }
        $newLevel = (int)$this->row['level'];
        // Concluir uma missao renova o pool SO DESTA cidade. As outras cidades
        // guardam as suas missoes intactas (ver ensureQuestPool) -- antes o DELETE
        // era sobre todas as quests do personagem.
        $stage = max(1, (int)($this->row['current_quest_stage'] ?? 1));
        Db::exec('DELETE FROM `quests` WHERE character_id = ? AND stage = ?', [$this->id(), $stage]);
        self::insertQuestPool($this->id(), $newLevel, 3, $stage);
    }

    /**
     * Perfil de batalha a partir das stats REAIS do personagem (base + treinado +
     * equipamento -- mesma formula de stat_total_* usada no character overlay, pra
     * bater com o que o proprio perfil do jogador mostra). $profile='b' aplica um
     * penalidade artificial de -1 (usado so quando NAO ha um oponente real, ex.:
     * luta de missao contra NPC -- duelo/liga chamam com 'a' no personagem real,
     * seja ele atacante ou oponente, e so relabelam o campo 'profile' depois).
     */
    /**
     * Perfil do NPC de uma missao de luta. Porte de `NPC::randomiseQuestStats`:
     * o inimigo e uma PERCENTAGEM dos atributos do proprio jogador, conforme a
     * dificuldade (constants.fight_quest_npc_stat_percentage_*):
     *     facil   89% .. 94%
     *     media   93% .. 96%
     *     dificil 97% .. 98.5%
     * Ou seja, esta sempre um pouco abaixo do jogador — perder e possivel mas
     * raro, e nas dificeis e mesmo disputado.
     */
    public function questNpcProfile(int $difficulty): array
    {
        $range = [
            1 => [0.89, 0.94],
            2 => [0.93, 0.96],
            3 => [0.97, 0.985],
        ][$difficulty] ?? [0.89, 0.94];

        $mine = $this->battleProfile('a');
        $pct = static function () use ($range): float {
            return $range[0] + ($range[1] - $range[0]) * (random_int(0, 1000) / 1000);
        };
        $stamina  = max(1, (int)round((int)$mine['stamina'] * $pct()));
        $strength = max(1, (int)round((int)$mine['strength'] * $pct()));

        return [
            'profile'        => 'b',
            'level'          => (int)$this->row['level'],
            'stamina'        => $stamina,
            'strength'       => $strength,
            'criticalrating' => max(1, (int)round((int)$mine['criticalrating'] * $pct())),
            'dodgerating'    => max(1, (int)round((int)$mine['dodgerating'] * $pct())),
            'weapondamage'   => 0,
            'hitpoints'      => $stamina * Fight::HP_SCALE,
            'damage_normal'  => $strength,
            'damage_bonus'   => $strength,
            'chance_critical' => 0.1,
            'chance_dodge'    => 0.1,
        ];
    }

    public function battleProfile(string $profile): array
    {
        // Inclui o bonus da guilda, tal como o `stat_total_*` mostrado no perfil --
        // senao a batalha usaria atributos menores do que os que o jogador ve.
        // O bonus de conjunto entra como linha ADITIVA propria (igual ao cliente,
        // que soma get_itemSet* ao total), nunca multiplicada pelo booster/guilda.
        // O sidekick idem: batalhas de grupo esperam TOTAIS prontos ja com a
        // parcela dele incluida (docs/sidekicks/extract/03 §8.3).
        $equip = $this->equipBonus();
        $sidekickBonus = $this->sidekickBonus();
        $stamina = (int)$this->row['stat_base_stamina'] + (int)($this->row['stat_trained_stamina'] ?? 0) + ($equip['stamina'] ?? 0) + $this->statBoostBonus('stamina') + $this->itemSetBonus('stamina') + ($sidekickBonus['stamina'] ?? 0);
        $strength = (int)$this->row['stat_base_strength'] + (int)($this->row['stat_trained_strength'] ?? 0) + ($equip['strength'] ?? 0) + $this->statBoostBonus('strength') + $this->itemSetBonus('strength') + ($sidekickBonus['strength'] ?? 0);
        $critical = (int)$this->row['stat_base_critical_rating'] + (int)($this->row['stat_trained_critical_rating'] ?? 0) + ($equip['critical_rating'] ?? 0) + $this->statBoostBonus('critical_rating') + $this->itemSetBonus('critical_rating') + ($sidekickBonus['critical_rating'] ?? 0);
        $dodge = (int)$this->row['stat_base_dodge_rating'] + (int)($this->row['stat_trained_dodge_rating'] ?? 0) + ($equip['dodge_rating'] ?? 0) + $this->statBoostBonus('dodge_rating') + $this->itemSetBonus('dodge_rating') + ($sidekickBonus['dodge_rating'] ?? 0);
        $weaponDamage = (int)($equip['weapon_damage'] ?? 0);
        if ($profile === 'b') {
            $stamina = max(1, $stamina - 1);
            $strength = max(1, $strength - 1);
            $critical = max(1, $critical - 1);
            $dodge = max(1, $dodge - 1);
        }
        // SIDEKICK: mesmo subobjeto de battleAppearance() — o Fight le
        // {level, stage1..3_skill_id} daqui para disparar as skills na luta.
        // Sem ativo fica null e nenhuma skill entra no combate.
        $sidekickBonusSidekick = null;
        $sk = Sidekick::activeRow($this->id());
        if ($sk !== null) {
            $sidekickBonusSidekick = [
                'identifier'       => (string)$sk['identifier'],
                'name'             => (string)$sk['name'],
                'level'            => (int)$sk['level'],
                'stage1_skill_id'  => (int)$sk['stage1_skill_id'],
                'stage2_skill_id'  => (int)$sk['stage2_skill_id'],
                'stage3_skill_id'  => (int)$sk['stage3_skill_id'],
                'sewed_identifier' => (string)($sk['sewed_identifier'] ?? ''),
                'sewed_level'      => (int)($sk['sewed_level'] ?? 0),
            ];
        }
        return [
            'profile' => $profile,
            'missiles' => Missiles::snapshot($this->id()),
            'level' => (int)$this->row['level'],
            'stamina' => $stamina,
            'strength' => $strength,
            'criticalrating' => $critical,
            'dodgerating' => $dodge,
            // dano de arma vem da arma equipada; `damage_normal = forca + arma`
            // (Player::calculateEntity oficial). Estava fixo a 0, logo a arma nao
            // fazia diferenca nenhuma no combate.
            'weapondamage' => $weaponDamage,
            'hitpoints' => $stamina * Fight::HP_SCALE,
            'damage_normal' => $strength + $weaponDamage,
            'damage_bonus' => $strength + $weaponDamage,
            'chance_critical' => 0.1,
            'chance_dodge' => 0.1,
            // SIDEKICK: o Fight le {level, stage1..3_skill_id} daqui para aplicar
            // as skills (o cliente ignora chaves extra em profile_X).
            'sidekick' => $sidekickBonusSidekick,
        ];
    }

    /** Casta cada campo de $row para o tipo do mesmo campo em $tpl (mantendo extras como estao). */
    private static function castRowLike(array $tpl, array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[$k] = array_key_exists($k, $tpl) ? self::castLike($tpl[$k], $v) : $v;
        }
        return $out;
    }

    /**
     * Aparência de batalha (usada no bloco `rounds` da battle: profile_a_appearance /
     * profile_b_appearance). O cliente (Battle.calculateRounds) exige estas chaves
     * no JSON de rounds para renderizar os lutadores; sem elas o painel de batalha
     * crasha (tutorial/duelo "preso em combate").
     */
    public function battleAppearance(): array
    {
        $out = [
            'name' => (string)($this->row['name'] ?? ''),
            'gender' => (string)($this->row['gender'] ?? 'm'),
            'appearance_skin_color' => (int)($this->row['appearance_skin_color'] ?? 1),
            'appearance_hair_color' => (int)($this->row['appearance_hair_color'] ?? 1),
            'appearance_hair_type' => (int)($this->row['appearance_hair_type'] ?? 1),
            'appearance_head_type' => (int)($this->row['appearance_head_type'] ?? 1),
            'appearance_eyes_type' => (int)($this->row['appearance_eyes_type'] ?? 1),
            'appearance_eyebrows_type' => (int)($this->row['appearance_eyebrows_type'] ?? 1),
            'appearance_nose_type' => (int)($this->row['appearance_nose_type'] ?? 1),
            'appearance_mouth_type' => (int)($this->row['appearance_mouth_type'] ?? 1),
            'appearance_facial_hair_type' => (int)($this->row['appearance_facial_hair_type'] ?? 0),
            'appearance_decoration_type' => (int)($this->row['appearance_decoration_type'] ?? 0),
            'show_mask' => (bool)($this->row['show_mask'] ?? true),
            'show_cape' => (bool)($this->row['show_cape'] ?? true),
        ];

        // ROUPA: o cliente desenha as pecas equipadas a partir do IDENTIFIER de
        // cada uma, dentro deste bloco (a captura oficial tem, p.ex., "belt":
        // "belt_extension_cord1"). Sem estas chaves os lutadores apareciam sem
        // equipamento nenhum no duelo.
        // Como no `DuelBattle::characterAppearance()` oficial, arma (6) e gadget (7)
        // sao SALTADOS -- nao se desenham no corpo.
        $slots = [
            'mask_item_id' => 'mask', 'cape_item_id' => 'cape', 'suit_item_id' => 'suit',
            'belt_item_id' => 'belt', 'boots_item_id' => 'boots', 'missiles_item_id' => 'missiles',
            'missiles1_item_id' => 'missiles1', 'missiles2_item_id' => 'missiles2',
            'missiles3_item_id' => 'missiles3', 'missiles4_item_id' => 'missiles4',
        ];
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$this->id()]);
        if ($inv !== null) {
            $ids = [];
            foreach ($slots as $col => $key) {
                $id = (int)($inv[$col] ?? 0);
                if ($id > 0) $ids[$id] = $key;
            }
            if ($ids !== []) {
                $in = implode(',', array_map('intval', array_keys($ids)));
                $rows = Db::rows(
                    "SELECT id, identifier FROM `items` WHERE id IN ($in) AND character_id = ?",
                    [$this->id()]
                );
                foreach ($rows as $r) {
                    $key = $ids[(int)$r['id']] ?? null;
                    if ($key !== null) $out[$key] = (string)$r['identifier'];
                }
            }
        }

        // SIDEKICK: subobjeto lido SEM guarda quando presente
        // (docs/sidekicks/extract/03 §8.2) — {identifier, name, level,
        // stage1..3_skill_id} obrigatorios; sewed_* com default. Se nao ha
        // ativo, OMITE-SE a chave por completo.
        $sidekick = Sidekick::activeRow($this->id());
        if ($sidekick !== null) {
            $out['sidekick'] = [
                'identifier'       => (string)$sidekick['identifier'],
                'name'             => (string)$sidekick['name'],
                'level'            => (int)$sidekick['level'],
                'stage1_skill_id'  => (int)$sidekick['stage1_skill_id'],
                'stage2_skill_id'  => (int)$sidekick['stage2_skill_id'],
                'stage3_skill_id'  => (int)$sidekick['stage3_skill_id'],
                'sewed_identifier' => (string)($sidekick['sewed_identifier'] ?? ''),
                'sewed_level'      => (int)($sidekick['sewed_level'] ?? 0),
            ];
        }
        return $out;
    }
    /**
     * Lista de oponentes de duelo: contas REAIS registadas (`character` com user_id <> 0).
     * Monta o resumo esperado pelo cliente, tipado pelo template $tplOpp.
     */
    public static function duelOpponents(int $excludeId, array $tplOpp, int $limit = 10): array
    {
        // JOGADORES REAIS (user_id <> 0), nao os NPCs semeados: a lista de duelo
        // mostrava so as 10 contas fake do seed (user_id = 0).
        $rows = Db::rows(
            'SELECT * FROM `character` WHERE user_id <> 0 AND id <> ? ORDER BY RAND() LIMIT ' . (int)$limit,
            [$excludeId]
        );
        $list = [];
        foreach ($rows as $r) {
            // Atributos TOTAIS (base + treino + equipamento + boosters/guilda),
            // os mesmos que o oponente ve no proprio perfil -- antes mandava so a
            // base, entao o tooltip subestimava o adversario.
            $opp = self::load((int)$r['id']);
            $profile = $opp->battleProfile('a');
            $sta = (int)$profile['stamina'];
            $str = (int)$profile['strength'];
            $crit= (int)$profile['criticalrating'];
            $dg  = (int)$profile['dodgerating'];
            $opp = [
                'id'                         => (int)$r['id'],
                'server_id'                  => 'br30',
                'name'                       => (string)$r['name'],
                'level'                      => (int)$r['level'],
                'honor'                      => (int)$r['honor'],
                'gender'                     => (string)$r['gender'],
                'has_missile'                => !empty($profile['missiles']),
                'stat_total_stamina'         => $sta,
                'stat_total_strength'        => $str,
                'stat_total_critical_rating' => $crit,
                'stat_total_dodge_rating'    => $dg,
                'stat_weapon_damage'         => (int)$profile['weapondamage'],
                'online_status'              => (\HeroZero\GameTime::now() - (int)($r['ts_last_action'] ?? 0)) < 300 ? 1 : 2,
                'total_stats'                => $sta + $str + $crit + $dg,
            ];
            // garante exatamente a shape/tipos do template.
            $list[] = $tplOpp ? self::castRowLike($tplOpp, $opp) : $opp;
        }
        return $list;
    }

    /**
     * Inicia uma quest: valida posse/energia, marca a quest como INICIADA (status=2) com
     * ts_complete = agora + duration, aponta active_quest_id no personagem e debita energia.
     * O cliente (Qe): get_isStarted = status==2; get_remainingSeconds = ts_complete - now;
     * o painel quest_progress usa character.get_activeQuest() (=_quests[active_quest_id]).
     */
    public function startQuest(int $questId): void
    {
        $this->withStoryActivity(fn() => $this->startQuestLocked($questId));
    }

    private function startQuestLocked(int $questId): void
    {
        $this->syncDailyReset();
        if ((int)($this->row['active_worldboss_attack_id'] ?? 0) > 0
            && (int)($this->row['ts_active_multitasking_boost_expires'] ?? 0) <= GameTime::now()) {
            throw new GameError('errStartQuestActiveWorldbossAttackFound');
        }
        $q = Db::row('SELECT * FROM `quests` WHERE id = ? AND character_id = ?', [$questId, $this->id()]);
        if ($q === null) {
            throw new GameError('errStartQuestInvalidQuest');
        }
        $q = QuestNpcs::repairOffer($this->id(), $q);
        if ((int)$q['status'] === 2 && (int)$this->row['active_quest_id'] === $questId) {
            return; // retry da mesma missão não conta energia novamente
        }
        if ((int)$q['status'] !== 1 || (int)$this->row['active_quest_id'] > 0) {
            throw new GameError('errStartQuestInvalidQuest');
        }
        // O energy_cost e duration ja foram gerados com o energetico aplicado
        // (ver insertQuestPool). Aqui debita-se o valor exacto sem transformacoes.
        $cost = (int)$q['energy_cost'];
        if ((int)$this->row['quest_energy'] < $cost) {
            throw new GameError('errRemoveQuestEnergyNotEnough');
        }
        $tsComplete = GameTime::now() + max(0, (int)$q['duration']);
        Db::exec('UPDATE `quests` SET status = 2, ts_complete = ? WHERE id = ?', [$tsComplete, $questId]);
        Db::exec('UPDATE `character` SET active_quest_id = ?, quest_energy = quest_energy - ? WHERE id = ?',
                 [$questId, $cost, $this->id()]);
        $this->row['active_quest_id'] = $questId;
        $this->row['quest_energy']    = (int)$this->row['quest_energy'] - $cost;
        Herobook::record($this->id(), 16, $cost);
        StoryDungeon::record($this->id(), StoryDungeon::ENERGY, $cost);
        EventQuests::record($this->id(), EventQuests::ENERGY, $cost);
        HeroCon::record($this->id(), 19, $cost);
        if ((int)$q['type'] === 1) HeroCon::record($this->id(), 6, $cost);
    }

    /** Cancela a missao ativa sem recompensa (a energia gasta ao iniciar nao e devolvida). */
    public function abortQuest(): void
    {
        $q = $this->activeQuest();
        Db::exec('UPDATE `quests` SET status = 1, ts_complete = 0 WHERE id = ?', [(int)$q['id']]);
        Db::exec('UPDATE `character` SET active_quest_id = 0 WHERE id = ?', [$this->id()]);
        $this->row['active_quest_id'] = 0;
    }

    /** Aceita uma ocorrência real do calendário de eventos de coleção. */
    public function assignEventQuest(string $identifier): void
    {
        EventQuests::assign($this->id(), $identifier);
        $this->row['event_quest_id'] = (int)Db::value('SELECT event_quest_id FROM `character` WHERE id=?', [$this->id()]);
    }

    /** Recarrega o contador de sessoes de treino (premium). */
    /**
     * Recarrega +15 de training_energy (constants: training_energy_refill_amount=15).
     * Custo: 1 premium (donut).
     */
    public function buyTrainingEnergy(int $premiumCost): void
    {
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        $maxEnergy = GuildArtifacts::effectiveMax(
            'training_energy',
            (int)($this->row['max_training_energy'] ?? 30),
            (int)($this->row['guild_id'] ?? 0)
        ) * BonusEvents::factor(10);
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT bonus_training_refill'); else $pdo->beginTransaction();
        try {
            if ($premiumCost > 0) {
                $affected = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$premiumCost, $this->userId(), $premiumCost]
                );
                if ($affected !== 1) {
                    throw new GameError('errRemovePremiumCurrencyNotEnough');
                }
                $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
            }
            // Calcula energia viva actual (stored + regeneração desde a última mudança)
            $current  = $this->liveTrainingEnergy();
            $newEnergy = min($maxEnergy, $current + 15);
            Db::exec(
                'UPDATE `character` SET training_energy = ?, ts_last_training_energy_change = ? WHERE id = ?',
                [$newEnergy, GameTime::now(), $this->id()]
            );
            $this->row['training_energy']                   = $newEnergy;
            $this->row['ts_last_training_energy_change']    = GameTime::now();
            if ($nested) $pdo->exec('RELEASE SAVEPOINT bonus_training_refill'); else $pdo->commit();
        } catch (\Throwable $e) {
            if ($nested) { $pdo->exec('ROLLBACK TO SAVEPOINT bonus_training_refill'); $pdo->exec('RELEASE SAVEPOINT bonus_training_refill'); }
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Finaliza instantaneamente o ciclo de treino ativo (premium), completando os 3 stars. */
    public function finishTraining(int $premiumCost): void
    {
        // Tenta encontrar treino ativo (em curso) ou ja concluido (todas as estrelas).
        $active = $this->activeTrainingRow(includeComplete: true);

        // Com as 3 estrelas reclamadas e o "OK" do dialogo "Treino Concluido": fecha
        // sem custo. A condicao e `iterations >= 3` e NAO `status === 4` — o status
        // fica a 2 ate aqui (ver claimTrainingStar). O oficial marca status=3.
        if ($active !== null && (int)$active['iterations'] >= 3) {
            $now = GameTime::now();
            Db::exec('UPDATE `character` SET ts_last_training_finished = ? WHERE id = ?', [$now, $this->id()]);
            Db::exec('UPDATE `training` SET status = 3 WHERE id = ?', [(int)$active['id']]);
            $this->row['ts_last_training_finished'] = $now;
            return;
        }

        if ($active === null) {
            // Sem treino ativo nem concluido — noop silencioso (idempotente).
            return;
        }

        // Treino em curso: skip premium.
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($premiumCost > 0) {
                $affected = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$premiumCost, $this->userId(), $premiumCost]
                );
                if ($affected !== 1) {
                    throw new GameError('errRemovePremiumCurrencyNotEnough');
                }
                $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
            }
            Db::exec('UPDATE `training` SET used_resources = 100, iterations = 3, status = 4 WHERE id = ?', [(int)$active['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Identifier -> [slot, base duration em segundos p/ producao]. */
    public const HIDEOUT_STARTER_ROOMS = [
        'main_building'       => 0,
        'generator'            => 2,
        'glue_production'      => 4,
        'stone_production'     => 6,
        'attacker_production'  => 8,
        'defender_production'  => 10,
    ];

    /** Garante as 6 salas iniciais do hideout (idempotente) e devolve todas do personagem. */
    public function ensureHideoutRooms(): array
    {
        $existing = Db::rows('SELECT * FROM `hideout_rooms` WHERE character_id = ? ORDER BY id', [$this->id()]);
        if ($existing !== []) {
            return $existing;
        }
        $now = GameTime::now();
        foreach (self::HIDEOUT_STARTER_ROOMS as $identifier => $slot) {
            Db::exec(
                'INSERT INTO `hideout_rooms`
                    (character_id, identifier, slot, status, level, current_resource_amount,
                     max_resource_amount, ts_last_resource_change, ts_activity_end, current_generator_level)
                 VALUES (?, ?, ?, 1, 1, 0, 100, ?, 0, 0)',
                [$this->id(), $identifier, $slot, $now]
            );
        }
        return Db::rows('SELECT * FROM `hideout_rooms` WHERE character_id = ? ORDER BY id', [$this->id()]);
    }

    /** Constroi uma sala nova no slot indicado (custa moeda de jogo). */
    public function buildHideoutRoom(string $identifier, int $slot): array
    {
        if ($identifier === '') {
            throw new GameError('errRequestInvalidParameter');
        }
        $this->ensureHideoutRooms();
        $atSlot = Db::row('SELECT id FROM `hideout_rooms` WHERE character_id = ? AND slot = ?', [$this->id(), $slot]);
        if ($atSlot !== null) {
            throw new GameError('errBuildHideoutRoomSlotOccupied');
        }
        $cost = 100;
        if ($this->gameCurrency() < $cost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$cost, $this->id()]);
        $this->row['game_currency'] = $this->gameCurrency() - $cost;

        $now = GameTime::now();
        Db::exec(
            'INSERT INTO `hideout_rooms`
                (character_id, identifier, slot, status, level, current_resource_amount,
                 max_resource_amount, ts_last_resource_change, ts_activity_end, current_generator_level)
             VALUES (?, ?, ?, 1, 1, 0, 100, ?, 0, 0)',
            [$this->id(), $identifier, $slot, $now]
        );
        $roomId = (int)Db::pdo()->lastInsertId();
        return Db::row('SELECT * FROM `hideout_rooms` WHERE id = ?', [$roomId]) ?? [];
    }

    private function ownHideoutRoom(int $roomId): array
    {
        $room = Db::row('SELECT * FROM `hideout_rooms` WHERE id = ? AND character_id = ?', [$roomId, $this->id()]);
        if ($room === null) {
            throw new GameError('errRequestInvalidParameter');
        }
        return $room;
    }

    /** Sobe o nivel da sala (custa moeda de jogo, escala com o nivel atual). */
    public function upgradeHideoutRoom(int $roomId): array
    {
        $room = $this->ownHideoutRoom($roomId);
        $cost = 50 * ((int)$room['level'] + 1);
        if ($this->gameCurrency() < $cost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$cost, $this->id()]);
        $this->row['game_currency'] = $this->gameCurrency() - $cost;
        Db::exec('UPDATE `hideout_rooms` SET level = level + 1 WHERE id = ?', [$roomId]);
        return Db::row('SELECT * FROM `hideout_rooms` WHERE id = ?', [$roomId]) ?? [];
    }

    /** Inicia a producao de recursos da sala (fica ocupada ate ts_activity_end). */
    public function startHideoutRoomProduction(int $roomId, int $productionCount): array
    {
        $room = $this->ownHideoutRoom($roomId);
        if ((int)$room['ts_activity_end'] > GameTime::now()) {
            throw new GameError('errStartHideoutRoomProductionAlreadyActive');
        }
        $duration = max(1, $productionCount) * 300; // 5min por ciclo de producao
        $endsAt = GameTime::now() + $duration;
        Db::exec('UPDATE `hideout_rooms` SET ts_activity_end = ? WHERE id = ?', [$endsAt, $roomId]);
        $room['ts_activity_end'] = $endsAt;
        return $room;
    }

    /** Finaliza instantaneamente a atividade da sala (premium). */
    public function instantFinishHideoutRoomActivity(int $roomId, int $premiumCost): array
    {
        $room = $this->ownHideoutRoom($roomId);
        if ((int)$room['ts_activity_end'] <= GameTime::now()) {
            throw new GameError('errInstantFinishHideoutRoomActivityNotActive');
        }
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        if ($premiumCost > 0) {
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ?', [$premiumCost, $this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
        }
        Db::exec('UPDATE `hideout_rooms` SET ts_activity_end = 0 WHERE id = ?', [$roomId]);
        $room['ts_activity_end'] = 0;
        return $room;
    }

    /** @deprecated Usa Season::ensureProgress() directamente. */
    public function ensureSeasonProgress(): array
    {
        return Season::ensureProgress($this->id());
    }

    /** Participação real da Caça às Toupeiras; blobs antigos são preservados. */
    public function ensureTreasureEventState(): array { return EventTreasures::state($this->id()); }
    public function assignTreasureEvent(string $identifier): void { EventTreasures::assign($this->id(), $identifier); }

    /**
     * Estado da dungeon de historia (JSON em character.story_dungeon_state):
     * {"active":{"index":N,"step":N,"status":2|3|4,"points":N,"rewards":{...}}|null,"completed":["1_1",...]}.
     * completed usa o formato "{index}_{step}" que o cliente compara em
     * hasStoryDungeonStepCompleted.
     */
    public function storyDungeonState(): array
    {
        $raw = json_decode((string)($this->row['story_dungeon_state'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        return $raw + ['active' => null, 'completed' => []];
    }

    /** Id sintetico e estavel do passo (o cliente so precisa de consistencia). */
    public function storyDungeonStepId(int $index, int $step): int
    {
        return $this->id() * 100 + $index * 10 + $step;
    }

    public function startStoryDungeonStep(int $index, int $step): void
    {
        $this->withStoryActivity(fn() => StoryDungeon::start($this->id(), $index, $step));
    }

    public function claimStoryDungeonStepReward(): void
    {
        $this->withStoryActivity(function (): void {
            $active = $this->storyDungeonState()['active'] ?? [];
            StoryDungeon::claim($this->id(), (int)($active['index'] ?? 0), (int)($active['step'] ?? 0), false);
        });
    }

    /** Estado do casino relido da BD; o resultado é protegido pelo Casino. */
    public function casinoState(): array { return Casino::state($this->id()); }

    // =====================================================================
    // Goals (character.goal_stats, JSON {chave: contador}) — so os contadores
    // de SIDEKICK sao vivos; o resto do painel de metas continua estatico.
    // Mesmo padrao do servidor oficial antigo (Player::incrementGoalStat).
    // =====================================================================

    /** Contador atual de um goal (0 se nunca incrementado). */
    public function goalStat(string $key): int
    {
        return self::goalStatsFor($this->id())[$key] ?? 0;
    }

    /** Incrementa e grava; devolve o novo valor. */
    public function bumpGoalStat(string $key, int $amount = 1): int
    {
        return self::bumpGoalStatFor($this->id(), $key, $amount);
    }

    /** Contadores crus do personagem (JSON em character.goal_stats). */
    public static function goalStatsFor(int $characterId): array
    {
        $raw = Db::value('SELECT goal_stats FROM `character` WHERE id = ?', [$characterId]);
        $stats = json_decode((string)$raw, true);
        return is_array($stats) ? $stats : [];
    }

    /** Versao estatica do incremento (para pontos sem Character carregado). */
    public static function bumpGoalStatFor(int $characterId, string $key, int $amount = 1): int
    {
        $stats = self::goalStatsFor($characterId);
        $stats[$key] = ($stats[$key] ?? 0) + $amount;
        Db::exec('UPDATE `character` SET goal_stats = ? WHERE id = ?', [json_encode($stats), $characterId]);
        return $stats[$key];
    }

    public function spinCasino(int $multiSpin): array { return Casino::spin($this->id(), $multiSpin); }
    public function activeCasinoSpin(): array { return Casino::pending($this->id()); }
    public function applyCasinoReward(bool $discardItem): void { Casino::apply($this->id(), $discardItem); }
    public static function casinoPossibleRewardSections(int $cid): array { return Casino::sections($cid); }

    public function createNextTreasureEventLevel(): void { EventTreasures::next($this->id()); }
    public function openTreasureCell(int $level, int $x, int $y, bool $premium): array
    {
        EventTreasures::open($this->id(), $level, $x, $y, $premium);
        return EventTreasures::state($this->id());
    }
    public function collectTreasureCellReward(int $level, int $x, int $y, bool $discard = false): void
    { EventTreasures::collect($this->id(), $level, $x, $y, $discard); }
    public function claimTreasureEventReward(int $rewardIndex, bool $discard = false): void
    { EventTreasures::claim($this->id(), $rewardIndex, $discard); }
    public function claimFreeTreasureRevealItems(): void { EventTreasures::free($this->id()); }

    /** Custo em game_currency para comprar +1 no proximo ponto de atributo. */
    /**
     * Custo de comprar N pontos pagos a partir de $currentBought.
     * Fórmula do cliente: round(base + scale × (base_scale × bought)^exp)
     * Os parâmetros vêm de balancing_constants.json (lidos via CDN pelo cliente).
     */

    /**
     * Centrifugadora (washing machine): redistribui os stats de combate do item
     * por 1, 2, 3 ou 4 habilidades (probabilidades: 15% / 25% / 40% / 20%).
     * 1 uso gratuito por dia; dai em diante custa 1 donut (2 para itens tipo 12).
     */
    public function washItem(int $itemId, bool $usePremium): void
    {
        $this->withStoryActivity(fn() => $this->washItemLocked($itemId, $usePremium));
    }

    private function washItemLocked(int $itemId, bool $usePremium): void
    {
        if ($itemId <= 0) throw new GameError('errRequestInvalidParameter');

        $item = Db::row(
            'SELECT * FROM `items` WHERE id = ? AND character_id = ?',
            [$itemId, $this->id()]
        );
        if (!$item) throw new GameError('errRequestInvalidParameter');

        $today     = (int)(GameTime::now() / 86400);
        $lastWash  = (int)($this->row['ts_last_wash_item'] ?? 0);
        $usedToday = $lastWash > 0 && (int)($lastWash / 86400) === $today;

        if ($usedToday) {
            $premiumCost = (int)($item['type'] ?? 0) === 12 ? 2 : 1;
            if (!$usePremium) throw new GameError('errRemoveGameCurrencyNotEnough');
            if ($this->premiumCurrency() < $premiumCost) throw new GameError('errRemovePremiumCurrencyNotEnough');
            Db::exec(
                'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                [$premiumCost, $this->userId(), $premiumCost]
            );
            $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
        }

        // Redistribuir stats de combate (weapon_damage fica inalterado).
        $statCols = ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating'];
        $total = 0;
        foreach ($statCols as $col) {
            $total += (int)($item[$col] ?? 0);
        }

        if ($total > 0) {
            // Sortear número de stats: 1=15% 2=25% 3=40% 4=20%
            $r = random_int(1, 100);
            if      ($r <= 15) $numStats = 1;
            elseif  ($r <= 40) $numStats = 2;
            elseif  ($r <= 80) $numStats = 3;
            else               $numStats = 4;

            // Escolher quais stats recebem pontos.
            $keys = $statCols;
            shuffle($keys);
            $chosen = array_slice($keys, 0, $numStats);

            // Distribuir o total em partes aleatórias (método das ordens).
            $newVals = array_fill_keys($statCols, 0);
            if ($numStats === 1) {
                $newVals[$chosen[0]] = $total;
            } else {
                $cuts = [];
                while (count($cuts) < $numStats - 1) {
                    $c = random_int(1, $total - 1);
                    if (!in_array($c, $cuts, true)) $cuts[] = $c;
                }
                sort($cuts);
                $cuts[] = $total;
                $prev = 0;
                foreach ($chosen as $i => $col) {
                    $newVals[$col] = $cuts[$i] - $prev;
                    $prev = $cuts[$i];
                }
            }

            Db::exec(
                'UPDATE `items`
                    SET stat_stamina=?, stat_strength=?, stat_critical_rating=?, stat_dodge_rating=?
                  WHERE id = ? AND character_id = ?',
                [
                    $newVals['stat_stamina'],
                    $newVals['stat_strength'],
                    $newVals['stat_critical_rating'],
                    $newVals['stat_dodge_rating'],
                    $itemId, $this->id(),
                ]
            );
        }

        Db::exec('UPDATE `character` SET ts_last_wash_item = ? WHERE id = ?', [GameTime::now(), $this->id()]);
        $this->row['ts_last_wash_item'] = GameTime::now();
        Herobook::record($this->id(), 4);
        StoryDungeon::record($this->id(), StoryDungeon::WASH);
        EventQuests::record($this->id(), EventQuests::WASH);
    }

    public function calcStatCost(int $currentBought, int $paidPoints): int
    {
        $base       = 5.0;
        $scale      = 0.12;
        $baseScale  = 0.85;
        $exp        = 2.15;
        $total = 0;
        for ($i = 0; $i < $paidPoints; $i++) {
            $total += (int)round($base + $scale * (($baseScale * ($currentBought + $i)) ** $exp));
        }
        return $total;
    }

    /**
     * Sobe +$amount no atributo indicado (stat_type do cliente). Persiste no banco.
     * Pontos gratis (stat_points_available, de level-up) sao consumidos PRIMEIRO
     * e nao contam como stat_bought; o restante e comprado com game_currency.
     * Lanca GameError se nao houver moeda suficiente para a parte paga.
     */
    public function improveStat(int $statType, int $amount = 1): void
    {
        $this->withStoryActivity(fn() => $this->improveStatLocked($statType, $amount));
    }

    private function improveStatLocked(int $statType, int $amount): void
    {
        $stat = self::STAT_MAP[$statType] ?? null;
        if ($stat === null || $amount < 1) {
            throw new GameError('errRequestInvalidParameter');
        }
        $free   = (int)($this->row['stat_points_available'] ?? 0);
        $useFree = min($free, $amount);
        $paid    = $amount - $useFree;

        // Custo acumulado: escala com o stat_bought DO STAT ESPECIFICO (igual ao cliente).
        $currentBought = (int)($this->row["stat_bought_$stat"] ?? 0);
        $cost = $this->calcStatCost($currentBought, $paid);
        if ($this->gameCurrency() < $cost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        $newCurrency = $this->gameCurrency() - $cost;
        $newFree     = $free - $useFree;
        $newBase     = (int)$this->row["stat_base_$stat"] + $amount;
        $newBought   = (int)$this->row["stat_bought_$stat"] + $paid;

        Db::exec(
            "UPDATE `character`
                SET game_currency = ?, stat_points_available = ?, `stat_base_$stat` = ?, `stat_bought_$stat` = ?
              WHERE id = ?",
            [$newCurrency, $newFree, $newBase, $newBought, $this->id()]
        );
        $this->row['game_currency']         = $newCurrency;
        $this->row['stat_points_available'] = $newFree;
        $this->row["stat_base_$stat"]       = $newBase;
        $this->row["stat_bought_$stat"]     = $newBought;
        Herobook::record($this->id(), 10, $paid);
        HeroCon::record($this->id(), 17, $paid);
        // Tutorial: gastar pontos de atributo liberta a seta para a loja.
        // (`stats_spent` so o servidor deriva; o cliente so o lê via hasNewTutorialFlag.)
        $this->setTutorialFlag('stats_spent', true);
    }
}
