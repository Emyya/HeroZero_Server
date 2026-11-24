# Sistema de combate do hideout no cliente (`HeroZero.min.js`)

> Fonte: análise do `server-laravel/public/js/HeroZero.min.js` (build 257).
> Citações verbatim. Foco no FLUXO de ataque; a estrutura de dados
> (`hideout_battle`, profiles, rounds) está em `modelo_dados.md` e as respostas
> reais em `respostas_capturadas.md`.
>
> Classes: `HideoutBattle`=dv, `DOHideoutBattle`=pp, `HideoutBattleRound`=m6,
> `Hideout`=Ie, `DOHideout`=dh, `HideoutOpponent`=Gx,
> `DialogHideoutBattleOpponentSelection`=wm, `DialogHideoutBattleResults`=uU,
> `DialogHideoutBattleHistory`=Yp, `DialogHideoutTutorialComplete`=eu,
> `LeaderboardHideout`=Jt, `PanelBattle`=F, `PanelHideout`=tg.

## 1. Regra de ouro

**Todo o combate é resolvido no servidor.** O cliente apenas reproduz o array
de rondas por ordem (`visualizeHideoutBattleRound` → ronda seguinte; skip
avança para a próxima troca de lutador). Não há geração de dano nem ordem de
ataque no cliente; HP restante é derivado dos dados do servidor:

```js
XA.prototype.get_hitPoints=function(){return Q.getHitPoints(this.getInt("stamina"))};
Q.getHitPoints = Math.round(stamina * constants["battle_hp_scale"])
dv.endHitPoints(id, atéRondaN)  // percorre rounds e subtraí dano/heals/backfire
```

## 2. Fluxo de ataque

### 2.1 Abertura do diálogo de seleção de oponente

Se nunca houve primeiro ataque (`!get_hasHideoutFirstAttack()`) ou o oponente
atual é "fresco" (<120s), usa-se o atual; senão pede-se novo:

```js
!this.get_character().get_hasHideoutFirstAttack()||
null!=this._hideout.get_currentOpponent()&&this._hideout.get_currentOpponent().get_lastRefresh()+wm.OPPOENT_REFRESH>K.get_now()
 ? this.refresh()
 : sendActionRequest("getHideoutOpponent",{},...)
// wm.OPPOENT_REFRESH=120 (typo original)
```

`getHideoutOpponent {}` → resposta com chaves `hideout` + `hideout_opponent`
(`{opponent, opponent_inventory, opponent_inventory_items}`); merge via updateData.

### 2.2 Contagem de atacantes

Slider min=1, max = min(`max_attacker_units` cru do servidor, `current_attacker_units`);
memoriza a última escolha na setting de user `"hideout_attack_count"`:

```js
var a=Math.min(this._hideout.get_maxAttackerUnitsForAttack(),this._hideout.get_currentAttackerUnits()),
    b=user.hasSetting("hideout_attack_count")?...:0,c=a;0<b&&(c=Math.min(a,b));
b=0<a?1:0;
```

⚠️ O max usado aqui é o valor CRU (`get_maxAttackerUnitsForAttack`) — sem o
bónus do robot_storage (ver modelo_dados §6.4).

### 2.3 Cooldown pós-derrota

Se `now - ts_last_lost_attack < 60 × hideout_battle_cooldown` (constante em
minutos), timer de 1s e o botão de ataque passa a custar premium
(`hideout_battle_skip_cooldown_premium_amount`) — `use_premium:true` salta o cooldown:

```js
this._hideout.get_tsLastLostAttack()>K.get_now()-60*x.get_hideout_battle_cooldown()&&
(this._timer=n.createTimer(...,1E3,...),...,this.onTimerEvent())
...
wm.prototype.onTimerEvent=function(){var a=this._hideout.get_tsLastLostAttack()+60*x.get_hideout_battle_cooldown()-K.get_now();
 0<a?(this._btnAttack.set_showAsCost(!0),this._content.txtCooldown.set_visible(!0),...):(...)}
this._btnAttack=new oc(this._content.btnAttack,x.get_hideout_battle_skip_cooldown_premium_amount(),...)
```

### 2.4 Pedido de ataque

```js
sendActionRequest("startHideoutFight",{attacker_count:this._unitsToAttack,
 expacted_glue_reward:this._hideout.get_currentOpponent().get_reward().get_hideoutGlue()+bonusGlue,
 expacted_stone_reward:this._hideout.get_currentOpponent().get_reward().get_hideoutStone()+bonusStone,
 use_premium:this._btnAttack.get_showAsCost()},...)
```

