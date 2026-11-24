# Sidekicks/Pets (02) — Constantes e estruturas de dados do cliente

Documento de engenharia inversa do cliente HTML5 oficial (`server-laravel/public/js/HeroZero.min.js`, 15 757 146 bytes, build 257).
Descreve **todas** as constantes e estruturas de dados de sidekicks que o cliente carrega/espera, com âncoras literais e offsets no ficheiro minificado.

Método: leitura única do ficheiro + navegação por `str.find` de âncoras literais e fatias de contexto (`js[i-200:i+600]`); regexes curtas apenas dentro de janelas. Todos os offsets abaixo referem-se a `HeroZero.min.js` na versão analisada.

Ficheiros consultados:

| Ficheiro | Papel |
|---|---|
| `server-laravel/public/js/HeroZero.min.js` | Cliente HTML5 (fonte primária) |
| `server-laravel/data/initGame.json` | Captura da resposta `initGame` do nosso servidor (secções sidekick **a null**) |
| `server-laravel/data/autoLoginUser.json` | Captura do login (campo `sidekicks: []`, `reskill_enabled: false`) |
| `server-laravel/public/assets/i18n/pt_BR/text.data` | Textos pt-BR (zlib + JSON, 2 490 418 bytes descomprimido, 20 033 chaves) |
| `tools/allclasses.txt` | Lista de classes completas do cliente |

---

## Índice

