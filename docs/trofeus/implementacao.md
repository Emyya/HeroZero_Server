# Troféus / Artefactos — Análise e implementação

Análise da mecânica oficial (pesquisa online), do que o cliente JS espera,
do que a BD já tem e do que faltava no servidor.

**ESTADO (2026-08-23): IMPLEMENTADO** — ver secção 7.

---

## 1. Mecânica oficial (Hero Zero real)

| Regra | Detalhe |
|---|---|
| Pool do servidor | Máximo **50 troféus em todo o servidor** |
| Entrada de troféus novos | Ao **ganhar um ATAQUE** entre guildas enquanto o servidor tiver <50 troféus, entra **1 troféu novo de tipo aleatório**, dado à guilda vencedora (se tiver slot livre) |
| Roubo | Quem **ganha** (ataque ou defesa) contra uma guilda que tenha troféus e tendo ele próprio slot livre, **rouba 1 troféu aleatório** ao perdedor |
| Perda | A guilda que perde contra outra com slot livre perde 1 troféu aleatório |
| Slots | 1→5 slots, desbloqueados pela **expansão**: 0% / 24.5% / 50% / 74.8% / 99.4% |
| Libertar (release) | Líder/conselheiro pode libertar um troféu por **40 donuts**, cooldown **7 dias** — devolve-o ao pool (permite gerir penalizações) |
| 4 tipos | Cada um dá bónus + penalização: **Unidade de Motivação** (+treinos / −15% habs), **Energia** (+20 energia / −10%), **Stamina de Duelo & Liga** (+25% / −12%), **Coragem** (+20 coragem, −2 custo duelo, +2 lutas de liga/dia / −7%) |

## 2. O que o cliente JS espera

### Registo de tipos

Classe minificada `gi` = `CGuildArtifact`
(`com.playata.application.data.constants.CGuildArtifact`), alimentada pelas
constantes `guild_artifacts`. Cada registo tem:

```js
{ id, identifier /* asset */, type, value, attribute_reduction }
```

- `type`: **1**=duel_stamina, **2**=quest_energy, **3**=training_session,
  **4**=coin_pvp_reward
- `value`: magnitude do bónus
- `attribute_reduction`: fração 0–1 (tooltip mostra ×100 como %)

⚠️ **No nosso `initGame.json`, `constants.guild_artifacts` está `null`** —
o cliente não consegue construir nomes/tooltips/tipos de nenhum troféu.
É preciso gerar os 50 registos (é a peça de dados que falta, além da lógica).

### Assets

`assets/artifacts/` = **50 webp = 5 cores × 10 formas**
(`blue/green/purple/red/yellow` ×
`bumbbell/burger/cristall/duel/flash/football/gun/hot_rod/hulk/league`).
As cores são quase de certeza os tiers de raridade/valor
(id 1–50 mapeia cor+forma).

### Objeto guilda (`Va`)

- Lê `artifact_ids` do bloco `guild` do accountState (**já flui** —
  `shapeGuild` passa todas as colunas `g.*`) como string JSON de ints
- `get_unlockedArtifactSlots()` compara `totalImprovementPercentage()`
  **com o divisor BASE 175** (não o 180 do display!) contra os
  5 thresholds — detalhe importante para paridade
- `get_activeDuelLeagueArtifactAmount()`: soma os `value` de todos os
  troféus **type==4** + bónus passivo do quarto *trophy_room* do hideout.
  Este montante multiplica as **moedas de duelo e liga** por
  `(1 + amount/100)` (linhas 2567 e 8277 do min.js) — é o único efeito
  mecânico visível no cliente; os outros tipos são cosméticos/status
  no cliente clássico

### UI

Os slots vivem na janela de **estatísticas da guilda** (linha 9347 do min.js):
slot trancado quando `unlockedSlots < index`, tooltip
`dialog/guild_statistics/guild_artifact_locked`; resultados de batalha
mostram o troféu via ícone `icon_big_reward_artifact_<identifier>`.

### Contratos de batalha

As rewards carregam `artifact_id` (int) e `artifact_stolen` (bool) — é assim
que o ecrã de resultado anuncia "roubámos/perdemos troféu X".

### Única ação cliente→servidor

`sendActionRequest("releaseArtifact")`.

## 3. Base de dados local — o que já existe

Tabela `guild` (`server-laravel/docker/initdb/01_schema.sql`, linhas 490–501):

```
artifact_ids               -- JSON array de ints (ex.: "[3,17,42]")
artifacts_won / artifacts_lost
artifacts_owned_max / artifacts_owned_current
ts_last_artifact_released
```

Constantes já presentes em
`server-laravel/data/reference/balancing_constants.json`:

