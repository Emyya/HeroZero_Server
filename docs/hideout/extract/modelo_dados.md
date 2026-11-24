# Modelo de dados do hideout no cliente (`HeroZero.min.js`)

> Fonte: análise do `server-laravel/public/js/HeroZero.min.js` (build 257).
> Todas as citações são código verbatim. Este documento descreve os campos que o
> servidor TEM de enviar e as fórmulas que o cliente recalcula localmente.

## 0. Mapeamento de classes (nomes minificados)

| Classe completa | Minificado | Nota |
|---|---|---|
| `dataobject.DOHideout` | `dh` | payload `hideout` |
| `dataobject.DOHideoutBattle` | `pp` | payload `hideout_battle` |
| `dataobject.DOHideoutHistoryBattle` | `qp` | item de `hideout_history_battles` |
| `dataobject.DOHideoutOpponentData` | `c6` | payload `hideout_opponent` |
| `dataobject.DOHideoutOpponent` | `Pna` | campo `opponent` (sem getters próprios) |
| `dataobject.DOHideoutRoom` | `hl` | payload `hideout_room(s)` |
| `data.hideout.Hideout` | `Ie` | estende `DOHideout`; lógica |
| `data.hideout.HideoutBattle` | `dv` | estende `DOHideoutBattle` |
| `data.hideout.HideoutBattleCharacter` | `XA` | perfil de robô |
| `data.hideout.HideoutBattleRound` | `m6` | ronda de batalha |
| `data.hideout.HideoutHistoryBattle` | `YA` | estende DO |
| `data.hideout.HideoutOpponent` | `Gx` | estende `Character` |
| `data.hideout.HideoutRoom` | `Vf` | estende `DOHideoutRoom`; fórmulas |
| `data.hideout.HideoutRoomSlotLevel` | `$Oa` | par `{level, slot}` |
| `data.hideout.HideoutUtil` | `tb` | utilitários estáticos |
| `data.hideout.HideoutWorkerActivity` | `RQa` | `{room, level, slot}` |
| `data.hideout.LeaderboardHideout` | `Jt` | linha do leaderboard |

## 1. Envelope das respostas — chaves lidas pelo cliente

A classe de resposta (`AppRequestResponseData`) expõe:

```js
ta.prototype.get_hideout=function(){return this.hasData("hideout",!0)?new dh(this.getData("hideout")):null}
ta.prototype.get_hideout_rooms=function(){...new od(this.getArray("hideout_rooms"),hl)...}
ta.prototype.get_hideout_room=function(){return this.hasData("hideout_room",!0)?new hl(this.getData("hideout_room")):null}
ta.prototype.get_hideout_battle=function(){...new pp(this.getData("hideout_battle"))}
ta.prototype.get_hideout_opponent=function(){...new c6(this.getData("hideout_opponent"))}
ta.prototype.get_missed_hideout_attacks=function(){return this.getInt("missed_hideout_attacks")}
ta.prototype.get_requested_hideout=function(){...}          // hideout alheio (visita)
ta.prototype.get_requested_hideout_rooms=function(){...}
ta.prototype.get_requested_hideout_optical_changes=function(){...}
ta.prototype.get_hideout_history_battles=function(){...}
ta.prototype.get_leaderboard_hideouts=function(){...new od(this.getArray("leaderboard_hideouts"),$f)}
ta.prototype.get_max_hideouts=function(){return this.getInt("max_hideouts")}
```

Processamento global (`updateData`) — merge incremental:

```js
c.hasData("hideout",!0)&&...refreshHideout(c.get_hideout());
c.hasData("hideout_room",!0)&&...get_hideout().refreshRoom(c.get_hideout_room());
c.hasData("hideout_rooms",!0)&&...refreshRooms(c.get_hideout_rooms());
c.hasData("hideout_opponent",!0)&&...refreshOpponent(c.get_hideout_opponent());
c.hasData("hideout_battle",!0)&&...refreshBattle(c.get_hideout_battle());
c.hasData("missed_hideout_attacks",!0)&&...set_missedAttacks(c.get_missed_hideout_attacks());
c.hasData("guild_gem_production_level",!0)&&...Cf.set_guildGemProductionLevel(...)
```

Visita a outro hideout (`Hideout.fromData`) — chaves `requested_*`:

```js
Ie.fromData=function(a){var b=new Ie(a.get_requested_hideout());
a.hasData("requested_hideout_rooms")&&b.refreshRooms(a.get_requested_hideout_rooms());
a.hasData("requested_hideout_optical_changes")&&b.update(...);return b};
```

