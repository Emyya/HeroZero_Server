# Ligas — Funcionamento e Implementação

Documento de referência da funcionalidade **Ligas** no servidor Laravel (cliente html5_257).
O plano original está em [`implementar_leagues.md`](implementar_leagues.md) (mesma pasta);
este documento descreve **como a liga funciona depois de implementada** e **todas as
modificações feitas no código**, ficheiro a ficheiro.

Estado: implementado e validado com smoke tests HTTP assinados a 2026-08-24.
Falta apenas teste em jogo pelo cliente.

---

## 1. Como funciona

### 1.1 Modelo de dados

Tudo vive em colunas já existentes da tabela `character` + uma tabela nova global:

| Coluna / Tabela | Função |
|---|---|
| `character.league_group_id` | Codifica divisão **e** grupo: `divisão = floor(group / 100000)` (`Dd.getLeagueDivisionId` do cliente). Divisão 1 (Bronze I) = grupo `100001`; **0 = nunca entrou**. O cliente pede sempre `div×100000+1`; o valor `div×100000` nunca é pedido (ver BUG-000095). |
| `character.league_points` | Pontos ELO da época atual |
| `character.league_opponents` | Lista persistida dos 3 alvos oferecidos (ids separados por vírgula) |
| `character.ts_last_league_opponents_refresh` | Última vez que o botão manual de refresh foi usado (regra grátis 1×/dia) |
| `character.league_stamina` / `max_league_stamina` / `league_stamina_cost` | Barra própria da liga (custo fixo 20) |
| `character.active_league_booster_id` / `ts_active_league_boost_expires` | Energético de liga ativo (aumenta a barra para 60/120) |
| `character.ts_last_league_stamina_change` | Relógio da regeneração lazy |
| `character.active_league_fight_id` | Luta em aberto do atacante (status 1/2) |
| `character.ts_last_league_fight` / `league_fight_count` | Controlo do limite diário |
| `league_season` (**nova**) | UMA linha (`id = 1`) com `ts_end` da época vigente |

A tabela `league_fight` espelha a `duel`: `battle_id`, `character_a_id` (atacante),
`character_b_id` (defensor), status 1=nova → 2=vista → 3=reivindicada/fechada,
`*_rewards` em JSON. As recompensas do **defensor são creditadas no momento do ataque**
(não quando ele reclama) — igual ao servidor oficial.

### 1.2 Entrada na liga (`enterLeagueDivision`)

- Portão de honra pela constante oficial `league_min_honor_points` (500 no asset),
  erro `errEnterLeagueDivisionMinHonorNotReached`.
- Semeadura: `league_group_id = 1 × 100000 + 1`, `league_points = 0` (decisão tomada:
  o servidor oficial começa a 0; o seed antigo de 100 fazia o cliente considerar a
  divisão já vencida). O `+1` é obrigatório — o cliente pede sempre grupos `div*100000+1`,
  nunca `div*100000` (que funcionava como grupo zero invisível). Ver BUG-000095.
- Gera logo os 3 oponentes e devolve-os na resposta.

### 1.3 Oponentes

- `Live::leagueOpponents()` sorteia 3 alvos entre **contas reais inscritas na liga**:
  - Cada oponente é sorteado individualmente: **90% da mesma divisão**,
    **10% de uma divisão abaixo**.
  - Se a divisão preferida não tiver jogadores disponíveis, tenta a outra.
  - Na divisão 1 (Bronze I) só sorteia da mesma (não há abaixo).
  - Nunca apanha jogadores não inscritos (`league_group_id = 0`).
- A lista é **persistida** em `character.league_opponents` no formato **JSON ARRAY**
  (`"[25295,25294,25297]"`, com colchetes) — o cliente faz `JSON.parse` dessa coluna
  (`get_leagueOpponentIds` → `getFromJson(...)`); uma string sem colchetes devolve
  lista vazia. Abrir o painel serve sempre a mesma lista até haver refresh/nova luta.
  O parser do servidor (`Live::savedLeagueOpponentIds`) usa `json_decode` e aceita,
  por segurança, o formato legado sem colchetes.
