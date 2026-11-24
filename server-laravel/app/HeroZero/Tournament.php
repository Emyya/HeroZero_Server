<?php
declare(strict_types=1);

namespace HeroZero;

use HeroZero\Live;
use HeroZero\Db as Store;

/**
 * Gestão do torneio semanal.
 *
 * Tipos de torneio (campo `type` no leaderboard):
 *   1 = XP ganho na semana          (delta xp          - xp_start)
 *   2 = Pontos de habilidade gastos (delta stat_base - stat_base_start)
 *   3 = PvP / duelos realizados     (COUNT duel)
 *
 * Recompensas por tipo (oficial, constants_json.data):
 *   XP (1):    donuts + stat_points
 *   Stats (2): stat_points
 *   PvP (4):   X% do XP do nível actual do herói
 */
final class Tournament
{
    // Status da tabela tournaments
    public const STATUS_ACTIVE     = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_FINISHED   = 3;

    // Tipos de torneio
    public const TYPE_XP    = 1;
    public const TYPE_STATS = 2;
    // Tipo INTERNO/pedido de leaderboard ("retrieveCharacterTournamentLeaderboard"
    // envia type=3 para o PvP). ATENCAO: as CHAVES do payload de recompensas usam
    // OUTRA numeracao no cliente (o dialogo tD so desenha 1, 2 e 4; "4" = Honra
    // do time/PvP). Usar self::rewardKey() ao escrever tournament_rewards.
    public const TYPE_PVP   = 3;