**Conclusão prática:** todas as ações podem devolver só os objetos/campos
alterados; o cliente faz merge. Ver `respostas_capturadas.md` para exemplos reais.

## 2. Payload `hideout` (`DOHideout`) — campos

| Campo JSON | Getter | Tipo |
|---|---|---|
| `id` | get_id | int |
| `hideout_points` | get_hideoutPoints | int |
| `current_level` | get_currentLevel | int |
| `idle_worker_count` | get_idleWorkerCount | int |
| `max_worker_count` | get_maxWorkerCount | int |
| `ts_time_worker_expires` | get_tsTimeWorkerExpires | int |
| `current_resource_glue` / `max_resource_glue` | — | int |
| `current_resource_stone` / `max_resource_stone` | — | int |
| `current_attacker_units` / `max_attacker_units` | — | int |
| `current_defender_units` / `max_defender_units` | — | int |
| `current_opponent_id` | get_currentOpponentId | int |
| `current_opponent_server_id` | — | string |
| `current_opponent_chest_reward` | — | bool |
| `ts_last_opponent_refresh` | — | int |
| `active_battle_id` | get_activeBattleId | int |
| `ts_last_lost_attack` | — | int |
| `current_worker_level` | — | int |
| `current_barracks_level` / `max_barracks_level` | — | int |
| `current_gem_production_level` | — | int |
| `current_broker_level` | — | int |
| `current_robot_storage_level` | — | int |
| `room_slot_<level>_<slot>` (12×5) | via getRoomBySlot/isSlotUnlocked | int — ver §5 |
| `server_id`, `owner_name`, `owner_gender`, `optical_changes` | só em hideouts alheios | — |

## 3. Payload de sala (`DOHideoutRoom`)

```js
hl.prototype.get_id=function(){return this.getInt("id")}
hl.prototype.get_hideoutId=function(){return this.getInt("hideout_id")}
hl.prototype.get_identifier=function(){return this.getString("identifier")}
hl.prototype.get_status=function(){return this.getInt("status")}
hl.prototype.get_level=function(){return this.getInt("level")}
hl.prototype.get_currentResourceAmount=function(){return this.getInt("current_resource_amount")}
hl.prototype.get_maxResourceAmount=function(){...}              // valor base do servidor
hl.prototype.get_tsLastResourceChange=function(){...}
hl.prototype.get_tsActivityEnd=function(){...}
hl.prototype.get_currentGeneratorLevel=function(){return this.getInt("current_generator_level")}
hl.prototype.get_additionalValue1=function(){return this.getInt("additional_value_1")}
hl.prototype.get_additionalValue2=function(){return this.getString("additional_value_2")}   // STRING JSON
hl.prototype.get_guildSynergyAdditionalValue=function(){...}    // STRING JSON
```

- `additional_value_2` — mini-JSON lido com `zf.decode`; chave usada: **`duration`**
  (exchange_room, gym, guild_medikit/weapon_oil_production).
- `guild_synergy_additional_value` — mini-JSON com chaves:
  **`qualified`, `active`, `upgrade_level`, `affected_generator_level`,
  `is_synergy_upgrading`, `additional_generator_level`**.

### Status da sala (enum)

```js
Vf.prototype.get_isIdle=function(){return 1==this.get_status()}      // 1 idle
Vf.prototype.get_isBuilding=function(){return 2==this.get_status()}  // 2 building
Vf.prototype.get_isUpgrading=function(){return 3==this.get_status()} // 3 upgrading
Vf.prototype.get_isPlacing=function(){return 4==this.get_status()}   // 4 placing
Vf.prototype.get_isStoring=function(){return 5==this.get_status()}   // 5 storing
Vf.prototype.get_isProducing=function(){return 6==this.get_status()} // 6 producing
```

### Categorias por identifier

```js
get_isAutoProductionRoom -> main_building, glue_production, stone_production,
                            xp_production, guild_competition_point_production
get_isManuallyProductionRoom -> attacker_production, defender_production, gem_production,
                            exchange_room, gym, blacksmith, guild_auto_join_production,
                            guild_missile_production, guild_medikit_production,
                            guild_weapon_oil_production
get_isGuildSynergyRoom -> os 4 guild_* acima + guild_competition_point_production
get_isAffectedByGenerator -> isAutoProductionRoom() || isGuildSynergyRoom()
```