- Refresh manual (`refreshLeagueOpponents`): **grátis uma vez por dia** (comparação de
  data no relógio de jogo), depois exige `use_premium` e debita
  `league_change_opponents_premium_currency_amount` (2 donuts). Sem premium no mesmo
  dia → `errRefreshLeagueOpponentsNotYetAllowed`.
- Rerolls automáticos (entrada na liga, nova luta com `refresh_opponents=true`) **não**
  tocam no timestamp do refresh manual.

### 1.4 A luta (`startLeagueFight`) — ordem das verificações

1. `League::ensureSeason()` (rolamento lazy da época).
2. **Caminho de retoma**: se `active_league_fight_id` aponta para luta com status 1/2,
   volta a servir essa luta (reload/crash não prende o jogador). Status 3 ou linha
   desaparecida limpa o ponteiro.
3. `errLeagueNotJoined` se `league_group_id = 0`.
4. `errStartLeagueFightMinHonorNotReached` se honra < constante.
5. Sincroniza stamina e contador diário (lazy) **antes** de validar custos.
6. Limite diário: `errStartLeagueFightDailyLimitReached` *(código defensivo — não existe
   no bundle; o cliente desativa o botão com `countLeftToday == 0`)*.
7. Pagamento: barra ≥ 20 gasta stamina; sem stamina e `use_premium` debita 4 donuts;
   caso contrário `errRemoveDuelStaminaNotEnough` / `errRemovePremiumCurrencyNotEnough`.
8. O alvo tem de estar na lista oferecida (`savedLeagueOpponentIds`), senão
   `errStartLeagueFightInvalidLeagueOpponent`.
9. Perfis reais (`battleProfile('a')`) dos dois lados **com o bónus de atributos da
   própria divisão** aplicado.
10. Combate simulado real (`HeroZero\Fight`); rounds + apareências incluídos (sem eles
    o painel de batalha crasha).
11. `INSERT INTO battle` → captura imediata de `lastInsertId` (BUG-000037: qualquer
    UPDATE pelo meio reseta-o para 0).
12. Recompensas calculadas (ver 1.5), `INSERT INTO league_fight` → `lastInsertId` já.
13. Defensor creditado imediatamente (moedas + pontos, piso em 0).
14. Atacante: débito de stamina (se for o caso), `ts_last_league_fight`,
    `league_fight_count + 1`, ponteiro `active_league_fight_id` persistido.
15. `refresh_opponents=true` → novos 3 alvos.
16. Resposta: `accountState` + `league_fight`/`battle` (linhas cruas — ver nota abaixo)
    + `opponent(+opponent_inventory/opponent_inventory_items)` + `unset(battles)`
    + lista nova de oponentes.

> Nota: a resposta usa as **linhas cruas** da BD para `league_fight`/`battle`. O template
> do boot não tem chave `league_fight`, e `shapeLike` contra template vazio devolveria
> `[]`.

### 1.5 Recompensas (porte exato do cliente, `Dd.leagueFight*`)

Onde o plano divergia do bundle, ganhou o bundle.

**Vitória** (`League::eloWin`):

```
exp = 1 / (1 + 10^((pontosAdversário − meusPontos) / 1200))
pts = jsRound(11 × (1 − exp))
se pts > 0: pts += 5 + league_elo_calculation_bonus (=5)   // bónus positivo total +10
final = jsRound(pts × mult1 × mult2)
```

`mult1 = 1 + boosterDueloGuildaDoVENCEDOR%/100`; `mult2 = GuildArtifacts::pvpRewardMultiplier`
(troféus type 4 do vencedor). **Os pontos de vitória levam multiplicadores** (é o que o
tooltip promete) — ao contrário do que dizia o plano.

**Derrota** (`League::eloLoss`): mesma fórmula com os pontos trocados, resultado negativo
subtrai `5 + bonus`; **sem** multiplicadores.

**Moedas** (`League::coinsWin`): `DuelRewards::coinsWin(nívelDoVENCIDO) × mult1 × mult2
× (1 + game_currency_bonus[divisãoDoVENCEDOR])`. Fator **×2.0 … ×4.4** conforme a divisão
(o cliente multiplica por `1 + valor cru`, não por `1 + valor/100`).

