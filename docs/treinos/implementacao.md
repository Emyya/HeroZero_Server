# Sistema de Treino — Implementação

Documenta o que foi alterado/criado para o sistema de treino neste servidor privado.
Estado: parcialmente completo (ver secção "Por fazer").

---

## Arquitectura geral

O treino usa:
- Tabela `training` — regista o treino ativo por personagem.
- Colunas em `character` — contadores, timestamps, pool.
- `Character.php` — toda a lógica de treino.
- `actions/` — uma action por operação do cliente.

---

## Custo, duração e energia (fórmulas)

**Não há tiers fixos.** Cada oferta sorteia um custo de **1 a 9 unidades**, e tudo o resto deriva dele:

```
duration      = cost * 300              (segundos)
needed_energy = cost * 50 + 300 + (cost >= 4 ? 50 : 0)
```

Confirmado contra o HAR oficial (lv 15-17), 10 combinações observadas, todas a bater:

| Custo | Duração | `needed_energy` |
|---|---|---|
| 1  |  5 min |  350 |
| 2  | 10 min |  400 |
| 3  | 15 min |  450 |
| 4  | 20 min |  550 |
| 5  | 25 min |  600 |
| 7  | 35 min |  700 |
| 10 | 50 min |  850 |
| 11 | 55 min |  900 |
| 13 | 65 min | 1000 |
| 14 | 70 min | 1050 |

> A constante `TRAINING_TIERS` e o helper `trainingTier()` foram **removidos**.
> Helpers atuais: `trainingCost()`, `trainingDuration()`, `trainingNeededEnergy()`.

### Encoding do stat_type

`stat_type` na tabela `training` codifica o stat **e** o custo:

```
stat_type = cost * 10 + base        (base 1-4, cost 1-9)
```

- Base: 1=stamina, 2=strength, 3=critical, 4=dodge
- `trainingStatType(st)` → `st % 10` → base 1-4 (é o que vai para o cliente)
- `trainingCost(st)` → `intdiv(st, 10)` → custo 1-9

---

## Pool de ofertas

4 ofertas por personagem (uma por stat), custo sorteado por seed.

```
seed = crc32(char_id . '|' . ts_last_training)
cost de cada stat = ((seed >> ((base-1)*8)) & 0xFF) % 9 + 1     -> 1..9
stat_type = cost * 10 + base
id        = char_id * 1000 + stat_type
```

> **O multiplicador do id passou de `*100` para `*1000`** — o `stat_type` agora vai até 94
> (cost 9 + base 4) e já não cabe em dois dígitos.

- A seed muda ao fazer refresh e ao iniciar um treino (ambos atualizam `ts_last_training`).
- Quando há treino ativo, `trainingsData()` força o slot do stat correto a mostrar o `stat_type` do treino — a pool pode ter rodado mas o treino em curso mantém-se visível.

### Custo limitado às unidades disponíveis

`maxCost = max(1, min(9, training_count))` — nunca são geradas ofertas que o jogador não
possa pagar (com 3 unidades só saem custos 1-3). Ver **BUG-TR-021**.

### Pool vazia = ecrã "sem treinos"

Com `training_count <= 0` **e** sem treino a decorrer, `currentTrainingPool()` devolve `[]`
e o `overlayCharacter()` emite `training_pool = ''` (string vazia, **não** `'[]'`).
O cliente decide assim (ver **BUG-TR-024**):

```js
get_hasTrainingOffers()            // = character.training_pool != ""
  ? mostrar as 4 ofertas
  : (trainingCount > 0 ? info_no_offers              // cooldown, botão "Pular"
                       : info_no_training_sessions)  // 0 unidades
```

- `json_encode([])` dá `'[]'`, que passa no teste `!= ""` → o cliente julgaria haver
  ofertas e rebentava em `offer1.refresh(undefined)`.
- **Com treino a decorrer a pool nunca esvazia**, mesmo com 0 unidades: é ela que leva o
  treino ativo dentro de `trainings` (BUG-TR-008).
- A contagem regressiva é do cliente (`get_secondsUntilTomorrow()`); ao chegar a 0 ele
  dispara `refreshTrainingPool` sozinho. O servidor não envia nada para isso.

Método: `currentTrainingPool()` em `Character.php`.

---

## Unidades de treino (training_count)