- **Typo original do jogo**: `expacted_*` (não "expected"). O backend tem de
  aceitar estes nomes tal qual.
- Os rewards esperados são preview+bonus somados — mecanismo anti-dessincronização
  que produz `errStartHideoutFightRewardsChanged` se o servidor recalculou entretanto.

Handler de sucesso — aplica updateData e 0,5s depois arranca a visualização
(ou dispara `ViewMessage.notifyBattleFinished` se a setting `show_hideout_battles`
estiver off, abrindo logo os resultados):

```js
""==a.error?(this._btnAttack.set_buttonEnabled(!1),n.application.updateData(a.data),
 b=function(){F.set_activeHideoutBattle(c.get_character().get_hideout().get_activeHideoutBattle());
  c.get_user().get_showHideoutBattles()?(A.get_instance().showPanel("battle"),...startHideoutBattleVisualization())
   :na.dispatch(new sc("ViewMessage.notifyBattleFinished",null,c))},this.close(),ka.delayFunction(b,.5))
```

### 2.5 Erros de startHideoutFight (tratamento completo)

| Erro | Ação do cliente |
|---|---|
| `errStartHideoutFightRewardsChanged` | refaz `getHideoutOpponent {}` + dialog de erro |
| `errStartHideoutFightNotJetPossible` | fecha + dialog de erro |
| `errStartHideoutFightNoOpponent` | apenas fecha |
| `errStartHideoutFightInvalidTarget` | `refreshHideoutOpponent {use_premium:!1}` |
| `errstartHideoutBattleInvalidAttackerCount` (prefixo minúsculo!) | fecha + `syncHideout {}` |
| `errStartHideoutFightNotYetAllowed` | dialog de erro + `syncHideout {}` |
| `errRemovePremiumCurrencyNotEnough` | dialog "premium insuficiente" |

```js
"errStartHideoutFightInvalidTarget"==a.error?n.application.sendActionRequest("refreshHideoutOpponent",
 {use_premium:!1},k(this,this.handleRequests)):"errstartHideoutBattleInvalidAttackerCount"==a.error?(this.close(),
 q.get_current().get_character().get_hideout().sync()):...
```

### 2.6 refreshHideoutOpponent

Botão próprio; grátis após `60 × hideout_opponent_refresh_free_cooldown` desde
`ts_last_opponent_refresh`; senão premium `hideout_opponent_refresh_premium_amount`:

```js
tU.prototype.refresh=function(){var a=60*x.get_hideout_opponent_refresh_free_cooldown()-
 (K.get_now()-q.get_current().get_character().get_hideout().get_tsLastOpponentRefresh());
 0>=a?(this._button.gotoAndStop("free"),...,this._isPremium=!1):
 (this._button.gotoAndStop("premium"),this._hint.set_cost(p.string(x.get_hideout_opponent_refresh_premium_amount())),this._isPremium=!0)}
sendActionRequest("refreshHideoutOpponent",{use_premium:this._btnRefreshHideoutEnemies.get_isPremium()},...)
```

Erros: `errRemovePremiumCurrencyNotEnough` → dialog; `errRefreshHideoutOpponentNoOpponents`
→ fecha; `errRefreshHideoutOpponentNotYetAllowed` → **ignorado silenciosamente**.

### 2.7 Previsão de vitória (SÓ apresentação, não regra)

```js
a=this._unitsToAttack*Math.pow(this._hideout.get_currentUnitsLevel(),2);
var b=8<wallLevel ? Math.pow(wallLevel,3)+defenderUnits*Math.pow(unitsLevel,2)
    : 6<wallLevel ? Math.pow(wallLevel,2)+... : wallLevel+...;
a/=b;
2<=a?"win_chance_very_easy":1<=a?"easy":.9<=a?"medium":.8<=a?"hard":"very_hard"
```

(Nota: quanto maior o rácio, MAIS fácil — very_easy ≥ 2.)

## 3. Socket/tempo-real

### 3.1 `syncHideoutAttack` — recebido quando somos atacados

Sem payload usado: incrementa o contador local de ataques perdidos, o que
dispara `syncHideout` (HTTP):

```js
case "syncHideoutAttack":null!=q.get_current()&&...&&(m=q.get_current().get_character().get_hideout(),
 m.set_missedAttacks(m.get_missedAttacks()+1));break;
Ie.prototype.set_missedAttacks=function(a){a>this._missedAttacks&&-1<this._missedAttacks&&this.sync();return this._missedAttacks=a};
Ie.prototype.sync=function(){this._forceSync=!1;n.application.sendActionRequest("syncHideout",{},k(this,this.handleRequests))};
```

