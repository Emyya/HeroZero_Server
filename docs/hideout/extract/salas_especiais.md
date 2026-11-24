# Salas especiais e extras do hideout no cliente (`HeroZero.min.js`)

> Fonte: análise do `server-laravel/public/js/HeroZero.min.js` (build 257).
> Citações verbatim. Complementa `modelo_dados.md` (campos) e `constantes.md`
> (valores de config).

## 0. Notas transversais

- Pedidos: `n.application.sendActionRequest(<action>, <params>, handler)`; resposta
  merge via updateData; estado dessincronizado → fallback `syncHideout {}`.
- Valores adicionais da sala vivem em `additional_value_2` (**string JSON**):

```js
Vf.prototype.getAdditionalValue=function(a,b){if(""==this.get_additionalValue2())return b;
 var c=new d(zf.decode(this.get_additionalValue2()));
 return null!=c.get_rawData()&&c.hasData(a,!0)?c.getData(a):b};
```

- Refresh grátis 1×/dia: comparação por timestamp em `additional_value_1` ou chave
  dedicada (`ts_*_refresh`) contra `K.get_serverDateTime().isToday(ts)`.
- Erros comuns: `errRemovePremiumCurrencyNotEnough` / `errRemoveGameCurrencyNotEnough` /
  `errRemoveGlueNotEnough` / `errRemoveStoneNotEnough` /
  `errRemoveOpticalChangeResourceNotEnough`; família
  `errStartProductionPendingActivity*` → todos fazem `syncHideout`.

## 1. Exchange room (`exchange_room`) — trocar stone↔glue

Pedidos:
- `refreshHideoutExchangeRate {hideoutRoomId, free}` — sorteia nova taxa;
  `free=true` se nunca refrescou hoje (`additional_value_1` = ts último refresh).
- Produção: `startHideoutRoomProduction {hideoutRoomId, productionCount:1}`.

Chaves lidas de `additional_value_2`: **`is_zeronit`** (0/1), **`duration`**
(a duração real é `60×duration`), **`resource_duration`**, **`exchange_rate`**.
(`additional_value_1` = timestamp do último refresh.)

Cálculo mostrado ao jogador:

```js
Em.prototype.get_exchangeRoomResultAmount=function(){
 var a=this._room.getAdditionalValue("is_zeronit"),
     b=this._room.getAdditionalValue("resource_duration",0);
 0==b&&(b=x.get_hideout_exchange_room_max_resource_duration_start());
 a=a?pd.fromId("stone_production").getLevel(level):pd.fromId("glue_production").getLevel(level);
 a=a.get_resourceProductionMax()/a.get_minTillMaxResource();
 return parseInt(b*a)};
Em.prototype.get_exchangeRoomCostAmount=function(){
 var a=this._room.getAdditionalValue("is_zeronit"),
     b=this._room.getAdditionalValue("exchange_rate"),c=this.get_exchangeRoomResultAmount();
 a=a?x.get_hideout_exchange_room_glue_to_stone_ratio():x.get_hideout_exchange_room_stone_to_glue_ratio();
 return Math.round(a/b*c)};
```

- **Normal** (`is_zeronit=0`): paga **stone**, recebe **glue**, taxa base
  `hideout_exchange_room_stone_to_glue_ratio`.
- **Zeronit** (`is_zeronit=1`): paga **glue**, recebe **stone** ("zeronit" é o nome
  interno do recurso stone), taxa base `hideout_exchange_room_glue_to_stone_ratio`.
  Validação invertida no cliente:
  `b?currentResourceGlue>=custo... : currentResourceStone>=custo...`.
- Resultado = `(resourceProductionMax/minTillMaxResource)` da sala de produção
  correspondente ao nível da exchange × `resource_duration`.
- Custo do refresh pago: premium `hideout_exchange_room_refresh_cost`.

## 2. Gym (`gym`) — comprar stats com desconto

Pedidos:
- `refreshHideoutGymRate {hideoutRoomId, free}` — sorteia stat + duração; grátis 1×/dia.
- `startHideoutRoomProduction {hideoutRoomId, productionCount:1}` — treina.

Chaves lidas de `additional_value_2`: **`stat_type`** (1..4), **`duration`**
(segundos, usado direto). `additional_value_1` = ts último refresh.

Recompensa: 1 ponto comprado do stat sorteado:

