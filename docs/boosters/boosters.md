# Boosters / Energéticos — Hero Zero

Todos os boosters estão definidos na master data (`constants_json.data`, chave `boosters`).
A compra é feita via `buyBooster` (tipos 1-4) ou `boosterExtra.php` (tipos 5-8).

---

## Estrutura na master data

```json
"booster_work3": {
  "type": 3,
  "amount": 50,
  "amount2": 0,
  "default_duration": 0,
  "duration": {
    "604800":  { "premium": true,  "cost": 10  },
    "2592000": { "premium": true,  "cost": 40  },
    "7776000": { "premium": true,  "cost": 115 }
  }
}
```

| Campo | Significado |
|---|---|
| `type` | Tipo do booster (ver tabela abaixo) |
| `amount` | Percentagem de bónus principal |
| `amount2` | Percentagem de bónus secundário (quando existe) |
| `duration` | Mapa `segundos → {premium, cost}` — chave `-1` = permanente |
| `premium: true` | Custa donuts; `false` = custa moedas de ouro |

---

## Tipos de boosters

| Tipo | ID(s) | Nome | Bónus | Efeito |
|---|---|---|---|---|
| 1 | `booster_quest1/2/3` | Energético de Missão | 10% / 25% / 50% | Reduz duração e custo de energia das missões, mantendo prémios |
| 2 | `booster_stats1/2/3` | Energético de Treino | 10% / 25% / 50% | +% ganho de atributos no treino |
| 3 | `booster_work1/2/3` | Energético de Trabalho | 10% / 25% / 50% | +% capacidade máxima de moedas da central do esconderijo |
| 4 | `sense_booster` | Sentido de Herói | — | Activa sentido especial temporariamente |
| 5 | `booster_league1/2` | Booster de Liga | +60 / +120 stamina máx | Aumenta stamina máxima de liga |
| 6 | `multitasking_booster` | Multitarefa | — | Permite missão extra em paralelo |
| 7 | `training_sense_booster` | Sentido de Treino | — | Bónus de treino especial |
| 8 | `booster_hideout1/2` | Booster do Esconderijo | 50% / 100% | +% produção de cola e pedra (⚠️ não implementado) |

---

## Onde ficam guardados na BD (tabela `character`)

| Tipo | Coluna ID | Coluna expiração |
|---|---|---|
| 1 — Missão | `active_quest_booster_id` | `ts_active_quest_boost_expires` |
| 2 — Treino | `active_stats_booster_id` | `ts_active_stats_boost_expires` |
| 3 — Trabalho | `active_work_booster_id` | `ts_active_work_boost_expires` |
| 4 — Sentido | *(sem coluna de ID)* | `ts_active_sense_boost_expires` |
| 5 — Liga | `active_league_booster_id` | `ts_active_league_boost_expires` |
| 6 — Multitarefa | *(sem coluna de ID)* | `ts_active_multitasking_boost_expires` |
| 7 — Sentido Treino | *(sem coluna de ID)* | *(sem coluna separada — usa sense?)* |
| 8 — Esconderijo | *(não implementado)* | *(não implementado)* |

> Duração acumula: comprar com tempo restante **soma** ao prazo actual, não substitui.

---

## Preços e durações

| ID | Duração | Custo | Premium? |
|---|---|---|---|
| `booster_quest1` / `booster_stats1` / `booster_work1` | 2 dias | 206 moedas | ❌ |
| `booster_quest2` / `booster_stats2` / `booster_work2` | 4 dias | 2.057 moedas | ❌ |
| `booster_quest3` / `booster_stats3` / `booster_work3` | 7 dias | 10 donuts | ✅ |
| `booster_quest3` / `booster_stats3` / `booster_work3` | 30 dias | 40 donuts | ✅ |
| `booster_quest3` / `booster_stats3` / `booster_work3` | 90 dias | 115 donuts | ✅ |
| `booster_league1` | 4 dias | 2.057 moedas | ❌ |
| `booster_league2` | 7 / 30 / 90 dias | 10 / 40 / 115 donuts | ✅ |
| `booster_hideout1` | 4 dias | 5 donuts | ✅ |
| `booster_hideout2` | 7 / 30 / 90 dias | 10 / 40 / 115 donuts | ✅ |
| `multitasking_booster` | 7 dias | 9 donuts | ✅ |
| `multitasking_booster` | Permanente | 99 donuts | ✅ |

---

## Efeito do Energético de Trabalho (tipo 3) na central

O bónus de trabalho é aplicado como multiplicador na fórmula da capacidade máxima
de moedas da central do esconderijo. Ver `docs/hideout/central_coin_gen_formula.md`.

```
capacidade = 14.3 × nível^1.39 × rpm × bónus_guilda × (1 + energético%/100)
```

O booster só é válido enquanto `ts_active_work_boost_expires > GameTime::now()`.

---

## Acções relevantes no servidor

| Acção | Ficheiro | Trata tipos |
|---|---|---|
| `buyBooster` | `actions/buyBooster.php` | 1, 2, 3, 4 |
| `buyLeagueBooster` | `actions/boosterExtra.php` | 5 |
| `buyMultitaskingBooster` | `actions/boosterExtra.php` | 6 |
| `buySenseBooster` | `actions/buySenseBooster.php` | 4 (sentido) |
| `buyGuildBooster` | `actions/boosterExtra.php` | boosters de guilda |
| `buyHideoutBooster` | `actions/boosterExtra.php` | 8 |

## Energético de tempo da equipa e missões

`guild_booster_quest1/2`: 10/20%, reduz apenas duração das missões.
A redução de tempo soma-se à do energético pessoal; energia usa só o pessoal.
O backend aplica ambos ao gerar novas missões e conserva prémios. Ver
[missões](../missoes/geral.md) para fórmula, arredondamentos e testes.
