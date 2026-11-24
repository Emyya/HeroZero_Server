# Extração do cliente — Protocolo das ações de sidekicks

Fonte: `server-laravel/public/js/HeroZero.min.js` (build HTML5 257, minificado).
Método: âncoras literais + janelas de contexto; offsets citados para regressão.
Complementa: [02_constantes_dados.md](02_constantes_dados.md), [03_combate.md](03_combate.md), [04_ui_ciclo_vida.md](04_ui_ciclo_vida.md)

---

## 0. Envelope e pipeline geral

Todas as ações seguem `sendActionRequest(action, params, callback)` → `POST /request.php`.
A resposta chega como `{data, error}`; o callback trata `a.action`, `a.error` e `a.data`.

**Pipeline central de atualização de estado** — função `onRefreshData` (@~2020318),
executada por `n.application.updateData(a.data)` em TODOS os handlers de sucesso:

```js
c.hasData("sidekicks",!0)&&null!=b.get_character()&&b.get_character().refreshSidekicks(c.get_sidekicks());
c.hasData("sidekick",!0)&&null!=b.get_character()&&b.get_character().refreshSidekick(c.get_sidekick());
```

Isto significa: **qualquer action pode devolver `data.sidekicks[]` (lista) e/ou
`data.sidekick{}` (singular)** e o cliente funde-os no estado local:

```js
// Y = classe Character do cliente
Y.prototype.refreshSidekicks=function(a){for(a=a.iterator();...;)this.refreshSidekick(...)};
Y.prototype.refreshSidekick=function(a){
  // procura por id em this._sidekicks; se existir -> b.update(a); senão push(new Cd(a))
};
```

No boot, `_sidekicks` nasce **`[]`** no construtor de Y. Se a resposta de login não
trouxer a chave `sidekicks`, a lista fica vazia (não crasha).

### Comportamento de DO com campos em falta (CORREÇÃO a docs antigas)

Classe base TypedObject (@252252–252900):

```js
hasData=function(a){ ... Object.prototype.hasOwnProperty.call(this._data,a) ... };
getData=function(a,b){return this.hasData(a)?r.field(this._data,a):b};
getInt=function(a,b){null==b&&(b=0);a=this.getData(a,b);return null==a?b:p.parseInt(p.string(a))};
getString=function(a,b){a=this.getData(a,b);return null==a?b:p.string(a)};
```

→ Campo em falta num DO de sidekick **devolve o default (0/"")**, não lança exceção.
O crash por chave em falta aplica-se só às secções *constants* (ver doc 02).
Ainda assim, enviar os campos todos é obrigatório porque getters específicos
(ex.: `Cd.get_xpCurrentLevel`) usam valores diretamente.

---

## 1. Contrato do DO sidekill (`lf` @894163 + wrapper `Cd` @1514286)

Campos crus lidos por `lf` (todos `getInt`/`getString`, default `0`/`""`):

| campo | tipo | uso |
|---|---|---|
| `id` | int | identidade |
| `character_id` | int | dono (distingue próprios de catálogo) |
| `status` | int | **só `status==1` aparece em "Meus"**, é equipável, mergeável e melhorável |
| `identifier` | string | chave do catálogo `sidekick_data` + i18n `sidekick/<id>/name` |
| `name` | string | nome custom ("" = nome i18n default) |
| `quality` | int | 1=common, 2=rare, 3=epic, 4=legendary (nome via `Za.getQualityName`) |
| `level` | int | 1..`sidekick_max_level`; variação visual 1-14→1,15-29→2,30+→3 |
| `xp` | int | acumulado; barra via tabela `sidekick_levels` |
| `item_improvement_id` | int | ver §8 |
| `stat_base_stamina/strength/critical_rating/dodge_rating` | int | base |
| `stat_stamina/strength/critical_rating/dodge_rating` | int | totais mostrados |
| `stage1_skill_id`,`stage2_skill_id`,`stage3_skill_id` | int | ids em `sidekick_skills` |
| `sewed_identifier` | string | skin cosida ("" = nenhuma) |
| `sewed_level` | int | nível da skin cosida (>0 = ativa) |

Extras só em itens do catálogo `all_sidekicks`: `ever_owned` (bool), `now_owned`
(bool), `sources` (vector de strings → i18n `sidekick_source/<x>`).
Nota `Cd.get_isReal(): return !this.hasData("ever_owned")` — presença de
`ever_owned` marca entrada de catálogo.

---

## 2. Ações — contrato completo

### 2.1 `getSidekicks`

Pedido: `{}` (sem parâmetros). Enviado pelo painel quando
`now - LAST_SIDEKICK_UPDATE > timeout`; senão reusa cache local.
`sd.forceReloadSidekicks()` (= `LAST_SIDEKICK_UPDATE=0`) força refetch após
merge/sew bem-sucedidos.

Resposta exigida:

```json
{ "all_sidekicks": [ {…DO completo de cada um dos ~212 do catálogo…} ] }
```