    // Constants do cliente ("tournament_rewards" / "global_tournament_rewards").
    // O cliente desenha os premios da barra do torneio APENAS a partir destes
    // catalogos (Nn.getRewardString filtra por tournament_type + rank), nao usa
    // os rewards pagos pelo servidor. Tipos NO CLIENTE:
    //   1 = Experiencia (donuts + stat points)   [aba "Experiência"]
    //   2 = Pontos de habilidade (stat points)   [aba "Pontos de Habilidade"]
    //   3/4/5 = legados (donuts / moedas guilda)
    //   6 = HeroCon Solo (stat points)
    // O PvP e a aba global (tipo 7) e le "global_tournament_rewards" tipo 1.
    // Valores OFICIAIS copiados de assets/data/constants_json.data (CDN).
    // reward_type: 1 = donuts, 2 = moedas premium de guilda, 3 = stat points.
    public const CLIENT_TOURNAMENT_REWARDS = [
        ['tournament_type' => 1, 'rank_start' => 1,  'rank_end' => 1,   'reward_type' => 1, 'reward_amount' => 75, 'reward_type_2' => 3, 'reward_amount_2' => 10],
        ['tournament_type' => 1, 'rank_start' => 2,  'rank_end' => 2,   'reward_type' => 1, 'reward_amount' => 60, 'reward_type_2' => 3, 'reward_amount_2' => 7],
        ['tournament_type' => 1, 'rank_start' => 3,  'rank_end' => 3,   'reward_type' => 1, 'reward_amount' => 40, 'reward_type_2' => 3, 'reward_amount_2' => 5],
        ['tournament_type' => 1, 'rank_start' => 4,  'rank_end' => 10,  'reward_type' => 1, 'reward_amount' => 20, 'reward_type_2' => 3, 'reward_amount_2' => 3],
        ['tournament_type' => 1, 'rank_start' => 11, 'rank_end' => 100, 'reward_type' => 1, 'reward_amount' => 5,  'reward_type_2' => 3, 'reward_amount_2' => 2],
        ['tournament_type' => 2, 'rank_start' => 1,  'rank_end' => 1,   'reward_type' => 3, 'reward_amount' => 40, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 2, 'rank_start' => 2,  'rank_end' => 2,   'reward_type' => 3, 'reward_amount' => 35, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 2, 'rank_start' => 3,  'rank_end' => 3,   'reward_type' => 3, 'reward_amount' => 30, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 2, 'rank_start' => 4,  'rank_end' => 10,  'reward_type' => 3, 'reward_amount' => 20, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 2, 'rank_start' => 11, 'rank_end' => 100, 'reward_type' => 3, 'reward_amount' => 10, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 3, 'rank_start' => 1,  'rank_end' => 1,   'reward_type' => 1, 'reward_amount' => 30, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 3, 'rank_start' => 2,  'rank_end' => 2,   'reward_type' => 1, 'reward_amount' => 25, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 3, 'rank_start' => 3,  'rank_end' => 3,   'reward_type' => 1, 'reward_amount' => 20, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 3, 'rank_start' => 4,  'rank_end' => 10,  'reward_type' => 1, 'reward_amount' => 10, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 3, 'rank_start' => 11, 'rank_end' => 100, 'reward_type' => 1, 'reward_amount' => 5,  'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 4, 'rank_start' => 1,  'rank_end' => 1,   'reward_type' => 2, 'reward_amount' => 200, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 4, 'rank_start' => 2,  'rank_end' => 2,   'reward_type' => 2, 'reward_amount' => 150, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 4, 'rank_start' => 3,  'rank_end' => 3,   'reward_type' => 2, 'reward_amount' => 100, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 4, 'rank_start' => 4,  'rank_end' => 10,  'reward_type' => 2, 'reward_amount' => 50,  'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 5, 'rank_start' => 1,  'rank_end' => 1,   'reward_type' => 2, 'reward_amount' => 100, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 5, 'rank_start' => 2,  'rank_end' => 2,   'reward_type' => 2, 'reward_amount' => 80,  'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 5, 'rank_start' => 3,  'rank_end' => 3,   'reward_type' => 2, 'reward_amount' => 60,  'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 5, 'rank_start' => 4,  'rank_end' => 10,  'reward_type' => 2, 'reward_amount' => 30,  'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 1,  'rank_end' => 1,   'reward_type' => 3, 'reward_amount' => 20, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 2,  'rank_end' => 2,   'reward_type' => 3, 'reward_amount' => 18, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 3,  'rank_end' => 3,   'reward_type' => 3, 'reward_amount' => 16, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 4,  'rank_end' => 4,   'reward_type' => 3, 'reward_amount' => 14, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 5,  'rank_end' => 5,   'reward_type' => 3, 'reward_amount' => 12, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 6,  'rank_end' => 10,  'reward_type' => 3, 'reward_amount' => 10, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 11, 'rank_end' => 20,  'reward_type' => 3, 'reward_amount' => 5,  'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 6, 'rank_start' => 21, 'rank_end' => 30,  'reward_type' => 3, 'reward_amount' => 3,  'reward_type_2' => 0, 'reward_amount_2' => 0],
    ];

    // Catalogo "global_tournament_rewards" (aba PVP): faixas de rank globais.
    public const CLIENT_GLOBAL_TOURNAMENT_REWARDS = [
        ['tournament_type' => 1, 'rank_start' => 1,    'rank_end' => 30,    'reward_type' => 1, 'reward_amount' => 50, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 31,   'rank_end' => 100,   'reward_type' => 1, 'reward_amount' => 45, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 101,  'rank_end' => 500,   'reward_type' => 1, 'reward_amount' => 40, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 501,  'rank_end' => 1000,  'reward_type' => 1, 'reward_amount' => 35, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 1001, 'rank_end' => 2500,  'reward_type' => 1, 'reward_amount' => 30, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 2501, 'rank_end' => 5000,  'reward_type' => 1, 'reward_amount' => 25, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 5001, 'rank_end' => 7500,  'reward_type' => 1, 'reward_amount' => 20, 'reward_type_2' => 0, 'reward_amount_2' => 0],
        ['tournament_type' => 1, 'rank_start' => 7501, 'rank_end' => 10000, 'reward_type' => 1, 'reward_amount' => 10, 'reward_type_2' => 0, 'reward_amount_2' => 0],
    ];