```js
switch(stat_type){case 1:new ea(27,1);case 2:new ea(26,1);case 3:new ea(28,1);case 4:new ea(29,1)}
// 27=stamina, 26=strength, 28=critical_rating, 29=dodge_rating
```

Custo em gold (com desconto do bónus passivo da sala):

```js
this._neededCoins=Q.calcNeededGameCurrency(statBought_do_stat);
this._neededCoins=Math.round(this._neededCoins*(1-this._room.passivBonusAmount1(level)/100));
Q.calcNeededGameCurrency=function(a){var b=x.get_cost_stat_base(),c=x.get_cost_stat_scale(),
 e=x.get_cost_stat_base_scale(),m=x.get_cost_stat_base_exp();return Math.round(b+c*Math.pow(e*a,m))}
```

Usa os campos do personagem `stat_bought_stamina/strength/critical_rating/dodge_rating`.
Erro: `errRemoveGameCurrencyNotEnough`. Refresh pago: premium
`hideout_gym_room_refresh_cost`.

## 3. Blacksmith (`blacksmith`) — forjar item melhorado

Pedidos (todos com `{hideoutRoomId}`):
- `getHideoutBlacksmithItem {hideoutRoomId}` — busca o item atual quando
  `additional_value_1 > 0` mas o item não está no inventário local:

```js
0>=this._room.get_additionalValue1()
 ? sendActionRequest("refreshHideoutBlacksmithItem",{...,free:!0})
 : ((a=this.get_character().getItemById(this._room.get_additionalValue1()))==null
    ? sendActionRequest("getHideoutBlacksmithItem",{...}) : ...)
```

- `refreshHideoutBlacksmithItem {hideoutRoomId, free}` — novo item; grátis se
  `ts_item_refresh` não é hoje.
- `refreshHideoutBlacksmithCost {hideoutRoomId, free}` — novo custo; grátis se
  `ts_cost_refresh` não é hoje.
- Forjar: `startHideoutRoomProduction {hideoutRoomId, productionCount:1}`.

Chaves de `additional_value_2`: **`cost_type`** (1=gold, 2=glue, 3=stone),
**`cost_amount`**, `ts_cost_refresh`, `ts_item_refresh`.
`additional_value_1` = id do item disponível (procura-se no inventário).

Duração da forja: `60 × min_till_max_resource` da tabela `hideout_rooms` ao nível
atual. Itens possíveis filtrados pela master data
`hideout_room_blacksmith_items` por `min_blacksmith_level ≤ nível efetivo`
(item_identifier é lista separada por ";"). Refreshes pagos: premium
`hideout_blacksmith_cost_refresh_cost` / `hideout_blacksmith_item_refresh_cost`.

## 4. Item improvement (`gem_production`)

Fluxo em 2 diálogos: slot primeiro, raridade depois.

### 4a. Slot (`DialogHideoutSelectItemImprovementSlot`)

- Automático se não há slot: `refreshHideoutItemImprovementSlot {hideoutRoomId, free:!0}`;
  manual conforme `ts_slot_refresh` (grátis 1×/dia). Pago = premium
  `hideout_item_improvement_refresh_cost`.
- Mapeamento de slots normais: 1=mask, 2=cape, 3=suit, 4=belt, 5=boots, 6=weapon,
  7=gadget (mostram o improvement item épico correspondente).
- **Slots especiais**: `13` = "add improvement slot"
  (`hideout_add_improvement_slot_identifier` = `"special_add_improvement_slot"`),
  `14` = "add sidekick improvement slot". Estes é que disparam produção:

```js
13==a||14==a?sendActionRequest("startHideoutRoomProduction",{...,productionCount:1},...)
            :(abrir diálogo de raridade)
```

- Duração mostrada: `floor(item_improvement_duration/3600%24)` horas (=12h com
  `item_improvement_duration=43200`).
- Erro: `errRefreshHideoutItemImprovementSlotNotFree` → re-render botão.

### 4b. Raridade (`DialogHideoutSelectItemImprovementRarity`) — o diálogo do gem_production

- Bónus de guilda: `getHideoutItemImprovementGuildBonus {}` **sem params**, cache
  client-side de 1800s; resposta traz campo **`guild_gem_production_level`**:

```js
Cf.get_guildBonus=function(){return guild!=null?
 Math.round(Cf._guildBonus/(20*Math.max(guild.memberCount,5))*1E4)/100:0}
```

