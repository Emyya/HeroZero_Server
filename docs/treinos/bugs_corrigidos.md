# Bugs de Treino Corrigidos

Registo dos bugs encontrados e corrigidos no sistema de treino.
Para integrar no `docs/bugs_encontrados.md` quando for conveniente.

---

## BUG-TR-001 — `max_training_count` base a 3 em vez de 10

**Sintoma:** O máximo de unidades de treino era 3/3 em vez de 10/10.
**Causa:** `syncTrainingForLevel()` tinha `max(3, ...)` hardcoded.
**Fix:** Substituído por `GuildArtifacts::effectiveMax('training_count', 10, $guildId)`.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `syncTrainingForLevel()`

---

## BUG-TR-002 — Bónus do booster de guilda não contabilizado em `max_training_count`

**Sintoma:** Uma team com trofeu tipo-3 (+5) e booster ativo (+7) mostrava só 15 em vez de 22.
**Causa:** `syncTrainingForLevel()` só contava os troféus; o booster ativo da guilda (`active_training_booster_id`) não era lido.
**Fix:** Consulta ao `guild` para verificar `active_training_booster_id` + `ts_active_training_boost_expires`; adiciona o montante (+4 ou +7) ao `effectiveMax`.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `syncTrainingForLevel()`

---

## BUG-TR-003 — Reset diário de unidades usava timezone do sistema em vez do relógio interno

**Sintoma:** Reset a horas erradas consoante o timezone do servidor.
**Causa:** Cálculo de meia-noite usava `new \DateTimeZone('Europe/Lisbon')` em vez do GameTime.
**Fix:** `$todayMidnight = GameTime::now() - (GameTime::now() % 86400)` — usa o relógio interno do servidor.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `syncTrainingForLevel()`

---

## BUG-TR-004 — Botão de atualizar pool ligado à action errada

**Sintoma:** O botão de refrescar ofertas de treino não fazia nada útil.
**Causa:** O router apontava `refreshTrainingPool` para `syncAccountState.php`.
**Fix:** Criado `actions/refreshTrainingPool.php` com lógica própria: valida treino ativo, cooldown de 600 s (livre) ou 1 donut (premium), atualiza `ts_last_training`.
**Ficheiro:** `server-laravel/actions/refreshTrainingPool.php` (novo), `server-laravel/app/HeroZero/Router.php`

---

## BUG-TR-005 — Pool de treino gerava 4 ofertas iguais (mesmo stat, mesmo tier)

**Sintoma:** Os 4 slots do painel mostravam sempre o mesmo treino.
**Causa:** O código gerava 12 entradas planas sem distinção de stat; o cliente só mostra 4 slots (um por stat).
**Fix:** `currentTrainingPool()` gera exactamente 4 IDs (um por stat base 1-4), com tier aleatório por seed `crc32(char_id|ts_last_training)`. Tier encode em stat_type: 1-4 = tier1, 11-14 = tier2, 21-24 = tier3.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `currentTrainingPool()`

---

## BUG-TR-006 — Barra de progresso mostrava 00:00:00 em vez do tempo restante

**Sintoma:** A barra de tempo no painel de treino ativo mostrava sempre 00:00:00.
**Causa:** O campo `ts_end` era sempre 0 — não era guardado ao iniciar o treino.
**Fix:** `startTraining()` calcula `ts_end = GameTime::now() + duration` e insere na tabela `training`. `trainingsData()` lê `ts_end` da linha ativa e passa ao cliente.
**Ficheiros:** `server-laravel/app/HeroZero/Character.php` — `startTraining()`, `trainingsData()`
**Schema:** Coluna `training.ts_end INT NOT NULL DEFAULT 0` adicionada.

---

## BUG-TR-007 — Ecrã de treino ficava preso após conclusão (não voltava às ofertas)

