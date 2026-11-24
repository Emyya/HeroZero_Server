# Fórmulas do Sistema de Treino

Referência das fórmulas matemáticas usadas no sistema de treino deste servidor privado.
Todas as implementações estão em `server-laravel/app/HeroZero/Character.php`.

---

## Encoding do `stat_type`

O campo `stat_type` na tabela `training` codifica simultaneamente o stat base e o custo:

```
stat_type = cost * 10 + base
```

- `base` = 1 (stamina) · 2 (strength) · 3 (critical) · 4 (dodge)
- `cost` = 1–9 (unidades de treino gastas)

**Helpers** (`Character.php`):
- `trainingStatType(int $st): int` — linha 947 → `$st % 10` → base 1-4
- `trainingCost(int $st): int` — linha 953 → `intdiv($st, 10)` → custo 1-9

---

## Duração e energia necessária

Derivados do custo (confirmados contra `training_durations` em `constants_json.data`):

```
duration      = cost × 300           (segundos)
needed_energy = cost × 50 + 300 + (cost >= 4 ? 50 : 0)
```

| Custo | Duração | `needed_energy` |
|---|---|---|
| 1 | 5 min | 350 |
| 2 | 10 min | 400 |
| 3 | 15 min | 450 |
| 4 | 20 min | 550 |
| 5 | 25 min | 600 |
| 6 | 30 min | 650 |
| 7 | 35 min | 700 |
| 8 | 40 min | 750 |
| 9 | 45 min | 800 |

**Helpers** (`Character.php`):
- `trainingDuration(int $cost): int` — linha 959
- `trainingNeededEnergy(int $cost): int` — linha 965

---

## Pool de ofertas

4 ofertas por personagem (uma por stat base), geradas por seed determinística:

```
seed     = crc32(char_id . '|' . ts_last_training)
maxCost  = max(1, min(9, training_count))
cost[base] = ((seed >> ((base-1) * 8)) & 0xFF) % maxCost + 1
stat_type  = cost * 10 + base
pool_id    = char_id * 1000 + stat_type
```

- `maxCost` garante que nunca saem ofertas mais caras que as unidades disponíveis.
- Com `training_count <= 0` e sem treino ativo, a pool devolve `[]` → o cliente mostra o ecrã "sem treinos".

**Referência** (`Character.php`): `currentTrainingPool()` — linha 975

---

## Unidades de treino (`training_count`)

```
max = 10 (base)
    + 5 × n_troféus_tipo3_guilda    (Motivação — GuildArtifacts)
    + 4 ou 7 (guild_booster_training1/2, se ativo)
```

Reset diário a 00:00 do relógio interno do servidor:

```
today = Live::gameNow()->format('Y-m-d')
reset se daily_bonus_resources.training_date < today
max_efectivo = max_base × BonusEvents::factor(5)
```

**Referência** (`Character.php`): `syncTrainingForLevel()` — linha 1009

---

## Energia de treino (`training_energy`)

- **Máximo base:** 30 (não recebe bónus de troféus nem boosters); eventos tipo 10 multiplicam a capacidade.
- **Regeneração:** 1 por minuto (`training_energy_refresh_amount_per_minute = 1` nos constants)
- **Reset ao máximo** em cada `startTraining()`

```
live_energy = min(max, stored + floor((now - ts_last_energy_change) / 60))
```

**Referência** (`Character.php`): `liveTrainingEnergy()` — linha 2669

---

## Limiares das estrelas

Copiados da tabela `training_ratings` em `constants_json.data` (campo `nk/CTrainingRating`).
O cliente usa os mesmos valores — divergir faz o servidor recusar estrelas que o cliente já mostra disponíveis.

```
progressValue = floor(energy / needed_energy × 100)    (calculado no cliente)
```

| Estrela | `needed_progress` | `reward_factor` | `special_factor` |
|---|---|---|---|
| ★ | 10 | 0.2 | 0 |
| ★★ | 40 | 0.5 | 0 |
| ★★★ | 100 | 1.0 | 1 (bónus ativo) |

**Constante** (`Character.php`): `TRAINING_RATINGS` — linha 72

---

## Recompensas de moedas e XP por estrela

```
level_factor = max(1.0, level × (22.25 / 16) × (1 + level / 5000))

coins = round(150 × cost × reward_factor × special_factor × level_factor × 0.10)
xp    = round( 50 × cost × reward_factor × special_factor × level_factor × 0.10)
```

