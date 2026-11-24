# Plano: Implementar Sidekicks/Pets

> **Rev. 2 (2026-08-25)** — revisto com base na extração completa do cliente
> (`docs/sidekicks/extract/`). Contratos de protocolo, valores das constants e
> fórmulas abaixo são **verificados no JS**, não suposições. Os pontos ainda
> decididos por nós estão marcados **[decisão]**.

## Contexto

O cliente HTML5 (build 257) tem suporte completo para sidekicks — painel,
batalha, skills, merge, sew — mas o servidor só tem um protótipo mínimo. O trabalho
descrito como feito em `pets_sidekicks.md` **não existe nesta árvore** (perdido);
este plano parte do estado atual real.

Documentação de suporte (extração, é o contrato vinculativo):

| Doc | Conteúdo |
|---|---|
| [extract/01_protocolo_acoes.md](extract/01_protocolo_acoes.md) | As 9 ações: params, handlers, chaves de resposta obrigatórias, erros |
| [extract/02_constantes_dados.md](extract/02_constantes_dados.md) | Constants escalares + secções, fórmulas, preços, assets, i18n |
| [extract/03_combate.md](extract/03_combate.md) | Appearance de batalha, rounds `ae`/`de`, contributo de stats |
| [extract/04_ui_ciclo_vida.md](extract/04_ui_ciclo_vida.md) | Painel, sort/favoritos, teaser, fluxos UX, goals |

### Riscos de crash que este plano evita (todos verificados no cliente)

1. **Constant em falta = exceção** `"Error in Constants! Trying to retrieve
   unknown constant!"`. Sem os 11 escalares `sidekick_*`, quase qualquer ecrã do
   jogo rebenta (loja, duelos, ligas leem `sidekick_min_required_level` no
   `updateCharacter`). Sem as 4 secções, abrir UI de sidekicks rebenta.
2. `getSidekicks` sem `all_sidekicks` na resposta → `null.iterator()` crash.
3. `renameSidekick` sem `data.sidekick` na resposta → crash (leitura sem guarda).
4. `mergeSidekick` fase 1 sem `merged_sidekick` → crash.
5. `profile_x_appearance.sidekick` presente mas com `identifier`/`name`/`level`/
   `stage*_skill_id` em falta → crash (`getString`/`getInt` diretos). Se não há
   sidekick ativo, **omitir o campo** (não enviar objeto vazio).
6. Campos em falta num DO de sidekick **não** crasham (devolvem 0/"") — mas
   envia-se sempre o DO completo para evitar estados inconsistentes.

---

## Fase 1: Dados de Referência + Injeção no Boot

### 1A. Extrair JSONs de referência — ✅ FEITO

Extraídos de `assets/data/constants_json.data` (raiz do projeto, JSON zlib,
620 chaves) para `server-laravel/data/reference/`:

| Ficheiro | Conteúdo |
|---|---|
| `sidekick_levels.json` | 30 níveis, `{level, xp}` — xp **acumulado** (nível 30 = 30450) |
| `sidekick_skills.json` | 289 skills `{id, identifier, sidekick_battle_effect_id, stage, value1..3, type, charges(-1=∞), fixed_sidekick[], legendary, once_per_group_battle}` |
| `sidekick_data.json` | 212 sidekicks por identifier `{special_sidekick, licence_sidekick, offset_x/y, avatar_1..3_offset_x/y, avatar_1..3_scale, panel_offset_x/y, panel_scale}` |
| `battle_effects.json` | 34 efeitos `{id, sidekick_battle_effect_ids[], value1..3(bool!), round_start_effect, after_hit_effect, single_round_effect}` |
| `sidekick_scalars.json` | 11 escalares + 3 de preço: max_level=30, min_required_level=**60**, teaser_level=30, fatores common/rare=0.01 epic=0.017 legendary=0.025, custos rename=30 merge=15 reskill=1 sew=5 donuts, `item_sidekick_buy_price_factor`=10, `item_sidekick_sell_price_divider`=5, `item_sidekick_sell_price_multiplier`=2 |