**Sintoma:** Após reclamar a 3.ª estrela, o cliente ficava preso no ecrã de progresso.
**Causa:** `ts_last_training_finished` era sempre 0 no overlay do character. O cliente usa este campo para detectar que o treino terminou e navegar de volta ao painel de ofertas.
**Fix:** `claimTrainingStar()` agora grava `ts_last_training_finished = GameTime::now()` ao reclamar a 3.ª estrela. `overlayCharacter()` lê o valor real em vez de devolver 0 fixo.
**Ficheiros:** `server-laravel/app/HeroZero/Character.php` — `claimTrainingStar()`, `overlayCharacter()`
**Schema:** Coluna `character.ts_last_training_finished INT NOT NULL DEFAULT 0` adicionada.

---

## BUG-TR-008 — Crash `get_training() is undefined` ao fazer autoLoginUser com treino ativo

**Sintoma:** Após iniciar um treino e recarregar, o cliente crashava com `TypeError: can't access property "refreshTrainingQuests", b.get_character().get_training() is undefined`.
**Causa:** `startTraining()` atualiza `ts_last_training`, o que muda o seed de `currentTrainingPool()`. A pool regenerada podia não conter o `stat_type` do treino ativo → todos os 4 slots ficavam com `status=1` → `get_training()` devolvia null no cliente.
**Fix:** `trainingsData()` força o slot do stat base correspondente ao treino ativo a usar o `stat_type` real do treino, mesmo que a pool tenha rodado.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `trainingsData()`

---

## BUG-TR-009 — Pontos de treino não visíveis no painel de atributos

**Sintoma:** Após 3 estrelas de treino de força, o stat "Força" não aumentava visivelmente (ficava em 1000).
**Causa:** `claimTrainingStar()` só incrementava `stat_trained_X` (contador interno). O cliente mostra `stat_base_X` como o atributo base visível. No servidor oficial, o treino incrementa AMBOS.
**Fix:** `claimTrainingStar()` passa a incrementar também `stat_base_X` (além de `stat_trained_X`).
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `claimTrainingStar()`

---

## BUG-TR-010 — `training_quests` na resposta sem treino ativo causava crash

**Sintoma:** `training_quests` incluído na resposta quando não havia treino ativo → `get_training()` null → crash ao processar `refreshTrainingQuests`.
**Causa:** A resposta de `autoLoginUser` incluía `training_quests` do template Replay mesmo sem treino ativo.
**Fix:** `Live::attachTrainingState()` já fazia `unset($data['training_quests'])` quando `trainingQuestsData()` devolvia `[]` — o bug TR-008 é que mascarava este (o treino ativo não era encontrado). Resolvido em conjunto com TR-008.
**Ficheiro:** `server-laravel/app/HeroZero/Live.php` — `attachTrainingState()`

---

## BUG-TR-011 — Ecrã da batalha ficava preso após quest de luta de treino

**Sintoma:** Após escolher uma quest de luta (type=2) no painel de treino e a luta terminar, o ecrã da batalha não fechava — o cliente ficava preso.
**Causa:** `startTrainingQuest` devolvia `fight_battle_id=0` e nenhum `training_battle`. O cliente abre o painel `"battle"` mas não conseguia carregar nenhuma batalha → ecrã preso.
**Fix:** `startTrainingQuest` para type=2 agora simula o combate (`battleProfile` + `questNpcProfile` + `Fight`), guarda o resultado na tabela `battle`, e devolve `fight_battle_id` preenchido na quest + `training_battle` na resposta. O action `trainingQuest.php` inclui `training_battle` no extra quando presente.
**Ficheiros:** `server-laravel/app/HeroZero/Character.php` — `startTrainingQuest()`, `server-laravel/actions/trainingQuest.php`

---

## BUG-TR-012 — `LOC` em todas as labels do painel de batalha das quests de treino

**Sintoma:** Ao iniciar uma quest de luta (type=2), o painel de batalha mostrava `LOC` no nível, na barra de vida e nos 4 atributos, tanto do herói como do NPC. Só os nomes apareciam corretos.

**`LOC` NÃO é tradução em falta.** É o `text:` por defeito dos labels nas definições de layout do cliente (`text:"LOC"` em `HeroZero.min.js`). Ver `LOC` significa que **o código que preenche o label nunca correu** — a barra de vida só recebe texto em `BD.init()`/`refresh()`, que precisam dos hitpoints vindos de `profile_*_stats` da batalha.