**Empates `.5`**: todas as rondagens usam `League::jsRound` (ver §3.3).

**Mesma team**: se atacante e alvo são membros da MESMA guilda, nenhum dos
lados ganha ou perde pontos de liga (`league_points = 0` para ambos); as
moedas mantêm-se. Porte do bundle: `getLeagueFightResultTooltip` só chama os
cálculos ELO quando `!opponent.get_isMyGuildMember()` (texto oficial
"screen/league_fight/tooltip_same_team"). Colegas de guilda continuam a poder
aparecer na lista de oponentes — o sorteio não filtra por guilda, só exclui o
próprio. Implementado em `startLeagueFight` (`$sameTeam`, BUG-000070).

### 1.6 Bónus de atributos por divisão (global)

O `base_attribute_bonus` da divisão (+3%…+39%) é um bónus às **habilidades
básicas** — ao contrário do bónus de moedas (§1.5), que só vale em lutas de liga.
Vale em TODOS os combates: missões, duelos, batalhas de guilda, gangues NPC e,
claro, as próprias lutas de liga.

Implementação: `League::attributeFactor(div)` entra como mais um termo percentual
no `Character::statMultiplier()` (o mesmo fator que soma booster de stats +
instalação da guilda sobre a base efetiva; equipamento continua a somar plano
depois). Assim o bonus chega automaticamente a tudo o que usa `battleProfile()`
e também aos `stat_total_*` exibidos no perfil — subir de divisão vê-se nos
atributos, como no jogo original. Cada lutador usa sempre a SUA divisão.

Histórico: antes (decisão "aplicar já") `League::applyAttributeBonus`
multiplicava as stats FINAIS do perfil (incluindo equipamento) só nas lutas de
liga; o método foi removido — reaplicar sobre um perfil pronto contaria o bonus
duas vezes.

### 1.7 Stamina de liga (`League::syncStamina`)

Clone lazy do `syncDuelStamina` dos duelos:

- Expira o energético fora do prazo (id limpo, máx. volta a 20).
- Máximo **base** pelo energético: sem → 20, `booster_league1` → 60, `booster_league2`
  → 120 (coluna `max_league_stamina` guarda só o base).
- Os **troféus Coragem (type 1)** somam +2 cada por cima, só no overlay/resposta, via
  `GuildArtifacts::effectiveMax('league_stamina', base, guildId)`.
- Regeneração lazy: taxa por minuto `0.333334` (sem) / `0.444445` (league1, 135 s/ponto)
  / `0.666667` (league2, 90 s/ponto); passo = `round(60/taxa)` segundos; loop consome os
  intervalos já decorridos. Barra cheia → `ts_last_league_stamina_change = agora` (o
  relógio só arranca quando se gasta).
- Custo fixo: coluna `league_stamina_cost` = 20 (`league_stamina_cost`). Ao gastar com a
  barra **cheia**, o timestamp é redefinido; parcial, preserva-se o progresso.
- **Corre em toda resposta**: o `Live::accountState()` chama `League::syncStamina`
  (chokepoint, como o `ensureSeason`) e recarrega o personagem se gravou algo. O cliente
  lê o máximo da barra direto de `character.max_league_stamina`
  (`ep.get_maxLeagueStamina`) — sem isto, comprar um energético devolvia o máximo velho
  e a barra só mudava ao começar uma luta (bug §3.6).

### 1.8 Limite diário

`League::maxDailyFights() = league_max_daily_league_fights (20) +
guild_artifact_duel_stamina_daily_league_fight (2) × troféusCoragemDaGuilda`.
Reset **lazy**: `syncDailyCount` compara o dia (no fuso de jogo, Europe/Lisbon) de
`ts_last_league_fight` com hoje e zera `league_fight_count` quando mudou de dia.
Corre em toda resposta (`accountState`) e também no boot (`autoLoginUser`) —
ver §1.14: o cliente não olha datas para este limite e exige receber o
contador já zerado quando o dia muda (BUG-000069).