### 3.2 `syncGuildSynergy` pode arrastar sync do hideout

```js
case "syncGuildSynergy":...e.hasData("sync_hideout")&&(...m.forceSync());break;
```

### 3.3 Contador também chega por HTTP

Campo top-level `missed_hideout_attacks` nas respostas (login/sync), aplicado
em updateData → `set_missedAttacks`.

Ao abrir o painel: se `missedAttacks>0` e setting
`missed_hideout_battles_auto_shown` ativa e não há batalha ativa, abre o
histórico automaticamente; se há batalha ativa, reproduz-na.

## 4. Tutorial fight

Disparo: no diálogo de seleção, sem oponente atual e tutorial incompleto →

```js
sendActionRequest("startHideoutTutorialFight",{attacker_count:this._unitsToAttack},...)
```

Oponente fake do tutorial (apresentação pura; valores das constantes locais):
professor, nível/pontos/recompensas das constantes
(`hideout_first_enemy_level/_hideout_points/_reward_hideout_glue/_stone/_points`),
parede nível 1, 0 defensores, win chance fixo "very_easy".

Erros: `errstartHideoutBattleInvalidAttackerCount` → close+sync;
`errstartHideoutTutorialFightAlreadyFinished` → close+sync.

**finishHideoutTutorial** `{}` enviado ao fechar `DialogHideoutTutorialComplete`
(diálogo mostra glue/stone de `hideout_tutorial_finished_reward_glue/stone`);
erro `errFinishHideoutTutorialAlreadyFinished` → apenas fecha.

### Flags do tutorial (guardadas em character.tutorial_flags)

Ordem observada no código:
1. `hideout_first_building` — main_building nível 1 a produzir
2. `hideout_first_upgrade` — após upgrade bem-sucedido
3. `hideout_build_stone` → `hideout_build_glue` → `hideout_build_attacker` — salas construídas
4. `hideout_build_attacker_units` — após produção de atacantes
5. `hideout_collect_attacker_units` — após recolher atacantes
6. `hideout_first_collect` — primeira recolha de recursos
7. Seta de ataque mostrada quando `hasTutorialFlag("hideout_collect_attacker_units") && !hasHideoutFirstAttack()`
8. `hideout_tutorial_show_attacker` / `hideout_tutorial_show_defender` — visibilidade dos contadores de unidades
9. `hideout_first_attack` — set após collect das rewards da 1ª batalha (ver §7)
10. `hideout_tutorial_completed` — após DialogHideoutTutorialComplete

Getters:

```js
Y.prototype.get_hasHideoutTutorialCompleted=function(){return this.hasTutorialFlag("hideout_tutorial_completed")};
Y.prototype.get_hasHideoutFirstBuilding=function(){return this.hasTutorialFlag("hideout_first_building")};
Y.prototype.get_hasHideoutFirstAttack=function(){return this.hasTutorialFlag("hideout_first_attack")};
```

## 5. Histórico de batalhas

```js
sendActionRequest("getHideoutBattleHistoryFights",{},...)
// sucesso: hideout.set_missedAttacks(0); resposta: hideout_history_battles[] (DOHideoutHistoryBattle)
```

Ver uma luta — devolve a batalha COMPLETA (com rounds) sob a chave
`hideout_history_battle` (tipo DOHideoutBattle, não history!), modo revisão:

```js
sendActionRequest("getHideoutBattleHistoryFight",{id:this._hideoutBattle.get_id()},...)
ta.prototype.get_hideout_history_battle=function(){return this.hasData("hideout_history_battle",!0)?new pp(this.getData("hideout_history_battle")):null};
```

Apagar — ids selecionados juntos com ";"; apagar tudo por max id:

```js
sendActionRequest("deleteHideoutHistoryBattles",{battle_ids:"id1;id2"},...)
sendActionRequest("deleteAllHideoutHistoryBattles",{max_battle_id:a},...)
```

Ordenação por `ts_creation` desc. "Ver inimigo" faz `loadCharacter(character_id)`.

Campos do history item: `id, ts_creation, attacker_hideout_id, hideout_winner_id,
server_winner_id, attacker_rewards, defender_rewards, destroyed_attacker,
destroyed_defender, character_id, character_name, character_gender,
character_online_status`.

## 6. Leaderboard

Params enviados (sempre com `server_id`):

```js
retrieveHideoutLeaderboard({server_id, points_sort:bool, rank?:int, character_name?:string})
```

- Sort por pontos vs expansão: `points_sort:!context.expansionSorted`
- Paginação por **rank**: scroll up/down pede `rank±1`; pesquisa por nome ou rank
- Limite total vindo do servidor em `max_hideouts`
- Throttle client-side de 30s entre refreshes