Handler `sd.handleRequests` (@3408996):

```js
case "getSidekicks":if(""==a.error){if(null!=this._content){
  n.application.updateData(a.data);sd.ALL_SIDEKICKS=[];
  for(a=a.get_appResponse().get_all_sidekicks().iterator();...)
    {b=new Cd(b);sd.ALL_SIDEKICKS.push(b)}
  sd.ALL_SIDEKICKS.sort(sd.sortByLevelAscName);
  this.updateMySidekicks();this.refreshList()}}
```

⚠️ `a.get_all_sidekicks().iterator()` é chamado **sem null-check** — se
`all_sidekicks` faltar na resposta, `null.iterator()` = crash. Lista vazia `[]`
é aceitável. Os "Meus" vêm do estado do personagem (`character.get_sidekicks()`,
alimentado pela chave `sidekicks` do boot/updateData), não desta resposta.

### 2.2 `setSidekick` (equipar/desequipar o ativo)

Pedido: `{sidekick_id}` (id do DO; desequipar = id que não existe na lista,
tipicamente `0`).

Resposta: `data` normal (com `inventory.sidekick_id` atualizado). O cliente faz
apenas `updateData(a.data)`; o lado visível deriva de
`character.get_currentSidekick()` (@~1515000):

```js
get_currentSidekick=function(){return null!=this._sidekicks&&null!=this._inventory
  &&this._inventory.hasData("sidekick_id")&&0!=this._inventory.get_sidekickId()
  ?this.getSidekickById(this._inventory.get_sidekickId()):null};
```

→ O ativo é definido **exclusivamente por `inventory.sidekick_id`**.
Erros: só `reportError`.

### 2.3 `renameSidekick`

Pedido: `{name, sidekick_id}`.

Resposta: **deve incluir `data.sidekick`** (DO atualizado) — o handler acede
sem null-check:

```js
""==a.error?(...
  this._sidekick.get_isActive()&&a.get_appResponse().get_sidekick().get_id()==this._sidekick.get_id()
    &&n.application.updateData(a.data),
  this.close(),B.showInfoDialog(...success...),
  this._onRename(a.get_appResponse().get_sidekick().get_name()))
:"errRemovePremiumCurrencyNotEnough"==a.error?
  A.get_instance().showNotEnoughPremiumDialog(x.get_sidekick_rename_premium_currency_amount())
:"errRenameSameName"==a.error? B.showErrorDial...
```

Erros tratados: `errRemovePremiumCurrencyNotEnough` (diálogo de donuts com custo
da constante `sidekick_rename_premium_currency_amount`), `errRenameSameName`.
Validação cliente: nome vazio → diálogo de erro local (sem pedido); limite prático
do servidor: coluna `varchar(32)`.

### 2.4 `releaseSidekick` (libertar/vender)

Pedido: `{sidekick_id}`. Confirmação client-side com preço de venda:
`text("dialog/sidekick_info/confirm_release_text", Q.getGameCurrencyString(this._sidekick.get_sellPrice()))`,
e aviso extra se houver outfits que referenciem o sidekick
(`get_outfits().getOutfitsWithSidekick(...)`).

Resposta: `data` normal. Sucesso → `_onRelease(sidekick,!0)` (remove das listas locais).
Se o sidekick libertado era o ativo, o servidor tem de pôr `inventory.sidekick_id=0`
(ou apontar para outro) na mesma transação.

### 2.5 `unbindSidekick` (desvincular sem apagar)

Pedido: `{sidekick_id}`. Resposta: `data` normal → `_onRelease(sidekick,!1)` +
refresh. Semântica observada: remove de outfits/equipamento mantendo o DO
(o oposto do release que apaga).

### 2.6 `reskillSidekick` (re-roll das skills)

Pedido: `{sidekick_id}`.

Resposta: `data` normal + opcionalmente `inactive_sidekick{}` e/ou `sidekick{}`;
ambos são aplicados se presentes:

```js
case "reskillSidekick":""==a.error?(n.application.updateData(a.data),
  null!=a.get_appResponse().get_inactive_sidekick()
    &&this._sidekick.update(new lf(a.get_appResponse().get_inactive_sidekick())),
  null!=a.get_appResponse().get_sidekick()
    &&this._sidekick.update(new lf(a.get_appResponse().get_sidekick())),
  this._onUpdate(this._sidekick))
:"errRemovePremiumCurrencyNotEnough"==a.error?
  A.get_instance().showNotEnoughPremiumDialog(x.get_sidekick_reskill_premium_currency_amount())
```

(`get_inactive_sidekick` @2075900: `new lf(this.getData("inactive_sidekick"))` ou null.)
Contrato recomendado: devolver `sidekick` com as skills novas; `inactive_sidekick`
fica para o caso de o sidekick re-skilled estar inativo.

### 2.7 `mergeSidekick` (fundir skill entre sidekicks) — DUAS FASES