### Base e máximo

```
max = 10 (base)
    + 5 × n_trofeus_tipo3 (Motivação, GuildArtifacts)
    + 4 ou 7 (booster de guilda ativo, tipo=1)
```

- Sincronizado em `syncTrainingForLevel()` a cada resposta.
- Reset diário a 00:00 do relógio INTERNO (`GameTime::now() - GameTime::now() % 86400`).
- Dois boosters existem: `guild_booster_training1` (+4) e `guild_booster_training2` (+7); só um ativo de cada vez.

> Os troféus tipo 3 dão bónus **só** a `training_count`. A barra de
> `training_energy` é **30/30 fixa** e não recebe bónus nenhum (ver BUG-TR-013).

### Custo

O custo da oferta (**1 a 9 unidades**) é debitado em `startTraining()`.

---

## Energia de treino (`training_energy`)

- **Base 30/30**, incluindo o INSERT de criação em `Character::createNew()`. Troféus e boosters não alteram a base; o evento de bónus tipo 10 aplica o seu fator à capacidade efetiva.
- **Reposta ao máximo em cada `startTraining()`**: cada treino começa com a barra cheia.
- Regenera 1/min entre respostas (`liveTrainingEnergy()`), limitada ao máximo.
- Debitada em `startTrainingQuest()` pelo `energy_cost` da quest.
- Erro quando não chega: `errRemoveTrainingQuestEnergyNotEnough` (o nome tem `Quest`; sem ele o cliente não mostra a mensagem certa).

---

### Reparar contas antigas com base 100

`php scripts/testing/repair_training_energy.php` simula; `--apply` grava e
`--hero="Nome"` limita a um herói. Seleciona apenas `max_training_energy=100`.
Calcula a energia atual com regeneração por `GameTime`, preserva o défice
na nova capacidade (mínimo zero) e mantém a fração de minuto no timestamp.
Respeita o fator do evento tipo 10. A aplicação usa transação e locks, e
exige backup em `server-laravel/storage/backups/` antes de alterar a BD.
Não modifica energia de missões, unidades ou treino ativo.

## Estrelas: limiares e recompensas

### `training_ratings` (constante do cliente — copiar, não inventar)

Cópia exata de `constants_json.data`; o cliente lê a mesma tabela (`nk`/`CTrainingRating`).
Constante `TRAINING_RATINGS` em `Character.php`. Ver **BUG-TR-017**.

| Estrela | `needed_progress` | `reward_factor` | `special_factor` |
|---|---|---|---|
| 1 | **10** | 0.2 | 0 |
| 2 | **40** | 0.5 | 0 |
| 3 | **100** | 1.0 | **1** |

O cliente calcula `progressValue = floor(energy / needed_energy × 100)` e oferece a
estrela cujo `needed_progress` já foi atingido. O servidor usa os **mesmos** limiares —
divergir faz o servidor recusar uma estrela que o cliente já mostra disponível.

### Recompensas

Fórmula completa com scaling de nível (ver `docs/treinos/formulas.md` para detalhe):

```
level_factor = max(1.0, level × (22.25/16) × (1 + level/5000))

coins = round(150 × custo × reward_factor × special_factor × level_factor × 0.10)
xp    = round( 50 × custo × reward_factor × special_factor × level_factor × 0.10)
```

| Estrela | `reward_factor` | `special_factor` | Atributo treinado |
|---|---|---|---|
| 1 | 0.2 | 1 | **+1** |
| 2 | 0.5 | 1 | **+2** |
| 3 | 1.0 | `BONUS_REWARD_FACTOR[bonus]` | **+3** |

**Calibração ao HAR oficial** (lv 15-17, custo=2, ★★★): 6 676 moedas / 2 126 XP.
O `level_factor` a nível 16 = 22.25 → `150 × 2 × 1.0 × 22.25 × 0.10 ≈ 6 675` ✓

**Componente quadrática suave** `(1 + level/5000)`: o ganho por nível cresce gradualmente
em vez de ser flat (lv 1 ≈ +21 moedas/nível para custo=1 ★★★; lv 800 ≈ +247 para custo=9).

Helper único `trainingStarReward()` serve o `rewards_star_N` (anúncio) **e** o crédito
real. Anunciar um valor e creditar outro faria o diálogo mentir ao jogador.