- `guild_artifact_slot{1..5}_unlock_percentage` = 0 / 0.245 / 0.5 / 0.748 / 0.994
- `release_guild_artifact_cost` = 40 · `release_guild_artifact_cooldown` = 604800 s
- `guild_artifact_duel_stamina_cost_step` = 2
- `guild_artifact_duel_stamina_daily_league_fight` = 2

## 4. O que falta no servidor (gaps)

1. **`constants.guild_artifacts` = null** no initGame — sem os 50 registos,
   todo o resto fica mudo no cliente
2. **Sem tabela de posse individual** — só o array JSON por guilda. Não há
   onde guardar *que* troféu concreto circula no servidor, tipo/valor de cada
   um, nem histórico. Duas opções de design:
   - (a) tabela `guild_artifact` (id, guild_id, artifact_id, ts_acquired,
     battle de origem) e derivar `artifact_ids`; ou
   - (b) manter só arrays JSON e tratar o id do troféu como chave suficiente
     (o tipo/valor vive nas constantes)
3. **`getGuildArtifacts`** mapeado para `guildState.php` **sem branch**
   (cai no accountState genérico)
4. **`releaseArtifact`** mapeado para `stateEcho.php` (stub não mutante)
5. **`GuildBattles::simulate*` sem lógica de troféus**: nada emite troféu
   novo em vitória por ataque, nada transfere troféu entre guildas, rewards
   não incluem `artifact_id`/`artifact_stolen`
6. **Sem distribuição inicial** ("dropam-se X troféus pelas teams")

## 5. Desenho nosso vs. oficial — diferenças a decidir

| Ponto | Nosso | Oficial | Sugestão |
|---|---|---|---|
| Entrada de troféus | Drop inicial no arranque do server | Servidor começa vazio; entram por vitórias de ataque até 50 | Fazer ambos: seed inicial + emissão por vitória de ataque |
| Slots | "2 até 5" | 1→5 por expansão (0% já dá 1 slot) | Usar os thresholds das constantes; hoje sonnet46 (80%) teria 4 slots, oxAlpha (10%) 1 |
| Roubo | Igual | Igual | Direto |

Nota final de paridade: o cliente calcula slots com divisor **175**
(`guild_percentage_total_base`) e expansão exibida com **180**
(`guild_percentage_total_real`) — se formos validar slots no lado do
servidor (p.ex. para recusar roubo sem slot), temos de replicar exatamente
isso para não dessincronizar com o que o jogador vê.

## 6. Ordem natural de implementação (quando avançar)

1. Popular `constants.guild_artifacts` no initGame com os 50 registos
   (id → cor+forma, type, value, attribute_reduction)
2. Decidir tabela `guild_artifact` vs. JSON puro
3. Implementar emissão + roubo no `GuildBattles`, com `artifact_id` /
   `artifact_stolen` nas rewards
4. Branch `getGuildArtifacts` em `guildState.php` + ação `releaseArtifact`
   real (custo 40 donuts, cooldown 7 dias, só líder/conselheiro)

---