**Causa (duas partes):**
1. `startTrainingQuest()` construía o `training_battle` à mão com só `{id, winner, rounds}`, sem `profile_a_stats`/`profile_b_stats`/`ts_creation`.
2. **A principal** — regressão introduzida pelo fix do [BUG-TR-011]: suprimir `training_quests` da resposta. O cliente liga a batalha assim:
   ```js
   for (a = get_training().get_trainingQuests().iterator(); a.hasNext();)
     if (e = a.next(), e.get_fightBattleId() == c.get_training_battle().get_id())
       { e.refreshBattle(c.get_training_battle()); break }
   ```
   Ou seja, procura a batalha na **lista de quests do treino**. Mas `Live::attachTrainingState()` mandava sempre `trainings` (plural), e no cliente:
   - `refreshTrainings` (plural) → `this._trainings = new Ec` — **apaga** tudo e cria `Vm` novos com lista de quests vazia
   - `refreshTraining` (singular) → `createOrUpdateMap(...)` — atualiza **no sítio**, preserva a lista

   Com o plural a limpar a lista e o `training_quests` suprimido, nada a repunha → o loop não encontrava a quest → `refreshBattle` nunca corria → labels ficavam no `"LOC"` do layout.

**Fix:** Replicar o servidor oficial: em `startTrainingQuest` com batalha, responder com `training` (**singular**) em vez de `trainings`+`training_quests`. Assim a lista de quests é preservada, o `refreshTrainingQuest` (singular) atualiza o `fight_battle_id` no sítio, e o loop do `training_battle` encontra a quest. Além disso, `training_battle` passa a usar `battleData()` (`SELECT *`), devolvendo as 6 colunas.

**Confirmação (HAR oficial):** a resposta de `startTrainingQuest` traz `training`, `training_quest`, `training_battle` — e **não** traz `trainings` nem `training_quests`. O `training_battle` tem exatamente `['id','ts_creation','profile_a_stats','profile_b_stats','winner','rounds']`.

**Ficheiros:** `app/HeroZero/Live.php` — `attachTrainingState()`, `accountState()`; `actions/trainingQuest.php`; `app/HeroZero/Character.php` — `startTrainingQuest()`

> **Nota:** `trainings` (plural) só pode ir numa resposta acompanhado de `training_quests`. Mandar o plural sozinho deixa o pool a apontar para quests inexistentes. Quando é preciso atualizar o treino sem mexer nas quests, usar `training` (singular).

---

## BUG-TR-013 — Barra de energia de treino mostrava 35/35 em vez de 30/30

**Sintoma:** A barra de energia de treino aparecia a `35/35` em vez de `30/30`.
**Causa:** `GuildArtifacts::BONUS_PER_TROPHY[3]` (troféu de Motivação) aplicava `+5` a **dois** recursos: `training_count` e `training_energy`. O bónus pertence **apenas às unidades de treino** (`training_count`, ex.: 22/22). A barra de energia é **30/30 fixa** e não recebe bónus de troféu.
**Fix:** Removido `training_energy` de `BONUS_PER_TROPHY[3]` e do `match` em `effectiveMax()`.
**Verificado:** resposta passou a `training_energy=27 max_training_energy=30`.
**Ficheiro:** `server-laravel/app/HeroZero/GuildArtifacts.php`

> **Tentativa falhada antes deste fix:** encher a barra até ao `effectiveMax` (35) no `startTraining()`. Estava errado — tratava o sintoma e não a causa. Revertido.

---

## BUG-TR-014 — Treino expirado ficava preso e impedia o reset da energia a 30/30