    // Torneio XP: donuts por rank
    private const REWARDS_XP_DONUTS = [
        ['rank_start' => 1,   'rank_end' => 1,   'amount' => 75],
        ['rank_start' => 2,   'rank_end' => 2,   'amount' => 60],
        ['rank_start' => 3,   'rank_end' => 3,   'amount' => 40],
        ['rank_start' => 4,   'rank_end' => 10,  'amount' => 20],
        ['rank_start' => 11,  'rank_end' => 100, 'amount' => 5],
    ];

    // Torneio XP: stat_points por rank (reward_type_2)
    private const REWARDS_XP_STAT_POINTS = [
        ['rank_start' => 1,   'rank_end' => 1,   'amount' => 10],
        ['rank_start' => 2,   'rank_end' => 2,   'amount' => 7],
        ['rank_start' => 3,   'rank_end' => 3,   'amount' => 5],
        ['rank_start' => 4,   'rank_end' => 10,  'amount' => 3],
        ['rank_start' => 11,  'rank_end' => 100, 'amount' => 2],
    ];

    // Torneio Stats: stat_points por rank
    private const REWARDS_STATS = [
        ['rank_start' => 1,   'rank_end' => 1,   'amount' => 40],
        ['rank_start' => 2,   'rank_end' => 2,   'amount' => 35],
        ['rank_start' => 3,   'rank_end' => 3,   'amount' => 30],
        ['rank_start' => 4,   'rank_end' => 10,  'amount' => 20],
        ['rank_start' => 11,  'rank_end' => 100, 'amount' => 10],
    ];

    // Torneio PvP: percentagem do XP do nível actual por rank (global_tournament_rewards)
    private const REWARDS_PVP_XP_PCT = [
        ['rank_start' => 1,     'rank_end' => 30,    'amount' => 50],
        ['rank_start' => 31,    'rank_end' => 100,   'amount' => 45],
        ['rank_start' => 101,   'rank_end' => 500,   'amount' => 40],
        ['rank_start' => 501,   'rank_end' => 1000,  'amount' => 35],
        ['rank_start' => 1001,  'rank_end' => 2500,  'amount' => 30],
        ['rank_start' => 2501,  'rank_end' => 5000,  'amount' => 25],
        ['rank_start' => 5001,  'rank_end' => 7500,  'amount' => 20],
        ['rank_start' => 7501,  'rank_end' => 10000, 'amount' => 10],
    ];

    /** Um ciclo por tipo, no fuso do jogo; 21:00 mantém-se durante mudanças de hora. */
    public static function period(int $type, int $now): array
    {
        $day = match ($type) { self::TYPE_XP => 0, self::TYPE_PVP => 2, self::TYPE_STATS => 3,
            default => throw new \InvalidArgumentException('Tipo de torneio inválido.') };
        $date = (new \DateTimeImmutable('@' . $now))->setTimezone(Live::gameTimezone());
        $start = $date->setTime(21, 0)->modify('-' . (((int)$date->format('w') - $day + 7) % 7) . ' days');
        if ($start->getTimestamp() > $now) $start = $start->modify('-7 days');
        return ['ts_start' => $start->getTimestamp(), 'ts_end' => $start->modify('+7 days')->getTimestamp(),
            'week' => (int)$start->format('oW')];
    }

    /** Segundos antes do fim de um ciclo em que se volta a exigir o lock do calendário. */
    private const LOCK_MARGIN = 5;

    /**
     * Ciclos atuais sem lock nem transação. Só responde quando existem os três tipos,
     * nenhum termina dentro da margem e não há torneios antigos por migrar; senão
     * devolve null e synchronize() segue o caminho com lock, o único que fecha/cria.
     */
    private static function currentUnlocked(int $now): ?array
    {
        if (Store::row('SELECT id FROM tournaments WHERE type IS NULL AND status IN (1,2) LIMIT 1') !== null) return null;
        $result = [];
        foreach ([self::TYPE_XP, self::TYPE_STATS, self::TYPE_PVP] as $type) {
            $row = Store::row('SELECT * FROM tournaments WHERE type = ? AND status = 1 ORDER BY id LIMIT 1', [$type]);
            if ($row === null || (int)$row['ts_end'] - $now <= self::LOCK_MARGIN) return null;
            $result[$type] = $row;
        }
        return $result;
    }

