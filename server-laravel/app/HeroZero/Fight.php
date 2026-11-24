<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Simulacao de combate — porte de `Cls\Fight` do servidor oficial antigo
 * (class/Fight.php). O vencedor sai DAQUI, dos atributos reais dos dois lados.
 *
 * Laco (Fight::fight): resolve a INICIATIVA (skill "Me First" do sidekick
 * desloca 50/50) e alterna golpes ate um dos lados ficar sem hitpoints. Cada
 * golpe (Fight::hit):
 *   - esquiva:  random() < chance_dodge_do_defensor        -> r=1, sem dano
 *   - dano:     random(0.8, 1.05) * damage_normal_do_atacante
 *   - critico:  random() < chance_critical_do_atacante     -> r=3, dano * 2.5
 * `damage_normal` = forca + dano_de_arma;  `hitpoints` = stamina * 10.
 *
 * SKILLS DE SIDEKICK (docs/sidekicks/extract/03 + descricoes i18n oficiais):
 * o perfil de batalha traz `sidekick` {level, stage1..3_skill_id}; cada skill
 * desbloqueada (nivel >= 10*stage) pode disparar. Rounds marcam os efeitos em
 * `ae`/`de` (ids sidekick_battle_effect_id da skill do ATACANTE/ATACADO) e os
 * danos extra vao em `dt` (veneno) e `bf` (recuo) — o cliente so renderiza.
 *
 * Nao porta misseis de guilda (ainda nao existem aqui): skills de missile
 * e de trofeu (stage2_9) continuam fora deste motor. Arremesso pessoal
 * usa m=1..5, dano dos pacotes e redução de dano (battle_effect 14).
 */
final class Fight
{
    public const HIT_DODGED   = 1;
    public const HIT_NORMAL   = 2;
    public const HIT_CRITICAL = 3;

    /** constants.battle_critical_factor */
    private const CRITICAL_FACTOR = 2.5;
    /** constants.battle_hp_scale */
    public const HP_SCALE = 10;

    /** constants.battle_critical_probability_* */
    private const CRIT_MIN = 0.01, CRIT_BASE = 0.02, CRIT_MAX = 1.0, CRIT_EXP_LOW = 20.0, CRIT_EXP_HIGH = 1.225;
    /** constants.battle_dodge_probability_* */
    private const DODGE_MIN = 0.01, DODGE_BASE = 0.1, DODGE_MAX = 0.6, DODGE_EXP_LOW = 5.0, DODGE_EXP_HIGH = 1.65;

    /** Trava de seguranca: dois lados sem dano nao podem prender o servidor. */
    private const MAX_ROUNDS = 400;

    /** Reacoes em cadeia (contra-ataques/golpes duplos) no maximo desta profundidade. */
    private const MAX_CHAIN = 2;