**Sintoma:** A energia não voltava a `30/30` ao começar um treino novo. Na prática, não era sequer possível começar um treino novo.
**Causa:** Regressão introduzida pelo fix do timer (ver BUG-TR-006/007). Quando `ts_end` expirava, `trainingsData()` marcava `used_resources = 100` **e `iterations = 3`**. Mas `iterations` é o contador de estrelas **já reclamadas** — pô-lo a 3 fazia `claimTrainingStar()` lançar `errClaimTrainingStarAlreadyClaimed`, o `status` nunca chegava a 4, e o treino ficava **ativo para sempre**. Como a reposição da energia vive dentro do `startTraining()` e este rebentava antes com `errStartTrainingAlreadyActive`, a energia nunca era reposta.
**Fix (duas partes):**
1. Na expiração, só `used_resources = 100` (as 3 estrelas ficam *disponíveis*); **nunca** mexer em `iterations`.
2. `startTraining()` fecha (`status = 4`) um treino cujo `ts_end` já passou, em vez de lançar `errStartTrainingAlreadyActive`.
**Verificado:** três treinos seguidos — `30/30` → gasta → expira → `30/30` → gasta → expira → `30/30`.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `trainingsData()`, `startTraining()`

---

## BUG-TR-015 — `trainings` (plural) em toda a resposta apagava a lista de quests

**Sintoma:** O `LOC` do BUG-TR-012 voltava a aparecer mesmo depois de corrigida a resposta do `startTrainingQuest`.
**Causa:** `Live::attachTrainingState()` mandava `trainings` (plural) em **todas** as respostas. No cliente, `refreshTrainings` faz `this._trainings = new Ec` — destrói os objetos e **apaga a lista de quests**. O `syncGame`, que corre em polling constante, apagava assim a batalha que o `startTrainingQuest` acabara de ligar.
**Descoberta (HAR oficial):** o servidor oficial quase nunca manda o plural. Contagem de 94 pedidos:

| Action | Chaves |
|---|---|
| `syncGame`, `getGuildLog`, `claimTrainingStar`, `buyTrainingEnergy` | `training` (singular) |
| `startTrainingQuest` | `training`, `training_quest`, `training_battle` |
| `startTraining`, `claimTrainingQuestRewards` | `training`, `training_quests` |
| `refreshTrainingPool`, `getDuelOpponents` | `trainings` (plural) |

**Fix:** `attachTrainingState()` passa a enviar `training` (singular) sempre que há treino ativo. O plural fica reservado ao boot (`autoLoginUser`, com `$forcePlural = true`, porque o mapa do cliente está vazio) e ao refresh de ofertas.
**Ficheiros:** `server-laravel/app/HeroZero/Live.php` — `attachTrainingState()`, `accountState()`; `actions/autoLoginUser.php`

---

## BUG-TR-016 — Ecrã da batalha continuava preso (botão de skip sem efeito)

**Sintoma:** Com o `LOC` já resolvido, a luta mostrava os valores certos mas o ecrã não fechava; o botão de saltar não fazia nada.
**Causa:** O cliente nunca era informado de que a quest tinha terminado. O oficial marca `training.training_quest_id` com o id da quest ao iniciar e repõe-no a **0** no `claimTrainingQuestRewards` — é esse `0` que fecha o painel. Nós devolvíamos `training_quest_id = 0` **fixo**, portanto a transição nunca acontecia. A coluna `training.training_quest_id` nem existia.
**Fix:** Criada a coluna `training.training_quest_id`. `startTrainingQuest()` grava lá o id; `claimTrainingQuestRewards()` repõe a 0. Alinhados no mesmo caminho, contra o HAR oficial:

| | Antes | Agora (= oficial) |
|---|---|---|
| `status` da quest ao iniciar | `3` | `2` |
| `won` | fixo `true` | resultado real da luta |
| Pool após cada claim | mesmos ids | 3 ofertas com **ids novos** |
| Progresso da barra | somado no start | somado no **claim** |
| `claimTrainingQuestRewards` | enviava `training_quest` | só `training` + `training_quests` |

**Verificado:** três rondas — `quest_id` sobe ao iniciar, volta a `0` no claim, pool com ids novos, estrela contabilizada. Mantida a tolerância a duplo-clique (claim repetido é no-op em vez de erro).
**Ficheiros:** `server-laravel/app/HeroZero/Character.php` — `startTrainingQuest()`, `claimTrainingQuestRewards()`, `trainingQuestsData()`, `trainingsData()`; `actions/trainingQuest.php`; `docker/initdb/01_schema.sql`