    /** Encerra e cria ciclos sob um único lock: pedidos concorrentes não abrem outra cadeia. */
    public static function synchronize(?int $now = null): array
    {
        $now ??= GameTime::now();
        if (($fast = self::currentUnlocked($now)) !== null) return $fast;
        return self::rewardTransaction(0, static function () use ($now): array {
            $lock = Store::row('SELECT id FROM tournament_calendar_lock WHERE id = 1 FOR UPDATE');
            if ($lock === null) throw new \RuntimeException('Executar migrate_tournament_calendar.php.');
            $legacy = Store::row('SELECT id FROM tournaments WHERE type IS NULL AND status IN (1,2) ORDER BY id LIMIT 1 FOR UPDATE');
            // Escolhe uma só base de progresso. Os ciclos antigos não geram mais prémios.
            $seed = $legacy === null ? null : (int)$legacy['id'];
            Store::exec('UPDATE tournaments SET status = 4 WHERE type IS NULL AND status IN (1,2)');
            $result = [];
            foreach ([self::TYPE_XP, self::TYPE_STATS, self::TYPE_PVP] as $type) {
                $current = self::row('SELECT * FROM tournaments WHERE type = ? AND status = 1 ORDER BY id LIMIT 1 FOR UPDATE', [$type]);
                if ($current !== null && (int)$current->ts_end <= $now) {
                    self::finish($current);
                    $current = null;
                }
                if ($current === null) {
                    $period = self::period($type, $now);
                    Store::exec('INSERT INTO tournaments (type, week, ts_start, ts_end, status) VALUES (?, ?, ?, ?, 1)',
                        [$type, $period['week'], $period['ts_start'], $period['ts_end']]);
                    $tid = (int)Store::pdo()->lastInsertId();
                    self::snapshotAll($tid);
                    if ($seed !== null) {
                        Store::exec('UPDATE tournament_snapshots n JOIN tournament_snapshots o ON o.character_id=n.character_id AND o.tournament_id=? SET n.xp_start=o.xp_start, n.honor_start=o.honor_start, n.guild_honor_start=o.guild_honor_start, n.stat_base_start=o.stat_base_start WHERE n.tournament_id=?', [$seed, $tid]);
                    }
                    $current = self::row('SELECT * FROM tournaments WHERE id = ?', [$tid]);
                }
                $result[$type] = (array)$current;
            }
            return $result;
        });
    }

    public static function active(int $type = self::TYPE_XP): ?array
    {
        return self::synchronize()[$type] ?? null;
    }

    /** Compatibilidade CLI: criar é idempotente, nunca força um segundo ciclo. */
    public static function create(int $type = self::TYPE_XP): ?array
    {
        return self::active($type);
    }

    public static function timestamps(): array
    {
        self::synchronize();
        return self::calendarTimestamps();
    }

    /** Contrato de datas sem acesso à BD, também usado no boot e respostas parciais. */
    public static function calendarTimestamps(?int $now = null): array
    {
        $now ??= GameTime::now();
        return ['tournament_end_timestamp' => self::period(self::TYPE_XP, $now)['ts_end'],
            'global_tournament_end_timestamp' => self::period(self::TYPE_PVP, $now)['ts_end'],
            'tournament_end_timestamps' => ['1' => self::period(self::TYPE_XP, $now)['ts_end'],
                '2' => self::period(self::TYPE_STATS, $now)['ts_end']]];
    }

    /** Jogadores novos recebem uma base em cada um dos três ciclos. */
    /** Inscrição comum aos dois logins; INSERT IGNORE preserva a base existente. */
    public static function ensureCharacterSnapshot(Character $char): void
    {
        $statBase = 0;
        foreach (['stamina', 'strength', 'critical_rating', 'dodge_rating'] as $stat) {
            $statBase += (int)($char->field('stat_base_' . $stat) ?? 0);
        }
        self::ensureSnapshot($char->id(), (int)($char->field('guild_id') ?? 0),
            (int)($char->field('xp') ?? 0), (int)($char->field('honor') ?? 0), $statBase);
    }