- Sortear as 3 opções: `refreshHideoutItemImprovementRarity {hideoutRoomId, free}`;
  grátis conforme `ts_rarity_refresh`. Erro específico:
  `errRefreshHideoutItemImprovementRarityNotFree`.
- As 3 opções vêm de `additional_value_2` chave **`improvement_rarity`** (objeto
  com chaves "0","1","2"; cada uma `{quality, stat_strength, stat_stamina,
  stat_critical_rating, stat_dodge_rating}`).
- **⚠️ Produzir escolhe a raridade pelo índice enviado em `productionCount`:**

```js
Cf.prototype.onClickProduce=function(a){a=-1;this._content.rarity0.selected.get_visible()?a=0:
 this._content.rarity1.selected.get_visible()?a=1:this._content.rarity2.selected.get_visible()&&(a=2);
 n.application.sendActionRequest("startHideoutRoomProduction",{hideoutRoomId:this._room.get_id(),productionCount:a},...)}
```

- Máximo teórico mostrado (validação para o servidor):

```js
Math.round(character.level*(room.passivBonusAmount1(level)/100+Cf.get_guildBonus()/100+1)/2
           *x.get_item_improvement_epic_stat_increase())
// item_improvement_epic_stat_increase=1.6
```

## 5. Salas de guilda + sinergia

Classes: `DialogHideoutProduceGuildAutoJoinBase`, `...MedikitBase`, `...MissileBase`,
`...WeaponOilBase`. Todas enviam apenas
`startHideoutRoomProduction {hideoutRoomId, productionCount:X}` — muda o significado:

| Sala | productionCount | Custo | Duração |
|---|---|---|---|
| `guild_auto_join_production` | nº de auto joins (slider) | optical-change currency: `round(hideout_guild_auto_join_room_cost×(1-bónus_sala%))` cada | `hideout_guild_auto_join_room_duration × qtd ÷ generatorFactor ÷ synergyFactor` |
| `guild_missile_production` | nº de mísseis (slider até min(resourceProductionMax, missilesMax−missiles)) | nenhum | `hideout_guild_missile_room_duration × qtd ÷ generatorFactor` (sem fator sinergia) |
| `guild_medikit_production` | **id do ITEM escolhido** do inventário | consome o item (sem recursos) | por raridade do item: common/rare/epic/legendary ÷ generatorFactor |
| `guild_weapon_oil_production` | **id do ITEM escolhido** | consome o item | idem com durations de weapon oil |

```js
// medikit/oil — duração por raridade
1==quality?common():2==quality?rare():3==quality?epic():4==quality&&legendary(); a/=generatorFactor
```

Limites: guild inventory (`medikits<medikitsMax`, `weaponOil<weaponOilMax`);
recompensas: auto join tipo 53, missile tipo 38, medikit tipo 55, weapon oil tipo 56.
Erro de moeda especial: `errRemoveOpticalChangeResourceNotEnough` → `syncOpticalChanges {}`.

### Pré-requisitos para construir salas de guilda (tb.canRoomBeBuild)

```js
m=b.getRoomsByType("guild_main_building");
if(0==m.length)return "...error_room_needs_guild_main_building";
if(-1==b.getRoomSlotAndLevel(m[0]).level)return "...error_room_needs_guild_main_building_placed";
switch(id){auto_join→synergy 1; competition_point→6; medikit→4; missile→2; weapon_oil→5}
if(guildSynergy.activeEnergy < gh.fromId(n).neededPoints) return "...error_room_needs_guild_synergy_energy";
if(!characterQualifiesForUpgrades(charId)) return "...error_character_not_qualified_for_guild_synergy_upgrades"
```

Erros de produção das salas de guilda → fecham diálogo + `syncHideout`:
`errStartHideoutRoomProductionInvalidProductionCount`,
`errStartHideoutRoomProductionGuildSynergyNotActive`,
`errStartHideoutRoomProductionNoGuildMainBuildingPlaced`.

Fator de sinergia (só auto_join e competition_point):

```js
Vf.prototype.get_currentGuildSynergyFactor=function(){var a=1;
 if("guild_auto_join_production"==id||"guild_competition_point_production"==id){
  var b=getAdditionalGuildSynergyValue("qualified",!1),c=getAdditionalGuildSynergyValue("active",!1),
      e=getAdditionalGuildSynergyValue("upgrade_level",0);
  b&&c&&0<e&&(b=id=="guild_auto_join..."?1:6,b=gh.fromId(b),a+=b.getGuildSynergyUpgradeLevel(e).get_value1())}return a}
```