---

## BUG-TR-017 — Limiares das estrelas e forma das recompensas divergiam do oficial

**Sintoma:** O servidor podia recusar uma estrela que o cliente já mostrava como disponível.
**Causa:** Usávamos limiares inventados **34/67/100**. O cliente lê os limiares da tabela `training_ratings` em `constants_json.data` (`nk`/`CTrainingRating`), que são **10/40/100**. O cliente calcula `progressValue = floor(energy / needed_energy * 100)` e oferece a estrela cujo `needed_progress` já foi atingido — divergir dos limiares dessincroniza cliente e servidor.
**Descoberta:** `training_ratings` tem também `reward_factor` (0.2 / 0.5 / 1.0) e `special_factor` (0 / 0 / **1**). Confirmado no HAR: `coins` 1335 / 3338 / 6676 → rácios **0,2 / 0,5 / 1,0** exatos; idem no XP (425/1063/2126). Ou seja, **recompensa = base × reward_factor**, e a 3.ª estrela tem um prémio especial.
**Fix:** Constante `TRAINING_RATINGS` como cópia exata da tabela do cliente. Limiares e recompensas passam a derivar dela. Helper único `trainingStarReward()` usado tanto no `rewards_star_N` (anúncio) como no crédito real — anunciar um valor e creditar outro fazia o diálogo mentir.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `trainingStarReward()`, `claimTrainingStar()`, `trainingsData()`

---

## BUG-TR-018 — Diálogo "Treino Concluído" nunca aparecia

**Sintoma:** Ao atingir 100% / 3.ª estrela, o diálogo de conclusão (`dialog/training_complete_new`) não abria.
**Causa:** `claimTrainingStar()` punha `status = 4` na 3.ª estrela. Isso tirava o treino do `activeTrainingRow()` (que só procura `status = 2`), o `active_training_id` ia a 0 e no cliente `get_training()` devolvia null. Mas o diálogo exige o objeto do treino:

```js
var a = get_character().get_training();
null != a && showDialog(new qv(a))     // com null, não abre nada
```

**Confirmação (HAR oficial):** `claimTrainingStar` com 3 estrelas devolve `training.status = 2` e mantém `active_training_id`; só o `finishTraining` seguinte põe `status = 3` e limpa o id.
**Fix:** `claimTrainingStar()` deixa de mexer no `status`. `finishTraining()` passa a decidir por `iterations >= 3` (e não por `status === 4`) e marca `status = 3`.
**Cadeia de gatilhos no cliente** (timer do painel `training_progress`):

```js
if (a.get_hasClaimableStars())        showDialog(new Ys(a))   // recompensa da estrela
else if (a.get_isComplete())
    3 > a.get_claimedStars() ? showDialog(new ku(a))          // "estender tempo?"
                             : showTrainingCompleteDialog()   // "Treino Concluído"
```

**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `claimTrainingStar()`, `finishTraining()`

---

## BUG-TR-019 — Pontos do atributo treinado fixos em +1 e sem bónus na 3.ª estrela

**Sintoma:** Todas as estrelas davam +1 ao atributo treinado; a 3.ª não dava prémio extra.
**Fix (regra do projeto, não do oficial):**
- Pontos do atributo treinado: **+1 / +2 / +3** por estrela (`TRAINING_STAT_POINTS`), refletidos em `stat_points_star_1/2/3`.
- 3.ª estrela ganha um bónus sorteado: **30% ponto de atributo livre** (`stat_points_available`), **30% item**, **40% nada**.

