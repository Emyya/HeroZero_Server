# Missões — Visão Geral da Implementação

Identidade e imagens dos NPCs de combate: [npcs.md](npcs.md).

Documentação principal desta pasta. Fórmulas das missões em
[missoes_escala.md](missoes_escala.md); implementação das chamadas heroicas em
[chamadas_emergencia.md](chamadas_emergencia.md).

Ficheiros principais:

- `server-laravel/app/HeroZero/QuestRewards.php` — fórmulas de recompensa e geração
- `server-laravel/app/HeroZero/Character.php` — pool de missões, reset diário, energéticos
- `server-laravel/actions/energyTraining.php` — compra de energia, depósito
- `server-laravel/data/reference/balancing_constants.json` — constantes enviadas ao cliente
- `server-laravel/app/HeroZero/Herobook.php` — chamadas heroicas/de emergência
- `server-laravel/actions/herobook.php` — resgate, troca e conclusão das chamadas

---

## Pool de missões

Cada cidade tem um pool de **3 missões por personagem** (tabela `quests`, `status=1`).
O pool é gerado em `Character::insertQuestPool()` e reconstruído:
- ao fazer _claim_ de uma missão completada (`Character::claimQuest()`)
- ao subir de nível (`Character::applyLevelUp()`)
- manualmente com `refreshQuestsAndTrainings` (1 donut, `energyTraining.php`)

O botão «novas missões» usa `generateNewQuests`: `all_stages=false` renova
apenas a zona selecionada (2 donuts); `all_stages=true` renova todas as zonas
até `max_quest_stage` (9 donuts), independentemente de `current_quest_stage`.
A missão ativa é preservada e ocupa uma vaga na respetiva zona. A geração
inclui os sorteios de objetos e referências temáticas de eventos em todas as
zonas renovadas. Correção registada em BUG-000163.

**Composição do pool:**
- Duas missões de tempo (`type=1`) e uma de luta (`type=2`), em posições aleatórias.
- Uma missão focada em XP por conjunto completo: a luta tem 65% de probabilidade
  de ser focada; nos restantes 35%, escolhe-se uma das duas de tempo.
- Na reposição parcial, os tipos existentes são contados para preencher apenas
  as vagas. Missões ativas e prémios existentes são preservados; a regra de uma
  focada é garantida na geração de um conjunto completo.

## Fórmulas de geração

### Duração e energia

Tempo: duração base entre `max(1,min(4,lvl-1))` e `max(1,min(lvl+5,12))` minutos.
Luta: sorteia uma faixa de energia final de referência com pessoal 50%:
50% entre 3–6, 30% entre 1–3 e 20% entre 6–9; sorteio uniforme dentro da faixa.
Energia base e minutos base são o dobro desse valor (2–18).
Em ambos os tipos, **energia base = duração base em minutos**. Esta relação é
balanceamento local para os rácios de XP/energia acordados; substitui a energia
independente das lutas e os aumentos antigos de duração/energia das missões normais.

### XP (`QuestRewards::missionXp`)

```
xp = round(segundos_base × 1.5 × perfil × (1 + expansão_xp × 0.02) × variação × dificuldade)
perfil = 1 (equilibrada) ou 1.4 (focada)
variação = 80% uniforme [0.90,1.10]; 15% [0.80,0.90]; 5% [1.10,1.25]
```

Expansão limitada a 0–50: nível 1 dá +2%, 25 dá +50%, 50 dá +100%.
Dificuldade no XP: tempo/fácil ×1, média ×1.2, difícil ×1.3.
Nas lutas, o sorteio mantém 50% fácil, 30% média e 20% difícil.
Este bónus é balanceamento local e aplica-se a equilibradas e focadas. A variação é sorteada uma vez e o prémio fica
persistido. Os fatores 1.5/1/1.4 são da função do cliente/constantes; distribuição,
relação duração/energia e seleção de focadas são regras locais, não o gerador oficial.

Com expansão 50/50 e energético pessoal 50%, a focada tem referência de
**504 XP/energia numa luta fácil ou missão de tempo**, faixa habitual 453.6–554.4 e média teórica 497.07.
O arredondamento e mínimo de uma energia alteram este rácio nas missões pequenas.

### Moedas (`QuestRewards::rollCoinsByGuildStat`)

```
guildStat    = stat_quest_game_currency_reward_boost (0-50)
guildFact    = 1 + guildStat × 0.031428      // gstat=50 → ×2.5714
scaleFactor  = max(1, (lvl/300)²)            // cúbico acima de lvl 300
base         = 113 × lvl × scaleFactor
perEnergy    = base × guildFact / 7.5        // 7.5 = energia média do pool
variance     = rand(0.85, 1.15)

coins = perEnergy × energy × variance × diffScale
```