## 6. Workers premium

NÃO são compras por premium currency — são produtos de pagamento real:

```js
St.open=function(){Sd.requestProduct("hideout_worker",function(a){...showDialog(new St(a))})};      // worker permanente
Rt.open=function(){Sd.requestProduct("hideout_time_worker",function(a){...showDialog(new Rt(a))})}; // aluguer temporário
```

Estado no cliente: campo `ts_time_worker_expires` do hideout.

```js
Ie.prototype.get_hasTimerWorker=function(){return this.get_tsTimeWorkerExpires()>=K.get_now()};
// enquanto há registo expirado (0<ts_time_worker_expires && !hasTimerWorker):
// idle = min(base_idle, max); max = base_max - 1   (desconta o worker que já expirou)
Ie.prototype.get_premiumWorkerBought=function(){...max==2?true:(hasTimer?max==3:false)}
```

Correção da interpretação: sem aluguer, o cliente considera o robô permanente
comprado quando `max_worker_count=2`; com aluguer ativo, quando o máximo é 3.
A base é um robô; a compra permanente dá o segundo. No servidor local,
`hideout_worker` é uma compra gratuita, com recibo atómico e sem aluguer novo.
Ver [compras locais](../../compras/overview.md).
Evento socket/broadcast associado: `"HideoutMessage.workerBought"` (bots reagem).

## 7. Boosters de hideout

Identificadores: **`booster_hideout1`** (small), **`booster_hideout2`** (big),
tipo 8. Pedido:

```js
sendActionRequest("buyHideoutBooster",{id:boosterId,duration:boosterDuration},...)
```

Efeito: acelera o enchimento das salas `glue_production`/`stone_production`;
magnitude = campo `amount` da master data `boosters`:

```js
Y.prototype.get_activeHideoutBoosterAmount=function(){...Xd.fromId(id).get_amount()}
Y.prototype.get_hideoutBoosterActive=function(){return""!=$a.get_activeHideoutBoosterId()?
 -1!=ts_active_hideout_boost_expires()?ts>now:!0:!1};
```

Campos do personagem: `active_hideout_booster_id` (string), 
`ts_active_hideout_boost_expires` (int; **-1 = permanente**). O cliente limpa
localmente boosters expirados.

Preços: sub-tabela `duration` por booster (`boosterDuration, premium(bool), cost`).
Se `premium=false` paga-se em gold com curva `Q.boosterCost(...)` (constantes
`booster_costs_coins_step`, `coins_per_time_base/scale/level_scale/level_exp`);
se `premium=true` paga `cost` em premium. Comprar booster diferente do ativo pede
confirmação ("overwrite"). ⚠️ A master data `boosters` está **null** neste backend
(`server-laravel/data/initGame.json`) — tem de ser fornecida no boot.

## 8. Unlock do hideout

- Pedido automático na primeira abertura do painel sem hideout:
  `unlockHideout {}` (guard `_unlockHideoutSend` para não repetir).
  Resposta devolve os dados do hideout criado (merge).
- Requisito de nível só no gating do botão HUD:
  `btnHideout.set_buttonEnabled(level >= hideout_unlock_min_level)` (valor 8);
  tooltip bloqueado; reset de produtos da loja ao atingir o nível.
- Sem custo inicial cobrado no cliente.
- `hasHideout = hideout_id > 0` (campo do personagem).
- Recompensas do tutorial (config): glue=200, stone=400; primeiro inimigo:
  level=7, pontos=10, rewards: points=1/glue=25/stone=50.

## 9. Slots (expansão)

Master `hideout_expansion` indexada pelo **nº de slots já desbloqueados**;
custos gold (com desconto broker)/glue/stone; duração
`max(round(60×duration×workerReductionFactor×hideout_room_unlock_duration_scaling),1)`.

```js
sendActionRequest("unlockHideoutRoomSlot",{level:this._level,slot:this._slot},...)          // {level, slot}
sendActionRequest("instantFinishHideoutSlotUnlock",{level:this._level,slot:this._slot},...) // {level, slot}
```

Codificação do valor `room_slot_L_S` durante unlock: valor negativo `< -1` =
desbloqueio em curso com `-ts_fim` embutido (ver modelo_dados §5).