Nota: o symlink `server-laravel/public/assets/data` foi reposto
(`../../../assets/data`) — o cliente volta a carregar o `constants_json.data`
oficial localmente. A injeção no servidor usa os mesmos valores → coerente nas
duas fontes.

### 1B. Injetar na resposta de boot via `Live::withLocalConstants()`

Em `app/HeroZero/Live.php`, método `withLocalConstants()` (~L58), após a injeção
de `league_divisions`, adicionar **escalares E secções** (os escalares são lidos
na raiz de `constants`, não dentro de uma secção):

```php
$sk = \HeroZero\Sidekick::scalars(); // sidekick_scalars.json
foreach ($sk as $k => $v) { $data['constants'][$k] = $v; }
$data['constants']['sidekick_levels'] = \HeroZero\Sidekick::levels();
$data['constants']['sidekick_skills'] = \HeroZero\Sidekick::skills();
$data['constants']['sidekick_data']   = \HeroZero\Sidekick::catalogData();
$data['constants']['battle_effects']  = \HeroZero\Sidekick::battleEffects();
```

Segue o padrão existente de `League::divisions()`. `battle_effects` é partilhada
com seasons/dungeons — injetá-la beneficia essas features também.

---

## Fase 2: Modelo Sidekick + Carregamento no Boot

### 2A. Criar `app/HeroZero/Sidekick.php`

Classe com lazy-init estático (padrão `Character::questCatalog()`):

**Dados de referência (static cached):**
- `scalars()` / `levels()` / `skills()` / `catalogData()` / `battleEffects()` — carregam os JSONs
- `skillById(int $id)` — lookup; `battleEffectForSkill(int $skillId)` — via `sidekick_battle_effect_id`
- `levelForXp(int $xp)` — nível pela tabela de limiares acumulados
- `statAtLevel(int $quality, int $base, int $level)` — fórmula abaixo
- `sellPrice(array $row)` — fórmula de venda abaixo (para release)

**Dados de conta:**
- `ownedByCharacter(int $cid)` — SELECT da tabela `sidekicks`, formatado como DO completo (campos `item_improvement_id=0`, `sewed_identifier=''`, `sewed_level=0` quando ausentes na BD)
- `activeSidekick(int $cid)` — resolve via `inventory.sidekick_id` (**nunca** campo próprio — é assim que o cliente resolve)
- `catalogEntry(string $identifier, ?array $owned)` — entrada de catálogo: DO + `ever_owned`/`now_owned`/`sources[]`. **[decisão]** Sem histórico na BD: `now_owned = ever_owned = possui atualmente`, `sources=[]` (o painel aceita lista vazia; enriquecer depois se quisermos)
- `sidekickDO(array $row)` — formata uma row da BD como DO completo

**Combate:**
- `statBonusForCharacter(int $cid)` — contributo total do ativo aos stats do herói (ver Fase 4 — inclui os bónus % das skills, não só os stats próprios)
- `battleModifiers(int $cid)` — efeitos ativos do ativo para o `Fight.php` (Fase 6)

**Gestão:**
- `grantFirstSidekick(int $cid)` — conforme referência do servidor flash 129:
  identifier `sidekick_dog1`, **quality=3 (épico)**, base stats **60/100/40/23**
  (stamina/strength/critical/dodge), skills aleatórias por stage (ver abaixo);
  cria entrada em `orders`; **não** equipa automaticamente; marcar flag
  `received_sidekick` no personagem (coluna existia no flash; criar se não houver).
  Skills iniciais: sortear 1 por stage de `sidekick_skills.json`, excluindo
  `fixed_sidekick`/especiais (o flash sorteava dos pools então implementados:
  stage1={2,3,24}, stage2={6,7,10}, stage3={21,26,30})