Dificuldade (missões de luta):
```
DIFF_NONE=1.0  DIFF_EASY=1.0  DIFF_MEDIUM=1.3  DIFF_HARD=1.5
```

O clamp `QuestRewards::expectedCoinsRange` garante que os valores ficam dentro de ±15%.

**Referência visual:** `docs/missoes/comparacao_moedas.html`

---

## Energéticos de missão

Aplicados **na geração**, usando o relógio `GameTime` para expiração:

- Pessoal (`booster_quest1/2/3`): 10/25/50%, reduz duração e energia.
- Equipa (`guild_booster_quest1/2`): 10/20%, reduz apenas duração.

```
energia_final = max(1, round(energia_base × (1 − pessoal/100)))
duração_final = max(60, round(segundos_base × (1 − (pessoal + equipa)/100)))
```

`duration_raw` conserva a duração base; `duration` e `energy_cost` guardam custos
finais. `startQuest` utiliza esses valores sem reaplicar energéticos. XP e moedas
não mudam com os energéticos. Comprar/expirar um energético ou alterar a expansão
não recalcula missões já geradas; para testar, usar «novas missões».
Exemplo: 10 minutos/10 energia base, pessoal 50% e equipa 20% → 3 minutos/5 energia.

Validação offline e PDO simulado, sem modificar a BD:

```bash
php scripts/testing/test_quest_generation.php
```

Inclui frequência de focadas e variação (20 000 sorteios), combinações de
expansões/energéticos, expiração e reposição mantendo uma luta existente.
A integração MySQL deve ser confirmada separadamente. Teste opcional com renovação
em todas as zonas dentro de transação, sempre revertida (não aplicar no mesmo
herói enquanto este joga):

```bash
php scripts/testing/test_quest_generation.php --mysql --hero=NomeDoHerói
```

---

## Patamares de energia gasta

Prémio aleatório diário aos 100/200/300 de energia das missões concluídas
e resgatadas. O contador avança ao recolher as recompensas da missão;
iniciar ou cancelar não conta. Missões que atravessam a meia-noite contam
no dia do resgate.
Cada resgate dá 1 ponto de temporada; energia e treinos são cupões.
Catálogo, protocolo, mudança de dia e testes em [patamares_energia.md](patamares_energia.md).

## Energia de missão — ciclo diário

### Reset à meia-noite (`Character::syncDailyReset`)

Chamado em `autoLoginUser.php` antes dos overlays, uma vez por UTC-midnight.

Referência de data: coluna `ts_last_quest_energy_refill` (não `ts_last_action`, que é actualizada pelo GameController antes do dispatch — BUG-000088).

```
todayMidnight = now - (now % 86400)   // UTC midnight
se ts_last_quest_energy_refill < todayMidnight:
    newStorage = min(100, current_energy_storage + quest_energy)
    quest_energy                     = effectiveMax
    current_energy_storage           = newStorage
    quest_energy_refill_amount_today = 0
    slotmachine_spin_count           = 0
    ts_last_quest_energy_refill      = now
```

`effectiveMax` inclui bónus de troféus de guilda (`GuildArtifacts::effectiveMax`).

### Depósito de energia (`current_energy_storage`)

- Máximo: **100 energias**
- Energia sobrante no reset vai para o depósito (até ao cap)
- Usar: action `useEnergyStorage` (`energyTraining.php`)
- Comprar: action `buyEnergyStorage` — +50 por **2 donuts** (máximo 2 compras = 100)

### Recargas manuais durante o dia (`buyQuestEnergy`)

- Acção: `buyQuestEnergy` (`energyTraining.php`)
- Cada recarga: **+25 energias**
- Limite diário: **4 recargas** (100 energias no total, contador `quest_energy_refill_amount_today`)
- Custo:
  - **2 donuts** (via `use_premium=true`)
  - **Moedas** (via `use_premium=false`): custo cresce por tier
    ```
    tier = min(recargas_hoje / 25, 3)   // 0,1,2,3
    factor = [1800, 6300, 10800, 15300][tier]
    custo  = round(factor × gameCurrencyPerTime(lvl))
    gameCurrencyPerTime(lvl) = 0.02 + 0.01 × (0.35 × lvl)^1.55
    ```
- O cliente exibe "Possível X hoje" como `(100 - quest_energy_refill_amount_today) / 25`

