# Constantes do hideout no cliente (`HeroZero.min.js`)

> Fonte: análise do `server-laravel/public/js/HeroZero.min.js` (build 257).
> Todas as citações são código verbatim do ficheiro (minificado).

## 0. Mecanismo de leitura

As classes `C*` estendem `com.playata.framework.data.constants.ConstantsData`
(var minificada `v`) e leem os dados master via:

```js
v.getConstantsData=function(a){return T.current.constant(a)};
```

onde `T` = `com.playata.framework.data.constants.Constants`. **Se a chave não
existir no objeto constants o cliente lança exceção fatal:**

```js
T.prototype.constant=function(a){...throw new yc("Error in Constants! Trying to retrieve unknown constant! key="+a);};
```

Getters primitivos herdados do DataObject — devolvem default quando o campo
falta (sem erro):

```js
d.prototype.getInt=function(a,b){null==b&&(b=0);a=this.getData(a,b);return null==a?b:p.parseInt(p.string(a))}
d.prototype.getNumber=function(a,b){null==b&&(b=0);a=this.getData(a,b);return null==a?b:parseFloat(p.string(a))}
```

Sub-dados por nível (`levels`): `getSubData(key, parentId, id, classe)` lê
`constants[key][parentId]["levels"][id]`.

Cada classe declara estáticos `CONSTANTS_KEY` (chave no constants_json) e
`ID_NAME`:

```js
li.CONSTANTS_KEY="hideout_generator_upgrades";li.ID_NAME="generator_level";
pd.CONSTANTS_KEY="hideout_rooms";pd.ID_NAME="identifier";
lk.CONSTANTS_KEY="hideout_room_blacksmith_items";lk.ID_NAME="blacksmith_item_id";
Wm.CONSTANTS_KEY="hideout_room_upgrade";Wm.ID_NAME="building_level";
dl.CONSTANTS_KEY="hideout_expansion";dl.ID_NAME="expansion";
Rp.CONSTANTS_KEY="hideout_units";Rp.ID_NAME="identifier";
```

## 1. Chaves master hideout (lista completa)

| Chave no constants_json | Classe | ID |
|---|---|---|
| `hideout_rooms` | CHideoutRoom | `identifier` (string) |
| `hideout_units` | CHideoutUnit | `identifier` (string) |
| `hideout_expansion` | CHideoutSlot | `expansion` (int) |
| `hideout_room_upgrade` | CHideoutRoomUpgrade | `building_level` (int) |
| `hideout_generator_upgrades` | CHideoutAdditionalGeneratorUpgrade | `generator_level` (int) |
| `hideout_room_blacksmith_items` | CHideoutRoomBlacksmithItem | `blacksmith_item_id` (int) |

`CHideoutRoomLevel` e `CHideoutUnitLevel` **não têm chave própria** — são os
sub-objetos `levels` dentro de `hideout_rooms[*]` / `hideout_units[*]`.

Além disto há ~50 chaves escalares hideout em `CConstant`
(`com.playata.application.data.constants.CConstant`), todas via
`T.current.getInt/getString/getNumber("...")`:

**Durações/scalings**
- `hideout_store_duration_per_level` (int)
- `hideout_place_duration_per_level` (int)
- `hideout_build_duration_scaling` (number)
- `hideout_upgrade_duration_scaling` (number)
- `hideout_room_unlock_duration_scaling` (number)
- `hideout_min_till_max_resource_duration_scaling` (number)

**Unlock/batalha/oponente**
- `hideout_unlock_min_level` (int) — nível mínimo do personagem para abrir o hideout
- `hideout_battle_cooldown` (int)
- `hideout_battle_skip_cooldown_premium_amount` (int)
- `hideout_opponent_refresh_free_cooldown` (int)
- `hideout_opponent_refresh_premium_amount` (int)

**Primeiro inimigo/tutorial**
- `hideout_first_enemy_level`, `hideout_first_enemy_hideout_points`,
  `hideout_first_enemy_reward_hideout_glue`,
  `hideout_first_enemy_reward_hideout_stone`,
  `hideout_first_enemy_reward_hideout_points` (int)
- `hideout_tutorial_finished_reward_glue`,
  `hideout_tutorial_finished_reward_stone` (int)

**Skip premium (instant finish)** — 4 escalões + teto:
- `hideout_skip_short_time` / `hideout_skip_short_premium_amount` (amount é *number*)
- `hideout_skip_medium_time` / `hideout_skip_medium_premium_amount`
- `hideout_skip_long_time` / `hideout_skip_long_premium_amount`
- `hideout_skip_very_long_time` / `hideout_skip_very_long_premium_amount`
- `hideout_skip_max_premium_amount` (int)

