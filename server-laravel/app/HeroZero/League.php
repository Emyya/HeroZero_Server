<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * LIGAS — regras puras (docs/implementar_leagues.md). Fonte de verdade: o PROPRIO
 * cliente (LeagueUtil/Dd no HeroZero.min.js) + constants_json.data do CDN oficial.
 * Onde o plano divergia do bundle, ganha o bundle (e.g. o bonus de moedas da
 * divisao e 1+valor, nao xvalor; os PONTOS de vitoria levam os multiplicadores
 * de guilda; o bonus ELO positivo e 5+league_elo_calculation_bonus).
 *
 * league_group_id codifica divisao E grupo: divisao = floor(group/100000)
 * (Dd.getLeagueDivisionId). Divisao 1 = group 100000; 0 = "nao inscrito".
 */
final class League
{
    public const GROUP_FACTOR = 100000;

    /** Constantes oficiais lidas de constants_json.data (mesma fonte que o cliente). */
    private static ?array $constantsCache = null;

    /** @return array<string,mixed> todas as chaves league_* + guild_artifact_duel_stamina_daily_league_fight. */
    public static function officialConstants(): array
    {
        if (self::$constantsCache !== null) {
            return self::$constantsCache;
        }
        // CACHE PERSISTENTE: o parse do asset (~80ms) repetia-se em toda acao
        // (PHP classico); a fatia league_* e pequena e invalida com o asset.
        $src = __DIR__ . '/../../public/assets/data/constants_json.data';
        if (!is_file($src)) {
            $src = __DIR__ . '/../../../assets/data/constants_json.data';
        }
        return self::$constantsCache = AssetCache::slice(
            'league_constants',
            is_file($src) ? $src : '',
            fn(): array => self::buildOfficialConstants()
        );
    }