- `grantXpToActive(int $cid, int $xp)` — soma, recalcula nível + stats
- `recalcStats(array &$row)` — aplica a fórmula às 4 stats
- `loadOrders(int $cid)` / `saveOrders(int $cid, array $orders, array $favorits)` — codifica/decodifica `inventory.sidekick_data` (**JSON PURO**: `json_encode(['orders'=>…,'favorit_orders'=>…])`; ver nota de implementação abaixo)

**Fórmulas (do jogo oficial — verificado):**
- Qualidade: 1=common, 2=rare, 3=epic, 4=legendary
- `stat(nível) = round(stat_base + stat_base × fator_qualidade × (nível−1))` (round, não floor!)
- XP **acumulado** contra `sidekick_levels`; o cliente não valida `(level,xp)`
- Skills desbloqueiam a `level ≥ 10×stage` (10/20/30); variação visual 1–14→1, 15–29→2, 30+→3 (`sewed_level` substitui o nível se cosido)
- Preço de venda (release): `totalStats = Σ statAtLevel(q, base_i, level)`; `base = ceil(totalStats / 5)`; `buy = round(precoCompra(q, base) × 10)`; `final = round((sell + melhorias) × 2)` — usar os helpers de preço de item já existentes no servidor

### 2B. Corrigir boot (`autoLoginUser.php`)

1. **Remover** `'sidekicks'` do array `ACCOUNT_STATE_KEYS` (L30)
2. Após o loop (L109), ao lado do padrão `season_progress`:
```php
$data['sidekicks'] = \HeroZero\Sidekick::ownedByCharacter($char->id());
```
**Não enviar `all_sidekicks` no boot**: o cliente só consome essa chave no handler
de `getSidekicks` (no `updateData` do login é ignorada) e seriam ~212 DOs
desperdiçados em cada login. O painel faz fetch quando abre.

### 2C. Corrigir `accountState()` (`Live.php` ~L396)

Após `attachStoryDungeonState`: `$data['sidekicks'] = Sidekick::ownedByCharacter($char->id());`

### 2D. `getSidekicks` (`actions/sidekicks.php`)

Resposta **obrigatoriamente** com `all_sidekicks` (crash se faltar; lista vazia
`[]` é aceitável): as 212 entradas de catálogo via `Sidekick::catalogEntry()`.
Incluir também `sidekicks` (estado fresco dos possuídos).

---

## Fase 3: Ações — contrato exato (doc extract/01)

Todas em `actions/sidekicks.php`. Regra global: **validar saldo premium ANTES de
aplicar** e devolver `errRemovePremiumCurrencyNotEnough` (o cliente abre diálogo
de donuts com o custo da constante correspondente).

| Ação | Params | Comportamento | Resposta obrigatória |
|---|---|---|---|
| `setSidekick` | `{sidekick_id}` | `inventory.sidekick_id = id` (0/inválido = desequipar) | data normal c/ inventory |
| `renameSidekick` | `{name, sidekick_id}` | cobrar 30; validar nome (≠atual→`errRenameSameName`, comprimento→`errRenameInvalidName`) | **`sidekick{}`** (sem guarda no cliente!) |
| `releaseSidekick` | `{sidekick_id}` | apagar row; creditar `sellPrice`; se era ativo → `sidekick_id=0`; remover dos arrays de ordem — tudo numa transação | data normal |
| `unbindSidekick` | `{sidekick_id}` | desvincular skin/melhoria mantendo o DO (`sewed_*=''`/0) | data normal |
| `reskillSidekick` | `{sidekick_id}` | cobrar 1; repor skills originais do identifier + redistribuir stats base (mesmo total) | **`sidekick{}`** (+ opcional `inactive_sidekick{}`); ligar `reskill_enabled:true` no login |
| `mergeSidekick` | `{target_sidekick_id, source_sidekick_id, skill_stage, confirm}` | **2 fases** — ver abaixo | fase 1: **`merged_sidekick{}`** |
| `sewSidekick` | `{target_sidekick_id, sewed_identifier, sewed_level}` | cobrar 5; persistir `sewed_*`; validar doador existe e `sewed_level` 1..30 (botão só ativo no nível máx.) | data normal |
| `setSidekickOrder` | `{sidekick_id, move_up, move_to_end, is_favorit}` | materializar nos arrays `orders`/`favorit_orders` de `inventory.sidekick_data` — **não** é uma lista completa nem coluna própria | data normal c/ inventory |
| `getSidekicks` | `{}` | ver 2D | **`all_sidekicks[]`** |