Cada estrela incrementa `stat_base_X` e `stat_trained_X` pelos pontos da tabela
(`TRAINING_STAT_POINTS` = 1/2/3), refletidos em `stat_points_star_1/2/3`.

> No servidor oficial `stat_trained_X` é um contador de ratio; o stat visível é `stat_base_X`. Aqui incrementamos os dois.

### Bónus da 3.ª estrela (`special_factor = 1`)

Probabilidades da tabela oficial `training_special_rewards` em `constants_json.data`:

```
roll = hexdec(substr(md5("training_bonus|<offerId>|<ts_last_training>"), 0, 8)) % 100
```

| roll | Resultado | Chance | `bonus_roll` | `special_factor` |
|---|---|---|---|---|
| 0–49 | Item épico | **50%** | 2 | ×1 (item é a recompensa) |
| 50 | Ponto de atributo livre | **1%** | 1 | ×1 |
| 51–84 | Sem bónus extra | **34%** | 0 | ×1 |
| 85–99 | Moedas/XP ×2 | **15%** | 3 | ×2 |

> O `factor = 9` da tabela oficial para o item (type=1) é o peso/valor do item,
> **não** um multiplicador de moedas — o item épico é a recompensa em si.

- Sorteado **na oferta** (o painel mostra o ícone antes de começar).
- Persistido em `training.bonus_roll` no `startTraining()`, **antes** de `ts_last_training`
  mudar — esse timestamp faz parte da seed, e sem persistir o sorteio mudava ao começar.
- `md5` e não `crc32`: com ids sequenciais o `crc32` agrupa os valores (BUG-TR-019).

### Item da recompensa

O cliente resolve o item na coleção do **próprio jogador**
(`ec.get_item()` → `character.getItemById(id)`), logo um id inventado não mostra nada.
Ver **BUG-TR-022** e **BUG-TR-023**.

1. `trainingPoolRewardItems()` **pré-gera** os itens das ofertas que sortearam "item"
   com **qualidade épica forçada** (`generateShopItem(false, 3)`) e guarda o mapa
   `{offerId: itemId}` em `character.training_pool_items`.
2. Regenerado quando `ts_last_training` muda. Itens de pools antigas que ninguém ganhou
   são **reciclados**; nunca os presos a um treino, equipados ou na mochila.
3. `startTraining()` fixa o item da oferta escolhida em `training.reward_item_id`.
4. **Só na 3.ª estrela** o `putItemInBag()` o coloca numa slot livre (`bag_item1..18_id`
   do `inventory`). Existir em `items` não é possuir — sem este passo o ícone aparecia
   mas a mochila ficava vazia.

---

## Timestamps importantes

| Campo | Onde | Uso |
|---|---|---|
| `training.ts_creation` | tabela training | quando o treino começou |
| `training.ts_end` | tabela training | `ts_creation + duration` → countdown no cliente |
| `character.ts_last_training` | character | seed da pool + reset diário |
| `character.ts_last_training_finished` | character | cliente detecta conclusão e navega de volta às ofertas |
| `character.ts_last_training_refresh` | overlay | alias de `ts_last_training` (campo do protocolo) |

---

## Fluxo de uma sessão de treino

1. **`startTraining`** — escolhe oferta, debita `training_cost` unidades, insere linha em `training` com `ts_end = now + duration`, **repõe `training_energy` ao máximo (30/30)**, persiste `bonus_roll` e `reward_item_id` da oferta.
   - Se existir um treino cujo `ts_end` já passou, fecha-o (`status=4`) em vez de dar `errStartTrainingAlreadyActive` (BUG-TR-014).
2. **`startTrainingQuest`** — escolhe uma das 3 quests, debita `energy_cost`, grava `training.training_quest_id`, devolve a quest com `status=2`. Se for de luta, simula o combate e devolve `training_battle`.
3. **`claimTrainingQuestRewards`** — soma `training_progress` real da quest a `used_resources` (absoluto, 0→`needed_energy`), repõe `training_quest_id = 0` (é isto que fecha o painel no cliente) e gera **3 ofertas novas** (a seed da pool usa `used_resources`).
4. Repetir 2-3 até a barra chegar aos limiares (10 / 40 / 100).
5. **`claimTrainingStar`** × 3 — moedas, XP e pontos do atributo treinado (+1/+2/+3).
   - **O `status` fica a 2**, mesmo na 3.ª estrela. Fechá-lo aqui mata o `get_training()` no cliente e o diálogo "Treino Concluído" nunca abre (BUG-TR-018).
   - Na 3.ª: aplica o bónus sorteado (ponto livre / item épico para a mochila / moedas×2 / nada) e grava `ts_last_training_finished`.
