# Fórmulas do Servidor HeroZero

---

## Missões

### Pool, duração e energia

Cada zona mantém **duas missões de tempo e uma de luta**. O conjunto completo
contém uma focada em XP: 65% na luta, 35% numa das duas de tempo.
Reposições parciais respeitam os tipos mantidos, incluindo a missão ativa.
Mudar de cidade preserva missões; concluir ou renovar gera o conjunto novo.

Tempo: 1–12 minutos base conforme nível. Combate: escolhe faixa de energia
de referência a pessoal 50% (50%: 3–6; 30%: 1–3; 20%: 6–9), depois um inteiro
uniforme dentro da faixa. Energia base e minutos base são o dobro (2–18).
**Energia base = minutos base**, para ambos os tipos (balanceamento local).
Removidos os fatores antigos ×1.5 e duplicação das missões normais de tempo.

---

### Dificuldade (Missões de Luta)

```
rand(1–100):
    1–50  → easy   (50%)
   51–80  → medium (30%)
   81–100 → hard   (20%)
```

---

### Recompensas — XP

```
xp = round(segundos_base × 1.5 × perfil × (1 + stat_xp_guilda × 0.02) × variação × dificuldade)
perfil = 1 (equilibrada) ou 1.4 (focada)
```

Variação sorteada uniformemente no intervalo escolhido:

| Probabilidade | Intervalo |
|---|---|
| 80% | 0.90–1.10 |
| 15% | 0.80–0.90 |
| 5% | 1.10–1.25 |

Média do multiplicador: 0.98625. Dificuldade no XP: tempo/fácil ×1, média ×1.2, difícil ×1.3.
Nas lutas, o sorteio mantém 50% fácil, 30% média e 20% difícil.
Este bónus é balanceamento local e aplica-se a equilibradas e focadas.
Expansão da equipa: 1/50 → +2%; 25/50 → +50%; 50/50 → +100%.
XP calculado com duração **base**, antes dos energéticos, e persistido na geração.
A fórmula usa fatores do cliente, com distribuição e geração locais; não é uma
reprodução confirmada do gerador oficial. Não há escala de XP pelo nível do herói.

---

### Recompensas — Moedas

O XP escala com a guilda; as moedas escalam com **nível + guilda**.

```
base       = 113 × nivel × max(1, (nivel / 300)²)
por_energia = base × fator_guilda / 7.5
moedas      = por_energia × energia × rand(0.85, 1.15) × escala_dificuldade
```

A `base` é o valor esperado de uma missão **média** do pool. O valor final escala
com a energia da missão (uma missão que custa o dobro paga o dobro) e leva ±15%
de variância, para que as 3 missões do pool nunca sejam idênticas.

O divisor **7.5** é a energia média do pool de 3 missões (1 focada + 2 normais a
50% luta / 50% tempo): missões de tempo têm energia média ~9.2, as de luta ~4.35.
Dividir por este valor faz com que a média das moedas por missão continue a ser
exactamente a `base` — a calibração dos níveis 1–500 mantém-se.

- **Níveis 1–300:** crescimento linear (`113 × nivel`)
- **Níveis 301+:** crescimento cúbico suave (`113 × nivel³ / 90 000`) — transição contínua em 300

**Fator de guilda — Moedas:**
```
fator_guilda = 1 + stat_quest_game_currency_reward_boost × 0.031
```

| stat guilda | Fator |
|:-----------:|:-----:|
| 0 | ×1.000 |
| 1 | ×1.031 |
| 25 | ×1.785 |
| 50 (máximo) | ×2.571 |

**Valores de referência — média por missão (`base × fator_guilda`):**

| Nível | stat 1/50 | stat 50/50 |
|:-----:|:---------:|:----------:|
| 50 | 5 825 | 14 526 |
| 100 | 11 650 | 29 052 |
| 200 | 23 301 | 58 104 |
| 300 | 34 951 | 87 157 |
| 400 | 82 847 | 206 595 |
| 500 | 161 818 | 403 401 |

**Escala de dificuldade — Moedas:**

| Dificuldade | Multiplicador |
|-------------|:-------------:|
| Tempo (sem luta) | ×1.00 |
| Easy  | ×1.00 |
| Medium | ×1.30 |
| Hard  | ×1.50 |

#### Implementação exacta

