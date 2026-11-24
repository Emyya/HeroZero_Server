<?php
declare(strict_types=1);

namespace HeroZero;

/** Fórmula do cliente com distribuição de XP e geração balanceadas localmente. */
final class QuestRewards
{
    /** Dificuldade da luta (0 = missao de tempo). */
    public const DIFF_NONE   = 0;
    public const DIFF_EASY   = 1;
    public const DIFF_MEDIUM = 2;
    public const DIFF_HARD   = 3;

    /** Bónus de XP por dificuldade: balanceamento local. */
    private const FIGHT_SCALE_XP = [0 => 1.0, 1 => 1.0, 2 => 1.2, 3 => 1.3];

    /** Escala de moedas por dificuldade. */
    private const FIGHT_SCALE_COINS = [0 => 1.0, 1 => 1.0, 2 => 1.3, 3 => 1.5];

    /** Plano completa as vagas sem substituir missões existentes/ativas. */
    public static function poolPlan(array $existingTypes, int $count): array
    {
        $types = array_count_values($existingTypes);
        $missing = [];
        foreach ([1 => 2, 2 => 1] as $type => $target) {
            for ($i = (int)($types[$type] ?? 0); $i < $target; $i++) $missing[] = $type;
        }
        shuffle($missing);
        $fightFocused = random_int(1, 100) <= 65;
        $focusType = $fightFocused ? 2 : 1;
        $candidates = array_keys($missing, $focusType, true);
        $focusedSlot = $candidates ? $candidates[array_rand($candidates)] : -1;
        $plan = [];
        foreach (array_slice($missing, 0, max(0, $count)) as $i => $type) {
            $plan[] = ['type' => $type, 'focused' => $i === $focusedSlot];
        }
        return $plan;
    }

    /** 80% próximo da base, 15% inferior, 5% superior; média 0,98625. */
    public static function rollXpMultiplier(): float
    {
        $roll = random_int(1, 100);
        if ($roll <= 80) return self::randFloat(0.90, 1.10);
        if ($roll <= 95) return self::randFloat(0.80, 0.90);
        return self::randFloat(1.10, 1.25);
    }

    public static function missionXp(int $durationSeconds, int $guildStat, bool $focused, float $multiplier, int $difficulty = self::DIFF_NONE): int
    {
        $guildFactor = 1.0 + max(0, min(50, $guildStat)) * 0.02;
        return max(1, (int)round(max(60, $durationSeconds) * 1.5
            * ($focused ? 1.4 : 1.0) * $guildFactor * $multiplier
            * (self::FIGHT_SCALE_XP[$difficulty] ?? 1.0)));
    }

    /** Compatibilidade: energia base corresponde agora a minutos base. */
    public static function rollXpByGuildStat(int $guildStat, int $energy, bool $focused = false): int
    {
        return self::missionXp(max(1, $energy) * 60, $guildStat, $focused, self::rollXpMultiplier());
    }

    /** Energéticos aplicados uma vez na geração, sem alterar prémios. */
    public static function boostedCosts(int $durationSeconds, int $energy, int $personal, int $team): array
    {
        $personal = max(0, min(50, $personal));
        $team = max(0, min(20, $team));
        return [
            'energy' => max(1, (int)round($energy * (1 - $personal / 100))),
            'duration' => max(60, (int)round($durationSeconds * (1 - ($personal + $team) / 100))),
        ];
    }

    /** Referência histórica da fórmula de moedas, preservada neste balanceamento. */
    private const COINS_ENERGY_REF = 7.5;

    /** Variancia das moedas por missao: ±15% em torno do valor esperado. */
    private const COINS_VARIANCE = 0.15;

    /**
     * Moedas: linear ate nivel 300, cubica a partir dai (transicao suave).
     *
     *   base = 113 × lvl × max(1, (lvl/300)²)
     *     lvl ≤ 300 → 113 × lvl               (linear)
     *     lvl > 300 → 113 × lvl³ / 90 000     (cubica, continua em 300)
     *
     *   guildFact = 1 + stat × 0.031           (stat=1→×1.031 | stat=50→×2.571)
     *
     * A `base` e o valor esperado para uma missao MEDIA do pool. O valor final
     * escala com a energia da missao (uma missao que custa o dobro paga o dobro)
     * e leva ±15% de variancia, para que as 3 missoes do pool nunca sejam iguais:
     *
     *   moedas = base / 7.5 × energia × rand(0.85, 1.15) × escala_dificuldade
     *
     * Referencias (media por missao, stat=50/50):
     *   lvl 300 → ~87k | lvl 400 → ~207k | lvl 500 → ~403k
     */
    public static function rollCoinsByGuildStat(int $guildStat, int $energy, int $difficulty = self::DIFF_NONE, int $level = 1): int
    {
        $lvl = max(1, $level);
        $s   = max(0, min(50, $guildStat));
        $e   = max(1, $energy);

        $guildFact   = 1.0 + $s * 0.031428;
        $scaleFactor = max(1.0, ($lvl / 300.0) ** 2);
        $base        = 113.0 * $lvl * $scaleFactor;

        $perEnergy = ($base * $guildFact) / self::COINS_ENERGY_REF;
        $variance  = self::randFloat(1.0 - self::COINS_VARIANCE, 1.0 + self::COINS_VARIANCE);

        $coins = $perEnergy * $e * $variance * (self::FIGHT_SCALE_COINS[$difficulty] ?? 1.0);
        return max(1, (int)round($coins));
    }

