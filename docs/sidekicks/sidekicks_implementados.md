# Sidekicks/Pets — Implementação Concluída

> Registo do que foi implementado (2026-08-25/26), complementar ao
> [implementar_sidekicks.md](implementar_sidekicks.md) (plano) e aos extratos em
> [extract/](extract/). Comunicação em português europeu.
>
> **Restrição permanente respeitada**: nada de épocas/temporadas — skills com
> `fixed_sidekick`/season ficaram fora das pools; sem `season_*`.

---

## 1. O que está feito

| # | Área | Estado |
|---|------|--------|
| 1 | Schema (`sewed_*`, `sidekick_data` → text) | ✅ |
| 2 | Modelo central `app/HeroZero/Sidekick.php` | ✅ |
| 3 | Constants no boot + carregamento na conta | ✅ |
| 4 | 9 contratos em `actions/sidekicks.php` | ✅ |
| 5 | Stats na ficha/combate + `requested_character_sidekick` | ✅ |
| 6 | Aparência de batalha (subobjeto `sidekick`) | ✅ |
| 7 | Motor de combate com skills (`Fight.php`) | ✅ |
| 8 | Ciclo de vida: XP share, primeiro aos 60, goals | ✅ |

## 2. Modelo central (`app/HeroZero/Sidekick.php`)

Referência + conta num só sítio; dados oficiais de
`data/reference/{sidekick_levels,sidekick_skills,sidekick_data,battle_effects,sidekick_scalars}.json`
(asset zlib → JSON, cache estático).

- **Fórmulas**: `stat(nível) = round(base + base × fator × (nível−1))`;
  fatores common/rare=0.01, epic=0.017, legendary=0.025; XP acumulado contra
  `sidekick_levels` (nível 30 = 30450 xp); skills desbloqueiam a nível ≥ 10×stage.
- **Pools de skills** (sem fixed_sidekick/season): stage1=[1,2,3,4,5,24],
  stage2=[6..12,25], stage3=[15..18,20,21,26].
- **Bónus passivo** por skill desbloqueada com efeito nos grupos
  105=strength/106=stamina/107=critical/108=dodge/109=all:
  `ceil(stat_base_do_heroi × value1)`; usa as colunas cruas `stat_base_*` (o
  cliente não conhece `stat_trained`).
- **sellPrice** exato do cliente (`Cd.get_sellPrice`): totalStats → imp=ceil(/5)
  → buy=trunc(itemBuyPrice(q,imp)×10) → sell=jsRound(buy×(premium?0.625:0.5)).
- **Ordens/favoritos**: `inventory.sidekick_data` é **JSON PURO**
  (`{"orders":[],"favorit_orders":[]}`) — no build 257 o `zf` do cliente é
  JsonUtil (`zf.encode=JSON.stringify`), não zlib. Coluna mudada para `text`.
- **Primeiro sidekick** (`grantFirstIfEligible`, chamado do
  `syncLevelFromXp()`): `sidekick_dog1`, quality 3, bases 60/100/40/23,
  flag `received_sidekick` torna-o único; NÃO auto-equipa.
- **XP share** (`giveXpToActive`): cai sempre só no ATIVO
  (`inventory.sidekick_id`); recalcula nível+stats.
- **Catálogo** (`catalogEntries`): as 212 entradas para `all_sidekicks`.
- **DO completo** (`doFromRow`): shape esperada pelo cliente.

## 3. Boot / resposta

- `Live::withLocalConstants()`: injeta escalares (14) +
  `sidekick_levels`/`sidekick_skills`/`sidekick_data`/`battle_effects` nas
  constants (só no boot — `accountState` não transporta constants).
- `Live::accountState()` + `actions/autoLoginUser.php`: `$data['sidekicks'] =
  Sidekick::ownedByCharacter(...)` em toda resposta (o cliente funde por id).
- `actions/getCharacter.php`: `requested_character_sidekick` = DO real do
  personagem consultado (omitido se não tem ativo).
- `Character::battleAppearance()` injeta o subobjeto `sidekick`; omitido sem ativo.

## 4. Actions (`actions/sidekicks.php`) — os 9 contratos