Estado de sala de sinergia (para UI):

```js
get_guildSynergyRoomStatus -> 1=activo, 2=sem guilda, 3=sem sala guild_main_building,
                              4=não colocada, 5=energia insuficiente
```

Outros identifiers usados no código: `generator`, `brain`, `barracks`, `wall`,
`worker_home`, `broker`, `robot_storage`, `storage`, `guild_main_building`.

## 4. Fórmulas do lado do cliente (HideoutRoom)

### 4.1 Produção acumulada (o que aparece na barra)

```js
Vf.prototype.get_currentCalculatedResourceAmount=function(){
 var a=this.get_currentResourceAmount(),b=this.get_maxResourceAmount();
 if(!this.get_isProducing()||a>=b)return a;
 if(this.get_isManuallyProductionRoom()&&this.get_tsActivityEnd()<=K.get_now())return b;
 var c=this.get_tsLastResourceChange();c=K.get_now()-c;c=Math.floor(c/60);
 if(0>=c)return a;
 c=Math.floor(this.get_resourceAmountPerMinute()*c);
 return p.parseInt(p.string(Math.min(c+a,b)))};
```

→ Auto-produção acumula **por minutos decorridos** desde `ts_last_resource_change`;
manual dá o máximo quando `ts_activity_end` já passou.

### 4.2 Capacidade efectiva (factores multiplicativos)

```js
// max efectivo = base × generator × brain × guildSynergy (só auto-production)
get_maxResourceAmount = base * currentGeneratorFactor * currentBrainFactor * currentGuildSynergyFactor
```

```js
Vf.prototype.get_currentGeneratorFactor=function(){var a=1;
 if(this.get_isAffectedByGenerator()&&0<this.get_currentGeneratorLevel()){
  a+=pd.fromId("generator").getLevel(this.get_currentGeneratorLevel()).get_passivBonusAmount1()/100;
  var b=this.getAdditionalGuildSynergyValue("affected_generator_level",0);
  0<b&&(a+=li.fromId(b).get_bonusAmount()/100)}return a};

// brain: só xp_production; soma TODAS as salas brain fora da store
Vf.prototype.get_currentBrainFactor = 1 + Σ brain[level].passiv_bonus_amount_1/100

// sinergia de guilda: só guild_auto_join(gh id 1)/guild_competition_point(gh id 6)
// se qualified && active && upgrade_level>0: factor += gh.fromId(n).getGuildSynergyUpgradeLevel(e).value1
```

### 4.3 Ritmo de produção (por minuto)

```js
Vf.prototype.get_resourceAmountPerMinute=function(){var a=0;
 this.get_isManuallyProductionRoom()
  ? a=60/((this.get_tsActivityEnd()-this.get_tsLastResourceChange())/(this.get_maxResourceAmount()-this.get_currentResourceAmount()))
  : this.get_isAutoProductionRoom()&&(a=pd.fromId(this.get_identifier()).getLevel(this.get_level()),
     a=p.parseInt(p.string(Math.max(Math.round(a.get_minTillMaxResource()*x.get_hideout_min_till_max_resource_duration_scaling()),1))),
     a=this.get_maxResourceAmount()/a);
 return a};
```

### 4.4 Segundos até terminar actividade

Auto-produção: extrapolação do ritmo até encher. Manual/build/upgrade/place/store:
`ts_activity_end - now`.

### 4.5 Hash visual da sala (deteção de mudanças)

```js
get_hash = "i"+identifier+"l"+level+"g"+current_generator_level+"e"+ts_activity_end+
           "s"+status+"r"+ts_last_resource_change+"b"+brainFactor+"gs"+guildSynergyFactor
```

## 5. Grelha de slots — semântica de `room_slot_<L>_<S>`

Constantes: `tb.MAX_LEVELS=12; tb.MAX_SLOTS=5; tb.MAX_HIDEOUT_LEVEL=0;`
ID do slot: `"<level>_<slot>"`.

| Valor no payload | Significado |
|---|---|
| chave ausente | slot não desbloqueado (`isSlotUnlocked`: `hasData ? valor>=0 : false`) |
| `-1` | bloqueado (mas conta como "não desbloqueado" no count) |
| `<= -2` | desbloqueio EM CURSO; valor = `-ts_activity_end` (timestamp negativo) |
| `0` | desbloqueado e vazio |
| `> 0` | id da sala colocada nesse slot |

