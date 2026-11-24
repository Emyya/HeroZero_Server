<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Trofeus de guilda (artefactos) — docs/trofeus/implementacao.md.
 *
 * Modelo de dados: SEM tabela propria. A posse vive no array JSON
 * `guild.artifact_ids` (coluna ja existente no schema); cada id e UNICO em
 * todo o servidor (maximo 50 a circular, um por entrada do registo oficial).
 * O tipo/valor/penalidade de cada id vem das constantes oficiais do CDN
 * (`public/assets/data/constants_json.data`, chave "guild_artifacts") que o
 * cliente ja carrega sozinho — aqui lemos o mesmo ficheiro para o servidor
 * conseguir, p.ex., validar releases.
 *
 * Regras oficiais implementadas:
 *  - Slots 1..5 desbloqueados pela expansao (thresholds 0/24.5/50/74.8/99.4%).
 *    ATENCAO: o cliente calcula os slots com o divisor BASE (175), nao com o
 *    180 usado na percentagem exibida — replicar exatamente para dessincronizar zero.
 *  - Roubo: vencedor (ataque OU defesa) com slot livre rouba 1 trofeu aleatorio ao perdedor.
 *  - Emissao: vitoria por ATAQUE com slot livre enquanto <50 trofeus no servidor
 *    entra 1 trofeu novo aleatorio (nao possuido por ninguem).
 */
final class GuildArtifacts
{
    /** guild_artifact_slot{1..5}_unlock_percentage (balancing_constants.json). */
    private const SLOT_THRESHOLDS = [0.0, 0.245, 0.5, 0.748, 0.994];

    /**
     * Divisor da expansao para SLOTS: o cliente chama
     * totalImprovementPercentage() sem argumentos → guild_percentage_total_base.
     * NAO usar o 180 ("real") aqui — e so para a percentagem exibida.
     */
    private const TOTAL_BASE_DIVISOR = 175;

    /** release_guild_artifact_cost / _cooldown (balancing_constants.json). */
    public const RELEASE_COST = 40;
    public const RELEASE_COOLDOWN = 604800;

    /** Maximo de trofeus a circular em todo o servidor. */
    public const MAX_SERVER_ARTIFACTS = 50;

    /**
     * Efeitos mecanicos por unidade de trofeu (valores das constantes oficiais):
     *  - type 1 (Coragem): +20 max_duel_stamina, -2 duel_stamina_cost, +2 max_league_stamina
     *  - type 2 (Energia): +20 max_quest_energy
     *  - type 3 (Motivacao): +5 max_training_energy
     *  - type 4 (Duelo & Liga): +25% recompensas de duelo/liga
     * A penalizacao de skills nas lutas entre teams usa o attribute_reduction
     * individual de CADA trofeu (soma), nao um valor fixo por tipo.
     */
    private const BONUS_PER_TROPHY = [
        1 => ['duel_stamina' => 20, 'league_stamina' => 2],
        2 => ['quest_energy' => 20],
        // Motivacao: +5 UNIDADES de treino (training_count). A barra de energia de
        // treino (training_energy) e 30/30 fixa e NAO recebe bonus de trofeu.
        3 => ['training_count' => 5],
        4 => ['pvp_reward_percent' => 25],
    ];

    /** Piso oficial do custo de coragem por duelo com trofeus type 1 (-2 cada). */
    public const DUEL_STAMINA_COST_BASE = 20;
    public const DUEL_STAMINA_COST_MIN = 10;

    /** Cache dos efeitos por guilda (invalidado em grant/takeRandom). */
    private static array $effectsCache = [];

    /**
     * Efeitos agregados dos trofeus de uma guilda (cache por request).
     *
     * @return array{n1:int,n2:int,n3:int,n4:int,reduction:float}
     *         n1..n4 = quantidade possuida de cada tipo;
     *         reduction = soma dos attribute_reduction (0.07+0.10+...) p/ skills.
     */
    public static function effects(?int $guildId): array
    {
        $zero = ['n1' => 0, 'n2' => 0, 'n3' => 0, 'n4' => 0, 'reduction' => 0.0];
        if ($guildId === null || $guildId <= 0) {
            return $zero;
        }
        if (isset(self::$effectsCache[$guildId])) {
            return self::$effectsCache[$guildId];
        }
        $g = Db::row('SELECT artifact_ids FROM `guild` WHERE id = ?', [$guildId]);
        if ($g === null) {
            return $zero;
        }
        $reg = self::registry();
        $out = $zero;
        foreach (self::ownedIds($g) as $id) {
            $entry = $reg[$id] ?? null;
            if ($entry === null) {
                continue;
            }
            $t = (int)$entry['type'];
            if ($t >= 1 && $t <= 4) {
                $out["n$t"]++;
            }
            $out['reduction'] += (float)$entry['attribute_reduction'];
        }
        self::$effectsCache[$guildId] = $out;
        return $out;
    }