### 1.9 Mudança de divisão (histerese oficial)

`League::applyDivisionChange(&row, cid)` corre **depois** de creditar os pontos no
`claimLeagueFightRewards`:

- sobe 1 divisão se `points >= max_points[div]` (com `max_points > 0`);
- desce 1 se `demotion >= 0 && points < demotion`;
- devolve `"X_Y"` (X = nova, Y = antiga) quando muda — a resposta ganha a chave
  `league_division_change`, e `X > Y` dispara o diálogo de subida no cliente.

### 1.10 Época (season) com rolamento

- Fim da época: **próxima segunda-feira 00:00 no relógio de jogo** (`seasonEndTimestamp`;
  respeita o offset de debug do GameTime → testável via `/servertime`).
- `League::ensureSeason()` é lazy e corre no `accountState` (choke point de praticamente
  todas as respostas) + nas actions de liga:
  - linha `league_season id=1` não existe → cria (`INSERT IGNORE`) com o próximo fim;
  - expirou → **rolamento**: uma `UPDATE` por divisão põe os pontos de todos dessa gama
    (`BETWEEN div×100000 AND div×100000+99999`) de volta ao `entry_points` da divisão,
    e agenda a semana seguinte.
- Quem nunca entrou (`group 0`) fica intacto. O rolamento ser transparente significa que
  `errLeagueLocked` praticamente nunca dispara, mas o caminho existe no cliente.
- Resposta do boot ganha `league_season_end_timestamp` (raiz) e `league_locked = false`
  quando o template traz a chave (timestamp fixo da captura oficial, data passada).

### 1.11 Ranking da liga (`retrieveLeagueGroupLeaderboard`)

Porte do `retrieveLeagueGroupLeaderboard.req.php` do servidor oficial:

- Janela de **50 entradas** centrada no rank central (`WHERE rank > centerRank - 49 LIMIT 50`),
  ordenação global por `league_points DESC`.
- Centro: `rank` enviado pelo cliente | pesquisa por nome **exato** | primeiro (melhor)
  colocado do meu `league_group_id`.
- Shape própria: linhas cruas com `rank, id, name, guild_id, gender, level, ts_last_action,
  league_points, online_status, league_group_id, honor, guild_name, emblem_*` — o painel
  de liga **não** lê a shape curta do ranking geral.
- Erros: `errRetrieveLeaderboardInvalidRank`, `errRetrieveLeaderboardInvalidCharacter`.

### 1.12 Energéticos de liga (`buyLeagueBooster`)

Preço pelo **catálogo oficial** (`Live::BOOSTER_CATALOG`, fonte `constants_json.data`):

| Energético | Barra | Durações / preços |
|---|---|---|
| `booster_league1` | 60 | 4 dias = **2057 moedas** |
| `booster_league2` | 120 | 7 dias = 10 donuts · 30 dias = 40 · 90 dias = 115 |

Renovar antes de expirar soma tempo ao prazo corrente. O novo máximo da barra é derivado
pelo `syncStamina` na sincronização seguinte (sem `UPDATE` manual do máximo).

### 1.13 Duelos/lutas perdidas da liga

Intocados nesta ronda: `getMissedLeagueFight(s)`, `claimMissedLeagueFightsRewards`,
`deleteMissedLeagueFights`, contadores `missed_league_fights` no accountState.

> Nota (BUG-000073): o detalhe do replay (`missed_league_fight_battle` /
> `missed_duel_battle`) serve a batalha **CRUA** (`profile_a`=atacante,
> `profile_b`=defensor). O cliente inverte os lados ele próprio nos branches
> `get_isMissedLeagueFight()`/`get_isMissedDuel()` do painel de batalha —
> espectador fica à ESQUERDA com os stats dele, atacante à DIREITA, e
> `get_isWon() = winner=='a'` sai correto. Pré-trocar lados no servidor
> duplicava a inversão e corrompia os rounds (sem chave `'d'`).

### 1.14 Relógio de jogo e sincronizações lazy

