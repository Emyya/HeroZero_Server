# Sidekicks em Combate e Perfis de Personagem (reverse engineering do cliente)

Ficheiro analisado: `server-laravel/public/js/HeroZero.min.js` (build 257, 15.757.146 bytes).
Método: navegação por âncoras literais (`str.find`) + janelas de contexto; os offsets
citados entre parênteses retos são índices de caracteres no ficheiro minificado, para
regresso rápido ao sítio.

## Índice

1. [O campo `sidekick` na appearance de batalha](#1)
2. [Durante a luta: battle effects dos sidekicks](#2)
3. [Contributo de stats do sidekick na ficha do herói](#3)
4. [Duelo/PvP: o sidekick de outro jogador](#4)
5. [Barras de XP do sidekick na UI de batalha](#5)
6. [Só dois lutadores são desenhados? Como entra o sidekick](#6)
7. [Outros contextos: guilda, dungeons, hideout, liga, temporada](#7)
8. [Contrato de aparência/dados para o servidor (tabela)](#8)

Classes principais (nomes originais recuperados dos marcadores `__name__` do output Haxe):

| Var minificada | Classe | Offset do marker |
|---|---|---|
| `uj` | `com.playata.application.data.battle.Battle` | 262992 |
| `lm` / `mw` / `WE` | `BattleRound` / `DOBattleRound` / `CharacterBattleRound` | 273815 / 272243 / 280377 |
| `bd` | `battle.BattleEffect` (helpers estáticos) | 265813 |
| `LA` | `battle.BattleEffectSkill` | 267588 |
| `Gl` | `dataobject.DOBattleOpponent` | 269450 |
| `Op` | `battle.BattleOpponent` | 271151 |
| `xi` | `battle.BattleVisualizationCharacter` | ~279000 (fim) |
| `cj` | `constants.CBattleEffect` (constantes `battle_effects`) | 440819 |
| `He` | `constants.CSidekickSkill` (constantes `sidekick_skills`) | 694804 |
| `Sh` | `constants.CSidekickData` | 690287 |
| `lf` | `dataobject.DOSidekick` | 896170 |
| `Cd` | `sidekick.Sidekick` | 1517697 |
| `jk` | `sidekick.SidekickSkill` (helpers de descrição) | 1524537 |
| `Hm` | `ui.elements.battle.UiBattleAvatar` | 4153951 |
| `lu` | `ui.elements.battle.SymbolBattleAvatarAnimation` | 4143313 |
| `Tk` / `yR` | `UiBattleSidekickSkillIcon` / `UiBattleSidekickSkills` | 4156632 / 4159009 |
| `zD` / `QV` | `SidekickAvatarImage` / `SpineSidekick` | 6875912 / 6877474 |
| `F` | `ui.panels.PanelBattle` | 7711521 (fim; corpo começa ~7628038) |
| `Yj` | `ui.elements.stats.UiStats` | 7048445+ |
| `fZ` | `ui.elements.sidekick.UiSidekickXpBar` | 6991501 |

---

<a name="1"></a>
## 1. O campo `sidekick` na appearance de batalha

### 1.1. Onde a appearance é lida

`Battle.calculateRounds` decodifica o blob da batalha e separa três peças
(`profile_a_appearance` literal @262597):

```js
uj.prototype.calculateRounds=function(){
  ...
  var b=new d(zf.decode(a));
  b.hasData("rounds")&&(
    b.hasData("profile_a_appearance")&&(this._profileAAppearance=b.getTypedObject("profile_a_appearance")),
    b.hasData("profile_b_appearance")&&(this._profileBAppearance=b.getTypedObject("profile_b_appearance")),
    b=b.getTypedObject("rounds"));
  // cada round -> new WE(e) (CharacterBattleRound)
```

Consumidores de `get_profileAAppearance()`: `Duel` (@915010), `LeagueFight` (@1339550),
`PanelBattle` (@7647052, @7656943). Ou seja: duelo, liga, quest, worldboss, story dungeon,
legendary dungeon e batalhas de guilda usam todos este mesmo formato.

### 1.2. Forma EXATA do objeto `sidekick` que o servidor tem de injetar

O construtor de `DOBattleOpponent` (`Gl`, @267973) normaliza o subobjeto `sidekick`
logo na construção — só cria defaults para os campos `sewed_*`:

```js
Gl.prototype._hx_constructor=function(a){
  J.prototype._hx_constructor.call(this,a);
  this.hasData("sidekick")&&(
    a=this.getTypedObject("sidekick"),
    a.hasData("sewed_identifier")||a.setString("sewed_identifier",""),
    a.hasData("sewed_level")||a.setInt("sewed_level",0))};
```

A forma canônica está em `xi.fromMissedBattle(profile, appearance)` (@277521), que é
exatamente o que o cliente espera encontrar no payload:

```js
xi.fromMissedBattle=function(a,b){          // a = profile_x, b = profile_x_appearance
  var c=b.hasData("sidekick",!0)?b.getTypedObject("sidekick"):null;
  a=new Gl({
    name:b.getString("name"),
    gender:b.getString("gender"),
    level:a.getInt("level"),
    leaguedivision:a.hasData("leaguedivision")?a.getData("leaguedivision"):null,
    stat_total_stamina:a.getInt("stamina"),
    stat_total_strength:a.getInt("strength"),
    stat_total_critical_rating:a.getInt("criticalrating"),
    stat_total_dodge_rating:a.getInt("dodgerating"),
    stat_weapon_damage:a.getInt("weapondamage"),
    show_mask:b.hasData("show_mask")?b.getBoolean("show_mask"):!0,
    show_cape:b.hasData("show_cape")?b.getBoolean("show_cape"):!0,
    mask:..., belt:..., boots:..., cape:..., suit:...,
    missiles:..., missiles1:..., missiles2:..., missiles3:..., missiles4:...,
    season_effect:a.hasData("season_effect")?a.getData("season_effect"):null,
    guild_battle_effects:a.hasData("guild_battle_effects")?a.getData("guild_battle_effects"):null,
    sidekick:null!=c?{
      identifier:c.getString("identifier"),           // obrigatório (getString direto!)
      sewed_identifier:c.hasData("sewed_identifier")?c.getString("sewed_identifier"):"",
      sewed_level:c.hasData("sewed_level")?c.getInt("sewed_level"):0,
      name:c.getString("name"),                       // obrigatório
      level:c.getInt("level"),                        // obrigatório
      stage1_skill_id:c.getInt("stage1_skill_id"),    // obrigatório
      stage2_skill_id:c.getInt("stage2_skill_id"),    // obrigatório
      stage3_skill_id:c.getInt("stage3_skill_id")     // obrigatório
    }:null});
```

Repara: **o `sidekick` vem da APPEARANCE** (`profile_x_appearance.sidekick`), não do
objeto `profile_x`. Os campos `level`, stats totais, `season_effect` e
`guild_battle_effects` é que vêm do `profile_x`.

Nota importante: `identifier`, `name`, `level` e os três `stage*_skill_id` são lidos com
`getString`/`getInt` **sem** guarda `hasData` — se faltarem, o cliente lança exceção.
Apenas `sewed_identifier`/`sewed_level` têm default ("" e 0, aplicados por `Gl`).
Quando o personagem não tem sidekick ativo, o servidor deve omitir o campo ou enviar
`null` (o cliente testa `hasData("sidekick", true)`).

Para referência, o próprio cliente gera este objeto em `xi.fromCharacter` (@275909)
quando desenha o jogador local sem appearance disponível (duelo/liga ativos):

```js
sidekick:null!=a.get_currentSidekick()?{
  identifier:a.get_currentSidekick().get_identifier(),
  sewed_identifier:..., sewed_level:...,
  name:..., level:...,
  stage1_skill_id:..., stage2_skill_id:..., stage3_skill_id:...
}:null
```

### 1.3. Da appearance ao ecrã: `get_sidekick()` das characterSettings

`BattleVisualizationCharacter` (`xi`, get_sidekick @275009) embrulha os dados crus num
par `DOSidekick`(lf)/`Sidekick`(Cd):

```js
xi.prototype.get_sidekick=function(){
  null==this._sidekick&&this.hasData("sidekick")&&null!=this.getData("sidekick")&&
    (this._sidekick=new Cd(new lf(this.getData("sidekick"))));
  return this._sidekick};
xi.prototype.get_appearanceSettings=function(){return qg.fromBattleVisualizationCharacter(this)};
```

E `qg.fromBattleVisualizationCharacter` (@295464, continuação @296300) copia o objeto
para as *character settings* usadas pelo avatar:

```js
qg.prototype.get_sidekick=function(){try{return y.__cast(this.getData("sidekick"),Cd)}catch(a){return null}};
...
b.setData("missiles4", ...);
b.setData("sidekick",a.get_sidekick());   // <- settings ficam com o Sidekick
return new qg(b);
```

### 1.4. Desenho: `UiBattleAvatar.fillCharacter`

`fillCharacter(settings, isLeft)` (@4147373) é o ponto onde o sidekick entra na cena:

```js
Hm.prototype.fillCharacter=function(a,b){
  this.emptyAvatar();
  this._characterSettings=a;
  a=new mu; this._uiAvatar=new fq(a); this._uiAvatar.update(this._characterSettings);
  this._avatar.container.character.addChild(a);
  null!=this._characterSettings.get_sidekick()&&(
    this._avatar.containerSidekick.set_alpha(1),
    this._sidekickImage.refresh(this._characterSettings.get_sidekick()));
  ...
  this._isLeft=b?(this._avatar.containerSidekick.sidekick.set_scaleX(1),
                  this._avatar.containerSidekick.sidekick.set_x(0))
               :(this._avatar.containerSidekick.sidekick.set_scaleX(-1),
                 this._avatar.containerSidekick.sidekick.set_x(-75));
```

- Se `settings.get_sidekick()==null`, o container fica invisível (`emptyAvatar` chama
  `this._sidekickImage.refresh(null)` @4150775, que faz `this._content.set_visible(false)`).
- O lado B (direita) espelha o sidekick (`scaleX=-1`, `x=-75`); o lado A usa `scaleX=1`.
- Árvore visual (@10684259 e @12116484): `SymbolBattleAvatarGeneric` → `containerSidekick`
  (`yCa`, criado em x:130,y:220) → filho `sidekick` (`VLa`) → filho `sidekickImage`.
  É **dentro do avatar do herói**, não um terceiro lutador na cena.

### 1.5. Renderização do sidekick: `SidekickAvatarImage.refresh`

`zD.refresh(sidekick, force)` (@6874438, continuação @6874950):

```js
zD.prototype.refresh=function(a,b){
  ...
  if(b||c!=this._useAnimations||this._sidekick!=a||
     null!=this._spine&&...)
    this._sidekick=a,
    this._useAnimations=c,                     // q.get_current().get_useAnimations()
    this._content.set_visible(null!=this._sidekick),
    null==this._sidekick?(this.disposeSpine(),this._wrapper.removeAllChildren(!0)):(
      this._sidekickDisplayVariation=this._sidekick.get_displayVariation(),
      1==this._useSidekickDataPosition&&Sh.exists(this._sidekick.get_displayIdentifier())&&(
        a=Sh.fromId(...),
        this._wrapper.set_x(a.get_offsetX()), this._wrapper.set_y(a.get_offsetY())),
      this._useAnimations?
        (/* spine */ null==this._spine&&(this._spine=new QV(this._sidekick), ...)):
        (this.disposeSpine(),
         this._wrapper.setUriSprite(this._sidekick.get_imageUrl(),175,175,!0,4,...)))
```

Detalhes úteis para o servidor/artes:

- Posição base vem das constantes `CSidekillData` (`Sh`, getters @688268+):
  `offset_x/offset_y` + `avatar_1/2/3_offset_x/y` + `avatar_1/2/3_scale`
  (variação 1/2/3 conforme nível) + `panel_offset_x/y`.
- Variação visual: `Cd.get_displayVariation()` (@1512910):
  `(isSewed()? sewedLevel : level) >= 30 ? 3 : >= 15 ? 2 : 1`.
- Assets: `sidekicks/<assetIdentifier>_<displayVariation>_i.webp` (ícone),
  `items/<displayIdentifier>_i.webp` (ícone tipo item), `sidekicks/<assetIdentifier>`
  (spine), `sidekicks/<assetIdentifier>_background.webp`.
- Com animações ligadas usa Spine (`QV`); senão, imagem estática 175x175.

---

<a name="2"></a>
## 2. Durante a luta: battle effects dos sidekicks

### 2.1. Modelo de dados: battle effect ↔ sidekick skill

As constantes `CBattleEffect` (`cj`, @439356) ligam cada "battle effect" do servidor aos
`sidekick_battle_effect_ids` que o produzem:

```js
cj.prototype.get_sidekickBattleEffectIds=function(){return this.getIntVector("sidekick_battle_effect_ids")};
cj.prototype.get_roundStartEffect=function(){return this.getBoolean("round_start_effect")};
cj.prototype.get_afterHitEffect=function(){return this.getBoolean("after_hit_effect")};
cj.prototype.get_singleRoundEffect=function(){return this.getBoolean("single_round_effect")};
// constantsData = v.getConstantsData("battle_effects")
```

Cada `CSidekickSkill` (`He`, getters @692550–693500) tem o inverso:

```js
He.prototype.get_id=function(){return this.getInt("id")};
He.prototype.get_identifier=function(){return this.getString("identifier")};
He.prototype.get_sidekickBattleEffectId=function(){return this.getInt("sidekick_battle_effect_id")};
He.prototype.get_stage=function(){return this.getInt("stage")};          // 1..3
He.prototype.get_value1/2/3=function(){...getNumber("value1"/"value2"/"value3")};
He.prototype.get_type=function(){return this.getInt("type")};
He.prototype.get_charges=function(){return this.getInt("charges")};       // -1 = ilimitado
He.prototype.get_fixedSidekick=function(){return this.getStringVector("fixed_sidekick")};
He.prototype.get_legendary=function(){return this.getBoolean("legendary")};
He.prototype.get_oncePerGroupBattle=function(){return this.getBoolean("once_per_group_battle")};
// constantsData = v.getConstantsData("sidekick_skills")
```

Os conversores estão em `BattleEffect` (`bd`, @263790):

```js
bd.sidekickBattleEffectToBattleEffect=function(a){   // id efeito sidekick -> id battle effect
  for(cj ids) if(0<=cj.fromId(e).get_sidekickBattleEffectIds().indexOf(a)) return e; return 0}
bd.sidekickBattleEffectToSidekickSkill=function(a){  // id efeito sidekick -> id skill
  for(He ids) if(He.fromId(e).get_sidekickBattleEffectId()==a) return e; return 0}
bd.isRoundStartEffect(a)= sidekickBattleEffectToBattleEffectData(a).get_roundStartEffect();
bd.isAfterHitEffect(a)  = ...get_afterHitEffect();
bd.isSingleEvent(a)     = ...get_singleRoundEffect();
bd.multiUsePossible(a)  = He.exists(skill) ? -1==He.fromId(skill).get_charges() : !1;
```

### 2.2. O que chega nos rounds

`BattleRound` (`lm` sobre `DOBattleRound`, @271983–274343) — campos curtos:

```js
r  -> resultType: 1=dodged, 2=normalHit, 3=criticalHit
a  -> attacker ("a"|"b");   d -> attacked ("a"|"b")
v  -> valor total;          dt -> dotDamage
bd -> bonusDamage;          bf -> backfireDamage
ha -> healingAttacker;      hd -> healingAttacked
m  -> usedMissileIndex
ae -> attackerEffects : int[] (ids de sidekick battle effect ATIVOS no atacante)
de -> attackedEffects : int[] (ids ativos no atacado)
```

`WE` (`CharacterBattleRound`) acrescenta apenas `attackerA()/attackerB()`
(`"a"==attacker`). Não há eventos separados por tipo de efeito (stun, DoT, etc.): tudo
chega como dano/heal nos campos acima + listas `ae`/`de`; o significado de cada id de
efeito é resolvido nas constantes `battle_effects` do lado do cliente. Os valores
numéricos das skills (dano do veneno, cura, etc.) também já vêm calculados em `dt`, `v`,
`ha`, `hd` — o cliente nunca recalcula efeitos de combate.

Formato dos efeitos extra (temporada/guilda): mapa de 1 entrada cuja chave é o id do
battle effect e o valor um número ou `{chance, value, value2}` (construtor de `LA`,
@265438):

```js
"guild_battle_effects": { "<battleEffectId>": {"chance":..,"value":..,"value2":..} }
```

### 2.3. Reprodução: quando cada efeito dispara

Em `PanelBattle.visualizeRound` (@7665191):

```js
F.prototype.visualizeRound=function(a){
  var b=a.get_attackerA()?this._avatarA:this._avatarB,   // atacante
      c=...,                                             // atacado
      e=a.get_attackerA()?this._sidekickSkillsA:this._sidekickSkillsB,  // ícones do atacante
      d=...;                                             // ícones do atacado
  ...
  this.showRoundStartEffects(a.get_attackerEffects(),a.get_attackedEffects(),b,c,e,d);
  b.attacking(!1,!1,a.get_criticalHit());
  a.get_normalHit()?c.beingAttackedHit(a.get_damage())
   :a.get_criticalHit()?c.beingAttackedCritical(a.get_damage())
   :a.get_dodged()&&c.beingAttackedDodge()};
```

```js
F.prototype.showRoundStartEffects=function(a,b,c,e,d,f){   // @7667477
  if(null!=a) for each h in a:
    bd.isRoundStartEffect(h)&&(
      c.queueEffect(Q.getSidekickSkillIdentifierFromSidekickBattleEffect(h)),
      d.showSkillUsage(h,bd.isSingleEvent(h),!bd.multiUsePossible(h)));
  if(null!=b) for each c in b:  // mesmos passos no avatar/ícones do DEFENSOR };
F.prototype.showAfterHitEffects=function(a,b,c,e,d,f){     // @7667954 (mesma estrutura)
  ... bd.isAfterHitEffect(h) && (queueEffect + showSkillUsage) ...
```

Ou seja: o servidor marca em cada round quais efeitos estão ativos (`ae`/`de`); o
cliente classifica-os com as flags `round_start_effect` / `after_hit_effect` /
`single_round_effect` das constantes e reage.

Animações disparadas (todas em `UiBattleAvatar`):

- `queueEffect(identifier)` (@4146297): carrega o asset
  **`sidekick_skills/<identifier>_infight.webp`** e anima-o sobre o herói
  (`iconAnimation`, tween de escala/opacidade, ~1.43s); efeitos em fila.
- `showSkillUsage(effectId, singleEvent, keepActive)` em `UiBattleSidekickSkills` (@4158400):
  converte o id de efeito em skill e "ativa" o ícone correspondente (pulso de escala;
  filtro escuro permanente se não for multi-uso).
- `onAttackComplete` (@7666177) → `showAfterHitEffects(...)`, barra de HP desce.
- `onDotDamageDone` (@7670329): aplica `bar.decrease(dotDamage)` e olha à frente nos
  rounds: se o próximo round do MESMO atacante tiver `dotDamage==0`, considera o DoT
  terminado e desativa os ícones dos efeitos mapeados dos battle effects **16, 18 e 22**
  (`bd.battleEffectToSidekickBattleEffects(16/18/22)`) — estes três ids são os grupos de
  efeitos DoT/periódicos hard-coded no cliente.
- Cura: `beingHealedAttacker(v)/beingHealedAttacked(v)` mostram `healingDisplay`
  (campos `ha`/`hd`); dano de recuo: `beingAttackedBonusDamage(v)` (campo `bf`).
- Fim de luta: `SymbolBattleAvatarAnimation.defeated()` (@4127880) faz fade-out do
  herói E do `containerSidekick` (alpha→0 em 0.63s); `fadeIn()` (@4137939) faz o oposto.

### 2.4. Os ícones junto à barra do herói

`UiBattleSidekillSkills.refresh(sidekick, extraEffects)` (@4156632/@4159009):

```js
yR.prototype.refresh=function(a,b){
  this.clearIcons();
  this._content.set_visible(null!=a||null!=b&&0<b.length);
  if(this._content.get_visible()&&(null!=a&&(
       null!=a.get_stage1Skill()&&a.hasUnlockedStageSkill(1)&&this.addSkillIcon(a.get_stage1Skill()),
       null!=a.get_stage2Skill()&&a.hasUnlockedStageSkill(2)&&this.addSkillIcon(a.get_stage2Skill()),
       null!=a.get_stage3Skill()&&a.hasUnlockedStageSkill(3)&&this.addSkillIcon(a.get_stage3Skill())),
     null!=b&&0<b.length))
    for each c in b: this.addSkillIcon(null,c)};   // efeitos extra como ícones
```

- Desbloqueio de skills por nível (@1513768): `hasUnlockedStageSkill(n)` =
  `level >= 10*n`; `hasUnlockedSkill(id)` = skill id == stageN_skill_id com nível >= 10/20/30.
- `resetSkillUsage()` (@4158840): repõe os ícones no início da luta/troca de lutador,
  exceto skills com `once_per_group_battle=true` (essas mantêm o estado "gasto").
- Tooltip do ícone: nome/descrição via `sidekick_skill/<identifier>/name`,
  `/short_description`, `/description` com substituição de valores 1..3 (`jk.getSkillDescription`,
  @1524300). Ícone: `sidekick_skills/<identifier>_i.webp` (`LA.get_iconUrl` @267413).

---

<a name="3"></a>
## 3. Contributo de stats do sidekick na ficha do herói

### 3.1. O cliente CALCULA a partir do DO do sidekick (não espera totals prontos)

Na classe `Character` (`Y`, @353889/@354243):

```js
Y.prototype.get_sidekickStamina=function(){
  return null==this.get_currentSidekick()?0
    :this.get_currentSidekick().get_statStamina()+this.sidekickBaseStatBonus(1,106)+this.sidekickBaseStatBonus(1,109)};
Y.prototype.get_sidekickStrength=function(){ ... get_statStrength()+bonus(2,105)+bonus(2,109) };
Y.prototype.get_sidekickCriticalRating=function(){ ... bonus(3,107)+bonus(3,109) };
Y.prototype.get_sidekickDodgeRating=function(){ ... bonus(4,108)+bonus(4,109) };

Y.prototype.sidekickBaseStatBonus=function(a,b){
  b=bd.battleEffectToSidekickBattleEffects(b);        // battle effect -> lista de efeitos sidekick
  for(var c=0,e=0;e<b.length;){
    var m=b[e];++e;
    m=bd.sidekickBattleEffectToSidekickSkill(m);
    if(this.get_currentSidekick().hasUnlockedSkill(m))
      switch(m=He.fromId(m).get_value1(),a){
        case 1:c+=Math.ceil(this.get_statBaseStamina()*m);break;
        case 2:c+=Math.ceil(this.get_statBaseStrength()*m);break;
        case 3:c+=Math.ceil(this.get_statBaseCriticalRating()*m);break;
        case 4:c+=Math.ceil(this.get_statBaseDodgeRating()*m)}
  }
  return c};
```

Leitura: contributo = `stat_stamina/strength/critical_rating/dodge_rating` do DO do
sidekick **+ bónus percentuais** das skills desbloqueadas que mapeiam para os battle
effects fixos 105 (força), 106 (stamina), 107 (critical), 108 (dodge) e 109 (todos os
stats) — `Math.ceil(baseStat * value1_da_skill)`. Estes cinco ids de battle effect estão
hard-coded no cliente; a associação efeito↔skills vem das constantes.

### 3.2. Onde é somado/exibido

- Totais do personagem: `Outfits` monta `stat_total_stamina` etc. somando
  `base + items + boosters + guildBooster + itemSet + sidekickXxx + leagueBonus +
  hideoutRoomBonus` (@1427651–1428911).
- Tooltips das barras de stats (`UiStatBar`/`UiStats`, @7019300+): a linha do sidekick é
  `formatHugeNumber(get_sidekickStamina())` dentro das chaves de localização
  `general/brawn_season_tooltip_new` (stamina/força/critical/dodge têm variantes) —
  aparece como parcela separada na ficha/comparação.
- Em batalhas de grupo NÃO há cálculo: `UiStats.refreshFromGuildBattleProfile` (@7048245)
  lê valores PRONTOS do perfil enviado pelo servidor:
  ```js
  Yj.prototype.refreshFromGuildBattleProfile=function(a,b,c,e){
    this._statBarStamina.set_value(a.getInt("stamina"));
    this._statBarStrength.set_value(a.getInt("strength"));
    this._statBarCriticalRating.set_value(a.getInt("criticalrating"));
    this._statBarDodgeRating.set_value(a.getInt("dodgerating")); ...}
  ```
  (`GuildBattleCharacter.get_totalStats` soma exatamente estes cinco ints + weapondamage,
  @1058000.)

Resposta à pergunta: **para o próprio herói (ficha/tooltips) o cliente calcula a partir
do DO do sidekick + constantes; para perfis de outros personagens em batalhas de grupo
espera os totais prontos** (`stamina`, `strength`, `criticalrating`, `dodgerating`,
`weapondamage` — já INCLUSIVE o contributo do sidekick, pois são os totais de combate).

---

<a name="4"></a>
## 4. Duelo/PvP: o sidekick de outro jogador

`RequestedCharacterData` (`EC`, @432532) traz o sidekick de outro personagem:

```js
EC.prototype.get_requested_character_sidekick=function(){
  return this.hasData("requested_character_sidekick")?new lf(this.getData("requested_character_sidekick")):null};
```

Consumo único (@404672), ao construir/atualizar o `Character` visualizado:

```js
null!=a.get_requested_character_sidekick()&&b.refreshSidekick(a.get_requested_character_sidekick());
```

`Character.refreshSidekick` (@385582) insere/atualiza a lista `_sidekicks` do personagem
(em bruto `new Cd(a)`), e `get_currentSidekick()` resolve o ativo via inventário:

```js
Y.prototype.get_currentSidekick=function(){
  return null!=this._sidekicks&&null!=this._inventory&&this._inventory.hasData("sidekick_id")
    &&0!=this._inventory.get_sidekickId()?this.getSidekickById(this._inventory.get_sidekickId()):null};
```

Consequência prática para o servidor: basta enviar `requested_character_sidekick`
(objeto `DOSidekick` completo — ver §8) na resposta de "ver personagem"; toda a UI
(perfil, tooltip, comparação) passa a mostrar o sidekick dele através dos getters
normais. Nos duéis/liga, quando existe `profile_a/b_appearance`, o oponente é
reconstruído por `xi.fromMissedBattle` (§1.2); quando não existe (luta ativa), o cliente
usa `xi.fromCharacter` para o próprio jogador local e `xi.fromCharacter(this.get_opponent())`
para o adversário — nesse caso o sidekick do oponente só aparece se os dados dele
(`requested_character_*`) já foram carregados:

```js
En.prototype.get_opponentActiveDuel=function(){                    // Duelo @915010
  return null==this.get_battle().get_profileBAppearance()
    ?xi.fromCharacter(this.get_opponent())
    :xi.fromMissedBattle(this.get_battle().get_profileB(),this.get_battle().get_profileBAppearance())};
```

(Em `LeagueFight` @1339800 a mesma alternância com `selfCharacter`/`missedOpponent`.)

---

<a name="5"></a>
## 5. Barras de XP do sidekick na UI de batalha

**Não existem.** Confirmação:

- `UiSidekickXpBar` (`fZ`, def @6990225, marker @6991501) só é instanciada em
  `UiSidekickInfo` (`new fZ` @6901914) e `UiSidekickPanel` (@6931080) — diálogos do
  sidekick, fora do painel de batalha.
- No corpo do `PanelBattle` (região 7628038–7711521) não há qualquer referência a XpBar.
- O que a UI de batalha mostra do sidekick: `txtSidekickNameA/B` (nome) e
  `sidekickSkillsA/B` (ícones de skills/efeitos), posicionados junto ao nome
  (`updateSidekickAPosition/updateSidekickBPosition` @7660303: alinham os ícones após a
  largura do texto do nome).

A própria barra (`fZ.setProgressValue`) suporta animação de transição de nível
(`setValueAndTween(1,.25,.75,continueUpdateOnNextLevel)`), mas é usada apenas nos
painéis de gestão do sidekick.

---

<a name="6"></a>
## 6. Só dois lutadores? Sim — e o sidekick vive DENTRO do avatar

Confirmado mecanicamente:

1. A replay só conhece dois perfis: `profile_a_appearance`/`profile_b_appearance`
   (§1.1) e cada round refere apenas `attacker/attacked ∈ {a,b}`.
2. `SymbolPanelBattleGeneric` cria exatamente dois avatares; `PanelBattle` mantém
   `_avatarA/_avatarB`, `_barA/_barB`, `_statsA/_statsB`, `_sidekickSkillsA/_sidekickSkillsB`,
   `txtSidekickNameA/B` — nada mais (@7713359, lista de campos de F).
3. Batalhas de grupo (guilda, guilda-dungeon, hideout) trocam os membros **nos mesmos
   dois slots**: `updateGuildBattleCharacters(round)` escolhe `ownGuildCharacter` vs
   `otherGuildCharacter`, escreve o nome no slot A/B, mostra
   `getRemainingCharacterNames(...)` ("restantes X, Y, Z") e refaz
   `fillCharacter`/`refresh(sidekick, additionalEffects)` para o novo ocupante
   (@7689629/@7697970). Cada troca faz `fadeIn()` no avatar que entra.
4. O sidekick não é um terceiro ator: é um filho do avatar do herói
   (`containerSidekick` dentro de `SymbolBattleAvatarGeneric`, §1.4), herdando o fade
   `defeated()/fadeIn()` do dono. As animações de ataque do herói movem apenas
   `container` (herói); o sidekick acompanha porque está contido no mesmo display object
   raiz, e some/aparece com os fades de alpha do container pai.

---

<a name="7"></a>
## 7. Outros contextos com sidekicks em combate

### 7.1. Batalhas de guilda (guilda vs guilda)

- `DOGuildBattle` traz `attacker_character_profiles` e `defender_character_profiles`
  (strings JSON, keyed por id de personagem — getters @824100, parsing em
  `GuildBattle.getCharacter` @1049868).
- `GuildBattleCharacter` (`cv`) **estende `BattleVisualizationCharacter`**:
  ```js
  cv.prototype._hx_constructor=function(a,b,c){
    this._ownGuild=c;
    xi.prototype._hx_constructor.call(this,new Gl(a));   // perfil -> Gl (com "sidekick")
    this.setInt("id",b)};
  ```
  Logo cada membro pode levar o seu objeto `sidekick` no perfil (mesma forma do §1.2,
  mais `position`, `stamina`, `strength`, `criticalrating`, `dodgerating`,
  `weapondamage`, opcional `total_stamina`).
- Rounds de guilda (`GuildBattleRound`) adicionam resultados de mísseis de guilda
  (`get_guildMissileNormalHit/CriticalHit/ChainHit`), tratados no mesmo pipeline de
  efeitos sidekick (`showAfterHitEffects` com `ae`/`de`).
- Efeitos de artefacto de guilda: `xi.get_guildBattleEffects()` (@275009) desserializa o
  mapa `guild_battle_effects` do perfil em `BattleEffectSkill`s e injeta-os nos ícones:
  `_sidekickSkillsA.refresh(c, a.get_additionalEffects())`.

### 7.2. Guilda dungeon

`GuildDungeonBattleCharacter` (`LC`) estende `cv` ($jscomp.inherits(LC,cv) @1118886) —
mesmo contrato, mais `npcTeamIdentifier` e nomes de NPC traduzidos. O fluxo de rounds é
idêntico (`visualizeGuildDungeonBattleRound`, @7697670).

### 7.3. Hideout

`HideoutBattleCharacter` (`XA`) estende diretamente o DO base (`J`) — **não** tem
getter de sidekick nem wrapper `xi`. Ainda assim, os rounds de hideout trazem
`attackerEffects/attackedEffects` e `playHideoutBattleRound` (@7702600) dispara
`showRoundStartEffects/showAfterHitEffects` nos mesmos painéis de ícones: os ícones de
efeitos funcionam, mas não há nome/imagem de sidekick (defensores são unidades do
hideout, ex.: "wall").

### 7.4. Legendary dungeon

Os buffs de sala reutilizam o campo `sidekick_battle_effect_id` na constante
`CLegendaryDungeonBuff` (@643547). `PanelBattle` mostra-os como ícones EXTRA na faixa do
defensor (NPC), sem sidekick real:

```js
this._sidekickSkillsA.refresh(a.get_sidekick(), F.get_activeLegendaryDungeonRun().get_modificationBattleEffectSkills());
...
if(null!=e&&null!=e.get_battleEffects()&&0<e.get_battleEffects().length){
  b=[]; for each d in e.get_battleEffects(): b.push(bd.getBattleEffectSkill(d));
  this._sidekickSkillsB.refresh(null,b)}     // só efeitos, sem sidekick
```

`bd.getBattleEffectSkill(id)` (@265161) decide quantos values ler a partir das flags
booleanas `value1/value2/value3` do `CBattleEffect`.

### 7.5. Temporada (season) e helpers vários

- As constantes de temporada também expõem `sidekick_battle_effect_id` +
  `sidekick_battle_effect_value_1/2/3` (classe das seasons, @673237) e o personagem tem
  `seasonBaseStatBonus(stat, battleEffectId)` análogo ao `sidekickBaseStatBonus`
  (visível nos tooltips @7019300, efeitos 105–109 reutilizados).
- `BattleEffect.getMediKitSidekickSkill()` = skill 266; `getWeaponOilSidekickSkill()` =
  skill 267 (@263790) — ids usados em fluxos de itens consumíveis.
- `xi.get_seasonEffect()` desserializa `season_effect` do perfil (mapa estilo
  `guild_battle_effects`) numa `BattleEffectSkill` única.

---

<a name="8"></a>
## 8. Contrato de aparência/dados para o servidor

Legenda: **O** = obrigatório (leitura direta, ausência rebenta), **o** = opcional com
default no cliente, **—** = opcional/pode faltar (testado com `hasData`).

### 8.1. `battle.profile_X_appearance` (objeto typed; usado por duelo, liga, quests, worldboss, story/legendary dungeon, e como base visual em guild battles)

| Campo | Tipo | Obr. | Notas |
|---|---|---|---|
| `name` | string | **O** | `fromMissedBattle` lê direto |
| `gender` | string | **O** | determina conjunto de chaves de aparência |
| `appearance_<key>` | int | **O**\* | uma por constante key do género (exceto `hair_back`); \*lidas com `getInt` direto |
| `show_mask`, `show_cape` | bool | o | default `true` |
| `mask`,`belt`,`boots`,`cape`,`suit` | string (identifier) | — | `null` aceitável |
| `missiles`,`missiles1`..`missiles4` | string (identifier) | — | projéteis |
| `sidekick` | objeto/null | — | ver 8.2; omitir se não há sidekick |

### 8.2. `profile_X_appearance.sidekick`

| Campo | Tipo | Obr. | Notas |
|---|---|---|---|
| `identifier` | string | **O** | id textual do sidekick (constantes `CSidekillData`) |
| `name` | string | **O** | nome custom ou base |
| `level` | int | **O** | define variação visual (>=15 → 2, >=30 → 3) e skills desbloqueadas |
| `stage1_skill_id` | int | **O** | 0/negativo se não há skill (cliente testa `get_stageNSkill()!=null` via id) |
| `stage2_skill_id` | int | **O** | idem |
| `stage3_skill_id` | int | **O** | idem |
| `sewed_identifier` | string | o | default `""` |
| `sewed_level` | int | o | default `0`; se sewed e >=15/30 muda variação |

(NOTA: `stage*_skill_id` são lidos sem guarda — envia sempre ints, mesmo que 0. Os
ícones só aparecem se `level >= 10*stage` e o id corresponder a uma skill existente.)

### 8.3. `battle.profile_X` (stats/nível; lido por `fromMissedBattle` e perfis de guilda)

| Campo | Tipo | Obr. | Notas |
|---|---|---|---|
| `level` | int | **O** | |
| `stamina`,`strength`,`criticalrating`,`dodgerating` | int | **O** | totais de combate JÁ COM sidekick incluído |
| `weapondamage` | int | **O** | |
| `leaguedivision` | int | — | divisão de liga (badge) |
| `season_effect` | mapa `{ "<effectId>": num \| {chance,value,value2} }` | — | 1 entrada por efeito |
| `guild_battle_effects` | mapa idêntico | — | artefactos/bónus de guilda |

### 8.4. `battle.rounds` (array; cada round)

| Campo | Tipo | Obr. | Notas |
|---|---|---|---|
| `a` | string `"a"`/`"b"` | **O** | atacante |
| `d` | string `"a"`/`"b"` | **O** | atacado |
| `r` | int | **O** | 1=esquivado, 2=normal, 3=crítico |
| `v` | int | o | valor total do hit (se falhar, dano 0) |
| `dt` | int | o | dano DoT aplicado neste round (dispara `onDotDamageDone`) |
| `bd` | int | o | dano bónus |
| `bf` | int | o | dano de recuo (backfire) |
| `ha` / `hd` | int | o | cura no atacante / atacado |
| `m` | int | o | índice do projétil usado (1..5) |
| `ae` | int[] | o | ids de sidekick battle effect ativos NO ATACANTE |
| `de` | int[] | o | ids ativos NO ATACADO |

Semântica que o servidor tem de respeitar: ids em `ae`/`de` devem ser
`CSidekillSkill.sidekick_battle_effect_id`s que mapeiam (via constantes
`battle_effects.sidekick_battle_effect_ids`) para battle effects com as flags certas
(`round_start_effect`, `after_hit_effect`, `single_round_effect`); enquanto um DoT
estiver ativo, cada round do afetado deve trazer `dt>0` até ao round em que termina
(esse traz `dt` ausente/0 → cliente desativa os ícones dos grupos 16/18/22).

### 8.5. Dados de sessão do próprio jogador (resposta de request)

| Campo | Tipo | Notas |
|---|---|---|
| `sidekicks` | lista de `DOSidekick` | merge por id em `Character._sidekicks` |
| `inventory.sidekick_id` | int | 0 = nenhum ativo; define `get_currentSidekick()` |
| `requested_character_sidekick` | `DOSidekick` | ao VER outro jogador; merge na lista dele |

`DOSidekick` completo (getters @894640–896170): `id`, `character_id`, `status`,
`identifier`, `name`, `quality`, `level`, `xp`, `item_improvement_id`,
`stat_base_stamina/strength/critical_rating/dodge_rating`,
`stat_stamina/strength/critical_rating/dodge_rating` (totais já com melhoramentos),
`stage1/2/3_skill_id`, `sewed_identifier`, `sewed_level`; opcionalmente
`special_skill_id`, `sources`, `now_owned`, `ever_owned`.

### 8.6. Perfis de batalhas de grupo

| Contexto | Campo | Forma |
|---|---|---|
| Guild battle | `attacker_character_profiles` / `defender_character_profiles` | JSON string `{ "<charId>": {perfil Gl com `sidekick`, `position`, `stamina`, `strength`, `criticalrating`, `dodgerating`, `weapondamage`, opc. `total_stamina`} }` |
| Guild dungeon | igual, via `LC` (estende `cv`) | idêntico |
| Hideout | perfis sem `sidekick` (unidades), mas rounds com `ae`/`de` | ícones funcionam na mesma |

### 8.7. Constantes que o servidor tem de servir corretamente

| Constante | Campos críticos |
|---|---|
| `battle_effects` (CBattleEffect) | `sidekick_battle_effect_ids` (int[]), `round_start_effect`, `after_hit_effect`, `single_round_effect`, `value1/2/3` (bool: quais values o efeito usa) |
| `sidekick_skills` (CSidekillSkill) | `identifier`, `stage`, `sidekick_battle_effect_id`, `value1/2/3`, `type`, `charges` (-1 = ilimitado), `once_per_group_battle`, `legendary`, `fixed_sidekick` |
| `sidekick_data` (CSidekillData) | `identifier`, `offset_x/y`, `avatar_1/2/3_offset_x/y`, `avatar_1/2/3_scale`, `panel_offset_x/y`, `special_sidekick`, `licence_sidekick` |
| `legendary_dungeon_buffs` | reutiliza `sidekick_battle_effect_id`, `stat_type`, `value`, `charges`, `keycard_cost` |
