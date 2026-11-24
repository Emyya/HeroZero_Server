# Ações HTTP e mensagens socket do hideout (`HeroZero.min.js` build 257)

> Fonte: análise exaustiva do `server-laravel/public/js/HeroZero.min.js`.
> Lista COMPLETA de `sendActionRequest` relacionados com hideout (39 ações +
> mensagens socket), com params verbatim e tratamento de erros.
> Complementa: `modelo_dados.md`, `combate.md`, `salas_especiais.md`,
> `constantes.md`, `respostas_capturadas.md`.

Índice de ações:
[unlockHideout](#1-unlockhideout) · [getHideout](#2-gethideout) ·
[syncHideout](#3-synhideout) · [buildHideoutRoom](#4-buildhidoutroom) ·
[placeHideoutRoom](#5-placehideoutroom) · [upgradeHideoutRoom](#6-upgradehideoutroom) ·
[storeHideoutRoom](#7-storehideoutroom) · [startHideoutRoomProduction](#8-starthideoutroomproduction) ·
[collectHideoutRoomActivityResult](#9-collecthideoutroomactivityresult) ·
[checkHideoutRoomActivityFinished](#10-checkhideoutroomactivityfinished) ·
[instantFinishHideoutRoomActivity](#11-instantfinishhideoutroomactivity) ·
[collectAllHideoutRoomResources](#12-collectallhideoutroomresources) ·
[produceAllHideoutUnits](#13-produceallhideoutunits) ·
[unlockHideoutRoomSlot](#14-unlockhideoutroomslot) ·
[checkUnlockHideoutRoomSlotFinished](#15-checkunlockhideoutroomslotfinished) ·
[instantFinishHideoutSlotUnlock](#16-instantfinishhideoutslotunlock) ·
[abort×3](#1719-aborthideoutroomstoring--upgrading--slotunlocking) ·
[refreshHideoutOpponent](#20-refreshhideoutopponent) · [startHideoutFight](#21-starthideoutfight) ·
[startHideoutTutorialFight](#22-starthideouttutorialfight) · [finishHideoutTutorial](#23-finishhideouttutorial) ·
[collectHideoutFightRewards](#24-collecthideoutfightrewards) ·
[getHideoutBattleHistoryFights](#25-gethideoutbattlehistoryfights) ·
[getHideoutBattleHistoryFight](#26-gethideoutbattlehistoryfight) ·
[deleteHideoutHistoryBattles](#27-deletehideouthistorybattles) ·
[deleteAllHideoutHistoryBattles](#28-deleteallhideouthistorybattles) ·
[retrieveHideoutLeaderboard](#29-retrievehideoutleaderboard) ·
[getHideoutOpponent](#30-gethideoutopponent) ·
[getHideoutBlacksmithItem](#31-gethideoutblacksmithitem) ·
[refreshHideoutBlacksmithItem/Cost](#3233-refreshhideoutblacksmithitem--refreshhideoutblacksmithcost) ·
[refreshHideoutExchangeRate/GymRate](#3435-refreshhideoutexchangerate--refreshhideoutgymrate) ·
[refreshHideoutItemImprovementRarity/Slot](#3637-refreshhideoutitemimprovementrarity--slot) ·
[buyHideoutBooster](#38-buyhideoutbooster) ·
[getHideoutItemImprovementGuildBonus](#39-gethideoutitemimprovementguildbonus)

---

## Padrões comuns (lidos diretamente do cliente)

### Envelope do pedido

`eb.prototype.sendActionRequest` embrulha o callback num objeto
`{action, request, data, error}` com accessor tipado `get_appResponse()`.
`ApplicationRequest` **proíbe** o chamador de passar `action`, `user_id`,
`user_session_id`, `client_version` ou `auth` nos params — são adicionados sempre:

```js
this._request.setString("user_id",Ra.config.getString("userId"));
this._request.setString("user_session_id",rf.get_userSessionId());
this._request.setString("client_version",zq._clientVersion);
this._request.setInt("build_number",zq.buildNumber);
this._request.setString("auth",this.getRequestSignature(this._request.getString("action"),this._request.getString("user_id")));
this._request.setInt("rct",im.current.get_serverConnection().get_sessionConnectionType());
this._request.setBoolean("keep_active",!0);
```

Assinatura: `md5(action + "GN1al351" + user_id)`.

Dedupe client-side: pedidos idênticos em <500 ms são descartados em silêncio:

```js
wi._lastRequestKey==b&&wi._lastRequestTimestamp+500>=g → return
```

### Envelope da resposta

```js
if(!L.hasData("data"))throw new aa("Field 'data' not found.");
if(!L.hasData("error"))throw new aa("Field 'error' not found.");
""==L.getString("error")?e=L.getData("data"):(a=L.getString("error"),e=null)
```

O campo opcional `server_time` (+ `time_correction`) corrige o relógio local:
`K.correctTime(e.server_time,.5*b)`.

### `updateData` — injeção de estado

`updateData(data)` dispara `ApplicationMessage.updateUserAndCharacter`; o handler
consome estas chaves hideout em QUALQUER resposta (@2020527):

```js
c.hasData("hideout",!0)             → refreshHideout(...)        // merge parcial
c.hasData("hideout_room",!0)        → get_hideout().refreshRoom(...)
c.hasData("hideout_rooms",!0)       → refreshRooms(...)
c.hasData("hideout_opponent",!0)    → refreshOpponent(...)
c.hasData("hideout_battle",!0)      → refreshBattle(...)         // só se id==active_battle_id
c.hasData("missed_hideout_attacks",!0)   → set_missedAttacks(...)
c.hasData("guild_gem_production_level",!0) → Cf.set_guildGemProductionLevel(...)
```

Accessores adicionais do envelope: `get_requested_hideout`,
`get_requested_hideout_rooms`, `get_requested_hideout_optical_changes`,
`get_hideout_history_battle(s)`, `get_leaderboard_hideouts`, `get_max_hideouts`,
`get_centered_rank`.

### Erros

- `n.reportError(a.error,a.request,c)` — 3º arg `!1` suprime o comportamento
  default do error reporter; usado nas ações de leitura/refresh.
- Todos os switches terminam em
  `default:throw new aa("Unsupported request action '"+a.action+"'!")` — o
  backend nunca deve devolver uma action desconhecida.
- Padrão "pending activity": erros `err*PendingActivity*` indicam estado
  divergente → recuperação é SEMPRE `syncHideout {}` ou `hideout.sync()`.
- Erros `*NotJetPossible` [sic] → retry automático após 1 s
  (`ka.delayFunction(fn,1)`).

### Convenção de nomes de params (inconsistente — replicar tal qual!)

| Estilo | Params |
|---|---|
| camelCase | `hideoutRoomId`, `level`, `slot`, `identifier`, `productionCount`, `ignoreResource`, `synergyUpgrade`, `free`, `use_premium`, `id`, `duration` |
| snake_case | `hideout_room_id`, `hideout_battle_id`, `battle_ids`, `max_battle_id`, `attacker_count`, `expacted_glue_reward` [sic], `expacted_stone_reward` [sic], `discard_item`, `collect`, `points_sort`, `character_name`, `rank`, `server_id` |
| híbrido | `hideout_id` |

---

## 1. unlockHideout

Envio (ao abrir o painel sem hideout; guard `_unlockHideoutSend` evita duplicados):

```js
this._unlockHideoutSend||(n.application.sendActionRequest("unlockHideout",{},k(this,this.handleRequests)),this._unlockHideoutSend=!0,
```

Params: `{}`. Resposta: sucesso → `updateData(a.data)` + `this._rooms.startAnimations()`;
erro → só `reportError` genérico (e `_unlockHideoutSend=!1` para permitir retry).

## 2. getHideout

Visitar o hideout de OUTRO jogador (`loadHideout`; só se ainda não carregado ou
id/server diferente):

```js
b.setInt("hideout_id",a);b.setString("server_id",c);
n.application.sendActionRequest("getHideout",{hideout_id:a,server_id:c},k(this,this.handleRequests))
```

Resposta: `updateData` + `showCustomHideout(Ie.fromData(...))`. O hideout visitado
chega sob as chaves `requested_hideout`, `requested_hideout_rooms`,
`requested_hideout_optical_changes`:

```js
var b=new Ie(a.get_requested_hideout());
a.hasData("requested_hideout_rooms")&&b.refreshRooms(a.get_requested_hideout_rooms());
a.hasData("requested_hideout_optical_changes")&&b.update(...)
```

## 3. syncHideout {#synhideout}

Sempre `{}`. Origens: `Ie.prototype.sync()` (contador de ataques perdidos,
forceSync), erros pending-activity dos dialogs. Handler na classe Hideout:

```js
if("syncHideout"==a.action)""==a.error?n.application.updateData(a.data):
 n.reportError(a.error,a.request,!1);
else throw new aa("Unsupported request action '"+a.action+"'!");
```

`forceSync()` marca `_forceSync=!0` e sincroniza imediatamente SE o painel
"hideout" estiver ativo; senão fica pendente até à próxima abertura.

## 4. buildHideoutRoom

Colocar sala NOVA comprada/na loja (`onConfirmPlacing`):

```js
n.application.sendActionRequest("buildHideoutRoom",{identifier:b,level:c,slot:e},...)
// identifier = tipo de sala ("glue_production"...), level, slot
```

Sucesso → som `hideout_room_start_building.mp3` + notificação local
`notify("hideout_room_build", ts_end-now)`. Erros:

| Erro | Ação |
|---|---|
| `errBuildRoomNoIdleWorker` | dialog no_idle_worker |
| `errAssignRoomNoSpace` | dialog no_space |
| `errRemoveGameCurrencyNotEnough` / Glue / Stone | dialogs de recursos (preço nível 1 c/ desconto broker) |
| `errBuildRoomMainbuildingToLow` | `sync()` |
| `errBuildRoomLimitReached` | `sync()` |

## 5. placeHideoutRoom

Sala vinda da store/inventário:

```js
n.application.sendActionRequest("placeHideoutRoom",{hideoutRoomId:a.get_id(),level:c,slot:e},...)
```

Erros: `errPlaceRoomNoIdleWorker`, `errAssignRoomNoSpace` → dialogs;
`errInvalidHideoutRoom` ou família `errPlacePendingActivity{Building,Upgrading,
Placing,Storing,Producing}` ou `errPlacePendingActivity` → close+`sync()`.
Sucesso → notificação `hideout_room_placed`.

## 6. upgradeHideoutRoom

```js
n.application.sendActionRequest("upgradeHideoutRoom",
 {hideoutRoomId:this._room.get_id(),ignoreResource:!1,synergyUpgrade:a},...)
// ignoreResource:!0 quando o jogador escolhe DESCARTAR os recursos na sala
// synergyUpgrade:!0 quando é upgrade adicional de generator via sinergia
```

Sucesso → flag tutorial `hideout_first_upgrade`, som, notify `hideout_room_upgraded`. Erros:

| Erro | Ação |
|---|---|
| `errUpgradeResourceLeftInRoom` | dialog confirmação → reenvio com `ignoreResource:!0` |
| `errUpgradeRoomNoIdleWorker` | tentar instant-finish dialog; senão erro |
| `errRemoveGameCurrencyNotEnough`/Glue/Stone | dialogs (preço do nível+1 c/ broker) |
| `errUpgradingHideoutRoomNotJetPossible` | retry em 1 s |
| `errUpgradeRoomCharacterLevelToLow`, `errUpgradePendingActivity*`, `errUpgradeRoomGuildSynergyLevelMissingUpgrade`, `errUpgradeRoomNoGuildMainBuildingPlaced` | close+`sync()` |

## 7. storeHideoutRoom

```js
n.application.sendActionRequest("storeHideoutRoom",{hideoutRoomId:this._room.get_id(),ignoreResource:!1},...)
// variante discard: {...,ignoreResource:!0}
```

Erros: `errStoreResourceLeftInRoom` (dialog confirmação — botão `btn_cancle`
[sic]), `errStoreRoomNoIdleWorker` (instant finish), `errStoreHideoutRoomNotJetPossible`
(retry 1 s), `errCollectActivityResultInvalidAmount` (só fecha), família
`errStorePendingActivity*` → close+`sync()`. Sucesso → notify `hideout_room_stored`.

## 8. startHideoutRoomProduction

**10 pontos de envio**, todos `{hideoutRoomId:<id>, productionCount:<N>}` — o
SIGNIFICADO de `productionCount` varia por sala (ver detalhes em
`salas_especiais.md`):

| Sala | productionCount |
|---|---|
| exchange_room, blacksmith, gym, improvement slot (13/14) | 1 |
| attacker_production / defender_production | nº de unidades (slider) |
| guild_auto_join_production | nº de auto joins |
| guild_missile_production | nº de mísseis |
| guild_medikit_production / guild_weapon_oil_production | **id do item consumido** |
| gem_production (raridade) | **índice 0..2 da raridade escolhida** |

Sucesso → `updateData` + fechar diálogo + notificação local
(`hideout_blacksmith_production_finished`, `hideout_attacker_production_finished`,
`hideout_guild_missile_...`, etc.). Erros comuns:

```js
"errStartHideoutRoomProductionInvalidProductionCount"==a.error||
"errStartProductionPendingActivityBuilding"==...||"...Upgrading"||"...Placing"||
"...Storing"||"...Producing"||"errStartProductionPendingActivity"==a.error
 ? sendActionRequest("syncHideout",{},...) : n.reportError(...)
```

Variações por sala:
- exchange: também `errRemoveGlueNotEnough`/`errRemoveStoneNotEnough` (custo
  `get_exchangeRoomCostAmount()`).
- blacksmith: custos insuficientes mostram `additional_value_2.cost_amount`.
- gym: `errRemoveGameCurrencyNotEnough`.
- salas de guilda: acrescentam `errStartHideoutRoomProductionGuildSynergyNotActive`
  e `errStartHideoutRoomProductionNoGuildMainBuildingPlaced` → close+`sync()`;
  auto_join tem `errRemoveOpticalChangeResourceNotEnough` → `syncOpticalChanges {}`.
- gem_production / unidades: pending → `hideout.sync()` (sem syncHideout).

## 9. collectHideoutRoomActivityResult

⚠️ Aqui os params são **snake_case** (única família assim):

```js
n.application.sendActionRequest("collectHideoutRoomActivityResult",
 {hideout_room_id:this._room.get_id(),collect:!0},...)
// variante descartar item: {...,collect:!0,discard_item:!0}
```

Sucesso → animação/efeitos por tipo de sala, aviso "recurso cheio",
dispatch `HideoutMessage.attackerProduced`/`defenderProduced`. Erros:

| Erro | Ação |
|---|---|
| `errCollectHideoutRoomActivityNotJetPossible` | disable botão + retry 1 s |
| `errCollectActivityResultInvalidAmount` | **ignorado silenciosamente** |
| `errCollectActivityResultInvalidStatus`, `...GuildSynergyNotActive`, `...NoGuildMainBuildingPlaced` | `sync()` |
| `errIncreaseValueInvalidData` / `CharacterNotSaved` / `TournamentLocked` | dialog guild_competition_not_active |
| `errIncreaseValueNotInGuild` | dialog guild_competition_not_in_guild |
| `errIncreaseValueInvalidJoinTime` | dialog guild_competition_joined_too_recently |
| `errInventoryNoEmptySlot` | dialog sem slots livres (vender/descartar item) |

## 10. checkHideoutRoomActivityFinished

Polling quando o timer local de uma sala termina:

```js
n.application.sendActionRequest("checkHideoutRoomActivityFinished",
 {hideout_room_id:this._room.get_id()},...)   // snake_case!
```

Sucesso → `updateData` + som "ready" por identificador. Erros:
`errCheckHideoutRoomActivityNotJetPossible` → retry 1 s;
`errCheckHideoutRoomActivityFinishedInvalidStatus` → `sync()`;
`errInvalidHideoutRoom` → ignorado silenciosamente.

## 11. instantFinishHideoutRoomActivity

```js
n.application.sendActionRequest("instantFinishHideoutRoomActivity",
 {hideout_room_id:this._room.get_id()},...)   // snake_case!
```

Custo premium calculado pelo cliente (`tb.getInstantFinishPremiumAmount`). Erros:
`errRemovePremiumCurrencyNotEnough` → dialog premium;
`errInstantFinishActivityInvalidStatus` → close+`sync()`.

## 12. collectAllHideoutRoomResources

```js
n.application.sendActionRequest("collectAllHideoutRoomResources",{},...)
```

Resposta traz `hideout_rooms[]` com `current_resource_amount`. Erros:
`errCollectAllHideoutRoomResourcesNothingToCollect` ignorado;
`errRemovePremiumCurrencyNotEnough` → dialog com `hideout_collect_all_cost`.

## 13. produceAllHideoutUnits

```js
n.application.sendActionRequest("produceAllHideoutUnits",{},...)
```

Erros: `errProduceAllHideoutUnitsNothingToProduce` ignorado;
`errRemovePremiumCurrencyNotEnough` → dialog com `hideout_produce_all_cost`.

## 14. unlockHideoutRoomSlot

```js
n.application.sendActionRequest("unlockHideoutRoomSlot",{level:this._level,slot:this._slot},...)
```

Sucesso → som + notificação com `getSlotUnlockTsEnd(level,slot)`. Erros:
`errUnlockRoomSlotNoIdleWorker` → instant-finish dialog ou erro;
`errRemoveGameCurrencyNotEnough`/Glue/Stone → dialogs;
`errUnlockRoomSlotInvalidStatus` → close+`sync()`.

## 15. checkUnlockHideoutRoomSlotFinished

```js
n.application.sendActionRequest("checkUnlockHideoutRoomSlotFinished",
 {level:this._level,slot:this._slot},...)
```

Erros: `errUnlockRoomSlotInvalidStatus` → `sync()`.

## 16. instantFinishHideoutSlotUnlock

```js
n.application.sendActionRequest("instantFinishHideoutSlotUnlock",{level:this._level,slot:this._slot},...)
```

Sucesso → cancela notificação `hideout_room_slot_unlocked`. Erros:
`errRemovePremiumCurrencyNotEnough` → dialog premium.

## 17–19. abortHideoutRoomStoring / Upgrading / SlotUnlocking

```js
sendActionRequest("abortHideoutRoomStoring",{hideoutRoomId:this._room.get_id()},...)
sendActionRequest("abortHideoutRoomUpgrading",{hideoutRoomId:this._room.get_id()},...)
sendActionRequest("abortHideoutRoomSlotUnlocking",{level:this._level,slot:this._slot},...)
```

Padrão idêntico: sucesso → `updateData`; erro específico
`errAbortStoring/Upgrading/UnlockingInvalidStatus` → `sync()`; resto `reportError(...,!1)`.

## 20. refreshHideoutOpponent

```js
n.application.sendActionRequest("refreshHideoutOpponent",
 {use_premium:this._btnRefreshHideoutEnemies.get_isPremium()},...)
// após errStartHideoutFightInvalidTarget: {use_premium:!1}
```

Grátis após `60 × hideout_opponent_refresh_free_cooldown` desde
`ts_last_opponent_refresh`. Erros: `errRemovePremiumCurrencyNotEnough` (custo
`hideout_opponent_refresh_premium_amount`), `errRefreshHideoutOpponentNoOpponents`
→ fecha, `errRefreshHideoutOpponentNotYetAllowed` → **ignorado silenciosamente**.

## 21. startHideoutFight

```js
n.application.sendActionRequest("startHideoutFight",{attacker_count:this._unitsToAttack,
 expacted_glue_reward:this._hideout.get_currentOpponent().get_reward().get_hideoutGlue()
  +this._hideout.get_currentOpponent().get_bonusReward().get_hideoutGlue(),
 expacted_stone_reward:...idem stone...,use_premium:this._btnAttack.get_showAsCost()},...)
```

Typos originais: `expacted_*`. Os valores esperados = preview + bonus — o
servidor compara e devolve `errStartHideoutFightRewardsChanged` se drift.
Fluxo de sucesso: `updateData` → delay 0,5 s → visualização da batalha (ou
direto aos resultados se setting `show_hideout_battles` off). Erros completos:

| Erro | Ação |
|---|---|
| `errStartHideoutFightRewardsChanged` | refaz `getHideoutOpponent {}` + dialog attack_rewards_changed |
| `errStartHideoutFightNotJetPossible` | fecha + dialog |
| `errStartHideoutFightNoOpponent` | apenas fecha |
| `errStartHideoutFightInvalidTarget` | `refreshHideoutOpponent {use_premium:!1}` |
| `errstartHideoutBattleInvalidAttackerCount` (**minúscula!**) | close+`sync()` |
| `errStartHideoutFightNotYetAllowed` | dialog attack_not_jet_allowed + `sync()` |
| `errRemovePremiumCurrencyNotEnough` | dialog premium (custo `hideout_battle_skip_cooldown_premium_amount`) |

## 22. startHideoutTutorialFight

```js
this.get_character().get_hasHideoutTutorialCompleted()||
 n.application.sendActionRequest("startHideoutTutorialFight",{attacker_count:this._unitsToAttack},...)
```

Mesmo fluxo de batalha. Erros: `errstartHideoutBattleInvalidAttackerCount` e
`errstartHideoutTutorialFightAlreadyFinished` (ambos minúsculas!) → close+`sync()`.

## 23. finishHideoutTutorial

```js
n.application.sendActionRequest("finishHideoutTutorial",{},...)
```

Enviado ao fechar o diálogo de conclusão do tutorial. Erros:
`errFinishHideoutTutorialAlreadyFinished` → apenas fecha.

## 24. collectHideoutFightRewards

```js
n.application.sendActionRequest("collectHideoutFightRewards",
 {hideout_battle_id:this._hideout.get_activeHideoutBattle().get_id()},...)
```

É AQUI que as rewards são creditadas (ver combate.md §7). Sucesso → set flag
tutorial `hideout_first_attack` (+ abre dialog fim-de-tutorial se aplicável) +
`showOwnHideout()`. Erro `errCollectHideoutFightRewardsInvalidBattle` →
**engolido silenciosamente**.

## 25. getHideoutBattleHistoryFights

```js
n.application.sendActionRequest("getHideoutBattleHistoryFights",{},...)
```

Sucesso → `set_missedAttacks(0)`; resposta: lista `hideout_history_battles`
(DOHideoutHistoryBattle).

## 26. getHideoutBattleHistoryFight

```js
n.application.sendActionRequest("getHideoutBattleHistoryFight",{id:this._hideoutBattle.get_id()},...)
```

Resposta: batalha COMPLETA (com rounds) sob `hideout_history_battle`; cliente
entra em modo revisão (`F.set_reviewMode(!0)`).

## 27. deleteHideoutHistoryBattles

```js
n.application.sendActionRequest("deleteHideoutHistoryBattles",{battle_ids:"id1;id2;"},...)
// ids concatenados com ";" (termina também em ";")
```

## 28. deleteAllHideoutHistoryBattles

```js
n.application.sendActionRequest("deleteAllHideoutHistoryBattles",{max_battle_id:a},...)
// a = maior id selecionado
```

Sucesso → limpa lista local + `set_missedDuels(0)`.

## 29. retrieveHideoutLeaderboard

Wrapper injeta sempre `server_id`:

```js
Xr.prototype.retrieveHideoutLeaderboard=function(a){...a.server_id=this._context.serverId;
 n.application.sendActionRequest("retrieveHideoutLeaderboard",a,...)}
```

Formas de params: `{points_sort:bool}`, `{character_name:str, points_sort}`,
`{rank:int, points_sort}` (scroll usa `rank±1`; pesquisa numérica → rank com
fallback `Number.MAX_SAFE_INTEGER`). Resposta: `leaderboard_hideouts[]`,
`max_hideouts`, `centered_rank`. Erros:
`errRetrieveHideoutLeaderboardInvalidCharacter` / `...InvalidRank` / `...Sync` → info dialogs.

## 30. getHideoutOpponent

```js
n.application.sendActionRequest("getHideoutOpponent",{},...)
```

Chamado quando o oponente atual está stale
(`last_refresh + wm.OPPOENT_REFRESH <= now` — typo OPPOENT) ou após
rewards-changed. Resposta chega como `hideout_opponent`. Erro tratado:
`errRefreshHideoutOpponentNoOpponents` → fecha (sim, este erro aparece aqui também!).

## 31. getHideoutBlacksmithItem

```js
n.application.sendActionRequest("getHideoutBlacksmithItem",{hideoutRoomId:this._room.get_id()},...)
```

Chamado quando `additionalValue1 > 0` mas o item não existe no inventário local
(p.ex. após reload). Resposta → merge + refresh.

## 32–33. refreshHideoutBlacksmithItem / refreshHideoutBlacksmithCost

```js
sendActionRequest("refreshHideoutBlacksmithItem",{hideoutRoomId:...,free:a},...)
sendActionRequest("refreshHideoutBlacksmithCost",{hideoutRoomId:...,free:a},...)
// free:!0 inicial quando additionalValue1<=0 (item); caso contrário isFree(ts_*_refresh)
```

Erros: `errRemovePremiumCurrencyNotEnough` → dialog com
`hideout_blacksmith_item_refresh_cost` / `hideout_blacksmith_cost_refresh_cost`.

## 34–35. refreshHideoutExchangeRate / refreshHideoutGymRate

```js
sendActionRequest("refreshHideoutExchangeRate",{hideoutRoomId:...,free:a},...)  // a=isToday(additionalValue1)?pago:grátis
sendActionRequest("refreshHideoutGymRate",{hideoutRoomId:...,free:a},...)
```

Erros: `errRemovePremiumCurrencyNotEnough` → dialogs
(`hideout_exchange_room_refresh_cost` / `hideout_gym_room_refresh_cost`).

## 36–37. refreshHideoutItemImprovementRarity / Slot

```js
ka.delayFunction(function(){sendActionRequest("refreshHideoutItemImprovementRarity",
 {hideoutRoomId:a._room.get_id(),free:!a._btnRefresh.get_showAsCost()},...)},.4)
sendActionRequest("refreshHideoutItemImprovementSlot",{hideoutRoomId:...,free:a},...)
// free:!0 automático quando improvement_slot é NaN/0
```

Erros específicos (além do premium): `errRefreshHideoutItemImprovementRarityNotFree`
→ botão passa a custo; `errRefreshHideoutItemImprovementSlotNotFree` → re-render
do botão de refresh.

## 38. buyHideoutBooster

```js
n.application.sendActionRequest("buyHideoutBooster",
 {id:this._confirmationBoosterButton.get_boosterId(),
  duration:this._confirmationBoosterButton.get_boosterDuration()},...)
```

Sucesso → som `booster_activate.mp3`. Erros: premium/game currency insuficiente
→ dialogs. (Também acessível pelo shop geral de boosters, case 8.)

## 39. getHideoutItemImprovementGuildBonus

```js
0==Cf._guildId||0<Cf._guildBonusLastUpdate&&Cf._guildBonusLastUpdate+1800>K.get_now()
 ? this.updateBonusInfo()                                    // cache 30 min
 : n.application.sendActionRequest("getHideoutItemImprovementGuildBonus",{},...)
```

Params: `{}`. Resposta: chave `guild_gem_production_level`; o bónus apresentado é
`round(bonus/(20×max(memberCount,5))×10000)/100` %.

---

## Mensagens SOCKET (ApplicationSocketHandler.handleMessage)

Tipos tratados: `clientRegistered`, `deleteGuildLogMessage`, `refreshInfoMessage`,
`requestClientInfo`, `syncFriendBar`, `syncGame`, `syncGameAndGuild`, `syncGuildLog`,
**`syncGuildSynergy`**, `syncGuildbookObjective`, **`syncHideoutAttack`**,
`syncSlotmachineChat`, `syncStreams`, `syncUserVoucher`, `syncWorldboss`.

### syncHideoutAttack

Payload ignorado; incrementa contador local → dispara HTTP `syncHideout`:

```js
case "syncHideoutAttack":...(m=q.get_current().get_character().get_hideout(),
 m.set_missedAttacks(m.get_missedAttacks()+1));break;
Ie.prototype.set_missedAttacks=function(a){a>this._missedAttacks&&-1<this._missedAttacks&&this.sync();...};
```

### syncGuildSynergy (com sync_hideout)

```js
e.hasData("sync_hideout")&&(m=character.hideout,null!=m&&m.forceSync());
```

### Ciclo de sessão

`clientRegistered` → `updateGameSession {connection_type:2}`; disconnect →
`connection_type:1`. `requestClientInfo` responde
`{game_id, server_id, user_id, session_id}`. `syncGame`/`syncGameAndGuild`/
`syncStreams` marcam `pendingSyncGame` → o próximo `syncGame` HTTP periódico leva
`force_sync:true`.

### Extra (workers)

Compra premium de worker não é ação hideout-nomeada: `Sd.handlePurchaseComplete`
recebe resposta de pagamento com chave `hideout` (contendo `max_worker_count`) e
dispara `HideoutMessage.workerBought`.

---

## Checklist final para o backend

Todas as ações que o servidor TEM de implementar (por ordem de importância):

**Núcleo:** `unlockHideout`, `syncHideout`, `buildHideoutRoom`,
`placeHideoutRoom`, `upgradeHideoutRoom`, `storeHideoutRoom`,
`startHideoutRoomProduction` (semântica por sala!), `collectHideoutRoomActivityResult`,
`checkHideoutRoomActivityFinished`, `instantFinishHideoutRoomActivity`,
`collectAllHideoutRoomResources`, `produceAllHideoutUnits`,
`unlockHideoutRoomSlot`, `checkUnlockHideoutRoomSlotFinished`,
`instantFinishHideoutSlotUnlock`, `abortHideoutRoomStoring/Upgrading/SlotUnlocking`.

**Combate/social:** `getHideoutOpponent`, `refreshHideoutOpponent`,
`startHideoutFight`, `startHideoutTutorialFight`, `finishHideoutTutorial`,
`collectHideoutFightRewards`, `getHideoutBattleHistoryFights`,
`getHideoutBattleHistoryFight`, `deleteHideoutHistoryBattles`,
`deleteAllHideoutHistoryBattles`, `retrieveHideoutLeaderboard`, `getHideout`
(visita a hideout alheio).

**Salas especiais:** `refreshHideoutExchangeRate`, `refreshHideoutGymRate`,
`getHideoutBlacksmithItem`, `refreshHideoutBlacksmithItem`,
`refreshHideoutBlacksmithCost`, `refreshHideoutItemImprovementSlot`,
`refreshHideoutItemImprovementRarity`, `getHideoutItemImprovementGuildBonus`.

**Extras:** `buyHideoutBooster` (+ master data `boosters`), workers via produtos
de pagamento.