**Refreshes de salas especiais / conveniência (custos premium)**
- `hideout_gym_room_refresh_cost`
- `hideout_blacksmith_cost_refresh_cost`
- `hideout_blacksmith_item_refresh_cost`
- `hideout_item_improvement_refresh_cost`
- `hideout_collect_all_cost`
- `hideout_produce_all_cost`

**Improvement slots** (identifiers de item que desbloqueiam slots extra):
- `hideout_add_improvement_slot_identifier` (string)
- `hideout_add_sidekick_improvement_slot_identifier` (string)

**Exchange room**
- `hideout_exchange_room_refresh_cost` (int)
- `hideout_exchange_room_stone_to_glue_ratio` (number)
- `hideout_exchange_room_glue_to_stone_ratio` (number)
- `hideout_exchange_room_max_resource_duration_start` (int)

**Salas de guilda**
- `hideout_guild_auto_join_room_duration` (int), `hideout_guild_auto_join_room_cost` (int)
- `hideout_guild_missile_room_duration` (int)
- `hideout_guild_medikit_room_duration_common/rare/epic/legendary` (int)
- `hideout_guild_weapon_oil_room_duration_common/rare/epic/legendary` (int)

## 2. Campos por classe

### CHideoutRoom (`pd`) — `hideout_rooms`, ID `identifier`

```js
pd.prototype.get_identifier=function(){return this.getString("identifier")};
pd.prototype.get_limit=function(){return this.getInt("limit")};                    // nº máx. de cópias; 0 = sala teaser (nunca construível)
pd.prototype.get_unlockWithMainbuilding=function(){return this.getIntVector("unlock_with_mainbuilding")}; // vetor
pd.prototype.get_size=function(){return this.getInt("size")};                      // largura no grid (em slots)
pd.prototype.get_type=function(){return this.getString("type")};
pd.prototype.get_index=function(){return this.getInt("index")};                    // ordem na lista de colocação
pd.prototype.getLevel=function(a){return this.getSubData("levels",this.get_identifier(),a,yx)}; // CHideoutRoomLevel
```

### CHideoutRoomLevel (`yx`) — sub-objeto `levels[N]` de cada sala

```js
get_level()                 -> int "level"
get_priceGold()             -> int "price_gold"
get_priceGlue()             -> int "price_glue"
get_priceStone()            -> int "price_stone"
get_buildTime()             -> int "build_time"
get_passivBonusAmount1()    -> int "passiv_bonus_amount_1"
get_passivBonusAmount2()    -> int "passiv_bonus_amount_2"
get_minTillMaxResource()    -> int "min_till_max_resource"
get_resourceProductionMax() -> getNumber "resource_production_max"   // único float dos room levels!
get_imageLevel()            -> int "image_level"
```

### CHideoutRoomUpgrade (`Wm`) — `hideout_room_upgrade`, ID `building_level`

```js
get_buildingLevel=function(){return this.getInt("building_level")};   // só serve de ID (sem outros usos)
get_characterMinLevel=function(){return this.getInt("character_min_level")};
```

Tabela global **nível-da-sala-alvo → nível mínimo do personagem**. É esta tabela
que valida upgrades (não a sala):

```js
// DialogHideoutRoomUpgradeBase.refresh
b=Wm.fromId(this._room.get_level()+1).get_characterMinLevel();
```

### CHideoutSlot (`dl`) — `hideout_expansion`, ID `expansion`

```js
get_expansion()  -> int (o próprio índice)
get_priceGold()  -> int "price_gold"
get_priceGlue()  -> int "price_glue"
get_priceStone() -> int "price_stone"
get_duration()   -> int "duration"
```

Índice = nº de slots já desbloqueados (`dl.fromId(unlockedCount+1)` para o
próximo). Ver §3.9.

### CHideoutUnit (`Rp`) — `hideout_units`, ID `identifier`

Identificadores usados no código: `"attacker"` e `"defender"`.

```js
Rp.prototype.get_identifier=function(){return this.getString("identifier")};
Rp.prototype.getLevel=function(a){return this.getSubData("levels",this.get_identifier(),a,PA)}; // CHideoutUnitLevel
```

### CHideoutUnitLevel (`PA`) — sub-objeto `levels[N]` de cada unidade