*Fontes online: fórum oficial us.herozerogame.com ("Team Trophies –
Everything you need to know"), guia do fórum BR, Hero Zero Wiki (Fandom).*

## 7. Implementação (2026-08-23)

### Descoberta chave

As constantes `guild_artifacts` (os 50 registos oficiais) **já existem** no
`assets/data/constants_json.data` que servimos ao cliente (zlib + JSON,
chave `guild_artifacts`) — tal como os textos PT em
`public/assets/i18n/pt_BR/text.data` (`artifact/<identifier>/name`, etc.) e
as imagens em `assets/artifacts/*.webp`. O `null` no initGame é irrelevante:
o cliente lê as constantes do CDN, não do initGame. Registo extraído
(id → asset/type/value/reduction): type 1 = ids 1–10 (gun+cristall, valor 20,
−7%), type 2 = 11–20 e 31–35 (hulk+football+flash, 20, −10%),
type 3 = 21–30 e 36–40 (hot_rod+burger+bumbbell, 5, −15%),
type 4 = 41–50 (duel+league, 25, −12%).

### Modelo de dados adotado

SEM tabela nova: a posse vive no array JSON `guild.artifact_ids` (coluna já
existente); cada id é ÚNICO em todo o servidor — um troféu físico por entrada
do registo (máx. 50 a circular). Pool de emissão = ids do registo que ninguém
possui. Contadores mantidos pelo código:
`artifacts_owned_current` = nº atual; `artifacts_owned_max` = slots
desbloqueados (para o diálogo "atuais/max"); `artifacts_won/lost`
incrementados nas transferências. `Live::shapeGuild()` também recalcula
current/max ao vivo.

### Ficheiros

| Ficheiro | Papel |
|---|---|
| `app/HeroZero/GuildArtifacts.php` | NOVO — classe central: registo (lê o constants_json.data), slots (divisor BASE 175!), posse, pool, grant/takeRandom/syncCounters, `resolveBattleTransfer()` |
| `app/HeroZero/GuildBattles.php` | Gancho no fim de `simulateGuildBattle`: roubo/emissão UMA vez por batalha; escreve `artifact_id`/`artifact_stolen` nas rewards do lado vencedor (e `artifact_id` no lado perdedor quando é roubo) |
| `actions/releaseArtifact.php` | NOVO — valida rank 1/2, sem batalha pendente, cooldown 7 dias, debita 40 donuts do user (atómico), remove o troféu (volta ao pool), devolve accountState |
| `app/HeroZero/Live.php` | shapeGuild: current/max ao vivo |
| `app/HeroZero/Router.php` | `releaseArtifact` → releaseArtifact.php (antes stateEcho) |
| `tools/distribute_trophies.php` | Distribuição inicial: `php tools/distribute_trophies.php [N]` (default 20); respeita slots por guilda; ids sem repetição |
| `public/assets/artifacts` | symlink para `assets/artifacts` (imagens webp ao cliente) |

Nota: o Router tinha `getGuildArtifacts` mapeado mas **o cliente nunca envia
essa ação** (só existe como helper de constantes) — não precisou de branch.

### Erros do contrato (DialogReleaseArtifact/DU)

`errReleaseArtifactNoPermission`, `errReleaseArtifactActiveBattle`,
`errReleaseArtifactActiveCooldown`, `errRemovePremiumCurrencyNotEnough`,
`""` = sucesso. O `getInt` do cliente tem default 0 → rewards SEM
`artifact_id` são inofensivas (nada crasha quando não há evento).

### Testes feitos (todos dentro de transação com rollback)

- Roubo: vencedor com slot livre rouba 1 aleatório; sem slot livre → nada;
- ~~Emissão: vitória por ATAQUE com slot livre e pool<50 entra troféu novo~~ — removida (BUG-000064);
- Batalha real simulada (`simulateGuildBattle` com battle fictícia):
  atacante venceu → `attacker_rewards {"honor":1971,"artifact_id":32,"artifact_stolen":true}`,
  `defender_rewards {"honor":-118,"artifact_id":32}`, contadores certos;
- `releaseArtifact`: cooldown/permissão/custo/sucesso verificados via ação real;
- Distribuição executada na BD real: sonnet46 5/5 `[41,4,48,6,43]`,
  oxAlpha 1/1 `[32]` — 6/50 a circular;
- `/assets/artifacts/*.webp` e `/assets/data/constants_json.data` a servir 200 OK.

### Como experimentar no jogo

1. Reabrir o jogo → estatísticas da team → linha dos artefactos mostra
   "atuais/max" e os slots (trancados abaixo da expansão necessária);
2. Batalha guilda-vs-guilda: quem ganha com slot livre rouba um troféu ao
   perdedor (se este tiver algum) — o diálogo de resultado mostra o ícone
   do troféu roubado/perdido; se o perdedor não tiver troféus, nada acontece;
3. Libertar: líder/conselheiro, 40 donuts, cooldown de 7 dias por guilda;
4. Mais troféus no arranque: `php tools/distribute_trophies.php <N>`.

## 8. Efeitos na gameplay (2026-08-23)

Os bónus/penalidades oficiais de cada tipo estão aplicados no SERVIDOR
(não é só cosmética). API central em `GuildArtifacts::effects($guildId)`
(`{n1,n2,n3,n4,reduction}`, cache por request com invalidação em
grant/takeRandom/releaseArtifact) + helpers `effectiveMax()`,
`duelStaminaCost()` e `pvpRewardMultiplier()`.

| Tipo | Bónus por unidade | Onde aplica |
|---|---|---|
| 1 Coragem | +20 coragem máx., −2 custo por duelo (piso 10), +2 lutas de liga/dia* | `syncDuelStamina`, overlay, `startDuel`, `leagueMissed`* |
| 2 Energia | +20 energia máxima | `overlayCharacter`, refills do `energyTraining` |
| 3 Motivação | +5 unidades de treino | `liveTrainingEnergy`, overlay |
| 4 Duelo & Liga | +25% moedas de duelo e liga (vencedor) | `startDuel` ($duelMult), `leagueMissed` (coins do atacante; pontos intocados) |
| todos | −attribute_reduction somado nos skills EM LUTAS ENTRE TEAMS | `GuildBattles::simulateGuildBattle` (fator 1−Σ, cada lado o seu) |

\* +2 lutas de liga/dia ainda não tem limite diário no nosso servidor
(ninguém limita league_fight_count), logo não há onde aplicar.

Detalhes:

- Os `max_*` e o `duel_stamina_cost` são lidos PELO CLIENTE direto do payload
  → `overlayCharacter` emite-os JÁ com bónus (ex.: 2×Energia →
  `max_quest_energy` 100→140). A coluna da BD continua a guardar a BASE.
- Regeneração de coragem enche até ao teto efetivo (pode ficar acima da base);
  refills premium (`buyDuelStamina`/`buyQuestEnergy`/`useEnergyStorage`)
  também respeitam o teto efetivo.
- Penalização de skills: só em batalhas guilda-vs-guilda (é "team fights"
  oficial). Duelo/liga 1v1 e dungeons vs NPC ficam sem penalização — decisão
  nossa, conservadora para os jogadores.
- Testes: `test_effects.php` (27 checks, rollback) cobre efeitos agregados,
  overlay, regeneração, refill premium e fator por lado na batalha;
  `test_trophies.php`/`test_battle_trophies.php` continuam verdes.

---

## 9. Referência técnica exaustiva (registo para DEBUG)

Inventário completo de tudo o que foi criado/modificado. Âncoras por NOME de
função/método (números de linha mudam; nomes não).

### 9.1 Ficheiros CRIADOS

#### `server-laravel/app/HeroZero/GuildArtifacts.php` (NOVO — classe central)

Classe `final class GuildArtifacts`, namespace `HeroZero`. Toda a lógica de
troféus passa por aqui — se algo de troféus rebenta, começar o debug aqui.

**Constantes:**

| Constante | Valor | Significado |
|---|---|---|
| `SLOT_THRESHOLDS` (private) | `[0.0, 0.245, 0.5, 0.748, 0.994]` | % de expansão para desbloquear slots 1..5 |
| `TOTAL_BASE_DIVISOR` (private) | `175` | divisor BASE da expansão para SLOTS (**não** o 180 do display!) |
| `RELEASE_COST` | `40` | donuts para libertar 1 troféu |
| `RELEASE_COOLDOWN` | `604800` | cooldown de release em segundos (7 dias) |
| `MAX_SERVER_ARTIFACTS` | `50` | troféus a circular em todo o servidor |
| `BONUS_PER_TROPHY` (private) | type 1: `duel_stamina=20, league_stamina=2`; type 2: `quest_energy=20`; type 3: `training_energy=5`; type 4: `pvp_reward_percent=25` | efeito mecânico por unidade |
| `DUEL_STAMINA_COST_BASE` | `20` | custo base de coragem por duelo (`duel_stamina_cost` oficial) |
| `DUEL_STAMINA_COST_MIN` | `10` | piso do custo com troféus type 1 |
| `$effectsCache` (private static array) | — | cache de `effects()` por guild_id |

**Métodos (todos static):**

| Método | Assinatura | Faz |
|---|---|---|
| `registry()` | `(): array<int, {identifier,type,value,attribute_reduction}>` | Lê os 50 registos de `public/assets/data/constants_json.data` (zlib → **`gzuncompress`**, chave JSON `guild_artifacts`). Cache estática. Fallback de caminho para a raiz do repo. Devolve `[]` se o ficheiro faltar/corrompido |
| `effects()` | `(?\int $guildId): {n1,n2,n3,n4:int, reduction:float}` | Agrega os troféus possuídos: `n1..n4` = contagem por tipo; `reduction` = SOMA dos `attribute_reduction` individuais (ex.: 1×type1 + 1×type4 = 0.07+0.12 = 0.19). Zeroes se guilda inexistente/sem guilda. Cache por request |
| `forgetEffects()` | `(\int $guildId): void` | Invalida o cache (chamar sempre que `artifact_ids` muda FORA de grant/takeRandom) |
| `effectiveMax()` | `(string $resource, \int $baseMax, ?\int $guildId): \int` | `$baseMax` (coluna BD) + bónus do tipo correspondente. Recursos válidos: `quest_energy`, `training_energy`, `duel_stamina`, `league_stamina` |
| `duelStaminaCost()` | `(?\int $guildId): \int` | `max(10, 20 − 2×n1)` — custo de coragem efetivo por duelo |
| `pvpRewardMultiplier()` | `(?\int $guildId): float` | `1 + 25×n4/100` — multiplicador de MOEDAS de duelo/liga |
| `unlockedSlots()` | `(array $guild): \int` | Slots 1..5: soma das 4 upgrades (`stat_guild_capacity + stat_character_base_stats_boost + stat_quest_xp_reward_boost + stat_quest_game_currency_reward_boost`) ÷ **175** ×100 vs thresholds |
| `ownedIds()` | `(array $guild): list<\int>` | Parse tolerante do JSON `artifact_ids`; únicos, >0 |
| `circulatingCount()` | `(): \int` | Soma dos troféus possuídos em TODAS as guildas (full scan `SELECT artifact_ids FROM guild`) |
| `availableIds()` | `(): list<\int>` | Ids do registo que NINGUÉM possui (pool de emissão). Também full scan |
| `grant()` | `(\int $guildId, \int $artifactId): void` | Adiciona id (sem duplicar) + `forgetEffects` implícito + `syncCounters` |
| `takeRandom()` | `(\int $guildId): ?\int` | Remove 1 troféu ALEATÓRIO (`array_rand`); devolve o id ou null. Invalida cache + syncCounters |
| `syncCounters()` | `(\int $guildId): void` | `artifacts_owned_current` = count real; `artifacts_owned_max` = slots desbloqueados |
| `resolveBattleTransfer()` | `(\int $winnerGuildId, \int $loserGuildId, bool $attackerWon): ?{artifact_id:\int, stolen:bool}` | ÚNICA ocorrência por batalha. Prioridade: 1º ROUBO (perdedor tem troféus E vencedor tem slot livre → takeRandom+grant, `artifacts_lost++`/`artifacts_won++`); 2º EMISSÃO (só vitória por ATAQUE + slot livre + circulando<50 → id aleatório do pool, `artifacts_won++`). null = nada aconteceu |

#### `server-laravel/actions/releaseArtifact.php` (NOVO — action `releaseArtifact`)

Entrada `{user_id, id: artifact_id}`. Resposta: `Live::accountState($userId)`
ou GameError. **Ordem de validação** (a ordem importa para o debug):

1. rank ∈ {1=líder, 2=conselheiro} senão `errReleaseArtifactNoPermission`
2. `pending_guild_battle_attack_id`/`_defense_id` = 0 senão `errReleaseArtifactActiveBattle`
3. `ts_last_artifact_released` + 604800 ≤ agora senão `errReleaseArtifactActiveCooldown`
4. id ∈ ownedIds senão `errRequestInvalidParameter`
5. débito atómico de 40 donuts no `user` (`UPDATE ... WHERE premium_currency >= ?`) senão `errRemovePremiumCurrencyNotEnough`
6. remove do JSON, `ts_last_artifact_released = agora`, **`GuildArtifacts::forgetEffects($guildId)`**, `syncCounters`

#### `tools/distribute_trophies.php` (NOVO — CLI)

`php tools/distribute_trophies.php [N]` (default 20). Bootstrap Laravel
(vendor/autoload + kernel). Limita N à capacidade livre total e a 50; sorteia
guildas ponderado pelos slots livres; ids SEM repetição via `availableIds()`;
chama `grant()` (logo atualiza contadores). Idempotente: slots cheios → "Nada
a fazer".

#### `docs/trofeus/implementacao.md` (NOVO — este documento)

#### `public/assets/artifacts` (symlink NOVO)

Aponta para a pasta real dos 50 `.webp` (mesmo padrão do já existente
`public/assets/data`). Sem ele as imagens davam 404 (o servidor serve
`public/`).

### 9.2 Ficheiros MODIFICADOS

#### `app/HeroZero/GuildBattles.php` — função `simulateGuildBattle()`

Três blocos dentro desta função (e só ela):

1. **Penalização de skills** (topo, antes do carregamento dos rosters):
   closure `$skillFactor = fn(guildId) => max(0, 1 − effects(guildId)['reduction'])`.
   `$loadSide` passou de `(array $ids)` para **`(array $ids, float $factor)`**;
   depois do `battleProfile+battleAppearance` escala os 4 stats
   (`stamina, strength, criticalrating, dodgerating`) com `(int)round(stat × factor)`,
   e em seguida recalcula **`hitpoints = stamina ESCALADA × Fight::HP_SCALE`**
   (a barra do cliente é `Q.getHitPoints(stamina_do_perfil)` = `battle_hp_scale`
   (10) × stamina; o hitpoints sai do `battleProfile()` com a stamina BRUTA —
   sem este reescalonamento o servidor deixava o lado penalizado viver ALEM do
   zero visível: bug "lutou com 0 hp" da batalha #15, sonnet46 vs oxAlpha).
   `_stat` (ordenção dos rosters) é calculado DEPOIS da escala, logo a ordem
   de luta reflete os stats penalizados. Chamadas: atacantes com fator de
   `guild_attacker_id`, defensores com fator de `guild_defender_id`.
   ⚠️ Os perfis persistidos (`attacker_/defender_character_profiles`) são os
   JÁ escalados — é isso mesmo, para o cliente mostrar o que lutou.
   **Sem carryover de HP entre lutas 1v1**: cada confronto começa com os DOIS
   lados à vida cheia; no fim só se marcam os derrotados (`hitpoints=0`), os
   sobreviventes ficam com o pool cheio da própria stamina. Motivo: o cliente
   reinicia as barras ao total a CADA matchup (`_barB.init(get_totalHitPoints())`)
   e reproduz todos os rounds somando o dano gravado — HP carregado de uma
   luta anterior não tem representação visual e fazia a barra passar do zero
   (mesma batalha #15: Debug entrou na 2ª luta com 6396/153000 no servidor,
   mas a barra do cliente já estava a 0 desde ~137700).
2. **Gancho de troféus** (após atualização da honra, antes do UPDATE final):
   `$artifactEvent = GuildArtifacts::resolveBattleTransfer(vencedor, perdedor, $winner===1)`.
   Se houver evento: lado VENCEDOR recebe `artifact_id` + `artifact_stolen`;
   lado PERDEDOR recebe `artifact_id` apenas quando é roubo. Refs PHP
   (`&$winnerSide/&$loserSide`) trocadas quando `$winner===2`.
3. O UPDATE final grava `attacker_rewards`/`defender_rewards` como JSON
   `{"honor":X[,"artifact_id":N[,"artifact_stolen":bool]]}`.

⚠️ `simulateDungeonBattle()` (dungeons vs NPC) NÃO foi tocado — sem roubo,
sem emissão, sem penalização (decisão desenhada, ver §8).

#### `app/HeroZero/Live.php` — função `shapeGuild()`

Duas linhas no fim: recalcula ao vivo
`artifacts_owned_current = count(ownedIds(...))` e
`artifacts_owned_max = unlockedSlots(...)`. Garante que QUALQUER resposta que
leve a guilda mostra contadores corretos, mesmo sem passar por grant/sync.

#### `app/HeroZero/Character.php` — 3 funções modificadas

| Função | Mudança |
|---|---|
| `syncDuelStamina()` | `$max` deixou de ser a coluna `max_duel_stamina`: agora `GuildArtifacts::effectiveMax('duel_stamina', coluna, row['guild_id'])`. Consequências: regeneração enche até ao teto EFETIVO (pode ficar acima da coluna base) e NUNCA clampeia para baixo valores já acima da base. Mesmo ciclo de regen (180s/pt; 30s abaixo do custo) |
| `liveTrainingEnergy()` | idem com `'training_energy'` (+5×n3). Usada por `startTrainingQuest` (validação+gasto) e pelo overlay |
| `overlayCharacter()` | Bloco novo "1b)" após o loop de overlay das colunas: para as chaves PRESENTES no template — `max_quest_energy`(+20×n2), `max_training_energy`(+5×n3), `max_duel_stamina`(+20×n1), `max_league_stamina`(+2×n1) — aplica `effectiveMax` sobre o valor vindo da linha; e `duel_stamina_cost = duelStaminaCost(gid)` se a chave existir. É disto que o CLIENTE lê os máximos/custo — sem este bloco o jogador nunca via os bónus |

Nota: `buyTrainingEnergy()` (Character) mexe em `max_training_count` (sessões
de treino) — recurso DIFERENTE de `training_energy`; não foi alterado
de propósito.

#### `app/HeroZero/Router.php` — tabela de rotas

`'releaseArtifact' => 'releaseArtifact.php'` (antes caía em `stateEcho.php`,
stub não-mutante). `getGuildArtifacts => guildState.php` ficou como estava:
o cliente NUNCA envia essa ação (helper interno), confirmado por captura.

#### `actions/startDuel.php` — 3 pontos

1. Validação do custo (antes de aceitar o duelo): `$staminaCostFull =
   GuildArtifacts::duelStaminaCost(char.guild_id)`; `useStamina = stamina >= $staminaCostFull`.
2. Multiplicador do VENCEDOR: `$duelMult = (1 + booster_duelo_guilda/100) ×
   pvpRewardMultiplier(winnerChar.guild_id)` — entra nas moedas+honra via
   `DuelRewards::forDuel(...)` (comentário antigo "artefacto não existe → 1.0"
   removido, já existe).
3. Gasto pós-batalha: `$staminaCost = $useStamina ? $staminaCostFull : 0`;
   `$wasFull` compara contra o máximo EFETIVO (`effectiveMax`) e não contra a
   coluna — sem isto, char com coragem acima de 100 nunca arrancava o relógio
   de regeneração após gastar.

Fluxo premium alternativo (pagar 2 donuts sem stamina) não mudou.

#### `actions/energyTraining.php` — 3 casos do switch

Antes do switch: calcula `$gid`, `$maxQuestEnergy`, `$maxDuelStamina` via
`effectiveMax` sobre as colunas do próprio char. Depois:

- `buyQuestEnergy`: `LEAST(max_quest_energy, …+50)` → **`LEAST(?, …+50)`**
  com valor ligado (coluna BD = base, ficava abaixo do teto com troféus);
- `buyDuelStamina`: `duel_stamina = max_duel_stamina` → **`duel_stamina = ?`**
  (efetivo);
- `useEnergyStorage`: idem ao buyQuestEnergy.

Os restantes casos (storages de treino, boosters, quests) não mexem nestes
recursos — intactos.

#### `actions/leagueMissed.php` — caso `startLeagueFight`

Recompensas deixaram de ser JSON literal fixo: `$aRewards = ['coins' =>
round(10 × pvpRewardMultiplier(guild do atacante)), 'league_points' => 15]`,
`$bRewards = ['coins'=>2,'league_points'=>-5]` (variável reutilizada no
UPDATE imediato do defensor — removido o segundo decode duplicado). Pontos de
liga NÃO multiplicam (leitura conservadora de "+25% reward"). O vencedor de
liga é sempre o lado 'a' (iniciador), pré-existente.

### 9.3 Alterações à BASE DE DADOS

**NENHUMA migração, NENHUMA tabela nova, NENHUMA coluna nova.** Tudo usa
colunas que JÁ existiam no schema (`docker/initdb/01_schema.sql`, tabela
`guild`):

| Coluna | Tipo | Quem escreve |
|---|---|---|
| `guild.artifact_ids` | TEXT com array JSON de ints, ex. `[41,4,48,6,43]` | `grant()`, `takeRandom()`, `releaseArtifact.php` |
| `guild.artifacts_owned_current` | INT | `syncCounters()` (e recalculado ao vivo em `Live::shapeGuild`) |
| `guild.artifacts_owned_max` | INT (=slots) | idem |
| `guild.artifacts_won` / `artifacts_lost` | INT | `resolveBattleTransfer()` (+1 por transferência) |
| `guild.ts_last_artifact_released` | BIGINT ts | `releaseArtifact.php` |

Invariantes:

- Cada `id` do registo existe UMA vez em todo o servidor (máx. 50 a circular).
  Não há constraint na BD — quem violar isto cria troféus duplicados.
- As colunas `character.max_quest_energy / max_training_energy /
  max_duel_stamina / max_league_stamina` CONTINUAM com a BASE (ex.: 100/100/100/10
  nas contas locais; oficial 100/30/100/20). Os bónus aplicam-se APENAS em
  runtime (overlay/regen/refills). Debug tipico: "BD diz 100 mas cliente vê
  140" = correto (2×type2).
- `character.duel_stamina` PODE ficar acima de `max_duel_stamina` (até ao
  efetivo). Não é corrupção.

### 9.4 Fontes de constantes (valores todos de onde vêm)

- Registo dos 50 troféus (id→tipo/valor/redução): `public/assets/data/
  constants_json.data`, chave `guild_artifacts` — MESMO ficheiro que o cliente
  carrega (fonte única). zlib header `78 9c` ⇒ decodificar com **`gzuncompress`**
  (NÃO `gzdecode`). Mapeamento: type 1 = ids 1–10 (gun+cristall, valor 20,
  −0.07); type 2 = 11–20 e 31–35 (hulk+football+flash, 20, −0.10); type 3 =
  21–30 e 36–40 (hot_rod+bumbbell+burger, 5, −0.15); type 4 = 41–50
  (duel+league, 25, −0.12).
- Thresholds de slots/custos: `data/reference/balancing_constants.json`
  (`guild_artifact_slot{1..5}_unlock_percentage`, `release_guild_artifact_cost=40`,
  `release_guild_artifact_cooldown=604800`, `guild_artifact_duel_stamina_cost_step=2`,
  `guild_artifact_duel_stamina_daily_league_fight=2`).
- Divisor de slots = **175** (`guild_percentage_total_base`, porque o cliente
  chama `totalImprovementPercentage()` sem argumentos). O **180** é só para a
  PERCENTAGEM exibida. Misturar os dois = slots dessincronizados com o UI.

### 9.5 Fluxos ponto-a-ponto (para seguir no debugger)

**Batalha guilda-vs-guilda:**
`Router` → `checkForGuildBattleStart`/due → `GuildBattles::simulateGuildBattle(id)`:
carrega battle → `$skillFactor` por lado → `loadSide(ids, factor)` escala stats →
loop de lutas (`Fight`) → honra das guildas → `memberReward` (coins em
`guild_battle_rewards`) → `resolveBattleTransfer` (roubo→null) → monta
`attacker_rewards`/`defender_rewards` (honor ± artifact_id/stolen) → UPDATE
final (status 3) → zera `pending_guild_battle_*`. Cliente reclama via
`finished_guild_battle_attack/_defense_id == battle.id`.

**Release:** `sendActionRequest("releaseArtifact", {id})` → Router →
`actions/releaseArtifact.php` (ordem de validação §9.1) → accountState.

**Overlay (qualquer poll/login):** `Live::accountState` → `overlayCharacter`
→ bloco 1b aplica bónus aos `max_*`/`duel_stamina_cost`; `shapeGuild`
recalcula `artifacts_owned_current/max`.

**Refill premium:** `energyTraining` (casos buyQuestEnergy/buyDuelStamina/
useEnergyStorage) com tetos efetivos calculados antes do switch.

**Duelo:** `startDuel` valida custo efetivo → luta → `$duelMult` (booster ×
troféus type 4 do VENCEDOR) → `DuelRewards::forDuel` → gasto de stamina +
`wasFull` efetivo. Liga: `leagueMissed::startLeagueFight` multiplica só as
moedas do atacante.

### 9.6 Cache e invalidações (armadilha clássica)

`GuildArtifacts::$effectsCache` vive durante o REQUEST. Invalidada em:
`grant()`, `takeRandom()`, `forgetEffects()` (chamado por `releaseArtifact.php`).
Se alguém escrever `artifact_ids` com UPDATE direto e depois chamar `effects()`
no MESMO request, recebe valores velhos — chamar `forgetEffects($gid)` nesse
ponto. Em testes CLI, o cache também persiste dentro do processo: fazer os
UPDATEs de cenário ANTES da primeira chamada a `effects()`.

### 9.7 Contratos de erro (cliente DialogReleaseArtifact/DU)

`errReleaseArtifactNoPermission`, `errReleaseArtifactActiveBattle`,
`errReleaseArtifactActiveCooldown`, `errRemovePremiumCurrencyNotEnough`;
string vazia = sucesso. Nas rewards de batalha o `getInt` do cliente tem
default 0 ⇒ rewards SEM `artifact_id` são inofensivas.

### 9.8 Suite de testes (padrão: transação + ROLLBACK, estado real intacto)

Scripts permanentes em **`scripts/testing/trofeus/`** (README próprio lá
dentro com cenários e saídas esperadas):

| Script | Cobre |
|---|---|
| `test_trophies.php` | Roubo (com/sem slot livre), emissão (ataque, pool<50), null sem slot, releaseArtifact (cooldown/permissão/débito/sucesso) |
| `test_battle_trophies.php` | Batalha real fictícia: rewards JSON com `artifact_id`/`artifact_stolen` nos DOIS lados, contadores won/lost, roubo pelo lado DEFENSOR incluído |
| `test_effects.php` | 27 checks: effects agregados, custo/multiplicador, máximos efetivos, overlay (char guilda 1 e 2), regen até teto efetivo (115 mantido; 99→120), refill premium até efetivo, escala dos 4 stats por lado na batalha (fatores 0.56/0.90) |

Todos fazem bootstrap Laravel + `require_once Protocol.php` (GameError vive
em `Protocol.php`, não tem autoloader próprio — sem isto CLI rebenta com
"class not found") e `beginTransaction/rollBack` no `finally`. Correr da raiz:
`php scripts/testing/trofeus/<script>.php`.

### 9.9 Gotchas resumidos (os que já morderam)

1. Divisor 175 (slots) vs 180 (% exibida) — misturar dessincroniza com o UI.
2. `constants_json.data` é zlib → `gzuncompress`, não `gzdecode`.
3. Troféus só circulam entre guildas — a emissão automática foi removida
   (BUG-000064). Se não há troféu para roubar, não acontece nada.
4. Penalização de skills só em guild-vs-guild; duelo/liga 1v1 e dungeons limpos.
5. `training_count`/`max_training_count` (sessões) ≠ `training_energy`/
   `max_training_energy` (energia 1/min) — o troféu type 3 incide na ENERGIA.
6. Perfis persistidos da batalha JÁ vêm escalados pela penalização — não
   "descalibrar" comparando com `battleProfile` cru. E o `hitpoints` gravado
   tem de ser recalculado da stamina ESCALADA (×10): o que sai do
   `battleProfile()` vem da bruta — esquecer isto = "lutou com 0 hp" (batalha
   #15). Invariante: nos perfis gravados, `hitpoints` ∈ {0, stamina×10}.
6b. Sem carryover de HP entre lutas 1v1 de guilda: o cliente reinicia as
    barras a cada matchup e repete TODOS os rounds — dano carregado não tem
    onde aparecer e a barra repassa pelo zero. Cada confronto começa cheio.
7. Cache de efeitos por request: invalidar após UPDATE direto a `artifact_ids`.
8. `GameError` vive em `Protocol.php` (CLI precisa de `require_once`).
9. Sem o symlink `public/assets/artifacts`, imagens 404.
10. `duel_stamina_cost`/`league_stamina_cost` NÃO são colunas — são campos do
    template; o custo de duelo emitido sai do bloco 1b do overlay.