6. **`finishTraining`** — o `OK` do diálogo. Decide por `iterations >= 3` (não por `status`) e marca `status = 3`.

> Se chamado com treino em curso e sem as 3 estrelas: skip premium (custo estimado 3 donuts).

### Diálogos no fim (cadeia de gatilhos no cliente)

Timer do painel `training_progress` (barra absoluta 0→`needed_energy`):

```js
if (a.get_hasClaimableStars())        showDialog(new Ys(a))   // recompensa da estrela
else if (a.get_isComplete())
    3 > a.get_claimedStars() ? showDialog(new ku(a))          // "estender tempo?"
                             : showTrainingCompleteDialog()   // "Treino Concluído"
```

`get_isComplete()` = sem estrelas por reclamar **e** (`status` 4/5 **ou** `energy >= needed_energy`).
`showTrainingCompleteDialog()` exige `get_training()` não-nulo — daí o `status` ter de ficar a 2.

### Quando o tempo do treino expira

`trainingsData()` deteta `ts_end <= now` e força `used_resources = needed_energy` — as 3 estrelas ficam
disponíveis. **Nunca mexer em `iterations`**: é o contador de estrelas já *reclamadas*, e pô-lo
a 3 deixa o treino preso para sempre (ver BUG-TR-014).

---

## Quests de treino

Geradas em `trainingQuestsData()`. 3 quests, uma de cada tipo, em ordem permutada por seed.

```
seedBase = char_id | ts_creation | used_resources
id       = (char_id*1000 + stat_type) * 1000 + used_resources*10 + slot
```

O `used_resources` na seed faz a pool **rodar a cada quest concluída** (ids novos, como no
oficial) mantendo-se estável durante a quest em curso — por isso o progresso é somado no
`claimTrainingQuestRewards` e não no `startTrainingQuest`.

| Tipo | Identifiers | Campos extra | Comportamento no cliente |
|------|-------------|-------------|--------------------------|
| 1 (Instant) | `indoor_instant1..5` | — | Abre dialog resultado (`gt`) imediatamente |
| 2 (Fight) | `indoor_fight1..3` | `fight_npc_identifier`, `fight_difficulty` | Abre painel `"battle"` contra NPC |
| 3 (Stat) | `{stat}_indoor1..3` | `stat` = 1-4 | Abre painel `"stat_quest"` com animação |

NPCs de luta (confirmados no HAR oficial): `npc_racoon`, `npc_vending_machine`, `npc_meterman`.

> Os 3 identifiers têm de existir em `quest_templates` (`constants_json.data`), mesmo que como
> objeto vazio: `Su.fromId()` devolve null para identifiers desconhecidos e o cliente rebenta
> em `null.get_voice()` ao montar o painel.

**Dificuldade** (HAR confirma 1/2/3, não 0/1/2):
- instant: `fight_difficulty = 0` (sem dificuldade)
- fight / stat: `fight_difficulty = ((variantSeed >> 8) % 3) + 1`  →  1=fácil, 2=médio, 3=difícil

**Custo de energia** (dinâmico; intervalo 1–9 observado pelo jogador):
`energy_cost = 1 + ((variantSeed >> 24) & 0xFF) % 9`  →  1–9 por quest

**Progresso da barra** por quest (absoluto, `used_resources` vai de 0 a `needed_energy`):
`training_progress = 45 + ((variantSeed >> 16) & 0xFF) % 136`  →  45–180 por quest

Erros retornados pelo servidor:
- `errStartTrainingQuestNoActiveTraining`
- `errRemoveTrainingQuestEnergyNotEnough`
- `errStartTrainingQuestInvalidQuest`
- `errStartInvalidStatus`

---

## Refresh do pool

Action: `refreshTrainingPool`
Ficheiro: `actions/refreshTrainingPool.php`

