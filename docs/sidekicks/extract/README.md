# Extração do cliente HTML5 — Sidekicks/Pets (índice e síntese)

Engenharia inversa de `server-laravel/public/js/HeroZero.min.js` (build 257,
15,7 MB) + verificação no disco (assets, i18n, templates capturados).
Data: 2026-08. Objetivo: servir de contrato para implementar sidekicks no
`server-laravel`.

## Documentos

| Doc | Conteúdo |
|---|---|
| [01_protocolo_acoes.md](01_protocolo_acoes.md) | As 9 ações `*Sidekick*`: params exatos, handlers, chaves de resposta, erros; formato de `inventory.sidekick_data`; comportamento de DO com campos em falta |
| [02_constantes_dados.md](02_constantes_dados.md) | Constants obrigatórias (escalares + secções), fórmulas (stats, XP, preço), battle effects, assets, i18n, estado do disco |
| [03_combate.md](03_combate.md) | Aparência de batalha (`profile_X_appearance.sidekick`), rounds (`ae`/`de`), ícones/animações, contributo de stats, perfis de grupo, contrato por tabela |
| [04_ui_ciclo_vida.md](04_ui_ciclo_vida.md) | Painéis/tabs/sort/favoritos, XP bar, teaser/desbloqueio, fluxos merge/reskill/sew/release, goals |

## Descobertas críticas (resumo executivo)

1. **Constants em falta = crash.** O cliente lança
   `Error in Constants! Trying to retrieve unknown constant!` se faltar qualquer
   chave/secção: os escalares `sidekick_*` (11) e as secções `sidekick_levels`,
   `sidekick_skills`, `sidekick_data`, `battle_effects`. No nosso servidor estão
   **todas a null** nos templates (`data/initGame.json`) → abrir qualquer UI de
   sidekick rebenta hoje.
2. **RESOLVIDO — valores obtidos**: o `constants_json.data` oficial está em
   `assets/data/` na raiz do projeto (JSON zlib). Extraído para
   `server-laravel/data/reference/`: `sidekick_levels.json` (30 níveis, XP
   acumulado até 30450), `sidekick_skills.json` (289 skills com values reais),
   `sidekick_data.json` (212 sidekicks com offsets visuais),
   `battle_effects.json` (34 efeitos) e `sidekick_scalars.json` (escalares).
   O symlink `server-laravel/public/assets/data` foi reposto
   (`../../../assets/data`) — o cliente volta a carregar as constants localmente.
   Valores-chave: max_level=30, **min_required_level=60**, teaser=30,
   fatores common/rare=0.01, epic=0.017, legendary=0.025; custos rename=30,
   merge=15, reskill=1, sew=5 donuts.
3. **Fórmulas conhecidas** — o servidor tem de ser consistente com elas:
   - Stats: `stat(nível) = round(stat_base + stat_base × fator_qualidade × (nível−1))`
   - XP **acumulado** na tabela `sidekick_levels`; cliente NÃO valida (mostra o que chega)
   - Skills desbloqueiam a `level ≥ 10×stage` (10/20/30)
   - Variação visual: nível 1–14→1, 15–29→2, 30+→3 (`sewed_level` substitui se cosido)
4. **Protocolo**: 9 ações com contratos precisos (doc 01). Pontos que crasham se
   violados: `getSidekicks` sem `all_sidekicks`; `renameSidekick` sem
   `data.sidekick`; merge fase 1 sem `merged_sidekick`. Chave universal:
   qualquer resposta pode trazer `sidekick{}` / `sidekicks[]` e o cliente funde
   no estado local.
5. **Ativo = `inventory.sidekick_id`** (0 = nenhum). Ordem/favoritos =
   `inventory.sidekick_data` = string zlib+JSON `{"orders":[],"favorit_orders":[]}`.
6. **Combate** (doc 03): sidekick entra como subobjeto da appearance do herói;
   campos obrigatórios `{identifier, name, level, stage1..3_skill_id}` lidos sem
   guarda. Rounds trazem ids de efeito em `ae`/`de`; DoT usa grupos 16/18/22;
   bónus % de stats = battle effects 105–109 (`Math.ceil(base×value1)`).
   Batalhas de grupo esperam **totais prontos** já com sidekick incluído.
7. **Estado atual do servidor**: tabela `sidekicks` existe no schema (falta
   `sewed_identifier`, `sewed_level`); boot esvazia a chave `sidekicks`
   (`autoLoginUser.php:30`); `actions/sidekicks.php` é um protótipo mínimo;
   `Fight.php` não aplica skills; `getCharacter.php` faz unset de
   `requested_character_sidekick`. O trabalho descrito como feito em
   `docs/sidekicks/pets_sidekicks.md` não existe nesta árvore (perdido).

## Lacunas/risks fora do código

- ~~**Valores das constants**~~ **RESOLVIDO**: ver descoberta #2 — extraídos de
  `assets/data/constants_json.data` para `server-laravel/data/reference/`.
- **Assets**: só 1 placeholder + 6 skins Spine no mirror; faltam ~200 webp/spine.
  Sem eles há imagens partidas (o jogo funciona, fica feio).
- **i18n**: só `pt_BR`; para pt-PT é preciso criar locale ou usar pt_BR.

## Ordem de implementação sugerida

1. **Dados** ✅ desbloqueado: JSONs de referência já em
   `server-laravel/data/reference/` — falta injetá-los no boot
   (padrão `Live::withLocalConstants`). Gate: sem isto nada funciona.
2. **Modelo** `Sidekick.php` + leitura real da BD no boot (`sidekicks`,
   `all_sidekicks`) e colunas `sewed_*` no schema.
3. **Ações** (doc 01): completar `setSidekick/rename/release/unbind/order/reskill/
   merge/sew` + erros premium.
4. **Stats/ficha**: fórmula de stats no level-up; contributo na ficha
   (`requested_character_sidekick` no getCharacter).
5. **Aparência em combate**: injetar `sidekick` nas appearances (doc 03 §8).
6. **Skills no combate** (`Fight.php`): efeitos prioritários + marcar `ae`/`de`
   nos rounds + totais de perfil de grupo.
7. **Ciclo de vida**: XP share, primeiro sidekick ao `min_required_level`,
   goals (`current_goal_values.sidekick_*`).