Constantes enviadas ao cliente (`balancing_constants.json`):
```json
"quest_energy_refill_amount": 25,
"quest_max_refill_amount_per_day": 100
```

---

## Dificuldade das missões de luta

Gerada em `QuestRewards::rollDifficulty()`:
```
rand(1,100) ≤ 50  → EASY   (50%)
rand(1,100) ≤ 80  → MEDIUM (30%)
else              → HARD   (20%)
```

---

## Histórias das zonas (Story Dungeon)

Objetivos sequenciais de cinco etapas por história, apresentados no painel de
missões: cumprir metas de atividades, resgatar XP/equipamento e avançar para a
etapa seguinte. Progresso, prémios, custo premium e chefe foram implementados no
backend; ver [histórias das zonas](historias.md) para o protocolo, testes e limites
(missão especial da história 7 e repetições).

---

## Chamadas heroicas / Chamadas de emergência (Herobook)

São objetivos adicionais acessíveis pelo painel de missões, não uma quarta
missão do pool da cidade. O cliente chama ao sistema `herobook`; o catálogo
oficial em `constants_json.data` contém 53 definições de objetivos.

### Geração e progresso

- Disponíveis a partir do nível 40; apresentação antecipada a partir do 25.
- A implementação gera três chamadas diárias e duas semanais. A escolha respeita
  nível, duração e tipos que já têm atividades ligadas no servidor.
- Diárias renovam à meia-noite e semanais à segunda-feira, no calendário do
  jogo (`GameTime` e fuso de `Live`). Geração/renovação acontece ao carregar a conta.
- Cada objetivo guarda identificador, meta, progresso, prazo, vaga e prémio
  na tabela existente `herobook_objectives`; não foi necessário alterar colunas.
- Contam missões resgatadas, missões de tempo resgatadas, energia debitada
  ao iniciar, duelos resgatados, lavagens, renovações da loja e atributos pagos.
  Pontos de atributos gratuitos não contam como compra.
- Progresso não ultrapassa a meta. Objetivos incompletos expirados ficam
  abortados; concluídos ainda não resgatados são preservados.

Estados: 1 em curso, 2 pronto a resgatar, 3 resgatado, 4 abortado.
Preencher a meta não entrega o prémio automaticamente: é preciso resgatar.

### Prémios e ligação ao passe

O sorteio usa os pesos `chance` de `herobook_daily_rewards` ou
`herobook_weekly_rewards`. Pode dar XP, moedas, donuts, pontos gratuitos,
equipamento, energia de missão, treino, zenite ou supercola — não apenas XP.
Quantidades fixas e bónus de equipamento vêm do catálogo. Fórmulas dos valores
escalados, seleção das metas e layout 3+2 são balanceamento local; não estão
validados como reprodução exata do backend oficial.

Equipamento é gerado como preview e entra na mochila no resgate; mochila cheia
não perde a chamada. Energia, unidades de treino, zenit e supercola ficam em
[cupões das mensagens](../cupoes/mensagens.md) para resgate posterior.
Troca, descarte e expiração limpam previews não entregues. Chamadas existentes
mantêm o prémio que já foi mostrado.

Além do prémio, resgatar dá pontos à temporada ativa segundo `season_locations`:
**1 ponto por diária e 5 por semanal** em todos os 80 temas (localizações 14 e 13).
É regra local nos temas antigos `beetle`/`bear`, que tinham zero nas constantes
originais; os restantes já tinham esses valores. Cliente e resgate usam a mesma
fatia de constantes, corrigindo a regressão BUG-000144 após a rotação.
Os pontos chegam ao resgatar, não ao terminar a barra ou pagar a conclusão.
Não há duplicação em repetição nem compensação automática de resgates antigos.
Descarte explícito não entrega prémio nem pontos de temporada.

### Ações, protocolo e segurança

| Ação | Resultado |
|---|---|
| `collectHerobookReward` | Entrega prémio/pontos e marca resgatada |
| `instantFinishHerobookObjective` | Cobra 9 donuts (diária) ou 19 (semanal), deixa pronta a resgatar |
| `renewHerobookObjective` | Cobra 1 donut e troca um objetivo em curso por outro da mesma vaga/duração |

As operações validam propriedade, estado e saldo, usando transação/locks e
savepoint quando necessário. Prémio, pontos da season e marcação do resgate
são atómicos. O login e as respostas parciais incluem objetivos e itens de
preview; as constantes também são enviadas ao cliente.

### Validação e limites