    public static function ensureSnapshot(int $charId, int $guildId, int $xp, int $honor, int $statBase): void
    {
        $cycles = self::synchronize();
        foreach ($cycles as $t) {
            $guildHonor = $guildId > 0 ? (int)(Store::value('SELECT honor FROM guild WHERE id = ?', [$guildId]) ?? 0) : 0;
            Store::exec('INSERT IGNORE INTO tournament_snapshots (tournament_id, character_id, guild_id, xp_start, honor_start, guild_honor_start, stat_base_start) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [(int)$t['id'], $charId, $guildId, $xp, $honor, $guildHonor, $statBase]);
        }
    }

    private static function row(string $sql, array $params = []): ?object
    {
        $row = Store::row($sql, $params);
        return $row === null ? null : (object)$row;
    }

    private static function rows(string $sql, array $params = []): array
    {
        return array_map(static fn($row) => (object)$row, Store::rows($sql, $params));
    }

    /** Retorna a coluna e snapshot corretos para o tipo de torneio. */
    public static function columnsForType(int $type): array
    {
        return match ($type) {
            self::TYPE_XP    => ['col' => 'xp',        'snap' => 'xp_start'],
            self::TYPE_PVP   => ['col' => 'honor',     'snap' => 'honor_start'],
            self::TYPE_STATS => ['col' => 'stat_base', 'snap' => 'stat_base_start'],
            default          => ['col' => 'xp',        'snap' => 'xp_start'],
        };
    }

    /**
     * Leaderboard de torneio (local, mesmo servidor).
     * Devolve array com: leaderboard_characters, max_characters, centered_rank, tournament_end_timestamp
     */
    public static function leaderboard(int $type, int $charId, ?string $charName, int $sortRank): array
    {
        $t = self::active($type);
        if ($t === null) {
            return ['error' => 'errTournamentLocked'];
        }
        $tid  = (int)$t['id'];
        $tsEnd = (int)$t['ts_end'];

        $tsStart = (int)$t['ts_start'];

        // Expressao de valor por tipo:
        //   XP    -> delta de xp desde o snapshot
        //   PVP   -> numero de duelos jogados desde ts_start (tabela duel)
        //   STATS -> delta de stat_base total desde o snapshot
        if ($type === self::TYPE_PVP) {
            $valueExpr = "(SELECT COUNT(*) FROM duel d WHERE (d.character_a_id = ch.id) AND d.ts_creation >= {$tsStart} AND d.ts_creation < {$tsEnd})";
        } elseif ($type === self::TYPE_STATS) {
            $valueExpr = "GREATEST(0, (ch.stat_base_stamina + ch.stat_base_strength + ch.stat_base_critical_rating + ch.stat_base_dodge_rating) - CAST(ts.stat_base_start AS SIGNED))";
        } else {
            $valueExpr = "GREATEST(0, CAST(ch.xp AS SIGNED) - CAST(ts.xp_start AS SIGNED))";
        }

        $maxCh = (int)(self::row(
            "SELECT COUNT(*) as cnt FROM tournament_snapshots WHERE tournament_id = ?",
            [$tid]
        )?->cnt ?? 0);

        if ($maxCh === 0) {
            return ['error' => 'errRetrieveLeaderboardEmpty'];
        }

        // Determinar centered_rank
        if ($sortRank > 0) {
            $centerRank = min($sortRank, $maxCh);
        } elseif ($charName !== null && $charName !== '') {
            $rows = self::rows(
                "SELECT ch.name, ROW_NUMBER() OVER (ORDER BY {$valueExpr} DESC, ch.id ASC) as rnk
                 FROM tournament_snapshots ts
                 JOIN `character` ch ON ch.id = ts.character_id
                 WHERE ts.tournament_id = ?",
                [$tid]
            );
            $found = array_filter($rows, fn($r) => stripos($r->name, $charName) !== false);
            $centerRank = $found ? (int)array_values($found)[0]->rnk : 1;
        } else {
            $row = self::row(
                "SELECT rnk FROM (
                    SELECT ch.id, ROW_NUMBER() OVER (ORDER BY {$valueExpr} DESC, ch.id ASC) as rnk
                    FROM tournament_snapshots ts
                    JOIN `character` ch ON ch.id = ts.character_id
                    WHERE ts.tournament_id = ?
                ) sub WHERE sub.id = ?",
                [$tid, $charId]
            );
            $centerRank = $row ? (int)$row->rnk : 1;
        }

        $offset = max(0, $centerRank - 25);
        $now = GameTime::now();

        $lb = self::rows(
            "SELECT
                ROW_NUMBER() OVER (ORDER BY {$valueExpr} DESC, ch.id ASC) as `rank`,
                ch.id, ch.name, ch.guild_id, ch.gender, ch.level, ch.honor,
                {$valueExpr} as `value`,
                IF(ch.ts_last_action IS NOT NULL AND ({$now} - ch.ts_last_action) < 300, 1, 2) as online_status,
                ch.league_group_id,
                COALESCE(g.name, '') as guild_name,
                COALESCE(g.emblem_background_shape, '') as emblem_background_shape,
                COALESCE(g.emblem_background_color, '') as emblem_background_color,
                COALESCE(g.emblem_background_border_color, '') as emblem_background_border_color,
                COALESCE(g.emblem_icon_shape, '') as emblem_icon_shape,
                COALESCE(g.emblem_icon_color, '') as emblem_icon_color,
                COALESCE(g.emblem_icon_size, '') as emblem_icon_size
             FROM tournament_snapshots ts
             JOIN `character` ch ON ch.id = ts.character_id
             LEFT JOIN guild g ON g.id = ch.guild_id
             WHERE ts.tournament_id = ?
             ORDER BY {$valueExpr} DESC, ch.id ASC
             LIMIT 50 OFFSET {$offset}",
            [$tid]
        );

        $characters = array_map(fn($r) => array_map(
            fn($v) => is_numeric($v) ? (int)$v : (string)$v,
            (array)$r
        ), $lb);

        return [
            'centered_rank'            => $centerRank,
            'leaderboard_characters'   => $characters,
            'max_characters'           => $maxCh,
            ...self::timestamps(),
        ];
    }