1. [Como o cliente carrega as constants](#1-como-o-cliente-carrega-as-constants)
2. [Constantes escalares de balanceamento (objeto `x`)](#2-constantes-escalares-de-balanceamento-objeto-x)
3. [Secções constants de sidekick](#3-secções-constants-de-sidekick)
4. [Battle effects — classe `bd` e mapeamentos](#4-battle-effects--classe-bd-e-mapeamentos)
5. [Fórmula de stats do sidekick](#5-fórmula-de-stats-do-sidekick)
6. [XP e nível](#6-xp-e-nível)
7. [`displayVariation` e URLs de assets](#7-displayvariation-e-urls-de-assets)
8. [Dados em runtime: DOSidekick e ações de rede](#8-dados-em-runtime-dosidekick-e-ações-de-rede)
9. [i18n](#9-i18n)
10. [Verificação no disco](#10-verificação-no-disco)
11. [Conclusões — o que o servidor precisa de fornecer](#11-conclusões--o-que-o-servidor-precisa-de-fornecer)

---

## 1. Como o cliente carrega as constants

Há **duas fontes**, por esta ordem:

**a) Ficheiro local no arranque** (@118109 / @~120000, função de setup da aplicação):

```js
a=n.assetManager.getLoader(bp.CONSTANTS);
a.enqueue(new uT("constants_json",
    new NOa(Wd.getAssetVersion("data/constants_json.data"),"json")),!0);
```

O asset pede-se a `data/constants_json.data` (relativo à pasta de assets; ver secção 10 — atualmente o symlink `assets/data` está **partido**). O loader (`com.playata.framework.data.constants.ConstantsLoader`, classe `tT`, @8185755) aceita dois formatos:

```js
tT.setConstantsData=function(a,b){
  if("json"==b.format)a=qh.getObjectFromCompressedJsonData(a);
  else if("amf3"==b.format)a=qh.getObjectFromCompressedAMF3Data(a);
  else throw new aa("Unknown ConstantsLoader format '"+b.format+"'");
  null!=T.current?T.current.setData(new d(a)):T.current=new T(new d(a));
  return T.current};
```

`getObjectFromCompressedJsonData` = descomprimir (zlib) e ler UTF-8 → JSON (`zf.decode`). Ou seja: **`constants_json.data` é JSON comprimido com zlib**.

**b) Resposta do servidor no login/initGame** (@160500):

```js
null!=T.current?T.current.setData(a.get_appResponse().get_constants())
               :T.current=new T(a.get_appResponse().get_constants()),
Ra.config.extend(a.get_appResponse().get_extendedConfig()), ...
```

A resposta de login pode trazer um objeto `constants` completo que substitui os dados locais; também `onUserLogin` (@2003039) reaplica `a.hasData("constants") && T.current.setData(a.get_constants())`.

**Acesso às constantes.** A classe `T` (`com.playata.framework...Constants`, @5259082):

```js
T.prototype.constant=function(a){this.cleanupMergedConstants();
  if(this._mergedConstants.hasData(a))return this._mergedConstants.getData(a);
  throw new yc("Error in Constants! Trying to retrieve unknown constant! key="+a);};
```

- Se a chave **não existir**: exceção `"Error in Constants! Trying to retrieve unknown constant! key=..."`. Uma secção inteira em falta (ou a `null`) faz o cliente rebentar assim que qualquer UI de sidekick tentar ler a secção.
- Os wrappers `getInt(key)`/`getString(key)`/etc. têm valores por omissão quando o **campo** falta dentro de um registo: `getInt=function(a,b){null==b&&(b=0); ...}` → devolve `0`; `getBoolean` análogo. Um campo numérico em falta não rebenta, mas silencia o valor.

Todas as secções são acedidas através da classe base `v` (`com.playata.framework.data.constants.ConstantsData`, @438615–@438886), com cache em `v._constantsDataCache` e `v.clearCache()`. Padrão gerado para cada secção:

```js
<Classe>.get_constantsData = function(){return v.getConstantsData("<seccao>")};
<Classe>.get_count         = function(){return v.getCount("<seccao>")};
<Classe>.getIds            = function(a){return v.getStringVectorIds("<seccao>",a)}; // ou getIntVectorIds
<Classe>.exists            = function(a){return v.getIdExists("<seccao>",a)};
<Classe>.fromId            = function(a){return v.getConstantsDataObject(a,<Classe>)}; // null se nao existir
```

`fromId` devolve **null** quando a secção existe mas o id não (@438615: `if(null==m || !hasOwnProperty(m,a) || null==field(m,a)) return null`).

---

## 2. Constantes escalares de balanceamento (objeto `x`)

Definição dos getters — offset **@470477–@471650** (bloco contíguo, todos via `T.current.getInt/getNumber`):

```js
x.get_sidekick_max_level      =function(){return T.current.getInt   ("sidekick_max_level")};
x.get_sidekick_min_required_level=function(){return T.current.getInt("sidekick_min_required_level")};
x.get_sidekick_teaser_level   =function(){return T.current.getInt   ("sidekick_teaser_level")};
x.get_sidekick_stat_factor_common   =function(){return T.current.getNumber("sidekick_stat_factor_common")};
x.get_sidekick_stat_factor_rare     =function(){return T.current.getNumber("sidekick_stat_factor_rare")};
x.get_sidekick_stat_factor_epic     =function(){return T.current.getNumber("sidekick_stat_factor_epic")};
x.get_sidekick_stat_factor_legendary=function(){return T.current.getNumber("sidekick_stat_factor_legendary")};
x.get_sidekick_rename_premium_currency_amount=function(){return T.current.getInt("sidekick_rename_premium_currency_amount")};
x.get_sidekick_merge_premium_currency_amount =function(){return T.current.getInt("sidekick_merge_premium_currency_amount")};
x.get_sidekick_reskill_premium_currency_amount=function(){return T.current.getInt("sidekick_reskill_premium_currency_amount")};
x.get_sidekick_sew_premium_currency_amount   =function(){return T.current.getInt("sidekick_sew_premium_currency_amount")};
```

(Existe ainda uma tabela espelho `get_X:"get_X"` em @517591–@518400 — mapa de propriedades gerado pelo compilador Haxe, sem lógica própria.)

### 2.1 Tabela-resumo

| Chave esperada nas constants | Tipo | Usado para | Offsets de uso relevantes |
|---|---|---|---|
| `sidekick_max_level` | int | XP/nível (secção 6), ativar botão "Coser" (`btnSew`) só no nível máximo (@6906405, @6935097) | def @470491; usos @1509461… |
| `sidekick_min_required_level` | int | Nível mín. do **personagem** para possuir/usar sidekick; gating de UI e de uso do item tipo 9 | @199775, @202332, @328495, @4040008, @6020996, @6079562, @6338360, @6551429, @6926513, @6929113, @7774532, @8033574 |
| `sidekick_teaser_level` | int | Mostrar o "teaser" do painel de sidekick antes do nível mínimo (@6928977, @6929968) | def @470661 |
| `sidekick_stat_factor_common` | number (fração) | Fórmula de stats, qualidade 1 (@247296) | def @470750 |
| `sidekick_stat_factor_rare` | number | qualidade 2 (@247348) | def @470854 |
| `sidekick_stat_factor_epic` | number | qualidade 3 (@247397) | def @470955 |
| `sidekick_stat_factor_legendary` | number | qualidade 4 (@247446) | def @471055 |
| `sidekick_rename_premium_currency_amount` | int | custo (moeda premium) de renomear — diálogo `DialogSidekickRename` (@3424355, erro premium @3425905) | def @471165 |
| `sidekick_merge_premium_currency_amount` | int | custo de fundir sidekicks — `DialogSidekickMergeStepConfirm` (@3414940, erro premium @3417493) | def @471290 |
| `sidekick_reskill_premium_currency_amount` | int | custo de reskill — botão em `UiSidekickInfo` (@6901566, erro premium @6910841) | def @471414 |
| `sidekick_sew_premium_currency_amount` | int | custo de "coser" (skin) — `UiSidekickSewStepConfirm` (@6945026, erro premium @6946781) | def @471541 |

### 2.2 Constantes relacionadas (fora do prefixo `sidekick_`, mas usadas nos mesmos fluxos)

Lidas por `Q.itemBuyPrice`/`Q.itemSellPrice`/`Cd.get_sellPrice` (classe `Q` = `com.playata.application.data.GameUtil`, @~247000/@1517642):

| Chave | Uso |
|---|---|
| `item_sidekick_sell_price_divider` | divide o total de stats para calcular preço base de venda do sidekick |
| `item_sidekick_buy_price_factor` | multiplica o preço de compra |
| `item_sidekick_sell_price_multiplier` | multiplica o preço final de venda |

### 2.3 O que acontece no cliente se faltarem

- **Chave ausente das constants** → `T.constant()` lança `Error in Constants! Trying to retrieve unknown constant! key=<chave>` na primeira chamada de `x.get_*` correspondente. Como quase todos os painéis chamam `x.get_sidekick_min_required_level()` no `updateCharacter`, a ausência desta chave rebenta a UI do jogo inteira (loja, duelos, ligas).
- **Valor com tipo errado** (`getInt` sobre texto não numérico) → `p.parseInt` devolve `NaN`/`0` sem exceção.
- **Valores REAIS** (confirmados a partir de `assets/data/constants_json.data` na raiz
  do projeto, JSON zlib, extraído em 2026-08 para `server-laravel/data/reference/`):
  `sidekick_max_level`=30, `sidekick_min_required_level`=**60** (não 10 como se supôs),
  `sidekick_teaser_level`=30, fatores de stat: common=0.01, rare=0.01, epic=0.017,
  legendary=0.025; custos: rename=30, merge=15, reskill=1, sew=5 donuts;
  `item_sidekick_buy_price_factor`=10, `item_sidekick_sell_price_divider`=5,
  `item_sidekick_sell_price_multiplier`=2.

---

## 3. Secções constants de sidekick

Enumeração exaustiva das secções que o cliente lê (varrimento de `getStringVectorIds(`, `getIdExists(`, `getConstantsDataObject(` e literais de secção). Relacionadas com sidekicks:

| Secção (CONSTANTS_KEY) | Classe DO (minificado) | Nome completo (registo `g[...]`) | ID_NAME | Offset |
|---|---|---|---|---|
| `sidekick_levels` | `Jk` | `com.playata.application.data.constants.CSidekickLevel` | `level` | @691751–@692500; registo estático @15481588 |
| `sidekick_skills` | `He` | `com.playata.application.data.constants.CSidekickSkill` | `id` | @692500–@695000 |
| `sidekick_data` | `Sh` | `com.playata.application.data.constants.CSidekickData` | `identifier` | @689300–@690350 |
| `battle_effects` | `cj` | `com.playata.application.data.constants.CBattleEffect` | `id` | @439416–@440800 |

Não existe secção `sidekick_battle_effects` nem `sidekick_battle_effect_ids` (0 ocorrências como chave de secção; o campo `sidekick_battle_effect_ids` vive **dentro** de `battle_effects`). Não existe secção `sidekick_item_skills` nas constants — é um **campo** opcional de respostas de ações (`c.hasData("sidekick_item_skills",!0)` → `refreshSidekickItemSkills(...)`, @2009836) e um DO próprio (`DOSidekickItemSkills`, ver secção 8).

### 3.1 Secção `sidekick_levels` → classe `Jk` (`CSidekickLevel`)

Registos indexados por **nível** (`ID_NAME="level"`, ids inteiros 1..N). Campos lidos:

| Campo JSON | Método | Tipo |
|---|---|---|
| `level` | `Jk.prototype.get_level` → `getInt("level")` | int |
| `xp` | `Jk.prototype.get_xp` → `getInt("xp")` | int |

Semântica: **`xp` é o total acumulado de XP necessário para atingir esse nível** (limiar inferior do nível — ver secção 6). Estáticos úteis: `Jk.minId/maxId(filter)`, `Jk.minIdSidekickLevel/maxIdSidekickLevel`, `Jk.getSidekickLevels(filter)`, `Jk.exists(level)`, `Jk.fromId(level)`.

**Formato esperado** (objeto id → registo):

```json
"sidekick_levels": {
  "1": {"level": 1, "xp": 0},
  "2": {"level": 2, "xp": 30},
  "...": {"...": "..."},
  "30": {"level": 30, "xp": 123456}
}
```

### 3.2 Secção `sidekick_skills` → classe `He` (`CSidekickSkill`)

Offset @692449 (início da classe) até @695000. Campos lidos (lista completa dos getters):

| Campo JSON | Método | Tipo | Notas |
|---|---|---|---|
| `id` | `get_id` | int | identificador numérico da skill (usado em `stage{1,2,3}_skill_id`) |
| `identifier` | `get_identifier` | string | chave i18n e de ícone (`sidekick_skill/<identifier>/name`, ícone `sidekick_skills/<identifier>_i.webp`) |
| `sidekick_battle_effect_id` | `get_sidekickBattleEffectId` | int | liga a skill ao "sidekick battle effect" |
| `stage` | `get_stage` | int | 1/2/3 — desbloqueada a nível 10×stage (secção 8) |
| `value1` | `get_value1` | number | fração (0.15 = 15%); o wrapper `jk` tolera ausência/valor não numérico → `null` |
| `value2` | `get_value2` | number | idem |
| `value3` | `get_value3` | number | idem |
| `type` | `get_type` | int | tipo da skill (lido, usado por lógica de batalha externa) |
| `charges` | `get_charges` | int | `-1` = usos ilimitados (`bd.multiUsePossible`: `-1==He.fromId(a).get_charges()`) |
| `fixed_sidekick` | `get_fixedSidekick` | vector de strings | sidekicks "fixos" a que a skill pertence (skills especiais de skin/season) |
| `legendary` | `get_legendary` | bool | skill lendária |
| `once_per_group_battle` | `get_oncePerGroupBattle` | bool | só 1 uso por batalha de grupo |

Estáticos: `He.getSidekickSkills(filter)`, `He.exists(id)`, `He.fromId(id)`, `minId/maxId/minIdSidekickSkill/maxIdSidekickSkill`.

Wrapper de instância `jk` (`com.playata.application.data.sidekick.SidekickSkill`, @1521716–@1524547):

- `new jk(skillId)`; `get_name` → `text("sidekick_skill/<identifier>/name")`; `get_shortDescription` → `.../short_description`; `get_description` → `jk.getSkillDescription(identifier, battleEffectId, value1..3)` que formata os valores ×100 com 2 casas decimais (`ua.roundDecimal(100*v,2)`) e escolhe a variante da chave consoante nº de parâmetros. Para os battle effects 16, 18 e 22 usa `value1` também no 2.º parâmetro (`16==bd.sidekickBattleEffectToBattleEffect(b)? d=e : 18==...? d=e : 22==...&&(d=e)`).
- `isLocked(character)` → `character.get_level() < 10*this.get_stage()`.
- `get_iconUrl` → `ma.assetURL("sidekick_skills/"+identifier+"_i","webp")`.
- `get_isLegendary`, `get_fixedSidekickId`, `get_charges`, `get_oncePerGroupBattle`.

### 3.3 Secção `sidekick_data` → classe `Sh` (`CSidekickData`)

Offset @689300–@690350. Indexada por **identifier** (string). Lista completa de campos:

| Campo JSON | Método | Tipo | Uso |
|---|---|---|---|
| `identifier` | `get_identifier` | string | id da entrada (ex.: `sidekick_dog3`) |
| `special_sidekick` | `get_specialSidekick` | bool | marcador de sidekick especial |
| `licence_sidekick` | `get_licenceSidekick` | bool | sidekick obtido por licença |
| `offset_x`, `offset_y` | `get_offsetX/Y` | number | posição no painel (usado com `_useSidekickDataPosition`, @6874785) |
| `avatar_1_offset_x/y`, `avatar_1_scale` | `get_avatar1*` | number | posicionamento do ícone junto ao avatar p/ variação 1 |
| `avatar_2_offset_x/y`, `avatar_2_scale` | `get_avatar2*` | number | variação 2 (nível 15+) |
| `avatar_3_offset_x/y`, `avatar_3_scale` | `get_avatar3*` | number | variação 3 (nível 30+) |
| `panel_offset_x/y`, `panel_scale` | `get_panel*` | number | posicionamento no painel dedicado |

Consumo típico (@6877013/@6880296): `Sh.exists(sidekick.get_displayIdentifier())` → `Sh.fromId(...)` → aplica offsets/scale conforme `get_displayVariation()` (caso 1/2/3); fallback sem secção: `set_y(61)` e escala padrão. Estáticos: `Sh.getIds(filter)`, `Sh.getSidekicksData(filter)`, `Sh.exists(id)`, `Sh.fromId(id)`.

### 3.4 Secção `battle_effects` → classe `cj` (`CBattleEffect`)

Offset @439416–@440800. Indexada por `id` (int). Campos:

| Campo JSON | Método | Tipo | Notas |
|---|---|---|---|
| `id` | `get_id` | int | id do battle effect (agrupador) |
| `sidekick_battle_effect_ids` | `get_sidekickBattleEffectIds` | vector de int | lista de efeitos "de sidekick" que pertencem a este battle effect |
| `value1` | `get_value1` | **bool** | se o slot value1 do efeito é apresentado/usado |
| `value2` | `get_value2` | **bool** | idem |
| `value3` | `get_value3` | **bool** | idem |
| `round_start_effect` | `get_roundStartEffect` | bool | dispara no início do round |
| `after_hit_effect` | `get_afterHitEffect` | bool | dispara após ser atingido |
| `single_round_effect` | `get_singleRoundEffect` | bool | dura um único round |

Nota importante: em `CBattleEffect` os `value1..3` são **booleanos** (flags "este efeito expõe o valor N?"), enquanto em `CSidekickSkill` os `value1..3` são **números** (os valores reais). A junção faz-se em `bd.getBattleEffectSkill` (secção 4).

IDs de `battle_effects` referenciados por código no cliente (hardcoded):

| id | Contexto |
|---|---|
| 16, 18, 22 | skills cujo uso é (des)ativado quando não há DOT num round (`deactivateSkillUsage(h,!bd.multiUsePossible(h))`) — @7671484…@7674596 (batalha normal, guilda, covil) |
| 104 | bónus de dano de arma de arremesso (`Za.getMissileDamage`, @1284814) |
| 105–108 | bónus % de stat base passado ao personagem (`Y.prototype.sidekickBaseStatBonus`, @354748): 105 força, 106 vigor(stamina), 107 crítico, 108 esquiva |
| 109 | bónus a **todos** os stats (aplicado nos 4 getters) |

---

## 4. Battle effects — classe `bd` e mapeamentos

Classe `bd` = `com.playata.application.data.battle.BattleEffect` (@264700–@265100). Bloco completo extraído:

```js
var bd=function(){};
bd.getMediKitSidekickSkill=function(){return He.fromId(266)};
bd.getWeaponOilSidekickSkill=function(){return He.fromId(267)};
bd.sidekickBattleEffectToBattleEffect=function(a){        // sidekick_battle_effect_id -> battle_effects.id
  for(var b=0,c=cj.get_ids();b<c.length;){var e=c[b];++b;
    if(0<=cj.fromId(e).get_sidekickBattleEffectIds().indexOf(a))return e}return 0};
bd.sidekickBattleEffectToBattleEffectData=function(a){a=bd.sidekickBattleEffectToBattleEffect(a);return cj.fromId(a)};
bd.sidekickBattleEffectToSidekickSkill=function(a){       // sidekick_battle_effect_id -> sidekick_skills.id
  for(var b=0,c=He.get_ids();b<c.length;){var e=c[b];++b;
    if(He.fromId(e).get_sidekickBattleEffectId()==a)return e}return 0};
bd.sidekickBattleEffectToSidekickSkillData=function(a){a=bd.sidekickBattleEffectToSidekickSkill(a);return He.fromId(a)};
bd.battleEffectToSidekickBattleEffects=function(a){       // battle_effects.id -> [sidekick_battle_effect_ids]
  return cj.fromId(a).get_sidekickBattleEffectIds()};
bd.isRoundStartEffect=function(a){return bd.sidekickBattleEffectToBattleEffectData(a).get_roundStartEffect()};
bd.isAfterHitEffect =function(a){return bd.sidekickBattleEffectToBattleEffectData(a).get_afterHitEffect()};
bd.isSingleEvent    =function(a){return bd.sidekickBattleEffectToBattleEffectData(a).get_singleRoundEffect()};
bd.multiUsePossible =function(a){a=bd.sidekickBattleEffectToSidekickSkill(a);
  return He.exists(a)?-1==He.fromId(a).get_charges():!1};
bd.getBattleEffectSkill=function(a){var b=null;
  if(0<a){var c=bd.sidekickBattleEffectToBattleEffectData(a);
    if(null!=c){var e=bd.sidekickBattleEffectToSidekickSkillData(a);
      b=new LA;
      c.get_value1()&&c.get_value2()&&c.get_value3()?b.refresh(a,e.get_value1(),e.get_value2(),e.get_value3()):
      c.get_value1()&&c.get_value2()&&!c.get_value3()?b.refresh(a,e.get_value1(),e.get_value2()):
      !c.get_value1()||c.get_value2()||c.get_value3()||b.refresh(a,e.get_value1())}}return b};
```

Fluxo de mapeamento skill ↔ efeito ↔ valores:

```
sidekick_skills[i].sidekick_battle_effect_id  ──(procura linear em battle_effects)──▶  battle_effects[j].id
battle_effects[j].sidekick_battle_effect_ids  ──▶ lista de sidekick_battle_effect_id (para efeitos agrupados)
battle_effects[j].value1..3 (bool)            ── decide quantos values da skill se mostram
sidekick_skills[i].value1..3 (number)         ── valores reais (frações; UI mostra ×100%)
round_start_effect / after_hit_effect / single_round_effect ── momento de trigger
```

Classe `LA` = `com.playata.application.data.battle.BattleEffectSkill` (@267000–@267600): `refresh(effectId, v1[, v2[, v3]])`, `get_iconUrl` → `ma.assetURL("sidekick_skills/"+this.get_identifier()+"_i","webp")` (ícone da skill associada), usada pela barra de skills de batalha (`UiBattleSidekickSkills`/`UiBattleSidekickSkillIcon`).

Skills fixas por id: **266 = medikit de batalha de guilda** ("Kit de Primeiros Socorros", i18n `guild_battle_medikit`), **267 = óleo de arma** ("Turbo-Foguete", i18n `guild_battle_weapon_oil`).

Bónus passivos ao personagem (`com.playata...Character`, classe `Y`, @354748/@398884):

```js
Y.prototype.sidekickBaseStatBonus=function(a,b){        // a=stat(1..4), b=battle_effect id (105..109)
  b=bd.battleEffectToSidekickBattleEffects(b);
  for(var c=0,e=0;e<b.length;){var m=b[e];++e;
    m=bd.sidekickBattleEffectToSidekickSkill(m);
    if(this.get_currentSidekick().hasUnlockedSkill(m))
      switch(m=He.fromId(m).get_value1(),a){
        case 1:c+=Math.ceil(this.get_statBaseStamina()*m);break;
        case 2:c+=Math.ceil(this.get_statBaseStrength()*m);break;
        case 3:c+=Math.ceil(this.get_statBaseCriticalRating()*m);break;
        case 4:c+=Math.ceil(this.get_statBaseDodgeRating()*m)}}
  return c};

Y.prototype.get_sidekickStamina =function(){return null==this.get_currentSidekick()?0:
  this.get_currentSidekick().get_statStamina()+this.sidekickBaseStatBonus(1,106)+this.sidekickBaseStatBonus(1,109)};
// analogamente get_sidekickStrength(2,105)+(2,109), get_sidekickCriticalRating(3,107)+(3,109),
// get_sidekickDodgeRating(4,108)+(4,109)
```

Ou seja: o contributo do sidekick aos stats do personagem = stats próprios do sidekick **+ teto** de bónus percentuais sobre os stats base do personagem, só para skills já desbloqueadas (`hasUnlockedSkill`, secção 8).

As temporadas repetem o mecanismo com campos próprios em `season_locations`/`season_points` (`pw` = `CSeasonSeasonPoints`, @687124): `sidekick_battle_effect_value_1/2/3` (number) + `effectSkillId/effectSkillValue1` no progresso de temporada (@398884).

---

## 5. Fórmula de stats do sidekick

Implementação completa (classe `Q` = `com.playata.application.data.GameUtil`, offset **@247296**):

```js
Q.getSidekickStatPlus=function(a,b,c){                    // ganho entre nivel b-1 e b
  var e=Q.getNewSidekickStatValue(a,c,b-1);
  return Q.getNewSidekickStatValue(a,c,b)-e};

Q.getNewSidekickStatValue=function(a,b,c){                // a=quality(1..4), b=stat_base, c=level
  var e=0;
  switch(a){
    case 1:e=x.get_sidekick_stat_factor_common();break;
    case 2:e=x.get_sidekick_stat_factor_rare();break;
    case 3:e=x.get_sidekick_stat_factor_epic();break;
    case 4:e=x.get_sidekick_stat_factor_legendary()}
  return Math.round(b+b*e*(c-1))};
```

**Fórmula: `stat(nível) = round(stat_base + stat_base × fator_qualidade × (nível − 1))`**

- `stat_base` vem do servidor no DO do sidekick (`stat_base_stamina/strength/critical_rating/dodge_rating`).
- O crescimento é **linear** no nível; `fator_qualidade` é uma fração (ex.: com 0.02, um sidekick com base 100 sobe ~2/ponto de nível).
- O cliente **nunca calcula o stat atual para mostrar** — mostra `stat_stamina` etc. recebidos do servidor; a fórmula serve para: pré-visualização a outro nível (`staminaAtLevel(a)`, @1510108), diff entre níveis (`getLevelStatDiffText`), ganho por nível (`getLevelUpStatValue`), e preço de venda (`get_sellPrice`, @1517642). Conclusão: o servidor tem de aplicar a mesma fórmula ao subir níveis, senão as pré-visualizações divergem dos valores reais.

Usos diretos de `getNewSidekickStatValue` (~42 ocorrências): `Cd.staminaAtLevel/strengthAtLevel/criticalRatingAtLevel/dodgeRatingAtLevel/totalStatsAtLevel` (@1510108–@1510400), `Cd.getLevelUpStatValue` (+variante com melhoria de item), `Cd.get_sellPrice` (soma dos 4 stats ao nível atual), `Q.getSidekickStatPlus`.

Preço de venda (`Cd.prototype.get_sellPrice`, @1517300–@1517642):

```js
totalStats = Σ getNewSidekickStatValue(quality, statBase_i − melhoria_i, level);
base = ceil(totalStats / x.get_item_sidekick_sell_price_divider());
buy  = round(Q.itemBuyPrice(quality, base, 1) * x.get_item_sidekick_buy_price_factor());
sell = Q.itemSellPrice(buy, premiumOnly);                       // * item_sell_price_percentage (e premium factor)
precoFinal = round((sell + melhoria) * x.get_item_sidekick_sell_price_multiplier());
```

Qualidades (nomes partilhados com itens, `Za.getQualityName`): 1=`general/item_common`, 2=rare, 3=epic, 4=legendary.

---

## 6. XP e nível

Métodos em `Cd` (`com.playata.application.data.sidekick.Sidekick`), offset **@1509461–@1510211**:

```js
Cd.prototype.get_xpToLevelUp=function(){
  var a=x.get_sidekick_max_level();
  if(this.get_level()==a)return"";                     // nivel maximo -> sem texto
  a=this.get_xp();
  var b=Jk.fromId(this.get_level()+1).get_xp();
  return f.current.formatHugeNumber(b-a)};             // xp(prox nivel) - xp atual (TOTAL acumulado)

Cd.prototype.get_xpCurrentLevel=function(){            // largura da barra: xp necessario neste nivel
  ...  Jk.fromId(this.get_level()).get_xp() ... Jk.fromId(this.get_level()+1).get_xp() ...
  return formatHugeNumber(b - a)};

Cd.prototype.get_xpInThisLevel=function(){             // xp ja ganho dentro do nivel atual
  ...  formatHugeNumber(this.get_xp() - Jk.fromId(this.get_level()).get_xp())};

Cd.prototype.get_levelProgress=function(){
  var a=x.get_sidekick_max_level();
  if(this.get_level()==a)return 1;
  a=Jk.fromId(this.get_level()).get_xp();
  var b=Jk.fromId(this.get_level()+1).get_xp()-a;      // intervalo do nivel
  return (this.get_xp()-a)/b};                         // 0..1
```

Conclusões:

- **XP é total acumulado**: `xp(nível N)` na tabela `sidekick_levels` é o limiar mínimo de XP para estar no nível N. O cliente assume que `Jk.fromId(level+1).get_xp() > Jk.fromId(level).get_xp()`.
- **Não há validação de XP vs tabela**: o cliente confia no par `(level, xp)` enviado pelo servidor; se `xp` for inconsistente, a barra/percentagem fica errada mas nada rebenta. Só há guarda contra o nível máximo (`== sidekick_max_level`).
- Subida de nível detetada no cliente por diff de `level` em `Cd.update` (@1504922) → evento `CharacterMessage.notifySidekickLevelUp` → abre diálogo `DialogSidekickLevelUp` (@2188760). Desbloqueio inicial: `notifySidekickUnlock` disparado quando o **personagem** sobe para exactamente `x.get_sidekick_min_required_level()` (@328495) → diálogo `dialog/sidekicks_unlocked`.
- Desbloqueio de skills por estágio: `Cd.prototype.hasUnlockedStageSkill=function(a){return this.get_level()>=10*a}` e `hasUnlockedSkill(id)`: stage1 a partir do nível **10**, stage2 **20**, stage3 **30** (@1512927). Coerente com `jk.isLocked` (`level < 10*stage`).

---

## 7. `displayVariation` e URLs de assets

Em `Cd` (@1511973–@1512927):

```js
Cd.prototype.get_displayVariation=function(){
  var a=this.get_isSewed()?this.get_sewedLevel():this.get_level();
  return 30<=a?3 : 15<=a?2 : 1};
```

Confirma o mapeamento: nível **1–14 → 1**, **15–29 → 2**, **30+ → 3** (usa `sewed_level` se o sidekick foi "cosido" a uma skin).

URLs gerados (todos `.webp`, via `ma.assetURL(nome,"webp")`):

| Getter | Path | Notas |
|---|---|---|
| `get_imageUrl` | `sidekicks/<assetIdentifier>_<displayVariation>.webp` | imagem grande |
| `get_iconUrl` | `sidekicks/<assetIdentifier>_<displayVariation>_i.webp` | ícone (usado também no teaser @6929968) |
| `get_backgroundImageUrl` | `sidekicks/<a>_background.webp` | `a` = identifier com sufixo numérico aparado (remove dígitos 1–10) exceto lista branca: `sidekick_rio2016`, `sidekick_easter2`, `sidekick_easter3`, `sidekick_olympia_2020_tokyo_tanuki`, `sidekick_olympia_2020_tokyo_monkey`, qualquer `*server_launch*`, qualquer `*season*`; caso especial `sidekick_season_video_games1`→`sidekick_season_video_games` |
| `get_itemIconUrl` | `items/<displayIdentifier>_i.webp` | ícone na mochila (item tipo 9) |
| `get_stickerIconUrl` | `sidekicks/<assetIdentifier>_s.webp` | sticker |

- `assetIdentifier` = `displayIdentifier`, com caso especial `"sidekick_season_video_games"`→`"sidekick_season_video_games1"`; `displayIdentifier` = `sewed_identifier` se cosido, senão `identifier`.
- **Spine** (`SpineSidekick`, classe `QV`, @6876000): carrega `sidekicks/<assetIdentifier>` (json/atlas/webp na pasta `sidekicks/`) e escolhe a skin `"stage_"+displayVariation` do esqueleto; animações todas em loop excepto `idle` que corre primeiro.
- Estado real do disco (`public/assets/sidekicks/`, 27 ficheiros): existem apenas `sidekick_dog3_2_i.webp` (o placeholder do teaser!) e Spine completo de 6 skins: `leagueset_{bronze,silver,gold,champion,superhero}`, `speedserver_{globefish,int2,shark}`, `tuxedos`. **Faltam** todos os restantes webp/spine das ~212 variantes conhecidas no i18n — o cliente mostra placeholder/quebra de imagem se não existirem. A pasta `spine/` não tem nenhum sidekick.

---

## 8. Dados em runtime: DOSidekick e ações de rede

### 8.1 `DOSidekick` (classe `lf`, @896110) — campos esperados por sidekick

| Campo JSON | Tipo | Notas |
|---|---|---|
| `id` | int | |
| `character_id` | int | presença marca "sidekick real" vs entrada de coleção |
| `status` | int | estado interno |
| `identifier` | string | ex. `sidekick_dog3` |
| `sewed_identifier` | string | skin cosida (opcional) |
| `sewed_level` | int | nível da skin cosida |
| `name` | string | nome personalizado; "" → usar i18n |
| `quality` | int | 1..4 |
| `level` | int | |
| `xp` | int | total acumulado |
| `item_improvement_id` | int | sinal negativo indica melhoria aplicada (`get_hasImprovement`: `<0`) |
| `stat_base_stamina` / `strength` / `critical_rating` / `dodge_rating` | int | bases (pré-fórmula) |
| `stat_stamina` / `strength` / `critical_rating` / `dodge_rating` | int | valores finais ao nível atual (servidor calcula) |
| `stage1_skill_id` / `stage2_skill_id` / `stage3_skill_id` | int | ids de `sidekick_skills`; 0 = nenhum |
| *(opcionais)* `special_skill_id`, `sources` (vector string), `now_owned`, `ever_owned` | | entradas de coleção/loja (`Cd.get_specialSkill`, `get_sources`, `get_hasNowOwned/EverOwned`) |

`Cd` (`Sidekick`) estende `lf` e acrescenta lógica pura (sem novos campos de rede). Coleção: `get_isReal()` = `!hasData("ever_owned")` — entradas sem `ever_owned` são "fantasmas" do álbum.

`DOSidekickItemSkills` (classe `IT`, @897467; wrapper `Z5`=`SidekickItemSkills` @1521716): `stage1_skill_id`, `stage2_skill_id`, `stage3_skill_id` — skills concedidas por **itens**; chega em respostas como campo `sidekick_item_skills` (@2009836 → `character.refreshSidekickItemSkills`).

### 8.2 Campos de resposta usados

- `sidekicks` (array de `lf`) e `all_sidekicks` (coleção), `merged_sidekick`, `sidekick` (singular, pós-ação) — wrappers `ta` (@2075768–@2075805); processados em @2020318 (`refreshSidekicks`/`refreshSidekick`).
- Login: `reskill_enabled` (bool) — controla aviso de reskill (`An.onUserLogin`, @2003039).
- Sidekick ativo: **não existe campo próprio** — o personagem usa o slot do inventário: `get_currentSidekick()` lê `inventory.sidekick_id` (`this._inventory.hasData("sidekick_id") && 0!=this._inventory.get_sidekickId()`, @~354000). Item tipo **9** = consumível que dá sidekick (uso validado contra `x.get_sidekick_min_required_level()`, @4040008/@6020996).

### 8.3 Ações HTTP (`POST /request.php`, envelope `{data,error}`)

Encontradas por varrimento de `sendActionRequest("...idekick...")`:

| Ação | Parâmetros enviados | Sucesso → resposta | Erros tratados |
|---|---|---|---|
| `getSidekicks` | `{}` | refresca lista (@3400243) | genéricos |
| `mergeSidekick` | `{target_sidekick_id, source_sidekick_id, skill_stage, confirm:false}` (confirmação final com `confirm:true` implícito no passo seguinte) | `merged_sidekick` (@3415208/@3417493) | `errRemovePremiumCurrencyNotEnough` → diálogo "sem moedas premium" com custo `sidekick_merge_premium_currency_amount` |
| `renameSidekick` | `{name, sidekick_id}` (@3425154) | `sidekick` atualizado | `errRemovePremiumCurrencyNotEnough`, `errRenameSameName`, `errRenameInvalidName` (limites `get_character_name_length_min/max`) |
| `reskillSidekick` | `{sidekick_id}` (@6905531/@6910841) | `sidekick` atualizado (redistribui stats base) | `errRemovePremiumCurrencyNotEnough` |
| `releaseSidekick` | `{sidekick_id}` (@6904740) | liberta slot | — |
| `unbindSidekick` | `{sidekick_id}` (@6905011) | separa skin/melhoria | — |
| `setSidekick` | (ativo/in outfit) (@6910841) | `data` global | — |
| `setSidekickOrder` | `{sidekick_id, move_up, move_to_end, is_favorit}` (@3402419) | reordena lista/favoritos | — |
| `sewSidekick` | `{target_sidekick_id, sewed_identifier, sewed_level}` (@6945945) | `sidekick` com skin | `errRemovePremiumCurrencyNotEnough` |

Custos premium são mostrados pelo cliente a partir das constants (botões com `set_showAsCost(!0)`); o servidor deve cobrar os mesmos valores.

---

## 9. i18n

As chaves são resolvidas com `f.current.text(chave, args...)` sobre os textos carregados de `assets/i18n/<locale>/text.data` (**zlib + JSON**; confirmado ao descomprimir `pt_BR/text.data`: magic zlib `78da`, JSON com 20 033 chaves). Locais presentes no disco: **apenas `pt_BR`** — não existe `pt_PT`; o locale vem da configuração (`n.info.get_defaultLocale()`) e o servidor deve servir a pasta certa.

Chaves usadas pelo código do cliente (âncoras `text("sidekick...`):

| Padrão | Sub-chaves | Contagem pt_BR | Exemplos |
|---|---|---|---|
| `sidekick/<identifier>/name` | `name` | **212 identifiers** | `sidekick/sidekick_dog1/name = "Alfa"`, `sidekick_dog2="Brutus"`, `sidekick_dog3="Rambo"`, `sidekick_cat1="Watson"`, `sidekick_leagueset_gold="Striker"` |
| `sidekick_skill/<identifier>/name|short_description|description` | 3 | **289 identifiers** | `stage1_1..112`, `stage2_1..44`, `stage3_1..53`, `guild_battle_medikit`, `guild_battle_weapon_oil`, `season_*` (≈80) |
| `sidekick_source/<source>` | texto livre | **191 chaves** | `shop_general = "no brechó"`, `slotmachine = "no Cassino"`, `goal`, `unlock_reward`, `not_available`, `event_*`, `season_premium_*`, `surprise_box_*`, `tower_event_*`, `worldboss_event_*`, `legendary_dungeon_*`, `item_offer_*`, `hidden_object_event_*` |

- `Cd.get_name()`: se `name` (custom) for `""` → `text("sidekick/<identifier>/name")`; `get_defaultName()` igual.
- `Cd.get_sourceInfo()`: para cada string em `sources` → `"- "+text("sidekick_source/<s>")+"\n"` (dedup).
- Placeholders nas descrições de skill: `%1`,`%2`,`%3` (valores ×100, sufixo `[percent]`); suporte a género `[m:...|f:...]` nos textos.
- Outras chaves de UI relacionadas (já no text.data pt_BR): `general/sidekick_info_none`, `general/sidekick_info_unlock_info` ("%1" = min level), `general/sidekick_level_general_tooltip`, `general/sidekick_xp_tooltip` (`%1`=xpInThisLevel, `%2`=xpCurrentLevel, `%3`=xpToLevelUp), `general/sidekick_skills`, `general/sidekick_level_compare_caption`, `dialog/sidekick_info|sidekick_info_general|sidekick_level_up|sidekick_merge_confirm|sidekick_merge_selection|sidekick_merge_skill|sidekick_rename|sidekick_sew_confirm|sidekick_sew_selection|sidekick_sew_skin|sidekicks|sidekicks_unlocked`, `error/sidekick_invalid_name_text`, `error/sidekick_merge_selection_no_sidekicks/text`, `payment/item_offer/sidekick_ghostbusters_offer/description_not_buyable`.

**Implicação para o servidor**: qualquer `identifier` novo em `sidekick_data`/`sidekick_skills` precisa das chaves i18n acima, senão os textos aparecem vazios/a chave crua; a lista canónica de identifiers existentes está no `text.data` (212 sidekicks, 289 skills).

---

## 10. Verificação no disco

1. **Ficheiro de constants local**: `find public/assets -iname '*constants*'` → **nenhum resultado**. O caminho que o cliente pede é `assets/data/constants_json.data` (JSON zlib). A pasta `public/assets/data` é um **symlink partido** → `/home/laliene/HeroZero_Server/assets/data` (não existe). Logo, hoje o cliente só recebe constants se a resposta HTTP/login as trouxer; o arranque com asset local falha ou fica sem secções.
2. **Captura real do nosso servidor** (`server-laravel/data/initGame.json`, topo: `constants`, `extended_config`, …): dentro de `constants` (128 chaves), **todas** as secções de sidekick estão a `null`:
   `battle_effects=null, sidekick_data=null, sidekick_levels=null, sidekick_skills=null`
   (também `guild_battle_attack_cost`, `guild_battle_tactics`, etc.). Não há valores reais de sidekick para extrair daqui — é precisamente o gap a preencher. Também não há scalares `sidekick_*` definidos.
3. **autoLoginUser.json**: `sidekicks: []` (lista vazia), `reskill_enabled: false` — estrutura de runtime coerente com a secção 8.
4. **tools/**: `gen_sidekicks_list.py` e `sidekicks.txt` referidos em `docs/sidekicks/pets_sidekicks.md` (linha 47) **não existem** no disco (nem na raiz nem em tools/). tools/ tem apenas `allclasses.txt` (que confirma as classes: `CSidekickData`, `CSidekickLevel`, `CSidekickSkill`, `DOSidekick`, `DOSidekickItemSkills`, `Sidekick`, `SidekickFilter`, `SidekickFilterAll`, `SidekickItemSkills`, `SidekickSkill`, diálogos `DialogSidekick*`, UI `UiBattleSidekickSkillIcon/UiBattleSidekickSkills`, `SpineSidekick`, `SpineSidekickSewDog/SewSue`, etc.), `capture/` (vazio) e scripts PHP genéricos.
5. **Assets**: ver lacunas na secção 7 (só 1 ícone + 6 skins Spine em `sidekicks/`; `spine/` sem sidekicks; `i18n/` só `pt_BR`).

---

## 11. Conclusões — o que o servidor precisa de fornecer

Checklist consolidada (tudo verificado contra o cliente):

1. **Secções constants obrigatórias** (no `initGame`/login e idealmente em `public/assets/data/constants_json.data`, JSON comprimido com zlib):
   - `sidekick_levels`: registos `{level:int, xp:int}` para ids 1..`sidekick_max_level`, `xp` **acumulado** e crescente;
   - `sidekick_skills`: `{id:int, identifier:string, sidekick_battle_effect_id:int, stage:int(1-3), value1..3:number(fração), type:int, charges:int(-1=∞), fixed_sidekick:[string], legendary:bool, once_per_group_battle:bool}`;
   - `sidekick_data`: por identifier — `{identifier, special_sidekick:bool, licence_sidekick:bool, offset_x/y, avatar_1..3_offset_x/y, avatar_1..3_scale, panel_offset_x/y, panel_scale}`;
   - `battle_effects`: `{id:int, sidekick_battle_effect_ids:[int], value1..3:bool, round_start_effect:bool, after_hit_effect:bool, single_round_effect:bool}` — incluir pelo menos os ids 16, 18, 22, 104–109 usados hardcoded.
   - Secção a `null` ⇒ crash do cliente (`unknown constant`) ao abrir qualquer ecrã que as leia.
2. **Escalares de balance**: `sidekick_max_level`, `sidekick_min_required_level`, `sidekick_teaser_level`, `sidekick_stat_factor_{common,rare,epic,legendary}` (fração!), `sidekick_{rename,merge,reskill,sew}_premium_currency_amount`, mais `item_sidekick_{sell_price_divider,buy_price_factor,sell_price_multiplier}`. Ausência de qualquer um ⇒ exceção no arranque das UIs.
3. **Consistência de fórmulas**: aplicar no servidor `stat = round(base + base*fator*(nivel-1))` ao subir nível/reskill, e manter `stat_base_*` coerentes com `stat_*`; XP acumulado compatível com a tabela.
4. **Runtime**: DO `sidekick` com todos os campos da secção 8.1; sidekick ativo via `inventory.sidekick_id`; respostas das 9 ações `*Sidekick*` com os campos listados; erros `errRemovePremiumCurrencyNotEnough`, `errRenameSameName`, `errRenameInvalidName`.
5. **Assets**: gerar/copiar `sidekicks/<identifier>_<variation>.webp`, `..._i.webp`, `..._background.webp`, `..._s.webp`, `sidekick_skills/<skill_identifier>_i.webp` e Spine `sidekicks/<identifier>{.json,.atlas,.webp}` com skins `stage_1/2/3`; hoje só existe o mínimo (placeholder `sidekick_dog3_2_i.webp` + 6 conjuntos de ligas/servidores rápidos).
6. **i18n**: servir `i18n/<locale>/text.data` (zlib+JSON) com as chaves `sidekick/*/name`, `sidekick_skill/*/name|short_description|description`, `sidekick_source/*`; criar pt-PT (hoje só existe pt_BR).