Tudo o que é temporal na liga corre LAZY sobre o relógio de jogo
(`GameTime::now() = epoch de nascimento + uptime do servidor + offset de debug`,
persistido em `server-laravel/storage/game_time_offset`; adiantável via tinker,
ex.: `HeroZero\GameTime::addHours(24)`) — não há cron:

| Mecanismo | O que faz | Onde corre |
|---|---|---|
| `League::syncStamina` | regeneração/expiração da adrenalina de liga | `accountState` + `startLeagueFight` |
| `League::syncDailyCount` | zera `league_fight_count` quando mudou o dia de `ts_last_league_fight` | `accountState` + boot (`autoLoginUser`) + `startLeagueFight` |
| `League::ensureSeason` | rolamento da época à segunda-feira 00:00 de jogo | `accountState` + actions de liga |
| `$sameGameDay(ts)` | refresh grátis de oponentes 1×/dia | `refreshLeagueOpponents` |

Detalhe que provocou o BUG-000069: o cliente NÃO verifica datas no limite diário —
`get_leagueFightCountLeftToday = Math.max(get_maxDailyLeagueFights() −
get_leagueFightCount(), 0)` e `get_hasDailyLeagueFightLimitReached =
leftToday <= 0` (ao contrário de washItem/shopRefresh, que usam
`serverDateTime.isToday(ts)`). Ou seja, o servidor tem de servir o contador JÁ
ZERADO quando o dia muda; basta ecoar o valor cru da BD para o botão de ataque
nascer desativado ("atingiu o número máximo"). Por isso o `syncDailyCount` corre
também no boot e no `accountState`, ANTES dos overlays (que relêem o personagem
quando o sync gravou em BD).

Avançar o relógio (debug) dispara tudo isto no próximo pedido, sem restart:
contadores diários zeram, a adrenalina regenera pelos intervalos decorridos e,
ao atingir segunda-feira 00:00 do relógio de jogo, a época roda.

---

## 2. Modificações, ficheiro a ficheiro

### 2.1 `server-laravel/app/HeroZero/League.php` — **NOVO**

Classe pura de regras (sem estado além de caches). Fontes: `constants_json.data` do CDN
(a mesma que o cliente usa) + porte do `HeroZero.min.js`.

| Método | O que faz |
|---|---|
| `GROUP_FACTOR` | Const `100000` (codificação divisão+grupo) |
| `officialConstants(): array` | Lê e faz cache de todas as chaves `league_*` + `guild_artifact_duel_stamina_daily_league_fight` do asset zlib; fallback hardcoded com valores oficiais extraídos em 2026-08 |
| `constant(string $key): float` | Acesso individual |
| `jsRound(float $x): int` | `Math.round` do JS (`floor(x + 0.5)`): `-5.5 → -5`, `5.5 → 6`. O `round()` do PHP dava `-6` no negativo |
| `divisions(): array` | Tabela oficial `league_divisions` (13 divisões; `[]` se o asset faltar) |
| `divisionFromGroup(int $group): int` | `intdiv(group, 100000)`; 0 = não inscrito |
| `eloWin(myPts, oppPts, mult1, mult2): int` | Pontos de vitória (fórmula §1.5) |
| `eloLoss(myPts, oppPts): int` | Pontos de derrota (negativo, sem mults) |
| `coinsWin(winnerDivision, loserLevel, mult1, mult2): int` | Moedas do vencedor com bónus de divisão |
| `guildMultipliers(boosterAmountPercent, ?guildId): array` | `[1+p/100, pvpRewardMultiplier]` |
| `attributeFactor(int $division): float` | `1 + base_attribute_bonus`; consumido por `Character::statMultiplier` (§1.6, global) — não reaplicar sobre perfis prontos |
| `applyDivisionChange(array &$row, int $cid): ?string` | Histerese §1.9; grava `league_group_id` novo; devolve `"X_Y"` ou null |
| `expectedMaxStamina(string $boosterId): int` | `'' → 20`, `league1 → 60`, `league2 → 120` (do catálogo) |
| `refreshPerMinute(string $boosterId): float` | Taxa de regeneração por energético |
| `syncStamina(array $row): array` | Sincronização lazy completa §1.7; grava campos alterados e devolve a linha |
| `maxDailyFights(?int $guildId): int` | `20 + 2 × nº troféus type 1` |
| `syncDailyCount(array $row): array` | Reset lazy do contador por dia de jogo |
| `seasonEndTimestamp(): int` | Próxima segunda 00:00 no relógio de jogo |
| `ensureSeason(): int` | Cria/rola a época (§1.10); devolve o `ts_end` vigente |