| Ação | Notas |
|------|-------|
| `getSidekicks` | devolve `all_sidekicks[]` (catálogo) |
| `setSidekick` | ativo só por `inventory.sidekick_id` (0 desequipa); valida posse |
| `renameSidekick` | validações errRenameInvalidName (>32)/errRenameSameName; débito 30 donuts; devolve `sidekick{}` |
| `releaseSidekick` | transação: DELETE + crédito sellPrice + limpar ativo + fora das ordens |
| `unbindSidekick` | limpa só o apontador de ativo |
| `reskillSidekick` | débito 1; sortear novo trio; devolve `sidekick{}` |
| `mergeSidekick` | fase preview (`merged_sidekick`) sem gravar; fase confirm: débito 15, transfere skill da source, reset target (nível 1/xp 0), apaga source |
| `sewSidekick` | débito 5; valida identifier no catálogo; clamp sewed_level 0–3 |
| `setSidekickOrder` | toggle favorito + reordenar com clamp estilo flash; valida posse |

Débitos premium SEMPRE atómicos ANTES do efeito
(`UPDATE ... WHERE premium_currency >= ?` + rowCount!==1 → errRemovePremiumCurrencyNotEnough).

## 5. Combate (`app/HeroZero/Fight.php`)

- `battleProfile()` transporta `{level, stage1..3_skill_id}` do ativo;
  `Fight::sidekickState()` monta `[skillId => {identifier,effect,value1,value2,value3,charges,uses}]`
  (campo `value3` adicionado — usado pelo cálculo de DoT e survive_plus).
- O despacho deixou de ser por `identifier` literal e passou a usar a tabela
  `SKILL_BEHAVIOR` (`identifier → tipo`). Loops inline em `hit()` e `entity()`
  varrem todas as skills do combatente por tipo — cada uma tem o seu sorteio
  independente. Isso cobre automaticamente todas as variantes futuras do mesmo
  comportamento sem código extra.

### Comportamentos implementados

| Tipo | Skills abrangidas (exemplos) | Mecanismo |
|---|---|---|
| `initiative` | stage1_3 + variantes | Desloca p(A primeiro) = 0.5 + (vA−vB)/2 |
| `dodge_debuff` | stage2_6/8/11/12/13/15/27/33 | Passiva: reduz esquiva do adversário |
| `crit_debuff` | stage2_7/10 | Passiva: reduz crítico do adversário |
| `stun_attack` | stage1_1 + 26 variantes, stage3_46/49/52 | Atordoa defensor ao acertar |
| `stun_hit` | stage1_43–45/50/58/71/81/90/98, stage3_24/25/29/31/32/33/47/50/51 | Atordoa atacante ao ser atingido |
| `stun_crit` | stage3_45/16/53 | Atordoa defensor ao fazer crítico |
| `stun_if_dodge` | stage3_21 | Atordoa defensor quando ELE esquiva |
| `dot` | stage1_5/6/15/16/19/25/26/27/35/38/39/57/69/72/74/77/83/85/87/99/103/106 | Veneno ao acertar; tick = dano×v3÷v2 rondas |
| `dot_dodge` | stage1_36/51/64/95/105/111 | DoT ao adversário quando eu esquivo |
| `crit_extra` | stage3_7/9/28/48, stage1_12/18/20/46/54/61/62/66/67/68/73/75/76/80/82/84/86/88/94/96/100/101/104/108/110 | Dano extra no crítico × (1+v2) |
| `reduce_crit_dmg` | stage1_48/91 | Reduz dano de crítico recebido em v2 (ch=1) |
| `flat_dmg` | stage1_37/55/93/102/107/109/112 | Bónus flat × (1+v2) em qualquer golpe |
| `dmg_reduce` | stage3_8, stage1_2 | Reduz dano recebido em v2 |
| `counter_crit_dmg` | stage3_4 | Recuo após crítico recebido |
| `counter_any_dmg` | stage3_6 | Recuo em qualquer golpe |
| `counter_crit` | stage1_4/11/17/22/30/31/32/40/52/60/92/97, stage3_26 | Contra-ataque após crítico (cadeia) |
| `double_strike` | stage3_2/10/12/17/18/19/27/30/34/35/36/38/39/40/42/43/44 | Golpe duplo (cadeia) |
| `second_dodge` | stage3_3/13/20 | 2.ª hipótese de esquiva |
| `survive` | stage3_1 | Sobrevive ao golpe fatal com 1 HP (sem sorteio) |
| `survive_plus` | stage3_11/41 | Sobrevive + bónus v3 ao próximo ataque |
| `double_dodge` | stage3_22 | Golpe duplo após esquivar (cadeia) |