**Sorteio determinístico:** o `rewards_star_3` é mostrado *antes* de reclamar, portanto um sorteio aleatório no momento do claim faria o diálogo prometer uma coisa e entregar outra. O sorteio é feito a partir de uma seed estável e persistido.
**Nota:** usar `crc32` para a seed **não serve** — com ids sequenciais e timestamps quase iguais os valores ficam agrupados (6 sorteios seguidos deram todos ≥ 60, ou seja "nada" em todos). Trocado por `hexdec(substr(md5($seed), 0, 8)) % 100`, que em 3000 amostras dá 30,2% / 29,9% / 39,9%.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `TRAINING_STAT_POINTS`, `trainingOfferBonus()`, `claimTrainingStar()`

---

## BUG-TR-020 — Bónus da 3.ª estrela não aparecia no painel de ofertas

**Sintoma:** No ecrã `training_offers` o 3.º slot das ofertas ficava sem ícone de item nem de pontos.
**Causa:** O sorteio só era calculado para o treino **ativo**; para ofertas por começar passava-se sempre `'none'`.
**Armadilha:** não bastava calcular o sorteio na oferta. `startTraining()` atualiza `ts_last_training`, que faz parte da seed da oferta — o sorteio **mudava** ao começar e o treino entregava algo diferente do que a oferta prometera.
**Fix:** Sorteio feito na oferta (`trainingOfferBonus($offerId)`) e **persistido** em `training.bonus_roll` no `startTraining()`, antes de o timestamp mudar. Verificado em 5 rondas: o que é entregue coincide sempre com o que a oferta mostrou.
**Ficheiros:** `server-laravel/app/HeroZero/Character.php` — `trainingOfferBonus()`, `trainingBonusRoll()`, `startTraining()`; `docker/initdb/01_schema.sql`

---

## BUG-TR-021 — Pool gerava treinos mais caros do que as unidades disponíveis

**Sintoma:** Com 3 unidades motivacionais restantes, apareciam ofertas de custo 5, 8, etc. — impossíveis de comprar.
**Causa:** `currentTrainingPool()` sorteava o custo sempre no intervalo 1-9, ignorando o `training_count`.
**Fix:** Teto do sorteio passa a `max(1, min(9, training_count))`. Verificado com 40 refreshes por cenário: com 3 unidades o custo máximo gerado é 3; com 1 unidade é 1.
**Nota:** com 0 unidades ainda são geradas ofertas de custo 1 (piso mínimo) para o painel não ficar vazio; o `startTraining` recusa-as com `errRemoveTrainingNotEnough`.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `currentTrainingPool()`

---

## BUG-TR-022 — Item da recompensa não aparecia (id inventado)

**Sintoma:** O 3.º slot não mostrava ícone de item, mesmo quando a recompensa dizia ter um.
**Causa:** Enviávamos `"item": 1` como marcador. Mas o cliente resolve o item na coleção do **próprio jogador**:

```js
ec.prototype.get_item = function(){
  return 0 == this.get_itemId() ? null
       : get_character().getItemById(this.get_itemId())   // id tem de ser DELE
};
```

`getItemById(1)` devolve null → sem ícone.
**Confirmação (HAR oficial):** as 4 ofertas trazem ids **reais e sequenciais** (694162-694165) e o jogador **já os possui** antes de o treino começar — os itens são pré-gerados junto com a pool.
**Fix:** `trainingPoolRewardItems()` pré-gera os itens das ofertas que sortearam "item", guarda o mapa `{offerId: itemId}` em `character.training_pool_items` e regenera-o quando `ts_last_training` muda. Os itens de pools anteriores que ninguém ganhou são **reciclados** (apagados) — nunca os que estão presos a um treino, equipados ou na mochila.
**Ficheiros:** `server-laravel/app/HeroZero/Character.php` — `trainingPoolRewardItems()`, `trainingsData()`, `startTraining()`; `docker/initdb/01_schema.sql`

---

## BUG-TR-023 — Item aparecia na recompensa mas não ia para a mochila