### Componentes

| Componente | Valor | Fonte |
|---|---|---|
| Base moedas | `150 × cost` | Aproximação nossa |
| Base XP | `50 × cost` | Aproximação nossa |
| `reward_factor` | 0.2 / 0.5 / 1.0 por estrela | `training_ratings` (constants) |
| `special_factor` | `BONUS_REWARD_FACTOR[bonus]` — só na ★★★ | ver secção Bónus |
| `level_factor` | linear + quadrática suave | calibrado ao HAR |
| Escalar final | × 0.10 (moedas) · × 0.10 (XP) | ajuste deste servidor |

### Calibração do `level_factor`

Referência do HAR oficial (nível 15-17, custo=2, ★★★): **6 676 moedas / 2 126 XP**.

- Factor linear: `level × (22.25 / 16)` → ×22.25 a nível 16 ≈ referência ✓
- Componente quadrática: `× (1 + level / 5000)` → crescimento suave por nível
  - Nível 1: +~21 moedas/nível (custo 1 ★★★)
  - Nível 333: +~213 moedas/nível (custo 9 ★★★)
  - Nível 800: +~247 moedas/nível (custo 9 ★★★)

### Valores de referência (★★★, sem bónus especial)

| Nível | Custo 1 | Custo 3 | Custo 5 | Custo 9 |
|---|---|---|---|---|
| 1 | 21 m / 7 xp | 63 m / 21 xp | 104 m / 35 xp | 188 m / 63 xp |
| 50 | 1.043 m / 348 xp | 3.129 m / 1.043 xp | 5.215 m / 1.738 xp | 9.387 m / 3.129 xp |
| 100 | 2.086 m / 695 xp | 6.258 m / 2.086 xp | 10.430 m / 3.477 xp | 18.773 m / 6.258 xp |
| 200 | 4.172 m / 1.391 xp | 12.516 m / 4.172 xp | 20.859 m / 6.953 xp | 37.547 m / 12.516 xp |
| 333 | 6.946 m / 2.315 xp | 20.839 m / 6.946 xp | 34.731 m / 11.577 xp | 62.516 m / 20.839 xp |
| 500 | 10.430 m / 3.477 xp | 31.289 m / 10.430 xp | 52.148 m / 17.383 xp | 93.867 m / 31.289 xp |
| 800 | 16.688 m / 5.563 xp | 50.063 m / 16.688 xp | 83.438 m / 27.813 xp | 150.188 m / 50.063 xp |

**Referência** (`Character.php`): `trainingStarReward()` — linha 226

---

## Bónus da 3ª estrela (`special_factor = 1`)

Sorteio determinístico com `md5` (não `crc32` — ids sequenciais agrupam o crc32):

```
seed = 'training_bonus|' . offerId . '|' . ts_last_training
roll = hexdec(substr(md5(seed), 0, 8)) % 100
```

| roll | Resultado | Chance | `special_factor` |
|---|---|---|---|
| 0–49 | Item épico (`bonus_roll = 2`) | **50%** | ×1 (item é a recompensa) |
| 50 | Ponto de atributo livre (`bonus_roll = 1`) | **1%** | ×1 |
| 51–84 | Sem bónus extra (`bonus_roll = 0`) | **34%** | ×1 |
| 85–99 | Moedas/XP ×2 (`bonus_roll = 3`) | **15%** | ×2 |

Fonte: tabela `training_special_rewards` em `constants_json.data`.

O sorteio é feito **na oferta** (antes do treino começar) e persistido em `training.bonus_roll`
antes de `ts_last_training` ser atualizado — garante que o que a oferta promete é o que é entregue.

**Constantes** (`Character.php`):
- `BONUS_ROLL_NAMES` — linha 89
- `BONUS_REWARD_FACTOR` — linha 99
- `trainingOfferBonus()` — linha 115

---

## Item épico de recompensa

Pré-gerado no momento em que a pool é criada (como o servidor oficial):

1. `trainingPoolRewardItems()` (linha 159) — para cada oferta com bónus `'item'`, gera um item com `generateShopItem(false, 3)` (qualidade 3 = épico forçado) e guarda o id em `character.training_pool_items`.
2. No `startTraining()`, o item da oferta escolhida é fixado em `training.reward_item_id`.
3. Só na ★★★ o `putItemInBag()` (linha 124) coloca o item numa slot livre da mochila.