    private static function buildOfficialConstants(): array
    {
        $out = [];
        foreach ([
            __DIR__ . '/../../public/assets/data/constants_json.data',
            __DIR__ . '/../../../assets/data/constants_json.data',
        ] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }
            $json = @gzuncompress($raw);
            if ($json === false) {
                break;
            }
            $data = json_decode($json, true);
            if (!is_array($data)) {
                break;
            }
            foreach ($data as $k => $v) {
                if ($k === 'league_divisions' || str_starts_with($k, 'league_')
                    || $k === 'guild_artifact_duel_stamina_daily_league_fight') {
                    $out[$k] = $v;
                }
            }
            break;
        }
        // Rede de seguranca (ficheiro ausente/corrupto): valores OFICIAIS extraidos
        // do proprio asset em 2026-08. Nao incluir league_divisions aqui — sem a
        // tabela completa e preferivel o cliente ficar em "unknown" do que mentir.
        $out += [
            'league_min_honor_points'                          => 500,
            'league_max_daily_league_fights'                   => 20,
            'league_attack_opponent_premium_currency_amount'   => 4,
            'league_change_opponents_premium_currency_amount'  => 2,
            'league_elo_calculation_k'                         => 11,
            'league_elo_calculation_multi'                     => 1200,
            'league_elo_calculation_bonus'                     => 5,
            'league_stamina_cost'                              => 20,
            'league_stamina_refresh_amount_per_minute'         => 0.333334,
            'guild_artifact_duel_stamina_daily_league_fight'   => 2,
        ];
        return $out;
    }

    public static function constant(string $key): float
    {
        return (float)(self::officialConstants()[$key] ?? 0);
    }

    /**
     * Math.round do JS: arredonda .5 na direcao de +INFINITO (-5.5 -> -5, 5.5 -> 6).
     * O round() do PHP vai PARA LONGE do zero no negativo (-5.5 -> -6) e divergia
     * do cliente exatamente nos empates ELO de meio ponto.
     */
    public static function jsRound(float $x): int
    {
        return (int)floor($x + 0.5);
    }

    /** @return array<int,array<string,mixed>> tabela oficial league_divisions ([] se o asset faltar). */
    public static function divisions(): array
    {
        $d = self::officialConstants()['league_divisions'] ?? null;
        return is_array($d) ? $d : [];
    }

    public static function divisionFromGroup(int $group): int
    {
        return $group > 0 ? intdiv($group, self::GROUP_FACTOR) : 0;
    }

    // ---------------------------------------------------------------- ELO

    /**
     * Pontos de VITORIA. Porte exato de Dd.leagueFightLeaguePointsWinReward:
     *   exp = 1/(1+10^((opp-my)/multi)); pts = round(K*(1-exp));
     *   pts>0 -> pts += 5 + league_elo_calculation_bonus;
     *   final = round(pts * mult1 * mult2)   <- booster de duelo da guilda + trofeu type 4.
     */
    public static function eloWin(int $myPts, int $oppPts, float $mult1 = 1.0, float $mult2 = 1.0): int
    {
        $multi = self::constant('league_elo_calculation_multi');
        $exp = 1.0 / (1.0 + pow(10, ($oppPts - $myPts) / $multi));
        $pts = self::jsRound(self::constant('league_elo_calculation_k') * (1.0 - $exp));
        if ($pts > 0) {
            $pts += 5 + self::constant('league_elo_calculation_bonus');
        }
        return self::jsRound($pts * $mult1 * $mult2);
    }

    /**
     * Pontos de DERROTA (negativo). Porte de Dd.leagueFightLeaguePointsLostReward:
     * exp com os pontos TROCADOS; abaixo de 0 subtrai 5 + bonus. SEM multiplicadores.
     */
    public static function eloLoss(int $myPts, int $oppPts): int
    {
        $multi = self::constant('league_elo_calculation_multi');
        $exp = 1.0 / (1.0 + pow(10, ($myPts - $oppPts) / $multi));
        $pts = self::jsRound(-self::constant('league_elo_calculation_k') * (1.0 - $exp));
        if ($pts < 0) {
            $pts -= 5 + self::constant('league_elo_calculation_bonus');
        }
        return (int)$pts;
    }

    /**
     * Moedas do VENCEDOR. Porte de Dd.leagueFightCoinWinReward:
     * duelGameCurrencyWinReward(nivel do VENCIDO) x booster duelo da guilda
     * x trofeu type 4 x (divisao do VENCEDOR != 0 ? 1 + game_currency_bonus : 1).
     * NOTA: o cliente multiplica por (1 + valor cru), nao por (1 + valor/100) --
     * bronze3 vale x2.0, superhero x4.4.
     */
    public static function coinsWin(int $winnerDivision, int $loserLevel, float $mult1 = 1.0, float $mult2 = 1.0): int
    {
        $coins = (float)DuelRewards::coinsWin($loserLevel, $mult1, $mult2);
        if ($winnerDivision !== 0) {
            $bonus = (float)(self::divisions()[$winnerDivision]['game_currency_bonus'] ?? 0);
            $coins *= 1.0 + $bonus;
        }
        return self::jsRound($coins);
    }

    /** Multiplicadores de guilda do cliente: [mult1 booster duelo, mult2 trofeus type 4]. */
    public static function guildMultipliers(int $boosterAmountPercent, ?int $guildId): array
    {
        return [
            1.0 + $boosterAmountPercent / 100.0,
            GuildArtifacts::pvpRewardMultiplier($guildId),
        ];
    }

    // ------------------------------------------------- bonus de atributos

    /**
     * Fator de atributos da divisao (1+base_attribute_bonus: +3%..+39%; 1.0 se nao
     * inscrito). Consumido por Character::statMultiplier: o bonus entra no MESMO
     * fator percentual sobre a base que booster/guilda, logo vale em TODOS os
     * combates (missoes, duelos, batalhas de guilda, gangues, liga) e aparece nos
     * stat_total_* do perfil. NAO voltar a aplicar sobre o perfil pronto do
     * battleProfile: seria contado duas vezes.
     */
    public static function attributeFactor(int $division): float
    {
        if ($division === 0) {
            return 1.0;
        }
        return 1.0 + (float)(self::divisions()[$division]['base_attribute_bonus'] ?? 0);
    }

    // ---------------------------------------------- mudanca de divisao

    /**
     * Regra oficial COM HISTERESE sobre a linha do character (por referencia):
     * sobe se points >= max_points[div] (max_points>0); desce 1 se demotion >= 0
     * e points < demotion. Devolve "X_Y" quando muda (X=nova, Y=antiga), null caso
     * contrario. Chamar DEPOIS de creditar os pontos.
     *
     * @param array<string,mixed> $row linha com league_points/league_group_id (UPDATE feito aqui)
     */
    public static function applyDivisionChange(array &$row, int $characterId): ?string
    {
        $divisions = self::divisions();
        $oldDiv = self::divisionFromGroup((int)($row['league_group_id'] ?? 0));
        if ($oldDiv === 0 || !isset($divisions[$oldDiv])) {
            return null;
        }
        $points = (int)($row['league_points'] ?? 0);
        $newDiv = $oldDiv;
        $maxPoints = (int)($divisions[$oldDiv]['max_points'] ?? 0);
        $demotion = (int)($divisions[$oldDiv]['demotion'] ?? -1);
        if ($maxPoints > 0 && $points >= $maxPoints && isset($divisions[$oldDiv + 1])) {
            $newDiv = $oldDiv + 1;
        } elseif ($demotion >= 0 && $points < $demotion && isset($divisions[$oldDiv - 1])) {
            $newDiv = $oldDiv - 1;
        }
        if ($newDiv === $oldDiv) {
            return null;
        }
        $newGroup = self::GROUP_FACTOR * $newDiv + 1;
        Db::exec('UPDATE `character` SET league_group_id = ? WHERE id = ?', [$newGroup, $characterId]);
        $row['league_group_id'] = $newGroup;
        return $newDiv . '_' . $oldDiv;
    }

    // ------------------------------------------------------ stamina de liga

    /** Maximo BASE da barra pelo booster ativo (''=20, league1=60, league2=120). */
    public static function expectedMaxStamina(string $boosterId): int
    {
        return match ($boosterId) {
            'booster_league1' => (int)(Live::BOOSTER_CATALOG['booster_league1'][1] ?? 60),
            'booster_league2' => (int)(Live::BOOSTER_CATALOG['booster_league2'][1] ?? 120),
            default => 20,
        };
    }

    /** Taxa de regeneracao (pontos/minuto) pelo booster ativo. */
    public static function refreshPerMinute(string $boosterId): float
    {
        return match ($boosterId) {
            'booster_league1' => self::constant('league_stamina_refresh_amount_per_minute_first_fight_booster1'),
            'booster_league2' => self::constant('league_stamina_refresh_amount_per_minute_first_fight_booster2'),
            default => self::constant('league_stamina_refresh_amount_per_minute'),
        };
    }

    /**
     * Clone do Character::syncDuelStamina para a stamina de LIGA (lazy):
     * expira o booster fora do prazo (id limpo, max volta a 20), reescreve
     * max_league_stamina BASE conforme o booster (trofeus Coragem somam por cima
     * so no overlay/GuildArtifacts::effectiveMax) e regenera pelos intervalos JA
     * consumidos. Le a linha fresca do banco e devolve-a atualizada.
     *
     * @param array<string,mixed> $row linha `character` (minimo: id + colunas league_*)
     * @return array<string,mixed> a mesma linha com stamina/max/booster sincronizados
     */
    public static function syncStamina(array $row): array
    {
        $now = GameTime::now();
        $cid = (int)($row['id'] ?? 0);
        if ($cid <= 0) {
            return $row;
        }

        $boosterId = (string)($row['active_league_booster_id'] ?? '');
        $expires = (int)($row['ts_active_league_boost_expires'] ?? 0);
        $dirty = [];

        if ($boosterId !== '' && $expires > 0 && $expires <= $now) {
            $boosterId = '';
            $dirty += ['active_league_booster_id' => '', 'ts_active_league_boost_expires' => 0];
        }

        $baseMax = self::expectedMaxStamina($boosterId);
        $max = GuildArtifacts::effectiveMax(
            'league_stamina',
            $baseMax,
            (int)($row['guild_id'] ?? 0)
        ) + Hideout::personalArenaBonuses((int)($row['id'] ?? 0))['league_stamina'];
        $sta = (int)($row['league_stamina'] ?? 0);
        $ts  = (int)($row['ts_last_league_stamina_change'] ?? 0);

        if ((int)($row['max_league_stamina'] ?? 0) !== $baseMax) {
            $dirty['max_league_stamina'] = $baseMax;
        }
        if ($sta >= $max) {
            if ($ts !== $now) {
                $sta = min($sta, $max);
                $dirty += ['league_stamina' => $sta, 'ts_last_league_stamina_change' => $now];
            }
        } else {
            if ($ts <= 0) {
                $ts = $now;
            }
            $step = (int)round(60.0 / max(0.000001, self::refreshPerMinute($boosterId)));
            while ($sta < $max && $now - $ts >= $step) {
                $sta++;
                $ts += $step;
            }
            if ($sta >= $max) {
                $sta = $max;
                $ts = $now;
            }
            if ($sta !== (int)($row['league_stamina'] ?? 0) || $ts !== (int)($row['ts_last_league_stamina_change'] ?? 0)) {
                $dirty += ['league_stamina' => $sta, 'ts_last_league_stamina_change' => $ts];
            }
        }

        if ($dirty !== []) {
            $sets = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", array_keys($dirty)));
            Db::exec("UPDATE `character` SET {$sets} WHERE id = ?", [...array_values($dirty), $cid]);
            $row = array_merge($row, $dirty);
        }
        return $row;
    }

    // ------------------------------------------------------- limite diario

    /** Maximo de lutas hoje: constante + 2x trofeus Coragem (type 1) da guilda. */
    public static function maxDailyFights(?int $guildId): int
    {
        $perTrophy = (int)self::constant('guild_artifact_duel_stamina_daily_league_fight');
        return (int)self::constant('league_max_daily_league_fights')
            + $perTrophy * GuildArtifacts::effects($guildId)['n1'];
    }

    /**
     * Reset LAZY do contador diario: se a ultima luta foi noutro dia do RELOGIO
     * DE JOGO, zera league_fight_count (sem DDL novo). Recebe e devolve a linha.
     */
    public static function syncDailyCount(array $row): array
    {
        $count = (int)($row['league_fight_count'] ?? 0);
        if ($count === 0) {
            return $row;
        }
        $tz = Live::gameTimezone();
        $last = (new \DateTimeImmutable('@' . (int)($row['ts_last_league_fight'] ?? 0)))->setTimezone($tz);
        $today = (new \DateTimeImmutable('@' . GameTime::now()))->setTimezone($tz);
        if ($last->format('Y-m-d') === $today->format('Y-m-d')) {
            return $row;
        }
        $cid = (int)($row['id'] ?? 0);
        Db::exec('UPDATE `character` SET league_fight_count = 0 WHERE id = ?', [$cid]);
        $row['league_fight_count'] = 0;
        return $row;
    }

    // ---------------------------------------------------------------- epoca

    /**
     * Fim da epoca ATUAL: proxima segunda-feira 00:00 no RELOGIO DE JOGO
     * (respeita o offset de debug do GameTime -> testavel via /servertime).
     */
    public static function seasonEndTimestamp(): int
    {
        $now = Live::gameNow();
        // (8-N)%7 e nao (7-N)%7: esta ultima caia sempre no DOMINGO 00:00
        // ("daysUntilMonday" contava ate ao fim de semana). Na segunda (N=1)
        // da 0 -> a guarda de baixo empurra +7 dias.
        $daysUntilMonday = (8 - (int)$now->format('N')) % 7; // N: 1=seg .. 7=dom
        $end = $now->modify("+{$daysUntilMonday} days")->setTime(0, 0, 0);
        if ($end <= $now) {
            $end = $end->modify('+7 days');
        }
        return $end->getTimestamp();
    }

    /**
     * Garante uma epoca valida (lazy, chamada nas actions de liga e no boot):
     * cria a linha global se falta; se expirou, roda a epoca — pontos de todos
     * voltam ao entry_points da divisao atual — e agenda a semana seguinte.
     * Devolve o ts_end vigente.
     */
    public static function ensureSeason(): int
    {
        $now = GameTime::now();
        $season = Db::row('SELECT ts_end FROM `league_season` WHERE id = 1');
        if ($season === null) {
            $end = self::seasonEndTimestamp();
            Db::exec('INSERT IGNORE INTO `league_season` (id, ts_end) VALUES (1, ?)', [$end]);
            return $end;
        }
        $end = (int)$season['ts_end'];
        if ($end > $now) {
            return $end;
        }

        // ROLAMENTO: pontos de cada divisao voltam ao seu entry_points. Uma UPDATE
        // por divisao (o grupo semeado e sempre div*100000, mas cobrimos a gama
        // toda por seguranca). Quem nunca entrou (group 0) fica intacto.
        $nextEnd = self::seasonEndTimestamp();
        foreach (self::divisions() as $div => $info) {
            $from = (int)$div * self::GROUP_FACTOR;
            Db::exec(
                'UPDATE `character` SET league_points = ? WHERE league_group_id BETWEEN ? AND ?',
                [(int)($info['entry_points'] ?? 0), $from, $from + self::GROUP_FACTOR - 1]
            );
        }
        Db::exec('UPDATE `league_season` SET ts_end = ? WHERE id = 1', [$nextEnd]);
        return $nextEnd;
    }
}