    /** Invalida o cache de efeitos (chamar quando artifact_ids muda fora de grant/takeRandom). */
    public static function forgetEffects(int $guildId): void
    {
        unset(self::$effectsCache[$guildId]);
    }

    /** Maximo efetivo de um recurso: base da coluna + bonus dos trofeus. */
    public static function effectiveMax(string $resource, int $baseMax, ?int $guildId): int
    {
        $e = self::effects($guildId);
        $per = match ($resource) {
            'duel_stamina'    => self::BONUS_PER_TROPHY[1]['duel_stamina'] * $e['n1'],
            'league_stamina'  => self::BONUS_PER_TROPHY[1]['league_stamina'] * $e['n1'],
            'quest_energy'    => self::BONUS_PER_TROPHY[2]['quest_energy'] * $e['n2'],
            'training_count'  => self::BONUS_PER_TROPHY[3]['training_count']  * $e['n3'],
            default           => 0,
        };
        return $baseMax + $per;
    }

    /** Custo efetivo de coragem por duelo (trofeus type 1 descontam 2 cada). */
    public static function duelStaminaCost(?int $guildId): int
    {
        $cost = self::DUEL_STAMINA_COST_BASE - 2 * self::effects($guildId)['n1'];
        return max(self::DUEL_STAMINA_COST_MIN, $cost);
    }

    /** Multiplicador de recompensas pvp (1 + 25% x n4): duelo e liga. */
    public static function pvpRewardMultiplier(?int $guildId): float
    {
        return 1.0 + self::BONUS_PER_TROPHY[4]['pvp_reward_percent'] * self::effects($guildId)['n4'] / 100.0;
    }

    /**
     * Registo oficial dos trofeus: id => [identifier, type, value, attribute_reduction].
     * type: 1=duel_stamina, 2=quest_energy, 3=training_session, 4=coin_pvp_reward.
     * Lido do MESMO constants_json.data que o cliente usa (fonte unica); estatico.
     * @return array<int, array{identifier:string,type:int,value:int,attribute_reduction:float}>
     */
    public static function registry(): array
    {
        static $reg = null;
        if ($reg !== null) {
            return $reg;
        }
        // CACHE PERSISTENTE: mesmo asset (e mesmo custo de parse) do League/Sidekick.
        $src = __DIR__ . '/../../public/assets/data/constants_json.data';
        if (!is_file($src)) {
            $src = __DIR__ . '/../../../assets/data/constants_json.data';
        }
        return $reg = AssetCache::slice('guild_artifacts', is_file($src) ? $src : '', fn(): array => self::buildRegistry());
    }