### 2.2 `server-laravel/app/HeroZero/Live.php`

- **`withLocalConstants()`**: injeta todas as constantes `league_*` oficiais que o
  template trouxer a `null`/ausentes, e garante `constants.league_divisions` com a tabela
  real (os templates trazem-na a `null`; sem ela o cliente fica em "unknown division").
- **`accountState()`** (fim do método): quando o boot tem `league_season_end_timestamp`,
  substitui-o por `League::ensureSeason()` e acrescenta `league_locked = false` — o ponto
  de rolamento lazy da época.
- **`accountState()`** (início do método): corre `League::syncStamina` sobre a linha do
  personagem (adrenalina lazily em toda resposta, §1.7) e recarrega o `Character` quando
  a sincronização gravou — sem isto a barra não refletia energéticos recém-comprados
  (§3.6).
- **`leagueOpponents(int $cid)`**: reescrito — sorteia 3 oponentes inscritos na liga,
  cada um com 90% de chance da mesma divisão e 10% de uma divisão abaixo (§1.3).
  Persiste **só a lista** (o timestamp do refresh manual ficou a cargo exclusivo do
  `refreshLeagueOpponents`). Removido o fallback global que apanhava jogadores fora
  da liga (BUG-000094).
- **`savedLeagueOpponentIds(int $cid)`** — NOVO: parse da lista persistida, filtrando ids
  inexistentes/o próprio/duplicados.
- **`renderLeagueOpponents(array $ids)`** — NOVO: monta a shape do cliente
  `{opponent, opponent_inventory, opponent_inventory_items}` por id, com templates do boot.

### 2.3 `server-laravel/app/HeroZero/Character.php`

- **`createNew()`**: seed da stamina de liga alterado de `10, 10, 20` para `20, 20, 20`
  (`league_stamina`, `max_league_stamina`, `league_stamina_cost`) — o custo oficial de 20
  nunca caberia numa barra inicial de 10.

### 2.4 `server-laravel/actions/leagueMissed.php` — parte da liga **REESCRITA**

Helpers novos no topo do closure:

- `$leagueRow(cid)` — SELECT fresco de todas as colunas usadas pela liga;
- `$sameGameDay(ts)` — compara dia no relógio de jogo (usado no refresh grátis);
- `$spendPremium(char, cost)` — débito de donuts no `user` com erro dedicado.

Casos reescritos (os de duelos/lutas perdidos ficaram intactos):

| Caso | Comportamento novo |
|---|---|
| `getLeagueOpponents` | Serve a lista **persistida**; sorteia só se ainda não existir (antes re-sorteava sempre = refresh grátis infinito) |
| `refreshLeagueOpponents` | Grátis 1×/dia; depois exige `use_premium` + 2 donuts; grava `ts_last_league_opponents_refresh` |
| `startLeagueFight` | Fluxo completo de §1.4: retoma, portas, limite diário, pagamento, validação do alvo, combate real com bónus de divisão, ELO/moedas oficiais, defensor creditado à partida, ponteiro persistido |
| `checkForLeagueFightComplete` | + `ensureSeason()`; resto igual (status 1→2) |
| `claimLeagueFightRewards` | Erro dedicado sem luta pendente; credita; status→3; limpa ponteiro; `applyDivisionChange` sobre linha fresca → `league_division_change` |

Imports: `+ HeroZero\Fight`, `+ HeroZero\League`, mantido `GuildArtifacts` (usado no
`$wasFull` do débito de stamina).

### 2.5 `server-laravel/actions/enterLeagueDivision.php`