**Nota:** o `factor = 9` em `training_special_rewards` para `type = 1` (item) é o peso/valor do item na tabela do cliente, não um multiplicador de moedas/XP.

---

## Pontos do atributo treinado por estrela

Escalados com o custo do treino (confirmado no HAR herozone.fun, cost=4 → 1/1/2):

```
star 1 / 2 → max(1, round(cost × 0.25))
star 3     → max(1, round(cost × 0.50))
```

| Custo | ★ | ★★ | ★★★ |
|---|---|---|---|
| 1–3 | 1 | 1 | 1 |
| 4–5 | 1 | 1 | 2 |
| 6–7 | 2 | 2 | 3 |
| 8–9 | 2 | 2 | 4 |

**Referência** (`Character.php`): `trainingStatPoints()` (método privado estático)

---

## Quests de treino — recompensas e progresso

### Barra de progresso (`used_resources`)

`used_resources` é **absoluto** (0 → `needed_energy`). Cada quest soma `training_progress` ao valor acumulado.

```
training_progress = 45 + (variantSeed >> 16) & 0xFF) % 136   (45–180 por quest)
```

A barra fica cheia (`used_resources >= needed_energy`) tipicamente em ~5 quests.

Limiares das estrelas (absolutos):

```
estrela N → (needed_progress_N / 100) × needed_energy
```

### Energia por quest

```
energy_cost = 1 + ((variantSeed >> 24) & 0xFF) % 9   (1–9 por quest)
```

### Dificuldade das quests

- **instant** (tipo 1): sempre `fight_difficulty = 0` (sem dificuldade — HAR confirma)
- **fight / stat** (tipo 2 / 3): `fight_difficulty = ((variantSeed >> 8) % 3) + 1`  →  1=fácil, 2=médio, 3=difícil

### Moedas por quest

```
levelRate = 0.01 × level^1.55
baseMult  = needed_energy / 7.76
baseCoin  = levelRate × baseMult

tier por dificuldade (4 valores, escolhido com seed):
  diff=0 instant:  [×1.0, ×1.25, ×1.5, ×2.0]
  diff=1 fácil:    [×1.0, ×1.25, ×1.5, ×2.0]
  diff=2 médio:    [×1.25, ×1.5, ×2.0, ×2.5]
  diff=3 difícil:  [×1.5, ×2.5, ×4.0, ×5.0]

tierIdx   = ((variantSeed >> 16) & 0x3F) % 4
questCoins = round(baseCoin × tier[tierIdx])
```

### XP por quest

Constants oficiais: `quest_reward_xp_scale_balanced = 1` / `quest_reward_coin_scale_balanced = 1` → XP ≈ moedas.
**Ajuste deste servidor: ×0.5** (decisão do operador).

```
ratio   = 0.80 + ((variantSeed >> 22) & 0xFF) % 36 / 100   (0.80–1.15)
questXp = round(questCoins × ratio × 0.5)   ← metade do oficial
```

| Nível | Fácil (×1.25) | Médio (×1.75) | Difícil (×3.25) |
|------:|--------------|--------------|----------------|
| 50 | ~568 xp | ~795 xp | ~1 476 xp |
| 200 | ~3 714 xp | ~5 196 xp | ~9 659 xp |
| 350 | ~8 348 xp | ~11 682 xp | ~21 700 xp |
| 500 | ~14 193 xp | ~19 871 xp | ~36 910 xp |
| 800 | ~28 952 xp | ~40 527 xp | ~75 297 xp |

(custo=4, needed_energy=550, ratio≈0.975)

**Referência** (`Character.php`): `trainingQuestsData()`, `trainingQuestCoins()`, `trainingQuestXp()`

---

## ID das quests de treino

```
trainingId = char_id × 1000 + stat_type   (stat_type = cost×10 + base)
questId    = trainingId × 1000 + used_resources × 10 + slot   (slot = 1-3)
```

- `used_resources` (absoluto) como seed da ronda — muda a cada `claimTrainingQuestRewards`, gerando ids novos.
- Pool de quests estável durante a quest em curso; roda no claim (nunca no start).

**Referência** (`Character.php`): `trainingQuestsData()`

Eventos e marcador diário: ver [login diário e bónus](../logins/login_diario_e_bonus.md). O reset
usa agora data local de GameTime e um marcador próprio, sem depender de iniciar
um treino para impedir reposições repetidas. O máximo enviado ao cliente continua
base, porque o cliente aplica o multiplicador do evento.