- Rounds marcam `ae`/`de` (ids de battle effect do atacante/atacado), `dt`
  (tick veneno), `bf` (recuo); cargas limitam usos por luta; cliente só renderiza.
- **Sem efeito** (fora de âmbito): mísseis (`stage1_7`, `stage2_5`, `stage2_36`),
  troféu (`stage2_9`).

## 6. Ciclo de vida / metas

- **XP share 2%** (`round(xp×0.02)`) nos três únicos pontos que creditam XP ao
  herói: `claimTrainingStar`, `claimQuestRewards`, `applyCasinoReward`
  (duelo/liga não dão XP — só moedas/honra).
- **Goals vivos** em `character.goal_stats` (JSON; mesmo padrão do flash antigo):
  `first_sidekick_collected`, `sidekick_collected`,
  `different_sidekick_collected` (calculado na BD), `first/sidekick_maxed`
  (transição p/ nível 30), `first/sidekick_merged`, `sidekick_merged`,
  `sidekick_released`. Servidos como `current_goal_values` **PARCIAL** em
  accountState/boot — o cliente faz merge por chave (`refreshGoalValues`).
  O resto das metas continua estático.

## 7. Ficheiros tocados

```
server-laravel/docker/initdb/01_schema.sql   sewed_identifier/sewed_level; sidekick_data → text
server-laravel/app/HeroZero/Sidekick.php     NOVO — modelo central; STATIC_SOURCES + special_skill_id
server-laravel/app/HeroZero/Live.php         constants + sidekicks + goals no accountState
server-laravel/app/HeroZero/Character.php    bónus ficha/combate, aparência, syncLevelFromXp,
                                             claim*Rewards, goalStats helpers
server-laravel/app/HeroZero/Fight.php        reescrita: SKILL_BEHAVIOR + 21 comportamentos
server-laravel/app/HeroZero/AssetCache.php   NOVO — cache persistente das fatias do asset
server-laravel/app/HeroZero/League.php       officialConstants via AssetCache (perf)
server-laravel/app/HeroZero/GuildArtifacts.php registry via AssetCache (perf)
server-laravel/actions/sidekicks.php         NOVO — os 9 contratos
server-laravel/actions/autoLoginUser.php     sidekicks fora de ACCOUNT_STATE_KEYS + goals
server-laravel/actions/getCharacter.php      requested_character_sidekick real
server-laravel/actions/claimQuestRewards.php sidekicks na resposta (sincronizar pet)
docs/sidekicks/implementar_sidekicks.md      correções zlib→JSON + notas finais
docs/sidekicks/sidekicks_implementados.md    este ficheiro — actualizado 2026-09-07
docs/sidekicks/skills_implementadas.md       actualizado 2026-09-07
```

## 8. Testes feitos

- **Smoke em transação** (rollback, BD real Docker 3308): concessão ao subir de
  nível ✓; XP partilhado sobe nível/stats do pet ✓; bónus passivo
  ceil(300×0.1)=30 ✓; goal `maxed` dispara na transição 29→30 ✓.
- **Fight x300** com skills ofensivas: ae/de/dt/bf todos disparando; vitórias
  equilibradas (136/164); MAX_ROUNDS trava empates infinitos.
- **Vivos por HTTP** (stack local: server 8000): registo→personagem→boot com
  `sidekicks`+goals ✓; `getSidekicks` devolve 212 entradas ✓; ids falsos
  tratados em silêncio, SEM débito de donuts ✓; `getCharacter` devolve DO
  completo em `requested_character_sidekick` ✓; boot de conta real sem erros ✓.
- `php -l` limpo em todos os ficheiros tocados.

## 9. Correções de desempenho e sincronização (2026-08-26)

### 9.1 Lentidão global (~195ms por pedido → ~19ms)

O servidor corre PHP clássico (`php artisan serve`): caches estáticas morrem a
cada pedido. Cada ação pagava **dois parses** de `constants_json.data`
(zlib+json_decode, ~80ms cada): um pré-existente em `League::officialConstants()`
e um novo acrescentado pelo `Sidekick::official()` — ~160ms mortos em TODAS as
ações (o caminho passa sempre por `accountState()` → `template('autoLoginUser')`).