- `+ use HeroZero\League`;
- `League::ensureSeason()` antes de tudo;
- portão de honra via `League::constant('league_min_honor_points')` (antes hardcoded 500);
- semeadura `league_group_id = League::GROUP_FACTOR * 1` e **`league_points = 0`**
  (removido o `IF(league_points = 0, 100, ...)`).

### 2.6 `server-laravel/actions/leaderboard.php`

- Imports: `+ HeroZero\GameTime`, `+ HeroZero\League`;
- ramo **dedicado** `retrieveLeagueGroupLeaderboard` inserido antes do fallback genérico
  de templates (que devolvia top-30 genérico com shape errada): validação de rank,
  cálculo do centro (rank/nome/grupo), janela `LIMIT 50`, `LEFT JOIN guild` para
  `guild_name`/emblemas, `online_status` por `GameTime::now()`, resposta via
  `Live::timeKeys` com `character => []`.

### 2.7 `server-laravel/actions/boosterExtra.php`

- Caso `buyLeagueBooster` reescrito: preço por `Live::boosterCost($id, $duration)`
  (catálogo oficial) — moedas para `booster_league1`, donuts para `booster_league2`
  (antes cobrava `ceil(dias)` donuts para qualquer par). Empilha no prazo corrente;
  o máximo novo da barra sai do `syncStamina`.

### 2.8 Base de dados

- **`docker/initdb/01_schema.sql`**: tabela nova
  `league_season (id INT PK, ts_end INT)` — uma linha global (`id = 1`).
- Na BD viva: tabela criada manualmente (mesmo DDL).

### 2.9 Correção pontual de dados (one-time)

Executada na BD viva antes dos testes (personagens tinham stamina antiga de 10 e os
pontos seedados de 100):

```sql
UPDATE `character` SET league_stamina = 20, max_league_stamina = 20
 WHERE league_stamina <> 20 OR max_league_stamina <> 20;

UPDATE `character` SET league_points = 0
 WHERE league_fight_count = 0 AND ts_last_league_fight = 0 AND league_group_id > 0;
```

Segura porque ambos os membros da liga tinham contador e timestamp a zero — os 100
pontos eram puramente o seed antigo.

Depois do bug do botão "Ingressar" (§3.5), migração one-time do formato da coluna
(`"1,2,3"` → `"[1,2,3]"`; idempotente, ignora o que já tem colchetes):

```sql
UPDATE `character`
   SET league_opponents = CONCAT('[', REPLACE(TRIM(BOTH ',' FROM league_opponents), ' ', ''), ']')
 WHERE league_opponents IS NOT NULL AND league_opponents <> ''
   AND league_opponents NOT LIKE '[%';
```

---

## 3. Bugs encontrados durante a validação

1. **Import em falta** — `leagueMissed.php` referenciava `GuildArtifacts::effectiveMax`
   sem o `use` (tinha sido removido no rewrite) → `response_errorIO` genérico. Reproduzido
   chamando a action diretamente com o kernel Laravel bootstrapped.
2. **Underflow UNSIGNED** — `ORDER BY ABS(level - ?)` no fallback de oponentes: níveis
   inferiores ao meu produziam negativo numa coluna UNSIGNED → erro MySQL 1690. Fix:
   `ABS(CAST(level AS SIGNED) - ?)`.
3. **Arredondamento divergente do cliente** — o cliente usa `Math.round` do JS
   (meio ponto arredonda para +∞: `-5.5 → -5`); o `round()` do PHP arredonda para longe
   do zero (`-5.5 → -6`). Derrota entre iguais devia dar **−15**, não −16. Criado
   `League::jsRound()` e usado em `eloWin`/`eloLoss`/`coinsWin`.
4. **Design fix** — rerolls automáticos de oponentes gravavam
   `ts_last_league_opponents_refresh`, queimando o refresh grátis do dia. Passou a ser
   gravado apenas pelo botão manual.