```js
get_level()                     -> int "level"
get_statStamina()               -> int "stat_stamina"
get_statStrength()              -> int "stat_strength"
get_statCriticalRating()        -> int "stat_critical_rating"
get_statDodgeRating()           -> int "stat_dodge_rating"
get_buildTime()                 -> getNumber "build_time"          // float!
get_destroyedGlueForDefender()  -> int "destroyed_glue_for_defender"
get_destroyedStoneForDefender() -> int "destroyed_stone_for_defender"
```

**Importante:** as stats das unidades e os `destroyed_*_for_defender` **não têm
consumidor nenhum no cliente** — o cliente só usa `build_time`. Stats e loot de
robós destruídos são contas do servidor (combate).

### CHideoutAdditionalGeneratorUpgrade (`li`) — `hideout_generator_upgrades`, ID `generator_level`

```js
get_generatorLevel() -> int (ID)
get_priceGold()      -> int
get_priceGlue()      -> int
get_priceStone()     -> int
get_buildTime()      -> int
get_bonusAmount()    -> int   // % adicional ao generator factor
```

### CHideoutRoomBlacksmithItem (`lk`) — `hideout_room_blacksmith_items`, ID `blacksmith_item_id`

```js
get_blacksmithItemId()   -> int (ID)
get_itemIdentifier()     -> string "item_identifier"  // lista separada por ";"
get_minBlacksmithLevel() -> int "min_blacksmith_level"
get_plusItemLevel()      -> int "plus_item_level"
get_chance()             -> int "chance"
```

## 3. Usos no cliente (regras de negócio)

### 3.1 Upgrade de sala exige nível de personagem

```js
// UiHideoutRoom.refreshUpgradableIcon — esconde ícone se falta nível
if(Wm.fromId(this._room.get_level()+1).get_characterMinLevel()>q.get_current().get_character().get_level())
  this._content.ui.upgradeAvailable.set_visible(!1);
```

Custos do próximo nível vêm de `CHideoutRoomLevel(level+1)`:
`price_gold/glue/stone`; gold passa por desconto do broker:
`tb.getReducedGameCurrencyValue(priceGold, hideout.get_currentBrokerLevel())`.

### 3.2 Unlock de salas pelo main building — `unlock_with_mainbuilding`

O vetor é indexado pela **contagem de cópias já existentes dessa sala
(colocadas + em store)**: a cópia N (0-based) exige `unlock_with_mainbuilding[N]`.

```js
tb.canRoomBeBuild=function(a,b,c){
 if(0==c.get_limit())return f.current.text("dialog/hideout/place_room/error_room_teaser");
 var e=b.getRoomCountWithIdentifier(a);
 if(e>=c.get_limit())return f.current.text("dialog/hideout/place_room/error_room_limit_reached");
 var m=0,d=b.getMainbuilding();null==d||b.isRoomInStore(d)||(m=d.get_level());
 c=c.get_unlockWithMainbuilding()[e];
 if(m<c)return f.current.text("dialog/hideout/place_room/error_room_needs_mainbuilding_level", c);
 if("guild_main_building"==a&&!q.get_current().get_character().get_hasGuild())
   return f.current.text("dialog/hideout/place_room/error_room_needs_guild");
 ...
```

Regras adicionais no mesmo método: as salas
`guild_auto_join_production | guild_missile_production | guild_medikit_production |
guild_weapon_oil_production | guild_competition_point_production` exigem guilda +
`guild_main_building` colocado + pontos de sinergia
(`gh.fromId(N).get_neededPoints()` com N ∈ {auto_join:1, missile:2, medikit:4,
weapon_oil:5, competition_point:6}).

`getRoomCountWithIdentifier` conta sobre TODAS as salas (incluindo em store),
sem filtrar status.

### 3.3 Grid físico: 12 pisos × 5 slots

```js
tb.MAX_HIDEOUT_LEVEL=0;tb.MAX_LEVELS=12;tb.MAX_SLOTS=5;
tb.getSlotIdFromLevelAndSlot=function(a,b){return a+"_"+b};
```

Estado dos slots no payload do servidor: chaves `room_slot_<level>_<slot>` no
objeto hideout — `-1` = bloqueado, `0` = vazio, `>0` = id da sala:

```js
Ie.prototype.getUnlockedRoomsCount=function(){for(var a=0,b=0,c=tb.MAX_LEVELS;b<c;)
 for(var e=b++,m=0,d=tb.MAX_SLOTS;m<d;){var D=m++;-1!=this.getInt("room_slot_"+tb.getSlotIdFromLevelAndSlot(e,D))&&++a}return a};
Ie.prototype.getRoomBySlot=function(a){a="room_slot_"+a;if(!this.hasData(a)||0==this.getInt(a))return null;...}
```