Pedido fase 1: `{target_sidekick_id, source_sidekick_id, skill_stage, confirm:false}`
Pedido fase 2 (igual mas `confirm:true`).

Resposta fase 1 (**preview, não persiste**): **deve incluir `merged_sidekick{}`**
(DO do target COM a skill nova já aplicada, para pré-visualização):

```js
0==a.request.getData("confirm")?
  (a=new Cd(a.get_appResponse().get_merged_sidekick()),this._sidekickInfo.refresh(a))
:(sd.forceReloadSidekicks(),n.application.updateData(a.data),this.close())
```

Resposta fase 2: `data` normal com o estado final (target com level/xp resetados
e skill transferida; **source eliminado** — o cliente força reload da lista).
Erro: `errRemovePremiumCurrencyNotEnough` → diálogo com
`sidekick_merge_premium_currency_amount`. Custos exibidos antes do pedido vêm da
mesma constante.

### 2.8 `sewSidekick` (coser skin de outro sidekick)

Pedido: `{target_sidekick_id, sewed_identifier, sewed_level}` — o cliente calcula
o preview localmente escrevendo `sewed_identifier`/`sewed_level` no DO antigo
(`WX.refresh` @6945651).

Resposta: `data` normal. Sucesso → `forceReloadSidekicks()` + refresh de painéis.
Erro: `errRemovePremiumCurrencyNotEnough` → diálogo com
`sidekick_sew_premium_currency_amount`.

### 2.9 `setSidekickOrder` (ordenar/favoritar)

Pedido: `{sidekick_id, move_up(bool), move_to_end(bool), is_favorit(bool)}`
— 6 combinações nos botões sortUp/sortTop/sortDown/sortBottom/favorit.

Resposta: `data` normal contendo `inventory.sidekick_data` atualizado (ver §3).
Sucesso → `sortSidekicks()+refreshList(!0)`.

---

## 3. Formato de `inventory.sidekick_data` (ordem + favoritos)

Classe do inventário `zi` (@1277451):

```js
zi.prototype.getSidekickOrders=function(a){ // a=true -> favoritos
  var b=this.getString("sidekick_data"), c=null;
  if(""!=b) try{
    var e=new d(zf.decode(b));                    // zlib decode -> JSON -> TypedObject
    var m=a?"favorit_orders":"orders";
    c=e.hasData(m)?e.getIntVector(m):[]
  }catch(L){c=[]} ...
};
zi.prototype.isSidekickFavorit=function(a){return 0<=this.getSidekickOrders(!0).indexOf(a)};
zi.prototype.getSidekickOrderIndex=function(a){return this.getSidekickOrders(this.isSidekickFavorit(a)).indexOf(a)};
```

→ `inventory.sidekick_data` é uma **string zlib+JSON**: `{"orders":[id,...],"favorit_orders":[id,...]}`.
Ordenação apresentada (`sortByUserOrder` @3410231): favoritos primeiro; dentro de
cada grupo, ordem do array correspondente; ids ausentes vão para o fim.

## 4. Chaves de resposta reconhecidas no envelope `ta`

@2075768–2076000:

| chave | conteúdo | consumida por |
|---|---|---|
| `sidekicks` | array de DO | onRefreshData → `refreshSidekicks` |
| `sidekick` | DO singular | onRefreshData → `refreshSidekick` (obrigatória em renameSidekick) |
| `all_sidekicks` | array de DO catálogo | getSidekicks handler (sem null-check!) |
| `merged_sidekick` | DO | mergeSidekick fase 1 (sem null-check) |
| `inactive_sidekick` | DO | reskillSidekick (opcional) |

## 5. Erros globais tratados

- `errRemovePremiumCurrencyNotEnough` → `showNotEnoughPremiumDialog(<custo>)` com o
  custo lido da constante correspondente (rename/merge/reskill/sew). O servidor deve
  validar saldo ANTES de aplicar e devolver este código.
- Qualquer outro erro → `reportError` genérico.
- Handler default dos dialogs lança `Unsupported request action` — nunca devolver
  actions desconhecidas nestes pedidos.

## 6. Checklist "servidor precisa de"

1. Boot/login: chave `sidekicks` (lista do jogador, DOs completos) — sem ela, painel fica vazio.
2. `getSidekicks`: `all_sidekicks` SEMPRE presente (crash se ausente) — 212 entradas
   do catálogo com `ever_owned/now_owned/sources` + DOs completos.
3. `inventory.sidekick_id` (ativo) e `inventory.sidekick_data` (zlib+JSON orders/favorits).
4. renameSidekick → devolver `data.sidekick`.
5. mergeSidekick fase 1 → devolver `data.merged_sidekick` (preview); fase 2 → estado final + source removida.
6. reskillSidekick → `data.sidekick` (e opcionalmente `inactive_sidekick`).
7. Validação premium com erros `errRemovePremiumCurrencyNotEnough`.
8. Consistência: `status==1` para possuídos/ativos; ids de skills válidos em `sidekick_skills`.