```js
Ie.prototype.isSlotUnlockInProgress=function(a,b){a="room_slot_"+tb.getSlotIdFromLevelAndSlot(a,b);
 return this.hasData(a)?-1>this.getInt(a):!1};
Ie.prototype.getSlotUnlockTsEnd=function(a,b){...return this.hasData(a)?-this.getInt(a):0};
Ie.prototype.getUnlockedRoomsCount=function(){...-1!=this.getInt("room_slot_"+...)&&++a ...}; // conta !=-1
```

⚠️ Inconsistência útil a notar: `isSlotUnlocked` considera valores ≥0 como
desbloqueados e a ausência como bloqueado; `getUnlockedRoomsCount` conta
tudo excepto `-1` (inclui desbloqueios em curso ≤ -2).

`HideoutRoomSlotLevel` é apenas par `{level, slot}` (default -1,-1).

## 6. Fórmulas do HideoutUtil (`tb`)

### 6.1 Durações de actividades

```js
tb.getRoomDuration=function(a){...
 building:  60 * pd.fromId(id).getLevel(1).build_time * workerReductionFactor * hideout_build_duration_scaling
 placing:   level * hideout_place_duration_per_level * 60 * workerReductionFactor
 storing:   level * hideout_store_duration_per_level * 60 * workerReductionFactor
 upgrading: generator+is_synergy_upgrading ? li.fromId(addGenLevel+1).build_time
                                            : pd.fromId(id).getLevel(level+1).build_time,
            × 60 × workerReductionFactor × hideout_upgrade_duration_scaling
 producing:
   attacker/defender_production: Rp.fromId(u).getLevel(barracksBonus).build_time × maxResourceAmount × 60
   gem_production:               x.get_item_improvement_duration()
   exchange_room:                60 × additional_value_2.duration
   gym:                          additional_value_2.duration
   blacksmith:                   60 × pd.blacksmith.getLevel(level).min_till_max_resource
   guild_auto_join_production:   hideout_guild_auto_join_room_duration × maxResourceAmount
   guild_missile_production:     hideout_guild_missile_room_duration × maxResourceAmount
   guild_medikit/weapon_oil:     additional_value_2.duration
 depois: b /= currentGeneratorFactor; b /= currentGuildSynergyFactor;
 mínimo 1s; arredondar.
```

### 6.2 Factor de velocidade do worker

```js
Ie.prototype.getWorkerReductionFactor=function(a){null==a&&(a=!1);var b=1,c=this.get_currentWorkerLevel();
 0==c&&a&&0<this.getRoomsByType("worker_home").length&&(
   a=this.getRoomsByType("worker_home")[0],null==a||a.get_isBuilding()||(c=a.get_level()));
 0<c&&(b=1-pd.fromId("worker_home").getLevel(c).get_passivBonusAmount1()/100);return b};
```

→ redução = `passiv_bonus_amount_1`% do nível do `worker_home` (campo
`current_worker_level` do hideout; fallback opcional ao nível da sala em construção).

### 6.3 Curva do custo premium "instant finish"

```js
tb.getInstantFinishPremiumAmount=function(a){var b=0;
 if(0<a){b=x.get_hideout_skip_short_time();
  var c=x.get_hideout_skip_short_premium_amount(),e=x.get_hideout_skip_medium_time(),
      m=x.get_hideout_skip_medium_premium_amount(),d=x.get_hideout_skip_long_time(),
      D=x.get_hideout_skip_long_premium_amount(),f=x.get_hideout_skip_very_long_time(),
      h=x.get_hideout_skip_very_long_premium_amount();
  b=a<=b?c:a<=e?(m-c)/(e-b)*(a-b)+c:a<=d?(D-m)/(d-e)*(a-e)+m:(h-D)/(f-d)*(a-d)+D}
 return p.parseInt(p.string(Math.min(Math.ceil(b), x.get_hideout_skip_max_premium_amount())))};
```

→ Interpolação linear em 4 segmentos [short..medium..long..very_long], tecto
`hideout_skip_max_premium_amount`. Excepção UI: main_building nível 1 custa 0.

### 6.4 Máximos de unidades — robot_storage

```js
tb.getMaxAttackerUnits=function(a){if(0==a.get_currentRobotStorageLevel())return a.get_maxAttackerUnitsForAttack();
 var b=pd.fromId("robot_storage"),c=a.get_maxAttackerUnitsForAttack();
 b.hasLevelId(a.get_currentRobotStorageLevel())&&(c=p.parseInt(p.string(
  c + c*b.getLevel(a.get_currentRobotStorageLevel()).get_passivBonusAmount1()/100)));
 return c};  // getMaxDefenderUnits simétrico
```