**mergeSidekick (2 fases):**
- Fase 1 (`confirm:false`) — **preview, não persiste, não cobra**: devolver
  `merged_sidekick` = DO da target com a skill `skill_stage` da source já aplicada.
- Fase 2 (`confirm:true`) — cobrar 15; copiar `stage{N}_skill_id` da source para a
  target; apagar a source (se era ativa → desequipar; remover da ordem); devolver
  data com `sidekicks[]`/`sidekick{}` atualizados (o cliente força reload da lista).
- **Reset da target confirmado** no servidor flash 129: `level=1`, `xp=0`, e remover
  o source dos arrays de ordem. Curiosidade: existia a constante
  `sidekick_merge_plus_level=4` (definida mas nunca usada nesse código — possivelmente
  +4 níveis pós-reset no oficial; não implementar por agora). Seguir o comportamento
  de reset.

---

## Fase 4: Contributo de Stats na Ficha do Herói

**Como o cliente funciona (importante):** na ficha do PRÓPRIO herói o cliente
**calcula sozinho** a partir do DO do sidekick + constants — o servidor não
controla esse display. Mas os **totais de combate** (`profile_X.stamina` etc.,
usados em batalhas de grupo e no `Fight.php`) têm de vir prontos do servidor JÁ
COM o sidekick incluído.

Contributo correto (= `Y.sidekickBaseStatBonus` do cliente, doc extract/03 §3):

```
contributo(stat) = sk.stat_<stat>            // stats próprios do DO (já com fórmula de nível)
                 + Σ ceil(heroBase.<stat> × value1_da_skill)   // para CADA skill desbloqueada
                   // cuja skill mapeia (via battle_effects) para o efeito fixo:
                   //   105=força 106=stamina 107=critical 108=dodge
                   //   + efeito 109 (todos os stats, soma aos 4)
```

- `heroBase` = stat **base** do herói (sem itens), `Math.ceil` → `ceil` em PHP.
- Só skills desbloqueadas (`level ≥ 10×stage`) contam.

### 4A. `Character::battleProfile()` (~L1956)

Somar `Sidekick::statBonusForCharacter($this->id())` às 4 stats de combate.

### 4B. `Character::overlayCharacter()` (~L1037)

Idem no bloco `stat_total_*` (coerência entre ficha e batalha).

### 4C. `getCharacter.php` (L40)

Substituir o `unset($data['requested_character_sidekick'])` por:

```php
$sk = Sidekick::activeSidekick($characterId);
if ($sk) { $data['requested_character_sidekick'] = Sidekick::sidekickDO($sk); }
else { unset($data['requested_character_sidekick']); }
```

É assim que o perfil/tooltip de OUTRO jogador mostra o sidekick dele.

---

## Fase 5: Aparência em Batalha

### 5A. `Character::battleAppearance()` (~L2055)

Antes do return, se há sidekick ativo, injetar o subobjeto **completo** (os
campos marcados ** são lidos sem guarda — falta = crash):

```php
$out['sidekick'] = [
    'identifier'       => $sk['identifier'],        // ** obrigatório
    'sewed_identifier' => (string)$sk['sewed_identifier'],
    'sewed_level'      => (int)$sk['sewed_level'],
    'name'             => $sk['name'],              // ** obrigatório (custom ou i18n default)
    'level'            => (int)$sk['level'],        // ** obrigatório
    'stage1_skill_id'  => (int)$sk['stage1_skill_id'], // ** sempre int, mesmo 0
    'stage2_skill_id'  => (int)$sk['stage2_skill_id'], // **
    'stage3_skill_id'  => (int)$sk['stage3_skill_id'], // **
];
// sem sidekick ativo: NÃO enviar a chave
```