**Sintoma:** O ícone do item aparecia na 3.ª estrela, mas o item nunca chegava ao inventário.
**Causa:** Existir na tabela `items` com o `character_id` **não é** possuir o item. O inventário são slots na tabela `inventory` (`bag_item1_id` … `bag_item18_id`) — e nunca escrevíamos lá. O ícone aparecia porque o cliente resolve o id em `items`; a mochila continuava vazia.
**Fix:** Helper `putItemInBag()` coloca o item na primeira slot livre; chamado no `claimTrainingStar()` da 3.ª estrela (e só aí — antes de ganho o item não deve estar na mochila). É idempotente: se o item já estiver numa slot, não duplica.
**Verificado:** antes do treino → não; após `startTraining` → não; estrelas 1 e 2 → não; **estrela 3 → sim (slot 1)**; sobrevive ao refresh seguinte.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `putItemInBag()`, `claimTrainingStar()`, `trainingPoolRewardItems()`

---

## BUG-TR-024 — Com 0 unidades ainda apareciam ofertas em vez do ecrã "sem treinos"

**Sintoma:** Com `0 / 22` unidades motivacionais o painel continuava a mostrar 4 ofertas (de custo 1), impossíveis de comprar. Devia mostrar o ecrã "No momento, não há nenhum treino para você escolher".
**Causa:** Regressão do fix do [BUG-TR-021]: o teto do custo era `max(1, min(9, training_count))`, e o piso de 1 fazia com que **sempre** houvesse pool, mesmo com 0 unidades.
**Como o cliente decide** (`Sr.prototype.refresh`):

```js
get_hasTrainingOffers()            // = character.training_pool != ""
  ? mostrar as 4 ofertas
  : (mostrar noOffersContent,
     trainingCount > 0 ? info_no_offers             // cooldown, com botão "Pular"
                       : info_no_training_sessions) // 0 unidades
```

**Fix:** `currentTrainingPool()` devolve `[]` quando `training_count <= 0` **e** não há treino a decorrer; `overlayCharacter()` emite `training_pool = ''` nesse caso.

**Duas armadilhas neste fix:**
1. `json_encode([])` dá `'[]'`, **não** `''`. Com `'[]'` o cliente considera que há ofertas e rebenta em `offer1.refresh(undefined)`. É preciso emitir a string vazia explicitamente.
2. **Com treino a decorrer a pool não pode esvaziar**, mesmo com 0 unidades — senão o treino em curso não vem em `trainings`, o mapa do cliente fica sem ele e `get_training()` devolve null (é o [BUG-TR-008] outra vez).

**A contagem regressiva não vem do servidor:** o cliente calcula-a com `K.get_serverDateTime().get_secondsUntilTomorrow()` quando `trainingCount <= 0`. Ao chegar a 0 dispara sozinho `refreshTrainingPool`.
**Verificado:** 22/22, 3/22 e 1/22 → ofertas (com o custo limitado); 0/22 → ecrã vazio. Com 0 unidades **e** treino ativo → pool preenchida e `training` singular presente.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `currentTrainingPool()`, `overlayCharacter()`

---

## BUG-TR-025 — Botão "pular" da stat_quest inativo após refresh (quest ativa com status=1)

**Sintoma:** Com uma stat_quest (type=3) em curso, ao fazer refresh o painel ficava bloqueado — o botão de skip/reclamar não respondia.
**Causa:** `trainingQuestsData()` gerava sempre todas as quests com `status=1`. Após um refresh, o `autoLoginUser` enviava `training_quest_id != 0` (a quest está em curso na BD) mas a quest correspondente na lista vinha com `status=1` (não iniciada). O cliente abria o painel `stat_quest` mas num estado inconsistente — `get_isStarted()` devolvia false e o skip ficava desativado.
**Fix:** Em `trainingQuestsData()`, se o id gerado corresponder ao `active['training_quest_id']`, define `status=2` em vez de `1`.
**Ficheiro:** `server-laravel/app/HeroZero/Character.php` — `trainingQuestsData()`

---

## Regras aprendidas (evitar regressões)

