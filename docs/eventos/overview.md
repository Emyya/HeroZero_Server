# Eventos — catálogo, protocolo e estado de implementação

Análise local em **2026-10-05**. Este documento descreve os assets e o código presentes no repositório; não afirma que os eventos estejam ativos no servidor oficial. O levantamento inicial foi seguido por implementação local e testes MySQL com rollback. O estado atual e as regras locais estão em [implementacao.md](implementacao.md).

## 1. Âmbito e fontes

A família principal é `event_quests`: eventos de coleção/com objetivos, incluindo Natal, Halloween, Carnaval e Olimpíadas. Existem **35 definições**. O cliente também suporta famílias distintas: sorteios, tesouros, objetos escondidos, torres e vilões. As temporadas com passe pertencem a `seasons` e não são sinónimo de `event_quests`.

Fontes primárias:

- [Constantes da raiz](../../assets/data/constants_json.data): zlib + JSON.
- [Constantes públicas](../../server-laravel/public/assets/data/constants_json.data): verificar equivalência antes de implementar.
- [JavaScript do cliente](../../server-laravel/public/js/HeroZero.min.js): leitura direta do bundle local.
- [Textos pt_BR servidos](../../server-laravel/public/assets/i18n/pt_BR/text.data): zlib + JSON; os nomes deste catálogo preservam o texto do jogo.
- [Router](../../server-laravel/app/HeroZero/Router.php), [Character](../../server-laravel/app/HeroZero/Character.php), [Live](../../server-laravel/app/HeroZero/Live.php) e [schema inicial](../../server-laravel/docker/initdb/01_schema.sql).

Documentação complementar: [protocolo](../PROTOCOL.md), [schema de respostas](../RESPONSE_SCHEMA.md), [temporadas](../seasons/Overview.md), [vilões](../vilao%20-%20worldboss/worldboss.md), [casino](../casino/README.md), [pets](../sidekicks/sidekicks_implementados.md).

### Evidência e limites

- **Dados dos assets**: identificadores, metas, tipos numéricos, fatores, referências, datas e cores são transcritos das constantes.
- **Comportamento do cliente**: ações, campos, estados e fórmulas abaixo foram encontrados no JavaScript.
- **Implementação local**: o estado atual e resultados de integração estão descritos em [implementacao.md](implementacao.md).
- **Decisões pendentes**: calendário real, recorrência e algumas regras de atribuição exigem implementação local ou capturas oficiais. Não deduzir regras só a partir dos nomes.
- Os enums de recompensas variam entre famílias; não reutilizar um número de `event_quests` como se tivesse o mesmo significado em sorteios, tesouros ou objetos escondidos.

### Identificação dos ficheiros analisados

- `assets/data/constants_json.data` — SHA-256 `eee7c3228c00485df032262eb7173081eed339fbda4739cf6e858ccf1f0adad2`.
- `server-laravel/public/js/HeroZero.min.js` — SHA-256 `667e02b70b87145cc7b03fb061aec53c7cab0a020863173bf6dd5395d5d51035`.
- `server-laravel/public/assets/i18n/pt_BR/text.data` — SHA-256 `7de7db08c43c057a51b87f52262c12647f09da05a6cd741098d5a9b9a6ecadfa`.

Constantes públicas: iguais byte a byte à raiz.

## 2. Índice dos 35 eventos

| # | Nome no jogo | Identificador | Objetivos |
| --- | --- | --- | --- |
| 1 | Semana de verão dupla | `summer_event` | 5 |
| 2 | A festa de Halloween | `halloween` | 6 |
| 3 | O Natal está cancelado! | `xmas_event_1` | 5 |
| 4 | Papai Noel em apuros | `xmas_event_2` | 5 |
| 5 | Jogos de Inverno da Yoyodyne | `winterolympics_event` | 5 |
| 6 | O desfile de carnaval | `carnival_event` | 6 |
| 7 | Coelho da Páscoa em apuros | `easter` | 5 |
| 8 | Vai ligar pra quem? | `ghostbusters_event` | 5 |
| 9 | Vamos ao estádio! | `worldcup_event_1` | 5 |
| 10 | A fiiinaaalll! Uuuuuhuuuuu! | `worldcup_event_2` | 5 |
| 11 | A festa de comemoração | `birthday_event` | 5 |
| 12 | Oktoberfest em Humphreydale | `octoberfest` | 5 |
| 13 | O Festival de Esportes de Verão | `olympia_2016_rio` | 5 |
| 14 | A Convenção de Heróis de Ficção Científica | `sci_fi_week` | 4 |
| 15 | A Conspiração do Lixo | `environmental_week` | 5 |
| 16 | A LAN Party | `video_games_week` | 5 |
| 17 | Um Dia no Museu | `server_launch_primal` | 5 |
| 18 | Alienígenas em Humphreydale | `server_launch_utopia` | 5 |
| 19 | Apocalipse Zumbi em Humphreydale | `server_launch_dystopia` | 5 |
| 20 | Expedição a Atlantice | `server_launch_2023_exotic_places` | 5 |
| 21 | O Jardim Botânico | `server_launch_2023_blooming_nature` | 5 |
| 22 | Campeonato Heroico Internacional | `server_launch_2023_scorching_heat` | 5 |
| 23 | Aventuras fantásticas | `server_launch_2023_fantastic_adventures` | 5 |
| 24 | Pão e Circos | `server_launch_2024_mythical_gods` | 5 |
| 25 | Caos Virtual | `server_launch_2024_tech` | 5 |
| 26 | A Corrida de Carrinhos de Rolimã Solares | `sun_moon_stars_season_arc_event_2024` | 5 |
| 27 | Pânico do RPG de Mesa | `doom_and_darkness_season_arc_event_2024` | 5 |
| 28 | Magmântida Perdida | `server_launch_2025_arctic_exploration` | 5 |
| 29 | A Conspiração do Chocolate | `server_launch_2025_mayan_empire` | 5 |
| 30 | O Festival do Solstício de Verão | `midsummer` | 5 |
| 31 | A receita secreta | `server_launch_2025_heroic_feast_appetizer` | 6 |
| 32 | Surto das feras blindadas | `server_launch_2025_october` | 5 |
| 33 | Salve a Zeronita! | `server_launch_2026_overgrown` | 5 |
| 34 | O festival de cinema retrô | `server_launch_2026_gangsta1` | 5 |
| 35 | A Cruzada dos Cubos | `server_launch_2026_cube` | 5 |

## 3. Estrutura e regras observadas

### Calendário e elegibilidade

`start_date` e `end_date` fazem parte dos templates. Nas fichas abaixo, conservar os valores exatos: datas antigas não constituem um calendário sazonal atual. O servidor terá de escolher as janelas ativas e fornecer o estado correspondente. Não ativar automaticamente todos os templates nem interpretar estes intervalos como recorrência anual.

O nível mínimo de `event_quests` é **5** (`event_quest_min_level`). O cliente verifica este limite ao mostrar convites; o backend deverá validar também. A disponibilidade em `Ae.get_isAvailable` depende de `end_date`: a diferença entre o timestamp final e o relógio do cliente é limitada a zero. Usar `GameTime` e o fuso/calendário escolhido no servidor.

### Objetivos

Cada objetivo tem `index`, `icon_asset`, `type`, `reference`, `value`, `reward_type`, `reward_factor` e `reward_reference`. O índice associa a definição ao contador `objectiveN_value`. O identificador completo do objetivo é enviado nas ações de resgate e conclusão instantânea.

Os significados abaixo são apoiados pelos identificadores dos objetivos e pelo comportamento visual do cliente. Não constituem uma enumeração completa de todos os valores possíveis do protocolo:

| Tipo | Interpretação/evidência | Exemplo real |
| --- | --- | --- |
| 1 | Missões de tempo concluídas — identificadores `timequest_completed` | `worldcup_event_1_timequest_completed` |
| 2 | Missões de combate ganhas | `summer_event_fightquest_won` |
| 4 | Energia utilizada | `easter_energy_used` |
| 5 | Unidades motivacionais utilizadas — confirmado nos textos; debitadas ao iniciar treino | `summer_event_training_completed` |
| 6 | Vilões derrotados | `halloween_worldboss_defeated` |
| 7 | Objetos de evento recolhidos | `summer_event_eventitem_autograph_collected` |
| 8 | Missões com referência temática | `halloween_quest_completed` |
| 9 | Duelos realizados | `summer_event_duels_fought` |
| 10 | Utilização da máquina de lavar | `summer_event_washing_machine_used` |
| 11 | Renovações da loja | `carnival_event_shop_refreshed` |

`reference` deve conservar o formato original: pode ser vazio, `all`, um identificador de objeto/vilão ou um padrão como `*_halloween_*`. A semântica exata de matching e quais ações contam devem ser verificadas antes da implementação.

### Contadores e resgate: contrato importante

Em `EventQuestObjective` (`il`):

- `get_isCollectable`: **contador == meta**.
- `get_isCollected`: **contador > meta**.
- `get_currentValue`: lê `objectiveN_value` no evento; se o campo faltar, devolve zero.
- O progresso visual é `min(contador / meta, 1)` e o texto limita o contador à meta.

Logo, deixar um contador ultrapassar naturalmente a meta pode fazer o cliente interpretar o objetivo como já resgatado. A implementação deverá limitar o progresso e marcar o resgate deliberadamente. Usar `meta + 1` é uma opção compatível com estas comparações, mas não foi confirmado como representação exata do servidor oficial por uma captura nesta análise.

`Ae.isCompleted(false)` exige que todos os objetivos estejam prontos ou resgatados; `Ae.isCompleted(true)` exige todos resgatados. Não confundir concluir os objetivos, resgatar cada prémio e reclamar o prémio final.

### Estados do evento

| Valor | Leitura explícita no cliente |
| --- | --- |
| 1 | `get_isStarted` |
| 2 | `get_isFinished` |
| 3 | `get_isAborted` |
| 5 | `get_isAbortedButFinished` |

O estado 4 não foi identificado para `Ae` nesta análise; não importar o significado observado noutra família. Um convite é considerado não atribuído quando **não contém a chave `id`** (`get_isUnassigned`). `id: 0` não é equivalente à ausência.

### Recompensas individuais e fórmulas visíveis

`Q.getEventQuestObjectiveRewardAmount(type, factor, level)` contém:

| Tipo do objetivo (`reward_type`) | Recompensa | Cálculo visível |
| --- | --- | --- |
| 1 | Moedas | `round(round(gameCurrencyPerTime(level) × percentage_base × factor × time) + level^exp)` |
| 2 | Donuts | `premium_base × round(factor)` |
| 3 | Pontos de atributos | `stat_points_base × round(factor)` |
| 4 | XP | `round((xp(level+1) − xp(level)) × xp_percentage_base × factor)`; zero no nível máximo |
| 5 | Item | O cliente usa o item recebido por ID ou `reward_reference` para apresentar o prémio |
| 6 | Alteração visual | O cliente usa a alteração visual associada ao prémio |

`round` nesta tabela representa o `Math.round` do JavaScript. Preservar essa semântica na implementação PHP, sobretudo se surgirem valores negativos. `gameCurrencyPerTime` é outra função do cliente; esta documentação não substitui a análise dessa dependência.

Os campos finais `reward1_type`, `reward2_type` e `reward_itemN_type` são guardados em bruto nas fichas. **Não aplicar automaticamente o enum de prémios individuais a estes campos.** Os fatores não são necessariamente quantidades diretas nem indicam por si só a qualidade final de um item.

A interface também prepara uma recompensa de pontos de temporada via `ea.fromSeasonPoints(2, seasonProgress)` ao mostrar objetivos. A atribuição efetiva e as condições devem ser verificadas no sistema de temporadas; a apresentação no cliente não prova entrega pelo backend.

### Conclusão instantânea

O custo apresentado é `ceil((1 − progresso) × event_quest_objective_instant_finish_amount)`, com progresso limitado a 1. A constante atual é **29**. O servidor deve recalcular o custo com o estado bloqueado, validar saldo e impedir débito duplicado; a ação só envia o identificador do objetivo.

### Objetos de evento

`event_items` define objetos como `halloween_pumpkin`, `xmas_lights` e `carnival_sweets`, com `reward_type`, `reward_chance` e `reward_reference`. Alguns têm `reward_chance: 0.1`; não assumir sem análise adicional que todos os campos `chance` noutras famílias são probabilidades no intervalo 0–1. Os índices de sorteio, por exemplo, têm valores superiores a 100.

## 4. Protocolo e interface do cliente

O transporte usa `POST /request.php` e envelope `{data, error}`. Os parâmetros específicos observados são:

| Ação | Parâmetros específicos | Finalidade |
| --- | --- | --- |
| assignEventQuest | `event_quest_identifier` | Aceitar o convite |
| claimEventQuestObjectiveRewards | `objective_identifier`, `discard_item` | Resgatar um objetivo |
| instantFinishEventQuestObjective | `objective_identifier` | Concluir um objetivo pagando o custo |
| claimEventQuestRewards | `reward_item_id`, `discard_item` | Recolher prémios finais e escolher/descartar item |

`discard_item` é enviado como booleano no código do cliente. `reward_item_id` é um ID de instância, não o identificador do template. Preservar os tipos e validar propriedade do item.

### Estado e resposta

- `data.event_quest`: objeto do evento/convidado, atualizado pelo cliente.
- `character.event_quest_id`: liga o personagem ao evento atribuído; `Y.get_eventQuest` procura um objeto com esse ID.
- `DOEventQuest` (`Gr`): `id`, `identifier`, `status`, `rewards`, `reward_item1_id`, `reward_item1_rewards`, `reward_item2_id`, `reward_item2_rewards`, `reward_item3_id`, `reward_item3_rewards`.
- `Ae` lê adicionalmente `end_date`, `objectiveN_value`, `objectiveN_reward_item_id` e, quando presente, `worldboss_start_times`.
- `worldboss_start_times` é uma coleção; `DOEventTimes` lê `startDateTime`, `endDateTime` e `identifier`.
- Objetivos que entregam itens resolvem os IDs pela coleção de itens do personagem. Enviar os objetos referenciados antes de o cliente desenhar os prémios.
- Verificar login, `Live::accountState` e respostas parciais: devolver apenas o ID sem o objeto associado pode quebrar a interface.

### Erros observados

O diálogo de aceitação trata `errAssignEventQuestAlreadyAssigned` e `errCreateInvalidEventQuestIdentifier`. O resgate final trata `errClaimEventQuestRewardsNoEventQuest` e `errInventoryNoEmptySlot`; o resgate de objetivo trata também `errFinishObjectiveAlreadyCollected`. Estes exemplos não constituem catálogo completo de erros; verificar os handlers específicos do cliente antes de escolher códigos adicionais.

### Traduções e imagens

Textos do evento:

- `event_quest/<identifier>/name`
- `event_quest/<identifier>/caller`
- `event_quest/<identifier>/intro_description`
- `event_quest/<identifier>/outro_win_description`
- `event_quest/<identifier>/outro_lose_description`

Textos de objetivo: `event_quest/objective/<objective_identifier>/{name,progress,description}`. O cliente substitui `<target_value>` e `<progress_value>` e suporta plurais; conservar os placeholders.

Imagens: `event_quests/<identifier>_logo.webp`, `<identifier>_i.webp`, `<identifier>_header.webp` (o getter do header passa a variante `v2`) e `event_quest_objectives/<icon_asset>_i.webp`. Cores vêm das constantes.

As fontes dos pets usam `sidekick_source/<source>`; a existência de uma fonte no catálogo de pets não prova que o evento esteja implementado. Após atualizar traduções, verificar a versão/cache dos assets: uma sessão com textos antigos pode apresentar a chave literal, como foi confirmado no diagnóstico de `pets.png`/`pets.har` em 2026-10-05.

## 5. Estado atual do backend

Os 35 eventos de `event_quests` usam agora o motor `EventQuests.php`: calendário por ocorrência, aceitação, contadores, previews, conclusão premium e resgates individuais/finais. Integra-se com missões, energia, treino, duelos, loja, lavagem, vilões, login e respostas parciais. Ver [implementação, regras locais e scripts](implementacao.md).

A tabela antiga `event_quests` e os seus dados são preservados. O novo progresso fica em `event_quest_progress`; o cliente continua a receber `character.event_quest_id` e `data.event_quest`. `assignEventQuest` já não grava um ID fixo. Os três resgates/conclusão já não usam `stateEcho.php`.

### Famílias associadas

- A Pirâmide da Sorte tem motor próprio para os 55 pools, com sorteios sem reposição, custos progressivos e resgates. Ver [catálogo](event_draws_overview.md) e [implementação](event_draws_implementacao.md). Objetos escondidos e as ações de torre inventariadas continuam com handlers genéricos.
- Caça às Toupeiras: os 47 templates `event_treasures` têm agora motor próprio, calendário global, tabuleiros/pistas, pás e resgates; reset e salto de desbloqueio já são reais. Ver [catálogo](event_treasures_overview.md) e [implementação](event_treasures_implementacao.md).
- Vilões mantêm o motor próprio e agora avançam o objetivo de coleção ao resgatar o prémio de um vilão derrotado com participação do herói.
- O hub preserva o casino e inclui os eventos de coleção, a Caça às Toupeiras e a Pirâmide da Sorte.

As questões e tarefas do levantamento inicial nas secções 10/11 devem ser lidas juntamente com o estado atual: parte desse trabalho já foi realizada, e as decisões não oficiais foram documentadas expressamente.

## 6. Fichas completas dos eventos

Todas as fichas preservam índices, enums, fatores e referências originais. Os nomes dos objetivos são textos pt_BR; as metas vêm das constantes. O JSON no fim de cada ficha permite consultar os campos sem perder tipos ou valores.

### 6.1. Semana de verão dupla

Identificador: `summer_event`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E7FF51` / `#FF81EF`.

**Contacto:** Beto, o presidente do clube

**Introdução:** “Nós estamos muito felizes com a sua visita na reunião do nosso fã-clube prevista para esta semana. Afinal, somos o seu primeiro e, se me permite a observação, o seu único fã-clube. Estamos muito ansiosos para ouvir as suas estórias e receber os cartões autografados prometidos. Até breve.”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Vontade de agir<br>`summer_event_duels_fought` | 9 | `` | 225 | 4 / 1 / `` |
| 2 | Recém-lavado<br>`summer_event_washing_machine_used` | 10 | `` | 8 | 3 / 1 / `` |
| 3 | Cartões autografados perdidos<br>`summer_event_eventitem_autograph_collected` | 7 | `summer_event_autograph` | 45 | 4 / 1 / `` |
| 4 | Forte como um herói<br>`summer_event_training_completed` | 5 | `all` | 90 | 3 / 1 / `` |
| 5 | Estórias de heróis<br>`summer_event_fightquest_won` | 2 | `all` | 120 | 3 / 1 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `belt_bathingring3` |
| reward_item2 | 7 | 20 | `weapon_water_pistol1` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Uau, que sucesso! Você esteve aqui e todos os 30 membros do clube também. Suas estórias... Incrível! Inacreditável! Já estamos ansiosos aguardando a sua próxima visita.”