    /**
     * Comportamento de combate de cada skill de sidekick (identifier -> tipo).
     * Usado por todos os dispatches em hit() e pelos debuffs passivos em entity().
     * Bónus de arremesso (efeito 104) calculados em Missiles::snapshot;
     * redução (efeito 14) tratada diretamente em hit(). Melões/troféus omitidos.
     *
     * Comportamentos cujo prefixo e o mesmo (ex: stun_attack em stage1 e stage3)
     * podem coexistir no mesmo combatente — sao verificados em loop independente.
     */
    private const SKILL_BEHAVIOR = [
        // Debuffs passivos (entity(), nao hit())
        'stage2_6'   => 'dodge_debuff', 'stage2_8'   => 'dodge_debuff',
        'stage2_11'  => 'dodge_debuff', 'stage2_12'  => 'dodge_debuff',
        'stage2_13'  => 'dodge_debuff', 'stage2_15'  => 'dodge_debuff',
        'stage2_27'  => 'dodge_debuff', 'stage2_33'  => 'dodge_debuff',
        'stage2_7'   => 'crit_debuff',  'stage2_10'  => 'crit_debuff',

        // Iniciativa: fight(), nao hit()
        'stage1_3'   => 'initiative',

        // Stun ao acertar (cada skill proca independente por golpe)
        'stage1_1'   => 'stun_attack', 'stage1_8'   => 'stun_attack',
        'stage1_9'   => 'stun_attack', 'stage1_14'  => 'stun_attack',
        'stage1_21'  => 'stun_attack', 'stage1_23'  => 'stun_attack',
        'stage1_24'  => 'stun_attack', 'stage1_28'  => 'stun_attack',
        'stage1_29'  => 'stun_attack', 'stage1_33'  => 'stun_attack',
        'stage1_34'  => 'stun_attack', 'stage1_41'  => 'stun_attack',
        'stage1_42'  => 'stun_attack', 'stage1_47'  => 'stun_attack',
        'stage1_49'  => 'stun_attack', 'stage1_53'  => 'stun_attack',
        'stage1_56'  => 'stun_attack', 'stage1_59'  => 'stun_attack',
        'stage1_63'  => 'stun_attack', 'stage1_65'  => 'stun_attack',
        'stage1_70'  => 'stun_attack', 'stage1_78'  => 'stun_attack',
        'stage1_79'  => 'stun_attack', 'stage1_89'  => 'stun_attack',
        'stage3_46'  => 'stun_attack', 'stage3_49'  => 'stun_attack',
        'stage3_52'  => 'stun_attack',

        // Stun ao ser atingido (defensor atordoa o atacante, reativo)
        'stage1_43'  => 'stun_hit', 'stage1_44'  => 'stun_hit',
        'stage1_45'  => 'stun_hit', 'stage1_50'  => 'stun_hit',
        'stage1_58'  => 'stun_hit', 'stage1_71'  => 'stun_hit',
        'stage1_81'  => 'stun_hit', 'stage1_90'  => 'stun_hit',
        'stage1_98'  => 'stun_hit', 'stage3_24'  => 'stun_hit',
        'stage3_25'  => 'stun_hit', 'stage3_29'  => 'stun_hit',
        'stage3_31'  => 'stun_hit', 'stage3_32'  => 'stun_hit',
        'stage3_33'  => 'stun_hit', 'stage3_47'  => 'stun_hit',
        'stage3_50'  => 'stun_hit', 'stage3_51'  => 'stun_hit',

        // Stun ao fazer critico
        'stage3_45'  => 'stun_crit', 'stage3_16'  => 'stun_crit',
        'stage3_53'  => 'stun_crit',

        // Veneno ao acertar (DoT; so um ativo de cada vez — primeiro a procar ganha)
        'stage1_5'   => 'dot', 'stage1_6'   => 'dot',
        'stage1_15'  => 'dot', 'stage1_16'  => 'dot',
        'stage1_19'  => 'dot', 'stage1_25'  => 'dot',
        'stage1_26'  => 'dot', 'stage1_27'  => 'dot',
        'stage1_35'  => 'dot', 'stage1_38'  => 'dot',
        'stage1_39'  => 'dot', 'stage1_57'  => 'dot',
        'stage1_69'  => 'dot', 'stage1_72'  => 'dot',
        'stage1_74'  => 'dot', 'stage1_77'  => 'dot',
        'stage1_83'  => 'dot', 'stage1_85'  => 'dot',
        'stage1_87'  => 'dot', 'stage1_99'  => 'dot',
        'stage1_103' => 'dot', 'stage1_106' => 'dot',

        // DoT apos esquiva propria (tick = damage_normal/v2; afeta o atacante)
        'stage1_36'  => 'dot_dodge', 'stage1_51'  => 'dot_dodge',
        'stage1_64'  => 'dot_dodge', 'stage1_95'  => 'dot_dodge',
        'stage1_105' => 'dot_dodge', 'stage1_111' => 'dot_dodge',

        // Dano extra no critico (cada skill proca independente no mesmo critico)
        'stage3_7'   => 'crit_extra', 'stage3_9'   => 'crit_extra',
        'stage1_12'  => 'crit_extra', 'stage1_18'  => 'crit_extra',
        'stage1_20'  => 'crit_extra', 'stage1_46'  => 'crit_extra',
        'stage3_28'  => 'crit_extra', 'stage1_54'  => 'crit_extra',
        'stage1_61'  => 'crit_extra', 'stage1_62'  => 'crit_extra',
        'stage1_66'  => 'crit_extra', 'stage1_67'  => 'crit_extra',
        'stage1_68'  => 'crit_extra', 'stage1_73'  => 'crit_extra',
        'stage1_75'  => 'crit_extra', 'stage1_76'  => 'crit_extra',
        'stage1_80'  => 'crit_extra', 'stage1_82'  => 'crit_extra',
        'stage1_84'  => 'crit_extra', 'stage1_86'  => 'crit_extra',
        'stage1_88'  => 'crit_extra', 'stage1_94'  => 'crit_extra',
        'stage1_96'  => 'crit_extra',
        'stage1_100' => 'crit_extra', 'stage1_101' => 'crit_extra',
        'stage1_104' => 'crit_extra', 'stage1_108' => 'crit_extra',
        'stage1_110' => 'crit_extra', 'stage3_48'  => 'crit_extra',

        // Reducao de dano de critico recebido (uma carga; stage1, logo so uma por combatente)
        'stage1_48'  => 'reduce_crit_dmg', 'stage1_91'  => 'reduce_crit_dmg',

        // Bonus de dano flat em qualquer golpe (primeiro a procar por golpe)
        'stage1_37'  => 'flat_dmg', 'stage1_55'  => 'flat_dmg',
        'stage1_93'  => 'flat_dmg', 'stage1_102' => 'flat_dmg',
        'stage1_107' => 'flat_dmg', 'stage1_109' => 'flat_dmg',
        'stage1_112' => 'flat_dmg',

        // Reducao de dano recebido (cada skill proca independente)
        'stage3_8'   => 'dmg_reduce', 'stage1_2'   => 'dmg_reduce',

        // Recuo para o atacante (dano refletido)
        'stage3_4'   => 'counter_crit_dmg',  // so apos critico recebido
        'stage3_6'   => 'counter_any_dmg',   // qualquer golpe

        // Contra-ataque apos critico recebido (cadeia; primeiro a procar dispara)
        'stage1_4'   => 'counter_crit', 'stage1_11'  => 'counter_crit',
        'stage1_17'  => 'counter_crit', 'stage1_22'  => 'counter_crit',
        'stage1_30'  => 'counter_crit', 'stage1_31'  => 'counter_crit',
        'stage1_32'  => 'counter_crit', 'stage1_40'  => 'counter_crit',
        'stage1_52'  => 'counter_crit', 'stage1_60'  => 'counter_crit',
        'stage1_92'  => 'counter_crit', 'stage1_97'  => 'counter_crit',
        'stage3_26'  => 'counter_crit',

        // Golpe duplo (cadeia; primeiro a procar dispara)
        'stage3_2'   => 'double_strike', 'stage3_10'  => 'double_strike',
        'stage3_12'  => 'double_strike', 'stage3_17'  => 'double_strike',
        'stage3_18'  => 'double_strike', 'stage3_19'  => 'double_strike',
        'stage3_27'  => 'double_strike', 'stage3_30'  => 'double_strike',
        'stage3_34'  => 'double_strike', 'stage3_35'  => 'double_strike',
        'stage3_36'  => 'double_strike', 'stage3_38'  => 'double_strike',
        'stage3_39'  => 'double_strike', 'stage3_40'  => 'double_strike',
        'stage3_42'  => 'double_strike', 'stage3_43'  => 'double_strike',
        'stage3_44'  => 'double_strike',

        // Segunda hipotese de esquiva
        'stage3_3'   => 'second_dodge', 'stage3_13'  => 'second_dodge',
        'stage3_20'  => 'second_dodge',

        // Sobreviver ao golpe fatal (1 HP; sem sorteio, uma vez por luta)
        'stage3_1'   => 'survive',

        // Sobreviver + bonus de dano ao proximo ataque (v3 = multiplicador)
        'stage3_11'  => 'survive_plus', 'stage3_41'  => 'survive_plus',

        // Atordoar adversario quando ELE esquiva do meu golpe
        'stage3_21'  => 'stun_if_dodge',

        // Golpe duplo apos esquivar (cadeia a partir do defensor)
        'stage3_22'  => 'double_dodge',
    ];