Largura visual: 200px por unidade de `size` (`Gf.get_roomWidth=200`,
`Gf.get_roomHeight=113`). Ao arrastar, a sala é clamped:
`_slot+_roomSize>=tb.MAX_SLOTS && (_slot=tb.MAX_SLOTS-_roomSize)`.

Detalhes de navegação entre pisos: botão "descer de piso" só aparece se o slot 4
do piso seguinte estiver desbloqueado (`isSlotUnlocked(level+1, 4)`); o botão de
escavar ("dig") um novo slot aparece junto ao último slot do piso ou a seguir a um
slot desbloqueado. Há um elevador (`hideout_elevator_shaft_part`) que liga pisos.

### 3.4 Nível do hideout ("expansion")

```js
Ie.prototype.get_expansion=function(){return this.get_currentLevel()/tb.get_getMaxHideoutLevel()};
tb.get_getMaxHideoutLevel=function(){
 if(0<tb.MAX_HIDEOUT_LEVEL)return tb.MAX_HIDEOUT_LEVEL;
 for(var a=0,b=pd.get_ids();a<b.length;){var c=b[a];++a;var e=pd.fromId(c);
  tb.MAX_HIDEOUT_LEVEL+=e.get_limit()*e.get_levelIds().length;   // soma limit×níveis de todas as salas
  "generator"==c&&gh.exists(3)&&(c=gh.fromId(3).get_guildSynergyUpgradeLevelIds(),
   tb.MAX_HIDEOUT_LEVEL+=Math.round(e.get_limit()*gh.fromId(3).getGuildSynergyUpgradeLevel(c[c.length-1]).get_value1()))}
 return tb.MAX_HIDEOUT_LEVEL};
```

Ou seja: `current_level` do hideout é a soma dos níveis de todas as salas; o
máximo teórico = Σ(limit×nº de níveis) por tipo de sala (+ bónus de sinergia do
generator). Usado em requisitos de candidatura a guilda
(`minApplyHideoutLevel > round(100*expansion)`).

### 3.5 Nível das unidades deriva da barracks

O bónus passivo 1 da sala `barracks` É o nível das unidades produzidas:

```js
Ie.prototype.get_currentUnitsLevel=function(){var a=1;
 0<this.get_currentBarracksLevel()&&
 (a=pd.fromId("barracks").getLevel(this.get_currentBarracksLevel()).get_passivBonusAmount1());return a};
```

Duração de produção de unidades (HideoutUtil):

```js
var e=1;0<c.get_maxBarracksLevel()&&(e=pd.fromId("barracks").getLevel(c.get_maxBarracksLevel()).get_passivBonusAmount1());
"attacker_production"==a.get_identifier()
 ? b=Rp.fromId("attacker").getLevel(e).get_buildTime()*a.get_maxResourceAmount()*60
 : "defender_production"... b=Rp.fromId("defender").getLevel(e).get_buildTime()*a.get_maxResourceAmount()*60
```

Quantidade máxima por produção limitada por `resource_production_max` do nível
da sala. No diálogo de unidades: duração =
`unitData.get_buildTime() * unitsToProduce * 60`.

### 3.6 Fatores multiplicativos (generator/brain/wall/trophy/broker)

```js
// generator — aplica-se às salas "affectedByGenerator"
Vf.prototype.get_currentGeneratorFactor=function(){var a=1;
 if(this.get_isAffectedByGenerator()&&0<this.get_currentGeneratorLevel()){
  a+=pd.fromId("generator").getLevel(this.get_currentGeneratorLevel()).get_passivBonusAmount1()/100;
  var b=this.getAdditionalGuildSynergyValue("affected_generator_level",0);
  0<b&&(a+=li.fromId(b).get_bonusAmount()/100)}return a};

// brain — só afeta xp_production
Vf.get_currentBrainFactor -> 1 + pd.fromId("brain").getLevel(...).passiv_bonus_amount_1/100

// wall — nível da parede nos perfis de oponente/histórico
Gx.get_wallLevel -> pd.fromId("wall").getLevel(wallBuildingLevel).passiv_bonus_amount_1
```

- **trophy_room**: `passiv_bonus_amount_1` entra como boost de recompensa nos goals.
- **broker**: reduz preços em gold — `tb.getReducedGameCurrencyValue(price,
  hideout.get_currentBrokerLevel())`, usado em todos os diálogos.
- **worker_home**: reduz tempos —
  `getWorkerReductionFactor = 1 - worker_home[level].passiv_bonus_amount_1/100`.