Válido para duelo, liga, quests, worldboss, dungeons e como base dos perfis de
guilda (`attacker/defender_character_profiles`, mesma forma + `position`,
totais prontos, `weapondamage`).

Efeito imediato no cliente: com a appearance correta, os **ícones das skills
desbloqueadas aparecem sozinhos** junto ao nome/barra de vida do herói
(`UiBattleSidekickSkills`; mostra apenas `level ≥ 10×stage`). Nada a fazer do
lado da UI.

---

## Fase 6: Skills no Combate (`Fight.php`)

Os rounds transportam `ae`/`de` = arrays de ids de **sidekick battle effect**
ativos no atacante/atacado nesse round; o cliente classifica-os pelas flags
`round_start_effect`/`after_hit_effect`/`single_round_effect` das constants.
Todo o dano/heal chega já calculado em `v/dt/bd/bf/ha/hd`.

Modificar o construtor para aceitar modifiers opcionais; chamadores
(startDuel, startLeagueFight, quest/worldboss/dungeon, GuildBattles) passam
`Sidekick::battleModifiers($cid)`.

Prioridade de implementação:

1. Marcar `ae`/`de` nos rounds (mesmo sem mecânica, os ícones acendem-se e o
   cliente reproduz a **animação de ativação** sozinho: overlay
   `sidekick_skills/<identifier>_infight.webp` sobre o herói + pulso no ícone,
   respeitando `round_start_effect`/`after_hit_effect` das constants)
2. Bónus passivos % — NÃO são efeitos de round: entram nos totais (Fase 4)
3. Critical damage bonus (efeito 3) — estender o fator crítico fixo
4. Stun (efeitos 1, 2, 4, 9) — defensor perde o próximo turno
5. Double attack (efeito 6) — atacante bate 2×
6. Counter-attack após levar crítico (efeito 5) — dano de recuo (`bf`)
7. DoT (efeitos dos grupos **16, 18 e 22** — hard-coded no cliente): marcar
   `dt>0` em cada round afetado; no round final `dt` ausente/0 → o cliente
   desliga os ícones
8. Cura (`ha`/`hd`) e `charges` (-1 = ilimitado)/`once_per_group_battle`

---

## Fase 7: Ciclo de Vida

### 7A. Partilha de XP

Taxa confirmada no servidor flash 129 (`class/GameSettings.php`): constante
**`sidekick_xp_factor` = 0.02**, aplicada como `round($xp × 0.02)` — ou seja ~1/50,
mas com round. O cliente não calcula nem valida (o XP chega pronto), portanto é
constante nossa; usar 0.02.

Aplicar num **único ponto de passagem** (padrão do servidor antigo: dentro do
método central que dá XP ao herói, não espalhado por cada ação):
`Sidekick::grantXpToActive($cid, round($xpGanho * 0.02))`.

### 7B. Primeiro sidekick ao nível 60

Confirmado: `sidekick_min_required_level=60` (teaser na HUD aparece a 30). Em
`syncLevelFromXp()`, após level-up:

```php
if ($level >= 60 && count(Sidekick::ownedByCharacter($this->id())) === 0) {
    Sidekick::grantFirstSidekick($this->id());
}
```

Idempotente por "não tem nenhum" — evita coluna extra tipo `received_sidekick`.
O diálogo `sidekicks_unlocked` dispara sozinho no cliente quando ele deteta o
personagem a atingir exatamente o nível 60.

### 7C. Goals (contadores em `current_goal_values`)

Incrementar nos eventos: `first_sidekick_collected`, `sidekick_collected`,
`first_sidekick_maxed`/`sidekick_maxed` (chegar a 30),
`first_sidekick_merged`/`sidekick_merged`,
`different_sidekick_collected` (identifiers distintos já possuídos),
`hideout_blacksmith_sidekick_collected`, `sidekick_released`.