- Valida que não há treino ativo.
- **Livre**: cooldown 600 s desde `ts_last_training`; erro `errGenerateNewTrainingsNotYetAllowed`.
- **Premium**: gasta 1 donut; erro `errRemovePremiumCurrencyNotEnough`.
- Atualiza `ts_last_training = GameTime::now()` → nova seed → novo pool.

---

## `training` (singular) vs `trainings` (plural) — REGRA CRÍTICA

No cliente:

```js
refreshTrainings (plural)   -> this._trainings = new Ec   // DESTRÓI tudo: a lista de quests fica vazia
refreshTraining  (singular) -> createOrUpdateMap(...)     // atualiza no sítio, preserva as quests
```

**Com treino ativo manda-se `training` (singular).** O plural só no boot e no refresh de ofertas.
Mandar o plural em toda a resposta fazia o `syncGame` (polling constante) apagar a lista de
quests e, com ela, a batalha que o `startTrainingQuest` acabara de ligar (ver BUG-TR-015).

Implementado em `Live::attachTrainingState($data, $char, $singularQuestId = 0, $forcePlural = false)`:

| Situação | Chaves enviadas |
|---|---|
| Sem treino ativo, ou `$forcePlural` (boot) | `trainings` |
| Treino ativo (caso geral: `syncGame`, `claimTrainingStar`, …) | `training` + `training_quests` |
| `startTrainingQuest` qualquer tipo (`$singularQuestId > 0`) | `training` + `training_quest` (+ `training_battle` se type=2) |

No `startTrainingQuest` **nunca** se reenvia `training_quests`: `refreshTrainingQuests` cria objetos
novos e quebra a referência que o painel aberto já tem (BUG-TR-011). Antes só o type=2 (batalha)
usava este modo; type=3 (stat_quest) mandava `training_quests` e ficava com `training_quest_id`
errado da BD — `INT(11)` overflowava para 2147483647 com ids grandes (BUG-TR-025).

O `training_battle` tem de levar a linha **completa** (`battleData()` → `SELECT *`), incluindo
`profile_a_stats`/`profile_b_stats` — é de lá que o cliente lê nível, vida e atributos.

---

## Schema — colunas adicionadas

| Tabela | Coluna | Tipo | Default | Motivo |
|---|---|---|---|---|
| `training` | `ts_end` | INT | 0 | countdown no cliente |
| `training` | `training_quest_id` | **BIGINT** | 0 | quest em curso; volta a 0 no claim e fecha o painel da batalha (BUG-TR-016). BIGINT obrigatório — ids chegam a ~25 mil milhões e overflowavam para 2147483647 com INT, quebrando o painel stat_quest (BUG-TR-025) |
| `training` | `reward_item_id` | INT | 0 | item da 3.ª estrela, fixado no arranque (BUG-TR-022) |
| `training` | `bonus_roll` | TINYINT | 0 | bónus sorteado: 0=nada, 1=ponto livre, 2=item épico, 3=moedas×2 (BUG-TR-020) |
| `character` | `ts_last_training_finished` | INT | 0 | detecção de conclusão no cliente |
| `character` | `training_pool_items` | VARCHAR(255) | `''` | JSON `{ts, map:{offerId:itemId}}` dos itens pré-gerados da pool (BUG-TR-022) |

---

## Ficheiros alterados / criados

| Ficheiro | Tipo | O que mudou |
|---|---|---|
| `app/HeroZero/Character.php` | alterado | `syncTrainingForLevel`, `trainingsData`, `trainingQuestsData`, `currentTrainingPool`, `startTraining`, `startTrainingQuest`, `claimTrainingQuestRewards`, `claimTrainingStar`, `finishTraining`, `overlayCharacter`; helpers `trainingStatType`/`trainingCost`/`trainingDuration`/`trainingNeededEnergy`/`trainingStarReward`/`trainingOfferBonus`/`trainingBonusRoll`/`trainingPoolRewardItems`/`putItemInBag`; constantes `TRAINING_RATINGS`/`TRAINING_STAT_POINTS`/`BONUS_ROLL_NAMES` (removidos `TRAINING_TIERS` e `trainingTier`) |
| `app/HeroZero/Live.php` | alterado | `attachTrainingState` (modo singular/plural), `accountState` |
| `app/HeroZero/GuildArtifacts.php` | alterado | `BONUS_PER_TROPHY[3]` só com `training_count` (removido `training_energy`) |
| `app/HeroZero/Router.php` | alterado | `refreshTrainingPool` → `refreshTrainingPool.php` |
| `actions/trainingQuest.php` | alterado | modo singular no `startTrainingQuest` para **todos** os tipos de quest (antes só type=2/fight); `claimTrainingQuestRewards` já não devolve `training_quest` (BUG-TR-025) |
| `actions/autoLoginUser.php` | alterado | `attachTrainingState(..., forcePlural: true)` |
| `actions/refreshTrainingPool.php` | novo | refresh do pool (livre + premium) |
| `public/assets/data/constants_json.data` | alterado | `quest_templates` com `npc_racoon`, `npc_vending_machine`, `npc_meterman` |
| `docker/initdb/01_schema.sql` | alterado | `training.ts_end`, `training.training_quest_id`, `training.reward_item_id`, `training.bonus_roll`, `character.ts_last_training_finished`, `character.training_pool_items` |