    /** Duração de tempo em minutos, com mínimo local de um minuto. */
    public static function rollDurationMinutes(int $level): int
    {
        $lo = min(4, $level - 1);
        $hi = min($level + 5, 12);
        if ($lo > $hi) $lo = $hi;
        return random_int(max(1, $lo), max(1, $hi));
    }

    /** Energia base: um ponto por minuto base. */
    public static function rollEnergy(int $durMinutes): int
    {
        return max(1, $durMinutes);
    }

    /** Dificuldade: 50% facil / 30% media / 20% dificil. */
    public static function rollDifficulty(): int
    {
        $r = random_int(1, 100);
        if ($r <= 50) return self::DIFF_EASY;
        return $r <= 80 ? self::DIFF_MEDIUM : self::DIFF_HARD;
    }

    /** Faixas de energia final de combate, calibradas para energético pessoal 50%. */
    public static function fightEnergyBand(int $roll): array
    {
        if ($roll < 1 || $roll > 100) throw new \InvalidArgumentException('Sorteio deve estar entre 1 e 100');
        if ($roll <= 50) return [3, 6];
        if ($roll <= 80) return [1, 3];
        return [6, 9];
    }

    /** Energia base = minutos base: dobro do custo sorteado para pessoal 50%. */
    public static function rollFightDurationMinutes(): int
    {
        [$min, $max] = self::fightEnergyBand(random_int(1, 100));
        return 2 * random_int($min, $max);
    }

    /** Limites de XP por energia BASE (antes dos energéticos). */
    public static function xpRangeForStat(int $guildStat, bool $focused = false): array
    {
        $base = 90.0 * ($focused ? 1.4 : 1.0) * (1 + max(0, min(50, $guildStat)) * 0.02);
        return [$base * 0.8, $base * 1.25];
    }

    public static function expectedXpRange(int $guildStat, int $energy, bool $focused = false, int $difficulty = self::DIFF_NONE): array
    {
        return [self::missionXp(max(1, $energy) * 60, $guildStat, $focused, 0.8, $difficulty),
            self::missionXp(max(1, $energy) * 60, $guildStat, $focused, 1.25, $difficulty)];
    }

    /** [min, max] de moedas esperado (inclui ±15% de variancia e escala de dificuldade). */
    public static function expectedCoinsRange(int $guildStat, int $energy, int $difficulty, int $level): array
    {
        $lvl = max(1, $level);
        $s   = max(0, min(50, $guildStat));
        $e   = max(1, $energy);

        $guildFact   = 1.0 + $s * 0.031428;
        $scaleFactor = max(1.0, ($lvl / 300.0) ** 2);
        $base        = 113.0 * $lvl * $scaleFactor;
        $perEnergy   = ($base * $guildFact) / self::COINS_ENERGY_REF;
        $scale       = self::FIGHT_SCALE_COINS[$difficulty] ?? 1.0;

        return [
            (int)round($perEnergy * $e * (1.0 - self::COINS_VARIANCE) * $scale),
            (int)round($perEnergy * $e * (1.0 + self::COINS_VARIANCE) * $scale),
        ];
    }

    /**
     * Devolve lista de violacoes (string[]) — vazio = missao conforme.
     */
    public static function validateRewards(
        int $guildStatXp, int $guildStatCoins,
        int $energy, int $difficulty, int $level,
        bool $focused, int $xp, int $coins
    ): array {
        $errors = [];
        [$xpMin, $xpMax] = self::expectedXpRange($guildStatXp, $energy, $focused, $difficulty);
        if ($xp < $xpMin || $xp > $xpMax) {
            $errors[] = "xp=$xp fora do intervalo [$xpMin, $xpMax] (stat=$guildStatXp, energia=$energy, focused=" . ($focused ? 'true' : 'false') . ")";
        }
        [$cMin, $cMax] = self::expectedCoinsRange($guildStatCoins, $energy, $difficulty, $level);
        if ($coins < $cMin || $coins > $cMax) {
            $errors[] = "coins=$coins fora do intervalo [$cMin, $cMax] (stat=$guildStatCoins, energia=$energy, diff=$difficulty, lvl=$level)";
        }
        return $errors;
    }

    private static function randFloat(float $min, float $max): float
    {
        return $min + ($max - $min) * (random_int(0, 1000000) / 1000000);
    }
}