Correção: novo `app/HeroZero/AssetCache.php` — cache PERSISTENTE das fatias
derivadas (pequenas) em `storage/framework/cache/hz_*.cache`, validada por
`mtime:size` do asset (basta tocar no asset para invalidar sozinha), escrita
atómina (tmp+rename). Ligado a três classes:

| Classe | Antes (frio) | Depois |
|--------|-------------|--------|
| `Sidekick::official()` | 80ms | 1.0ms |
| `League::officialConstants()` | 82ms | 0.4ms |
| `GuildArtifacts::registry()` | ~80ms | <1ms |

Round-trip HTTP real (`goalsState`, 10 medições): **195ms → 19ms média**.
Build da cache: 84ms uma única vez (primeiro pedido após tocar no asset).

### 9.2 XP/nível do sidekick chegava tarde ao cliente

Relato: o XP do herói anima logo na barra ao concluir uma missão, mas o
sidekick não atualiza e o diálogo de subida de nível demora.

Causa: `actions/claimQuestRewards.php` devolvia resposta REDUZIDA
(`user`,`character`,`quests`) **sem `sidekicks`**. O cliente funde
`data.sidekicks` em qualquer resposta (`refreshSidekicks` → `Cd.update`) e é
esse merge que dispara `CharacterMessage.notifySidekickLevelUp` (diálogo) e
atualiza o modelo da barra. Com o modelo velho, tudo esperava pelo sync
seguinte (~60s) ou pela próxima ação que trouxesse o `accountState`.

Correção: `'sidekicks' => Sidekick::ownedByCharacter($char->id())` na resposta
do `claimQuestRewards`. As outras duas vias de XP partilhado já passavam por
`Live::accountState` (`claimTrainingStar`/trainingQuest.php e casino.php) e não
precisavam de alteração. Bónus: a concessão do primeiro sidekick (nível 60,
dentro de `syncLevelFromXp`) também aparecia com atraso e fica coberta.

Nota de diagnóstico: o nível "25" com xp=15000 visto em testes era estado
sintético inconsistente; `refreshLevelAndStats()` recalcula o nível SEMPRE pelo
XP acumulado contra `sidekick_levels` (15060 xp → nível 19), que é o correto.

## 10. Catálogo: habilidade especial e fonte de obtenção (2026-09-07)

Implementado em `Sidekick.php` — o `getSidekicks` agora devolve para cada entrada:

- **`special_skill_id`** — id da skill com `fixed_sidekick == <identifier>` (existe
  para ~188 dos 214 pets; os pets genéricos como dog1/cat1 não têm, o cliente
  mostra "sem habilidade especial" corretamente).
- **`sources`** — lista de chaves i18n `sidekick_source/<chave>` que indicam onde
  obter o pet. Derivado em duas camadas:
  1. **Dinâmica** (`buildOfficial()`): lê as constants do cliente para 105+ pets —
     event_quests, seasons, draw pools, treasures.
  2. **Estática** (`STATIC_SOURCES`): mapa hardcoded em `Sidekick.php` para os
     restantes ~107 pets normais (loja geral, loja sazonal, recompensa de
     desbloqueio, eventos, torneios, etc.) e os 2 pets indisponíveis (`not_available`).

O painel `UiSidekickInfoGeneral` do cliente lê `special_skill_id` e `sources[]`
diretamente do DO devolvido por `getSidekicks` — ambos agora preenchidos.

## Identificadores completos e reparação de nomes (BUG-000152)

`sidekicks.identifier` e `sidekicks.sewed_identifier` usam `varchar(128)`.
O nome personalizado continua limitado a 32 caracteres. Se estiver vazio,
o cliente procura `sidekick/<identifier>/name`; identificadores cortados
fazem aparecer a chave de tradução na lista e podem afetar a aparência.

Para instalações existentes:

```bash
php scripts/testing/migrate_sidekick_identifiers.php
php scripts/testing/migrate_sidekick_identifiers.php --apply
php scripts/testing/test_sidekick_identifiers.php "Nome do herói"
```

Sem `--apply`, o script só diagnostica. Com `--apply`, guarda backup JSON da
tabela, schema dos campos e plano em `server-laravel/storage/backups/`, amplia
os campos (ou cria o campo de aparência quando faltar) e repara apenas casos
com prova: um único identificador oficial com esse prefixo, ou histórico do
casino com uma única variante e contagem igual à dos pets afetados do herói.
Casos ambíguos ficam assinalados como pendentes; não se adivinha o sufixo.
DDL é executado fora da transação; reparações usam locks por herói e uma
transação, sem mudar nomes personalizados, habilidades, atributos ou saldos.