- UI mostra `effect_percentage = round(100*(resourceProductionMax-1))` etc.
  (`UiHideoutRoomEffect`/`UiHideoutRoomInfoLine`).

### 3.7 Upgrades adicionais de generator (via sinergia de guilda)

Gate no diálogo de upgrade — só existe caminho alternativo se NÃO houver próximo
nível normal de sala mas houver upgrade adicional disponível:

```js
pd.fromId(...).hasLevelId(room.get_level()+1)
  || li.exists(room.getAdditionalGuildSynergyValue("additional_generator_level",0)+1) ...
```

Custo/duração: `li.fromId(additional_generator_level+1)` → price_gold (com
desconto broker)/glue/stone, duração `60*buildTime*workerReductionFactor*scaling`.
Bónus: `li.bonusAmount/100` somado ao generator factor (ver §3.6).

### 3.8 Itens do blacksmith — filtro por `min_blacksmith_level`

```js
var c=0<b?b:a.get_level();  // nível efetivo (sala ou preview de upgrade)
for(var e=lk.get_ids(),...){
 if(!(lk.fromId(h).get_minBlacksmithLevel()>c)){
   // item_identifier.split(";") → identificadores disponíveis
 }
 if(!(lk.fromId(h).get_minBlacksmithLevel()<=this._room.get_level())){
   // identificadores ainda bloqueados no nível atual
 }}
```

Os getters `chance` e `plusItemLevel` não têm consumo client-side encontrado —
o roll do item/custo é feito no servidor (`refreshHideoutBlacksmithItem {hideoutRoomId, free}`).

### 3.9 Expansão de slots — custos e duração

```js
tb.getRoomUnlockDuration=function(){var a=q.get_current().get_character().get_hideout().getUnlockedRoomsCount();
 return p.parseInt(p.string(Math.max(Math.round(60*dl.fromId(a).get_duration()*x.get_hideout_room_unlock_duration_scaling()),1)))};

// DialogHideoutRoomSlotUnlockBase
b=a.getUnlockedRoomsCount();b=dl.fromId(b+1);
this._costGameCurrency=tb.getReducedGameCurrencyValue(b.get_priceGold(),a.get_currentBrokerLevel());
this._costGlue=b.get_priceGlue();this._costStone=b.get_priceStone();
```

### 3.10 Unlock do hideout

```js
// botão base
this._btnHideout.set_buttonEnabled(a.get_level()>= x.get_hideout_unlock_min_level());
// seta de tutorial na primeira visita
!q.get_current().get_character().get_hasHideoutFirstBuilding()
 && q.get_current().get_character().get_level()>=x.get_hideout_unlock_min_level()
 && this.showHideoutArrow();
```

Ao subir de nível até `hideout_unlock_min_level` o cliente faz reset da loja
(`billing.resetProducts()`).

### 3.11 Resumo das fórmulas de duração (com constantes)

| Operação | Fórmula |
|---|---|
| Build (colocar) | `60 × buildTime(nível) × workerReductionFactor × hideout_build_duration_scaling` |
| Upgrade | `60 × buildTime(nível+1) × workerReductionFactor × hideout_upgrade_duration_scaling` (mín. 1s) |
| Unlock slot | `60 × dl.duration × hideout_room_unlock_duration_scaling` |
| Produção manual (exchange/blacksmith) | `60 × min_till_max_resource` (blacksmith) / direto (exchange), × `hideout_min_till_max_resource_duration_scaling` |
| Store room | `hideout_store_duration_per_level × nível da sala` |
| Place room | `hideout_place_duration_per_level × nível da sala` |
| Instant finish premium | interpolação linear entre escalões `hideout_skip_{short,medium,long,very_long}_{time,premium_amount}`, tecto `hideout_skip_max_premium_amount` |

Todas as durações divididas por `currentGeneratorFactor` e
`currentGuildSynergyFactor` quando aplicável:

```js
b/=a.get_currentGeneratorFactor();b/=a.get_currentGuildSynergyFactor();
```

## 4. Notas para o servidor

- `resource_production_max` é lido como float (`getNumber`) — os restantes campos
  numéricos dos room levels são ints.
- O cliente lança erro fatal se faltar qualquer uma das 6 chaves master; campos
  individuais em falta devolvem 0/"" silenciosamente (fallbacks).
- As stats de unidade (`stat_stamina/strength/critical_rating/dodge_rating`) e
  `destroyed_*_for_defender` só interessam ao servidor (combate/loot).
- `chance`/`plus_item_level` do blacksmith: roll no servidor; cliente ignora.