---

## Por fazer

- [x] Gerar os 3 tipos de quests reais (instant/fight/stat) com identifiers corretos
- [x] `errRemoveTrainingQuestEnergyNotEnough` — nome correto e débito antes de iniciar a quest
- [x] `training_energy` — 30/30 fixo, reposto em cada `startTraining`, regenera 1/min
- [x] Limiares das estrelas alinhados com `training_ratings` (10/40/100) — BUG-TR-017
- [x] Diálogos de fim de treino ("recompensa da estrela" e "Treino Concluído") — BUG-TR-018
- [x] Pontos do atributo treinado +1/+2/+3 e bónus da 3.ª estrela — BUG-TR-019
- [x] Bónus visível já no painel de ofertas e cumprido no fim — BUG-TR-020
- [x] Pool nunca gera treinos mais caros que as unidades restantes — BUG-TR-021
- [x] Ecrã "sem treinos" com 0 unidades (`training_pool` vazio) — BUG-TR-024
- [x] Item da recompensa com id real e entregue na mochila — BUG-TR-022, BUG-TR-023
- [x] XP/moedas escalarem com nível do herói — fórmula `level × (22.25/16) × (1 + level/5000)`, calibrada ao HAR (lv 16, custo=2, ★★★ = 6 676 moedas). `docs/treinos/formulas.md`
- [x] Item da recompensa sempre épico (`generateShopItem(false, 3)`) — confirmado como comportamento oficial
- [x] Bónus da 3.ª estrela com probabilidades oficiais (50% item / 1% ponto / 34% nada / 15% ×2 moedas) em vez de 30/30/40
- [x] Novo tipo `coins_2x` (`bonus_roll = 3`) com `special_factor = 2`
- [x] **Custos de energia dinâmicos**: `energy_cost = 1 + ((variantSeed >> 24) & 0xFF) % 9` (1–9 por quest, intervalo corrigido após observação do jogador)
- [x] **Modelo do progresso absoluto**: `used_resources` vai de 0 a `needed_energy`; cada quest soma `training_progress` (45–180, variável)
- [x] **Dificuldade variável**: fight/stat usam 1/2/3 (HAR confirma), instant sempre 0
- [x] **Recompensas das quests escaladas**: moedas por `0.01×level^1.55×needed_energy/7.76×tier`; XP = moedas×(0.80–1.15)×0.5 (ajuste servidor)
- [x] **stat_points_star_N escalados com custo**: `cost×0.25` / `cost×0.50` em vez de fixo 1/2/3
- [x] **buyTrainingEnergy corrigido**: adiciona +15 a `training_energy` (era a atualizar `training_count`); custo 1 premium
- [ ] **Mochila cheia**: `putItemInBag()` devolve `false` e o item fica sem slot. Comportamento do oficial desconhecido.
- [ ] **Assets em falta**: `npc_racoon` e `npc_vending_machine` não têm ficheiro local (só `npc_meterman_t2.webp`)

## Eventos de bónus (2026-10-04)

Tipos 5 e 10 multiplicam respectivamente unidades diárias e capacidade da barra.
O reset usa `daily_bonus_resources.training_date` no calendário local de GameTime,
e não `ts_last_training`. Isso impede repor unidades em cada sync antes de iniciar
um treino. O acréscimo do evento só é concedido uma vez por data.
Ver [login e Dias de Bónus](../logins/login_diario_e_bonus.md).
