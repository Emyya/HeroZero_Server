<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Relógio do servidor de jogo.
 *
 * now() = epoch de nascimento + uptime acumulado + offset de debug.
 * O tempo só avança enquanto o servidor está ligado, simulando um
 * servidor oficial que nunca desliga.
 */
final class GameTime
{
    private static ?int $offset = null;
    private static ?int $cachedUptime = null;
    private static ?int $cachedAt = null;
    private static ?int $epoch = null;

    private const OFFSET_FILE = __DIR__ . '/../../storage/game_time_offset';
    private const RUN_DIR = __DIR__ . '/../../../.run';

    /**
     * Timestamp do servidor de jogo.
     * epoch (1.ª execução) + uptime + offset de debug.
     */
    public static function now(): int
    {
        return self::epoch() + self::uptime() + self::offset();
    }

    /** Timestamp real de quando o servidor arrancou pela primeira vez. */
    public static function epoch(): int
    {
        if (self::$epoch === null) {
            $file = self::RUN_DIR . '/epoch';
            self::$epoch = file_exists($file) ? (int)trim((string)file_get_contents($file)) : time();
        }
        return self::$epoch;
    }

    /** Segundos totais com o servidor ligado (sessões anteriores + atual). */
    public static function uptime(): int
    {
        if (self::$cachedUptime !== null && self::$cachedAt === time()) {
            return self::$cachedUptime;
        }

        $runDir = self::RUN_DIR;
        $accumFile = $runDir . '/uptime_accumulated';
        $state = json_decode((string)@file_get_contents($runDir.'/game_clock.json'), true);
        // Apenas checkpoints produzidos enquanto o container do jogo corre.
        // Sem extrapolação: desligado, mantém-se exatamente no último checkpoint.
        self::$cachedUptime = isset($state['uptime_ns'])
            ? intdiv(max(0, (int)$state['uptime_ns']), 1000000000)
            : max(0, (int)@file_get_contents($accumFile));
        self::$cachedAt = time();
        return self::$cachedUptime;
    }

    /** Dia do servidor (1-based, avança a cada 24h de uptime). */
    public static function gameDay(): int
    {
        return (int)floor(self::uptime() / 86400) + 1;
    }

    // ── Offset de debug ────────────────────────────────────────────────────

    public static function offset(): int
    {
        if (self::$offset === null) {
            $file = self::OFFSET_FILE;
            self::$offset = file_exists($file) ? (int)trim((string)file_get_contents($file)) : 0;
        }
        return self::$offset;
    }

    public static function setOffset(int $seconds): void
    {
        self::$offset = $seconds;
        file_put_contents(self::OFFSET_FILE, (string)$seconds);
    }

    public static function addDays(int $days): void
    {
        self::setOffset(self::offset() + $days * 86400);
    }

    public static function addHours(int $hours): void
    {
        self::setOffset(self::offset() + $hours * 3600);
    }

    public static function reset(): void
    {
        self::setOffset(0);
    }
}