Resposta: `leaderboard_hideouts[]` (linhas LeaderboardHideout), `max_hideouts`,
`centered_rank` (linha a centrar na UI).

Erros: `errRetrieveHideoutLeaderboardInvalidCharacter`, `...InvalidRank`, `...Sync`.

Render das linhas: rank, name (`___` = apagado → texto "character_deleted"),
guild_name, `roundDecimal(100*hideout_level/maxLevel,1)+"%"`, hideout_points,
online_status==1, emblemas.

## 7. Rewards (`collectHideoutFightRewards`)

Diálogo de resultados abre em `ViewMessage.notifyBattleFinished` com batalha
ativa fought. Ganhou → mostra `attacker_rewards` fundidos com
`attacker_bonus_rewards` (glue tipo 12 / stone tipo 13 somados às entradas
existentes ou adicionadas); chest de optical changes (tipo 22) se
`current_opponent_chest_reward`:

```js
if(0<this._hideout.get_activeHideoutBattle().get_attackerBonusReward().get_hideoutGlue()){
 ...if(12==e.get_type()){e.set_amount(e.get_amount()+...get_hideoutGlue());b=!0;break}}
 b||a.push(new ea(12,...))}
this._hideout.get_hasOpticalChangeChestBattleReward()&&a.push(new ea(22,1));
Ie.prototype.get_hasOpticalChangeChestBattleReward=function(){return this.get_currentOpponentChestReward()};
```

Perdeu → esconde zona de recompensas, textos `title_lost`/`outcome_lost`.

**Fechar o diálogo é que cobra:**

```js
uU.prototype.handleClickClose=function(a){this._btnClose.set_enabled(!1);
 n.application.sendActionRequest("collectHideoutFightRewards",
 {hideout_battle_id:this._hideout.get_activeHideoutBattle().get_id()},k(this,this.handleRequests))};
```

Sucesso → updateData + marca flag `hideout_first_attack` (se 1ª vez) + abre
DialogHideoutTutorialComplete se o tutorial não está completo.
Erro `errCollectHideoutFightRewardsInvalidBattle` → **engolido silenciosamente**
(nem reativa o botão).

Estrutura JSON dos rewards: `{hideout_points, hideout_glue, hideout_stone, xp,
coins, premium, honor, league_points, item, booster{booster_id,days}, ...}`
(classe Reward).

## 8. Vencedor e batalha ativa

```js
dv.prototype.get_isOwnHideoutWinner=function(){
 return this.get_hideoutWinnerId()==q.get_current().get_character().get_hideout().get_id()
  ? null!=this.get_serverWinnerId()&&""!=this.get_serverWinnerId()?this.get_serverWinnerId()==ma.get_serverId():!0:!1};
Ie.prototype.get_hasActiveBattle=function(){return null!=this._battle&&this._battle.get_id()==this.get_activeBattleId()?1==this._battle.get_attackerStatus():!1};
Ie.prototype.refreshBattle=function(a){a.get_id()==this.get_activeBattleId()&&(this._battle=new dv(a))};  // só aceita a batalha ativa
```

Tipos de ronda extra do hideout: `r=4` guild missile normal, `r=5` crit,
`r=6` chain hit (mesma animação de missile hit).

## Notas de implementação para o backend

- Constantes necessárias no config: `battle_hp_scale`, `hideout_battle_cooldown`,
  `hideout_battle_skip_cooldown_premium_amount`, `hideout_opponent_refresh_free_cooldown`,
  `hideout_opponent_refresh_premium_amount`, `hideout_unlock_min_level`,
  `hideout_first_enemy_*`, `hideout_tutorial_finished_reward_*`.
- Chaves de resposta consumidas: `hideout`, `hideout_room`, `hideout_rooms`,
  `hideout_opponent`, `hideout_battle` (só aplicada se `id == active_battle_id`),
  `missed_hideout_attacks`, `hideout_history_battle`, `hideout_history_battles`,
  `leaderboard_hideouts` + `max_hideouts` + `centered_rank`.
- `collectHideoutFightRewards` é o momento em que as rewards são creditadas —
  `startHideoutFight` cria a batalha mas NÃO credita nada.

## Extensão local: patamares diários de ataques

No servidor atual, `collectHideoutFightRewards` também avança o contador dos
patamares 5/10/20 quando o herói ataca outro esconderijo real, ganhando ou
perdendo. Tutorial e defesas não contam. Contagem, recursos e fecho da batalha
são atómicos e retries não duplicam. Regras, protocolo e testes em
[patamares_combates.md](../patamares_combates.md).