---

## Alterações ao Schema da BD

Em `docker/initdb/01_schema.sql` (a tabela `sidekicks` já existe):

```sql
ALTER TABLE `sidekicks`
  ADD COLUMN `sewed_identifier` VARCHAR(32) NOT NULL DEFAULT '' AFTER `stage3_skill_id`,
  ADD COLUMN `sewed_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `sewed_identifier`;
```

**Sem coluna `sort_order`**: a ordem/favoritos vive em `inventory.sidekick_data`
(JSON puro `{"orders":[],"favorit_orders":[]}` — ver nota de implementação) —
é o único formato que o cliente lê, e a coluna já existe no schema (mudada para
`text`: o JSON cresce e varchar(64) não chegava). Melhorias de item (tipo 9 /
`item_improvement_id`) ficam fora do âmbito inicial.

---

## Ficheiros Tocados

| Ficheiro | Tipo | O quê |
|----------|------|-------|
| `data/reference/{sidekick_levels,sidekick_skills,sidekick_data,battle_effects,sidekick_scalars}.json` | **feito** | Dados oficiais extraídos |
| `app/HeroZero/Sidekick.php` | **novo** | Modelo central (referência + conta + fórmulas) |
| `app/HeroZero/Live.php` | modificar | withLocalConstants (escalares+secções) + accountState |
| `app/HeroZero/Character.php` | modificar | battleProfile, overlayCharacter, syncLevelFromXp, claim*Rewards |
| `app/HeroZero/Fight.php` | modificar | Efeitos de skills + `ae`/`de` nos rounds |
| `actions/autoLoginUser.php` | modificar | Remover de ACCOUNT_STATE_KEYS, carregar do BD |
| `actions/sidekicks.php` | modificar | As 9 ações com contratos completos |
| `actions/getCharacter.php` | modificar | `requested_character_sidekick` |
| `docker/initdb/01_schema.sql` | modificar | Colunas `sewed_*` |

## Ordem de Implementação

1. ~~Extrair JSONs de referência~~ ✅
2. Schema (`sewed_*`) + `Sidekick.php`
3. Injetar constants no boot (Live.php) + carregar `sidekicks` (autoLoginUser/accountState) → painel abre sem crash
4. `getSidekicks` + restantes ações (Fase 3) → gestão funcional
5. Stats na ficha/combate + `requested_character_sidekick` (Fase 4)
6. Aparência em batalha (Fase 5)
7. Skills/efeitos no Fight.php (Fase 6)
8. XP share + primeiro sidekick + goals (Fase 7)

**Estado: IMPLEMENTADO (2026-08-26)** — todas as fases acima feitas, com lint e
testes vivos contra a stack local (DB Docker 3308 + server 8000). Notas finais
que divergem ou afinam o plano:

- **`inventory.sidekick_data` é JSON PURO**, não zlib+JSON. No build HTML5 257,
  `zf` do cliente é JsonUtil (`zf.encode=JSON.stringify` / `zf.decode=JSON.parse`,
  verificado no minified). A coluna passou de `varchar(64)` para `text NOT NULL`.
  (O `constants_json.data` do asset continua zlib — isso não muda.)
- **XP share**: aplicado nos três únicos pontos que creditam XP ao herói —
  `claimTrainingStar`, `claimQuestRewards`, `applyCasinoReward` (duelo/liga só
  dão moedas/honra). Taxa `round(xp × 0.02)` via `Sidekick::giveXpToActive`.
- **Primeiro sidekick**: concedido em `syncLevelFromXp()` quando o nível atinge
  `sidekick_min_required_level` (60); flag `received_sidekick` torna-o único.
- **Goals de sidekick vivos**: contadores acumulados em `character.goal_stats`
  (JSON), servidos como `current_goal_values` PARCIAL (o cliente faz merge por
  chave em `refreshGoalValues`). Chaves: `first_sidekick_collected`,
  `sidekick_collected`, `different_sidekick_collected` (calculado na BD),
  `first/sidekick_maxed`, `first/sidekick_merged`, `sidekick_released`.
  O resto das metas continua estático, como antes.
- **Skills sem efeito no combate** (fora de âmbito): mísseis (`stage1_7`
  "Intercepted", `stage2_5` "Get the Stick") e troféu (`stage2_9`).
- **Constant Dripping** (`stage1_5`): assume-se 100% do golpe gatilho repartido
  pelas `value2` rodadas (`value3=null` nos dados oficiais).
- **Sem épocas/temporadas**: skills com `fixed_sidekick`/season ficam fora das
  pools; nada de `season_*` (restrição permanente do projeto por agora).
- **Detalhes**: `name` nasce vazio (como no flash; cliente mostra nome
  localizado da espécie); `setSidekickOrder` valida posse antes de ordenar;
  débitos premium sempre atómicos ANTES do efeito.

## Referência: servidor antigo flash 129

Analisado em 2026-08 (`~/Transferências/herozero(flash 129 2018)/server/`).
Segunda fonte útil, mas o cliente era o flash (protocolo/DOs diferentes) — não
copiar às cegas. Nada de épocas/temporadas aí (não existiam).

**Confirmado e aproveitado:**
- `sidekick_xp_factor=0.02` aplicado no `giveExp()` central (Player.php L385) —
  ponto único de partilha de XP, adotar o mesmo padrão
- Primeiro sidekick ao nível exato 60 (ver Gestão acima)
- merge: reset da target + source fora das ordens + saldo verificado antes de cobrar
- Passivas com `ceil(base×1.10)` — mesma semântica dos battle effects 105–109 do HTML5
- `setSidekickOrder`: move/clamp de posição; resposta devolve `inventory`

**Erros do antigo a NÃO copiar:**
- Stats do sidekick **nunca crescem** com o nível (fatores definidos, nunca aplicados)
- `releaseSidekick` não paga as moedas de venda
- `unbindSidekick` ignora os params e faz `sidekick_id=0` antes do SELECT (fica null)
- Ordens em JSON puro — **na altura parecia errado, mas é o formato certo**: no
  build HTML5 257 o `zf` do cliente é JsonUtil (`zf.encode=JSON.stringify`,
  `zf.decode=JSON.parse`), logo `inventory.sidekick_data` É JSON puro mesmo
  (ver nota de implementação abaixo)
- Rename valida comprimento com constantes de *guild name*; catálogo montado ad-hoc
  de `item_templates` com ids negativos e `ever_owned=false` eterno
- Combate: efeitos hard-coded por id de skill da era flash com % à solta — usar
  como referência **semântica** (initiative, redução de crítico, dano mísseil,
  multiplicadores), mas mapear pelos dados novos (`sidekick_skills`/`battle_effects`)
- Sem reskill/sew (não existiam na altura)

## Verificação

1. **Boot**: login → abrir painel de sidekicks sem crash; tab "Todos" mostra 212; tab "Meus" mostra os possuídos
2. **Equipar**: `setSidekick` → stats do herói sobem na ficha (parcela "sidekick" nos tooltips)
3. **Renomear**: custo 30 mostrado; resposta atualiza o nome sem reload
4. **Merge**: preview na fase 1 mostra a skill na target; fase 2 remove a source; sem donuts → diálogo de erro
5. **Ordem/favoritos**: reordenar sobrevive a relogin (JSON puro em `inventory.sidekick_data`)
6. **Batalha PvP**: sidekick visível junto ao herói; skills desbloqueadas aparecem como ícones junto à barra de vida; skill ativada → animação `_infight.webp` + pulso no ícone; `ae`/`de` corretos nos rounds
7. **Perfil alheio**: ver outro jogador com sidekick ativo mostra o lado dele
8. **Nível 60**: conta nova a subir a 60 recebe o primeiro sidekick + diálogo de desbloqueio
9. **XP**: missão concluída → barra de XP do sidekick avança (taxa definida em 7A)
