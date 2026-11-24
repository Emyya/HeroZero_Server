<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Recompensas de duelo — porte 1:1 das formulas do cliente
 * (`com.playata.application.data.GameUtil`, no HeroZero.min.js):
 *
 *   Q.duelHonorWinReward(a,b,c,e)   -> honorWin()
 *   Q.duelHonorLostReward(a,b)      -> honorLost()
 *   Q.duelGameCurrencyWinReward(..) -> coinsWin()
 *   Q.gameCurrencyPerTime(a)        -> gameCurrencyPerTime()
 *
 * O tooltip da lista de duelos (`$c.getDuelResultTooltip(opp.level, opp.honor,
 * opp.totalStats)`) usa exatamente estas funcoes, com esta ordem de argumentos:
 *   ganho  = duelHonorWinReward(MINHA honra, honra do OPONENTE)
 *   perda  = duelHonorLostReward(honra do OPONENTE, MINHA honra)   <- invertido!
 *   moedas = duelGameCurrencyWinReward(NIVEL do oponente)
 * Manter esta ordem: trocar os argumentos muda o resultado.
 *
 * As constantes vivem em `T.current` no cliente, alimentado pelo asset
 * `data/constants_json.data` do CDN oficial (nao temos copia local, por isso
 * estao replicadas aqui). Se um valor for corrigido, o tooltip do cliente e a
 * recompensa gravada pelo servidor deixam de bater certo — confirmar sempre
 * contra o cliente antes de mexer.
 */
final class DuelRewards
{
    /**
     * Constantes de balanceamento PVP — valores OFICIAIS, extraidos de
     *   https://hz-static-2.akamaized.net/assets/data/constants_json.data
     * (zlib -> JSON, 620 chaves). Repare no caminho: `assets/data/...`, NAO
     * `assets/html5/data/...` (esse devolve 404). E a mesma fonte de onde saiu a
     * tabela de niveis em Character::xpTable().
     */
    private const PVP_HONOR_WIN_EXP_BETTER = 3;
    private const PVP_HONOR_WIN_EXP_WORSE  = 3;
    private const PVP_HONOR_LOSE_AMOUNT    = 0.75;
    private const PVP_HONOR_LOSE_MAX       = 0.05;
    private const PVP_WAITING_TIME         = 600;
    private const PVP_EFFECTIVENESS_WON    = 0.3;

    private const COINS_PER_TIME_BASE        = 0.02;
    private const COINS_PER_TIME_SCALE       = 0.01;
    private const COINS_PER_TIME_LEVEL_SCALE = 0.35;
    private const COINS_PER_TIME_LEVEL_EXP   = 1.55;

    /**
     * Honra ganha ao VENCER. Espelha:
     *   Math.round((100 - 100*(a>b ? 1-Math.pow(b/a,d) : -1*(1-Math.pow(a/b,m)))) * c * e)
     * Bater no mais fraco tende a 0; bater no mais forte tende a 200.
     */
    public static function honorWin(int $ownHonor, int $opponentHonor, float $mult1 = 1.0, float $mult2 = 1.0): int
    {
        if ($ownHonor <= 0 && $opponentHonor <= 0) {
            return 0;
        }
        if ($ownHonor > $opponentHonor) {
            // Oponente mais fraco: quanto maior a diferenca, menos honra.
            $factor = 1.0 - pow($opponentHonor / $ownHonor, self::PVP_HONOR_WIN_EXP_WORSE);
        } else {
            // Oponente mais forte (ou empate): factor negativo -> premio acima de 100.
            $factor = -1.0 * (1.0 - pow($ownHonor / max(1, $opponentHonor), self::PVP_HONOR_WIN_EXP_BETTER));
        }
        return (int)round((100.0 - 100.0 * $factor) * $mult1 * $mult2);
    }

    /**
     * Honra perdida ao PERDER (valor negativo). Espelha:
     *   a = duelHonorWinReward(a,b); return -1*Math.round(c*a < e*b ? c*a : e*b)
     * NOTA: `$opponentHonor` e o PRIMEIRO argumento (o do adversario), tal como
     * no cliente — a perda e proporcional ao que o adversario ganharia, com um
     * tecto de PVP_HONOR_LOSE_MAX da minha propria honra.
     */
    public static function honorLost(int $opponentHonor, int $ownHonor): int
    {
        if ($opponentHonor === 0) {
            return 0;
        }
        $win = self::honorWin($opponentHonor, $ownHonor);
        $byAmount = self::PVP_HONOR_LOSE_AMOUNT * $win;
        $byMax    = self::PVP_HONOR_LOSE_MAX * $ownHonor;
        return -1 * (int)round(min($byAmount, $byMax));
    }

    /** Moedas ganhas ao vencer, em funcao do NIVEL do oponente. */
    public static function coinsWin(int $opponentLevel, float $mult1 = 1.0, float $mult2 = 1.0): int
    {
        $value = self::PVP_WAITING_TIME
            * self::gameCurrencyPerTime($opponentLevel)
            * self::PVP_EFFECTIVENESS_WON
            * $mult1 * $mult2;
        return (int)round($value);
    }

    /** Moedas por segundo para um nivel (base + scale * (level_scale*level)^exp). */
    public static function gameCurrencyPerTime(int $level): float
    {
        $value = self::COINS_PER_TIME_BASE
            + self::COINS_PER_TIME_SCALE * pow(self::COINS_PER_TIME_LEVEL_SCALE * $level, self::COINS_PER_TIME_LEVEL_EXP);
        return round($value, 3);
    }

    /**
     * Bloco de recompensas dos dois lados de um duelo.
     * `$aWins` vem da simulacao (HeroZero\Fight) — nao assumir o vencedor.
     * Estrutura do `DuelBattle::start()` oficial: o VENCEDOR leva moedas (pelo
     * nivel do vencido) + honra ganha; o PERDEDOR leva 0 moedas e a penalizacao.
     *
     * `$mult1`/`$mult2` = multiplicadores do VENCEDOR (booster de duelo da
     * guilda dele e artefacto de liga). O tooltip do cliente
     * (`$c.getDuelResultTooltip`) promete EXATAMENTE estes multiplicadores na
     * honra E nas moedas:
     *     d = 1 + guild.get_activeDuelBoosterAmount()/100
     *     L = 1 + guild.get_activeDuelLeagueArtifactAmount()/100
     * Nao aplicar ao PERDEDOR: a perda (duelHonorLostReward) nunca leva mult no
     * tooltip. Sem isto, quem tem booster de duelo via no tooltip um valor X e
     * recebia outro (menor).
     *
     * @return array{a: array{coins:int, honor:int}, b: array{coins:int, honor:int}}
     */
    public static function forDuel(int $honorA, int $levelA, int $honorB, int $levelB, bool $aWins = true, float $mult1 = 1.0, float $mult2 = 1.0): array
    {
        if ($aWins) {
            return [
                'a' => ['coins' => self::coinsWin($levelB, $mult1, $mult2), 'honor' => self::honorWin($honorA, $honorB, $mult1, $mult2)],
                'b' => ['coins' => 0,                                       'honor' => self::honorLost($honorA, $honorB)],
            ];
        }
        return [
            'a' => ['coins' => 0,                                       'honor' => self::honorLost($honorB, $honorA)],
            'b' => ['coins' => self::coinsWin($levelA, $mult1, $mult2), 'honor' => self::honorWin($honorB, $honorA, $mult1, $mult2)],
        ];
    }
}