Erros: `errUnlockRoomSlotNoIdleWorker` → tenta dialog de instant finish ou erro;
`errUnlockRoomSlotInvalidStatus` → close+sync; custos insuficientes → dialogs.
Sucesso agenda notificação local `hideout_room_slot_unlocked`.

Curva de instant finish partilhada (`tb.getInstantFinishPremiumAmount`) — valores
de config observados: short ≤60s→0.2; medium ≤3600s→4; long ≤86400s→52;
very_long ≤604800s→200; cap `hideout_skip_max_premium_amount`=499 (interpolação linear).

A atividade atual do worker (para saber se pode instant-finish) considera salas
E slots: `getCurrentWorkerActivity()` → `{room, level, slot}`.

## 10. Bónus passivos das salas nos stats do personagem

**kybernetic_improvement → +stats** (aplicado sobre os stats BASE, arredondado
por cima; soma TODAS as salas colocadas fora da store e não em construção):

```js
Y.hideoutRoomBaseStatBonus=function(stat){if(!hasHideout)return 0;
 for(b=getRoomsByType("kybernetic_improvement"),c=0,...)
  if(!isRoomInStore(room)&&!room.get_isBuilding()){c+=room.passivBonusAmount1(level)/100}
 switch(stat){case 1:return Math.ceil(get_statBaseStamina()*c); ...}}
```

**trophy_room → +artefactos de liga de duelos**: soma `passiv_bonus_amount_1`
das trophy rooms (fora da store).

**brain → acelera xp_production** (fator somatório, ver §4.2 modelo_dados).
**worker_home → reduz durações** build/place/store/upgrade.
**broker → reduz preços gold** (campo `current_broker_level`).
**robot_storage → +capacidade de unidades** (campo `current_robot_storage_level`,
somado POR CIMA dos max crus — ver modelo_dados §6.4).
**barracks → nível das unidades** (campo `current_barracks_level`).
**generator → fator de produção** (+ upgrade adicional de sinergia).

**personal_arena, scout_center, sports_bar**: **nenhuma aplicação client-side
encontrada** — só sons de conclusão, ícones de tooltip e textos de efeito vindos
da localização `hideout/room/<identifier>/effect_passiv_1|2`. Os efeitos destas
três salas (dodge/crit, quest coin, training speed) são resolvidos no servidor.

## Cheat-sheet final

| Funcionalidade | Action | Params |
|---|---|---|
| Exchange refresh | `refreshHideoutExchangeRate` | `{hideoutRoomId, free}` |
| Gym refresh | `refreshHideoutGymRate` | `{hideoutRoomId, free}` |
| Blacksmith item | `refreshHideoutBlacksmithItem` / `getHideoutBlacksmithItem` | `{hideoutRoomId[, free]}` |
| Blacksmith custo | `refreshHideoutBlacksmithCost` | `{hideoutRoomId, free}` |
| Improvement slot | `refreshHideoutItemImprovementSlot` | `{hideoutRoomId, free}` |
| Improvement raridade | `refreshHideoutItemImprovementRarity` | `{hideoutRoomId, free}` |
| Bónus guilda improvement | `getHideoutItemImprovementGuildBonus` | `{}` → resposta `guild_gem_production_level` |
| Guild rooms | `startHideoutRoomProduction` | `{hideoutRoomId, productionCount: quantidade \| item_id}` |
| Improvement produzir | `startHideoutRoomProduction` | `{hideoutRoomId, productionCount: 0..2}` (índice da raridade!) |
| Workers | produtos de pagamento | `"hideout_worker"` / `"hideout_time_worker"` |
| Booster | `buyHideoutBooster` | `{id:"booster_hideout1"\|"booster_hideout2", duration}` |
| Unlock | `unlockHideout` | `{}` |
| Slots | `unlockHideoutRoomSlot` / `instantFinishHideoutSlotUnlock` | `{level, slot}` |

Semântica de `productionCount` por sala (resumo crítico para o backend):
attacker/defender/gem/exchange/gym/blacksmith = 1; gem_production (raridade) =
índice 0..2; guild_auto_join/missile = quantidade; guild_medikit/weapon_oil =
id do item consumido.

## Atualização da implementação local — energético (BUG-000154)

A descrição histórica acima da master data nula não representa o estado atual:
os energéticos locais são fornecidos por Live. Compra e cupões agora aplicam a
janela de +25/+50% à produção de cola/pedra, com cálculo correspondente no bundle.
Regras, migração e testes: [energeticos.md](../energeticos.md).