5. **Botão "Ingressar" não fazia nada** (reportado em jogo) — o servidor guardava
   `league_opponents` como string separada por vírgulas (`"25295,25294,25297"`), mas o
   cliente faz `JSON.parse` dessa coluna (`get_leagueOpponentIds`): JSON inválido → lista
   vazia → `get_canDoLeagueFights()` falso **para sempre** → o painel da liga ficava no
   estado "não inscrito" mesmo depois do `enterLeagueDivision` responder bem (o HAR
   `liga_entrar.har` confirmou que o servidor respondia corretamente). Fix: a coluna é
   agora escrita e lida em formato JSON ARRAY (`"[25295,25294,25297]"`,
   `Live::leagueOpponents` / `Live::savedLeagueOpponentIds`, com fallback de leitura para
   o formato legado) + migração one-time das linhas existentes.
6. **Barra de adrenalina não mudava ao comprar energéticos** (reportado em jogo) — o
   `buyLeagueBooster` só empilhava o prazo; o novo máximo (60/120) ficava para a próxima
   sincronização, que só acontecia no `startLeagueFight`. Como o cliente lê
   `character.max_league_stamina` diretamente (`ep.get_maxLeagueStamina`), a resposta da
   compra — e até um relogin — mostravam a barra velha. Fix: `Live::accountState()`
   corre `League::syncStamina` em toda resposta (e recarrega o personagem se gravou);
   testado comprando `booster_league1` com um `league2` ativo → resposta trouxe 60/60
   (substituição de booster + clamp correto) e o pedido seguinte persistiu.
7. **Oponentes de qualquer divisão/não inscritos** (BUG-000094) — o fallback global em
   `leagueOpponents` apanhava qualquer personagem do servidor quando a divisão do jogador
   tinha poucos membros. Fix: sorteio por divisão (90% mesma, 10% abaixo), sem fallback
   global; nunca mostra jogadores com `league_group_id = 0`.

---

## 4. Validação executada (smoke tests HTTP assinados)

Conta de teste: sonnet46 (`user_id 25329`) vs skel (`25296`), ambos Bronze I.

| Teste | Resultado |
|---|---|
| `getLeagueOpponents` (lista fresca) | ✓ 3 alvos reais, lista persistida |
| `startLeagueFight` vs skel | ✓ luta criada (lf 5), sonnet perdeu para o lvl 301; `a_rewards = {coins:0, league_points:-15}`; skel (guilda com booster de duelo +20%) levou `{coins:1808, league_points:19}` — valores conferidos pelos próprios helpers (`eloWin(0,0,1.2,1)=19`, `coinsWin(1,140,1.2,1)=1808`, `eloLoss(0,0)=-15`) |
| Débitos do atacante | ✓ stamina 20→0, `count=1`, `active_league_fight_id` persistido |
| `checkForLeagueFightComplete` | ✓ status 1→2 |
| `claimLeagueFightRewards` | ✓ moedas/pontos creditados (piso 0 aplicado), status→3, ponteiro limpo |
| `retrieveLeagueGroupLeaderboard` | ✓ shape própria, ordenado por pontos, `centered_rank` correto |
| `refreshLeagueOpponents` 1ª do dia | ✓ grátis |
| `refreshLeagueOpponents` 2ª sem premium | ✓ `errRefreshLeagueOpponentsNotYetAllowed` |
| Ataque sem stamina e sem premium | ✓ `errRemoveDuelStaminaNotEnough` |
| Ataque com premium sem stamina | ✓ lf 6 criado, 4 donuts debitados |
| Retoma (`startLeagueFight` repetido) | ✓ serve a MESMA luta (id 6), sem duplicar linha |
| `enterLeagueDivision` honra < 500 (skelletonx) | ✓ `errEnterLeagueDivisionMinHonorNotReached` |

Estado final deixado limpo: lutas de teste fechadas, personagens restaurados
(stamina 20/20, contadores a zero, pontos 0, 4 donutos repostos).

## Patamares diários de combates (2026-10-04)

A liga tem prémios aos 5/10/20 combates iniciados pelo herói e resgatados,
ganhos ou perdidos. Contagem independente de `league_fight_count` (limite
de ataques); defesas e retries não contam. A resposta de
`claimLeagueFightRewards` atualiza a barra imediatamente. Prémios,
cupões, temporada, reset diário e instalação em
[patamares de combates](../missoes/patamares_combates.md).