    private array $rounds = [];
    private int $winner = 0;
    private int $hpA = 0;
    private int $hpB = 0;
    private array $missileUsage = ['a' => [], 'b' => []];

    /**
     * @param array $profileA perfil de batalha (Character::battleProfile('a'))
     * @param array $profileB idem, com 'profile' => 'b'
     * @param bool  $guildFight modo luta de GUILDA: cada round identifica os
     *              lutadores PELO ID DE PERFIL (official Fight.php:101 —
     *              'a' => id do atacante, 'd' => id do defensor), nao por 'a'/'b'
     */
    public function __construct(private array $profileA, private array $profileB, private bool $guildFight = false)
    {
    }

    /**
     * Probabilidade de acerto critico. Porte de `Utils::getCriticalHitPercentage`.
     */
    public static function criticalChance(int $own, int $other): float
    {
        return self::probability(
            $own, $other,
            self::CRIT_MIN, self::CRIT_BASE, self::CRIT_MAX, self::CRIT_EXP_LOW, self::CRIT_EXP_HIGH
        );
    }

    /** Probabilidade de esquiva. Porte de `Utils::getDodgePercentage`. */
    public static function dodgeChance(int $own, int $other): float
    {
        return self::probability(
            $own, $other,
            self::DODGE_MIN, self::DODGE_BASE, self::DODGE_MAX, self::DODGE_EXP_LOW, self::DODGE_EXP_HIGH
        );
    }