⚠️ O cliente soma o bónus do robot_storage POR CIMA dos valores crus enviados.
O servidor deve mandar a BASE em `max_attacker_units`/`max_defender_units`.

### 6.5 Broker — redução de preços gold

```js
tb.getReducedGameCurrencyValue=function(a,b){if(0==b)return a;
 var c=pd.fromId("broker");c.hasLevelId(b)&&(a*=1-c.getLevel(b).get_passivBonusAmount1()/100);
 return Math.ceil(a)};
```

### 6.6 Nível máximo do hideout ("expansion")

```js
tb.MAX_HIDEOUT_LEVEL += Σ_salas (limit × nº níveis) [+ bónus de sinergia do generator]
Ie.get_expansion = current_level / getMaxHideoutLevel()
```

### 6.7 Validação de colocação (`canRoomBeBuild`) — mensagens de erro

```
limit==0                                  -> error_room_teaser
cópias >= limit                           -> error_room_limit_reached
main_building colocado < unlock_with_mainbuilding[cópias] -> error_room_needs_mainbuilding_level
guild_main_building sem guilda            -> error_room_needs_guild
salas de guilda: energia < gh.fromId(N).neededPoints -> error_room_needs_guild_synergy_energy
                                          (N: auto_join=1, missile=2, medikit=4, weapon_oil=5, competition_point=6)
personagem não qualificado p/ sinergia    -> error_character_not_qualified_for_guild_synergy_upgrades
senão                                     -> ok
```

## 7. Workers (normal / aluguer / comprado)

```js
Ie.prototype.get_hasTimerWorker=function(){return this.get_tsTimeWorkerExpires()>=K.get_now()};
Ie.prototype.get_idleWorkerCount=function(){
 return 0<this.get_tsTimeWorkerExpires()&&!this.get_hasTimerWorker()
  ? Math.min(base_idle, max) : base_idle};
Ie.prototype.get_maxWorkerCount=function(){
 return 0<this.get_tsTimeWorkerExpires()&&!this.get_hasTimerWorker()
  ? base_max-1 : base_max};
Ie.prototype.get_premiumWorkerBought=function(){...2==max?true:3==max quando timer...};
```

Correção: sem compra e sem aluguer `max_worker_count=1`; comprado → 2;
`ts_time_worker_expires > agora` = worker de aluguer activo. Compra real via
produtos de pagamento `"hideout_time_worker"` e `"hideout_worker"`.

## 8. Batalha — estrutura de dados

### 8.1 Payload `hideout_battle` (`DOHideoutBattle`)

Campos: `id, attacker_hideout_id, defender_hideout_id, defender_server_id,
attacker_status, defender_status, attacker_count, attacker_profiles (JSON str),
defender_count, defender_profiles (JSON str), hideout_winner_id, server_winner_id,
rounds (JSON str), attacker_rewards (JSON str), attacker_bonus_rewards (JSON str),
defender_rewards (JSON str), destroyed_attacker, destroyed_defender,
attacker_character_name, defender_character_name`.

```js
Ie.prototype.get_hasActiveBattle=function(){return null!=this._battle&&this._battle.get_id()==this.get_activeBattleId()?1==this._battle.get_attackerStatus():!1};
```

### 8.2 Profiles (`HideoutBattleCharacter`)

Mapa chave→perfil; prefixos `a_N` (atacante), `d_N` (defensor), `w_N` (parede),
`oc` (optical changes do dono, nos defenders).

```js
XA.prototype.get_identifier=function(){return"a"==this.get_id()?"attacker":"d"==this.get_id()?"defender":"w"==this.get_id()?"wall":""};
XA.prototype.get_hitPoints=function(){return Q.getHitPoints(this.getInt("stamina"))};
// Q.getHitPoints(stamina) = Math.round(stamina * constants["battle_hp_scale"])
```

Imagem da parede escala por nível: `Math.min(Math.ceil(level/5),4)`.

### 8.3 Rounds

Cada ronda (crú): `a` (atacante), `d` (atacado), `v` (dano total), `r` (resultado),
`dt` dot, `bf` backfire, `bd` bonus damage, `ha`/`hd` healing, `m` missile index,
`ae`/`de` effect arrays.