**Texto de fim sem sucesso:** “Pena, que desta vez não tenha dado certo. Certamente você estava ocupado em prender malfeitores e ladrões malvados. Talvez você passe aqui na próxima reunião do clube...”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E7FF51",
  "background_color_bottom": "#FF81EF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "belt_bathingring3",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "weapon_water_pistol1",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "summer_event_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 225,
      "reward_type": 4,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "summer_event_washing_machine_used": {
      "index": 2,
      "icon_asset": "washing_machine_used",
      "type": 10,
      "reference": "",
      "value": 8,
      "reward_type": 3,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "summer_event_eventitem_autograph_collected": {
      "index": 3,
      "icon_asset": "summer_event_eventitem_autograph_collected",
      "type": 7,
      "reference": "summer_event_autograph",
      "value": 45,
      "reward_type": 4,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "summer_event_training_completed": {
      "index": 4,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 90,
      "reward_type": 3,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "summer_event_fightquest_won": {
      "index": 5,
      "icon_asset": "summer_event_fightquest_won",
      "type": 2,
      "reference": "all",
      "value": 120,
      "reward_type": 3,
      "reward_factor": 1,
      "reward_reference": ""
    }
  }
}
```

### 6.2. A festa de Halloween

Identificador: `halloween`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFD7AF` / `#FF8124`.

**Contacto:** O pequeno Toni pede ajuda

**Introdução:** “Oh não, a minha festa de Halloween corre o risco de ser cancelada! O fornecedor de acessórios e doces enviou o meu pedido ao endereço errado. Você pode me ajudar a substituir a decoração da festa perdida?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Histórias de assombração<br>`halloween_quest_completed` | 8 | `*_halloween_*` | 80 | 4 / 0.4 / `` |
| 2 | Monstro da abóbora<br>`halloween_eventitem_pumpkin_collected` | 7 | `halloween_pumpkin` | 13 | 3 / 0.5 / `` |
| 3 | Morcegos de borracha<br>`halloween_eventitem_bat_collected` | 7 | `halloween_bat` | 20 | 4 / 0.5 / `` |
| 4 | Balas de mascar azedas<br>`halloween_eventitem_candy1_collected` | 7 | `halloween_candy1` | 13 | 3 / 0.5 / `` |
| 5 | Bomboms doces<br>`halloween_eventitem_candy2_collected` | 7 | `halloween_candy2` | 13 | 1 / 0.3 / `` |
| 6 | Zumbicalipse<br>`halloween_worldboss_defeated` | 6 | `halloween_event_stage2` | 3 | 4 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `weapon_halloween3` |
| reward_item2 | 7 | 20 | `mask_halloween3` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Demais! Você salvou a minha festa de Halloween! Agora eu só preciso de uma ajudazinha na decoração...”

**Texto de fim sem sucesso:** “Que pena que você nao pôde encontrar todas as coisas, porque você teve que se preparar para o campeonato de esculpir abóboras assustadoras. Ainda bem que eu ainda tenho algumas aranhas de plástico e doces do ano retrasado...”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFD7AF",
  "background_color_bottom": "#FF8124",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "weapon_halloween3",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "mask_halloween3",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "halloween_quest_completed": {
      "index": 1,
      "icon_asset": "halloween_quest_completed",
      "type": 8,
      "reference": "*_halloween_*",
      "value": 80,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "halloween_eventitem_pumpkin_collected": {
      "index": 2,
      "icon_asset": "halloween_eventitem_pumpkin_collected",
      "type": 7,
      "reference": "halloween_pumpkin",
      "value": 13,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "halloween_eventitem_bat_collected": {
      "index": 3,
      "icon_asset": "halloween_eventitem_bat_collected",
      "type": 7,
      "reference": "halloween_bat",
      "value": 20,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "halloween_eventitem_candy1_collected": {
      "index": 4,
      "icon_asset": "halloween_eventitem_candy1_collected",
      "type": 7,
      "reference": "halloween_candy1",
      "value": 13,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "halloween_eventitem_candy2_collected": {
      "index": 5,
      "icon_asset": "halloween_eventitem_candy2_collected",
      "type": 7,
      "reference": "halloween_candy2",
      "value": 13,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "halloween_worldboss_defeated": {
      "index": 6,
      "icon_asset": "halloween_worldboss_defeated",
      "type": 6,
      "reference": "halloween_event_stage2",
      "value": 3,
      "reward_type": 4,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.3. O Natal está cancelado!

Identificador: `xmas_event_1`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Mary

**Introdução:** “Socooooorro! Por favor, me ajude! Olhe para isso, toda a decoração natalina sumiu! Não há velas, correntes de luzes pisca-pisca e nenhum docinho... Esse cara verde e malvado roubou tudo! Você pode me ajudar a recuperar todas as coisas? Por favor!”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Correntes de luzes pisca-pisca<br>`xmas1_event_eventitem_lights` | 7 | `xmas_lights` | 25 | 4 / 0.4 / `` |
| 2 | Bolo de mel<br>`xmas1_event_eventitem_gingerbread` | 7 | `xmas_gingerbread` | 25 | 1 / 0.3 / `` |
| 3 | O Sabotador do Natal<br>`xmas1_event_worldboss_defeated` | 6 | `xmas_event_stage2` | 2 | 4 / 0.5 / `` |
| 4 | O Mal<br>`xmas1_event_fightquest_won` | 2 | `all` | 70 | 3 / 0.5 / `` |
| 5 | Arrumadinho<br>`xmas1_event_washing_machine_used` | 10 | `` | 7 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `mask_xmas_setitem_mask5` |
| reward_item2 | 7 | 20 | `mask_xmas_winterhat` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Olhe, como tudo voltou a brilhar! Isto não é bonito? Agora o Natal pode chegar. Muito obrigada! Você também vai me dar uma boneca de presente? É o meu pedido de Natal!”

**Texto de fim sem sucesso:** Oh, o Sabotador do Natal deve ter escondido as coisas bem demais. Mas você ainda poderia se fantasiar de Papai Noel e colocar alguns dos seus brinquedos velhos nas meias das crianças, não é?!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "mask_xmas_setitem_mask5",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "mask_xmas_winterhat",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "xmas1_event_eventitem_lights": {
      "index": 1,
      "icon_asset": "xmas1_event_eventitem_lights",
      "type": 7,
      "reference": "xmas_lights",
      "value": 25,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "xmas1_event_eventitem_gingerbread": {
      "index": 2,
      "icon_asset": "xmas1_event_eventitem_gingerbread",
      "type": 7,
      "reference": "xmas_gingerbread",
      "value": 25,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "xmas1_event_worldboss_defeated": {
      "index": 3,
      "icon_asset": "xmas1_event_worldboss_defeated",
      "type": 6,
      "reference": "xmas_event_stage2",
      "value": 2,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "xmas1_event_fightquest_won": {
      "index": 4,
      "icon_asset": "xmas1_event_fightquest_won",
      "type": 2,
      "reference": "all",
      "value": 70,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "xmas1_event_washing_machine_used": {
      "index": 5,
      "icon_asset": "washing_machine_used",
      "type": 10,
      "reference": "",
      "value": 7,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.4. Papai Noel em apuros

Identificador: `xmas_event_2`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#00763D` / `#9F0007`.

**Contacto:** Ajudante de Papai Noel

**Introdução:** “Oi, ... olá! Ah, que maravilha, então, por onde começar? Estava a caminho de Humphreydale com o meu trenó e daí... Crac, Bum, Bong... o patim do trenó quebrou! Ah, é o Papai Noel falando. Mas isso você já deve ter adivinhado. Além disso, a sua lista de pedidos de Natal, por favor... mas então... De qualquer modo eu estou preso aqui e preciso da sua ajuda. Posso contar com você?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Seguro contra panes<br>`xmas2_event_eventitem_candles` | 7 | `xmas_candles` | 30 | 4 / 0.4 / `` |
| 2 | Macaco<br>`xmas2_event_training_completed` | 5 | `all` | 75 | 1 / 0.3 / `` |
| 3 | O Sabotador do Natal, Volume II<br>`xmas2_event_worldboss_defeated` | 6 | `xmas_event_stage2` | 3 | 4 / 0.5 / `` |
| 4 | Nariz vermelho<br>`xmas2_event_eventitem_rednose` | 7 | `xmas_rednose` | 30 | 3 / 0.5 / `` |
| 5 | Esquis sobressalentes<br>`xmas2_event_eventitem_skids` | 7 | `xmas_skids` | 30 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `cape_xmasset2` |
| reward_item2 | 7 | 20 | `weapon_xmasset2` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Muito obrigado, você salvou o Natal de muitas crianças. Isso foi mais do que heroico! Mas será que esses pisca-piscas novos eram realmente necessários?”

**Texto de fim sem sucesso:** “Que bom que eu ainda tinha o número do serviço de resgate de trenós. Agora eu ainda poderei entregar os presentes na hora.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#00763D",
  "background_color_bottom": "#9F0007",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "cape_xmasset2",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "weapon_xmasset2",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "xmas2_event_eventitem_candles": {
      "index": 1,
      "icon_asset": "xmas2_event_eventitem_candles",
      "type": 7,
      "reference": "xmas_candles",
      "value": 30,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "xmas2_event_training_completed": {
      "index": 2,
      "icon_asset": "xmas2_event_training_completed",
      "type": 5,
      "reference": "all",
      "value": 75,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "xmas2_event_worldboss_defeated": {
      "index": 3,
      "icon_asset": "xmas2_event_worldboss_defeated",
      "type": 6,
      "reference": "xmas_event_stage2",
      "value": 3,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "xmas2_event_eventitem_rednose": {
      "index": 4,
      "icon_asset": "xmas2_event_eventitem_rednose",
      "type": 7,
      "reference": "xmas_rednose",
      "value": 30,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "xmas2_event_eventitem_skids": {
      "index": 5,
      "icon_asset": "xmas2_event_eventitem_skids",
      "type": 7,
      "reference": "xmas_skids",
      "value": 30,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.5. Jogos de Inverno da Yoyodyne

Identificador: `winterolympics_event`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFFFFF` / `#9DFCFF`.

**Contacto:** Presidente Oliver M. Pixe

**Introdução:** “A Liga de Heróis dos Jogos de Inverno precisa da sua ajuda! A Yoyodyne está convidando vilões do mundo inteiro para os Jogos de Inverno da Yoyodyne, para prestar homenagem aos melhores bandidos do mundo todo em modalidades como furar a fila no teleférico que leva à montanha de esqui e arremesso de fãs. Dê um basta nessa loucura!”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Fique em forma!<br>`winterolympics_event_training_completed` | 5 | `all` | 55 | 4 / 0.4 / `` |
| 2 | Imitação de medalha de ouro<br>`winterolympics_event_eventitem_medal` | 7 | `winterolympics_medal` | 25 | 1 / 0.3 / `` |
| 3 | Gorros de neve roubados<br>`winterolympics_event_eventitem_skihat` | 7 | `winterolympics_skihat` | 25 | 4 / 0.5 / `` |
| 4 | Coelho da neve<br>`winterolympics_event_eventitem_bunny` | 7 | `winterolympics_bunny` | 25 | 3 / 0.5 / `` |
| 5 | O fim para o Dr. Rob Alhão<br>`winterolympics_event_worldboss_defeated` | 6 | `winterolympics_event_stage2` | 2 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `weapon_winterolympics_torch` |
| reward_item2 | 7 | 20 | `suit_winterolympics_border` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Isso foi um enorme sucesso! Graças a você, Yoyodyne teve que encerrar os jogos mais rápido do que os participantes pudessem dizer ‘happy hour’. Um dia glorioso para a justiça!”

**Texto de fim sem sucesso:** “Que vergonha... Já na chegada você atolou na neve. Mas quem poderia adivinhar que Yoyodyne iria te guiar com placas falsas diretamente para a neve mais profunda? Ainda bem que você gravou o número do grupo de salvamento das montanhas no celular.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFFFFF",
  "background_color_bottom": "#9DFCFF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "weapon_winterolympics_torch",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "suit_winterolympics_border",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "winterolympics_event_training_completed": {
      "index": 1,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 55,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "winterolympics_event_eventitem_medal": {
      "index": 2,
      "icon_asset": "winterolympics_event_eventitem_medal",
      "type": 7,
      "reference": "winterolympics_medal",
      "value": 25,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "winterolympics_event_eventitem_skihat": {
      "index": 3,
      "icon_asset": "winterolympics_event_eventitem_skihat",
      "type": 7,
      "reference": "winterolympics_skihat",
      "value": 25,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "winterolympics_event_eventitem_bunny": {
      "index": 4,
      "icon_asset": "winterolympics_event_eventitem_bunny",
      "type": 7,
      "reference": "winterolympics_bunny",
      "value": 25,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "winterolympics_event_worldboss_defeated": {
      "index": 5,
      "icon_asset": "winterolympics_event_worldboss_defeated",
      "type": 6,
      "reference": "winterolympics_event_stage2",
      "value": 2,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.6. O desfile de carnaval

Identificador: `carnival_event`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E964FF` / `#0900E2`.

**Contacto:** Sam Espetacular

**Introdução:** “Preciso do seu apoio! O prefeito quer que eu organize o maior desfile carnavalesco de todos os tempos – mas o único evento que organizei na vida foi o aniversário do Tiozinho. Você pode me ajudar?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Compras de carnaval<br>`carnival_event_shop_refreshed` | 11 | `all` | 8 | 3 / 0.5 / `` |
| 2 | Ficar em forma<br>`carnival_event_training_completed` | 5 | `all` | 77 | 4 / 0.4 / `` |
| 3 | Pequenas guloseimas<br>`carnival_event_eventitem_sweets` | 7 | `carnival_sweets` | 25 | 3 / 0.5 / `` |
| 4 | Fundo musical<br>`carnival_event_eventitem_whistle` | 7 | `carnival_whistle` | 30 | 4 / 0.4 / `` |
| 5 | Decoração<br>`carnival_event_eventitem_streamers` | 7 | `carnival_streamers` | 25 | 3 / 0.5 / `` |
| 6 | Já chega desse Confetto!<br>`carnival_event_worldboss_defeated` | 6 | `carnival_stage2` | 3 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `belt_carnivalset3` |
| reward_item2 | 7 | 20 | `mask_carnivalset3` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Fantástico! Sempre sonhei com um desfile carnavalesco desses quando era criança e o prefeito também ficou animadíssimo! Muito obrigado! Agora, você poderia pegar uma vassoura para limpar a rua das serpentinas, bombons e balas grudados?”

**Texto de fim sem sucesso:** “Não foi o suficiente para ser o maior desfile de todos os tempos. Mas conseguimos animar algumas pessoas fantasiadas a dar algumas voltas no quarteirão cantando. Quem sabe no próximo ano dá mais certo...”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E964FF",
  "background_color_bottom": "#0900E2",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "belt_carnivalset3",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "mask_carnivalset3",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "carnival_event_shop_refreshed": {
      "index": 1,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "all",
      "value": 8,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "carnival_event_training_completed": {
      "index": 2,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 77,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "carnival_event_eventitem_sweets": {
      "index": 3,
      "icon_asset": "carnival_event_eventitem_sweets",
      "type": 7,
      "reference": "carnival_sweets",
      "value": 25,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "carnival_event_eventitem_whistle": {
      "index": 4,
      "icon_asset": "carnival_event_eventitem_whistle",
      "type": 7,
      "reference": "carnival_whistle",
      "value": 30,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "carnival_event_eventitem_streamers": {
      "index": 5,
      "icon_asset": "carnival_event_eventitem_streamers",
      "type": 7,
      "reference": "carnival_streamers",
      "value": 25,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "carnival_event_worldboss_defeated": {
      "index": 6,
      "icon_asset": "carnival_event_worldboss_defeated",
      "type": 6,
      "reference": "carnival_stage2",
      "value": 3,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.7. Coelho da Páscoa em apuros

Identificador: `easter`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#96FFBB` / `#FCFF89`.

**Contacto:** O coelho da Páscoa

**Introdução:** “S.O.S.! S.O.S.! Coelho da Páscoa em apuros! Fui assaltado por um ladrão de ovos malvado a caminho de Humphreydale. Ele roubou todos os ovos de Páscoa coloridos, os cestos e os coelhos de chocolate. Que desastre! Você pode me ajudar a repor tudo, por favor?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Ovos de Páscoa coloridos<br>`easter_eventitem_eastereggs` | 7 | `easter_eggs` | 30 | 4 / 0.5 / `` |
| 2 | Doce tentação<br>`easter_eventitem_bunnies` | 7 | `easter_bunnies` | 30 | 3 / 0.5 / `` |
| 3 | Com energia até o sucesso<br>`easter_energy_used` | 4 | `` | 1600 | 4 / 0.5 / `` |
| 4 | Sempre limpinho<br>`easter_washing_machine_used` | 10 | `all` | 7 | 3 / 0.5 / `` |
| 5 | Abaixo o ladrão de ovos!<br>`easter_worldboss_defeated` | 6 | `easter_event_stage2` | 2 | 4 / 0.5 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_easter3` |
| reward_item2 | 7 | 20 | `mask_easter_bunny1` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Fantástico! Conseguimos todos os cestos, ovos e coelhos de chocolate que precisamos para a festa da Páscoa em Humphreydale. Você salvou a festa! Mesmo assim estou meio atrasado. Você pode me ajudar a esconder as cestinhas de Páscoa?”

**Texto de fim sem sucesso:** “Maldição, isso não deu certo. Mas ainda bem que entrei em contato com o meu colega na Suíça que ainda pôde nos mandar alguns ovos de chocolate. Vamos lá, pegue as suas orelhas de coelho de pelúcia daquela fantasia antiga e me ajude a esconder os ovos de Páscoa!”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#96FFBB",
  "background_color_bottom": "#FCFF89",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_easter3",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "mask_easter_bunny1",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "easter_worldboss_defeated": {
      "index": 5,
      "icon_asset": "easter_worldboss_defeated",
      "type": 6,
      "reference": "easter_event_stage2",
      "value": 2,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "easter_washing_machine_used": {
      "index": 4,
      "icon_asset": "washing_machine_used",
      "type": 10,
      "reference": "all",
      "value": 7,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "easter_eventitem_eastereggs": {
      "index": 1,
      "icon_asset": "easter_eventitem_eastereggs",
      "type": 7,
      "reference": "easter_eggs",
      "value": 30,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "easter_eventitem_bunnies": {
      "index": 2,
      "icon_asset": "easter_eventitem_bunnies",
      "type": 7,
      "reference": "easter_bunnies",
      "value": 30,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "easter_energy_used": {
      "index": 3,
      "icon_asset": "energy_used",
      "type": 4,
      "reference": "",
      "value": 1600,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    }
  }
}
```

### 6.8. Vai ligar pra quem?

Identificador: `ghostbusters_event`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Dr. Peter Venkman

**Introdução:** “Alô, é o Peter Venkman falando. Soubemos que na sua vizinhança há algo estranho? Livros voadores, rachaduras interdimensionais na geladeira e coisas análogas? Meus rapazes Ray, Egon, Winston e eu podemos cuidar disso! Durante o nosso percurso para chegar aí, entretanto, perdemos alguns utensílios importantes. Você pode nos ajudar a completar o nosso equipamento?!”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Armadilha para fantasmas<br>`ghostbusters_event_eventitem_trap` | 7 | `ghostbusters_event_trap` | 35 | 4 / 0.4 / `` |
| 2 | Ficar sempre em forma<br>`ghostbusters_event_training_completed` | 5 | `all` | 30 | 1 / 0.3 / `` |
| 3 | Stay Puft<br>`ghostbusters_event_worldboss_defeated` | 6 | `ghostbusters_event_stage2` | 1 | 4 / 0.5 / `` |
| 4 | Energimômetro<br>`ghostbusters_event_eventitem_pkemeter` | 7 | `ghostbusters_event_pkemeter` | 10 | 3 / 0.5 / `` |
| 5 | Baterias sobressalentes<br>`ghostbusters_event_eventitem_accu` | 7 | `ghostbusters_event_accu` | 15 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_slimer` |
| reward_item2 | 7 | 20 | `random_epic` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Uau, recuperamos tudo! Obrigado pela ajuda. Os fantasmas estão armazenados em segurança, o Homem-Marshmallow Stay Puft está frito e o Ecto-1 está de tanque cheio. Nós terminamos por aqui! Caso você esteja pensando sobre uma carreira como caça-fantasmas você já sabe pra quem ligar! Você pode encontrar um uniforme de caça-fantasma que combine com você nas Ofertas Secretas.”

**Texto de fim sem sucesso:** “Bem, o nosso equipamento já era. Mas graças à ideia brilhante do Egon de causar uma inversão total de prótons, conseguimos mandar as criaturas de volta ao lugar de onde vieram. Você já pensou em seguir carreira como caça-fantasmas? Vá buscar um uniforme de caça-fantasmas de verdade nas Ofertas Secretas. Assim você estará [m:preparado|f:preparada] para uma emergência!”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_slimer",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "random_epic",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "ghostbusters_event_eventitem_trap": {
      "index": 1,
      "icon_asset": "ghostbusters_event_eventitem_trap",
      "type": 7,
      "reference": "ghostbusters_event_trap",
      "value": 35,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "ghostbusters_event_training_completed": {
      "index": 2,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 30,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "ghostbusters_event_worldboss_defeated": {
      "index": 3,
      "icon_asset": "ghostbusters_event_worldboss_defeated",
      "type": 6,
      "reference": "ghostbusters_event_stage2",
      "value": 1,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "ghostbusters_event_eventitem_pkemeter": {
      "index": 4,
      "icon_asset": "ghostbusters_event_eventitem_pkemeter",
      "type": 7,
      "reference": "ghostbusters_event_pkemeter",
      "value": 10,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "ghostbusters_event_eventitem_accu": {
      "index": 5,
      "icon_asset": "ghostbusters_event_eventitem_accu",
      "type": 7,
      "reference": "ghostbusters_event_accu",
      "value": 15,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.9. Vamos ao estádio!

Identificador: `worldcup_event_1`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFFFFF` / `#44FF56`.

**Contacto:** Diretoria do Clube dos Heróis

**Introdução:** “Oi! Os membros do meu Clube de Heróis querem pegar o avião e ver o grande evento de futebol. Mas o tempo urge e precisam de ajuda para fazer as malas. Você pode ajudá-los?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Hálito fresco<br>`worldcup_event_1_eventitem_toothbrush` | 7 | `worldcup_event_1_toothbrush` | 20 | 4 / 0.4 / `` |
| 2 | Buzinas a gás<br>`worldcup_event_1_eventitem_vuvuzela` | 7 | `worldcup_event_1_vuvuzela` | 20 | 1 / 0.3 / `` |
| 3 | Que calor!<br>`worldcup_event_1_eventitem_minifan` | 7 | `worldcup_event_1_minifan` | 20 | 4 / 0.5 / `` |
| 4 | Procura intensa<br>`worldcup_event_1_timequest_completed` | 1 | `*_worldcup_*` | 25 | 3 / 0.5 / `` |
| 5 | Malhação<br>`worldcup_event_1_training_completed` | 5 | `all` | 65 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_worldcup_tiger` |
| reward_item2 | 7 | 20 | `gadget_worldcup_whistle` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Isso deu muito certo! Minhas garotas e rapazes estão muito bem equipados e chegaram no aeroporto a tempo. Obrigado pela sua ajuda!”

**Texto de fim sem sucesso:** “Bem, não conseguimos juntar tudo. Mas perto do estádio certamente encontraremos alguma loja de artigos para as torcidas onde as garotas e rapazes poderão comprar o resto das coisas. O importante é que pelo menos conseguiram pegar o voo a tempo. Mesmo assim, obrigado!”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFFFFF",
  "background_color_bottom": "#44FF56",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_worldcup_tiger",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "gadget_worldcup_whistle",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "worldcup_event_1_eventitem_toothbrush": {
      "index": 1,
      "icon_asset": "worldcup_event_1_eventitem_toothbrush",
      "type": 7,
      "reference": "worldcup_event_1_toothbrush",
      "value": 20,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "worldcup_event_1_eventitem_vuvuzela": {
      "index": 2,
      "icon_asset": "worldcup_event_1_eventitem_vuvuzela",
      "type": 7,
      "reference": "worldcup_event_1_vuvuzela",
      "value": 20,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "worldcup_event_1_eventitem_minifan": {
      "index": 3,
      "icon_asset": "worldcup_event_1_eventitem_minifan",
      "type": 7,
      "reference": "worldcup_event_1_minifan",
      "value": 20,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "worldcup_event_1_timequest_completed": {
      "index": 4,
      "icon_asset": "worldcup_event_1_timequest_completed",
      "type": 1,
      "reference": "*_worldcup_*",
      "value": 25,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "worldcup_event_1_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 65,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.10. A fiiinaaalll! Uuuuuhuuuuu!

Identificador: `worldcup_event_2`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFFFFF` / `#44FF56`.

**Contacto:** Rudi

**Introdução:** “Alô, aqui fala o Rudi da loja de artigos para torcedores! É de enlouquecer, mas agora em cima da final, um dos meus fornecedores avisou que não vai poder entregar os artigos encomendados – e as prateleiras da minha loja já estão praticamente vazias! Você pode me ajudar a preencher o meu estoque?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Alegria contagiante dos torcedores<br>`worldcup_event_2_eventitem_button` | 7 | `worldcup_event_2_button` | 25 | 4 / 0.4 / `` |
| 2 | Hora do lanche!<br>`worldcup_event_2_eventitem_mat` | 7 | `worldcup_event_2_mat` | 25 | 1 / 0.3 / `` |
| 3 | Um exemplo desportivo<br>`worldcup_event_2_training_completed` | 5 | `all` | 55 | 4 / 0.5 / `` |
| 4 | Não dê chance ao mau humor<br>`worldcup_event_2_worldboss_defeated` | 6 | `worldcup_event_stage2` | 2 | 3 / 0.5 / `` |
| 5 | Chaveiro<br>`worldcup_event_2_eventitem_keyringpendant` | 7 | `worldcup_event_2_keyringpendant` | 25 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_worldcup_fox` |
| reward_item2 | 7 | 20 | `weapon_worldcup_fanfare` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Nossa, isso foi ótimo e deu certo! Minhas prateleiras estão mais cheias do que nunca e os clientes estão fazendo fila. Obrigado pela ajuda!”

**Texto de fim sem sucesso:** “Bem, não conseguimos juntar tudo. Mas talvez seja possível fazer alguma coisa em casa, tipo broches artesanais. Mesmo assim, obrigado. Ah, e você sabe tricotar? Ainda preciso de alguns xales para os torcedores...”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFFFFF",
  "background_color_bottom": "#44FF56",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_worldcup_fox",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "weapon_worldcup_fanfare",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "worldcup_event_2_eventitem_button": {
      "index": 1,
      "icon_asset": "worldcup_event_2_eventitem_button",
      "type": 7,
      "reference": "worldcup_event_2_button",
      "value": 25,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "worldcup_event_2_eventitem_mat": {
      "index": 2,
      "icon_asset": "worldcup_event_2_eventitem_mat",
      "type": 7,
      "reference": "worldcup_event_2_mat",
      "value": 25,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "worldcup_event_2_training_completed": {
      "index": 3,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 55,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "worldcup_event_2_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldcup_event_2_worldboss_defeated",
      "type": 6,
      "reference": "worldcup_event_stage2",
      "value": 2,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "worldcup_event_2_eventitem_keyringpendant": {
      "index": 5,
      "icon_asset": "worldcup_event_2_eventitem_keyringpendant",
      "type": 7,
      "reference": "worldcup_event_2_keyringpendant",
      "value": 25,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.11. A festa de comemoração

Identificador: `birthday_event`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** O prefeito

**Introdução:** “Alô? É... aqui fala o prefeito. Humphreydale vai celebrar. As preparações para a comemoração da cidade não estão transcorrendo muito bem. Ainda precisamos de apoio voluntário. Você pode ajudar?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Show de fogos de artifício<br>`birthday_event_eventitem_firecracker` | 7 | `birthday_event_firecracker` | 60 | 3 / 0.6 / `` |
| 2 | Motivação<br>`birthday_event_duels_fought` | 9 | `all` | 200 | 4 / 0.6 / `` |
| 3 | [m:Pronto\|f:Pronta] para a festa<br>`birthday_event_training_completed` | 5 | `` | 100 | 1 / 0.6 / `` |
| 4 | Impressionando<br>`birthday_event_timequest_completed` | 1 | `all` | 50 | 4 / 0.6 / `` |
| 5 | Preparação Festiva<br>`birthday_event_shop_refreshed` | 11 | `all` | 9 | 3 / 0.5 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 5 | 7 | `booster_quest3` |
| reward_item1 | 7 | 20 | `suit_kungfu` |
| reward_item2 | 7 | 20 | `weapon_foam_cannon3` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Uau, isso foi perfeito! A cidade inteira de Humphreydale se reuniu e está festejando na praça do mercado. Obrigado pela sua ajuda!”

**Texto de fim sem sucesso:** “Isso não transcorreu como planejado. Ainda bem que pudemos improvisar e a comemoração pode ser realizada. Talvez você ainda possa passar na praça do mercado e distribuir alguns cupcakes com a minha filha Lucy para os festeiros?”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 5,
  "reward2_factor": 7,
  "reward2_flag": "booster_quest3",
  "reward_item1_type": 7,
  "reward_item1_flag": "suit_kungfu",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "weapon_foam_cannon3",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "birthday_event_eventitem_firecracker": {
      "index": 1,
      "icon_asset": "birthday_event_eventitem_firecracker",
      "type": 7,
      "reference": "birthday_event_firecracker",
      "value": 60,
      "reward_type": 3,
      "reward_factor": 0.6,
      "reward_reference": ""
    },
    "birthday_event_duels_fought": {
      "index": 2,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "all",
      "value": 200,
      "reward_type": 4,
      "reward_factor": 0.6,
      "reward_reference": ""
    },
    "birthday_event_training_completed": {
      "index": 3,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 100,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    },
    "birthday_event_timequest_completed": {
      "index": 4,
      "icon_asset": "timequest_completed",
      "type": 1,
      "reference": "all",
      "value": 50,
      "reward_type": 4,
      "reward_factor": 0.6,
      "reward_reference": ""
    },
    "birthday_event_shop_refreshed": {
      "index": 5,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "all",
      "value": 9,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    }
  }
}
```

### 6.12. Oktoberfest em Humphreydale

Identificador: `octoberfest`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#BCFFFF` / `#02B4FF`.

**Contacto:** Pretzel Bernd

**Introdução:** Nossa! O pavilhão de cerveja na Oktoberfest em Humphreydale está explodindo de gente. Meus meninos e meninas não estão dando conta de tantos convidados! Precisamos realmente de um herói que nos apoie ativamente – você tem nervos de aço e pode ajudar?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Fique em forma!<br>`octoberfest_training_completed` | 5 | `` | 50 | 4 / 0.4 / `` |
| 2 | Pretzel de queijo<br>`octoberfest_eventitem_prezel_collected` | 7 | `octoberfest_prezel` | 20 | 1 / 0.3 / `` |
| 3 | Capacidade de superação<br>`octoberfest_fightquest_won` | 2 | `all` | 55 | 4 / 0.5 / `` |
| 4 | Duelo de mesa<br>`octoberfest_duels_fought` | 9 | `` | 120 | 3 / 0.5 / `` |
| 5 | Sem gorro sem Sepp<br>`octoberfest_eventitem_hat_collected` | 7 | `octoberfest_hat` | 20 | 1 / 0.3 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `missile_octoberfest1` |
| reward_item2 | 7 | 20 | `weapon_octoberfest1` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** Você é realmente um herói festeiro bávaro! Você fez a animação da festa no pavilhão. Posso contar com você da próxima vez?

**Texto de fim sem sucesso:** Vish, a animação no pavilhão de cerveja mais do que precisava ser melhorada. Talvez deva se engajar um pouco mais da próxima vez para que os convidados realmente se divirtam?

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#BCFFFF",
  "background_color_bottom": "#02B4FF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "missile_octoberfest1",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "weapon_octoberfest1",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "octoberfest_training_completed": {
      "index": 1,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 50,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "octoberfest_eventitem_prezel_collected": {
      "index": 2,
      "icon_asset": "octoberfest_eventitem_prezel_collected",
      "type": 7,
      "reference": "octoberfest_prezel",
      "value": 20,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "octoberfest_fightquest_won": {
      "index": 3,
      "icon_asset": "fightquest_won",
      "type": 2,
      "reference": "all",
      "value": 55,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "octoberfest_duels_fought": {
      "index": 4,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 120,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "octoberfest_eventitem_hat_collected": {
      "index": 5,
      "icon_asset": "octoberfest_eventitem_hat_collected",
      "type": 7,
      "reference": "octoberfest_hat",
      "value": 20,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    }
  }
}
```

### 6.13. O Festival de Esportes de Verão

Identificador: `olympia_2016_rio`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Diretor do Clube Esportivo Eddy

**Introdução:** Alô? Preciso de uma ajudinha por aqui. Tem algo extremamente podre no Festival de Esportes de Verão. Uns palhaços estão distribuindo livretos de programação falsos, tentando cadastrar modalidades totalmente insanas, e alguém pintou por cima do banner que está pendurado na entrada principal e escreveu “Jogos de Verão Yoyo-límpicos”. Você pode resolver isso para que o Festival de Esportes de Verão possa seguir sem mais problemas?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Total uso do corpo!<br>`olympia_2016_rio_training_completed` | 5 | `` | 70 | 4 / 0.6 / `` |
| 2 | Salto em altura vem antes do tombo<br>`olympia_2016_rio_eventitem_stick_collected` | 7 | `olympia_2016_rio_stick` | 35 | 1 / 0.3 / `` |
| 3 | Reflexo<br>`olympia_2016_rio_duels_fought` | 9 | `` | 175 | 4 / 0.5 / `` |
| 4 | Sabotagem de informação<br>`olympia_2016_rio_eventitem_booklet_collected` | 7 | `olympia_2016_rio_booklet` | 35 | 3 / 0.5 / `` |
| 5 | O fim para o “Dr. Rob Alhão”<br>`olympia_2016_rio_worldboss_defeated` | 6 | `olympia_event_stage2` | 3 | 3 / 0.5 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `mask_olympia_2016_rio` |
| reward_item2 | 7 | 20 | `missile_olympia_2016_rio` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** Estava claro que Yoyodyne tinha um dedinho nisso. Felizmente, você pôde sobrepujar este estraga prazeres antidesportivo com verdadeira alma esportiva e expulsá-lo do terreno. Bom trabalho!

**Texto de fim sem sucesso:** Bem, sejamos sinceros... Você poderia mostrar um pouco mais de iniciativa como herói exemplar. Ainda bem que os vilões perceberam que têm medo de altura quando estavam tentando pular a cerca. Assim pelo menos a polícia local pôde prendê-los.

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "mask_olympia_2016_rio",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "missile_olympia_2016_rio",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "olympia_2016_rio_training_completed": {
      "index": 1,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 70,
      "reward_type": 4,
      "reward_factor": 0.6,
      "reward_reference": ""
    },
    "olympia_2016_rio_eventitem_stick_collected": {
      "index": 2,
      "icon_asset": "olympia_2016_rio_eventitem_stick_collected",
      "type": 7,
      "reference": "olympia_2016_rio_stick",
      "value": 35,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "olympia_2016_rio_duels_fought": {
      "index": 3,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "olympia_2016_rio_eventitem_booklet_collected": {
      "index": 4,
      "icon_asset": "olympia_2016_rio_eventitem_booklet_collected",
      "type": 7,
      "reference": "olympia_2016_rio_booklet",
      "value": 35,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "olympia_2016_rio_worldboss_defeated": {
      "index": 5,
      "icon_asset": "olympia_2016_rio_worldboss_defeated",
      "type": 6,
      "reference": "olympia_event_stage2",
      "value": 3,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    }
  }
}
```

### 6.14. A Convenção de Heróis de Ficção Científica

Identificador: `sci_fi_week`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E93244` / `#7C3539`.

**Contacto:** Capitão P. Carte

**Introdução:** “Sede do Herói, Capitão P. Carte falando. Eu e minha tripulação estamos no processo de montar a Convenção de Heróis de Ficção Científica e podemos precisar de um pouco de ajuda de [m:um herói|f:uma heroína] local experiente. Além de entradas VIP gratuitas, há também uma recompensa. Podemos contar com você?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Autêntico<br>`sci_fi_week_eventitem_pin` | 7 | `sci_fi_week_pin` | 70 | 4 / 0.4 / `` |
| 2 | Exército de Nerds<br>`sci_fi_week_duels_fought` | 9 | `` | 180 | 1 / 0.3 / `` |
| 3 | Nerd invertido de cabeça para baixo<br>`sci_fi_week_worldboss_defeated` | 6 | `sci_fi_week_stage2` | 2 | 6 / 1 / `117` |
| 4 | Sacolas de brinde<br>`sci_fi_week_eventitem_goodie_bag` | 7 | `sci_fi_week_goodie_bag` | 70 | 3 / 0.5 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `mask_sci_fi_week_2` |
| reward_item2 | 7 | 20 | `suit_sci_fi_week_2` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** “Muito bem! A Convenção de Heróis de Ficção Científica foi um grande sucesso, mas não teria sido possível sem você. Aqui está a sua recompensa mais do que merecida.”

**Texto de fim sem sucesso:** “Céus, o que aconteceu? Não é nenhuma surpresa que a convenção fracassou com tão pouco esforço. Espero que você se esforce um pouco mais da próxima vez.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E93244",
  "background_color_bottom": "#7C3539",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "mask_sci_fi_week_2",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "suit_sci_fi_week_2",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "sci_fi_week_eventitem_pin": {
      "index": 1,
      "icon_asset": "sci_fi_week_eventitem_pin",
      "type": 7,
      "reference": "sci_fi_week_pin",
      "value": 70,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "sci_fi_week_duels_fought": {
      "index": 2,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 180,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    },
    "sci_fi_week_worldboss_defeated": {
      "index": 3,
      "icon_asset": "sci_fi_week_worldboss_defeated",
      "type": 6,
      "reference": "sci_fi_week_stage2",
      "value": 2,
      "reward_type": 6,
      "reward_factor": 1,
      "reward_reference": "117"
    },
    "sci_fi_week_eventitem_goodie_bag": {
      "index": 4,
      "icon_asset": "sci_fi_week_eventitem_goodie_bag",
      "type": 7,
      "reference": "sci_fi_week_goodie_bag",
      "value": 70,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    }
  }
}
```

### 6.15. A Conspiração do Lixo

Identificador: `environmental_week`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFB965` / `#FFE687`.

**Contacto:** Chefe dos Garis

**Introdução:** Tem algo de muito sujo nesta cidade! Parece que uma empresa recém-criada de descarte de lixo decidiu espalhar poluição pesada por toda a cidade, e curiosamente o nosso maior concorrente sempre consegue chegar para limpar o local antes de nós. Por isso, estão ficando com quase toda a verba pública destinada a descarte eficiente de lixo. Se as coisas continuarem desse jeito, teremos que fechar as portas. Na minha opinião, essa situação cheira bem mal. Pode investigar?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Análise de lixo<br>`environmental_week_eventitem_garbage` | 7 | `environmental_week_garbage` | 20 | 4 / 0.5 / `` |
| 2 | Paciência é uma virtude<br>`environmental_week_timequest_completed` | 1 | `` | 25 | 3 / 0.7 / `` |
| 3 | Quem não arrisca, não petisca<br>`environmental_week_fightquest_won` | 2 | `` | 60 | 3 / 0.5 / `` |
| 4 | Papelada<br>`environmental_week_eventitem_form` | 7 | `environmental_week_form` | 40 | 4 / 0.7 / `` |
| 5 | Hoje não, Emissio!<br>`environmental_week_eventitem_worldboss_defeated` | 6 | `environmental_week_stage2` | 3 | 1 / 0.3 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_environmental_week_1` |
| reward_item2 | 7 | 20 | `random_epic` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** Você descobriu que a YoyoWaste é uma empresa afiliada à YoyoClean. A YoyoWaste espalhou lixo na cidade de propósito para que a YoyoClean pudesse ser a primeira a limpá-lo e, portanto, receber a verba pública. É um truque sujo e decididamente ilegal - e, graças a você, os criminosos estão no tribunal aguardando o julgamento. Bom trabalho!

**Texto de fim sem sucesso:** Você não conseguiu encontrar os responsáveis, mas pelo menos conseguiu limpar a cidade mais rápido que eles, o que foi o bastante para fazê-los desistirem. Da próxima vez que eles atacarem, seria ótimo se você pudesse pegá-los no flagra.

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFB965",
  "background_color_bottom": "#FFE687",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_environmental_week_1",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "random_epic",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "environmental_week_eventitem_garbage": {
      "index": 1,
      "icon_asset": "environmental_week_eventitem_garbage",
      "type": 7,
      "reference": "environmental_week_garbage",
      "value": 20,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "environmental_week_timequest_completed": {
      "index": 2,
      "icon_asset": "environmental_week_timequest_completed",
      "type": 1,
      "reference": "",
      "value": 25,
      "reward_type": 3,
      "reward_factor": 0.7,
      "reward_reference": ""
    },
    "environmental_week_fightquest_won": {
      "index": 3,
      "icon_asset": "environmental_week_fightquest_won",
      "type": 2,
      "reference": "",
      "value": 60,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "environmental_week_eventitem_form": {
      "index": 4,
      "icon_asset": "environmental_week_eventitem_form",
      "type": 7,
      "reference": "environmental_week_form",
      "value": 40,
      "reward_type": 4,
      "reward_factor": 0.7,
      "reward_reference": ""
    },
    "environmental_week_eventitem_worldboss_defeated": {
      "index": 5,
      "icon_asset": "environmental_week_eventitem_worldboss_defeated",
      "type": 6,
      "reference": "environmental_week_stage2",
      "value": 3,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    }
  }
}
```

### 6.16. A LAN Party

Identificador: `video_games_week`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#1693FF` / `#7CFFC9`.

**Contacto:** Clint “Cy83rh3r01” Claasson

**Introdução:** E aí, camarada! Quero organizar uma LAN Party irada na arena municipal, onde todos os gamers fanáticos possam jogar seus jogos prediletos com e contra os outros gamers. Vai ser insano! Mas eu ainda não tenho muita experiência com organização de eventos. Pode me ajudar?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Conexão de banda larga<br>`video_games_week_eventitem_repeater` | 7 | `video_games_week_repeater` | 50 | 4 / 1 / `` |
| 2 | Teste de carga<br>`video_games_week_energy_used` | 4 | `` | 1750 | 3 / 1 / `` |
| 3 | Brindes exclusivos!<br>`video_games_week_eventitem_headset` | 7 | `video_games_week_headset` | 30 | 3 / 1 / `` |
| 4 | Prêmio de segunda mão<br>`video_games_week_shop_refreshed` | 11 | `` | 7 | 4 / 1 / `` |
| 5 | Não se esqueça de se hidratar!<br>`video_games_week_timequest_completed` | 1 | `` | 30 | 1 / 0.3 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_video_games_week_2` |
| reward_item2 | 7 | 20 | `random_epic` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** Espetacular! A LAN Party foi um sucesso absoluto! Os gamers estão claramente se divertindo, e os primeiros patrocinadores já se cadastraram para a próxima sessão do evento. Obrigado pela ajuda!

**Texto de fim sem sucesso:** Que pena. Por algum motivo, a LAN Party não chegou a ficar com o clima certo, então a maioria das pessoas saiu bem cedo. Talvez possamos conversar sobre como melhorar da próxima vez?

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#1693FF",
  "background_color_bottom": "#7CFFC9",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_video_games_week_2",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "random_epic",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "video_games_week_eventitem_repeater": {
      "index": 1,
      "icon_asset": "video_games_week_eventitem_repeater",
      "type": 7,
      "reference": "video_games_week_repeater",
      "value": 50,
      "reward_type": 4,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "video_games_week_energy_used": {
      "index": 2,
      "icon_asset": "energy_used",
      "type": 4,
      "reference": "",
      "value": 1750,
      "reward_type": 3,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "video_games_week_eventitem_headset": {
      "index": 3,
      "icon_asset": "video_games_week_eventitem_headset",
      "type": 7,
      "reference": "video_games_week_headset",
      "value": 30,
      "reward_type": 3,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "video_games_week_shop_refreshed": {
      "index": 4,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 7,
      "reward_type": 4,
      "reward_factor": 1,
      "reward_reference": ""
    },
    "video_games_week_timequest_completed": {
      "index": 5,
      "icon_asset": "timequest_completed",
      "type": 1,
      "reference": "",
      "value": 30,
      "reward_type": 1,
      "reward_factor": 0.3,
      "reward_reference": ""
    }
  }
}
```

### 6.17. Um Dia no Museu

Identificador: `server_launch_primal`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Daley, [m:o|f:a] Diretor[m:|f:a] do Museu

**Introdução:** “Preciso de ajuda urgente! Um[m:|f:a] cientista maluc[m:o|f:a] invadiu o museu e roubou um monte de ossos dos nossos esqueletos de dinossauros. Nas câmeras de segurança, conseguimos ouvi-l[m:o|f:a] falando algum blá-blá-blá sobre ‘clonagem’ e um ‘Prêmio Nobel’. Seja como for, [m:ele|f:ela] escapou e estou com um péssimo pressentimento! Você poderia nos ajudar a encontrar [m:esse maluco|f:essa maluca]?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Reflexos Primais<br>`server_launch_primal_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_season_event_primal` |
| 2 | Trabalho de amassar os ossos<br>`server_launch_primal_eventitem` | 7 | `server_launch_primal_bone` | 80 | 5 / 18 / `cape_season_event_primal` |
| 3 | Bem equipad[m:o\|f:a]<br>`server_launch_primal_shop_refresh` | 11 | `` | 7 | 5 / 18 / `suit_season_event_primal` |
| 4 | Fique em forma!<br>`server_launch_primal_training_completed` | 5 | `` | 70 | 5 / 18 / `belt_season_event_primal` |
| 5 | Tiranossauro Rexy<br>`server_launch_primal_worldboss_defeated` | 6 | `primal_season_worldboss` | 2 | 5 / 18 / `boots_season_event_primal` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_primal` |
| reward_item2 | 7 | 20 | `sidekick_season_event_primal` |
| reward_item3 | 7 | 20 | `sidekick_season_event_primal` |

**Texto de sucesso:** “Ufa! Graças aos seus esforços heroicos, [m:o|f:a] cientista maluc[m:o|f:a] foi pres[m:o|f:a] e nunca mais poderá pôr as mãos em DNA dos dinos. Bom trabalho!”

**Texto de fim sem sucesso:** “Bom, isso não deu certo! Você bem que poderia ter demonstrado um pouquinho mais de esforço. Mas não se preocupe, seus colegas heroicos conseguiram encontrar [m:o|f:a] tal d[m:o|f:a] cientista e colocá-l[m:o|f:a] atrás das grades. Tenho certeza que você consegue dar conta da próxima vez!”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_primal",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_primal",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_primal",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_primal_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_primal"
    },
    "server_launch_primal_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_primal_eventitem",
      "type": 7,
      "reference": "server_launch_primal_bone",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_primal"
    },
    "server_launch_primal_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 7,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_primal"
    },
    "server_launch_primal_training_completed": {
      "index": 4,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 70,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_primal"
    },
    "server_launch_primal_worldboss_defeated": {
      "index": 5,
      "icon_asset": "server_launch_primal_worldboss_defeated",
      "type": 6,
      "reference": "primal_season_worldboss",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_primal"
    }
  }
}
```

### 6.18. Alienígenas em Humphreydale

Identificador: `server_launch_utopia`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFC388` / `#FF89CA`.

**Contacto:** General Talbert

**Introdução:** Socorro! Alienígenas! Por todos os lados! Obviamente, pretendem fazer o ar de Humphreydale feder mais que ovos podres com seus aparelhos fétidos para se sentirem à vontade na nossa atmosfera. Credo! Precisamos do seu apoio heroico para impedir que isso aconteça, custe o que custar. Os moradores da cidade descobriram que os alienígenas são alérgicos a aromas florais - talvez você possa usar essa informação. Boa sorte!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | É melhor prevenir do que remediar!<br>`server_launch_utopia_duels_fought` | 9 | `` | 200 | 5 / 18 / `mask_season_event_utopia` |
| 2 | Poder das flores!<br>`server_launch_utopia_eventitem` | 7 | `server_launch_utopia_deo` | 100 | 5 / 18 / `cape_season_event_utopia` |
| 3 | Com um cheirinho de frescor!<br>`server_launch_utopia_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_season_event_utopia` |
| 4 | Não perca a forma!<br>`server_launch_utopia_training_completed` | 5 | `` | 80 | 5 / 18 / `belt_season_event_utopia` |
| 5 | General Garglsmorg<br>`server_launch_utopia_worldboss_defeated` | 6 | `utopia_season_worldboss` | 2 | 5 / 18 / `boots_season_event_utopia` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_utopia` |
| reward_item2 | 7 | 20 | `sidekick_season_event_utopia` |
| reward_item3 | 7 | 20 | `sidekick_season_event_utopia` |

**Texto de sucesso:** Ha! Pelo visto, os alienígenas não esperavam tanta concentração de poder das flores! Obrigado pelo seu esforço fragrante! Eles não voltarão aqui tão cedo.

**Texto de fim sem sucesso:** Parece que você ficou sem fôlego no meio do caminho, né? Faz sentido, com tanto fedor! Mas parece que estava um pouco abafado demais até mesmo para os alienígenas, porque eles já deram meia-volta e saíram correndo. Da próxima vez, tente se esforçar um pouco mais para que os moradores não precisem arejar suas casas antes de poderem voltar a elas.

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFC388",
  "background_color_bottom": "#FF89CA",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_utopia",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_utopia",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_utopia",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_utopia_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 200,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_utopia"
    },
    "server_launch_utopia_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_utopia_eventitem",
      "type": 7,
      "reference": "server_launch_utopia_deo",
      "value": 100,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_utopia"
    },
    "server_launch_utopia_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_utopia"
    },
    "server_launch_utopia_training_completed": {
      "index": 4,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_utopia"
    },
    "server_launch_utopia_worldboss_defeated": {
      "index": 5,
      "icon_asset": "server_launch_utopia_worldboss_defeated",
      "type": 6,
      "reference": "utopia_season_worldboss",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_utopia"
    }
  }
}
```

### 6.19. Apocalipse Zumbi em Humphreydale

Identificador: `server_launch_dystopia`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#00C38E` / `#5F5F5F`.

**Contacto:** Xerife Rick

**Introdução:** Está na hora! O apocalipse zumbi começou, como eu sempre avisei! E agora as hordas estão pisoteando todos os lindos girassóis nos quintais de toda a cidade. Espero que você tenha prestado atenção no meu curso preparatório para o apocalipse zumbi e saiba o que fazer agora! Boa sorte!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Porradalipse Zumbi!<br>`server_launch_dystopia_duels_fought` | 9 | `` | 150 | 5 / 18 / `mask_season_event_dystopia` |
| 2 | Procurando Gnomos<br>`server_launch_dystopia_eventitem` | 7 | `server_launch_dystopia_gardengnome` | 75 | 5 / 18 / `cape_season_event_dystopia` |
| 3 | Bem equipad[m:o\|f:a]<br>`server_launch_dystopia_shop_refresh` | 11 | `` | 7 | 5 / 18 / `suit_season_event_dystopia` |
| 4 | Defesa do Bairro<br>`server_launch_dystopia_training_completed` | 5 | `` | 60 | 5 / 18 / `belt_season_event_dystopia` |
| 5 | Assim!<br>`server_launch_dystopia_worldboss_defeated` | 6 | `dystopia_season_worldboss` | 2 | 5 / 18 / `boots_season_event_dystopia` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_dystopia` |
| reward_item2 | 7 | 20 | `sidekick_season_event_dystopia` |
| reward_item3 | 7 | 20 | `sidekick_season_event_dystopia` |

**Texto de sucesso:** Que desempenho estelar! Sua estratégia de defesa foi implementada 100% conforme as diretrizes do meu livro “A Morte Caminhante e Você - Um Guia para o Apocalipse Zumbi”! Confesso que tudo isso não passou de um cenário de treino bastante generoso. Mas, caso ocorra uma emergência, o que é praticamente uma questão de tempo, agora eu sei que posso contar com você!

**Texto de fim sem sucesso:** Sente-se! Que fracasso. Decepção total. Felizmente, tudo isso foi apenas um cenário de treino em grande escala. Caso ocorra uma emergência de verdade, o que é praticamente uma questão de tempo, você terá que se esforçar um pouquinho mais, senão estamos todos ferrados!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#00C38E",
  "background_color_bottom": "#5F5F5F",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_dystopia",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_dystopia",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_dystopia",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_dystopia_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 150,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_dystopia"
    },
    "server_launch_dystopia_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_dystopia_eventitem",
      "type": 7,
      "reference": "server_launch_dystopia_gardengnome",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_dystopia"
    },
    "server_launch_dystopia_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 7,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_dystopia"
    },
    "server_launch_dystopia_training_completed": {
      "index": 4,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 60,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_dystopia"
    },
    "server_launch_dystopia_worldboss_defeated": {
      "index": 5,
      "icon_asset": "server_launch_dystopia_worldboss_defeated",
      "type": 6,
      "reference": "dystopia_season_worldboss",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_dystopia"
    }
  }
}
```

### 6.20. Expedição a Atlantice

Identificador: `server_launch_2023_exotic_places`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#63F7FF` / `#7B99B3`.

**Contacto:** Prof. Paxton O. Biruta

**Introdução:** “É a oportunidade do milênio! Uma constelação especialíssima se abriu momentaneamente entre as calotas de gelo, nos dando acesso à cidade lendária de Atlantice, que todos acreditavam ter sido perdida no gelo! Não temos muito tempo! Em breve, as geleiras mudarão de posição novamente e o acesso ficará fechado por mais 1000 anos. Preciso de ajuda heroica para essa expedição até a cidade perdida. Posso contar com você?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Volta de aquecimento<br>`server_launch_exoctic_places_duels_fought` | 9 | `` | 220 | 5 / 18 / `mask_season_event_exotic_places` |
| 2 | Mistérios no gelo<br>`server_launch_exoctic_places_eventitem` | 7 | `server_launch_exotic_places_ice` | 100 | 5 / 18 / `cape_season_ev_exotic_places` |
| 3 | Agasalhe-se<br>`server_launch_exoctic_places_shop_refresh` | 11 | `` | 10 | 5 / 18 / `suit_season_event_exotic_places` |
| 4 | Você não!<br>`server_launch_exoctic_places_worldboss_defeated` | 6 | `` | 4 | 5 / 18 / `belt_season_event_exotic_places` |
| 5 | Não esfrie<br>`server_launch_exoctic_places_training_completed` | 5 | `` | 90 | 5 / 18 / `boots_season_event_exotic_places` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_exotic_places` |
| reward_item2 | 7 | 20 | `sidekick_season_event_exotic_places` |
| reward_item3 | 7 | 20 | `sidekick_season_event_exotic_places` |

**Texto de sucesso:** “Fantástico! Quem teria imaginado que ainda existe um reino exuberante cheio de magia e maravilhas nessa cidade escondida embaixo do gelo? Sem você, esse segredo nunca teria sido revelado. Muitíssimo obrigado!”

**Texto de fim sem sucesso:** “Que pena... Não conseguimos chegar perto o suficiente para realmente aprender qualquer coisa sobre a cidade perdida, nem sobre a cultura de seus antigos habitantes. Pelo visto, essa expedição nos deixou com mais dúvidas do que respostas. Quem sabe, quando a passagem reabrir daqui a 1000 anos, nossos futuros colegas terão mais sorte.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#63F7FF",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_exotic_places",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_exotic_places",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_exotic_places",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_exoctic_places_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 220,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_exotic_places"
    },
    "server_launch_exoctic_places_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_exoctic_places_eventitem",
      "type": 7,
      "reference": "server_launch_exotic_places_ice",
      "value": 100,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_ev_exotic_places"
    },
    "server_launch_exoctic_places_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 10,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_exotic_places"
    },
    "server_launch_exoctic_places_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 4,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_exotic_places"
    },
    "server_launch_exoctic_places_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 90,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_exotic_places"
    }
  }
}
```

### 6.21. O Jardim Botânico

Identificador: `server_launch_2023_blooming_nature`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#00E186` / `#A145FF`.

**Contacto:** Margarida N. Arthur

**Introdução:** Oi, preciso da sua ajuda, urgente! Estou organizando a reabertura do Jardim Botânico, mas minha encomenda de plantas exóticas foi enviada num caminhão freezer por engano. Agora o pedido inteiro está estragado e a reabertura corre risco! Por favor me ajude, não sei o que fazer!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Fora, poluidores!<br>`server_launch_blooming_nature_duels_fought` | 9 | `` | 190 | 5 / 18 / `mask_season_event_blooming_nature` |
| 2 | Beleza Rara<br>`server_launch_blooming_nature_eventitem` | 7 | `server_launch_blooming_nature_lotus` | 80 | 5 / 18 / `cape_season_event_blooming_nature` |
| 3 | Bem Equipad[m:o\|f:a]<br>`server_launch_blooming_nature_shop_refresh` | 11 | `` | 7 | 5 / 18 / `suit_season_event_blooming_nature` |
| 4 | Paciência é uma virtude<br>`server_launch_blooming_nature_timequest_completed` | 1 | `` | 50 | 5 / 18 / `belt_season_event_blooming_nature` |
| 5 | Ação Potente<br>`server_launch_blooming_nature_training_completed` | 5 | `` | 80 | 5 / 18 / `boots_season_event_blooming_nature` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_blooming_nature` |
| reward_item2 | 7 | 20 | `sidekick_season_event_blooming_nature` |
| reward_item3 | 7 | 20 | `sidekick_season_event_blooming_nature` |

**Texto de sucesso:** Que maravilha! A reabertura foi um sucesso enorme! Agora, as visitas podem admirar plantas nativas e exóticas e ainda aprender bastante sobre como a natureza é linda e como nós podemos protegê-la. Sem o seu comprometimento, o jardim nunca teria ficado pronto a tempo. Muitíssimo obrigada!

**Texto de fim sem sucesso:** Puxa vida, infelizmente só conseguimos terminar metade do jardim antes do dia da reabertura. Agora, quem visitar o Jardim Botânico vai ter que se contentar com apenas algumas poucas plantas nativas. Infelizmente, não vai ser tão emocionante quanto eu esperava...

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#00E186",
  "background_color_bottom": "#A145FF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_blooming_nature",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_blooming_nature",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_blooming_nature",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_blooming_nature_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 190,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_blooming_nature"
    },
    "server_launch_blooming_nature_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_blooming_nature_eventitem",
      "type": 7,
      "reference": "server_launch_blooming_nature_lotus",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_blooming_nature"
    },
    "server_launch_blooming_nature_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 7,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_blooming_nature"
    },
    "server_launch_blooming_nature_timequest_completed": {
      "index": 4,
      "icon_asset": "timequest_completed",
      "type": 1,
      "reference": "",
      "value": 50,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_blooming_nature"
    },
    "server_launch_blooming_nature_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_blooming_nature"
    }
  }
}
```

### 6.22. Campeonato Heroico Internacional

Identificador: `server_launch_2023_scorching_heat`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#ECA500` / `#ED7300`.

**Contacto:** Sr. Miago

**Introdução:** Olá! Ouvi dizer que você está procurando um treinador para te preparar para o Campeonato Heroico Internacional. Na verdade eu já me aposentei, mas ouvi falar muito bem de você e quero te ajudar a ganhar o título. Mas só se for para começar agora! Você está pront[m:o|f:a]?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Sondar a concorrência<br>`server_launch_scorching_heat_duels_fought` | 9 | `` | 225 | 5 / 18 / `mask_season_event_scorching_heat` |
| 2 | Passe uma cera e comece a polir!<br>`server_launch_scorching_heat_eventitem` | 7 | `server_launch_scorching_item` | 90 | 5 / 18 / `cape_season_event_scorching_heat` |
| 3 | Bem Equipad[m:o\|f:a]<br>`server_launch_scorching_heat_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_season_event_scorching_heat` |
| 4 | Paciência e Foco<br>`server_launch_scorching_heat_timequest_completed` | 1 | `` | 50 | 5 / 18 / `belt_season_event_scorching_heat` |
| 5 | Músculos de Aço<br>`server_launch_scorching_heat_training_completed` | 5 | `` | 90 | 5 / 18 / `boots_season_event_scorching_heat` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_scorching_heat` |
| reward_item2 | 7 | 20 | `sidekick_season_event_scorching_heat` |
| reward_item3 | 7 | 20 | `sidekick_season_event_scorching_heat` |

**Texto de sucesso:** Bom trabalho! Agora você está pront[m:o|f:a] para entrar no ringue com seus adversários e derrotá-los todos. Agora vai lá conquistar esse título!

**Texto de fim sem sucesso:** Oh, minha Nossa Senhora, um pouquinho mais de esforço teria sido necessário para você alcançar sua melhor forma. De toda forma, te desejo boa sorte no campeonato... Você vai precisar!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#ECA500",
  "background_color_bottom": "#ED7300",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_scorching_heat",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_scorching_heat",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_scorching_heat",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_scorching_heat_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 225,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_scorching_heat"
    },
    "server_launch_scorching_heat_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_scorching_heat_eventitem",
      "type": 7,
      "reference": "server_launch_scorching_item",
      "value": 90,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_scorching_heat"
    },
    "server_launch_scorching_heat_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_scorching_heat"
    },
    "server_launch_scorching_heat_timequest_completed": {
      "index": 4,
      "icon_asset": "timequest_completed",
      "type": 1,
      "reference": "",
      "value": 50,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_scorching_heat"
    },
    "server_launch_scorching_heat_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 90,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_scorching_heat"
    }
  }
}
```

### 6.23. Aventuras fantásticas

Identificador: `server_launch_2023_fantastic_adventures`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFC77E` / `#00541B`.

**Contacto:** Professor

**Introdução:** “Oi! Sou o Professor! Eu vajo pelos universos à procura de seres mágicos feridos e os capturo com Cronobolas para lhes dar um novo lar seguro! Infelizmente, as bolas deram defeito e foram espalhadas pelo seu mundo - assim como seus habitantes indefesos! Me disseram que você não tem medo de um desafio... Me ajuda a encontrar meus bichinhos?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Busca chegando<br>`server_launch_fantastic_adventures_duels_fought` | 9 | `` | 190 | 5 / 18 / `mask_season_event_fantastic_adventures` |
| 2 | Uma criatura selvagem!<br>`server_launch_fantastic_adventures_eventitem` | 7 | `server_launch_fantastic_adventures_item` | 50 | 5 / 18 / `cape_season_event_fantastic_adventures` |
| 3 | Roupa despretensiosa<br>`server_launch_fantastic_adventures_shop_refresh` | 11 | `` | 5 | 5 / 18 / `suit_season_event_fantastic_adventures` |
| 4 | Valeu a espera<br>`server_launch_fantastic_adventures_timequest_completed` | 1 | `` | 30 | 5 / 18 / `belt_season_event_fantastic_adventures` |
| 5 | Estar preparado importa<br>`server_launch_fantastic_adventures_training_completed` | 5 | `` | 50 | 5 / 18 / `boots_season_event_fantastic_adventures` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_fantastic_adventures` |
| reward_item2 | 7 | 20 | `sidekick_season_event_fantastic_adventures` |
| reward_item3 | 7 | 20 | `sidekick_season_event_fantastic_adventures` |

**Texto de sucesso:** “Uau! Graças a você, todos os meus bichinhos estão sãos e salvos de novo. Muitíssimo obrigado pela sua ajuda!”

**Texto de fim sem sucesso:** “Ops... Você foi tão útil para a minha missão quanto meu peixinho dourado é para cortar lenha - sem querer ofender nem você nem o peixinho... Bem, por sorte, consegui pegar todos os fujões sozinho. Seja como for, obrigado por ter tentado.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFC77E",
  "background_color_bottom": "#00541B",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_fantastic_adventures",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_fantastic_adventures",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_fantastic_adventures",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_fantastic_adventures_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 190,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_fantastic_adventures"
    },
    "server_launch_fantastic_adventures_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_fantastic_adventures_eventitem",
      "type": 7,
      "reference": "server_launch_fantastic_adventures_item",
      "value": 50,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_fantastic_adventures"
    },
    "server_launch_fantastic_adventures_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 5,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_fantastic_adventures"
    },
    "server_launch_fantastic_adventures_timequest_completed": {
      "index": 4,
      "icon_asset": "timequest_completed",
      "type": 1,
      "reference": "",
      "value": 30,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_fantastic_adventures"
    },
    "server_launch_fantastic_adventures_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 50,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_fantastic_adventures"
    }
  }
}
```

### 6.24. Pão e Circos

Identificador: `server_launch_2024_mythical_gods`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#ECC700` / `#997400`.

**Contacto:** Crussle Rowe

**Introdução:** “Olá, meu nome é Crussle Rowe. Sou ator e diretor no Teatro Municipal. Minha última peça, ‘Pão e Circos’, vai estrear em breve, mas preciso de figurantes e, acima de tudo, [m:um consultor|f:uma consultora] de marketing competente! Ouvi dizer que você é a pessoa certa para esse trabalho! Pode me ajudar a fazer dessa estreia um sucesso?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Boa luta<br>`server_launch_mythical_gods_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_season_event_mythical_gods` |
| 2 | Caos de papel<br>`server_launch_mythical_gods_eventitem` | 7 | `server_launch_mythical_gods_item` | 70 | 5 / 18 / `cape_season_event_mythical_gods` |
| 3 | Propaganda com estilo<br>`server_launch_mythical_gods_shop_refresh` | 11 | `` | 7 | 5 / 18 / `suit_season_event_mythical_gods` |
| 4 | Estratégias Sofisticadas<br>`server_launch_mythical_gods_timequest_completed` | 1 | `` | 40 | 5 / 18 / `belt_season_event_mythical_gods` |
| 5 | Só por segurança<br>`server_launch_mythical_gods_training_completed` | 5 | `` | 70 | 5 / 18 / `boots_season_event_mythical_gods` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_mythical_gods` |
| reward_item2 | 7 | 20 | `sidekick_season_event_mythical_gods` |
| reward_item3 | 7 | 20 | `sidekick_season_event_mythical_gods` |

**Texto de sucesso:** “Que estreia! A casa está cheia, o público está vibrando, e eu vou contratar aqueles figurantes agora mesmo para a nossa próxima exibição. Obrigado pela sua ajuda! Eu jamais teria conseguido sem você!”

**Texto de fim sem sucesso:** “Putz, não deu tempo suficiente para procurar figurantes competentes e organizar todo o marketing ao mesmo tempo. Bem, tomara que o público se divirta mesmo assim.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#ECC700",
  "background_color_bottom": "#997400",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_mythical_gods",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_mythical_gods",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_mythical_gods",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_mythical_gods_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_mythical_gods"
    },
    "server_launch_mythical_gods_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_mythical_gods_eventitem",
      "type": 7,
      "reference": "server_launch_mythical_gods_item",
      "value": 70,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_mythical_gods"
    },
    "server_launch_mythical_gods_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 7,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_mythical_gods"
    },
    "server_launch_mythical_gods_timequest_completed": {
      "index": 4,
      "icon_asset": "timequest_completed",
      "type": 1,
      "reference": "",
      "value": 40,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_mythical_gods"
    },
    "server_launch_mythical_gods_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 70,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_mythical_gods"
    }
  }
}
```

### 6.25. Caos Virtual

Identificador: `server_launch_2024_tech`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Cyra

**Introdução:** Socorro! A rede VR “Humphreydale Virtual”, da Cyberfruit Systems, foi atacada e infectada com um vírus terrível, e agora tudo está se degringolando no mais absoluto caos: os sistemas de navegação VR das pessoas estão as fazendo baterem em postes e dirigirem por gramados e parques! Não sei mais o que fazer... Por favor, entre na Humphreydale Virtual com meus óculos VR de co-admin, me ajude a encontrar todos os traços do vírus, e acabe com ele de uma vez por todas!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Serviço de segurança virtual<br>`server_launch_tech_duels_fought` | 9 | `` | 250 | 5 / 18 / `mask_season_event_tech` |
| 2 | Ameaça localizada!<br>`server_launch_tech_eventitem` | 7 | `server_launch_tech_item` | 120 | 5 / 18 / `cape_season_event_tech` |
| 3 | De roupa nova<br>`server_launch_tech_shop_refresh` | 11 | `` | 10 | 5 / 18 / `suit_season_event_tech` |
| 4 | Bravões Malvados<br>`server_launch_tech_worldboss_defeated` | 6 | `` | 3 | 5 / 18 / `belt_season_event_tech` |
| 5 | Exercício faz bem!<br>`server_launch_tech_training_completed` | 5 | `` | 100 | 5 / 18 / `boots_season_event_tech` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_tech` |
| reward_item2 | 7 | 20 | `sidekick_season_event_tech` |
| reward_item3 | 7 | 20 | `sidekick_season_event_tech` |

**Texto de sucesso:** Inacreditável! Conseguimos! Sem a sua ajuda, o projeto experimental “Humphreydale Virtual” teria sido um fracasso absoluto. Talvez o projeto ainda não estivesse maduro o suficiente. Colhemos informação valiosa sobre como poderemos impedir que algo assim aconteça de novo no futuro. E também prendemos o grupo de cyber piratas que estavam por trás do vírus do caos. Ótimo trabalho!

**Texto de fim sem sucesso:** Putz, acho que precisaremos pausar o projeto “Humphreydale Virtual” por enquanto, até descobrirmos quem é o responsável e como algo como isso pode sequer acontecer. Que pena que não conseguimos descobrir muita coisa.

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_tech",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_tech",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_tech",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_tech_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 250,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_tech"
    },
    "server_launch_tech_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_tech_eventitem",
      "type": 7,
      "reference": "server_launch_tech_item",
      "value": 120,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_tech"
    },
    "server_launch_tech_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 10,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_tech"
    },
    "server_launch_tech_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 3,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_tech"
    },
    "server_launch_tech_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 100,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_tech"
    }
  }
}
```

### 6.26. A Corrida de Carrinhos de Rolimã Solares

Identificador: `sun_moon_stars_season_arc_event_2024`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#C200E5` / `#4299FF`.

**Contacto:** Helios, o Diretor da Corrida

**Introdução:** “Oi! Eu sou Helios, o Diretor da Corrida. Estamos organizando uma grande corrida de carrinhos de rolimã solares e precisamos da sua ajuda! Os vilões da Yoyodyne parecem ter maquinado um plano para manipular a corrida a seu favor. Eles querem roubar os aprimoramentos dos painéis solares dos carrinhos de rolimã dos outros competidores para sabotá-los. Você está pront[m:o|f:a] para detê-los?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Confronto Heroico<br>`server_launch_sun_moon_stars_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_season_event_sun_moon_stars` |
| 2 | Aprimoramentos de Painel Solar<br>`server_launch_sun_moon_stars_eventitem` | 7 | `sun_moon_stars_season_arc_event_2024_item` | 80 | 5 / 18 / `cape_season_event_sun_moon_stars` |
| 3 | Aquisição de Inovações<br>`server_launch_sun_moon_stars_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_season_event_sun_moon_stars` |
| 4 | Falcatrua de Proteção do Sol<br>`server_launch_sun_moon_stars_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_season_event_sun_moon_stars` |
| 5 | Treino Preparatório para a Corrida<br>`server_launch_sun_moon_stars_training_completed` | 5 | `` | 70 | 5 / 18 / `boots_season_event_sun_moon_stars` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_sun_moon_stars` |
| reward_item2 | 7 | 20 | `sidekick_season_event_sun_moon_stars` |
| reward_item3 | 7 | 20 | `sidekick_season_event_sun_moon_stars` |

**Texto de sucesso:** “Ótimo! Você conseguiu ser mais inteligente que os vilões da Yoyodyne e a corrida foi um sucesso retumbante. Os painéis solares funcionaram perfeitamente com os aprimoramentos. Obrigado pela sua ajuda!”

**Texto de fim sem sucesso:** “Ah, não! Os vilões da Yoyodyne conseguiram seguir seu plano e a corrida foi um negócio bem sem graça sem os aprimoramentos dos painéis solares. Da próxima vez, não deixe isso acontecer!”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#C200E5",
  "background_color_bottom": "#4299FF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_sun_moon_stars",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_sun_moon_stars",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_sun_moon_stars",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_sun_moon_stars_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_sun_moon_stars"
    },
    "server_launch_sun_moon_stars_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_sun_moon_stars_eventitem",
      "type": 7,
      "reference": "sun_moon_stars_season_arc_event_2024_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_sun_moon_stars"
    },
    "server_launch_sun_moon_stars_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_sun_moon_stars"
    },
    "server_launch_sun_moon_stars_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_sun_moon_stars"
    },
    "server_launch_sun_moon_stars_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 70,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_sun_moon_stars"
    }
  }
}
```

### 6.27. Pânico do RPG de Mesa

Identificador: `doom_and_darkness_season_arc_event_2024`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#B8FFC1` / `#007DAC`.

**Contacto:** Gary, o Dungeon Master

**Introdução:** “Sou eu, Gary, o Dungeon Master. Socorro! Nossa noite de RPG de mesa totalmente inofensiva ficou fora de controle! Durante a nossa sessão, sem querer invocamos uma criatura sombria de verdade que agora está ameaçando Humphreydale. Ela está transformando cidadãos comuns em estranhos NPCs de RPG! Você pode nos ajudar a pôr um fim nesse caos antes que toda a cidade de Humphreydale se transforme em um imenso RPG de live action?”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Quebre o Feitiço!<br>`server_launch_doom_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_season_event_doom_and_darkness` |
| 2 | Missão de coleção obrigatória<br>`server_launch_doom_eventitem` | 7 | `doom_and_darkness_season_arc_event_item` | 80 | 5 / 18 / `cape_season_event_doom_and_darkness` |
| 3 | Não se esqueça de pegar o butim!<br>`server_launch_doom_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_season_event_doom_and_darkness` |
| 4 | Chefão<br>`server_launch_doom_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_season_event_doom_and_darkness` |
| 5 | Suba de nível!<br>`server_launch_doom_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_season_event_doom_and_darkness` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_doom_and_darkness` |
| reward_item2 | 7 | 20 | `sidekick_season_event_doom_and_darkness` |
| reward_item3 | 7 | 20 | `sidekick_season_event_doom_and_darkness` |

**Texto de sucesso:** “Que incrível! Você derrotou a criatura sombria, e todos os cidadãos enfeitiçados voltaram ao normal. Graças a você, Humphreydale foi salva! Adoraríamos te receber na nossa próxima noite de RPG, se você estiver afim!”

**Texto de fim sem sucesso:** “Ops, o tiro saiu pela culatra. A criatura sombria continua rondando enfurecida e agora metade da população de Humphreydale está correndo por aí vestindo fantasias. Bem, pelo menos agora nós temos gente suficiente para o nosso próximo jogo de RPG...”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#B8FFC1",
  "background_color_bottom": "#007DAC",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_doom_and_darkness",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_doom_and_darkness",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_doom_and_darkness",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_doom_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_doom_and_darkness"
    },
    "server_launch_doom_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_doom_eventitem",
      "type": 7,
      "reference": "doom_and_darkness_season_arc_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_doom_and_darkness"
    },
    "server_launch_doom_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_doom_and_darkness"
    },
    "server_launch_doom_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_doom_and_darkness"
    },
    "server_launch_doom_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_doom_and_darkness"
    }
  }
}
```

### 6.28. Magmântida Perdida

Identificador: `server_launch_2025_arctic_exploration`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Professor Hemslow

**Introdução:** Olá, aqui é o Professor Hemslow! Encontramos a cidade perdida de Magmântida debaixo de um vulcão e agora precisamos agir rápido! O vulcão está prestes a entrar em erupção, o que significa a destruição total desta antiga maravilha do mundo! Antes que isso aconteça, precisamos reunir a maior quantidade possível de dados e artefatos para preservá-los para a posteridade. Pode nos ajudar?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Luta Quente<br>`server_launch_arctic_exploration_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_season_event_arctic_exploration` |
| 2 | Um Pedaço da História<br>`server_launch_arctic_exploration_eventitem` | 7 | `arctic_exploration_season_arc_event_item` | 80 | 5 / 18 / `cape_season_event_arctic_exploration` |
| 3 | Colecionadores de artefatos com estilo<br>`server_launch_arctic_exploration_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_season_event_arctic_exploration` |
| 4 | Processo Curto<br>`server_launch_arctic_exploration_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_season_event_arctic_exploration` |
| 5 | Fique em forma!<br>`server_launch_arctic_exploration_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_season_event_arctic_exploration` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_arctic_exploration` |
| reward_item2 | 7 | 20 | `sidekick_season_event_arctic_exploration` |
| reward_item3 | 7 | 20 | `sidekick_season_event_arctic_exploration` |

**Texto de sucesso:** Chegamos a tempo! Eba! Os artefatos estão seguros e a caminho dos museus. As gerações futuras poderão aproveitá-los por muito tempo! Obrigado!

**Texto de fim sem sucesso:** Ah, não, o vulcão está prestes a entrar em erupção e é muito perigoso ficar em Magmântida. Que triste fim para os belos artefatos antigos!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_arctic_exploration",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_arctic_exploration",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_arctic_exploration",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_arctic_exploration_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_arctic_exploration"
    },
    "server_launch_arctic_exploration_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_arctic_exploration_eventitem",
      "type": 7,
      "reference": "arctic_exploration_season_arc_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_arctic_exploration"
    },
    "server_launch_arctic_exploration_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_arctic_exploration"
    },
    "server_launch_arctic_exploration_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_arctic_exploration"
    },
    "server_launch_arctic_exploration_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_arctic_exploration"
    }
  }
}
```

### 6.29. A Conspiração do Chocolate

Identificador: `server_launch_2025_mayan_empire`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFD576` / `#EA6800`.

**Contacto:** Coco Latté

**Introdução:** Olá! Era para ter uma exposição em Humphreydale sobre a história do chocolate maia, mas uma gente esquisita vestindo trajes encapuzados roubou os antigos artefatos de chocolate de valor inestimável. Eles planejam derretê-los para invocar Chocoatl, o demônio do chocolate. A criatura pode ser apenas uma lenda, mas é melhor não arriscar! Detenha essa gente estranha e salve a exposição - e possivelmente toda a cidade de Humphreydale!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Abram alas!<br>`server_launch_mayan_empire_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_season_event_mayan_empire` |
| 2 | Rastro de chocolate<br>`server_launch_mayan_empire_eventitem` | 7 | `mayan_empire_season_arc_event_item` | 80 | 5 / 18 / `cape_season_event_mayan_empire` |
| 3 | Roupa confortável<br>`server_launch_mayan_empire_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_season_event_mayan_empire` |
| 4 | Fim da linha para os vilões!<br>`server_launch_mayan_empire_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_season_event_mayan_empire` |
| 5 | Camarão que dorme a onda leva!<br>`server_launch_mayan_empire_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_season_event_mayan_empire` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_season_event_mayan_empire` |
| reward_item2 | 7 | 20 | `sidekick_season_event_mayan_empire` |
| reward_item3 | 7 | 20 | `sidekick_season_event_mayan_empire` |

**Texto de sucesso:** Que boa notícia, você conseguiu! Tirando um pequeno pedaço de chocolate que, aparentemente, foi fundido um pedaço inútil em formato de ovo, todas as obras estão ilesas! A exposição pode continuar como planejado e não há sinal nenhum do tal lendário Chocoatl. Que bom! Excelente trabalho!

**Texto de fim sem sucesso:** Ah, não, demoramos demais! A maior parte do chocolate já foi derretida... Mas, pelo menos, toda aquela bobagem de Chocoatl era apenas uma lenda. Que sorte a nossa!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFD576",
  "background_color_bottom": "#EA6800",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_season_event_mayan_empire",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_season_event_mayan_empire",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_season_event_mayan_empire",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_mayan_empire_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_season_event_mayan_empire"
    },
    "server_launch_mayan_empire_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_mayan_empire_eventitem",
      "type": 7,
      "reference": "mayan_empire_season_arc_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_season_event_mayan_empire"
    },
    "server_launch_mayan_empire_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_season_event_mayan_empire"
    },
    "server_launch_mayan_empire_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_season_event_mayan_empire"
    },
    "server_launch_mayan_empire_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_season_event_mayan_empire"
    }
  }
}
```

### 6.30. O Festival do Solstício de Verão

Identificador: `midsummer`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFFE34` / `#00CD32`.

**Contacto:** Bert Bloom

**Introdução:** Oi, aqui é o Beto Botão. Fui escalado para ser o mestre de cerimônias do Festival de Solstício de Verão na praça da cidade, mas minha rinite alérgica está me incomodando muito. Você pode me substituir e assumir como mestre de cerimônias? Não se preocupe, eu te ajudo a se preparar. Vamos te deixar pront[m:o|f:a] para o palco!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Compras!<br>`midsummer_shop_refreshed` | 11 | `all` | 9 | 3 / 0.5 / `` |
| 2 | Toque final<br>`midsummer_training_completed` | 5 | `all` | 90 | 4 / 0.4 / `` |
| 3 | Roupa Lavada Cheirosa<br>`midsummer_washing_machine_used` | 10 | `all` | 7 | 3 / 0.5 / `` |
| 4 | multitarefas<br>`midsummer_energy_used` | 4 | `` | 2200 | 4 / 0.5 / `` |
| 5 | Febre das Mesas de Ofertas!<br>`midsummer_fightquest_won` | 2 | `all` | 155 | 1 / 0.6 / `` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `random_epic` |
| reward_item2 | 7 | 20 | `random_epic` |
| reward_item3 | 3 | 10 | `` |

**Texto de sucesso:** Uau, que espetáculo! Você organizou o Festival de Solstício de Verão como um profissional. Humphreydale te ama! Bom trabalho!

**Texto de fim sem sucesso:** Que pena que você precisou cancelar na última hora - você certamente tinha seus motivos. Agora eu tenho que subir ao palco com o nariz escorrendo e os olhos lacrimejando.

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFFE34",
  "background_color_bottom": "#00CD32",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "random_epic",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "random_epic",
  "reward_item2_factor": 20,
  "reward_item3_type": 3,
  "reward_item3_flag": "",
  "reward_item3_factor": 10,
  "objectives": {
    "midsummer_shop_refreshed": {
      "index": 1,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "all",
      "value": 9,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "midsummer_training_completed": {
      "index": 2,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 90,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "midsummer_washing_machine_used": {
      "index": 3,
      "icon_asset": "washing_machine_used",
      "type": 10,
      "reference": "all",
      "value": 7,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "midsummer_energy_used": {
      "index": 4,
      "icon_asset": "energy_used",
      "type": 4,
      "reference": "",
      "value": 2200,
      "reward_type": 4,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "midsummer_fightquest_won": {
      "index": 5,
      "icon_asset": "fightquest_won",
      "type": 2,
      "reference": "all",
      "value": 155,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.31. A receita secreta

Identificador: `server_launch_2025_heroic_feast_appetizer`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FFEEE6` / `#A1D0FF`.

**Contacto:** Mestre Sushito

**Introdução:** Oi! Eu sou o Sushimaster Sushito - e preciso desesperadamente da sua ajuda! Roubaram minha receita secreta experimental do revolucionário Mega Maki Sushito! E o pior é que ela nem foi testada ainda! Se alguém prepará-lo errado, minha reputação como lenda do sushi pode ser prejudicada irremediavelmente. Você precisa encontrar os ladrões antes que tudo acabe em um desastre culinário!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Bem [m:vestido\|f:vestida]<br>`heroic_feast_appetizer_shop_refreshed` | 11 | `all` | 9 | 3 / 0.5 / `` |
| 2 | Treinamento especial<br>`heroic_feast_appetizer_training_completed` | 5 | `all` | 90 | 4 / 0.4 / `` |
| 3 | Impecável<br>`heroic_feast_appetizer_washing_machine_used` | 10 | `all` | 7 | 3 / 0.5 / `` |
| 4 | Energia Cheia<br>`heroic_feast_appetizer_energy_used` | 4 | `` | 2200 | 5 / 18 / `weapon_heroic_feast_appetizer` |
| 5 | A qualquer custo<br>`heroic_feast_appetizer_fightquest_won` | 2 | `all` | 155 | 1 / 0.6 / `` |
| 6 | Terríveis tentativas de sabotagem<br>`heroic_feast_appetizer_worldboss_defeated` | 6 | `` | 4 | 5 / 18 / `gadget_heroic_feast_appetizer` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_heroic_feast_appetizer` |
| reward_item2 | 7 | 20 | `sidekick_heroic_feast_appetizer` |
| reward_item3 | 7 | 20 | `sidekick_heroic_feast_appetizer` |

**Texto de sucesso:** Ufa, você conseguiu encontrar minha receita secreta de Mega Maki a tempo! Minha reputação está salva, e graças a você, eu posso continuar trabalhando na explosão de sabor definitiva. Acredite, o mundo do sushi está à beira de uma revolução!

**Texto de fim sem sucesso:** Ah, não! A receita apareceu, mas infelizmente em péssimas condições! Está toda rasgada e encharcada, a ponto de ser impossível ler a lista de ingredientes. E, claro, era minha única cópia. Parece que vou ter que começar tudo de novo. Que pena!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FFEEE6",
  "background_color_bottom": "#A1D0FF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_heroic_feast_appetizer",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_heroic_feast_appetizer",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_heroic_feast_appetizer",
  "reward_item3_factor": 20,
  "objectives": {
    "heroic_feast_appetizer_energy_used": {
      "index": 4,
      "icon_asset": "energy_used",
      "type": 4,
      "reference": "",
      "value": 2200,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "weapon_heroic_feast_appetizer"
    },
    "heroic_feast_appetizer_washing_machine_used": {
      "index": 3,
      "icon_asset": "washing_machine_used",
      "type": 10,
      "reference": "all",
      "value": 7,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "heroic_feast_appetizer_training_completed": {
      "index": 2,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "all",
      "value": 90,
      "reward_type": 4,
      "reward_factor": 0.4,
      "reward_reference": ""
    },
    "heroic_feast_appetizer_worldboss_defeated": {
      "index": 6,
      "icon_asset": "heroic_feast_appetizer_worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 4,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "gadget_heroic_feast_appetizer"
    },
    "heroic_feast_appetizer_shop_refreshed": {
      "index": 1,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "all",
      "value": 9,
      "reward_type": 3,
      "reward_factor": 0.5,
      "reward_reference": ""
    },
    "heroic_feast_appetizer_fightquest_won": {
      "index": 5,
      "icon_asset": "fightquest_won",
      "type": 2,
      "reference": "all",
      "value": 155,
      "reward_type": 1,
      "reward_factor": 0.6,
      "reward_reference": ""
    }
  }
}
```

### 6.32. Surto das feras blindadas

Identificador: `server_launch_2025_october`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#E6D5BB` / `#7B99B3`.

**Contacto:** Guarda Florestal Roxie

**Introdução:** “Ei, [m:herói|f:heroína]! Aqui é a Guarda Florestal Roxie do Santuário de Criaturas Extraordinárias ultrassecreto. Você não vai acreditar: uma criatura enorme com pele blindada cheia de espinhos escapou do nosso cercado de águas profundas - e ainda libertou seus amigos monstruosos. Eles são mansos, mas estão todos perambulando juntos por Humphreydale e destruindo tudo! Precisamos da sua ajuda para capturá-los antes que a Prefeitura fique parecendo uma marmita amassada.”

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Não tem nada para ver aqui!<br>`server_launch_2025_october_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_server_launch_2025_october` |
| 2 | A isca perfeita<br>`server_launch_2025_october_eventitem` | 7 | `server_launch_2025_october_event_item` | 80 | 5 / 18 / `cape_server_launch_2025_october` |
| 3 | Só por segurança<br>`server_launch_2025_october_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_server_launch_2025_october` |
| 4 | Feras são amigas, não comida!<br>`server_launch_2025_october_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_server_launch_2025_october` |
| 5 | Não desista<br>`server_launch_2025_october_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_server_launch_2025_october` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_server_launch_2025_october` |
| reward_item2 | 7 | 20 | `sidekick_server_launch_2025_october` |
| reward_item3 | 7 | 20 | `sidekick_server_launch_2025_october` |

**Texto de sucesso:** “Ótimo! Você conseguiu atrair as feras de volta para seus currais - até o enorme alfa finalmente desceu da torre da prefeitura. Você mais do que mereceu seu lugar na história heroica de Humphreydale!”

**Texto de fim sem sucesso:** “Bem, não foi exatamente como planejado, mas as feras voltaram voluntariamente para seus currais e foram dormir em seus ninhos. Pelo visto, passear na cidade é bem cansativo, mesmo para feras monstruosas.”

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#E6D5BB",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_server_launch_2025_october",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_server_launch_2025_october",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_server_launch_2025_october",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_2025_october_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_server_launch_2025_october"
    },
    "server_launch_2025_october_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_2025_october_eventitem",
      "type": 7,
      "reference": "server_launch_2025_october_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_server_launch_2025_october"
    },
    "server_launch_2025_october_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_server_launch_2025_october"
    },
    "server_launch_2025_october_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_server_launch_2025_october"
    },
    "server_launch_2025_october_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_server_launch_2025_october"
    }
  }
}
```

### 6.33. Salve a Zeronita!

Identificador: `server_launch_2026_overgrown`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#8D00A2` / `#7B99B3`.

**Contacto:** Rick Blurry

**Introdução:** Socorro! A Yoyodyne se infiltrou na comunidade de super-heróis e começou a espionar esconderijos heroicos! O objetivo deles é encontrar os maiores estoques de Zeronita e saqueá-los completamente. Você não pode deixar isso acontecer. Como os super-heróis vão continuar aprimorando seus esconderijos sem Zeronita? Pare essa gangue de criminosos!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Espiões... Espiões por toda parte!<br>`server_launch_2026_overgrown_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_server_launch_2026_overgrown` |
| 2 | Negócios empoeirados<br>`server_launch_2026_overgrown_eventitem` | 7 | `server_launch_2026_overgrown_event_item` | 80 | 5 / 18 / `cape_server_launch_2026_overgrown` |
| 3 | Como um camaleão<br>`server_launch_2026_overgrown_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_server_launch_2026_overgrown` |
| 4 | Chega de vilania!<br>`server_launch_2026_overgrown_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_server_launch_2026_overgrown` |
| 5 | Músculos de Zeronita<br>`server_launch_2026_overgrown_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_server_launch_2026_overgrown` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_server_launch_2026_overgrown` |
| reward_item2 | 7 | 20 | `sidekick_server_launch_2026_overgrown` |
| reward_item3 | 7 | 20 | `sidekick_server_launch_2026_overgrown` |

**Texto de sucesso:** É o fim da Yoyodyne! Com a sua ajuda, os vilões foram capturados e as reservas de Zeronita estão a salvo. A comunidade de super-heróis pode respirar aliviada... e você merece um tapinha nas costas!

**Texto de fim sem sucesso:** Droga, os espiões da Yoyodyne conseguiram saquear alguns estoques de Zeronita antes de tudo ficar arriscado demais e agora recuaram e se esconderam. Se eles tentarem de novo, é bom você estar [m:pronto|f:pronta] para enfrentá-los!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#8D00A2",
  "background_color_bottom": "#7B99B3",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_server_launch_2026_overgrown",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_server_launch_2026_overgrown",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_server_launch_2026_overgrown",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_2026_overgrown_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_server_launch_2026_overgrown"
    },
    "server_launch_2026_overgrown_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_2026_overgrown_eventitem",
      "type": 7,
      "reference": "server_launch_2026_overgrown_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_server_launch_2026_overgrown"
    },
    "server_launch_2026_overgrown_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_server_launch_2026_overgrown"
    },
    "server_launch_2026_overgrown_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_server_launch_2026_overgrown"
    },
    "server_launch_2026_overgrown_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_server_launch_2026_overgrown"
    }
  }
}
```

### 6.34. O festival de cinema retrô

Identificador: `server_launch_2026_gangsta1`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FF5273` / `#FFDBFF`.

**Contacto:** Jacky Jackpot

**Introdução:** Ah, não... O Festival de Cinema Retrô deste ano ia exibir os clássicos dos anos 30 aos 60, mas nosso rolo de filme com os melhores momentos do rock’n’roll dos anos 50 molhou e estragou! Sem esse filme, vai ficar um buraco na programação do tamanho de um drive-in! Ouvi dizer que você é especialista em improviso. Será que você consegue salvar o evento com um espetáculo improvisado?

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Brilho de norte a sul com HeroWool!<br>`server_launch_2026_gangsta1_washing_machine_used` | 10 | `all` | 10 | 5 / 18 / `mask_server_launch_2026_gangsta1` |
| 2 | Para o melhor penteado<br>`server_launch_2026_gangsta1_eventitem` | 7 | `server_launch_2026_gangsta1_event_item` | 80 | 5 / 18 / `cape_server_launch_2026_gangsta1` |
| 3 | Baú de Fantasias<br>`server_launch_2026_gangsta1_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_server_launch_2026_gangsta1` |
| 4 | Testar figurantes<br>`server_launch_2026_gangsta1_fightquest_won` | 2 | `all` | 130 | 5 / 18 / `belt_server_launch_2026_gangsta1` |
| 5 | Ensaio geral<br>`server_launch_2026_gangsta1_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_server_launch_2026_gangsta1` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_server_launch_2026_gangsta1` |
| reward_item2 | 7 | 20 | `sidekick_server_launch_2026_gangsta1` |
| reward_item3 | 7 | 20 | `sidekick_server_launch_2026_gangsta1` |

**Texto de sucesso:** Você conseguiu! Seu espetáculo improvisado no estilo dos anos 50 foi o destaque absoluto da noite. Com penteados perfeitamente estilizados e figurantes motivados, tudo estava perfeito e o público ficou encantado. Valeu por todo esse esforço!

**Texto de fim sem sucesso:** Ah... bem... O que dizer? Com um pouquinho mais de esforço, isso poderia ter dado certo. Em vez disso, o público ganhou um intervalo mais longo. Talvez da próxima vez seja melhor.

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FF5273",
  "background_color_bottom": "#FFDBFF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_server_launch_2026_gangsta1",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_server_launch_2026_gangsta1",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_server_launch_2026_gangsta1",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_2026_gangsta1_washing_machine_used": {
      "index": 1,
      "icon_asset": "washing_machine_used",
      "type": 10,
      "reference": "all",
      "value": 10,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_server_launch_2026_gangsta1"
    },
    "server_launch_2026_gangsta1_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_2026_gangsta1_eventitem",
      "type": 7,
      "reference": "server_launch_2026_gangsta1_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_server_launch_2026_gangsta1"
    },
    "server_launch_2026_gangsta1_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_server_launch_2026_gangsta1"
    },
    "server_launch_2026_gangsta1_fightquest_won": {
      "index": 4,
      "icon_asset": "fightquest_won",
      "type": 2,
      "reference": "all",
      "value": 130,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_server_launch_2026_gangsta1"
    },
    "server_launch_2026_gangsta1_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_server_launch_2026_gangsta1"
    }
  }
}
```

### 6.35. A Cruzada dos Cubos

Identificador: `server_launch_2026_cube`. Intervalo do template: `2012-01-01` → `2012-01-02`. Cores: `#FF84F7` / `#8400EF`.

**Contacto:** Raíssa Rabisco

**Introdução:** Preciso desesperadamente da sua ajuda! O professor de geometria, Prof. Quina de Ângulo, que é obcecado por textos antigos, afirma ter descoberto, em um antigo cubo cheio de inscrições, que o próprio universo é um cubo - e agora ele quer banir todas as coisas redondas de Humphreydale! Na fábrica de donuts, ele já trocou todas as formas de assar por monstruosidades retangulares. Pare essa loucura ortogonal e salve nossos anéis açucarados antes que a única coisa que saia da linha de produção sejam tristes blocos de massa!

| Índice | Objetivo / identificador | Tipo | Referência | Meta | Prémio: tipo / fator / referência |
| --- | --- | --- | --- | --- | --- |
| 1 | Soltem esses moldes!<br>`server_launch_2026_cube_duels_fought` | 9 | `` | 175 | 5 / 18 / `mask_server_launch_2026_cube` |
| 2 | Isso não está nada redondo!<br>`server_launch_2026_cube_eventitem` | 7 | `server_launch_2026_cube_event_item` | 80 | 5 / 18 / `cape_server_launch_2026_cube` |
| 3 | À paisana<br>`server_launch_2026_cube_shop_refresh` | 11 | `` | 8 | 5 / 18 / `suit_server_launch_2026_cube` |
| 4 | Chega de vilania<br>`server_launch_2026_cube_worldboss_defeated` | 6 | `` | 2 | 5 / 18 / `belt_server_launch_2026_cube` |
| 5 | Calorias? É de comer?<br>`server_launch_2026_cube_training_completed` | 5 | `` | 75 | 5 / 18 / `boots_server_launch_2026_cube` |

**Prémios finais configurados (valores brutos):**

| Campo | Tipo | Fator | Referência/flag |
| --- | --- | --- | --- |
| reward1 | 3 | 10 | `` |
| reward2 | 2 | 5 | `` |
| reward_item1 | 7 | 20 | `sidekick_server_launch_2026_cube` |
| reward_item2 | 7 | 20 | `sidekick_server_launch_2026_cube` |
| reward_item3 | 7 | 20 | `sidekick_server_launch_2026_cube` |

**Texto de sucesso:** Você conseguiu! Os moldes redondos voltaram aos seus lugares e o suposto cubo milenar cheio de inscrições acabou se revelando ser nada mais que um adereço impresso em 3D para uma aula de geometria da 6ª série. Os donuts voltaram a ser redondos e maravilhosamente pegajosos, como devem ser!

**Texto de fim sem sucesso:** Ah, não... O canalha foi capturado, mas os moldes redondos foram todos destruídos e as criações massudas e quadradas já estão se acumulando na Fábrica de Donuts. Agora eu preciso arredondar os cantos dessas coisas e conseguir novos moldes o quanto antes!

**Definição completa nas constantes:**

```json
{
  "start_date": "2012-01-01",
  "end_date": "2012-01-02",
  "background_color_top": "#FF84F7",
  "background_color_bottom": "#8400EF",
  "reward1_type": 3,
  "reward1_factor": 10,
  "reward1_flag": "",
  "reward2_type": 2,
  "reward2_factor": 5,
  "reward2_flag": "",
  "reward_item1_type": 7,
  "reward_item1_flag": "sidekick_server_launch_2026_cube",
  "reward_item1_factor": 20,
  "reward_item2_type": 7,
  "reward_item2_flag": "sidekick_server_launch_2026_cube",
  "reward_item2_factor": 20,
  "reward_item3_type": 7,
  "reward_item3_flag": "sidekick_server_launch_2026_cube",
  "reward_item3_factor": 20,
  "objectives": {
    "server_launch_2026_cube_duels_fought": {
      "index": 1,
      "icon_asset": "duels_fought",
      "type": 9,
      "reference": "",
      "value": 175,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "mask_server_launch_2026_cube"
    },
    "server_launch_2026_cube_eventitem": {
      "index": 2,
      "icon_asset": "server_launch_2026_cube_eventitem",
      "type": 7,
      "reference": "server_launch_2026_cube_event_item",
      "value": 80,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "cape_server_launch_2026_cube"
    },
    "server_launch_2026_cube_shop_refresh": {
      "index": 3,
      "icon_asset": "shop_refreshed",
      "type": 11,
      "reference": "",
      "value": 8,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "suit_server_launch_2026_cube"
    },
    "server_launch_2026_cube_worldboss_defeated": {
      "index": 4,
      "icon_asset": "worldboss_defeated",
      "type": 6,
      "reference": "",
      "value": 2,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "belt_server_launch_2026_cube"
    },
    "server_launch_2026_cube_training_completed": {
      "index": 5,
      "icon_asset": "training_completed",
      "type": 5,
      "reference": "",
      "value": 75,
      "reward_type": 5,
      "reward_factor": 18,
      "reward_reference": "boots_server_launch_2026_cube"
    }
  }
}
```

## 7. Famílias relacionadas: catálogo completo de identificadores

Estas secções são inventários separados. Um nome temático semelhante não demonstra calendário comum nem dependência automática entre eventos.

### `event_draw_pools` — 55 definições

Sorteios: pools com prémios, pesos/chances e custos premium por entrada.

| # | Identificador |
| --- | --- |
| 1 | `beetle_draw_event` |
| 2 | `bear_draw_event` |
| 3 | `fox_draw_event` |
| 4 | `boar_draw_event` |
| 5 | `science_draw_event` |
| 6 | `lightning_draw_event` |
| 7 | `ghost_draw_event` |
| 8 | `revenge_draw_event` |
| 9 | `happiness_draw_event` |
| 10 | `buffalo_draw_event` |
| 11 | `hawk_draw_event` |
| 12 | `ant_draw_event` |
| 13 | `fire_draw_event` |
| 14 | `water_draw_event` |
| 15 | `air_draw_event` |
| 16 | `earth_draw_event` |
| 17 | `creativity_draw_event` |
| 18 | `old_gods_draw_event` |
| 19 | `pumpkins_draw_event` |
| 20 | `arcade_draw_event` |
| 21 | `video_games_draw_event` |
| 22 | `internet_draw_event` |
| 23 | `dinosaurs_draw_event` |
| 24 | `volcano_draw_event` |
| 25 | `mammoth_draw_event` |
| 26 | `aliens_draw_event` |
| 27 | `brawling_spirit_draw_event` |
| 28 | `distant_worlds_draw_event` |
| 29 | `country_life_draw_event` |
| 30 | `future_draw_event` |
| 31 | `apocalypse_draw_event` |
| 32 | `post_apocalypse_draw_event` |
| 33 | `magical_circus_draw_event` |
| 34 | `pool_party_draw_event` |
| 35 | `zombies_draw_event` |
| 36 | `rome_draw_event` |
| 37 | `greek_draw_event` |
| 38 | `steampunk_draw_event` |
| 39 | `corruption_draw_event` |
| 40 | `ice_kingdom_draw_event` |
| 41 | `bounties_of_seven_seas_draw_event` |
| 42 | `spooky_xmas_draw_event` |
| 43 | `wild_west_draw_event` |
| 44 | `bounty_hunter_draw_event` |
| 45 | `egypt_draw_event` |
| 46 | `arctic_exploration_draw_event` |
| 47 | `wild_savannah_draw_event` |
| 48 | `heroic_feast_main_course_draw_event` |
| 49 | `wasteland_draw_event` |
| 50 | `heroic_feast_dessert_draw_event` |
| 51 | `shadow_draw_event` |
| 52 | `holy_light_draw_event` |
| 53 | `elk_draw_event` |
| 54 | `elk_2_draw_event` |
| 55 | `shang_dynasty_draw_event` |

**Resumo dos prémios por definição:**

#### `beetle_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_beetle" | 60 | 0 |
| 2 | 12 | 20 | "improvement_sidekick_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_beetle" | 80 | 6 |
| 4 | 12 | 20 | "improvement_cape_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_beetle" | 100 | 12 |
| 6 | 12 | 20 | "improvement_gadget_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_beetle" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_beetle" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_beetle" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_beetle" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_beetle" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_beetle" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `bear_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_bear" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_bear" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_bear" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_bear" | 140 | 35 |
| 8 | 12 | 20 | "improvement_sidekick_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_bear" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_bear" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_bear" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_bear" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_bear" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `fox_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_fox" | 60 | 0 |
| 2 | 12 | 20 | "improvement_gadget_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_fox" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_fox" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_fox" | 140 | 35 |
| 8 | 12 | 20 | "improvement_sidekick_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_fox" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_fox" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_fox" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_fox" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_fox" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `boar_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_boar" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_boar" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_boar" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_boar" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_boar" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_boar" | 250 | 49 |
| 11 | 12 | 20 | "improvement_boots_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_boar" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_boar" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_boar" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `science_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_science" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_science" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_science" | 100 | 12 |
| 6 | 12 | 20 | "improvement_mask_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_science" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_science" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_science" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_science" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_science" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_science" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `lightning_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_lightning" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_lightning" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_lightning" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_lightning" | 140 | 35 |
| 8 | 12 | 20 | "improvement_weapon_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_lightning" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_lightning" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_lightning" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_lightning" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_lightning" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `ghost_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_ghost" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_ghost" | 80 | 6 |
| 4 | 12 | 20 | "improvement_weapon_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_ghost" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_ghost" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_ghost" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_ghost" | 250 | 49 |
| 11 | 12 | 20 | "improvement_belt_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_ghost" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_ghost" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_ghost" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `revenge_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_revenge" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_revenge" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_revenge" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_revenge" | 140 | 35 |
| 8 | 12 | 20 | "improvement_belt_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_revenge" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_revenge" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_revenge" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_revenge" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_revenge" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `happiness_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_happiness" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_happiness" | 80 | 6 |
| 4 | 12 | 20 | "improvement_suit_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_happiness" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_happiness" | 140 | 35 |
| 8 | 12 | 20 | "improvement_mask_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_happiness" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_happiness" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_happiness" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_happiness" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_happiness" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `buffalo_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_buffalo" | 60 | 0 |
| 2 | 12 | 20 | "improvement_gadget_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_buffalo" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_buffalo" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_buffalo" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_buffalo" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_buffalo" | 250 | 49 |
| 11 | 12 | 20 | "improvement_mask_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_buffalo" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_buffalo" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_buffalo" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `hawk_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_hawk" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_hawk" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_hawk" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_hawk" | 140 | 35 |
| 8 | 12 | 20 | "improvement_mask_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_hawk" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_hawk" | 250 | 49 |
| 11 | 12 | 20 | "improvement_weapon_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_hawk" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_hawk" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_hawk" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `ant_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_ant" | 60 | 0 |
| 2 | 12 | 20 | "improvement_boots_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_ant" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_ant" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_ant" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_ant" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_ant" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_ant" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_ant" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_ant" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `fire_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_fire" | 60 | 0 |
| 2 | 12 | 20 | "improvement_gadget_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_fire" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_fire" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_fire" | 140 | 35 |
| 8 | 12 | 20 | "improvement_belt_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_fire" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_fire" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_fire" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_fire" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_fire" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `water_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_water" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_water" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_water" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_water" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_water" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_water" | 250 | 49 |
| 11 | 12 | 20 | "improvement_boots_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_water" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_water" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_water" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `air_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_air" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_air" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_air" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_air" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_air" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_air" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_air" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_air" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_air" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `earth_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_earth" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_earth" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_earth" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_earth" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_earth" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_earth" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_earth" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_earth" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_earth" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `creativity_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_creativity" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_creativity" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_creativity" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_seas_creativi" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_creativity" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_creativity" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_creativity" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_creativity" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_creativity" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `old_gods_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_old_gods" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_old_gods" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_old_gods" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_seas_old_gods" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_old_gods" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_old_gods" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_old_gods" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_old_gods" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_old_gods" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `pumpkins_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_pumpkins" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_pumpkins" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_pumpkins_1" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_pumpkins" | 140 | 35 |
| 8 | 5 | 20 | "suit_season_pumpkins_2" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_pumpkins" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_pumpkins" | 250 | 49 |
| 11 | 12 | 20 | "improvement_belt_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_pumpkins" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_pumpkins" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_pumpkins" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `arcade_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_arcade" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_arcade" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_arcade" | 100 | 12 |
| 6 | 12 | 20 | "improvement_gadget_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_arcade" | 140 | 35 |
| 8 | 12 | 20 | "improvement_weapon_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_arcade" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_arcade" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_arcade" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_arcade" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_arcade" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `video_games_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_video_games" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_video_games" | 80 | 6 |
| 4 | 12 | 20 | "improvement_cape_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_video_games" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_video_games" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_video_games" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_video_games" | 250 | 49 |
| 11 | 12 | 20 | "improvement_belt_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_video_games" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_video_games" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_video_games" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `internet_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_internet" | 60 | 0 |
| 2 | 12 | 20 | "improvement_boots_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_internet" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_internet" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_internet" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_internet" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_internet" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_internet" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_internet" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_internet" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `dinosaurs_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_dinosaurs" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_dinosaurs" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_dinosaurs" | 100 | 12 |
| 6 | 12 | 20 | "improvement_mask_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_dinosaurs" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_dinosaurs" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_dinosaurs" | 250 | 49 |
| 11 | 12 | 20 | "improvement_boots_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_dinosaurs" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_dinosaurs" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_dinosaurs" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `volcano_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_volcano_1" | 60 | 0 |
| 2 | 5 | 20 | "sidekick_season_volcano_2" | 70 | 3 |
| 3 | 12 | 20 | "improvement_gadget_legendary" | 80 | 6 |
| 4 | 5 | 20 | "mask_season_volcano" | 90 | 9 |
| 5 | 12 | 20 | "improvement_sidekick_epic" | 100 | 12 |
| 6 | 5 | 20 | "suit_season_volcano" | 110 | 25 |
| 7 | 12 | 20 | "improvement_cape_epic" | 140 | 35 |
| 8 | 5 | 20 | "cape_season_volcano" | 180 | 39 |
| 9 | 12 | 20 | "improvement_weapon_epic" | 200 | 45 |
| 10 | 5 | 20 | "missiles_season_volcano" | 250 | 49 |
| 11 | 5 | 20 | "gadget_season_volcano" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_volcano" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_volcano" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_volcano" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `mammoth_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_mammoth" | 60 | 0 |
| 2 | 12 | 20 | "improvement_cape_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_mammoth" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_mammoth" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_mammoth" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_mammoth" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_mammoth" | 250 | 49 |
| 11 | 12 | 20 | "improvement_belt_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_mammoth" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_mammoth" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_mammoth" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `aliens_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_aliens" | 60 | 0 |
| 2 | 12 | 20 | "improvement_boots_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_aliens" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_aliens" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_aliens" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_aliens" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_aliens" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_aliens" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_aliens" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_aliens" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `brawling_spirit_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_brawling_spirit" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_brawling_spirit" | 80 | 6 |
| 4 | 12 | 20 | "improvement_suit_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_brawling_spirit" | 100 | 12 |
| 6 | 12 | 20 | "improvement_mask_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_brawling_spirit" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_brawling_spirit" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_brawling_spirit" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_brawling_spirit" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_brawling_spirit" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_brawling_spirit" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `distant_worlds_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_distant_worlds" | 60 | 0 |
| 2 | 12 | 20 | "improvement_gadget_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_distant_worlds" | 80 | 6 |
| 4 | 12 | 20 | "improvement_boots_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_distant_worlds" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_distant_worlds" | 140 | 35 |
| 8 | 12 | 20 | "improvement_weapon_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_distant_worlds" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_distant_worlds" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_distant_worlds" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_distant_worlds" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_distant_worlds" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `country_life_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_country_life" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_country_life" | 80 | 6 |
| 4 | 12 | 20 | "improvement_weapon_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_country_life" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_country_life" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_country_life" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_country_life" | 250 | 49 |
| 11 | 12 | 20 | "improvement_belt_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_country_life" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_country_life" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_country_life" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `future_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_future" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_future" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_future" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_future" | 140 | 35 |
| 8 | 12 | 20 | "improvement_belt_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_future" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_future" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_future" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_future" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_future" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `apocalypse_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_apocalypse" | 60 | 0 |
| 2 | 12 | 20 | "improvement_cape_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_apocalypse" | 80 | 6 |
| 4 | 12 | 20 | "improvement_boots_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_apocalypse" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_apocalypse" | 140 | 35 |
| 8 | 12 | 20 | "improvement_belt_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_apocalypse" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_apocalypse" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_apocalypse" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_apocalypse" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_apocalypse" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `post_apocalypse_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_post_apocalypse" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_post_apocalypse" | 80 | 6 |
| 4 | 12 | 20 | "improvement_weapon_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_post_apocalypse" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_post_apocalypse" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_post_apocalypse" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_post_apocalypse" | 250 | 49 |
| 11 | 12 | 20 | "improvement_boots_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_post_apocalypse" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_post_apocalypse" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_post_apocalypse" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `magical_circus_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_magical_circus" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_magical_circus" | 80 | 6 |
| 4 | 12 | 20 | "improvement_cape_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_magical_circus" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_magical_circus" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_magical_circus" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_magical_circus" | 250 | 49 |
| 11 | 12 | 20 | "improvement_weapon_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_magical_circus" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_magical_circus" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_magical_circus" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `pool_party_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_pool_party" | 60 | 0 |
| 2 | 12 | 20 | "improvement_sidekick_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_pool_party" | 80 | 6 |
| 4 | 12 | 20 | "improvement_suit_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_pool_party" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_pool_party" | 140 | 35 |
| 8 | 12 | 20 | "improvement_weapon_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_pool_party" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_pool_party" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_pool_party" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_pool_party" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_pool_party" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `zombies_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_zombies" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_zombies" | 80 | 6 |
| 4 | 12 | 20 | "improvement_cape_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_zombies" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_zombies" | 140 | 35 |
| 8 | 12 | 20 | "improvement_weapon_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_zombies" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_zombies" | 250 | 49 |
| 11 | 12 | 20 | "improvement_suit_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_zombies" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_zombies" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_zombies" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `rome_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_rome" | 60 | 0 |
| 2 | 12 | 20 | "improvement_cape_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_rome" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_rome" | 100 | 12 |
| 6 | 12 | 20 | "improvement_belt_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_rome" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_rome" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_rome" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_rome" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_rome" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_rome" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `greek_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_greek" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_greek" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_greek" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_greek" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_greek" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_greek" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_greek" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_greek" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_greek" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `steampunk_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_steampunk" | 60 | 0 |
| 2 | 12 | 20 | "improvement_mask_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_steampunk" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_steampunk" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_steampunk" | 140 | 35 |
| 8 | 12 | 20 | "improvement_weapon_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_steampunk" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_steampunk" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_steampunk" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_steampunk" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_steampunk" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `corruption_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_corruption" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_corruption" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_corruption" | 100 | 12 |
| 6 | 12 | 20 | "improvement_gadget_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_corruption" | 140 | 35 |
| 8 | 12 | 20 | "improvement_mask_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_corruption" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_corruption" | 250 | 49 |
| 11 | 12 | 20 | "improvement_belt_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_corruption" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_corruption" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_corruption" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `ice_kingdom_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_ice_kingdom" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_ice_kingdom" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_ice_kingdom" | 100 | 12 |
| 6 | 12 | 20 | "improvement_cape_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_ice_kingdom" | 140 | 35 |
| 8 | 12 | 20 | "improvement_belt_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_ice_kingdom" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_ice_kingdom" | 250 | 49 |
| 11 | 12 | 20 | "improvement_weapon_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_ice_kingdom" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_ice_kingdom" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_ice_kingdom" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `bounties_of_seven_seas_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_bounties_of_seven_seas" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_bounties_of_seven_seas" | 80 | 6 |
| 4 | 12 | 20 | "improvement_suit_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_bounties_of_seven_seas" | 100 | 12 |
| 6 | 12 | 20 | "improvement_mask_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_bounties_of_seven_seas" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_bounties_of_seven_seas" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_bounties_of_seven_seas" | 250 | 49 |
| 11 | 12 | 20 | "improvement_sidekick_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_bounties_of_seven_seas" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_bounties_of_seven_seas" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_bounties_of_seven_seas" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `spooky_xmas_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_spooky_xmas" | 60 | 0 |
| 2 | 12 | 20 | "improvement_boots_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_spooky_xmas" | 80 | 6 |
| 4 | 12 | 20 | "improvement_belt_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_spooky_xmas" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_spooky_xmas" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_spooky_xmas" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_spooky_xmas" | 250 | 49 |
| 11 | 12 | 20 | "improvement_mask_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_spooky_xmas" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_spooky_xmas" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_spooky_xmas" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `wild_west_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_wild_west" | 60 | 0 |
| 2 | 12 | 20 | "improvement_boots_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_wild_west" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_wild_west" | 100 | 12 |
| 6 | 12 | 20 | "improvement_belt_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_wild_west" | 140 | 35 |
| 8 | 12 | 20 | "improvement_gadget_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_wild_west" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_wild_west" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_wild_west" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_wild_west" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_wild_west" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `bounty_hunter_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_bounty_hunter" | 60 | 0 |
| 2 | 12 | 20 | "improvement_boots_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_bounty_hunter" | 80 | 6 |
| 4 | 12 | 20 | "improvement_weapon_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_bounty_hunter" | 100 | 12 |
| 6 | 12 | 20 | "improvement_belt_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_bounty_hunter" | 140 | 35 |
| 8 | 12 | 20 | "improvement_mask_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_bounty_hunter" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_bounty_hunter" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_bounty_hunter" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_bounty_hunter" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_bounty_hunter" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `egypt_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_egypt" | 60 | 0 |
| 2 | 12 | 20 | "improvement_belt_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_egypt" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_egypt" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_egypt" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_egypt" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_egypt" | 250 | 49 |
| 11 | 12 | 20 | "improvement_weapon_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_egypt" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_egypt" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_egypt" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `arctic_exploration_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_arctic_exploration" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_arctic_exploration" | 80 | 6 |
| 4 | 12 | 20 | "improvement_cape_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_arctic_exploration" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_arctic_exploration" | 140 | 35 |
| 8 | 12 | 20 | "improvement_belt_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_arctic_exploration" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_arctic_exploration" | 250 | 49 |
| 11 | 12 | 20 | "improvement_weapon_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_arctic_exploration" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_arctic_exploration" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_arctic_exploration" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `wild_savannah_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_wild_savannah" | 60 | 0 |
| 2 | 12 | 20 | "improvement_weapon_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_wild_savannah" | 80 | 6 |
| 4 | 12 | 20 | "improvement_suit_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_wild_savannah" | 100 | 12 |
| 6 | 12 | 20 | "improvement_gadget_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_wild_savannah" | 140 | 35 |
| 8 | 12 | 20 | "improvement_mask_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_wild_savannah" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_wild_savannah" | 250 | 49 |
| 11 | 12 | 20 | "improvement_cape_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_wild_savannah" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_wild_savannah" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_wild_savannah" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `heroic_feast_main_course_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_heroic_feast_main_course" | 60 | 0 |
| 2 | 12 | 20 | "improvement_cape_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_heroic_feast_main_course" | 80 | 6 |
| 4 | 12 | 20 | "improvement_sidekick_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_heroic_feast_main_course" | 100 | 12 |
| 6 | 12 | 20 | "improvement_boots_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_heroic_feast_main_course" | 140 | 35 |
| 8 | 12 | 20 | "improvement_suit_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_heroic_feast_main_course" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_heroic_feast_main_course" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_heroic_feast_main_course" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_heroic_feast_main_course" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_heroic_feast_main_course" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `wasteland_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_wasteland" | 60 | 0 |
| 2 | 12 | 20 | "improvement_suit_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_wasteland" | 80 | 6 |
| 4 | 12 | 20 | "improvement_gadget_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_wasteland" | 100 | 12 |
| 6 | 12 | 20 | "improvement_weapon_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_wasteland" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_wasteland" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_wasteland" | 250 | 49 |
| 11 | 12 | 20 | "improvement_boots_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_wasteland" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_wasteland" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_wasteland" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `heroic_feast_dessert_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_heroic_feast_dessert" | 60 | 0 |
| 2 | 12 | 20 | "improvement_gadget_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_heroic_feast_dessert" | 80 | 6 |
| 4 | 12 | 20 | "improvement_weapon_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_heroic_feast_dessert" | 100 | 12 |
| 6 | 12 | 20 | "improvement_sidekick_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_heroic_feast_dessert" | 140 | 35 |
| 8 | 12 | 20 | "improvement_boots_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_heroic_feast_dessert" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_heroic_feast_dessert" | 250 | 49 |
| 11 | 12 | 20 | "improvement_mask_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_heroic_feast_dessert" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_heroic_feast_dessert" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_heroic_feast_dessert" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `shadow_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_shadow" | 60 | 0 |
| 2 | 5 | 20 | "mask_season_shadow_g" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_shadow_b" | 80 | 6 |
| 4 | 5 | 20 | "mask_season_shadow_y" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_shadow_g" | 100 | 12 |
| 6 | 5 | 20 | "suit_season_shadow_b" | 110 | 25 |
| 7 | 5 | 20 | "suit_season_shadow_y" | 140 | 35 |
| 8 | 5 | 20 | "cape_season_shadow" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_shadow" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_shadow" | 250 | 49 |
| 11 | 5 | 20 | "weapon_season_shadow" | 300 | 59 |
| 12 | 5 | 20 | "belt_season_shadow_g" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_shadow_b" | 400 | 79 |
| 14 | 5 | 20 | "belt_season_shadow_y" | 450 | 89 |
| 15 | 5 | 20 | "boots_season_shadow" | 500 | 99 |

#### `holy_light_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_holy_light" | 60 | 0 |
| 2 | 5 | 20 | "mask_season_holy_light" | 70 | 3 |
| 3 | 5 | 20 | "suit_season_holy_light_4" | 80 | 6 |
| 4 | 5 | 20 | "suit_season_holy_light_3" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_holy_light_2" | 100 | 12 |
| 6 | 5 | 20 | "suit_season_holy_light_1" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_holy_light_5" | 140 | 35 |
| 8 | 5 | 20 | "cape_season_holy_light_4" | 180 | 39 |
| 9 | 5 | 20 | "cape_season_holy_light_3" | 200 | 45 |
| 10 | 5 | 20 | "cape_season_holy_light_2" | 250 | 49 |
| 11 | 5 | 20 | "cape_season_holy_light_1" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_holy_light" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_holy_light_2" | 400 | 79 |
| 14 | 5 | 20 | "belt_season_holy_light_1" | 450 | 89 |
| 15 | 5 | 20 | "boots_season_holy_light" | 500 | 99 |

#### `elk_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_elk" | 60 | 0 |
| 2 | 5 | 20 | "mask_season_elk_bobble_b" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_elk_bobble_y" | 80 | 6 |
| 4 | 5 | 20 | "mask_season_elk_fur_bl" | 90 | 9 |
| 5 | 5 | 20 | "mask_season_elk_fur_r" | 100 | 12 |
| 6 | 5 | 20 | "mask_season_elk_fur_b" | 110 | 25 |
| 7 | 5 | 20 | "suit_season_elk_fur_bl" | 140 | 35 |
| 8 | 5 | 20 | "suit_season_elk_fur_r" | 180 | 39 |
| 9 | 5 | 20 | "suit_season_elk_fur_b" | 200 | 45 |
| 10 | 5 | 20 | "cape_season_elk" | 250 | 49 |
| 11 | 5 | 20 | "gadget_season_elk" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_elk" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_elk_r" | 400 | 79 |
| 14 | 5 | 20 | "belt_season_elk_b" | 450 | 89 |
| 15 | 5 | 20 | "boots_season_elk_rain" | 500 | 99 |

#### `elk_2_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_elk" | 60 | 0 |
| 2 | 5 | 20 | "mask_season_elk_knitted_r" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_elk_knitted_b" | 80 | 6 |
| 4 | 5 | 20 | "mask_season_elk_knitted_y" | 90 | 9 |
| 5 | 5 | 20 | "mask_season_elk_bobble_r" | 100 | 12 |
| 6 | 5 | 20 | "suit_season_elk_jacket_y" | 110 | 25 |
| 7 | 5 | 20 | "suit_season_elk_jacket_r" | 140 | 35 |
| 8 | 5 | 20 | "suit_season_elk_jacket_b" | 180 | 39 |
| 9 | 5 | 20 | "cape_season_elk" | 200 | 45 |
| 10 | 5 | 20 | "missiles_season_elk" | 250 | 49 |
| 11 | 5 | 20 | "gadget_season_elk" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_elk" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_elk_bl" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_elk_winter" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

#### `shang_dynasty_draw_event`

Metadados: `{"start_date":null,"end_date":null}`.

| Entrada | reward_type | reward_factor | reward_reference | chance | cost_premium_currency |
| --- | --- | --- | --- | --- | --- |
| 1 | 5 | 20 | "sidekick_season_shang_dynasty" | 60 | 0 |
| 2 | 12 | 20 | "improvement_sidekick_legendary" | 70 | 3 |
| 3 | 5 | 20 | "mask_season_shang_dynasty" | 80 | 6 |
| 4 | 12 | 20 | "improvement_mask_epic" | 90 | 9 |
| 5 | 5 | 20 | "suit_season_shang_dynasty" | 100 | 12 |
| 6 | 12 | 20 | "improvement_suit_epic" | 110 | 25 |
| 7 | 5 | 20 | "cape_season_shang_dynasty" | 140 | 35 |
| 8 | 12 | 20 | "improvement_cape_epic" | 180 | 39 |
| 9 | 5 | 20 | "missiles_season_shang_dynasty" | 200 | 45 |
| 10 | 5 | 20 | "gadget_season_shang_dynasty" | 250 | 49 |
| 11 | 12 | 20 | "improvement_gadget_rare" | 300 | 59 |
| 12 | 5 | 20 | "weapon_season_shang_dynasty" | 350 | 69 |
| 13 | 5 | 20 | "belt_season_shang_dynasty" | 400 | 79 |
| 14 | 5 | 20 | "boots_season_shang_dynasty" | 450 | 89 |
| 15 | 3 | 5 | "" | 500 | 99 |

### `event_treasures` — 47 definições

Tesouros: patamares de prémios e ligação a uma configuração de localização.

| # | Identificador |
| --- | --- |
| 1 | `revenge_treasure` |
| 2 | `happiness_treasure` |
| 3 | `buffalo_treasure` |
| 4 | `ice_kingdom_treasure` |
| 5 | `hawk_treasure` |
| 6 | `elk2_treasure` |
| 7 | `ghost_treasure` |
| 8 | `lightning_treasure` |
| 9 | `wild_west_treasure` |
| 10 | `bounties_of_seven_seas_treasure` |
| 11 | `boar_treasure` |
| 12 | `guardians_of_nature_treasure` |
| 13 | `bbq_treasure` |
| 14 | `pumpkins_treasure` |
| 15 | `flower_power_treasure` |
| 16 | `zombies_treasure` |
| 17 | `science_treasure` |
| 18 | `creativity_treasure` |
| 19 | `steampunk_treasure` |
| 20 | `fox_treasure` |
| 21 | `apocalypse_treasure` |
| 22 | `arcade_treasure` |
| 23 | `volcano_treasure` |
| 24 | `aliens_treasure` |
| 25 | `future_treasure` |
| 26 | `water_treasure` |
| 27 | `air_treasure` |
| 28 | `shadow_treasure` |
| 29 | `holy_light_treasure` |
| 30 | `bounty_hunter_treasure` |
| 31 | `magical_circus_treasure` |
| 32 | `fire_treasure` |
| 33 | `empire_of_the_sun_treasure` |
| 34 | `internet_treasure` |
| 35 | `empire_of_the_stars_treasure` |
| 36 | `post_apocalypse_treasure` |
| 37 | `witchcraft_treasure` |
| 38 | `greek_treasure` |
| 39 | `vampire_treasure` |
| 40 | `beetle_treasure` |
| 41 | `empire_of_the_moon_treasure` |
| 42 | `day_of_the_dead_treasure` |
| 43 | `mayan_empire_treasure` |
| 44 | `heroic_feast_appetizer_treasure` |
| 45 | `distant_worlds_treasure` |
| 46 | `armoured_beasts_1_treasure` |
| 47 | `corruption_treasure` |

**Resumo dos prémios por definição:**

#### `revenge_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_revenge" |
| 2 | 15 | 5 | 20 | "belt_season_revenge" |
| 3 | 25 | 5 | 20 | "weapon_season_revenge" |
| 4 | 40 | 5 | 20 | "gadget_season_revenge" |
| 5 | 55 | 5 | 20 | "missiles_season_revenge" |
| 6 | 70 | 5 | 20 | "cape_season_revenge" |
| 7 | 85 | 5 | 20 | "suit_season_revenge" |
| 8 | 100 | 5 | 20 | "mask_season_revenge" |
| 9 | 110 | 12 | 20 | "improvement_weapon_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_revenge" |

#### `happiness_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_happiness" |
| 2 | 15 | 5 | 20 | "belt_season_happiness" |
| 3 | 25 | 5 | 20 | "weapon_season_happiness" |
| 4 | 40 | 5 | 20 | "gadget_season_happiness" |
| 5 | 55 | 5 | 20 | "missiles_season_happiness" |
| 6 | 70 | 5 | 20 | "cape_season_happiness" |
| 7 | 85 | 5 | 20 | "suit_season_happiness" |
| 8 | 100 | 5 | 20 | "mask_season_happiness" |
| 9 | 110 | 12 | 20 | "improvement_mask_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_happiness" |

#### `buffalo_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_buffalo" |
| 2 | 15 | 5 | 20 | "belt_season_buffalo" |
| 3 | 25 | 5 | 20 | "weapon_season_buffalo" |
| 4 | 40 | 5 | 20 | "gadget_season_buffalo" |
| 5 | 55 | 5 | 20 | "missiles_season_buffalo" |
| 6 | 70 | 5 | 20 | "cape_season_buffalo" |
| 7 | 85 | 5 | 20 | "suit_season_buffalo" |
| 8 | 100 | 5 | 20 | "mask_season_buffalo" |
| 9 | 110 | 12 | 20 | "improvement_suit_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_buffalo" |

#### `ice_kingdom_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_ice_kingdom" |
| 2 | 15 | 5 | 20 | "belt_season_ice_kingdom" |
| 3 | 25 | 5 | 20 | "weapon_season_ice_kingdom" |
| 4 | 40 | 5 | 20 | "gadget_season_ice_kingdom" |
| 5 | 55 | 5 | 20 | "missiles_season_ice_kingdom" |
| 6 | 70 | 5 | 20 | "cape_season_ice_kingdom" |
| 7 | 85 | 5 | 20 | "suit_season_ice_kingdom" |
| 8 | 100 | 5 | 20 | "mask_season_ice_kingdom" |
| 9 | 110 | 12 | 20 | "improvement_cape_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_ice_kingdom" |

#### `hawk_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_hawk" |
| 2 | 15 | 5 | 20 | "belt_season_hawk" |
| 3 | 25 | 5 | 20 | "weapon_season_hawk" |
| 4 | 40 | 5 | 20 | "gadget_season_hawk" |
| 5 | 55 | 5 | 20 | "missiles_season_hawk" |
| 6 | 70 | 5 | 20 | "cape_season_hawk" |
| 7 | 85 | 5 | 20 | "suit_season_hawk" |
| 8 | 100 | 5 | 20 | "mask_2_season_hawk" |
| 9 | 110 | 5 | 20 | "mask_season_hawk" |
| 10 | 120 | 5 | 20 | "sidekick_season_hawk" |

#### `elk2_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_elk_winter" |
| 2 | 15 | 5 | 20 | "belt_season_elk_bl" |
| 3 | 20 | 5 | 20 | "weapon_season_elk" |
| 4 | 25 | 5 | 20 | "gadget_season_elk" |
| 5 | 30 | 5 | 20 | "missiles_season_elk" |
| 6 | 40 | 5 | 20 | "cape_season_elk" |
| 7 | 50 | 5 | 20 | "suit_season_elk_jacket_b" |
| 8 | 60 | 5 | 20 | "suit_season_elk_jacket_r" |
| 9 | 70 | 5 | 20 | "suit_season_elk_jacket_y" |
| 10 | 80 | 5 | 20 | "mask_season_elk_bobble_r" |
| 11 | 90 | 5 | 20 | "mask_season_elk_knitted_y" |
| 12 | 100 | 5 | 20 | "mask_season_elk_knitted_b" |
| 13 | 110 | 5 | 20 | "mask_season_elk_knitted_r" |
| 14 | 120 | 5 | 20 | "sidekick_season_elk" |

#### `ghost_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_ghost" |
| 2 | 15 | 5 | 20 | "belt_season_ghost" |
| 3 | 25 | 5 | 20 | "weapon_season_ghost" |
| 4 | 40 | 5 | 20 | "gadget_season_ghost" |
| 5 | 55 | 5 | 20 | "missiles_season_ghost" |
| 6 | 70 | 5 | 20 | "cape_season_ghost" |
| 7 | 85 | 5 | 20 | "suit_season_ghost" |
| 8 | 100 | 5 | 20 | "mask_season_ghost" |
| 9 | 110 | 12 | 20 | "improvement_sidekick_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_ghost" |

#### `lightning_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_lightning" |
| 2 | 15 | 5 | 20 | "belt_season_lightning" |
| 3 | 25 | 5 | 20 | "weapon_season_lightning" |
| 4 | 40 | 5 | 20 | "gadget_season_lightning" |
| 5 | 55 | 5 | 20 | "missiles_season_lightning" |
| 6 | 70 | 5 | 20 | "cape_season_lightning" |
| 7 | 85 | 5 | 20 | "suit_season_lightning" |
| 8 | 100 | 5 | 20 | "mask_season_lightning" |
| 9 | 110 | 12 | 20 | "improvement_gadget_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_lightning" |

#### `wild_west_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_wild_west" |
| 2 | 15 | 5 | 20 | "belt_season_wild_west" |
| 3 | 25 | 5 | 20 | "weapon_season_wild_west" |
| 4 | 40 | 5 | 20 | "gadget_season_wild_west" |
| 5 | 55 | 5 | 20 | "missiles_season_wild_west" |
| 6 | 70 | 5 | 20 | "cape_season_wild_west" |
| 7 | 85 | 5 | 20 | "suit_season_wild_west" |
| 8 | 100 | 5 | 20 | "mask_season_wild_west" |
| 9 | 110 | 12 | 20 | "improvement_belt_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_wild_west" |

#### `bounties_of_seven_seas_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_bounties_of_seven_seas" |
| 2 | 15 | 5 | 20 | "belt_season_bounties_of_seven_seas" |
| 3 | 25 | 5 | 20 | "weapon_season_bounties_of_seven_seas" |
| 4 | 40 | 5 | 20 | "gadget_season_bounties_of_seven_seas" |
| 5 | 55 | 5 | 20 | "missiles_season_bounties_of_seven_seas" |
| 6 | 70 | 5 | 20 | "cape_season_bounties_of_seven_seas" |
| 7 | 85 | 5 | 20 | "suit_season_bounties_of_seven_seas" |
| 8 | 100 | 5 | 20 | "mask_season_bounties_of_seven_seas" |
| 9 | 110 | 12 | 20 | "improvement_boots_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_bounties_of_seven_seas" |

#### `boar_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_boar" |
| 2 | 15 | 5 | 20 | "belt_season_boar" |
| 3 | 25 | 5 | 20 | "weapon_season_boar" |
| 4 | 40 | 5 | 20 | "gadget_season_boar" |
| 5 | 55 | 5 | 20 | "missiles_season_boar" |
| 6 | 70 | 5 | 20 | "cape_season_boar" |
| 7 | 85 | 5 | 20 | "suit_season_boar" |
| 8 | 100 | 5 | 20 | "mask_season_boar" |
| 9 | 110 | 12 | 20 | "improvement_weapon_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_boar" |

#### `guardians_of_nature_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_guardians_of_nature" |
| 2 | 15 | 5 | 20 | "belt_season_guardians_of_nature" |
| 3 | 25 | 5 | 20 | "weapon_season_guardians_of_nature" |
| 4 | 40 | 5 | 20 | "gadget_season_guardians_of_nature" |
| 5 | 55 | 5 | 20 | "missiles_season_guardians_of_nature" |
| 6 | 70 | 5 | 20 | "cape_season_guardians_of_nature" |
| 7 | 85 | 5 | 20 | "suit_season_guardians_of_nature" |
| 8 | 100 | 5 | 20 | "mask_season_guardians_of_nature" |
| 9 | 110 | 12 | 20 | "improvement_mask_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_guardians_of_nature" |

#### `bbq_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_bbq" |
| 2 | 15 | 5 | 20 | "belt_season_bbq" |
| 3 | 25 | 5 | 20 | "weapon_season_bbq" |
| 4 | 40 | 5 | 20 | "gadget_season_bbq" |
| 5 | 55 | 5 | 20 | "missiles_season_bbq" |
| 6 | 70 | 5 | 20 | "cape_season_bbq" |
| 7 | 85 | 5 | 20 | "suit_season_bbq" |
| 8 | 100 | 5 | 20 | "mask_season_bbq" |
| 9 | 110 | 12 | 20 | "improvement_suit_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_bbq" |

#### `pumpkins_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "missiles_season_pumpkins" |
| 2 | 15 | 5 | 20 | "boots_season_pumpkins" |
| 3 | 25 | 5 | 20 | "belt_season_pumpkins" |
| 4 | 40 | 5 | 20 | "weapon_season_pumpkins" |
| 5 | 55 | 5 | 20 | "gadget_season_pumpkins" |
| 6 | 70 | 5 | 20 | "cape_season_pumpkins" |
| 7 | 85 | 5 | 20 | "suit_season_pumpkins_2" |
| 8 | 100 | 5 | 20 | "suit_season_pumpkins_1" |
| 9 | 110 | 5 | 20 | "mask_season_pumpkins" |
| 10 | 120 | 5 | 20 | "sidekick_season_pumpkins" |

#### `flower_power_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_flower_power" |
| 2 | 15 | 5 | 20 | "belt_season_flower_power" |
| 3 | 25 | 5 | 20 | "weapon_season_flower_power" |
| 4 | 40 | 5 | 20 | "gadget_season_flower_power" |
| 5 | 55 | 5 | 20 | "missiles_season_flower_power" |
| 6 | 70 | 5 | 20 | "cape_season_flower_power" |
| 7 | 85 | 5 | 20 | "suit_season_flower_power" |
| 8 | 100 | 5 | 20 | "mask_season_flower_power" |
| 9 | 110 | 12 | 20 | "improvement_cape_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_flower_power" |

#### `zombies_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_zombies" |
| 2 | 15 | 5 | 20 | "belt_season_zombies" |
| 3 | 25 | 5 | 20 | "weapon_season_zombies" |
| 4 | 40 | 5 | 20 | "gadget_season_zombies" |
| 5 | 55 | 5 | 20 | "missiles_season_zombies" |
| 6 | 70 | 5 | 20 | "cape_season_zombies" |
| 7 | 85 | 5 | 20 | "suit_season_zombies" |
| 8 | 100 | 5 | 20 | "mask_season_zombies" |
| 9 | 110 | 12 | 20 | "improvement_sidekick_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_zombies" |

#### `science_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_science" |
| 2 | 15 | 5 | 20 | "belt_season_science" |
| 3 | 25 | 5 | 20 | "weapon_season_science" |
| 4 | 40 | 5 | 20 | "gadget_season_science" |
| 5 | 55 | 5 | 20 | "missiles_season_science" |
| 6 | 70 | 5 | 20 | "cape_season_science" |
| 7 | 85 | 5 | 20 | "suit_season_science" |
| 8 | 100 | 5 | 20 | "mask_season_science" |
| 9 | 110 | 12 | 20 | "improvement_gadget_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_science" |

#### `creativity_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_creativity" |
| 2 | 15 | 5 | 20 | "belt_season_creativity" |
| 3 | 25 | 5 | 20 | "weapon_season_creativity" |
| 4 | 40 | 5 | 20 | "gadget_season_creativity" |
| 5 | 55 | 5 | 20 | "missiles_season_creativity" |
| 6 | 70 | 5 | 20 | "cape_seas_creativi" |
| 7 | 85 | 5 | 20 | "suit_season_creativity" |
| 8 | 100 | 5 | 20 | "mask_2_season_creativity" |
| 9 | 110 | 5 | 20 | "mask_season_creativity" |
| 10 | 120 | 5 | 20 | "sidekick_season_creativity" |

#### `steampunk_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_steampunk" |
| 2 | 15 | 5 | 20 | "belt_season_steampunk" |
| 3 | 25 | 5 | 20 | "weapon_season_steampunk" |
| 4 | 40 | 5 | 20 | "gadget_season_steampunk" |
| 5 | 55 | 5 | 20 | "missiles_season_steampunk" |
| 6 | 70 | 5 | 20 | "cape_season_steampunk" |
| 7 | 85 | 5 | 20 | "suit_season_steampunk" |
| 8 | 100 | 5 | 20 | "mask_season_steampunk" |
| 9 | 110 | 12 | 20 | "improvement_belt_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_steampunk" |

#### `fox_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_fox" |
| 2 | 15 | 5 | 20 | "belt_season_fox" |
| 3 | 25 | 5 | 20 | "weapon_season_fox" |
| 4 | 40 | 5 | 20 | "gadget_season_fox" |
| 5 | 55 | 5 | 20 | "missiles_season_fox" |
| 6 | 70 | 5 | 20 | "cape_season_fox" |
| 7 | 85 | 5 | 20 | "suit_season_fox" |
| 8 | 100 | 5 | 20 | "mask_season_fox" |
| 9 | 110 | 12 | 20 | "improvement_boots_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_fox" |

#### `apocalypse_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_apocalypse" |
| 2 | 15 | 5 | 20 | "belt_season_apocalypse" |
| 3 | 25 | 5 | 20 | "weapon_season_apocalypse" |
| 4 | 40 | 5 | 20 | "gadget_season_apocalypse" |
| 5 | 55 | 5 | 20 | "missiles_season_apocalypse" |
| 6 | 70 | 5 | 20 | "cape_season_apocalypse" |
| 7 | 85 | 5 | 20 | "suit_season_apocalypse" |
| 8 | 100 | 5 | 20 | "mask_season_apocalypse" |
| 9 | 110 | 12 | 20 | "improvement_weapon_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_apocalypse" |

#### `arcade_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_arcade" |
| 2 | 15 | 5 | 20 | "belt_season_arcade" |
| 3 | 25 | 5 | 20 | "weapon_season_arcade" |
| 4 | 40 | 5 | 20 | "gadget_season_arcade" |
| 5 | 55 | 5 | 20 | "missiles_season_arcade" |
| 6 | 70 | 5 | 20 | "cape_season_arcade" |
| 7 | 85 | 5 | 20 | "suit_season_arcade" |
| 8 | 100 | 5 | 20 | "mask_season_arcade" |
| 9 | 110 | 5 | 20 | "mask_season_arcade_hologram" |
| 10 | 120 | 5 | 20 | "sidekick_season_arcade" |

#### `volcano_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_volcano" |
| 2 | 15 | 5 | 20 | "belt_season_volcano" |
| 3 | 25 | 5 | 20 | "weapon_season_volcano" |
| 4 | 40 | 5 | 20 | "gadget_season_volcano" |
| 5 | 55 | 5 | 20 | "missiles_season_volcano" |
| 6 | 70 | 5 | 20 | "cape_season_volcano" |
| 7 | 85 | 5 | 20 | "suit_season_volcano" |
| 8 | 100 | 5 | 20 | "mask_season_volcano" |
| 9 | 110 | 5 | 20 | "sidekick_season_volcano_2" |
| 10 | 120 | 5 | 20 | "sidekick_season_volcano_1" |

#### `aliens_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_aliens" |
| 2 | 15 | 5 | 20 | "belt_season_aliens" |
| 3 | 25 | 5 | 20 | "weapon_season_aliens" |
| 4 | 40 | 5 | 20 | "gadget_season_aliens" |
| 5 | 55 | 5 | 20 | "missiles_season_aliens" |
| 6 | 70 | 5 | 20 | "cape_season_aliens" |
| 7 | 85 | 5 | 20 | "suit_season_aliens" |
| 8 | 100 | 5 | 20 | "mask_season_aliens" |
| 9 | 110 | 12 | 20 | "improvement_mask_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_aliens" |

#### `future_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_future" |
| 2 | 15 | 5 | 20 | "belt_season_future" |
| 3 | 25 | 5 | 20 | "weapon_season_future" |
| 4 | 40 | 5 | 20 | "gadget_season_future" |
| 5 | 55 | 5 | 20 | "missiles_season_future" |
| 6 | 70 | 5 | 20 | "cape_season_future" |
| 7 | 85 | 5 | 20 | "suit_season_future" |
| 8 | 100 | 5 | 20 | "mask_season_future" |
| 9 | 110 | 12 | 20 | "improvement_suit_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_future" |

#### `water_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_water" |
| 2 | 15 | 5 | 20 | "belt_season_water" |
| 3 | 25 | 5 | 20 | "weapon_season_water" |
| 4 | 40 | 5 | 20 | "gadget_season_water" |
| 5 | 55 | 5 | 20 | "missiles_season_water" |
| 6 | 70 | 5 | 20 | "cape_season_water" |
| 7 | 85 | 5 | 20 | "suit_season_water" |
| 8 | 100 | 5 | 20 | "mask_season_water" |
| 9 | 110 | 12 | 20 | "improvement_cape_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_water" |

#### `air_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_air" |
| 2 | 15 | 5 | 20 | "belt_season_air" |
| 3 | 25 | 5 | 20 | "weapon_season_air" |
| 4 | 40 | 5 | 20 | "gadget_season_air" |
| 5 | 55 | 5 | 20 | "missiles_season_air" |
| 6 | 70 | 5 | 20 | "cape_season_air" |
| 7 | 85 | 5 | 20 | "suit_season_air" |
| 8 | 100 | 5 | 20 | "mask_season_air" |
| 9 | 110 | 12 | 20 | "improvement_sidekick_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_air" |

#### `shadow_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_shadow" |
| 2 | 10 | 5 | 20 | "belt_season_shadow_y" |
| 3 | 15 | 5 | 20 | "belt_season_shadow_b" |
| 4 | 20 | 5 | 20 | "belt_season_shadow_g" |
| 5 | 25 | 5 | 20 | "weapon_season_shadow" |
| 6 | 30 | 5 | 20 | "gadget_season_shadow" |
| 7 | 40 | 5 | 20 | "missiles_season_shadow" |
| 8 | 50 | 5 | 20 | "cape_season_shadow" |
| 9 | 60 | 5 | 20 | "suit_season_shadow_y" |
| 10 | 70 | 5 | 20 | "suit_season_shadow_b" |
| 11 | 80 | 5 | 20 | "suit_season_shadow_g" |
| 12 | 90 | 5 | 20 | "mask_season_shadow_y" |
| 13 | 100 | 5 | 20 | "mask_season_shadow_b" |
| 14 | 110 | 5 | 20 | "mask_season_shadow_g" |
| 15 | 120 | 5 | 20 | "sidekick_season_shadow" |

#### `holy_light_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_holy_light" |
| 2 | 10 | 5 | 20 | "belt_season_holy_light_1" |
| 3 | 15 | 5 | 20 | "belt_season_holy_light_2" |
| 4 | 20 | 5 | 20 | "weapon_season_holy_light" |
| 5 | 25 | 5 | 20 | "gadget_season_holy_light" |
| 6 | 30 | 5 | 20 | "missiles_season_holy_light" |
| 7 | 35 | 5 | 20 | "cape_season_holy_light_1" |
| 8 | 40 | 5 | 20 | "cape_season_holy_light_2" |
| 9 | 45 | 5 | 20 | "cape_season_holy_light_3" |
| 10 | 50 | 5 | 20 | "cape_season_holy_light_4" |
| 11 | 60 | 5 | 20 | "cape_season_holy_light_5" |
| 12 | 70 | 5 | 20 | "suit_season_holy_light_1" |
| 13 | 80 | 5 | 20 | "suit_season_holy_light_2" |
| 14 | 90 | 5 | 20 | "suit_season_holy_light_3" |
| 15 | 100 | 5 | 20 | "suit_season_holy_light_4" |
| 16 | 110 | 5 | 20 | "mask_season_holy_light" |
| 17 | 120 | 5 | 20 | "sidekick_season_holy_light" |

#### `bounty_hunter_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_bounty_hunter" |
| 2 | 15 | 5 | 20 | "belt_season_bounty_hunter" |
| 3 | 25 | 5 | 20 | "weapon_season_bounty_hunter" |
| 4 | 40 | 5 | 20 | "gadget_season_bounty_hunter" |
| 5 | 55 | 5 | 20 | "missiles_season_bounty_hunter" |
| 6 | 70 | 5 | 20 | "cape_season_bounty_hunter" |
| 7 | 85 | 5 | 20 | "suit_season_bounty_hunter" |
| 8 | 100 | 5 | 20 | "mask_season_bounty_hunter" |
| 9 | 110 | 12 | 20 | "improvement_gadget_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_bounty_hunter" |

#### `magical_circus_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_magical_circus" |
| 2 | 15 | 5 | 20 | "belt_season_magical_circus" |
| 3 | 25 | 5 | 20 | "weapon_season_magical_circus" |
| 4 | 40 | 5 | 20 | "gadget_season_magical_circus" |
| 5 | 55 | 5 | 20 | "missiles_season_magical_circus" |
| 6 | 70 | 5 | 20 | "cape_season_magical_circus" |
| 7 | 85 | 5 | 20 | "suit_season_magical_circus" |
| 8 | 100 | 5 | 20 | "mask_season_magical_circus" |
| 9 | 110 | 12 | 20 | "improvement_belt_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_magical_circus" |

#### `fire_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_fire" |
| 2 | 15 | 5 | 20 | "belt_season_fire" |
| 3 | 25 | 5 | 20 | "weapon_season_fire" |
| 4 | 40 | 5 | 20 | "gadget_season_fire" |
| 5 | 55 | 5 | 20 | "missiles_season_fire" |
| 6 | 70 | 5 | 20 | "cape_season_fire" |
| 7 | 85 | 5 | 20 | "suit_season_fire" |
| 8 | 100 | 5 | 20 | "mask_season_fire" |
| 9 | 110 | 12 | 20 | "improvement_boots_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_fire" |

#### `empire_of_the_sun_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_empire_of_the_sun" |
| 2 | 15 | 5 | 20 | "belt_season_empire_of_the_sun" |
| 3 | 25 | 5 | 20 | "weapon_season_empire_of_the_sun" |
| 4 | 40 | 5 | 20 | "gadget_season_empire_of_the_sun" |
| 5 | 55 | 5 | 20 | "missiles_season_empire_of_the_sun" |
| 6 | 70 | 5 | 20 | "cape_season_empire_of_the_sun" |
| 7 | 85 | 5 | 20 | "suit_season_empire_of_the_sun" |
| 8 | 100 | 5 | 20 | "mask_season_empire_of_the_sun" |
| 9 | 110 | 12 | 20 | "improvement_weapon_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_empire_of_the_sun" |

#### `internet_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_internet" |
| 2 | 15 | 5 | 20 | "belt_season_internet" |
| 3 | 25 | 5 | 20 | "weapon_season_internet" |
| 4 | 40 | 5 | 20 | "gadget_season_internet" |
| 5 | 55 | 5 | 20 | "missiles_season_internet" |
| 6 | 70 | 5 | 20 | "cape_season_internet" |
| 7 | 85 | 5 | 20 | "suit_season_internet" |
| 8 | 100 | 5 | 20 | "mask_season_internet" |
| 9 | 110 | 12 | 20 | "improvement_mask_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_internet" |

#### `empire_of_the_stars_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_empire_of_the_stars" |
| 2 | 15 | 5 | 20 | "belt_season_empire_of_the_stars" |
| 3 | 25 | 5 | 20 | "weapon_season_empire_of_the_stars" |
| 4 | 40 | 5 | 20 | "gadget_season_empire_of_the_stars" |
| 5 | 55 | 5 | 20 | "missiles_season_empire_of_the_stars" |
| 6 | 70 | 5 | 20 | "cape_season_empire_of_the_stars" |
| 7 | 85 | 5 | 20 | "suit_season_empire_of_the_stars" |
| 8 | 100 | 5 | 20 | "mask_season_empire_of_the_stars" |
| 9 | 110 | 12 | 20 | "improvement_suit_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_empire_of_the_stars" |

#### `post_apocalypse_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_post_apocalypse" |
| 2 | 15 | 5 | 20 | "belt_season_post_apocalypse" |
| 3 | 25 | 5 | 20 | "weapon_season_post_apocalypse" |
| 4 | 40 | 5 | 20 | "gadget_season_post_apocalypse" |
| 5 | 55 | 5 | 20 | "missiles_season_post_apocalypse" |
| 6 | 70 | 5 | 20 | "cape_season_post_apocalypse" |
| 7 | 85 | 5 | 20 | "suit_season_post_apocalypse" |
| 8 | 100 | 5 | 20 | "mask_season_post_apocalypse" |
| 9 | 110 | 12 | 20 | "improvement_cape_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_post_apocalypse" |

#### `witchcraft_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_witchcraft" |
| 2 | 15 | 5 | 20 | "belt_season_witchcraft" |
| 3 | 25 | 5 | 20 | "weapon_season_witchcraft" |
| 4 | 40 | 5 | 20 | "gadget_season_witchcraft" |
| 5 | 55 | 5 | 20 | "missiles_season_witchcraft" |
| 6 | 70 | 5 | 20 | "cape_season_witchcraft" |
| 7 | 85 | 5 | 20 | "suit_season_witchcraft" |
| 8 | 100 | 5 | 20 | "mask_season_witchcraft" |
| 9 | 110 | 12 | 20 | "improvement_sidekick_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_witchcraft" |

#### `greek_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_greek" |
| 2 | 15 | 5 | 20 | "belt_season_greek" |
| 3 | 25 | 5 | 20 | "weapon_season_greek" |
| 4 | 40 | 5 | 20 | "gadget_season_greek" |
| 5 | 55 | 5 | 20 | "missiles_season_greek" |
| 6 | 70 | 5 | 20 | "cape_season_greek" |
| 7 | 85 | 5 | 20 | "suit_season_greek" |
| 8 | 100 | 5 | 20 | "mask_season_greek" |
| 9 | 110 | 12 | 20 | "improvement_gadget_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_greek" |

#### `vampire_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_vampire" |
| 2 | 15 | 5 | 20 | "belt_season_vampire" |
| 3 | 25 | 5 | 20 | "weapon_season_vampire" |
| 4 | 40 | 5 | 20 | "gadget_season_vampire" |
| 5 | 55 | 5 | 20 | "missiles_season_vampire" |
| 6 | 70 | 5 | 20 | "cape_season_vampire" |
| 7 | 85 | 5 | 20 | "suit_season_vampire" |
| 8 | 100 | 5 | 20 | "mask_season_vampire" |
| 9 | 110 | 12 | 20 | "improvement_belt_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_vampire" |

#### `beetle_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_beetle" |
| 2 | 15 | 5 | 20 | "belt_season_beetle" |
| 3 | 25 | 5 | 20 | "weapon_season_beetle" |
| 4 | 40 | 5 | 20 | "gadget_season_beetle" |
| 5 | 55 | 5 | 20 | "missiles_season_beetle" |
| 6 | 70 | 5 | 20 | "cape_season_beetle" |
| 7 | 85 | 5 | 20 | "suit_season_beetle" |
| 8 | 100 | 5 | 20 | "mask_season_beetle" |
| 9 | 110 | 12 | 20 | "improvement_boots_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_beetle" |

#### `empire_of_the_moon_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_empire_of_the_moon" |
| 2 | 15 | 5 | 20 | "belt_season_empire_of_the_moon" |
| 3 | 25 | 5 | 20 | "weapon_season_empire_of_the_moon" |
| 4 | 40 | 5 | 20 | "gadget_season_empire_of_the_moon" |
| 5 | 55 | 5 | 20 | "missiles_season_empire_of_the_moon" |
| 6 | 70 | 5 | 20 | "cape_season_empire_of_the_moon" |
| 7 | 85 | 5 | 20 | "suit_season_empire_of_the_moon" |
| 8 | 100 | 5 | 20 | "mask_season_empire_of_the_moon" |
| 9 | 110 | 12 | 20 | "improvement_weapon_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_empire_of_the_moon" |

#### `day_of_the_dead_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_day_of_the_dead" |
| 2 | 15 | 5 | 20 | "belt_season_day_of_the_dead" |
| 3 | 25 | 5 | 20 | "weapon_season_day_of_the_dead" |
| 4 | 40 | 5 | 20 | "gadget_season_day_of_the_dead" |
| 5 | 55 | 5 | 20 | "missiles_season_day_of_the_dead" |
| 6 | 70 | 5 | 20 | "cape_season_day_of_the_dead" |
| 7 | 85 | 5 | 20 | "suit_season_day_of_the_dead" |
| 8 | 100 | 5 | 20 | "mask_season_day_of_the_dead" |
| 9 | 110 | 12 | 20 | "improvement_mask_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_day_of_the_dead" |

#### `mayan_empire_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_mayan_empire" |
| 2 | 15 | 5 | 20 | "belt_season_mayan_empire" |
| 3 | 25 | 5 | 20 | "weapon_season_mayan_empire" |
| 4 | 40 | 5 | 20 | "gadget_season_mayan_empire" |
| 5 | 55 | 5 | 20 | "missiles_season_mayan_empire" |
| 6 | 70 | 5 | 20 | "cape_season_mayan_empire" |
| 7 | 85 | 5 | 20 | "suit_season_mayan_empire" |
| 8 | 100 | 5 | 20 | "mask_season_mayan_empire" |
| 9 | 110 | 12 | 20 | "improvement_suit_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_mayan_empire" |

#### `heroic_feast_appetizer_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_heroic_feast_appetizer" |
| 2 | 15 | 5 | 20 | "belt_season_heroic_feast_appetizer" |
| 3 | 25 | 5 | 20 | "weapon_season_heroic_feast_appetizer" |
| 4 | 40 | 5 | 20 | "gadget_season_heroic_feast_appetizer" |
| 5 | 55 | 5 | 20 | "missiles_season_heroic_feast_appetizer" |
| 6 | 70 | 5 | 20 | "cape_season_heroic_feast_appetizer" |
| 7 | 85 | 5 | 20 | "suit_season_heroic_feast_appetizer" |
| 8 | 100 | 5 | 20 | "mask_season_heroic_feast_appetizer" |
| 9 | 110 | 12 | 20 | "improvement_cape_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_heroic_feast_appetizer" |

#### `distant_worlds_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_distant_worlds" |
| 2 | 15 | 5 | 20 | "belt_season_distant_worlds" |
| 3 | 25 | 5 | 20 | "weapon_season_distant_worlds" |
| 4 | 40 | 5 | 20 | "gadget_season_distant_worlds" |
| 5 | 55 | 5 | 20 | "missiles_season_distant_worlds" |
| 6 | 70 | 5 | 20 | "cape_season_distant_worlds" |
| 7 | 85 | 5 | 20 | "suit_season_distant_worlds" |
| 8 | 100 | 5 | 20 | "mask_season_distant_worlds" |
| 9 | 110 | 12 | 20 | "improvement_weapon_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_distant_worlds" |

#### `armoured_beasts_1_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_armoured_beasts_1" |
| 2 | 15 | 5 | 20 | "belt_season_armoured_beasts_1" |
| 3 | 25 | 5 | 20 | "weapon_season_armoured_beasts_1" |
| 4 | 40 | 5 | 20 | "gadget_season_armoured_beasts_1" |
| 5 | 55 | 5 | 20 | "missiles_season_armoured_beasts_1" |
| 6 | 70 | 5 | 20 | "cape_season_armoured_beasts_1" |
| 7 | 85 | 5 | 20 | "suit_season_armoured_beasts_1" |
| 8 | 100 | 5 | 20 | "mask_season_armoured_beasts_1" |
| 9 | 110 | 12 | 20 | "improvement_mask_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_armoured_beasts_1" |

#### `corruption_treasure`

Metadados: `{"location":"standard_balancing"}`.

| Entrada | required_token_amount | reward_type | reward_factor | reward_reference |
| --- | --- | --- | --- | --- |
| 1 | 5 | 5 | 20 | "boots_season_corruption" |
| 2 | 15 | 5 | 20 | "belt_season_corruption" |
| 3 | 25 | 5 | 20 | "weapon_season_corruption" |
| 4 | 40 | 5 | 20 | "gadget_season_corruption" |
| 5 | 55 | 5 | 20 | "missiles_season_corruption" |
| 6 | 70 | 5 | 20 | "cape_season_corruption" |
| 7 | 85 | 5 | 20 | "suit_season_corruption" |
| 8 | 100 | 5 | 20 | "mask_season_corruption" |
| 9 | 110 | 12 | 20 | "improvement_suit_epic" |
| 10 | 120 | 5 | 20 | "sidekick_season_corruption" |

### `hidden_object_event_quests` — 4 definições

Objetos escondidos: objetivos de recolha por dia/posição e patamares de prémios.

| # | Identificador |
| --- | --- |
| 1 | `find_ballo_event` |
| 2 | `find_sidekick_event` |
| 3 | `find_item_event` |
| 4 | `autumn_set_event` |

**Definições completas:**

```json
{
  "find_ballo_event": {
    "start_date": "2001-01-01",
    "end_date": "2001-01-01",
    "background_color_top": "#E6D5BB",
    "background_color_bottom": "#7B99B3",
    "reward1_need_objects": 2,
    "reward1_type": 3,
    "reward1_factor": 10,
    "reward1_identifier": "",
    "reward2_need_objects": 4,
    "reward2_type": 2,
    "reward2_factor": 5,
    "reward2_identifier": "",
    "reward3_need_objects": 6,
    "reward3_type": 8,
    "reward3_factor": 21,
    "reward3_identifier": "sidekick_racoon",
    "objects": {
      "1": {
        "day": 1,
        "position": 109,
        "scale": 0.7,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "2": {
        "day": 2,
        "position": 23,
        "scale": 0.6,
        "offset_x": 12,
        "offset_y": -8,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "3": {
        "day": 3,
        "position": 27,
        "scale": 0.5,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "4": {
        "day": 4,
        "position": 20,
        "scale": 0.5,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "5": {
        "day": 5,
        "position": 57,
        "scale": 0.5,
        "offset_x": 11,
        "offset_y": -12,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "6": {
        "day": 6,
        "position": 8,
        "scale": 0.54,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      }
    }
  },
  "find_sidekick_event": {
    "start_date": "2001-01-01",
    "end_date": "2001-01-01",
    "background_color_top": "#7AE1ED",
    "background_color_bottom": "#345032",
    "reward1_need_objects": 2,
    "reward1_type": 3,
    "reward1_factor": 10,
    "reward1_identifier": "",
    "reward2_need_objects": 4,
    "reward2_type": 2,
    "reward2_factor": 5,
    "reward2_identifier": "",
    "reward3_need_objects": 8,
    "reward3_type": 8,
    "reward3_factor": 21,
    "reward3_identifier": "sidekick_see_lion",
    "objects": {
      "1": {
        "day": 1,
        "position": 71,
        "scale": 0.5,
        "offset_x": -13,
        "offset_y": -8,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "2": {
        "day": 2,
        "position": 73,
        "scale": 1,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "3": {
        "day": 3,
        "position": 63,
        "scale": 0.9,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "4": {
        "day": 4,
        "position": 109,
        "scale": 0.7,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "5": {
        "day": 5,
        "position": 13,
        "scale": 0.64,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "6": {
        "day": 6,
        "position": 97,
        "scale": 0.6,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "7": {
        "day": 7,
        "position": 67,
        "scale": 0.7,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "8": {
        "day": 8,
        "position": 74,
        "scale": 1,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      }
    }
  },
  "find_item_event": {
    "start_date": "2001-01-01",
    "end_date": "2001-01-01",
    "background_color_top": "#B3AAFF",
    "background_color_bottom": "#4E1A1A",
    "reward1_need_objects": 2,
    "reward1_type": 6,
    "reward1_factor": 10,
    "reward1_identifier": "",
    "reward2_need_objects": 4,
    "reward2_type": 3,
    "reward2_factor": 10,
    "reward2_identifier": "",
    "reward3_need_objects": 7,
    "reward3_type": 9,
    "reward3_factor": 15,
    "reward3_identifier": "mask_heroic_design;suit_heroic_design;boots_heroic_design;cape_heroic_design;belt_heroic_design",
    "objects": {
      "1": {
        "day": 1,
        "position": 84,
        "scale": 0.55,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "2": {
        "day": 2,
        "position": 61,
        "scale": 0.55,
        "offset_x": -3,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "3": {
        "day": 3,
        "position": 74,
        "scale": 0.7,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "4": {
        "day": 4,
        "position": 118,
        "scale": 0.7,
        "offset_x": 10,
        "offset_y": -27,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "5": {
        "day": 5,
        "position": 1,
        "scale": 0.6,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "6": {
        "day": 6,
        "position": 93,
        "scale": 0.5,
        "offset_x": -3,
        "offset_y": 3,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "7": {
        "day": 7,
        "position": 67,
        "scale": 0.7,
        "offset_x": 0,
        "offset_y": 0,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      }
    }
  },
  "autumn_set_event": {
    "start_date": "2001-01-01",
    "end_date": "2001-01-01",
    "background_color_top": "#E19A2B",
    "background_color_bottom": "#AB5D2F",
    "reward1_need_objects": 2,
    "reward1_type": 6,
    "reward1_factor": 20,
    "reward1_identifier": "",
    "reward2_need_objects": 4,
    "reward2_type": 3,
    "reward2_factor": 10,
    "reward2_identifier": "",
    "reward3_need_objects": 5,
    "reward3_type": 9,
    "reward3_factor": 15,
    "reward3_identifier": "mask_lumberjack_set;cape_lumberjack_set;suit_lumberjack_set;belt_lumberjack_set;boots_lumberjack_set",
    "objects": {
      "1": {
        "day": 1,
        "position": 30,
        "scale": 0.55,
        "offset_x": -8,
        "offset_y": -6,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "2": {
        "day": 2,
        "position": 59,
        "scale": 0.5,
        "offset_x": 17,
        "offset_y": -15,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "3": {
        "day": 3,
        "position": 70,
        "scale": 0.5,
        "offset_x": 8,
        "offset_y": -13,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "4": {
        "day": 4,
        "position": 72,
        "scale": 0.5,
        "offset_x": -5,
        "offset_y": -3,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      },
      "5": {
        "day": 5,
        "position": 83,
        "scale": 0.5,
        "offset_x": -3,
        "offset_y": -3,
        "rotation": 0,
        "grow_width": 0,
        "grow_height": 0,
        "grow_offset_x": 0,
        "grow_offset_y": 0
      }
    }
  }
}
```

### `tower_events` — 6 definições

Torres: ambientes, moeda/token e ligação a objetivos.

| # | Identificador |
| --- | --- |
| 1 | `test_towerevent_2020` |
| 2 | `earth_towerevent` |
| 3 | `bounties_of_seven_seas_towerevent` |
| 4 | `sci_fi_week_towerevent` |
| 5 | `wasteland_towerevent` |
| 6 | `zombies_towerevent` |

**Definições completas:**

```json
{
  "test_towerevent_2020": {
    "asset_identifier": "test_towerevent",
    "token_item": "event_token_default",
    "environment": "grass2",
    "objectives": "test_towerevent",
    "tower_id": 200,
    "tower_objectives": "default",
    "repeatable_objectives": false,
    "tower_currency": "default"
  },
  "earth_towerevent": {
    "asset_identifier": "earth_towerevent",
    "token_item": "event_token_default",
    "environment": "grass2",
    "objectives": "earth_towerevent",
    "tower_id": 206,
    "tower_objectives": "default",
    "repeatable_objectives": false,
    "tower_currency": "default"
  },
  "bounties_of_seven_seas_towerevent": {
    "asset_identifier": "bounties_of_seven_seas_towerevent",
    "token_item": "event_token_default",
    "environment": "bounty",
    "objectives": "bounties_of_seven_seas_towerevent",
    "tower_id": 208,
    "tower_objectives": "default",
    "repeatable_objectives": false,
    "tower_currency": "default"
  },
  "sci_fi_week_towerevent": {
    "asset_identifier": "sci_fi_week_towerevent",
    "token_item": "event_token_default",
    "environment": "scifi",
    "objectives": "sci_fi_week_towerevent",
    "tower_id": 207,
    "tower_objectives": "default",
    "repeatable_objectives": false,
    "tower_currency": "default"
  },
  "wasteland_towerevent": {
    "asset_identifier": "wasteland_towerevent",
    "token_item": "event_token_default",
    "environment": "wasteland",
    "objectives": "wasteland_towerevent",
    "tower_id": 209,
    "tower_objectives": "default",
    "repeatable_objectives": false,
    "tower_currency": "default"
  },
  "zombies_towerevent": {
    "asset_identifier": "zombies_towerevent",
    "token_item": "event_token_default",
    "environment": "zombies",
    "objectives": "zombies_towerevent",
    "tower_id": 205,
    "tower_objectives": "default",
    "repeatable_objectives": false,
    "tower_currency": "default"
  }
}
```

### `worldboss_event_templates` — 39 definições

Vilões: templates de NPC; inclui templates genéricos e temáticos, não um calendário de ocorrências.

| # | Identificador |
| --- | --- |
| 1 | `default_event_stage1` |
| 2 | `default_event_stage2` |
| 3 | `default_event_stage3` |
| 4 | `default_event_stage4` |
| 5 | `default_event_stage5` |
| 6 | `default_event_stage6` |
| 7 | `default_event_stage7` |
| 8 | `default_event_stage8` |
| 9 | `default_event_stage9` |
| 10 | `default_event_stage10` |
| 11 | `default_event_stage11` |
| 12 | `olympia_event_stage2` |
| 13 | `halloween_event_stage2` |
| 14 | `xmas_event_stage2` |
| 15 | `easter_event_stage2` |
| 16 | `telekom_event_stage2` |
| 17 | `winterolympics_event_stage2` |
| 18 | `fonic_event_stage1` |
| 19 | `ghostbusters_event_stage2` |
| 20 | `worldcup_event_stage2` |
| 21 | `default_event_stage12` |
| 22 | `hercules_event_stage1` |
| 23 | `tmnt_event_stage1` |
| 24 | `disneyinfinity_event_stage1` |
| 25 | `rtl2promo_event_stage1` |
| 26 | `default_event_stage13` |
| 27 | `bttf_event_stage1` |
| 28 | `default_event_stage14` |
| 29 | `sci_fi_week_stage2` |
| 30 | `environmental_week_stage2` |
| 31 | `video_games_week_stage2` |
| 32 | `carnival_stage2` |
| 33 | `primal_season_worldboss` |
| 34 | `utopia_season_worldboss` |
| 35 | `default_event_stage1_2` |
| 36 | `default_event_stage2_2` |
| 37 | `default_event_stage3_2` |
| 38 | `default_event_stage4_2` |
| 39 | `dystopia_season_worldboss` |

**Definições completas:**

```json
{
  "default_event_stage1": {
    "npc_identifier": "npc_joker"
  },
  "default_event_stage2": {
    "npc_identifier": "npc_c3_d2"
  },
  "default_event_stage3": {
    "npc_identifier": "npc_sergant"
  },
  "default_event_stage4": {
    "npc_identifier": "npc_revenger"
  },
  "default_event_stage5": {
    "npc_identifier": "npc_mad_scientist"
  },
  "default_event_stage6": {
    "npc_identifier": "npc_dark_father"
  },
  "default_event_stage7": {
    "npc_identifier": "npc_prof_evil"
  },
  "default_event_stage8": {
    "npc_identifier": "npc_mafioso_godfather"
  },
  "default_event_stage9": {
    "npc_identifier": "npc_mario"
  },
  "default_event_stage10": {
    "npc_identifier": "npc_artist"
  },
  "default_event_stage11": {
    "npc_identifier": "npc_dirty_slob"
  },
  "olympia_event_stage2": {
    "npc_identifier": "npc_olympia_worldboss"
  },
  "halloween_event_stage2": {
    "npc_identifier": "npc_zombie"
  },
  "xmas_event_stage2": {
    "npc_identifier": "npc_grinch"
  },
  "easter_event_stage2": {
    "npc_identifier": "npc_easterthief"
  },
  "telekom_event_stage2": {
    "npc_identifier": "npc_telekom"
  },
  "winterolympics_event_stage2": {
    "npc_identifier": "npc_olympia_worldboss"
  },
  "fonic_event_stage1": {
    "npc_identifier": "npc_fonic"
  },
  "ghostbusters_event_stage2": {
    "npc_identifier": "npc_marshmellow_man"
  },
  "worldcup_event_stage2": {
    "npc_identifier": "npc_worldcup_killjoy"
  },
  "default_event_stage12": {
    "npc_identifier": "npc_poacher"
  },
  "hercules_event_stage1": {
    "npc_identifier": "npc_hercules_hydra"
  },
  "tmnt_event_stage1": {
    "npc_identifier": "npc_tmnt_shredder"
  },
  "disneyinfinity_event_stage1": {
    "npc_identifier": "npc_disneyinfinity_maleficent"
  },
  "rtl2promo_event_stage1": {
    "npc_identifier": "npc_rtl2promo_lora_ley"
  },
  "default_event_stage13": {
    "npc_identifier": "npc_gontrilla_roboter"
  },
  "bttf_event_stage1": {
    "npc_identifier": "npc_bttf_buff"
  },
  "default_event_stage14": {
    "npc_identifier": "npc_egypt_mummy_robot"
  },
  "sci_fi_week_stage2": {
    "npc_identifier": "npc_reverse_nerd"
  },
  "environmental_week_stage2": {
    "npc_identifier": "npc_air_polluter"
  },
  "video_games_week_stage2": {
    "npc_identifier": "npc_pacman"
  },
  "carnival_stage2": {
    "npc_identifier": "npc_carnival_boss"
  },
  "primal_season_worldboss": {
    "npc_identifier": "npc_primal_season_worldboss"
  },
  "utopia_season_worldboss": {
    "npc_identifier": "npc_utopia_season_worldboss"
  },
  "default_event_stage1_2": {
    "npc_identifier": "npc_burglar_duo"
  },
  "default_event_stage2_2": {
    "npc_identifier": "npc_rude_rocker"
  },
  "default_event_stage3_2": {
    "npc_identifier": "npc_pompous_salesman"
  },
  "default_event_stage4_2": {
    "npc_identifier": "npc_real_estate_shark"
  },
  "dystopia_season_worldboss": {
    "npc_identifier": "npc_dystopia_season_worldboss"
  }
}
```

### Objetos de evento — `event_items`

| Identificador | Definição completa |
| --- | --- |
| `summer_event_autograph` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `halloween_pumpkin` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `halloween_bat` | `{"reward_type":1,"reward_chance":0.15,"reward_reference":"all"}` |
| `halloween_candy1` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `halloween_candy2` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `xmas_lights` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `xmas_gingerbread` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `xmas_candles` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `xmas_rednose` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `xmas_skids` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `winterolympics_medal` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `winterolympics_skihat` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `winterolympics_bunny` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `carnival_sweets` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `carnival_whistle` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `carnival_streamers` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `easter_eggs` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `easter_bunnies` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `ghostbusters_event_trap` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `ghostbusters_event_pkemeter` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `ghostbusters_event_accu` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `worldcup_event_1_toothbrush` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `worldcup_event_1_vuvuzela` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `worldcup_event_1_minifan` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `worldcup_event_2_button` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `worldcup_event_2_mat` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `worldcup_event_2_keyringpendant` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `birthday_event_firecracker` | `{"reward_type":1,"reward_chance":0.15,"reward_reference":"all"}` |
| `octoberfest_prezel` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `octoberfest_hat` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `olympia_2016_rio_stick` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `olympia_2016_rio_booklet` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `sci_fi_week_pin` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `sci_fi_week_goodie_bag` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `environmental_week_garbage` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `environmental_week_form` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `video_games_week_repeater` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `video_games_week_headset` | `{"reward_type":1,"reward_chance":0.1,"reward_reference":"all"}` |
| `server_launch_primal_bone` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_utopia_deo` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_dystopia_gardengnome` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_exotic_places_ice` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_blooming_nature_lotus` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_scorching_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_fantastic_adventures_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_mythical_gods_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_tech_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `sun_moon_stars_season_arc_event_2024_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `doom_and_darkness_season_arc_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `arctic_exploration_season_arc_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `mayan_empire_season_arc_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_2025_october_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_2026_overgrown_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_2026_gangsta1_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |
| `server_launch_2026_cube_event_item` | `{"reward_type":1,"reward_chance":0.2,"reward_reference":"all"}` |

### Configuração de localização dos tesouros

```json
{
  "standard_balancing": {
    "levels": {
      "1": {
        "grid_size": 3,
        "tokens": 3,
        "bonus_reward": [
          5
        ],
        "bonus_reward_factor": [
          3
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 0
      },
      "2": {
        "grid_size": 3,
        "tokens": 4,
        "bonus_reward": [
          1
        ],
        "bonus_reward_factor": [
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 60
      },
      "3": {
        "grid_size": 4,
        "tokens": 4,
        "bonus_reward": [
          2
        ],
        "bonus_reward_factor": [
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 60
      },
      "4": {
        "grid_size": 4,
        "tokens": 5,
        "bonus_reward": [
          0
        ],
        "bonus_reward_factor": [
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 60
      },
      "5": {
        "grid_size": 5,
        "tokens": 5,
        "bonus_reward": [
          5,
          2
        ],
        "bonus_reward_factor": [
          3,
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 300
      },
      "6": {
        "grid_size": 5,
        "tokens": 5,
        "bonus_reward": [
          5,
          3
        ],
        "bonus_reward_factor": [
          3,
          25
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 300
      },
      "7": {
        "grid_size": 5,
        "tokens": 5,
        "bonus_reward": [
          1,
          2
        ],
        "bonus_reward_factor": [
          1,
          1
        ],
        "bonus_reward_reference": [
          "",
          ""
        ],
        "unlock_duration": 300
      },
      "8": {
        "grid_size": 5,
        "tokens": 6,
        "bonus_reward": [
          5,
          2
        ],
        "bonus_reward_factor": [
          3,
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 600
      },
      "9": {
        "grid_size": 6,
        "tokens": 6,
        "bonus_reward": [
          0,
          2
        ],
        "bonus_reward_factor": [
          1,
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 600
      },
      "10": {
        "grid_size": 6,
        "tokens": 6,
        "bonus_reward": [
          5,
          1
        ],
        "bonus_reward_factor": [
          3,
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 600
      },
      "11": {
        "grid_size": 6,
        "tokens": 7,
        "bonus_reward": [
          5,
          2
        ],
        "bonus_reward_factor": [
          5,
          1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 900
      },
      "12": {
        "grid_size": 6,
        "tokens": 7,
        "bonus_reward": [
          1,
          2
        ],
        "bonus_reward_factor": [
          1.1,
          1.1
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 900
      },
      "13": {
        "grid_size": 7,
        "tokens": 7,
        "bonus_reward": [
          5,
          4
        ],
        "bonus_reward_factor": [
          4,
          15
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 900
      },
      "14": {
        "grid_size": 7,
        "tokens": 7,
        "bonus_reward": [
          5,
          2
        ],
        "bonus_reward_factor": [
          4,
          1.2
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1200
      },
      "15": {
        "grid_size": 7,
        "tokens": 7,
        "bonus_reward": [
          5,
          2,
          1
        ],
        "bonus_reward_factor": [
          4,
          1.2,
          1.3
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1200
      },
      "16": {
        "grid_size": 7,
        "tokens": 7,
        "bonus_reward": [
          5,
          2,
          1
        ],
        "bonus_reward_factor": [
          5,
          1.3,
          1.5
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1200
      },
      "17": {
        "grid_size": 8,
        "tokens": 7,
        "bonus_reward": [
          3,
          2,
          1
        ],
        "bonus_reward_factor": [
          50,
          1.4,
          1.7
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1500
      },
      "18": {
        "grid_size": 8,
        "tokens": 7,
        "bonus_reward": [
          5,
          2,
          1
        ],
        "bonus_reward_factor": [
          5,
          1.5,
          1.9
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1500
      },
      "19": {
        "grid_size": 8,
        "tokens": 7,
        "bonus_reward": [
          0,
          2,
          1
        ],
        "bonus_reward_factor": [
          3,
          1.6,
          2
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1500
      },
      "20": {
        "grid_size": 8,
        "tokens": 8,
        "bonus_reward": [
          5,
          2,
          1
        ],
        "bonus_reward_factor": [
          5,
          1.7,
          2.2
        ],
        "bonus_reward_reference": [
          "",
          "",
          ""
        ],
        "unlock_duration": 1800
      }
    }
  }
}
```

### Secções auxiliares de torres

- `tower_event_objectives`: 6 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.
- `tower_event_regions`: 6 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.
- `tower_event_environment_styles`: 7 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.
- `tower_event_tower_objectives`: 1 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.
- `tower_event_floor_levels`: 30 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.
- `tower_event_elevator_levels`: 3000 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.
- `tower_event_vault_levels`: 3000 entradas. Consultar nas constantes; tabelas extensas de níveis não são duplicadas aqui.

## 8. Constantes escalares associadas

| Constante | Valor bruto |
| --- | --- |
| worldboss_event_instant_finish_premium_amount | 2 |
| worldboss_event_multi_attack_iterations | 3 |
| worldboss_event_multi_attack_premium_amount | 1 |
| worldboss_event_multi_attack_min_hp | 0.1 |
| event_quest_objective_reward_game_currency_time | 600 |
| event_quest_objective_reward_game_currency_percentage_base | 1 |
| event_quest_objective_reward_game_currency_exp | 1.9 |
| event_quest_objective_reward_premium_currency_base | 1 |
| event_quest_objective_reward_stat_points_base | 3 |
| event_quest_objective_reward_xp_percentage_base | 0.25 |
| event_quest_objective_instant_finish_amount | 29 |
| event_quest_min_level | 5 |
| hidden_object_instant_finish_cost_per_object | 10 |
| hidden_object_event_quest_min_level | 5 |
| event_treasure_min_level | 5 |
| event_treasure_open_cell_premium_cost | 1 |
| event_treasure_free_reveal_item_cooldown | 10800 |
| event_treasure_free_reveal_item_amount | 20 |
| event_treasure_reset_progress_premium_cost | 49 |
| event_treasure_unique_reward_alternative_type | 3 |
| event_treasure_unique_reward_alternative_factor | 10 |
| event_treasure_unique_reward_alternative_identifier | "" |
| tower_event_min_level | 5 |
| TowerEventResetProgressPremiumCost | 49 |

## 9. Ações relacionadas e encaminhamento atual

Inventário do Router após a implementação. O nome de um handler próprio não prova que toda a família esteja completa ou validada.

| Ação | Handler |
| --- | --- |
| assignEventQuest | assignEventQuest.php |
| getEventHubEntries | getEventHubEntries.php |
| assignTreasureEvent | treasureEvents.php |
| createNextTreasureEventLevel | treasureEvents.php |
| openTreasureCell | treasureEvents.php |
| collectTreasureCellReward | treasureEvents.php |
| claimTreasureEventReward | treasureEvents.php |
| claimFreeTreasureRevealItems | treasureEvents.php |
| abortWorldbossAttack | worldboss.php |
| assignDrawEvent | drawEvents.php |
| assignHiddenObjectEventQuest | stateEcho.php |
| assignWorldbossEvent | worldboss.php |
| checkForWorldbossAttackComplete | worldboss.php |
| claimDrawEventReward | drawEvents.php |
| claimEventQuestObjectiveRewards | eventQuests.php |
| claimEventQuestRewards | eventQuests.php |
| claimHiddenObjectEventQuestReward | stateEcho.php |
| claimHiddenObjectEventQuestRewards | stateEcho.php |
| claimWorldbossEventRewards | worldboss.php |
| collectHiddenObjectEventQuestObject | stateEcho.php |
| collectTowerEventObjectiveRewards | stateEcho.php |
| drawEventReward | drawEvents.php |
| finishWorldbossAttack | worldboss.php |
| instantFinishEventQuestObjective | eventQuests.php |
| instantFinishHiddenObjectEventQuest | stateEcho.php |
| instantFinishWorldbossAttack | worldboss.php |
| resetTowerEventProgress | stateEcho.php |
| resetTreasureEventProgress | treasureEvents.php |
| skipTowerEventObjective | stateEcho.php |
| skipTreasureEventLevelUnlock | treasureEvents.php |
| startTowerEvent | stateEcho.php |
| startWorldbossAttack | worldboss.php |
| syncTowerEvent | stateEcho.php |

## 10. Trabalho necessário para uma implementação completa

Esta secção é uma orientação de implementação, não uma lista de correções já realizadas.

1. **Ocorrências e calendário:** definir identificador de ocorrência, template, início/fim, fuso e política de repetição. Separar definição estática de ocorrência anual; impedir que progresso/prémios transitem acidentalmente para uma nova edição.
2. **Aceitação:** validar nível, janela ativa, template e evento já atribuído; criar estado próprio do personagem e devolver o objeto com ID correto.
3. **Progresso:** integrar missões, treinos, duelos, vilões, loja, lavagem e consumo de energia conforme cada tipo/referência. Definir precisamente se contam ações iniciadas ou concluídas e impedir contagem repetida da mesma ação.
4. **Objetos:** validar onde podem ser obtidos, chance, referência e disponibilidade. Não inferir que são itens normais de inventário apenas por terem imagens/identificadores.
5. **Resgate individual:** aplicar a fórmula confirmada, validar objetivo pronto e marcar resgate na mesma transação. Conservar o contrato do contador para o cliente.
6. **Conclusão instantânea:** recalcular progresso e preço, validar saldo e executar débito/conclusão atomicamente.
7. **Prémio final:** validar conclusão/expiração, escolha do item, capacidade e descarte; impedir resgate duplicado e incluir itens referenciados na resposta.
8. **Expiração:** confirmar como se distinguem aborto, conclusão e término com prémios pendentes; não inventar o estado 4 nem apagar pendências sem regra demonstrada.
9. **Hub e login:** anunciar apenas ocorrências elegíveis, integrar login/estado normal/respostas parciais e sincronizar o relógio do jogo.
10. **Schema:** comparar a BD existente com o schema inicial. Se forem necessários campos/índices/tabelas, fornecer migração idempotente além do initdb; não executar DDL dentro de transações de jogo.

Usar locks/transações para progresso, consumo e prémios; se já existir transação, usar savepoint. Não entregar recompensas pela simples reabertura do diálogo ou por repetição do pedido. A propriedade e os IDs devem ser validados no backend.

### Validação proposta

- Offline: catálogo, metas, referências, traduções, fórmulas e compatibilidade dos campos.
- PDO simulado: caminhos de erro e shape de respostas; não substitui MySQL.
- MySQL com rollback: aceitação, avanço, resgate individual/final, débito premium, inventário cheio, expiração e pedidos repetidos/concorrentes.
- Manual: convite, objetivos, contador, escolha de prémio, fim do evento e troca de ocorrência no cliente HTML5.
- Verificar os fluxos via `autoLoginUser`, `Live::accountState` e respostas parciais. Testes devem aceitar nome do herói e explicar qualquer preparação persistente.

### Questões ainda por confirmar com evidência

- Calendário oficial atual e política de recorrência.
- Critérios oficiais de contagem e matching, para além da política local implementada.
- Unidades motivacionais confirmadas nos textos e ligadas ao débito real; verificar equivalência num HAR oficial se disponível.
- Regras completas de drops e ligação entre objetos temáticos, missões e vilões.
- Fórmulas oficiais de escala das recompensas finais e atributos dos itens; o enum final já foi confirmado no cliente.
- Estado após resgate final, comportamento ao expirar e coexistência de ocorrências.
- Momento de fixação do nível para cálculo dos prémios.
- Pontos de temporada efetivamente atribuídos e proteção contra duplicação.
- Respostas oficiais destas ações, sobretudo resgates e conclusão instantânea.

## 11. Como rever este documento

Ao atualizar os assets, descomprimir zlib e comparar as definições e os textos; atualizar fichas, contagens e hashes. Comparar o Router e os métodos reais antes de mudar o estado de implementação. Não tratar este levantamento como prova de integração validada.

Este documento não modifica regras nem protocolo. Funcionalidades por implementar não foram registadas como bugs corrigidos.

