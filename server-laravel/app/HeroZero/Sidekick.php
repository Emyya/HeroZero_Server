<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Sidekicks/pets — fonte das constants oficiais + modelo.
 *
 * Valores extraidos do cliente HTML5 build 257 (docs/sidekicks/extract/):
 * asset constants_json.data (JSON zlib, mesmo ficheiro que League usa).
 * Os templates initGame.json trazem sidekick_levels/sidekick_skills/
 * sidekick_data/battle_effects a NULL e NAO trazem os escalares sidekick_* ->
 * abrir qualquer UI de sidekicks crasha ("Trying to retrieve unknown
 * constant!"). Live::withLocalConstants injeta tudo no boot atraves de
 * Sidekick::injectConstants() (atribuicao DIRETA: os templates trazem estas
 * chaves a null e += nao sobrescreve chaves existentes).
 *
 * Formatos que a extracao inicial tinha errado (corrigidos contra o minified):
 *  - `zf` do cliente e o JsonUtil (JSON.stringify/parse) — logo
 *    inventory.sidekick_data e JSON PURO {"orders":[id..],"favorit_orders":[id..]},
 *    NAO zlib;
 *  - battle_effects[105..109].sidekick_battle_effect_ids lista IDS DE EFEITO
 *    DE SKILL (o valor skill.sidekick_battle_effect_id), e cada um resolve para
 *    UMA entrada de battle_effects (contencao unica, verificado) que classifica
 *    o efeito (round_start/after_hit/single_round).
 */
final class Sidekick
{
    /** Caminhos candidatos do asset oficial (mesma ordem de League::officialConstants). */
    private const ASSET_PATHS = [
        __DIR__ . '/../../public/assets/data/constants_json.data',
        __DIR__ . '/../../../assets/data/constants_json.data',
    ];

    /** Fallback por secao quando o asset nao esta acessivel (extraidos do build 257). */
    private const REF_DIR = __DIR__ . '/../../data/reference/';

    /**
     * Escalares obrigatorios (fallback embutido; valores oficiais do build 257).
     * Os item_sidekick_* entram na cadeia do preco de venda (sellPrice()).
     */
    private const FALLBACK_SCALARS = [
        'sidekick_max_level'                        => 30,
        'sidekick_min_required_level'               => 60,
        'sidekick_teaser_level'                     => 30,
        'sidekick_stat_factor_common'               => 0.01,
        'sidekick_stat_factor_rare'                 => 0.01,
        'sidekick_stat_factor_epic'                 => 0.017,
        'sidekick_stat_factor_legendary'            => 0.025,
        'sidekick_rename_premium_currency_amount'   => 30,
        'sidekick_merge_premium_currency_amount'    => 15,
        'sidekick_reskill_premium_currency_amount'  => 1,
        'sidekick_sew_premium_currency_amount'      => 5,
        'item_sidekick_buy_price_factor'            => 10,
        'item_sidekick_sell_price_divider'          => 5,
        'item_sidekick_sell_price_multiplier'       => 2,
    ];

    /**
     * Pools de skills sorteadas (grant/reskill): as skills "normais" do build
     * 257 por stage — fixed_sidekick null e nao-legendary (as restantes sao de
     * epocas/licencas, que nao implementamos). Derivado de
     * data/reference/sidekick_skills.json.
     */
    public const SKILL_POOLS = [
        1 => [1, 2, 3, 4, 5, 24],
        2 => [6, 7, 8, 9, 10, 11, 12, 25],
        3 => [15, 16, 17, 18, 20, 21, 26],
    ];

    /**
     * Grupos passivos (battle_effects): grupo => stats do HEROI que as skills
     * do grupo melhoram; PASSIVE_GROUP_ALL melhora as quatro. A percentagem e o
     * value1 DA SKILL: bonus += ceil(stat_base_do_heroi * value1) por skill
     * desbloqueada (Y.sidekickBaseStatBonus, docs/sidekicks/extract/03 §3.1).
     */
    private const PASSIVE_GROUPS = [
        '105' => ['strength'],
        '106' => ['stamina'],
        '107' => ['critical_rating'],
        '108' => ['dodge_rating'],
    ];
    public const PASSIVE_GROUP_ALL = '109';

    private const ALL_STATS = ['stamina', 'strength', 'critical_rating', 'dodge_rating'];

    /**
     * Fontes manuais de fallback quando as constantes não têm recompensa explícita.
     * Chaves = source i18n sob sidekick_source/<key>; conquistas não entram aqui.
     */
    private const STATIC_SOURCES = [
        // Desbloqueio ao nível 60
        'sidekick_dog1'                         => ['unlock_reward'],
        // Loja geral (pets básicos)
        'sidekick_dog2'                         => ['shop_general'],
        'sidekick_dog3'                         => ['shop_general'],
        'sidekick_dog4'                         => ['shop_general'],
        'sidekick_cat1'                         => ['shop_general'],
        'sidekick_cat2'                         => ['shop_general'],
        'sidekick_cat3'                         => ['shop_general'],
        'sidekick_cat4'                         => ['shop_general'],
        'sidekick_bird1'                        => ['shop_general'],
        'sidekick_bird2'                        => ['shop_general'],
        'sidekick_ape1'                         => ['shop_general'],
        'sidekick_pig1'                         => ['shop_general'],
        'sidekick_goat1'                        => ['shop_general'],
        'sidekick_lion1'                        => ['shop_general'],
        'sidekick_owl1'                         => ['shop_general'],
        'sidekick_pitbull1'                     => ['shop_general'],
        'sidekick_pitbull2'                     => ['shop_general'],
        'sidekick_kangaroo1'                    => ['shop_general'],
        'sidekick_llama1'                       => ['shop_general'],
        'sidekick_llama2'                       => ['shop_general'],
        'sidekick_llama3'                       => ['shop_general'],
        'sidekick_turtle1'                      => ['shop_general'],
        'sidekick_meerkat1'                     => ['shop_general'],
        'sidekick_hedgehog1'                    => ['shop_general'],
        'sidekick_koala1'                       => ['shop_general'],
        'sidekick_fish1'                        => ['shop_general'],
        'sidekick_squid1'                       => ['shop_summer_event'],
        'sidekick_egypt_cat'                    => ['shop_general'],
        'sidekick_unicorn'                      => ['shop_general'],
        'sidekick_skeleton1'                    => ['shop_halloween'],
        'sidekick_pumpkin1'                     => ['shop_halloween'],
        'sidekick_robot1'                       => ['not_available'],
        'sidekick_capivara'                     => ['shop_general'],
        'sidekick_chameleon1'                   => ['not_available'],
        'sidekick_baywatch'                     => ['shop_summer_event'],
        // Natal / festivos
        'sidekick_snowman'                      => ['shop_xmas'],
        'sidekick_xmas2'                        => ['shop_xmas'],
        'sidekick_xmas_corgi1'                  => ['shop_xmas'],
        'sidekick_xmas_corgi2'                  => ['shop_xmas'],
        'sidekick_xmas_corgi3'                  => ['shop_xmas'],
        'sidekick_xmas_casino_1'                => ['slotmachine_christmas'],
        'sidekick_xmas_casino_2'                => ['slotmachine_christmas'],
        'sidekick_xmas_casino_3'                => ['slotmachine_christmas'],
        'sidekick_ld_xmas'                      => ['legendary_dungeon_xmas_legendary_dungeon'],
        'sidekick_new_year'                     => ['slotmachine_newyear'],
        // Páscoa
        'sidekick_easter1'                      => ['shop_easter'],
        'sidekick_easter2'                      => ['shop_easter'],
        'sidekick_easter3'                      => ['event_easter'],
        'sidekick_easter4'                      => ['special_offer_easter'],
        // Carnaval
        'sidekick_carnival1'                    => ['shop_carnival'],
        // Oktoberfest
        'sidekick_octoberfest1'                 => ['shop_octoberfest'],
        // Verão / Midsummer
        'sidekick_midsummer1'                   => ['shop_midsummer'],
        // Dia de S. Patrício
        'sidekick_st_patricks_day'              => ['shop_st_patricks_day'],
        // Dia do Pirata
        'sidekick_pirateday_1'                  => ['shop_pirateday'],
        'sidekick_pirateday_2'                  => ['shop_pirateday'],
        // Sci-Fi Week
        'sidekick_sci_fi_week_1'                => ['shop_sci_fi_week'],
        'sidekick_sci_fi_week_tower'            => ['tower_event_sci_fi_week_towerevent'],
        // Video Games Week
        'sidekick_video_games_week_1'           => ['special_offer_random_video_games_week'],
        'sidekick_video_games_week_2'           => ['event_video_games_week'],
        'sidekick_video_games_week_3'           => ['slotmachine_video_games_week'],
        // Cyber Monday
        'sidekick_cyber_monday'                 => ['special_offer_random_cyber_monday'],
        // Torres de evento
        'sidekick_bounties_of_seven_seas_tower' => ['tower_event_bounties_of_seven_seas_towerevent'],
        'sidekick_earth_tower'                  => ['tower_event_earth_towerevent'],
        'sidekick_wasteland_tower'              => ['tower_event_wasteland_towerevent'],
        'sidekick_zombies_tower'                => ['tower_event_zombies_towerevent'],
        // Liga
        'sidekick_leagueset_bronze'             => ['item_offer_full_leagueset_bronze_offer_with_sidekick'],
        'sidekick_leagueset_silver'             => ['item_offer_full_leagueset_silver_offer_with_sidekick'],
        'sidekick_leagueset_gold'               => ['item_offer_full_leagueset_gold_offer_with_sidekick'],
        'sidekick_leagueset_champion'           => ['item_offer_full_leagueset_champion_offer_with_sidekick'],
        'sidekick_leagueset_superhero'          => ['item_offer_full_leagueset_superhero_offer_with_sidekick'],
        // Speed Server / Jumpstart
        'sidekick_speedserver_globefish'        => ['item_offer_full_speedserver_globefish_offer_with_sidekick'],
        'sidekick_speedserver_shark'            => ['item_offer_full_speedserver_shark_offer_with_sidekick'],
        'sidekick_speedserver_int2'             => ['item_offer_full_speedserver_int2_offer_with_sidekick'],
        'sidekick_jumpstartserver_int_bronze'   => ['herozero_jumpstartserver'],
        'sidekick_jumpstartserver_int_silver'   => ['herozero_jumpstartserver'],
        'sidekick_jumpstartserver_int_gold'     => ['herozero_jumpstartserver'],
        'sidekick_jumpstartserver_int_2'        => ['herozero_jumpstartserver'],
        // Covil Lendário
        'sidekick_ld_drwhyfi'                   => ['legendary_dungeon_drwhyfi_legendary_dungeon'],
        'sidekick_ld_halloween'                 => ['legendary_dungeon_halloween_legendary_dungeon'],
        'sidekick_ld_inconvenience'             => ['legendary_dungeon_inconvenience_legendary_dungeon'],
        // Ofertas de Progresso
        'sidekick_progress_offer_1'             => ['special_offer_sidekick_progress_offer_small'],
        'sidekick_progress_offer_2'             => ['special_offer_sidekick_progress_offer_medium'],
        'sidekick_progress_offer_3'             => ['special_offer_sidekick_progress_offer_big'],
        // Tuxedos (oferta especial)
        'sidekick_tuxedos'                      => ['item_offer_full_tuxedos_offer_with_sidekick'],
        // Worldcup
        'sidekick_worldcup_tiger'               => ['event_worldcup_event_1'],
        'sidekick_worldcup_fox'                 => ['event_worldcup_event_2'],
        'sidekick_worldcup_bear'                => ['shop_worldcup'],
        'sidekick_worldcup_tucan'               => ['shop_worldcup'],
        // Olimpíadas
        'sidekick_rio2016'                      => ['shop_olympia_2016_rio'],
        'sidekick_olympia_2020_tokyo_monkey'    => ['shop_olympia_2020_tokyo'],
        'sidekick_olympia_2020_tokyo_tanuki'    => ['shop_olympia_2020_tokyo'],
        // HeroCon / Global PvP / XP Tournament
        'sidekick_solo_herocon'                 => ['surprise_box_surprise_box_solo_herocon_big'],
        'sidekick_global_pvp'                   => ['surprise_box_surprise_box_global_pvp_big'],
        'sidekick_xp_tournament'                => ['surprise_box_surprise_box_xp_tournament_big'],
        // Eventos ocultos
        'sidekick_racoon'                       => ['hidden_object_event_find_ballo_event'],
        'sidekick_see_lion'                     => ['hidden_object_event_find_sidekick_event'],
        // Temporadas de evento (server launch, etc.)
        'sidekick_server_launch_2025_october'   => ['event_server_launch_2025_october'],
        'sidekick_server_launch_2026_cube'      => ['event_server_launch_2026_cube'],
        'sidekick_server_launch_2026_gangsta1'  => ['event_server_launch_2026_gangsta1'],
        'sidekick_server_launch_2026_overgrown' => ['event_server_launch_2026_overgrown'],
        // Birthday / Aniversário
        'sidekick_birthday_event'               => ['slotmachine_birthday'],
        'sidekick_birthday_event2'              => ['slotmachine_birthday'],
        // Festas de peixe / Lula / animais aquáticos
        'sidekick_heroic_feast_appetizer'       => ['not_available'],
        // Worldboss de temporada
        'sidekick_season_worldboss_dystopia'    => ['worldboss_event_dystopia_season_worldboss'],
        'sidekick_season_worldboss_utopia'      => ['worldboss_event_utopia_season_worldboss'],
        'sidekick_season_boss_primal'           => ['worldboss_event_primal_season_worldboss'],
        // Servidor lendário (LD sem covil específico)
        'sidekick_season_video_games'           => ['season_premium_video_games'],
        'sidekick_season_video_games_2'         => ['special_offer_video_games_season_event'],
        'sidekick_season_volcano_1'             => ['season_premium_volcano'],
        'sidekick_season_volcano_2'             => ['special_offer_volcano_season_event'],
        // Eventos de arco de temporada
        'sidekick_season_event_arctic_exploration'        => ['event_server_launch_2025_arctic_exploration'],
        'sidekick_season_event_blooming_nature'           => ['event_server_launch_2023_blooming_nature'],
        'sidekick_season_event_doom_and_darkness'         => ['event_doom_and_darkness_season_arc_event_2024'],
        'sidekick_season_event_dystopia'                  => ['event_server_launch_dystopia'],
        'sidekick_season_event_exotic_places'             => ['event_server_launch_2023_exotic_places'],
        'sidekick_season_event_fantastic_adventures'      => ['event_server_launch_2023_fantastic_adventures'],
        'sidekick_season_event_mayan_empire'              => ['event_server_launch_2025_mayan_empire'],
        'sidekick_season_event_mythical_gods'             => ['event_server_launch_2024_mythical_gods'],
        'sidekick_season_event_primal'                    => ['event_server_launch_primal'],
        'sidekick_season_event_scorching_heat'            => ['event_server_launch_2023_scorching_heat'],
        'sidekick_season_event_sun_moon_stars'            => ['event_sun_moon_stars_season_arc_event_2024'],
        'sidekick_season_event_tech'                      => ['event_server_launch_2024_tech'],
        'sidekick_season_event_utopia'                    => ['event_server_launch_utopia'],
        'sidekick_season_heroic_feast_appetizer'          => ['event_server_launch_2025_heroic_feast_appetizer'],
        // Environmental Week
        'sidekick_environmental_week_1'                   => ['event_environmental_week'],
        'sidekick_environmental_week_2'                   => ['event_environmental_week'],
        // Fairytale 2026 server launch
        'sidekick_server_launch_2026_fairytale1'          => ['event_server_launch_2026_fairytale1'],
        // Slimer (evento especial)
        'sidekick_slimer'                                 => ['not_available'],
    ];

    /** Primeiro sidekick (ao atingir sidekick_min_required_level): spec fixa do servidor oficial antigo (Player.php @520). */
    private const FIRST_SIDEKICK = [
        'identifier'                => 'sidekick_dog1',
        'quality'                   => 3,
        'stat_base_stamina'         => 60,
        'stat_base_strength'        => 100,
        'stat_base_critical_rating' => 40,
        'stat_base_dodge_rating'    => 23,
    ];

    // =====================================================================
    // Constants oficiais
    // =====================================================================

    private static ?array $officialCache = null;

    /**
     * Secoes usadas do asset oficial, com fallback para data/reference/*.json.
     * `templates` = slice do item_templates com type==9 (as 212 entradas de
     * sidekick: quality/required_level/premium_only autoritativos).
     *
     * @return array{scalars:array,levels:array,skills:array,data:array,effects:array,templates:array}
     */
    public static function official(): array
    {
        if (self::$officialCache !== null) {
            return self::$officialCache;
        }

        // CACHE PERSISTENTE: o parse do asset (gzuncompress+json_decode, ~80ms)
        // repetia-se em TODA a acao (PHP classico). A fatia derivada e pequena e
        // invalida sozinha quando o asset muda (AssetCache::slice).
        $src = '';
        foreach (self::ASSET_PATHS as $path) {
            if (is_file($path)) {
                $src = $path;
                break;
            }
        }
        return self::$officialCache = AssetCache::slice(
            'sidekick_official_sources_v3',
            $src,
            fn(): array => self::buildOfficial()
        );
    }

    /** Parse + fatias derivadas do asset oficial (corre 1x ate ao proximo touch do asset). */
    private static function buildOfficial(): array
    {
        $data = null;
        foreach (self::ASSET_PATHS as $path) {
            $raw = @file_get_contents($path);
            if ($raw === false || $raw === '') {
                continue;
            }
            $json = json_decode(@gzuncompress($raw) ?: '', true);
            if (is_array($json)) {
                $data = $json;
                break;
            }
        }

        $section = static function (string $assetKey, string $refName) use ($data): array {
            if (isset($data[$assetKey]) && is_array($data[$assetKey])) {
                return $data[$assetKey];
            }
            $raw = @file_get_contents(self::REF_DIR . $refName . '.json');
            if (is_string($raw)) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    return $json;
                }
            }
            return [];
        };

        $scalars = self::FALLBACK_SCALARS;
        if ($data !== null) {
            foreach ($scalars as $key => $def) {
                if (array_key_exists($key, $data)) {
                    $scalars[$key] = $data[$key];
                }
            }
        }

        $templates = [];
        foreach (($data['item_templates'] ?? []) as $identifier => $tpl) {
            if (is_array($tpl) && (int)($tpl['type'] ?? 0) === 9) {
                $templates[(string)$identifier] = $tpl;
            }
        }

        // --- Mapas auxiliares para o catálogo ---

        // fixed_skills[identifier][stage] = skill_id
        // special_skill[identifier] = skill_id   (stage 0 — season/especial)
        $rawSkills = $section('sidekick_skills', 'sidekick_skills');
        $fixedSkills   = [];
        $specialSkill  = [];
        foreach ($rawSkills as $skillId => $sk) {
            if (!is_array($sk)) {
                continue;
            }
            $stage = (int)($sk['stage'] ?? -1);
            $fixed = $sk['fixed_sidekick'] ?? null;
            if ($stage === 0) {
                // Skill de temporada: identifier da skill == parte do identifier do sidekick
                // ex. skill.identifier="season_buffalo" -> sidekick="sidekick_season_buffalo"
                $skIdentifier = (string)($sk['identifier'] ?? '');
                if ($skIdentifier !== '') {
                    $sidekickIdent = 'sidekick_' . $skIdentifier;
                    $specialSkill[$sidekickIdent] = (int)$skillId;
                }
            } elseif ($stage >= 1 && $stage <= 3 && $fixed !== null) {
                $fixedList = is_array($fixed) ? $fixed : [$fixed];
                foreach ($fixedList as $ident) {
                    if (is_string($ident) && $ident !== '') {
                        $fixedSkills[$ident][$stage] = (int)$skillId;
                    }
                }
            }
        }

        // special_skill: para pets sem skill de temporada (stage0), usar a skill
        // fixa de menor stage como special_skill_id
        foreach ($fixedSkills as $ident => $stageMap) {
            if (!isset($specialSkill[$ident])) {
                ksort($stageMap);
                $specialSkill[$ident] = reset($stageMap);
            }
        }

        $sources = self::sourcesFromConstants($data ?? []);

        return [
            'scalars'      => $scalars,
            'levels'       => $section('sidekick_levels', 'sidekick_levels'),
            'skills'       => $rawSkills,
            'data'         => $section('sidekick_data', 'sidekick_data'),
            'effects'      => $section('battle_effects', 'battle_effects'),
            'templates'    => $templates,
            'fixed_skills' => $fixedSkills,
            'special_skill'=> $specialSkill,
            'sources'      => $sources,
        ];
    }

    /** Fontes de recompensa explícitas prevalecem sobre associações manuais antigas. */
    public static function sourcesFromConstants(array $data): array
    {
        $derived = [];
        $add = static function (string $identifier, string $source) use (&$derived, $data): void {
            if (!str_starts_with($identifier, 'sidekick_') || !isset($data['item_templates'][$identifier])) return;
            if (!in_array($source, $derived[$identifier] ?? [], true)) $derived[$identifier][] = $source;
        };
        $scan = static function ($value, string $source) use (&$scan, $add): void {
            if (is_array($value)) {
                foreach ($value as $child) $scan($child, $source);
            } elseif (is_string($value)) {
                // reward_data de torres pode ser um JSON embutido.
                preg_match_all('/sidekick_[A-Za-z0-9_]+/', $value, $matches);
                foreach ($matches[0] as $identifier) $add($identifier, $source);
            }
        };
        foreach ($data['goals'] ?? [] as $goalId => $goal) {
            foreach ($goal['values'] ?? [] as $target => $reward) {
                $add((string)($reward['reward_identifier'] ?? ''), 'goal_'.$goalId.'_'.$target);
            }
        }
        foreach ($data['seasons'] ?? [] as $seasonId => $season) {
            foreach ($season['season_points'] ?? [] as $reward) {
                $add((string)($reward['premium_reward_identifier'] ?? ''), 'season_premium_'.$seasonId);
                $add((string)($reward['reward_identifier'] ?? ''), 'season_free_'.$seasonId);
            }
        }
        foreach (['event_quests'=>'event_', 'event_draw_pools'=>'event_draw_',
            'event_treasures'=>'event_treasure_', 'hidden_object_event_quests'=>'hidden_object_event_',
            'tower_event_objectives'=>'tower_event_', 'legendary_dungeons'=>'legendary_dungeon_'] as $section => $prefix) {
            foreach ($data[$section] ?? [] as $id => $definition) $scan($definition, $prefix.$id);
        }
        // Só usar as fontes manuais quando o asset não fornece uma origem explícita.
        // Assim conquistas deixam de ser anunciadas como brechó ou indisponíveis.
        return array_intersect_key(array_replace(self::STATIC_SOURCES, $derived),
            array_filter($data['item_templates'] ?? [], static fn($row) => (int)($row['type'] ?? 0) === 9));
    }

    /** Injecao no boot (Live::withLocalConstants). Atribuicao DIRETA: os templates trazem estas chaves a null. */
    public static function injectConstants(array &$constants): void
    {
        $off = self::official();
        $constants['sidekick_levels'] = self::levels();
        $constants['sidekick_skills'] = $off['skills'];
        $constants['sidekick_data']   = $off['data'];
        $constants['battle_effects']  = $off['effects'];
        foreach ($off['scalars'] as $key => $value) {
            $constants[$key] = $value;
        }
    }

    public static function scalars(): array
    {
        return self::official()['scalars'];
    }

    /** Tabela sidekick_levels normalizada: [nivel => xp acumulado], crescente. */
    public static function levels(): array
    {
        $out = [];
        foreach (self::official()['levels'] as $level => $rec) {
            $out[(int)$level] = (int)($rec['xp'] ?? 0);
        }
        ksort($out);
        return $out;
    }

    public static function maxLevel(): int
    {
        return (int)self::scalars()['sidekick_max_level'];
    }

    public static function minRequiredLevel(): int
    {
        return (int)self::scalars()['sidekick_min_required_level'];
    }

    /** Catalogo sidekick_skills cru: [id => registo]. */
    public static function skills(): array
    {
        return self::official()['skills'];
    }

    public static function skill(int $skillId): ?array
    {
        return self::skills()[(string)$skillId] ?? null;
    }

    public static function battleEffects(): array
    {
        return self::official()['effects'];
    }

    /** item_templates das entradas sidekick (type==9): quality/required_level/premium_only. */
    public static function templates(): array
    {
        return self::official()['templates'];
    }

    public static function template(string $identifier): ?array
    {
        return self::templates()[$identifier] ?? null;
    }

    // =====================================================================
    // Formulas (portas exatas do cliente — docs/sidekicks/extract/02)
    // =====================================================================

    /** Primeiro nivel cujo XP acumulado <= xp. */
    public static function levelForXp(int $xp): int
    {
        $xp = max(0, $xp);
        $level = 1;
        foreach (self::levels() as $lvl => $need) {
            if ($xp >= $need) {
                $level = max($level, $lvl);
            }
        }
        return min($level, self::maxLevel());
    }

    private static function qualityFactor(int $quality): float
    {
        return match ($quality) {
            4 => (float)self::scalars()['sidekick_stat_factor_legendary'],
            3 => (float)self::scalars()['sidekick_stat_factor_epic'],
            2 => (float)self::scalars()['sidekick_stat_factor_rare'],
            default => (float)self::scalars()['sidekick_stat_factor_common'],
        };
    }

    /** Q.getNewSidekickStatValue: round(base + base*fator*(nivel-1)). */
    public static function statAtLevel(int $quality, int $baseStat, int $level): int
    {
        if ($level < 1) $level = 1;
        $factor = self::qualityFactor($quality);
        return League::jsRound($baseStat + $baseStat * $factor * ($level - 1));
    }

    /**
     * Preco de venda EXATO que o cliente mostra no dialogo de release
     * (Cd.get_sellPrice @1516520). O "nivel" passado ao itemBuyPrice e o valor
     * de melhoria (ceil(totalStats/divisor)), NAO o nivel do sidekick.
     */
    public static function sellPrice(array $row): int
    {
        if (isset($row['id'], $row['character_id'])) {
            $row = Db::row('SELECT * FROM sidekicks WHERE id = ? AND character_id = ?', [(int)$row['id'], (int)$row['character_id']]) ?? $row;
        }
        $sc = self::scalars();
        $quality = (int)($row['quality'] ?? 1);
        $level = (int)($row['level'] ?? 1);

        $total = 0;
        foreach (self::ALL_STATS as $stat) {
            $total += self::statAtLevel($quality, (int)($row['stat_base_' . $stat] ?? 0), $level);
        }
        $improvementValue = (int)ceil($total / (float)$sc['item_sidekick_sell_price_divider']);

        $buy = (int)(self::itemBuyPrice($quality, $improvementValue) * (float)$sc['item_sidekick_buy_price_factor']);

        // Cliente: parseInt(itemBuyPrice(...) * factor) — parseInt TRUNCA (o
        // produto e integral de qualquer forma porque factor=10 * inteiro).

        // itemSellPrice(buy, premiumOnly): percentage=0.5, premium_factor=1.25
        // (constantes globais item_sell_price_*, nao-sidekick -> cravadas aqui).
        $premiumOnly = (bool)(self::template((string)($row['identifier'] ?? ''))['premium_only'] ?? false);
        $sell = League::jsRound($buy * ($premiumOnly ? 0.5 * 1.25 : 0.5));

        $mod = self::improvement($row);
        if ($mod !== null) {
            $modTotal = 0;
            foreach (self::ALL_STATS as $stat) $modTotal += (int)$mod['stat_' . $stat];
            $modValue = (int)ceil($modTotal / (float)$sc['item_sidekick_sell_price_divider']);
            $sell += League::jsRound(self::itemBuyPrice((int)$mod['quality'], $modValue) * 0.5);
        }
        return League::jsRound($sell * (float)$sc['item_sidekick_sell_price_multiplier']);
    }

    public static function improvement(array $row): ?array
    {
        $id = (int)($row['item_improvement_id'] ?? 0);
        return $id < 0 ? Db::row('SELECT * FROM items WHERE character_id = ? AND id = ? AND type = 12',
            [(int)$row['character_id'], -$id]) : null;
    }

    /** Keep stored bases unchanged; the protocol exposes bases including the improvement. */
    public static function withImprovement(array $row): array
    {
        $mod = self::improvement($row);
        if ($mod === null) return $row;
        foreach (self::ALL_STATS as $stat) {
            $row['stat_base_' . $stat] = (int)$row['stat_base_' . $stat] + (int)$mod['stat_' . $stat];
            $row['stat_' . $stat] = self::statAtLevel((int)$row['quality'], (int)$row['stat_base_' . $stat], (int)$row['level']);
        }
        return $row;
    }

    public static function deleteImprovement(int $characterId, int $sidekickId): void
    {
        $row = Db::row('SELECT * FROM sidekicks WHERE character_id = ? AND id = ?', [$characterId, $sidekickId]);
        $id = (int)($row['item_improvement_id'] ?? 0);
        if ($id < 0) Db::exec('DELETE FROM items WHERE character_id = ? AND id = ? AND type = 12', [$characterId, -$id]);
    }

    /** Q.itemBuyPrice(quality, level, 1) — constantes globais item_buy_price e coins_per_time cravadas. */
    private static function itemBuyPrice(int $quality, int $level): int
    {
        $scale = match ($quality) {
            4 => 2.5, 3 => 2.0, 2 => 1.5, default => 1.0,   // item_buy_price_scale_{legendary,epic,rare,common}
        };
        // Q.gameCurrencyPerTime: roundDecimal(base + scale*(levelScale*level)^exp, 3)
        $perTime = round(0.02 + 0.01 * pow(0.35 * $level, 1.55), 3);
        // ajuste de nivel baixo: percentage + level/endLevel*(1-percentage) (endLevel=10)
        $adjust = $level < 10 ? 0.3 + ($level / 10) * 0.7 : 1.0;
        return League::jsRound(1200 * $perTime * $scale * $adjust);
    }

    // =====================================================================
    // Efeitos / bonus passivo
    // =====================================================================

    /**
     * Skills desbloqueadas DO lado do servidor — espelho exato de
     * Cd.hasUnlockedSkill: nivel>=10/stage1, >=20/stage2, >=30/stage3.
     *
     * @return int[]
     */
    public static function unlockedSkillIds(array $row): array
    {
        $level = (int)($row['level'] ?? 1);
        $ids = [];
        if ($level >= 10 && (int)($row['stage1_skill_id'] ?? 0) > 0) $ids[] = (int)$row['stage1_skill_id'];
        if ($level >= 20 && (int)($row['stage2_skill_id'] ?? 0) > 0) $ids[] = (int)$row['stage2_skill_id'];
        if ($level >= 30 && (int)($row['stage3_skill_id'] ?? 0) > 0) $ids[] = (int)$row['stage3_skill_id'];
        return $ids;
    }

    /**
     * Entrada de battle_effects que classifica um efeito de skill
     * (bd.sidekickBattleEffectToBattleEffectData: primeira entrada cuja lista
     * contem o id — contencao e unica, verificado nos dados). Flags
     * round_start/after_hit/single_round guiam QUANDO o efeito age;
     * grupos 105-109 sao passivos puros.
     */
    public static function effectRecordFor(int $sidekickBattleEffectId): ?array
    {
        foreach (self::battleEffects() as $rec) {
            if (in_array($sidekickBattleEffectId, $rec['sidekick_battle_effect_ids'] ?? [], false)) {
                return $rec;
            }
        }
        return null;
    }

    /**
     * Bonus passivo do ATIVO do heroi sobre as 4 stats — portas de
     * Y.sidekickBaseStatBonus: por skill desbloqueada cujo
     * sidekick_battle_effect_id pertence a um grupo passivo (105-109),
     * soma ceil(stat_base_do_heroi * value1_da_skill).
     *
     * @param array $heroBases ['stamina'=>..,'strength'=>..,'critical_rating'=>..,'dodge_rating'=>..]
     * @return array<float> mesmas chaves, somas
     */
    public static function passiveStatBonus(array $heroBases, ?array $row): array
    {
        $out = array_fill_keys(self::ALL_STATS, 0);
        if ($row === null || (int)($row['status'] ?? 0) !== 1) {
            return $out;
        }

        // efeito de skill => stats afetadas (grupos passivos + grupo ALL)
        $byEffect = [];
        $effects = self::battleEffects();
        foreach (self::PASSIVE_GROUPS as $group => $stats) {
            foreach ($effects[$group]['sidekick_battle_effect_ids'] ?? [] as $sid) {
                $byEffect[(int)$sid] = $stats;
            }
        }
        foreach ($effects[self::PASSIVE_GROUP_ALL]['sidekick_battle_effect_ids'] ?? [] as $sid) {
            $byEffect[(int)$sid] = self::ALL_STATS;
        }

        foreach (self::unlockedSkillIds($row) as $skillId) {
            $skill = self::skill($skillId);
            if ($skill === null) continue;
            $affected = $byEffect[(int)($skill['sidekick_battle_effect_id'] ?? 0)] ?? null;
            if ($affected === null) continue;
            $value1 = (float)($skill['value1'] ?? 0);
            foreach ($affected as $stat) {
                $base = (int)($heroBases[$stat] ?? 0);
                if ($base > 0) {
                    $out[$stat] += (int)ceil($base * $value1);
                }
            }
        }
        return $out;
    }

    // =====================================================================
    // Base de dados
    // =====================================================================

    /**
     * Todos os sidekicks DO personagem como DOs completos (chave `sidekicks`
     * do boot/accountState — sem ela o painel nasce vazio).
     *
     * @return array<int,array>
     */
    public static function ownedByCharacter(int $characterId): array
    {
        $rows = Db::rows('SELECT * FROM `sidekicks` WHERE character_id = ? ORDER BY id', [$characterId]);
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::doFromRow($row);
        }
        return $out;
    }

    public static function findOwned(int $characterId, int $sidekickId): ?array
    {
        $row = Db::row(
            'SELECT * FROM `sidekicks` WHERE id = ? AND character_id = ?',
            [$sidekickId, $characterId]
        );
        return $row === null ? null : self::doFromRow($row);
    }

    /** Id do sidekick ATIVO — resolucao exclusiva por inventory.sidekick_id (0 = nenhum). */
    public static function activeId(int $characterId): int
    {
        return (int)Db::value('SELECT sidekick_id FROM `inventory` WHERE character_id = ?', [$characterId]);
    }

    public static function activeRow(int $characterId): ?array
    {
        $activeId = self::activeId($characterId);
        if ($activeId <= 0) {
            return null;
        }
        $row = Db::row('SELECT * FROM `sidekicks` WHERE id = ? AND character_id = ?', [$activeId, $characterId]);
        return $row === null ? null : self::withImprovement($row);
    }

    public static function setActive(int $characterId, int $sidekickId): void
    {
        Db::exec('UPDATE `inventory` SET sidekick_id = ? WHERE character_id = ?', [$sidekickId, $characterId]);
    }

    /**
     * Catalogo completo para getSidekicks (`all_sidekicks`, ~212 entradas —
     * SEMPRE presente: o handler acede .iterator() sem null-check). Entrada de
     * catalogo = DO com ever_owned/now_owned/sources; presenca de ever_owned e
     * que marca entrada de catalogo (Cd.get_isReal).
     *
     * @return array<int,array>
     */
    public static function catalogEntries(int $characterId): array
    {
        $owned = [];
        foreach (Db::rows('SELECT identifier FROM `sidekicks` WHERE character_id = ?', [$characterId]) as $row) {
            $owned[(string)$row['identifier']] = true;
        }

        $off = self::official();
        $fixedSkills  = $off['fixed_skills'];
        $specialSkill = $off['special_skill'];
        $sourcesMap   = $off['sources'];

        $out = [];
        foreach ($off['data'] as $identifier => $meta) {
            $ident = (string)$identifier;
            $tpl   = self::template($ident) ?? [];
            $have  = isset($owned[$ident]);
            $fixed = $fixedSkills[$ident] ?? [];
            $entry = [
                'id'                  => 0,
                'character_id'        => 0,
                'status'              => 0,
                'identifier'          => $ident,
                'name'                => '',
                'quality'             => (int)($tpl['quality'] ?? 1),
                'level'               => (int)($tpl['required_level'] ?? self::minRequiredLevel()),
                'xp'                  => 0,
                'item_improvement_id' => 0,
                'stat_base_stamina'         => 0,
                'stat_base_strength'        => 0,
                'stat_base_critical_rating' => 0,
                'stat_base_dodge_rating'    => 0,
                'stat_stamina'              => 0,
                'stat_strength'             => 0,
                'stat_critical_rating'      => 0,
                'stat_dodge_rating'         => 0,
                'stage1_skill_id'     => $fixed[1] ?? 0,
                'stage2_skill_id'     => $fixed[2] ?? 0,
                'stage3_skill_id'     => $fixed[3] ?? 0,
                'sewed_identifier'    => '',
                'sewed_level'         => 0,
                'ever_owned'          => $have,
                'now_owned'           => $have,
                'sources'             => $sourcesMap[$ident] ?? [],
            ];
            if (isset($specialSkill[$ident])) {
                $entry['special_skill_id'] = $specialSkill[$ident];
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * Sorteio de skills novas (grant/reskill): uma por pool de stage.
     *
     * @return array{1:int,2:int,3:int} [stage => skill_id]
     */
    public static function randomSkills(): array
    {
        $picks = [];
        foreach (self::SKILL_POOLS as $stage => $pool) {
            $picks[$stage] = $pool[random_int(0, count($pool) - 1)];
        }
        return $picks;
    }

    /**
     * Devolve as fixed skills (stages 1-3) do identifier dado, ou null se não
     * existirem skills fixas. Usar na criação de sidekicks para preservar a
     * habilidade especial oficial de cada pet.
     *
     * @return array{1:int,2:int,3:int}|null
     */
    public static function skillsForIdentifier(string $identifier): ?array
    {
        $fixed = self::official()['fixed_skills'][$identifier] ?? null;
        if (!is_array($fixed) || $fixed === []) {
            return null;
        }
        // Garante que stages 1-3 existem; preenche com random se faltar algum.
        $result = self::randomSkills();
        foreach ([1, 2, 3] as $stage) {
            if (isset($fixed[$stage])) {
                $result[$stage] = (int)$fixed[$stage];
            }
        }
        return $result;
    }

    /**
     * Cria um sidekick (status=1, stats calculadas ao nivel dado).
     *
     * @param array $bases ['stat_base_stamina'=>.., ...] (4 chaves)
     * @return int id novo
     */
    public static function create(
        int $characterId,
        string $identifier,
        int $quality,
        array $bases,
        int $level = 1,
        int $xp = 0,
        ?array $skills = null
    ): int {
        $skills = $skills ?? self::randomSkills();
        // Toda a origem de obtenção preserva as habilidades fixas do pet.
        // Só os restantes estágios podem usar o sorteio ou valores do chamador.
        foreach (self::official()['fixed_skills'][$identifier] ?? [] as $stage => $skillId) {
            $skills[(int)$stage] = (int)$skillId;
        }
        $cols = [
            'identifier' => $identifier,
            'character_id' => $characterId,
            'name' => '',
            'status' => 1,
            'quality' => $quality,
            'level' => max(1, $level),
            'xp' => max(0, $xp),
        ];
        foreach (self::ALL_STATS as $stat) {
            $base = (int)($bases['stat_base_' . $stat] ?? 0);
            $cols['stat_base_' . $stat] = $base;
            $cols['stat_' . $stat] = self::statAtLevel($quality, $base, (int)$cols['level']);
        }
        foreach ([1, 2, 3] as $stage) {
            $cols['stage' . $stage . '_skill_id'] = (int)($skills[$stage] ?? 0);
        }

        $fields = array_map(static fn($c) => '`' . $c . '`', array_keys($cols));
        $marks = implode(', ', array_fill(0, count($cols), '?'));
        Db::exec(
            'INSERT INTO `sidekicks` (' . implode(', ', $fields) . ') VALUES (' . $marks . ')',
            array_values($cols)
        );
        return (int)Db::pdo()->lastInsertId();
    }

    /**
     * XP partilhado cai SEMPRE so no ATIVO (inventory.sidekick_id); subidas de
     * nivel recalculam as stats (fórmula depende so de quality/base/level).
     */
    public static function giveXpToActive(int $characterId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        $activeId = self::activeId($characterId);
        if ($activeId <= 0) {
            return;
        }
        Db::exec(
            'UPDATE `sidekicks` SET xp = xp + ? WHERE id = ? AND character_id = ?',
            [$amount, $activeId, $characterId]
        );
        self::refreshLevelAndStats($activeId);
    }

    /** Reavalia nivel (tabela sidekick_levels) e stats; grava apenas se mudou. */
    public static function refreshLevelAndStats(int $sidekickId): void
    {
        $cid=(int)Db::value('SELECT character_id FROM sidekicks WHERE id=?',[$sidekickId]);
        if ($cid<1) return;
        StoryDungeon::transaction($cid, static fn()=>self::refreshLevelAndStatsLocked($sidekickId));
    }

    private static function refreshLevelAndStatsLocked(int $sidekickId): void
    {
        $row = Db::row(
            'SELECT character_id, quality, level, xp, stat_base_stamina, stat_base_strength,
                    stat_base_critical_rating, stat_base_dodge_rating
               FROM `sidekicks` WHERE id = ? FOR UPDATE',
            [$sidekickId]
        );
        if ($row === null) {
            return;
        }
        $newLevel = self::levelForXp((int)$row['xp']);
        if ($newLevel === (int)$row['level']) {
            return;
        }
        $sets = ['level' => $newLevel];
        $params = [];
        foreach (self::ALL_STATS as $stat) {
            $sets['stat_' . $stat] = self::statAtLevel((int)$row['quality'], (int)$row['stat_base_' . $stat], $newLevel);
        }
        $sql = 'UPDATE `sidekicks` SET ';
        foreach ($sets as $col => $value) {
            $sql .= '`' . $col . '` = ?, ';
            $params[] = $value;
        }
        $params[] = $sidekickId;
        Db::exec(rtrim($sql, ', ') . ' WHERE id = ?', $params);
        if ($newLevel > (int)$row['level']) HeroCon::record((int)$row['character_id'], 29, $newLevel - (int)$row['level']);

        // GOAL: sidekick chegou ao nivel maximo (30).
        if ($newLevel >= self::maxLevel() && (int)$row['level'] < self::maxLevel()) {
            Character::bumpGoalStatFor((int)$row['character_id'], 'first_sidekick_maxed');
            Character::bumpGoalStatFor((int)$row['character_id'], 'sidekick_maxed');
        }
    }

    /**
     * Primeiro sidekick ao ATINGIR o nivel minimo (spec fixa do FIRST_SIDEKICK,
     * flag received_sidekick torna-o unico; NAO auto-equipa — o jogador escolhe).
     * Chamado do syncLevelFromXp.
     */
    public static function grantFirstIfEligible(Character $char): void
    {
        if ((int)($char->field('received_sidekick') ?? 0) === 1) {
            return;
        }
        if ((int)($char->field('level') ?? 0) < self::minRequiredLevel()) {
            return;
        }
        $characterId = $char->id();
        $newId = self::create($characterId, self::FIRST_SIDEKICK['identifier'], self::FIRST_SIDEKICK['quality'], [
            'stat_base_stamina'         => self::FIRST_SIDEKICK['stat_base_stamina'],
            'stat_base_strength'        => self::FIRST_SIDEKICK['stat_base_strength'],
            'stat_base_critical_rating' => self::FIRST_SIDEKICK['stat_base_critical_rating'],
            'stat_base_dodge_rating'    => self::FIRST_SIDEKICK['stat_base_dodge_rating'],
        ]);
        Db::exec('UPDATE `character` SET received_sidekick = 1 WHERE id = ?', [$characterId]);

        // GOALS de colecao (contadores vivos em goal_stats).
        $char->bumpGoalStat('first_sidekick_collected');
        $char->bumpGoalStat('sidekick_collected');

        // Regista nas ordens (igual ao servidor oficial antigo); favoritos ficam vazios.
        $orders = self::ordersData($characterId);
        $orders['orders'][] = $newId;
        self::saveOrders($characterId, $orders['orders'], $orders['favorit_orders']);
    }

    // =====================================================================
    // Goals de sidekick (current_goal_values) — unicos contadores vivos do
    // painel de metas; o cliente faz merge por chave (refreshGoalValues),
    // logo devolvemos SO estas entradas.
    // =====================================================================

    private const GOAL_KEYS = [
        'first_sidekick_collected',
        'sidekick_collected',
        'different_sidekick_collected',
        'first_sidekick_maxed',
        'sidekick_maxed',
        'first_sidekick_merged',
        'sidekick_merged',
        'sidekick_released',
    ];

    /**
     * Entradas parciais de current_goal_values {chave: {value, current_value}}.
     * Colecao vem da BD (contagem atual/distinta — sempre correta); o resto,
     * dos contadores acumulados em goal_stats.
     */
    public static function goalValues(int $characterId): array
    {
        $stats = Character::goalStatsFor($characterId);
        $ownedRow = Db::row(
            'SELECT COUNT(*) AS owned, COUNT(DISTINCT identifier) AS different FROM `sidekicks` WHERE character_id = ?',
            [$characterId]
        ) ?? [];
        $owned = (int)($ownedRow['owned'] ?? 0);
        $values = [
            // distintos ATUALMENTE possuidos (sem historico de libertados)
            'different_sidekick_collected' => $owned > 0 ? (int)($ownedRow['different'] ?? 0) : 0,
        ];
        $out = [];
        foreach (self::GOAL_KEYS as $key) {
            $v = $values[$key] ?? (int)($stats[$key] ?? 0);
            $out[$key] = ['value' => $v, 'current_value' => $v];
        }
        return $out;
    }

    // =====================================================================
    // Ordens / favoritos (inventory.sidekick_data — JSON PURO, ver cabecalho)
    // =====================================================================

    /** @return array{orders:int[],favorit_orders:int[]} */
    public static function ordersData(int $characterId): array
    {
        $empty = ['orders' => [], 'favorit_orders' => []];
        $raw = Db::value('SELECT sidekick_data FROM `inventory` WHERE character_id = ?', [$characterId]);
        if (!is_string($raw) || $raw === '') {
            return $empty;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $empty;
        }
        return [
            'orders'         => array_values(array_map('intval', $decoded['orders'] ?? [])),
            'favorit_orders' => array_values(array_map('intval', $decoded['favorit_orders'] ?? [])),
        ];
    }

    public static function saveOrders(int $characterId, array $orders, array $favorits): void
    {
        $payload = json_encode([
            'orders'         => array_values(array_map('intval', $orders)),
            'favorit_orders' => array_values(array_map('intval', $favorits)),
        ]);
        Db::exec('UPDATE `inventory` SET sidekick_data = ? WHERE character_id = ?', [$payload, $characterId]);
    }

    // =====================================================================
    // DO
    // =====================================================================

    /** DO completo do protocolo (lf @894163) — campos todos presentes, defaults 0/''. */
    public static function doFromRow(array $r): array
    {
        $r = self::withImprovement($r);
        $out = [
            'id'                  => (int)$r['id'],
            'character_id'        => (int)$r['character_id'],
            'status'              => (int)($r['status'] ?? 1),
            'identifier'          => (string)$r['identifier'],
            'name'                => (string)($r['name'] ?? ''),
            'quality'             => (int)($r['quality'] ?? 1),
            'level'               => (int)($r['level'] ?? 1),
            'xp'                  => (int)($r['xp'] ?? 0),
            'item_improvement_id' => (int)($r['item_improvement_id'] ?? 0),
        ];
        foreach (self::ALL_STATS as $stat) {
            $out['stat_base_' . $stat] = (int)($r['stat_base_' . $stat] ?? 0);
            $out['stat_' . $stat]      = (int)($r['stat_' . $stat] ?? 0);
        }
        foreach ([1, 2, 3] as $stage) {
            $out['stage' . $stage . '_skill_id'] = (int)($r['stage' . $stage . '_skill_id'] ?? 0);
        }
        $out['sewed_identifier'] = (string)($r['sewed_identifier'] ?? '');
        $out['sewed_level']      = (int)($r['sewed_level'] ?? 0);
        return $out;
    }
}