`app/HeroZero/QuestRewards.php` → `rollCoinsByGuildStat()`:

```php
$lvl = max(1, $level);
$s   = max(0, min(50, $guildStat));
$e   = max(1, $energy);

$guildFact   = 1.0 + $s * 0.031428;
$scaleFactor = max(1.0, ($lvl / 300.0) ** 2);
$base        = 113.0 * $lvl * $scaleFactor;

$perEnergy = ($base * $guildFact) / 7.5;           // COINS_ENERGY_REF
$variance  = randFloat(0.85, 1.15);                // COINS_VARIANCE = 0.15

$coins = $perEnergy * $e * $variance * FIGHT_SCALE_COINS[$difficulty];
return max(1, (int)round($coins));
```

O XP usa `missionXp()` com `rollXpMultiplier()`; `rollXpByGuildStat()` mantém
compatibilidade, convertendo energia base em segundos pela relação de 60 s/energia.

#### Comparação de fórmulas candidatas

Tabela interactiva com as 4 fórmulas avaliadas (quadrática original, quadrática
calibrada, linear+cúbica — a implementada — e raiz quadrada), para os níveis 1–500
com `stat` 1/50 e 50/50:

- Ficheiro local: [`comparacao_moedas.html`](comparacao_moedas.html)
- Versão publicada: <https://claude.ai/code/artifact/060344be-bd20-41c6-a4ff-d9b8707c81db>

---

### Expansões e energéticos

Valores fixados no momento da geração; alterações posteriores não recalculam
missões existentes. Energéticos expirados não entram na geração.

```
energia_final = max(1, round(energia_base × (1 − pessoal/100)))
duração_final = max(60, round(segundos_base × (1 − (pessoal + equipa)/100)))
```

Pessoal: 10/25/50%, reduz duração e energia. Equipa: 10/20%, reduz só duração.
Reduções de tempo somam-se. `duration_raw` guarda segundos base; `startQuest`
debita energia final e utiliza duração final sem aplicar descontos novamente.
Prémios mantêm-se. Exemplo: 600 s/10 energia, pessoal 50% + equipa 20% → 180 s/5 energia.

### Referência de XP por energia — focada

Sem arredondamentos do custo, antes da variação, para tempo ou luta fácil
(média multiplica por 1.2; difícil por 1.3):

| Expansão XP | Pessoal 25% | Pessoal 50% |
|---|---:|---:|
| 25/50 | 283.5 | 378 |
| 50/50 | 378 | 504 |

O energético de equipa não altera estes rácios, apenas XP/minuto.
Testes: `php scripts/testing/test_quest_generation.php` (offline e PDO simulado).

---

### Ficheiros de Referência

| Ficheiro | Função |
|----------|--------|
| `app/HeroZero/QuestRewards.php` | Todas as funções de randomização (duração, energia, XP, moedas, dificuldade, caps) |
| `app/HeroZero/Character.php` → `insertQuestPool()` | Gera e insere o pool de 3 missões na DB |
| `app/HeroZero/Character.php` → `rollQuest()` | Sorteia missão equilibrada ou focada |
| `app/HeroZero/Character.php` → `questRewardMultipliers()` | Lê o boost de guilda e devolve os fatores |
| `data/reference/quest_templates.json` | Catálogo de identificadores por stage e tipo |
| `actions/startQuest.php` | Inicia a missão (debita energia final sem reaplicar energéticos) |
| `actions/checkForQuestComplete.php` | Verifica conclusão; simula combate e aplica penalidade de derrota (−90%) |
| `actions/claimQuestRewards.php` | Aplica recompensas e regenera o pool do stage atual |
| `actions/generateNewQuests.php` | Botão "novas missões" — apaga pool e gera novo |

### Distribuição de energia de combate

As percentagens são os pesos das faixas sorteadas, não quotas exatas em cada
conjunto. Os limites 3 e 6 sobrepõem-se: com energético pessoal 50%, a
probabilidade final de 3 energias é 22.5% e de 6 é 17.5%. Assim, o intervalo
observado 3–6 reúne 65% dos resultados, incluindo os limites vindos das outras
faixas. Sem energético, os mesmos custos base variam de 2 a 18; com 25%,
variam de 2 a 14 por arredondamento. O energético de equipa não altera energia.
A regra aplica-se a combates equilibrados e focados; tempos não foram alterados.