O utilizador confirmou o funcionamento após corrigir os prémios e os pontos
da temporada. Isto não confirma individualmente todas as categorias de prémio.
Testes offline e integração MySQL com rollback passaram, incluindo resgates
em `beetle`/`bear`; confirmação visual da regressão BUG-000144 pendente.

```bash
php scripts/testing/test_herobook.php --offline
php scripts/testing/test_herobook.php
```

O segundo exige MySQL e usa rollback. Restam os outros tipos de atividade,
o bónus diário por três chamadas e a confirmação das fórmulas/limites oficiais.
Ver [documentação detalhada](chamadas_emergencia.md) e BUG-000128.

---

## Bugs relevantes

| Bug | Sintoma | Ficheiro |
|-----|---------|----------|
| BUG-000008 | Insta-skip de missão de luta não mostrava a batalha | `instantFinishQuest.php` |
| BUG-000009 | XP das missões praticamente parado (fórmula antiga linear sem guilda) | `QuestRewards.php` / `Character.php::insertQuestPool` |
| BUG-000012 | Missões sempre as mesmas (pool hardcoded com 3 templates) | `Character.php::questCatalog` |
| BUG-000013 | Bónus da guilda (moedas e XP de missão) nunca aplicados | `Character.php::insertQuestPool` |
| BUG-000014 | Energéticos (boosters pessoais) não aplicavam nada | `Character.php` / `energyTraining.php` |
| BUG-000016 | Missões trocavam ao andar entre cidades (pool partilhado errado) | `Character.php::insertQuestPool` |
| BUG-000017 | Jogador ganhava SEMPRE nas missões de luta (resultado fixo) | `Character.php::claimQuest` |
| BUG-000019 | Perder a luta da missão mostrava a recompensa CHEIA | `Character.php::claimQuestRewards` |
| BUG-000039 | Estender energético de herói perdia o tempo restante (reset em vez de somar) | `energyTraining.php` |
| BUG-000072 | Relogin com missão de luta pendente crashava o cliente (`battles` vazio) | `autoLoginUser.php` |
| BUG-000077 | XP/nível do sidekick chegava tarde ao cliente após concluir missão | `Character.php::claimQuestRewards` |
| BUG-000087 | Energético aplicado ao *iniciar* em vez de ao *gerar* | `Character.php::insertQuestPool` |
| BUG-000088 | `syncDailyReset` nunca disparava (usava `ts_last_action` em vez de `ts_last_quest_energy_refill`) | `Character.php::syncDailyReset` |
| BUG-000089 | `buyQuestEnergy` com moedas falhava (`UPDATE user` em vez de `UPDATE character`) | `energyTraining.php` |
| BUG-000090 | Clamp de moedas usava energia boosted → prémios sempre fixos no máximo | `Character.php::insertQuestPool` |
| BUG-000128 | Chamadas heroicas bloqueadas/sem lógica; versão inicial só dava XP e não avançava a season | `Herobook.php` / `herobook.php` / `Live.php` |

### Distribuição de energia de combate

As percentagens são os pesos das faixas sorteadas, não quotas exatas em cada
conjunto. Os limites 3 e 6 sobrepõem-se: com energético pessoal 50%, a
probabilidade final de 3 energias é 22.5% e de 6 é 17.5%. Assim, o intervalo
observado 3–6 reúne 65% dos resultados, incluindo os limites vindos das outras
faixas. Sem energético, os mesmos custos base variam de 2 a 18; com 25%,
variam de 2 a 14 por arredondamento. O energético de equipa não altera energia.
A regra aplica-se a combates equilibrados e focados; tempos não foram alterados.

## Dias de Bónus (2026-10-04)

Eventos tipo 7/8 multiplicam XP/moedas no resgate da missão normal. Os prémios
persistidos mantêm a base, porque o cliente multiplica a pré-visualização.
Tipo 1 multiplica a energia máxima diária, com concessão única do acréscimo.
O reset usa a data local de GameTime e marcador independente em
`daily_bonus_resources`; acontece também em `accountState` e antes de iniciar
uma missão. Ver [login diário e bónus](../logins/login_diario_e_bonus.md).


### Estado de missões no login (2026-10-21)

`loginUser` entrega as coleções reais de `Live::accountState`, incluindo missões,
inventário e itens, em vez de reutilizar IDs do template. Ao receber
`errStartQuestInvalidQuest`, o executor dinâmico exige sincronização antes de
voltar a agir e não contabiliza energia nessa tentativa rejeitada.
Validação: lint PHP e testes Python offline; confirmação com bots reais pendente.