```js
get_resultType: dodged r==1; normalHit r==2; criticalHit r==3;
HideoutBattleRound acrescenta: guildMissileNormalHit r==4; guildMissileCriticalHit r==5;
                               guildMissileChainHit r==6
get_damage = v - dt - bd
```

HP restante para barras de vida recalculado no cliente percorrendo as rondas
(`endHitPoints`). **A simulação é toda feita no servidor** — o cliente só reproduz.

### 8.4 Histórico (`DOHideoutHistoryBattle`)

Campos: `id, ts_creation, attacker_hideout_id, hideout_winner_id, server_winner_id,
attacker_rewards, defender_rewards, destroyed_attacker, destroyed_defender,
character_id, character_name, character_gender, character_online_status`.

## 9. Oponente

Payload `hideout_opponent` = `{opponent:<payload completo de personagem>,
opponent_inventory, opponent_inventory_items[, opponent_sidekick]}`.

Extras de hideout dentro de `opponent` (lidos pelo wrapper `HideoutOpponent`):

```js
Gx.prototype.get_hideoutPoints=function(){return this.getInt("hideout_points")}
Gx.prototype.get_defenderUnits=function(){return this.getInt("defender_units")}
Gx.prototype.get_wallBuildingLevel=function(){return this.getInt("wall_level")}     // nível do EDIFÍCIO
Gx.prototype.get_barracksLevel=function(){return this.getInt("barracks_level")}
Gx.get_wallLevel  = wall.passiv_bonus_amount_1 do nível (cliente deriva)
Gx.get_unitsLevel = barracks.passiv_bonus_amount_1 do nível
get_reward/get_bonusReward <- strings JSON "rewards"/"bonus_rewards"
```

Recompensas (classe Reward): chaves `hideout_points`, `hideout_glue`, `hideout_stone`
(+ xp, coins, honor, league_points, premium, item, booster, missiles, auto_join,
guild_synergy_energy, etc.).

Cooldowns vistos no cliente:
- ataque após derrota: `ts_last_lost_attack + 60*hideout_battle_cooldown - now`
- refresh grátis: `60*hideout_opponent_refresh_free_cooldown` desde
  `ts_last_opponent_refresh`; senão premium `hideout_opponent_refresh_premium_amount`.

Params de ataque (verbatim, nota o typo "expacted" do jogo original):

```js
sendActionRequest("startHideoutFight",{attacker_count:this._unitsToAttack,
 expacted_glue_reward:this._hideout.get_currentOpponent().get_reward().get_hideoutGlue()+bonusGlue,
 expacted_stone_reward:this._hideout.get_currentOpponent().get_reward().get_hideoutStone()+bonusStone,
 use_premium:this._btnAttack.get_showAsCost()},...)
sendActionRequest("startHideoutTutorialFight",{attacker_count:this._unitsToAttack},...)
```

## 10. Leaderboard

`retrieveHideoutLeaderboard` → `leaderboard_hideouts[]` + `max_hideouts`.
Campos por linha: `server_id, rank, id, character_id, name, locale, guild_id,
guild_name, emblem_*, gender, hideout_level, hideout_points, dungeon_hardmode_emblems,
level, league_points, league_group_id, honor, value, online_status`.

Derivados: `hideoutExpansion = hideout_level / getMaxHideoutLevel()`; nomes com
`___` = personagens apagados; online = `online_status==1`.

**Não há cálculo de Elo/hideout_points no cliente** — pontos vêm sempre do
servidor.

## 11. Checklist mínima para o nosso backend

1. `hideout`: todos os campos do §2 + grelha `room_slot_L_S` 12×5 + extras de
   visita (`server_id`, `owner_name`, `owner_gender`, `optical_changes`).
2. `hideout_rooms[]` do §3 com status 1–6, recursos, timestamps,
   `current_generator_level`, `additional_value_2` e
   `guild_synergy_additional_value` como strings JSON.
3. `hideout_opponent` com projeção completa de personagem (+ inventory/items/sidekick).
4. `hideout_battle` com profiles e rounds pré-computados no servidor.
5. `missed_hideout_attacks`, `max_hideouts`, `leaderboard_hideouts`,
   `guild_gem_production_level` conforme a ação.
6. Os valores base têm de ser coerentes com as fórmulas §4/§6 porque o cliente
   volta a aplicar factores (generator/brain/sinergia sobre `max_resource_amount`;
   wall/barracks como NÍVEIS de edifício, não valores finais; robot_storage somado
   por cima de `max_*_units`).
