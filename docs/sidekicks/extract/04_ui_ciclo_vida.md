# Extração do cliente — UI e ciclo de vida dos sidekicks

Fonte: `server-laravel/public/js/HeroZero.min.js` (build HTML5 257).
Complementa: [01_protocolo_acoes.md](01_protocolo_acoes.md), [02_constantes_dados.md](02_constantes_dados.md), [03_combate.md](03_combate.md)

---

## 1. Painel principal de sidekicks (classe `sd`, ~offset 3400000)

- **Tabs**: `_btnTabMySidekicks` ("Meus") / `_btnTabAllSidekicks` ("Todos").
  - "Meus": itera `character.get_sidekicks()` filtrando **`status==1`** (`updateMySidekicks` @3401128).
  - "Todos": itera `sd.ALL_SIDEKICKS` (construído da resposta `getSidekicks` → `all_sidekicks`), ordenado por nível asc + nome.
- **Sort options** (`sidekickSortOption` @1944600): setting do utilizador
  (`sidekick_sort_option`, default `1`). Valores (`sd.sortSidekicks`):
  | valor | ordem |
  |---|---|
  | 1 | ordem do utilizador (favoritos → orderIndex) |
  | 2 | nível asc, depois nome |
  | 3 | nível desc, depois nome |
  | 4 | nome asc, depois nível |
  | 5 | nome desc, depois nível |
- **Filtro**: setting `sidekick_filter_settings` (classe `dz`) — filtro All/Own.
- **Ordenação por utilizador** (`sortByUserOrder` @3410231): favoritos primeiro;
  dentro de cada grupo, índice nos arrays de `inventory.sidekick_data`
  (ver doc 01 §3); ids sem índice vão para o fim.
- Contador no header: `txtCount`; estado vazio: `noSidekicks`.

## 2. Favoritos e reordenação

Botões `sortUp/sortTop/sortDown/sortBottom` + botão favorit enviam
`setSidekickOrder {sidekick_id, move_up, move_to_end, is_favorit}` (doc 01 §2.9).
O servidor é quem materializa os arrays `orders`/`favorit_orders` — o cliente
só lê. Badge visual: `btnFavorit.iconOn/iconOff` conforme `get_isFavorit()`.

## 3. Barra de XP e progresso (`UiSidekickProgressBase` = `rs`, @6936393)

- Mostra nível atual + barra; fórmula client-side (doc 02 §6):
  `(xp − limiar(nível)) / (limiar(nível+1) − limiar(nível))`, limiares da secção
  constants `sidekick_levels` (xp acumulado). No máximo (`sidekick_max_level`),
  texto vazio.
- Animação "+XP" (timeline com label `UiSidekickXpBar`) ao ganhar XP.

## 4. Desbloqueio/teaser na HUD (classe `Vo`, @6928977)

```js
var b = character.get_level() >= x.get_sidekick_teaser_level();
this._content.set_visible(b);
b && (b = character.get_level() >= x.get_sidekick_min_required_level(),
      this._content.set_alpha(b?1:.5), ...);
```

- `< teaser_level`: slot escondido.
- entre teaser e min_required: slot visível a 50% (teaser "desbloqueia breve").
- `>= min_required_level`: ativo; se não há sidekick mostra tooltip
  `general/sidekick_info_none` (goal "ganha o teu sidekick").
- Fonte do sidekick mostrado: outfit com setting 3 (`getOutfitWithSetting(3).get_sidekick()`)
  ou `get_currentSidekick()`.

## 5. Fluxos UX (dialogs)

### 5.1 Ativar/equipar (`iu.activateSidekick` @6904028)
Clique em "Ativar" → `setSidekick {sidekick_id}` → fecha/refresca (doc 01 §2.2).

### 5.2 Renomear (`WF` @3425083)
Dialog com `inputName`; valida não-vazio localmente; envia `{name, sidekick_id}`;
exige `data.sidekick` na resposta; erros premium/same-name (doc 01 §2.3).