    /** CLI: apenas processa ciclos vencidos, sem encerrar antecipadamente. */
    public static function end(): string
    {
        self::synchronize();
        return 'Calendário sincronizado; apenas torneios vencidos foram encerrados.';
    }

    /** Chamado exclusivamente dentro do lock/transação do calendário. */
    private static function finish(object $t): void
    {
        $tid = (int)$t->id;

        Store::exec("UPDATE tournaments SET status = ? WHERE id = ?", [self::STATUS_PROCESSING, $tid]);

        $tsStart = (int)$t->ts_start;
        $tsEnd = (int)$t->ts_end;

        // Rankings por tipo
        $rankings = [];
        foreach ([(int)$t->type] as $type) {
            if ($type === self::TYPE_PVP) {
                $valueExpr = "(SELECT COUNT(*) FROM duel d WHERE (d.character_a_id = ch.id) AND d.ts_creation >= {$tsStart} AND d.ts_creation < {$tsEnd})";
            } elseif ($type === self::TYPE_STATS) {
                $valueExpr = "(ch.stat_base_stamina + ch.stat_base_strength + ch.stat_base_critical_rating + ch.stat_base_dodge_rating) - CAST(ts.stat_base_start AS SIGNED)";
            } else {
                $valueExpr = "CAST(ch.xp AS SIGNED) - CAST(ts.xp_start AS SIGNED)";
            }

            $safeExpr = $type === self::TYPE_PVP ? $valueExpr : "GREATEST(0, {$valueExpr})";
            $rows = self::rows(
                "SELECT
                    ROW_NUMBER() OVER (ORDER BY {$safeExpr} DESC, ch.id ASC) as rnk,
                    ts.character_id, ts.guild_id,
                    {$safeExpr} as delta
                 FROM tournament_snapshots ts
                 JOIN `character` ch ON ch.id = ts.character_id
                 WHERE ts.tournament_id = ?",
                [$tid]
            );

            foreach ($rows as $row) {
                $cid = (int)$row->character_id;
                $rankings[$cid] ??= ['guild_id' => (int)$row->guild_id];
                $rankings[$cid][$type] = (int)$row->rnk;
                $rankings[$cid]["val_{$type}"] = max(0, (int)$row->delta);
            }
        }

        // Distribui recompensas
        $count = 0;
        foreach ($rankings as $charId => $data) {
            $rewardsByType = [];

            // XP (key 1): donuts + stat_points
            $xpRank  = $data[self::TYPE_XP] ?? 0;
            $xpValue = $data['val_' . self::TYPE_XP] ?? 0;
            if ((int)$t->type === self::TYPE_XP) $rewardsByType[1] = [
                'rank'        => $xpRank,
                'value'       => $xpValue,
                'donuts'      => self::amountForRank(self::REWARDS_XP_DONUTS, $xpRank),
                'stat_points' => self::amountForRank(self::REWARDS_XP_STAT_POINTS, $xpRank),
            ];

            // Stats (key 2): stat_points
            $statsRank  = $data[self::TYPE_STATS] ?? 0;
            $statsValue = $data['val_' . self::TYPE_STATS] ?? 0;
            if ((int)$t->type === self::TYPE_STATS) $rewardsByType[2] = [
                'rank'        => $statsRank,
                'value'       => $statsValue,
                'stat_points' => self::amountForRank(self::REWARDS_STATS, $statsRank),
            ];

            // PvP (key 4): percentagem do XP do nível actual
            $pvpRank  = $data[self::TYPE_PVP] ?? 0;
            $pvpValue = $data['val_' . self::TYPE_PVP] ?? 0;
            if ((int)$t->type === self::TYPE_PVP) $rewardsByType[4] = [
                'rank'           => $pvpRank,
                'value'          => $pvpValue,
                'xp_percentage'  => self::amountForRank(self::REWARDS_PVP_XP_PCT, $pvpRank),
            ];

            Store::exec(
                "INSERT INTO tournament_rewards (tournament_id, character_id, week, rewards, claimed)
                 VALUES (?, ?, ?, ?, 0)",
                [$tid, $charId, (int)$t->week, json_encode($rewardsByType)]
            );
            Store::exec(
                "UPDATE `character` SET pending_tournament_rewards = pending_tournament_rewards + 1 WHERE id = ?",
                [$charId]
            );
            $count++;
        }

        Store::exec("UPDATE tournaments SET status = ? WHERE id = ?", [self::STATUS_FINISHED, $tid]);

    }

