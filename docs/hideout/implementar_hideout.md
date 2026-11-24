# Plano: Implementar Hideout (Esconderijo) — v2

> **v2** — reescrito depois da análise completa ao cliente (`docs/hideout/extract/`)
> e do estudo do servidor flash antigo (`herozero(flash 129 2018)/server/`),
> que tem uma implementação funcional do hideout. O plano original foi escrito
> antes de qualquer uma destas fontes; este substitui-o na totalidade.

## Fontes (por ordem de autoridade)

| Fonte | O que nos dá |
|---|---|
| [extract/](extract/README.md) (análise ao `HeroZero.min.js` build 257) | **Contrato exato**: as 39 ações com params verbatim, erros e respostas; campos dos objetos; fórmulas que o cliente recalcula |
| `server-laravel/data/*.json` | Respostas REAIS do oficial (formato dos envelopes, deltas, `hideout_battle`, profiles) |
| Servidor flash 129 (`class/Utils/HideoutUtils.php` + 24 request handlers + `GameSettings.php`) | Implementação de referência COMPLETA do núcleo: lógica de salas, combate, loot, Elo, oponentes + constantes server-side invisíveis no cliente |

## O que a referência flash resolve (e podemos portar quase diretamente)

`HideoutUtils.php` (~840 linhas) é um porte quase pronto para o nosso modelo:
grid de slots (mesma codificação `room_slot_L_S` com `-ts_fim` em desbloqueio),
ciclo completo de atividades (`completeRoomActivity`), caps/passivos/generator,
simulação de batalha com profiles `{a,w,d}` + rounds `{a,d,r,v}`, loot, Elo,
seleção de oponente, projeção do oponente (aparência+inventário), sweep de slot
unlocks expirados. Os handlers `request/*.req.php` mostram o fluxo pedido→validação
→custos→mutação→resposta para 24 ações.

### Descobertas que NÃO saíram da análise ao cliente (só existem aqui)

**Fórmulas de combate (server-side):**
```
ratio = crit1/crit2   (idem dodge)
se ratio <= 1: p = pow(ratio, exp_low)  * (base - min) + min
senão:         p = (1 - pow(1/ratio, exp_high)) * (max - base) + base

battle_critical_probability_{min,base,max} = 0.01, 0.02, 1.0
battle_critical_probability_{exp_low,exp_high} = 20, 1.225
battle_dodge_probability_{min,base,max}      = 0.01, 0.10, 0.60
battle_dodge_probability_{exp_low,exp_high}  = 5, 1.65
battle_critical_factor = 2.2   // multiplicador de dano crítico
battle_damage_tolerance = 0.3  // dano = rand(0.8..1.05) × strength
battle_hp_scale = 10           // HP = round(stamina × scale) — igual ao 257
battle_max_rounds = 80
```

**Modelo de loot (explica o split rewards/bonus_rewards das capturas):**
```
fraction    = hideout_battle_resource_win_amount            (0.33)
redução     = −(reduction × vitórias anteriores vs alvo)    (0.10/vitória)
base        = floor(recursos_atuais_do_defensor × fraction) → attacker_rewards
bonus       = round(base × increase/100)                    (60%) → attacker_bonus_rewards
```

**Elo:** k = `league_elo_calculation_k` (21); esperado `1/(1+10^((def−atk)/400))`;
delta clampado a ±[1..k]; defensor recebe sempre o simétrico.

**Outras regras:**
- Seleção de oponente: 70% ordenar por `hideout_points`, senão por `current_level`
  (`hideout_battle_use_points_leaderboard_chance`=0.7); escolhe o de valor mais
  próximo; proteção inicial de 24h medida sobre o `ts_creation` da 1ª sala.
- Validação anti-dessincronização do ataque: servidor recalcula loot e compara
  com `expacted_*`; se servidor < 80% do esperado → `errStartHideoutFightRewardsChanged`.
- Unidades subtraídas NO MOMENTO do ataque; loot creditado só no collect;
  recursos do defensor debitados imediatamente (no applyBattleOutcome).
- `missed_attacks` com teto 99 (`hideout_max_missed_attacks`); push socket
  `syncHideoutAttack {userId}` ao defensor.