    private static function buildRegistry(): array
    {
        $reg = [];
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
            // zlib (header 78 9c) — gzuncompress, nao gzdecode (que espera gzip).
            $json = @gzuncompress($raw);
            if ($json === false) {
                break;
            }
            $data = json_decode($json, true);
            if (!is_array($data) || !is_array($data['guild_artifacts'] ?? null)) {
                break;
            }
            foreach ($data['guild_artifacts'] as $id => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $reg[(int)$id] = [
                    'identifier'          => (string)($entry['asset_identifier'] ?? ''),
                    'type'                => (int)($entry['type'] ?? 0),
                    'value'               => (int)($entry['value'] ?? 0),
                    'attribute_reduction' => (float)($entry['attribute_reduction'] ?? 0),
                ];
            }
            break;
        }
        ksort($reg);
        return $reg;
    }

    /**
     * Slots desbloqueados pela expansao — mesma conta do cliente
     * (Va.get_unlockedArtifactSlots): soma dos 4 upgrades / divisor BASE,
     * comparada contra os 5 thresholds (slot 1 = 0% → qualquer guilda tem >=1).
     */
    public static function unlockedSlots(array $guild): int
    {
        $sum = (int)($guild['stat_guild_capacity'] ?? 0)
             + (int)($guild['stat_character_base_stats_boost'] ?? 0)
             + (int)($guild['stat_quest_xp_reward_boost'] ?? 0)
             + (int)($guild['stat_quest_game_currency_reward_boost'] ?? 0);
        $percentage = self::TOTAL_BASE_DIVISOR > 0 ? ($sum / self::TOTAL_BASE_DIVISOR) * 100 : 0.0;
        $slots = 0;
        foreach (self::SLOT_THRESHOLDS as $t) {
            if ($percentage >= 100 * $t) {
                $slots++;
            }
        }
        return $slots;
    }

    /** Ids possuidos pela guilda (parse tolerante do array JSON). @return list<int> */
    public static function ownedIds(array $guild): array
    {
        $ids = json_decode((string)($guild['artifact_ids'] ?? ''), true);
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $v) {
            $v = (int)$v;
            if ($v > 0) {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }

    /** Quantos trofeus existem em todo o servidor (soma das guildas). */
    public static function circulatingCount(): int
    {
        $total = 0;
        foreach (Db::rows('SELECT artifact_ids FROM `guild`') as $row) {
            $total += count(self::ownedIds($row));
        }
        return $total;
    }

    /** Ids do registo que ninguem possui (pool disponivel para emissao). @return list<int> */
    public static function availableIds(): array
    {
        $owned = [];
        foreach (Db::rows('SELECT artifact_ids FROM `guild`') as $row) {
            foreach (self::ownedIds($row) as $id) {
                $owned[$id] = true;
            }
        }
        $out = [];
        foreach (array_keys(self::registry()) as $id) {
            if (!isset($owned[$id])) {
                $out[] = (int)$id;
            }
        }
        return $out;
    }

    /** Adiciona um trofeu a guilda e sincroniza contadores. */
    public static function grant(int $guildId, int $artifactId): void
    {
        $g = Db::row('SELECT * FROM `guild` WHERE id = ?', [$guildId]);
        if ($g === null) {
            return;
        }
        $ids = self::ownedIds($g);
        if (!in_array($artifactId, $ids, true)) {
            $ids[] = $artifactId;
        }
        Db::exec('UPDATE `guild` SET artifact_ids = ? WHERE id = ?', [json_encode(array_values($ids)), $guildId]);
        unset(self::$effectsCache[$guildId]);
        self::syncCounters($guildId);
    }

    /**
     * Remove um trofeu ALEATORIO da guilda (roubo/libertacao automatica).
     * Devolve o id removido, ou null se a guilda nao tem trofeus.
     */
    public static function takeRandom(int $guildId): ?int
    {
        $g = Db::row('SELECT * FROM `guild` WHERE id = ?', [$guildId]);
        if ($g === null) {
            return null;
        }
        $ids = self::ownedIds($g);
        if ($ids === []) {
            return null;
        }
        $i = (int)array_rand($ids);
        $taken = (int)$ids[$i];
        unset($ids[$i]);
        Db::exec('UPDATE `guild` SET artifact_ids = ? WHERE id = ?', [json_encode(array_values($ids)), $guildId]);
        unset(self::$effectsCache[$guildId]);
        self::syncCounters($guildId);
        return $taken;
    }

    /** Recalcula artifacts_owned_current/max a partir do estado real da guilda. */
    public static function syncCounters(int $guildId): void
    {
        $g = Db::row('SELECT * FROM `guild` WHERE id = ?', [$guildId]);
        if ($g === null) {
            return;
        }
        // max = capacidade (slots desbloqueados), para o dialogo mostrar "atuais/max".
        Db::exec(
            'UPDATE `guild` SET artifacts_owned_current = ?, artifacts_owned_max = ? WHERE id = ?',
            [count(self::ownedIds($g)), self::unlockedSlots($g), $guildId]
        );
    }

    /**
     * Resolve a transferencia de trofeus no fim de uma batalha guilda-vs-guilda.
     * UMA ocorrencia por batalha; prioridade ao ROUBO (regra partilhada pelo
     * nosso desenho e pelo oficial). Sem roubo possivel, vitoria por ATAQUE com
     * slot livre e pool<50 emite um trofeu novo.
     *
     * @return null|array{artifact_id:int, stolen:bool} o que o VENCEDOR ganhou.
     */
    public static function resolveBattleTransfer(int $winnerGuildId, int $loserGuildId, bool $attackerWon): ?array
    {
        $winner = Db::row('SELECT * FROM `guild` WHERE id = ?', [$winnerGuildId]);
        $loser  = Db::row('SELECT * FROM `guild` WHERE id = ?', [$loserGuildId]);
        if ($winner === null || $loser === null) {
            return null;
        }
        $freeWinner = self::unlockedSlots($winner) - count(self::ownedIds($winner));

        // 1) Roubo: perdedor tem trofeus E vencedor tem slot livre.
        if ($freeWinner > 0 && self::ownedIds($loser) !== []) {
            $taken = self::takeRandom($loserGuildId);
            if ($taken !== null) {
                self::grant($winnerGuildId, $taken);
                Db::exec('UPDATE `guild` SET artifacts_lost = artifacts_lost + 1 WHERE id = ?', [$loserGuildId]);
                Db::exec('UPDATE `guild` SET artifacts_won = artifacts_won + 1 WHERE id = ?', [$winnerGuildId]);
                return ['artifact_id' => $taken, 'stolen' => true];
            }
        }

        return null;
    }
}