Na captura `pets.har`, 14/75 pets possuídos estavam truncados. Os três
`armoured_beasts_` foram recuperados como `armoured_beasts_2` pelo histórico.
As 14 traduções existem no ficheiro português servido. O teste MySQL cria um
pet com identificador longo, grava uma aparência longa, verifica ambos no
estado da conta e reverte tudo. A migração é idempotente. Recarregar o jogo
para substituir os objetos antigos em memória; confirmação visual pendente.

## Habilidades especiais na obtenção de pets (BUG-000153)

`Sidekick::create()` aplica sempre `official()['fixed_skills'][identifier]`
aos estágios definidos nas constantes. Os estágios restantes recebem o
sorteio normal ou os valores explícitos do chamador. A regra é central e
abrange prémios de casino, temporadas, itens/pets e desbloqueio inicial.
Mesmo uma chamada com habilidades explícitas não substitui as habilidades
fixas durante a criação. As habilidades especiais de estágio 0 continuam a
ser derivadas do identificador pelo sistema existente; não são inseridas
como habilidades dos estágios 1–3.

A alteração aplica-se aos pets criados a partir da correção. Não reescreve
habilidades de pets já possuídos, que podem ter sido modificadas por fusão.
O handler de reskill mantém o comportamento existente nesta alteração.

`php scripts/testing/test_sidekick_identifiers.php "Nome do herói"` verifica,
com rollback MySQL, criação dos 188 pets com habilidades fixas do catálogo,
chamada com habilidades explícitas, um pet genérico e identificadores longos.
O teste do casino verifica também as habilidades fixas no pet efetivamente
entregue pelo resgate. Não há migração de dados para esta regra de criação.

## Fontes do catálogo corrigidas (2026-10-05)

`getSidekicks` apresenta em `all_sidekicks.sources` as fontes derivadas de
conquistas (com limiar), passes premium/gratuitos, eventos, sorteios, tesouros,
objetos escondidos, torres e covis lendários. Referências explícitas das
constantes prevalecem sobre associações manuais; sem referência, mantém-se
o fallback local, sem o apresentar na documentação como evidência oficial.

Kylie usa `goal_different_sidekick_collected_140`: a interface recebe texto
que indica a conquista de 140 pets diferentes, em vez de brechó. O mesmo
tratamento cobre as outras conquistas de coleção e dungeon em modo difícil.
A interpretação de `sources` pelo cliente mantém o formato vector de strings;
as novas chaves têm traduções no asset pt_BR servido. A chave de cache
`sidekick_official_sources_v3` invalida os dados derivados anteriores.

Após atualizar constantes, sincronizar novos textos com:

```bash
python3 scripts/testing/sync_sidekick_source_texts.py --dry-run
python3 scripts/testing/sync_sidekick_source_texts.py
python3 scripts/testing/document_sidekicks.py
php scripts/testing/test_sidekick_sources.php --offline
php scripts/testing/test_sidekick_sources.php --hero="skel"
```

A sincronização escreve apenas chaves em falta nas duas cópias pt_BR, sem
substituir traduções existentes nem consultar a BD. O teste MySQL executa o
handler real com rollback. A geração do documento não modifica o catálogo.

## Geração no brechó em época normal (2026-10-05)

A geração usa `Character::normalShopSidekickPool`, com fonte `shop_general`
e qualidade do template, em vez das antigas listas abrangentes. Há 27 pets
épicos e um comum elegíveis; conquistas, eventos, ofertas, desbloqueio e
lendários não entram. A loja desportiva não tem candidatos de época normal
e não gera pets por agora. A restrição aplica-se a novas ofertas, sem
remover exemplares possuídos ou ofertas anteriores. Ver
[regras e validação das lojas](../shop_items/lojas.md).

## Atributos da oferta iguais aos entregues na compra (2026-10-05)

A geração no brechó sorteia os atributos e persiste-os em `items.stat_*`. A
compra preserva esses valores, mesmo após subir de nível, e não volta a
sortear as bases. Ofertas existentes não nulas são respeitadas; as novas
variam após refresh. Ver [scaling](scaling.md) e `test_shop_pet_stats.php`,
validado com compras MySQL sob rollback.