### 5.3 Libertar (`iu.onClickRelease`)
Confirm dialog com preço de venda (`get_sellPrice()` formatado) e aviso se há
outfits com este sidekick → `releaseSidekick {sidekick_id}` (doc 01 §2.4).

### 5.4 Unbind (`iu.onClickAlone`) → `unbindSidekick {sidekick_id}` (doc 01 §2.5).

### 5.5 Merge (`Rx` lista → `qD` confirmação, @3415000)
1. Escolher source (lista de sidekicks possuídos compatíveis) para um target fixo.
2. Escolher qual skill transferir: `_newSidekickSkill.get_stage()` ∈ {1,2,3}.
3. Fase preview `confirm:false` → resposta traz `merged_sidekick` → refresh info.
4. Confirmar `confirm:true` → persiste; source desaparece; target fica com
   level/xp resetados (comportamento esperado do servidor); `forceReloadSidekicks`.
   Custo exibido/validado: `sidekick_merge_premium_currency_amount` donuts.

### 5.6 Reskill (`iu.onClickReskill`)
Direto com confirmação de custo (`sidekick_reskill_premium_currency_amount`);
resposta repõe skills originais do identifier (doc 01 §2.6).

### 5.7 Sew (`WX` @6945000)
Escolher sidekick-doador de skin + nível da skin; preview local escreve
`sewed_identifier/sewed_level` no DO antigo; confirm envia
`{target_sidekick_id, sewed_identifier, sewed_level}`; custo
`sidekick_sew_premium_currency_amount`. Skin ativa muda a variação visual
(`get_displayVariation` usa `sewed_level` em vez de `level`; ver doc 02 §7).

## 6. Item improvements ligados a sidekicks (@3336344, @3343354)

- Itens de melhoria tipo **9** aplicam-se a sidekicks
  (`get_improvementItemType()==9`).
- Semântica de `item_improvement_id` observada:
  - `-x` → tem improvement equipado (`get_hasImprovement(): id<0`;
    getter público devolve `Math.abs(id)`);
  - `1` → slot livre disponível;
  - `0` → sem slot (`get_hasImprovementSlot(): id!=1 ? id<0 : true`).
- Dialogs de improvement listam sidekicks `status==1` com/sem slot consoante o modo.

## 7. Goals / tutorial (contadores no estado de conta)

O template de boot já traz as chaves em `current_goal_values`:

```
first_sidekick_collected, sidekick_collected,
first_sidekick_maxed, sidekick_maxed,
first_sidekick_merged, sidekick_merged,
different_sidekick_collected,
hideout_blacksmith_sidekick_collected,
sidekick_released
```

Estas chaves NÃO estão hard-coded no JS — são lidas pelo sistema genérico de
goals (i18n `goal/...` + valores por chave). **O servidor tem de as incrementar**
nos eventos correspondentes (obter 1.º sidekick, obter N, maxar nível, fundir,
libertar, blacksmith do hideout).

## 8. Outfits

- `Cd.get_isInOutfit()`: `character.get_outfits().hasOutfitWithSidekick(this)`.
- Outfit pode guardar sidekick associado (setting 3 usado pela HUD, §4).
- Release de sidekick usado por outfits pede confirmação extra (aviso de outfits).

## 9. Notificações/socket

Nenhum evento socket específico de sidekick encontrado — todas as atualizações
chegam por respostas HTTP (`data.sidekick(s)` via onRefreshData) ou refetch do
painel. Se houver ganho de sidekick assíncrono (ex.: recompensa), o cliente só o
verá no próximo boot/updateData que inclua as chaves.

## 10. Resumo de estado controlado pelo servidor

| estado | onde vive |
|---|---|
| lista possuída + stats/skills | chave `sidekicks` (boot e qualquer updateData) |
| catálogo completo + ever/now_owned + sources | resposta `getSidekicks` → `all_sidekicks` |
| ativo | `inventory.sidekick_id` |
| ordem/favoritos | `inventory.sidekick_data` (zlib+JSON) |
| contadores de goals | `current_goal_values.*_sidekick_*` |
| custos/fórmulas | constants (doc 02) |
| aparência em combate/aparição geral | campo `sidekick` da appearance (doc 03) |