- Parede sem nível + 0 defensores → parede dummy com stamina 50.
- Salvage do defensor: `destroyed_glue/stone_for_defender × unidades destruídas`
  → `defender_rewards` da batalha.
- Histórico = ataques RECEBIDOS (`defender_hideout_id = meu`), LIMIT 50, e limpa
  `missed_attacks`. Delete por ids `";"`-joined.
- Leaderboard: janela de 50 linhas centrada no rank pedido/pesquisado +
  `centered_rank`; online = `now - ts_last_action < 60`.

### Diferenças flash 129 ↔ HTML5 257 (adaptações obrigatórias)

| Ponto | Flash 129 | Build 257 (nós) |
|---|---|---|
| Pisos da grelha | `MAX_LEVELS=4` (20 slots) | **12 pisos × 5 = 60 slots** |
| Salas na master data | 11 (sem exchange/gym/blacksmith/trophy/brain/broker/kybernetic/personal_arena/scout_center/sports_bar/guild*) | 28 — já estão no template HAR do boot |
| `unlock_with_mainbuilding` | chaves planas `_1/_2/_3` | **vetor** indexado pela cópia (`get_unlockWithMainbuilding()[cópia]`) |
| Constantes de instant finish | `hideout_skip_{min,medium,long,max}_{time,premium_amount}` | renomeadas: `{short,medium,long,very_long}` + `hideout_skip_max_premium_amount` passa a TETO (499) e não custo máximo |
| `gem_production` | sala de produção manual normal | sala de **item improvement** (raridades, slots especiais) |
| Tutorial: `defender_hideout_id` | 0 | **−1** (confirmado nas capturas) |
| Rounds extras | só r=1/2/3 | r=4/5/6 (mísseis de guilda) — opcional no 1º passo |
| Ações ausentes | aborts×3, `getHideout`, `collectAllHideoutRoomResources`, `produceAllHideoutUnits`, todos os refreshes de salas especiais, `buyHideoutBooster`, guild bonus, workers premium | implementar a partir do `extract/acoes_protocolo.md` |

⚠️ O flash também aceita os DOIS estilos de param (`hideoutRoomId ?: hideout_room_id`)
— boa prática defensiva que mantemos, mas devemos emitir exatamente o que o
cliente 257 envia por ação (tabela no fim).

---

## Estado atual do nosso servidor

- `docker/initdb/05_hideout.sql`: só tabela `hideout_rooms` simples (sem `hideout_id`,
  sem valores adicionais).
- 6 ações: `getHideout` (cria 6 salas hardcoded), `buildHideoutRoom`,
  `upgradeHideoutRoom`, `startHideoutRoomProduction`,
  `instantFinishHideoutRoomActivity`, `hideoutExtra` (router genérico).
- Boot template já tem as 7 chaves master de hideout (da captura HAR);
  `boosters` vem **null** → temos de fornecer (ver §Constantes).
- Socket server existe (`socket-server/`) — usar para `syncHideoutAttack`.

## Fase 1 — Schema (`05_hideout.sql`)

### 1.1 Tabela `hideout` (1:1 com character)

Campos = payload `DOHideout` (extract/modelo_dados.md §2) + internos:

```sql
CREATE TABLE IF NOT EXISTS `hideout` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `hideout_points` int(11) NOT NULL DEFAULT 0,
  `current_level` int(11) NOT NULL DEFAULT 0,
  `idle_worker_count` int(11) NOT NULL DEFAULT 1,
  `max_worker_count` int(11) NOT NULL DEFAULT 1,
  `ts_time_worker_expires` int(11) NOT NULL DEFAULT 0,        -- worker de aluguer
  `current_resource_glue` int(11) NOT NULL DEFAULT 10,
  `max_resource_glue` int(11) NOT NULL DEFAULT 0,
  `current_resource_stone` int(11) NOT NULL DEFAULT 10,
  `max_resource_stone` int(11) NOT NULL DEFAULT 0,
  `current_attacker_units` int(11) NOT NULL DEFAULT 0,
  `max_attacker_units` int(11) NOT NULL DEFAULT 0,
  `current_defender_units` int(11) NOT NULL DEFAULT 0,
  `max_defender_units` int(11) NOT NULL DEFAULT 0,
  `current_opponent_id` int(11) NOT NULL DEFAULT 0,
  `current_opponent_chest_reward` tinyint(1) NOT NULL DEFAULT 0,
  `ts_last_opponent_refresh` int(11) NOT NULL DEFAULT 0,
  `active_battle_id` int(11) NOT NULL DEFAULT 0,
  `ts_last_lost_attack` int(11) NOT NULL DEFAULT 0,
  `current_worker_level` int(11) NOT NULL DEFAULT 0,
  `current_wall_level` int(11) NOT NULL DEFAULT 0,
  `current_barracks_level` int(11) NOT NULL DEFAULT 0,
  `max_barracks_level` int(11) NOT NULL DEFAULT 0,
  `current_gem_production_level` int(11) NOT NULL DEFAULT 0,
  `current_broker_level` int(11) NOT NULL DEFAULT 0,
  `current_robot_storage_level` int(11) NOT NULL DEFAULT 0,
  `missed_attacks` int(11) NOT NULL DEFAULT 0,                -- interno; sai como missed_hideout_attacks
  -- grelha 12×5 = 60 colunas room_slot_L_S (0..11 × 0..4):
  --   piso 0 todo a 0; pisos 1..11 a -1 (bloqueado)
  `room_slot_0_0` int(11) NOT NULL DEFAULT 0,
  ...                                                          -- (gerar as 60 por script)
  PRIMARY KEY (`id`), UNIQUE KEY `character_id` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

Justificação para 60 colunas em vez de JSON: o payload serializa cada chave
diretamente (é o formato que o cliente lê), a referência flash faz o mesmo e
evita expansão manual em cada resposta.

### 1.2 Tabela `hideout_battles`

Colunas da captura real (`respostas_capturadas.md`):

```sql
CREATE TABLE IF NOT EXISTS `hideout_battles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attacker_hideout_id` int(11) NOT NULL,
  `defender_hideout_id` int(11) NOT NULL,          -- -1 no tutorial
  `defender_server_id` varchar(16) NOT NULL DEFAULT '',
  `attacker_status` tinyint(4) NOT NULL DEFAULT 0, -- 1 fought, 2 finished, 3 rewards processed
  `defender_status` tinyint(4) NOT NULL DEFAULT 0,
  `attacker_count` int(11) NOT NULL DEFAULT 0,
  `defender_count` int(11) NOT NULL DEFAULT 0,
  `attacker_profiles` mediumtext NOT NULL,          -- STRING JSON {"a":{...}}
  `defender_profiles` mediumtext NOT NULL,          -- STRING JSON {"w":{},"d":{},"oc":[]}
  `rounds` mediumtext NOT NULL,                     -- STRING JSON [{a,d,r,v}]
  `attacker_rewards` text NOT NULL,                 -- STRING JSON Reward
  `attacker_bonus_rewards` text NOT NULL,
  `defender_rewards` text NOT NULL,
  `destroyed_attacker` int(11) NOT NULL DEFAULT 0,
  `destroyed_defender` int(11) NOT NULL DEFAULT 0,
  `hideout_winner_id` int(11) NOT NULL DEFAULT 0,
  `server_winner_id` varchar(16) NOT NULL DEFAULT '',
  `attacker_character_name` varchar(64) NOT NULL DEFAULT '',
  `defender_character_name` varchar(64) NOT NULL DEFAULT '',
  `ts_creation` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `attacker_hideout_id` (`attacker_hideout_id`),
  KEY `defender_hideout_id` (`defender_hideout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

### 1.3 Alterar `hideout_rooms`

```sql
ALTER TABLE `hideout_rooms`
  ADD COLUMN `hideout_id` int(11) NOT NULL DEFAULT 0 AFTER `character_id`,
  ADD COLUMN `ts_creation` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN `additional_value_1` int(11) NOT NULL DEFAULT 0,     -- ts refresh / id de item
  ADD COLUMN `additional_value_2` text NOT NULL,                  -- STRING JSON (is_zeronit, stat_type, cost_*, improvement_rarity...)
  ADD COLUMN `guild_synergy_additional_value` text NOT NULL,      -- STRING JSON (qualified, active, upgrade_level...)
  DROP COLUMN `slot`;                                             -- posição vive na grelha do hideout
```

## Fase 2 — Modelo `app/HeroZero/Hideout.php`

Porte do `HideoutUtils.php` com as adaptações da tabela acima. Métodos:

- **Master data**: `getRoomConfig/getRoomLevelConfig/getRoomSize/getRoomLimit`,
  `getUpgradeCharacterMinLevel`, `getMaxHideoutLevel` — ler das chaves do boot.
- **Grelha**: `slotKey/isSlotAreaFree/assignRoomToSlots/clearRoomFromSlots/
  isRoomPlaced/getUnlockedSlotCount/sweepExpiredSlotUnlocks` — direto do flash,
  com `MAX_LEVELS=12`.
- **Fatores**: `getWorkerSpeedFactor` (worker_home), `getGeneratorCapacityFactor/
  DurationFactor` (por sala via `current_generator_level`), fator de sinergia de
  guilda (novo, 257: salas auto_join/competition_point).
- **Recursos**: `calculateAutoRoomProducedAmount` (accrual por minuto até max),
  `getEffectiveMaxResourceAmount` (main_building → gold por nível do personagem;
  auto rooms × generator), `getMainBuildingGoldMax` (`14.3·lvl^1.39 × rpm × boost`),
  `recalculateHideoutCaps` (main_building passiv_bonus_amount_1/2 = max glue/stone;
  attacker/defender_production `resource_production_max` = max unidades).
  ⚠️ No 257 o cliente recalcula `maxResourceAmount` localmente com o generator
  factor — enviar a BASE (`resource_production_max` do nível) e deixar o cliente
  multiplicar (ver extract/modelo_dados.md §6).
- **Atividades**: `completeRoomActivity` (BUILDING→level 1; UPGRADING→level+1;
  PLACING soma nível ao current_level; STORING subtrai e limpa slots;
  PRODUCING→enche), `applyRoomPassives/removeRoomPassives`,
  `refreshGeneratorLevels`, flags tutorial de build (`hideout_first_building`,
  `hideout_build_stone/glue/attacker`).
- **Unidades**: `getProducedUnitLevel` (barracks passiv_bonus_amount_1),
  `getUnitStats`, custos/duração de produção.
- **Combate**: `buildBattleQueues` (parede primeiro, dummy stamina 50 se preciso),
  `simulateBattle` (com as fórmulas de probabilidade acima; HP = stamina ×
  `battle_hp_scale`; alternância de turnos; crítico ×2.2; dano
  rand(0.8–1.05)×strength; máx. `battle_max_rounds`), `profileKey`
  (`explode('_')[0]` → perfis agrupados `a`/`w`/`d` + array `oc`),
  `calculateDefenderSalvage`, `countWinsAgainst`, `calculateHideoutPointsDelta`
  (Elo), `applyBattleOutcome`.
- **Oponentes**: `selectOpponent` (70/30 pontos/nível, mais próximo, proteção 24h),
  `buildOpponentProjection` (personagem + aparência default-safe + inventory +
  items + sidekick + `rewards`/`bonus_rewards` como strings JSON — formato da
  captura em `respostas_capturadas.md`), `calculateOpponentLoot` (fração/redução/bónus).
- **Payload**: `buildHideoutPayload($cid)` →
  `['hideout','hideout_room'=>null,'hideout_rooms','hideout_opponent',
    'hideout_battle'(só se ativa),'missed_hideout_attacks']`;
  `attachHideoutState` para boot/accountState.

## Fase 3 — Boot + leitura

1. `autoLoginUser`/`accountState` (`Live.php`): injetar payload se tem hideout +
   `missed_hideout_attacks` (padrão loginUser.req.php L186-199 da referência).
2. Reescrever `actions/getHideout.php`: `ensureHideout` → cria row + 1ª sala
   (main_building) ou segue flash (hideout criado só por `unlockHideout`);
   devolver payload completo.
3. `syncHideout {}` → `buildHideoutPayload` (substituir case atual no router).
4. `unlockHideout {}` → valida nível ≥ `hideout_unlock_min_level`, cria row com
   bases (`hideout_base_worker_count`=1, glue/stone=10), devolve hideout.
5. `getHideout {hideout_id, server_id}` → payload sob `requested_hideout` +
   `requested_hideout_rooms` (+ optical_changes vazias) — visita a hideouts alheios.

## Fase 4 — Operações de salas (contratos no extract/acoes_protocolo.md)

Porte direto dos handlers flash, com validações/erros EXATOS do cliente 257:

| Ação | Notas do porte |
|---|---|
| `buildHideoutRoom {identifier, level, slot}` | worker, limit, vetor `unlock_with_mainbuilding[cópias]`, custos nível 1, duração `build_time×60×workerFactor`, STATUS_BUILDING, atribui slots |
| `placeHideoutRoom {hideoutRoomId, level, slot}` | só salas IDLE não colocadas; STATUS_PLACING `place_duration_per_level×nível`; família `errPlacePendingActivity*` |
| `upgradeHideoutRoom {hideoutRoomId, ignoreResource, synergyUpgrade}` | `errUpgradeResourceLeftInRoom` se auto-room com recursos; min level personagem por `hideout_room_upgrade[level+1]`; flag `hideout_first_upgrade` |
| `storeHideoutRoom {hideoutRoomId, ignoreResource}` | STATUS_STORING; STORING concluído → remove passivos, limpa slots, current_level −= nível |
| `checkHideoutRoomActivityFinished {hideout_room_id}` | `completeRoomActivity` quando `ts_activity_end ≤ now`; erros NotJetPossible/InvalidStatus |
| `collectHideoutRoomActivityResult {hideout_room_id, collect[, discard_item]}` | auto → accrual, continua PRODUCING; manual → tudo no fim, volta a IDLE; erros `errIncreaseValue*` (guild competition) e `errInventoryNoEmptySlot` para salas de item |
| `startHideoutRoomProduction {hideoutRoomId, productionCount}` | **semântica de productionCount por sala** (unidades=qtd; exchange/gym/blacksmith=1; improvement=índice raridade 0..2; medikit/oil=id do item; missile/auto_join=qtd) — ver extract/salas_especiais.md |
| `instantFinishHideoutRoomActivity {hideout_room_id}` | custo curva 257 (short/medium/long/very_long, teto 499) |
| `abortHideoutRoomStoring/Upgrading {hideoutRoomId}` · `...SlotUnlocking {level, slot}` | NOVO vs flash: liberta worker, repõe estado, erros `errAbort*InvalidStatus` |
| `unlockHideoutRoomSlot {level, slot}` / `checkUnlockHideoutRoomSlotFinished` / `instantFinishHideoutSlotUnlock` | slot = `-(now+duração)`; sweep no payload; custos `hideout_expansion[nº desbloqueados+1]` |
| `collectAllHideoutRoomResources {}` / `produceAllHideoutUnits {}` | loop pelas salas elegíveis; erros NothingToCollect/Produce ignoráveis pelo cliente |

Resposta mínima por ação: `user`, `character` + objetos alterados (deltas);
o cliente faz merge (`updateData`).

## Fase 5 — Combate (porte startHideoutFight.flash + correções 257)

1. `refreshHideoutOpponent {use_premium}` — cooldown
   `hideout_opponent_refresh_free_cooldown` (10 min) desde `ts_last_opponent_refresh`;
   premium `hideout_opponent_refresh_premium_amount`; erros
   `NotYetAllowed`(silencioso)/`NoOpponents`.
2. `getHideoutOpponent {}` — devolve o atual; se inválido seleciona outro.
3. `startHideoutFight {attacker_count, expacted_glue_reward, expacted_stone_reward,
   use_premium}` — validações por ordem: batalha ativa → `NotJetPossible`;
   sem oponente → `NoOpponent`; alvo inválido/protegido → `InvalidTarget`;
   `attacker_count ∈ [1..current_attacker_units]` → erro minúsculo
   `errstartHideoutBattleInvalidAttackerCount`; cooldown
   (`hideout_battle_cooldown` min desde `ts_last_lost_attack`) com skip premium
   `hideout_battle_skip_cooldown_premium_amount`; validação rewards 80% →
   `RewardsChanged`. Depois: queues → simulateBattle → criar batalha (status FOUGHT)
   → subtrair unidades → `applyBattleOutcome` (debita defensor, Elo, missed_attacks++,
   push socket `syncHideoutAttack`). Resposta: `hideout` + `hideout_battle`.
4. `collectHideoutFightRewards {hideout_battle_id}` — credita
   `attacker_rewards`+`attacker_bonus_rewards` (clamp aos caps), status
   REWARDS_PROCESSED, `active_battle_id=0`, flag `hideout_first_attack`, goal
   `hideout_hideout_points_reached`. Erro invalid-battle silencioso no cliente.
5. Tutorial: `startHideoutTutorialFight {attacker_count}` — defensor fixo
   ('professor' ou stats das constantes `hideout_first_enemy_*`), `defender_hideout_id=-1`;
   `finishHideoutTutorial {}` — flag + reward glue/stone das constantes.
6. Histórico (defesa): `getHideoutBattleHistoryFights {}` (limpa missed_attacks,
   lista c/ character join), `getHideoutBattleHistoryFight {id}` (batalha COMPLETA
   sob `hideout_history_battle`), `deleteHideoutHistoryBattles {battle_ids:";"}`,
   `deleteAllHideoutHistoryBattles {max_battle_id}`.
7. `retrieveHideoutLeaderboard {server_id, points_sort[, rank|character_name]}`
   — SQL da referência (janela 50 centrada) + `centered_rank`, `max_hideouts`.

## Fase 6 — Salas especiais (novas — contratos em extract/salas_especiais.md)

Estado por sala em `additional_value_1` (ts/id) + `additional_value_2` (JSON):
exchange (`is_zeronit`,`duration`,`resource_duration`,`exchange_rate`), gym
(`stat_type`,`duration`), blacksmith (`cost_type`,`cost_amount`, ts_item/cost_refresh;
item em `additional_value_1`), improvement (`improvement_rarity` {0,1,2},
`improvement_slot`). Ações: `refreshHideoutExchangeRate/GymRate/BlacksmithItem/
BlacksmithCost/ItemImprovementSlot/ItemImprovementRarity {hideoutRoomId, free}`
(grátis 1×/dia por ts; senão premium), `getHideoutBlacksmithItem`,
`getHideoutItemImprovementGuildBonus {}` → `guild_gem_production_level`.
Guild rooms + sinergia: exigem `guild_main_building` colocado + pontos de sinergia;
`guild_synergy_additional_value` por sala.

Workers premium: `ts_time_worker_expires` + produto de pagamento
(`hideout_worker`/`hideout_time_worker`) — integrar com o billing existente.
Boosters: `buyHideoutBooster {id, duration}` + master data `boosters` (fornecer!).

## Fase 7 — Integração final

- Passivos no personagem: **kybernetic_improvement implementado** —
  `Hideout::kyberneticBonuses(characterId)` devolve `{stats_pct, item_flat}` lidos
  de `passiv_bonus_amount_1/2` das constantes. `stats_pct` entra em
  `Character::statMultiplier()` (junto com booster/guilda/liga); `item_flat` é
  somado ao `$budget` de `generateShopItem()`. A sala só dá bónus quando está
  **efectivamente na grelha** — verificado via `Hideout::isRoomPlaced()` (BUG-000107).
  **personal_arena implementado** — `Hideout::personalArenaBonuses(characterId)`
  devolve `{duel_stamina, league_stamina}` flat a somar ao cap máximo.
  O bónus é aplicado em três locais: `Character::syncDuelStamina()` (regeneração
  de Coragem), `League::syncStamina()` (regeneração de Adrenalina), e
  `Character::characterData()` (serialização do `max_duel_stamina`/
  `max_league_stamina` enviado ao cliente, a seguir ao loop de
  `GuildArtifacts::effectiveMax`). Nível 1=+5/+2, nível 25=+125/+50.
  Só activo se a sala estiver na grelha (via `isRoomPlaced`).
  scout_center/sports_bar/trophy_room (efeitos resolvidos AQUI no
  servidor — o cliente não os calcula!), broker/robot_storage/barracks como
  níveis no payload.
- Socket: push `syncHideoutAttack {userId}` ao ser atacado; `syncGuildSynergy`
  pode levar `sync_hideout:true`.
- Missed attacks no boot; leaderboard ligado; goals (`hideout_glue_collected`, etc.).

## Constantes a garantir no config (além das 7 chaves master)

Server-side (da referência flash, invisíveis no cliente):
`hideout_base_worker_count=1`, `hideout_base_resource_glue/stone=10`,
`hideout_initial_protection_time=24`, `hideout_battle_use_points_leaderboard_chance=0.7`,
`hideout_battle_resource_win_amount=0.33` (+`_reduction=0.1`, `_increase=60`),
`hideout_max_missed_attacks=99`, curvas `battle_critical/dodge_probability_*`,
`battle_critical_factor=2.2`, `battle_damage_tolerance=0.3`, `battle_max_rounds=80`,
`league_elo_calculation_k=21`.

Do lado do cliente (257 — nomes NOVOS das skip):
`hideout_skip_{short,medium,long,very_long}_{time,premium_amount}` (60/0.2,
3600/4, 86400/52, 604800/200) + `hideout_skip_max_premium_amount=499` (TETO),
mais as ~50 escalares de extract/constantes.md. Verificar quais já existem no
constants_json e injetar as em falta (`withLocalConstants`).

## Mapeamento de ações → ficheiros

| Ficheiro | Ações |
|---|---|
| `actions/getHideout.php` (reescrever) | getHideout |
| `actions/buildHideoutRoom.php`, `upgradeHideoutRoom.php`, `startHideoutRoomProduction.php`, `instantFinishHideoutRoomActivity.php` (reescrever) | as homónimas |
| `actions/unlockHideout.php`, `syncHideout.php`, `placeHideoutRoom.php`, `storeHideoutRoom.php` (novos) | homónimas |
| `actions/hideoutExtra.php` (expandir) | check*, collect*, unlock slot×3, abort×3, all×2, refreshes de salas especiais, blacksmith, improvement, guild bonus, buyHideoutBooster, tutorial×2, opponent×2, fight, rewards, history×4, leaderboard |

## Ordem de implementação

1. Schema (Fase 1) + gerador das 60 colunas de slot
2. `Hideout.php` — núcleo (grelha, caps, atividades, fatores)
3. Boot/getHideout/sync/unlock → hideout visível com dados reais
4. Operações de salas → economia funciona
5. Combate + histórico + leaderboard → propósito PvP
6. Salas especiais → features avançadas
7. Workers/boosters/passivos/socket → polimento

## Verificação

1. Nível 8+ → `unlockHideout` cria row → painel abre sem erros de constantes
2. Build/upgrade/place/store com custos/durações/workers corretos; erros pending-activity recuperam com sync
3. Produção auto acumula por minuto; collect credita; caps respeitados
4. Unidades produzidas ao nível da barracks; atacar funciona; rewards creditadas SÓ no collect; defensor vê `missed_hideout_attacks` e recebe push socket
5. Elo aplicado aos dois lados; cooldown após derrota com skip premium
6. Slots 12×5 com unlock em curso (`-(ts)`), sweep automático, instant finish
7. Refreshes diários grátis das salas especiais; segunda vez cobra premium
8. Duas contas simultâneas: ataque de A reflete-se em B em tempo real


## Estado atual — robôs de trabalho (2026-10-07)

`unlockHideout` cria o esconderijo com um robô (`max_worker_count=1`,
`idle_worker_count=1`), sob lock/transação. Repetir o desbloqueio não dá robôs.
O segundo permanente exige o produto especial `hideout_worker`, anunciado na
loja e no diálogo «+ robô» com preço visual local de 34,90 R$: compra local
gratuita que aumenta o máximo para dois e soma um robô livre, sem alterar a
ocupação anterior. O catálogo deixa de anunciá-lo depois da compra. A resposta
e o login incluem o esconderijo real. Ver [compras](../compras/overview.md).

A interpretação histórica de «dois iniciais/três após compra» estava incorreta;
o bundle identifica a compra permanente pelo máximo dois, quando sem aluguer.
O aluguer temporário não foi implementado neste pedido. Esconderijos antigos
com dois robôs mantêm o estado existente; não se cancelam trabalhos para os reduzir.