1. **`trainings` (plural) apaga a lista de quests no cliente.** Com treino ativo usa-se sempre `training` (singular). O plural só no boot e no refresh de ofertas — e nunca sozinho: tem de levar `training_quests`.
2. **`iterations` = estrelas já RECLAMADAS**, não estrelas disponíveis. Quem controla a disponibilidade é `used_resources`.
3. **`LOC` não é tradução em falta.** É o `text:"LOC"` por defeito dos labels no layout do cliente — significa que o código que preenche o label nunca correu.
4. **Os troféus tipo 3 dão bónus a `training_count`, nunca a `training_energy`.** A barra de energia é 30/30 fixa.
5. **`refreshTrainingQuests` (plural) cria objetos novos** e quebra referências que painéis abertos tenham. Não reenviar quests enquanto um painel de batalha estiver aberto.
6. **O treino fica `status = 2` até ao `finishTraining`**, mesmo com as 3 estrelas reclamadas. Fechá-lo antes mata o `get_training()` no cliente e nenhum diálogo de conclusão abre (BUG-TR-018).
7. **Um id de item só serve se o jogador já possuir esse item** — o cliente resolve-o com `getItemById()` na sua própria coleção. Ids-marcador não mostram nada (BUG-TR-022).
8. **Ter o item em `items` não é tê-lo no inventário.** O inventário são as slots `bag_itemN_id` da tabela `inventory` (BUG-TR-023).
14. **A quest ativa deve vir com `status=2` em `training_quests`.** Se `training_quest_id != 0`, a quest correspondente na lista tem de ter `status=2` — caso contrário o cliente vê a quest como "por começar" e o skip/reclamar fica inativo (BUG-TR-026).
9. **Constantes do jogo: copiar, não inventar.** Limiares de estrela, fatores de recompensa e afins estão em `constants_json.data` e o cliente lê-os de lá. Valores inventados dessincronizam os dois lados (BUG-TR-017).
10. **`crc32` não serve para sortear com seeds parecidas.** Ids sequenciais e timestamps próximos produzem valores agrupados. Usar `md5` (BUG-TR-019).
11. **O que a oferta promete tem de ser cumprido.** O `rewards_star_3` é mostrado antes de reclamar, portanto qualquer sorteio tem de ser determinístico e persistido — e a seed não pode depender de algo que muda ao começar o treino (BUG-TR-020).
12. **Lista vazia ≠ campo vazio.** O cliente testa `training_pool != ""`; `json_encode([])` dá `'[]'` e passa nesse teste. Estados vazios precisam da string vazia (BUG-TR-024).
13. **A pool nunca pode esvaziar com um treino a decorrer** — é ela que leva o treino ativo dentro de `trainings` (BUG-TR-008, BUG-TR-024).

---

## BUG-TR-026 — `max_training_energy` a 100/100 em vez de 30/30 em contas novas

[Sintoma]
Contas novas continuavam a mostrar 100/100. Confirmado novamente em `2.png`
e `treinos.har`: iniciar um exercício de custo 8 deixava 92/100.

[Causa]
A correção histórica dos defaults de `GameData.php` e de 68 personagens
(ids 25301–25368) não abrangia o caminho real de criação.
`createCharacter.php` chama `Character::createNew()`, cujo INSERT explícito
continuava a gravar 100/100, sobrepondo-se ao DEFAULT 30 do schema.

[Correcção]
Em 2026-10-07, corrigido o INSERT para 30/30. Reparadas as três contas ainda
com máximo 100 usando `repair_training_energy.php --apply`, com backup prévio.
A reparação preserva o défice após regeneração pelo relógio do jogo e rebasa
o timestamp sem contar minutos duas vezes. Não altera energia de missões,
treino ativo ou unidades. A correção histórica que enchia a barra não deve
ser reutilizada como procedimento de reparação.

[Validação]
PHP lint e testes offline passaram. Integração MySQL com rollback verificou
criação, energia de missões, login, estado normal, início de treino/exercício
e reparação sem alterar os restantes campos. Aplicação real corrigiu três
contas; segunda simulação encontrou zero candidatos. Confirmação visual pendente.

[Ficheiros]
- `server-laravel/app/HeroZero/Character.php`
- `scripts/testing/repair_training_energy.php`
- `scripts/testing/test_training_energy.php`

---

## Pendente / Não corrigido ainda