    /** Serializa leitura/resgate no mesmo PDO do personagem, incluindo transações externas. */
    public static function rewardTransaction(int $cid, callable $fn): mixed
    {
        $pdo = \HeroZero\Db::pdo();
        $nested = $pdo->inTransaction();
        static $serial = 0;
        $save = 'tournament_reward_' . ++$serial;
        if ($nested) $pdo->exec('SAVEPOINT ' . $save); else $pdo->beginTransaction();
        try {
            if ($cid > 0) Store::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid]);
            $result = $fn();
            if ($nested) $pdo->exec('RELEASE SAVEPOINT ' . $save); else $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($nested) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $save);
                $pdo->exec('RELEASE SAVEPOINT ' . $save);
            } elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Pontos oficiais das posições XP/atributos, associados à temporada vigente no fecho. */
    public static function seasonReward(array $rewards, array $progress, int $end): array
    {
        $amount = 0;
        $identifier = (string)($progress['identifier'] ?? '');
        if ((int)($progress['status'] ?? 0) === 2 && $end >= (int)($progress['ts_start'] ?? 0)
            && $end < (int)($progress['ts_end'] ?? 0) && GameTime::now() < (int)$progress['ts_end']) {
            $locations = Herobook::constants()['season_locations'] ?? [];
            foreach ([1 => [6, 7, 8], 2 => [18, 19, 20]] as $type => $ids) {
                $rank = (int)($rewards[$type]['rank'] ?? 0);
                if ($rank <= 0 || $rank > 100) continue;
                $location = $ids[$rank <= 3 ? 0 : ($rank <= 10 ? 1 : 2)];
                $amount += max(0, (int)($locations[$location][$identifier] ?? 0));
            }
        }
        return ['amount' => $amount, 'identifier' => $identifier,
            'progress_id' => (int)($progress['id'] ?? 0), 'ts_start' => (int)($progress['ts_start'] ?? 0)];
    }

    /** Campos locais de pagamento + contrato visual do cliente. XP fica fixo na primeira leitura. */
    public static function displayRewards(array $rewards, int $level): array
    {
        foreach ([1, 2, 4] as $key) {
            if (!isset($rewards[$key])) continue;
            $entry = &$rewards[$key];
            if ($key === 1) {
                $entry['reward'] = ['type' => 1, 'amount' => max(0, (int)($entry['donuts'] ?? 0)),
                    'type_2' => 3, 'amount_2' => max(0, (int)($entry['stat_points'] ?? 0))];
            } elseif ($key === 2) {
                $entry['reward'] = ['type' => 3, 'amount' => max(0, (int)($entry['stat_points'] ?? 0)),
                    'type_2' => 0, 'amount_2' => 0];
            } else {
                if (!isset($entry['xp_amount'])) {
                    $entry['xp_amount'] = (int)((Character::xpForLevel($level + 1) - Character::xpForLevel($level))
                        * max(0, (int)($entry['xp_percentage'] ?? 0)) / 100);
                }
                $entry['reward'] = ['type' => 4, 'amount' => max(0, (int)$entry['xp_amount']),
                    'type_2' => 0, 'amount_2' => 0];
            }
            unset($entry);
        }
        return $rewards;
    }

    /**
     * Chave do payload `tournament_rewards.rewards` na numeracao DO CLIENTE:
     * 1 = Experiencia, 2 = Pontos de Habilidade, 4 = PvP/Honra (o dialogo tD
     * so desenha 1, 2 e 4 — o tipo interno/pedido de ranking do PvP e 3).
     */
    public static function rewardKey(int $type): int
    {
        return $type === self::TYPE_PVP ? 4 : $type;
    }

    public static function amountForRank(array $tiers, int $rank): int
    {
        if ($rank <= 0) {
            return 0;
        }
        foreach ($tiers as $tier) {
            if ($rank >= $tier['rank_start'] && $rank <= $tier['rank_end']) {
                return $tier['amount'];
            }
        }
        return 0;
    }

    private static function snapshotAll(int $tid): void
    {
        $chars = self::rows(
            'SELECT id, guild_id, xp, honor,
                    (stat_base_stamina + stat_base_strength + stat_base_critical_rating + stat_base_dodge_rating) as stat_base
             FROM `character`'
        );

        $guildHonors = [];
        $rows = [];
        foreach ($chars as $ch) {
            $gid = (int)$ch->guild_id;
            if ($gid > 0 && !isset($guildHonors[$gid])) {
                $gh = self::row("SELECT honor FROM guild WHERE id = ?", [$gid]);
                $guildHonors[$gid] = $gh ? (int)$gh->honor : 0;
            }
            $rows[] = [
                $tid,
                (int)$ch->id,
                $gid,
                (int)$ch->xp,
                (int)$ch->honor,
                $gid > 0 ? ($guildHonors[$gid] ?? 0) : 0,
                (int)($ch->stat_base ?? 0),
            ];
        }

        foreach ($rows as $r) {
            Store::exec(
                "INSERT INTO tournament_snapshots (tournament_id, character_id, guild_id, xp_start, honor_start, guild_honor_start, stat_base_start)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                $r
            );
        }
    }
}