    private static function probability(int $own, int $other, float $min, float $base, float $max, float $expLow, float $expHigh): float
    {
        $own = max(1, $own);
        $other = max(1, $other);
        $ratio = $own / $other;
        $p = $ratio <= 1
            ? pow($ratio, $expLow) * ($base - $min) + $min
            : (1 - pow(1 / $ratio, $expHigh)) * ($max - $base) + $base;
        return round($p, 3);
    }

    /** Corre o combate. Devolve 1 se ganhou o A, 2 se ganhou o B. */
    public function fight(): int
    {
        $a = $this->entity($this->profileA, 'a', $this->profileB);
        $b = $this->entity($this->profileB, 'b', $this->profileA);

        // INICIATIVA: stage1_3 "Me First" e variantes deslocam a probabilidade.
        $shiftA = 0.0;
        $shiftB = 0.0;
        foreach (($a->sidekick ?? []) as $rec) {
            if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) === 'initiative') {
                $shiftA = (float)($rec['value1'] ?? 0);
                break;
            }
        }
        foreach (($b->sidekick ?? []) as $rec) {
            if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) === 'initiative') {
                $shiftB = (float)($rec['value1'] ?? 0);
                break;
            }
        }
        $aFirst = self::randFloat() < (0.5 + ($shiftA - $shiftB) / 2);

        $guard = 0;
        do {
            [$first, $second] = $aFirst ? [$a, $b] : [$b, $a];
            if ($first->stun > 0) {
                $first->stun = 0;
            } elseif ($first->hitpoints > 0) {
                $this->hit($first, $second);
            }
            if ($second->stun > 0) {
                $second->stun = 0;
            } elseif ($second->hitpoints > 0) {
                $this->hit($second, $first);
            }
            $guard++;
        } while ($a->hitpoints > 0 && $b->hitpoints > 0 && $guard < self::MAX_ROUNDS);

        $this->hpA = (int)$a->hitpoints;
        $this->hpB = (int)$b->hitpoints;
        $this->winner = $a->hitpoints > 0 ? 1 : 2;
        if ($guard >= self::MAX_ROUNDS) {
            $this->winner = $a->hitpoints >= $b->hitpoints ? 1 : 2;
        }
        return $this->winner;
    }

    /** Estado de skills do sidekick deste perfil: [skill_id => rec] ou null. */
    private static function sidekickState(array $profile): ?array
    {
        $sidekick = $profile['sidekick'] ?? null;
        if (!is_array($sidekick)) {
            return null;
        }
        $level = (int)($sidekick['level'] ?? 0);
        if ($level < 10) {
            return null;
        }
        $state = [];
        foreach ([1, 2, 3] as $stage) {
            if ($level < 10 * $stage) continue;
            $skillId = (int)($sidekick['stage' . $stage . '_skill_id'] ?? 0);
            if ($skillId <= 0) continue;
            $rec = Sidekick::skill($skillId);
            if ($rec === null) continue;
            $state[$skillId] = [
                'identifier' => (string)$rec['identifier'],
                'effect'     => (int)($rec['sidekick_battle_effect_id'] ?? 0),
                'value1'     => (float)($rec['value1'] ?? 0),
                'value2'     => $rec['value2'] === null ? null : (float)$rec['value2'],
                'value3'     => $rec['value3'] === null ? null : (float)$rec['value3'],
                'charges'    => (int)($rec['charges'] ?? 0),
                'uses'       => 0,
            ];
        }
        return $state === [] ? null : $state;
    }

    private function entity(array $profile, string $side, array $other): object
    {
        $sidekick = self::sidekickState($profile);

        // Debuffs passivos do oponente: baixam critico/esquiva deste combatente.
        $otherSk = self::sidekickState($other);
        $critDebuff = 0.0;
        $dodgeDebuff = 0.0;
        foreach (($otherSk ?? []) as $rec) {
            // O catálogo HTML5 atribui estas skills a dano de arremesso.
            if (in_array($rec['effect'], Missiles::constants()['battle_effects'][104]['sidekick_battle_effect_ids'], true)) continue;
            $b = self::SKILL_BEHAVIOR[$rec['identifier']] ?? null;
            if ($b === 'crit_debuff')  $critDebuff  += $rec['value1'];
            if ($b === 'dodge_debuff') $dodgeDebuff += $rec['value1'];
        }

        return (object)[
            'profile'         => $this->guildFight ? (string)($profile['profile'] ?? $side) : $side,
            'hitpoints'       => max(1, (int)($profile['hitpoints'] ?? 1)),
            'damage_normal'   => max(1, (int)($profile['damage_normal'] ?? 1)),
            'chance_critical' => max(self::CRIT_MIN, self::criticalChance(
                (int)($profile['criticalrating'] ?? 0), (int)($other['criticalrating'] ?? 0)
            ) - $critDebuff),
            'chance_dodge'    => max(self::DODGE_MIN, self::dodgeChance(
                (int)($profile['dodgerating'] ?? 0), (int)($other['dodgerating'] ?? 0)
            ) - $dodgeDebuff),
            'sidekick'        => $sidekick,
            'side'            => $side,
            'missiles'        => $this->guildFight ? [] : ($profile['missiles'] ?? []),
            'stun'            => 0,    // rodadas de atordoamento pendentes
            'dot'             => null, // veneno pendente ['tick','left','effect']
            'next_bonus'      => 0.0,  // multiplicador extra no proximo ataque (survive_plus)
        ];
    }

    private function hit(object $attacker, object $defender, int $chain = 0): void
    {
        $round = $this->guildFight
            ? ['a' => (string)$attacker->profile, 'd' => (string)$defender->profile]
            : ['a' => $attacker->profile];

        $ae = [];
        $de = [];
        $missileDamage = 0;
        foreach ($attacker->missiles as &$package) {
            if ($package['charges'] <= 0) continue;
            $package['charges']--;
            $round['m'] = (int)$package['index'];
            $missileDamage = (int)$package['damage'];
            $id = (int)$package['id'];
            $this->missileUsage[$attacker->side][$id] = ($this->missileUsage[$attacker->side][$id] ?? 0) + 1;
            break;
        }
        unset($package);

        // ------------------------------------------------------------------ ESQUIVA
        $dodged = round(self::randFloat(), 3) < $defender->chance_dodge;

        // Segunda hipotese de esquiva (stage3_3 e variantes second_dodge)
        if (!$dodged) {
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'second_dodge') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $de[] = $rec['effect'];
                $dodged = true;
                break;
            }
        }

        if ($dodged) {
            // stun_if_dodge: atacante atordoa defensor mesmo ele se esquivando
            foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'stun_if_dodge') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $attacker->sidekick[$skillId]['uses']++;
                $defender->stun = 1;
                $ae[] = $rec['effect'];
                break;
            }

            $round['r'] = self::HIT_DODGED;
            if ($ae !== []) $round['ae'] = $ae;
            if ($de !== []) $round['de'] = $de;
            $this->rounds[] = $round;

            // dot_dodge: defensor que esquivou aplica DoT ao atacante
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'dot_dodge') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $dRounds = max(1, (int)($rec['value2'] ?? 3));
                $attacker->dot = [
                    'tick'   => max(1, (int)round($defender->damage_normal / $dRounds)),
                    'left'   => $dRounds,
                    'effect' => $rec['effect'],
                ];
                $this->markLast('de', (int)$rec['effect']);
                break;
            }

            // double_dodge: defensor golpeia depois de esquivar (cadeia)
            if ($defender->hitpoints > 0 && $attacker->hitpoints > 0 && $chain < self::MAX_CHAIN) {
                foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                    if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'double_dodge') continue;
                    if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                    if (self::randFloat() >= $rec['value1']) continue;
                    $defender->sidekick[$skillId]['uses']++;
                    $this->hit($defender, $attacker, $chain + 1);
                    $this->markLast('de', (int)$rec['effect']);
                    break;
                }
            }
            return;
        }

        // Redução de dano de arremesso: battle_effect 14 (chance e percentagem).
        if ($missileDamage > 0) {
            $effects = Missiles::constants()['battle_effects'][14]['sidekick_battle_effect_ids'];
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if (!in_array($rec['effect'], $effects, true)) continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $missileDamage = (int)round($missileDamage * (1.0 - (float)$rec['value2']));
                $de[] = $rec['effect'];
            }
        }

        // ------------------------------------------------------------------ DANO
        $round['r'] = self::HIT_NORMAL;
        $damage = self::randFloat(0.8, 1.05) * ($attacker->damage_normal + $missileDamage);

        // Bonus de survive_plus de rodada anterior
        if ($attacker->next_bonus > 0.0) {
            $damage *= 1.0 + $attacker->next_bonus;
            $attacker->next_bonus = 0.0;
        }

        $wasCritical = false;
        if (round(self::randFloat(), 3) < $attacker->chance_critical) {
            $damage = round($damage * self::CRITICAL_FACTOR);
            $round['r'] = self::HIT_CRITICAL;
            $wasCritical = true;

            // Dano extra no critico (stage3_7 e todas as variantes crit_extra;
            // cada skill proca independente — ex: stage3_7 + stage1_96 podem acumular)
            foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'crit_extra') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $attacker->sidekick[$skillId]['uses']++;
                $damage *= 1.0 + (float)($rec['value2'] ?? 0);
                $ae[] = $rec['effect'];
            }

            // Reducao de dano de critico recebido (stage1_48 / stage1_91; ch=1)
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'reduce_crit_dmg') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $damage *= 1.0 - (float)($rec['value2'] ?? 0);
                $de[] = $rec['effect'];
            }
        }

        // Bonus flat de dano em qualquer golpe (primeiro a procar ganha)
        foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
            if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'flat_dmg') continue;
            if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
            if (self::randFloat() >= $rec['value1']) continue;
            $attacker->sidekick[$skillId]['uses']++;
            $damage *= 1.0 + (float)($rec['value2'] ?? 0);
            $ae[] = $rec['effect'];
            break;
        }

        // Reducao de dano do defensor (stage3_8 e stage1_2 procam independente)
        foreach (($defender->sidekick ?? []) as $skillId => $rec) {
            if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'dmg_reduce') continue;
            if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
            if (self::randFloat() >= $rec['value1']) continue;
            $defender->sidekick[$skillId]['uses']++;
            $damage *= 1.0 - (float)($rec['value2'] ?? 0);
            $de[] = $rec['effect'];
        }

        $damage = max(1, (int)round($damage));
        $round['v'] = $damage;

        // Tick do DoT pendente (tica quando o envenenado e o defensor deste round)
        if ($defender->dot !== null) {
            $tick = min($defender->dot['tick'], max(0, $defender->hitpoints));
            if ($tick > 0) {
                $defender->hitpoints -= $tick;
                $round['dt'] = $tick;
                $ae[] = $defender->dot['effect'];
            }
            if (--$defender->dot['left'] <= 0) {
                $defender->dot = null;
            }
        }

        // Stun ao acertar (stage1_1 e todas as variantes; cada uma proca independente)
        foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
            if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'stun_attack') continue;
            if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
            if (self::randFloat() >= $rec['value1']) continue;
            $attacker->sidekick[$skillId]['uses']++;
            $defender->stun = 1;
            $ae[] = $rec['effect'];
        }

        // Stun ao fazer critico (stage3_45 e variantes)
        if ($wasCritical) {
            foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'stun_crit') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $attacker->sidekick[$skillId]['uses']++;
                $defender->stun = 1;
                $ae[] = $rec['effect'];
            }
        }

        // Veneno ao acertar (stage1_5 e variantes dot; so um DoT ativo de cada vez)
        // v3 < 1.0: percentagem do dano do golpe distribuida pelas rodadas.
        foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
            if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'dot') continue;
            if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
            if (self::randFloat() >= $rec['value1']) continue;
            $attacker->sidekick[$skillId]['uses']++;
            $dRounds = max(1, (int)($rec['value2'] ?? 4));
            $v3 = $rec['value3'] ?? null;
            $base = ($v3 !== null && $v3 > 0.0 && $v3 < 1.0) ? $damage * $v3 : $damage;
            $defender->dot = [
                'tick'   => max(1, (int)round($base / $dRounds)),
                'left'   => $dRounds,
                'effect' => $rec['effect'],
            ];
            $ae[] = $rec['effect'];
            break;
        }

        $defender->hitpoints -= $damage;

        // Sobreviver ao golpe fatal (survive ou survive_plus; sem sorteio)
        if ($defender->hitpoints <= 0) {
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                $b = self::SKILL_BEHAVIOR[$rec['identifier']] ?? null;
                if ($b !== 'survive' && $b !== 'survive_plus') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $defender->hitpoints = 1;
                if ($b === 'survive_plus') {
                    $defender->next_bonus = (float)($rec['value3'] ?? 0.5);
                }
                $de[] = $rec['effect'];
                break;
            }
        }

        // Recuo para o atacante (sobrevivendo apos o golpe)
        if ($defender->hitpoints > 0) {
            $backfire = 0.0;
            if ($wasCritical) {
                foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                    if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'counter_crit_dmg') continue;
                    if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                    if (self::randFloat() >= $rec['value1']) continue;
                    $defender->sidekick[$skillId]['uses']++;
                    $backfire += $damage * (float)($rec['value2'] ?? 0);
                    $de[] = $rec['effect'];
                }
            }
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'counter_any_dmg') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $backfire += $damage * (float)($rec['value2'] ?? 0);
                $de[] = $rec['effect'];
            }
            if ($backfire > 0) {
                $bf = max(1, (int)round($backfire));
                $round['bf'] = $bf;
                $attacker->hitpoints -= $bf;
            }
        }

        // Stun ao ser atingido (defensor atordoa o atacante; primeiro a procar por golpe)
        if ($defender->hitpoints > 0) {
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'stun_hit') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $attacker->stun = 1;
                $de[] = $rec['effect'];
                break;
            }
        }

        if ($ae !== []) $round['ae'] = $ae;
        if ($de !== []) $round['de'] = $de;
        $this->rounds[] = $round;

        // -------------------------------------------------------- REACOES EM CADEIA
        // Contra-ataque apos critico recebido (stage1_4 e variantes counter_crit)
        if ($wasCritical && $defender->hitpoints > 0 && $attacker->hitpoints > 0 && $chain < self::MAX_CHAIN) {
            foreach (($defender->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'counter_crit') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $defender->sidekick[$skillId]['uses']++;
                $this->hit($defender, $attacker, $chain + 1);
                $this->markLast('de', (int)$rec['effect']);
                break;
            }
        }

        // Golpe duplo (stage3_2 e variantes double_strike)
        if ($attacker->hitpoints > 0 && $defender->hitpoints > 0 && $chain < self::MAX_CHAIN) {
            foreach (($attacker->sidekick ?? []) as $skillId => $rec) {
                if ((self::SKILL_BEHAVIOR[$rec['identifier']] ?? null) !== 'double_strike') continue;
                if ($rec['charges'] >= 1 && $rec['uses'] >= $rec['charges']) continue;
                if (self::randFloat() >= $rec['value1']) continue;
                $attacker->sidekick[$skillId]['uses']++;
                $this->hit($attacker, $defender, $chain + 1);
                $this->markLast('ae', (int)$rec['effect']);
                break;
            }
        }
    }

    /** Adiciona um efeito ao ultimo round gravado (rounds de reacoes em cadeia). */
    private function markLast(string $key, int $effect): void
    {
        if ($this->rounds === [] || $effect <= 0) {
            return;
        }
        $i = count($this->rounds) - 1;
        $this->rounds[$i][$key][] = $effect;
    }

    /** @return array<int,array<string,mixed>> */
    public function missileUsage(string $side): array { return $this->missileUsage[$side] ?? []; }

    public function rounds(): array { return $this->rounds; }
    public function winner(): int   { return $this->winner; }
    public function hitpointsA(): int { return $this->hpA; }
    public function hitpointsB(): int { return $this->hpB; }

    private static function randFloat(float $min = 0.0, float $max = 1.0): float
    {
        return $min + ($max - $min) * (random_int(0, 1000000) / 1000000);
    }
}
