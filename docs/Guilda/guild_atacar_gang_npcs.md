# Guilda — Atacar Gangue de NPCs (guild dungeon)

> Estado: **FUNCIONAL** — fluxo completo de ataque, resolução lazy, visualização
> da batalha e claim de recompensa. Nomes dos NPCs aparecem genéricos (cosmético).
> Gangues não derrotadas persistem (stats/HP) até serem eliminadas; reroll
> bloqueado entretanto (BUG-000053). Última atualização: 2026-08-22.

---

## 1.1 Persistência da gangue não derrotada (BUG-000053, ox-alpha 2026-08-22)

### Comportamento novo

1. Ao atacar uma gangue e **não a derrotar**, a MESMA gangue volta a aparecer
   na seleção até ser eliminada — com os **stats/HP exatos do fim da última
   batalha** (NPCs não curam nem ressuscitam).
2. O botão "novo inimigo" (reroll) fica **bloqueado** entretanto:
   - cliente: o preview nem mostra o botão;
   - servidor: `rerollGuildDungeonNPCTeam` lança
     `errRerollGuildDungeonNPCTeamNotDefeated` (defesa extra).
3. Dentro da mesma batalha o HP do NPC também persiste entre membros
   (antes: o NPC "curava" quando morria um atacante).
4. Gangue derrotada → `settings.defeated=1` → o próximo
   `getGuildDungeonNPCTeam` sorteia gangue nova e o reroll desbloqueia.

### Estado guardado

Fonte única: `guild_dungeon.npc_team_character_profiles` + `guild_dungeon.settings`.

| estado | `npc_team_character_profiles` | settings | diálogo do cliente |
|---|---|---|---|
| gangue fresca (nunca batalhada / nova após derrota) | `[]` | — | seleção (`vz`) COM botão reroll |
| gangue batalhada e viva | perfis dos NPCs VIVOS: `hitpoints` = HP restante, `start_hitpoints` = HP restante | `reroll_blocked=1` | preview (`Px`) com **barra de HP danificada**, "vivos/total", nível — SEM botão reroll |
| gangue derrotada | `[]` | `defeated=1` | próximo get sorteia gangue nova |

### Detalhes do cliente que tornam isto possível (min.js html5_257)

- Dispatcher de `getGuildDungeonNPCTeam`: profiles VAZIOS → abre `vz`
  (seleção com reroll); profiles PREENCHIDOS → abre `Px`
  (`DialogGuildDungeonBattlePreview`) que renderiza `_npcBar = GF(total, current)`,
  vivos/total (`get_npcTeamMemberAliveCount/get_npcTeamMemberCount`) e nível.
- `LC.get_remainingHitpoints()` lê `hitpoints`;
  `get_startHitpoints()` lê `start_hitpoints` se presente.
- Sem "rounds" na resposta, `Oe.endHitPoints()` usa start_hitpoints como HP
  corrente → por isso os sobreviventes são gravados com
  `start_hitpoints = hitpoints`: a barra nasce DANIFICADA (só `hitpoints`
  aparecia cheia).
- Escala validada: `constants.battle_hp_scale = 10` = `Fight::HP_SCALE`.
- Patch adicional no `vz.refresh`: botão "novo inimigo" condicionado a
  `getIntSettingsValue("reroll_blocked",0)==0` (defensivo; com perfis
  preenchidos o cliente já usa `Px`, que nem tem o botão).

### Alterações ao código

**`app/HeroZero/GuildBattles.php` → `simulateDungeonBattle()`:**
- carrega estado persistido da coluna `npc_team_character_profiles`;
  se vier preenchida, usa esses perfis EXATOS (não regenera stats);
- NPCs com `hitpoints <= 0` entram na lista de mortos (não lutam);
- no fim de CADA luta individual, o HP do NPC é atualizado no perfil
  (`Fight::hitpointsB()`) — persiste para o membro seguinte e para batalhas
  futuras; vitória sobre um NPC marca-o com `hitpoints = 0`;
- derrota da guilda → grava sobreviventes (com `start_hitpoints`) na coluna +
  `settings.reroll_blocked=1`;
- vitória da guilda → coluna a `[]`, limpa `reroll_blocked`, `defeated=1`.

**`actions/guildDungeon.php`:**
- `getGuildDungeonNPCTeam`: se `settings.defeated`, re-sorteia gangue nova
  (`randomiseDungeon(true)`);
- `rerollGuildDungeonNPCTeam`: bloqueado se `settings.reroll_blocked`.

**`public/js/HeroZero.min.js`:**
- patch em `vz.refresh` (botão condicional, ver acima).

### Preview depois de agendar (BUG-000136)

O agendamento copia os perfis conhecidos da gangue para
`guild_dungeon_battle.npc_team_character_profiles`, como string JSON. Assim,
login, estado normal e respostas parciais mantêm membros, nível e HP restante.
Ataques antigos pendentes com perfis vazios recuperam o preview da mesma gangue
(`guild_id` + `npc_team_identifier`), sem alterar a BD. Esta recuperação não
se aplica a batalhas terminadas. Gangues frescas continuam com dados desconhecidos.

### Como testar / repor

```sql
-- ver estado atual da gangue da guilda 1
SELECT npc_team_identifier, npc_team_character_profiles, settings
  FROM guild_dungeon WHERE guild_id = 1;

-- repor gangue fresca (volta ao diálogo de seleção com reroll)
UPDATE guild_dungeon SET npc_team_character_profiles='[]',
       settings=JSON_REMOVE(settings,'$.reroll_blocked','$.defeated')
       -- settings é TEXT/JSON string: se TEXT, usar manipulação em PHP/CLI
 WHERE guild_id = 1;
```
Fluxo de teste rápido: agendar ataque → adiantar relógio para depois do slot
(`/servertime`) → login/sync resolve a luta → reabrir seleção: se a guilda
perdeu, o preview (`Px`) abre com a barra de HP danificada e sem botão reroll;
repetir até vencer → a gangue seguinte sai nova.

---

## 1. Como deve funcionar (comportamento pretendido)

1. **Marcar ataque**: um membro da guilda escolhe uma gangue NPC e um horário
   (slots fixos 12/16/18/20/22h). O ataque fica **pendente** (`status=2`) com
   `ts_attack` calculado no relógio de jogo (`Live::gameNow`, Europe/Lisbon).
   Regra oficial: se `hora_atual >= hora_slot - prep(10h)`, o slot cai para
   **amanhã**.
2. **Outros membros juntam-se** ao ataque pendente (`joinGuildDungeonBattle`).
3. **Quando chega a hora**, o servidor deve **simular a luta automaticamente**
   (sem timer): cada membro participante enfrenta os NPCs em sequência; o HP
   dos NPCs persiste entre membros; quem morre é substituído pelo seguinte.
   Ordem de entrada OFICIAL, fixa e não configurável: **nível menor → maior**
   ("Na sequência"); empate resolvido pelos pontos de habilidade
   (`stat_base+stat_bought+stat_trained` dos 4 stats). Quando o último NPC é
   derrotado a batalha acaba — membros que não chegaram a entrar ficam sem
   lutar (continuam com recompensa se a guilda venceu). Modelo oficial = lazy:
   a simulação corre no **primeiro pedido após `ts_attack` passar**
   (`GuildDungeonBattle::checkFight` no oficial).
4. **Qualquer membro que faça login e entre na team depois da hora** deve ver:
   - o diálogo com o resultado da luta (abre automaticamente no painel "guild");
   - a reprodução da luta (botão "ver batalha") com os rounds;
   - a recompensa por levantar (moedas se a guilda ganhou).
5. O mesmo se aplica (futuramente) às **batalhas entre teams**
   (`pending_guild_battle_attack_id` / `pending_guild_battle_defense_id`) —
   ainda NÃO existe fluxo de agendamento para isso neste servidor.

---

## 2. O que já está implementado e a funcionar

### Servidor

| Peça | Sítio | Estado |
|---|---|---|
| Agendamento do ataque (slots + janela de preparação em HORAS, fuso de jogo) | `actions/guildDungeon.php` (`attackGuildDungeonNPCTeam`) | ✅ |
| Juntar-se ao ataque | `joinGuildDungeonBattle` | ✅ |
| Simulação da luta (classe partilhada) | `app/HeroZero/GuildBattles.php` → `simulateDungeonBattle()` | ✅ |
| Persistência da gangue não derrotada (stats/HP) + reroll bloqueado | `GuildBattles` + `guildDungeon.php` + patch `min.js` (secção 1.1) | ✅ |
| Resolução automática fora de hora em QUALQUER login/sync | `Live::accountState()` (topo, ANTES dos overlays) | ✅ |
| Anexar resultado à resposta | `finished_guild_dungeon_battle` + `guild_dungeon_battle_rewards` no `accountState` | ✅ |
| Apontador no personagem | `character.finished_guild_dungeon_battle_id = <battle id>` gravado aos participantes na resolução; claim repõe a 0 | ✅ |
| Claim da recompensa (moedas + XP) | `claimGuildDungeonBattleReward` | ✅ |
| Refactor: actions usam a classe partilhada | `guildDungeon.php` usa `GuildBattles::resolveDueDungeonBattle()` | ✅ |

### Formatos do cliente descobertos (HeroZero.min.js, build html5_257)

O cliente é **muito sensível**: o DataObject lança exceção com chave
desconhecida, e lookups de catálogo devolvem null e crasham mais à frente.

**`finished_guild_dungeon_battle`** (DO `Oe`, linha ~2113):
- chaves: id, guild_id, battle_time, ts_attack, npc_team_identifier, status,
  settings, character_ids, joined_character_profiles, npc_team_character_profiles,
  rounds (todos os JSON como strings).

**`joined_character_profiles`** — MAPA indexado pelo ID do personagem:
```json
{ "25295": { "profile": "25295", "name": "...", "gender": "m", "level": 134,
  "position": 1, "stamina": ..., "total_stamina": ..., "strength": ...,
  "criticalrating": ..., "dodgerating": ..., "weapondamage": ...,
  "damage_bonus": ..., "appearance_*": ..., "show_mask": true, "belt": ..., ... } }
```
- `profile` = id do personagem (o cliente faz parseInt → identificador do
  lutador; `LC.get_npcIdentifier` corta no `_` se existir).
- `damage_bonus` é OBRIGATÓRIO: o tooltip (`pc.getStatGuildBattleTooltip`)
  faz `getInt("damage_bonus")` e chave ausente = exceção "unknown field".
- Outras chaves lidas: stamina, strength, criticalrating, dodgerating.

**`npc_team_character_profiles`** — lista de 5 entradas; cada entrada precisa de:
```json
{ "profile": "<team>_<npcIdentifier>-<indice>", "level": ..., "stamina": ...,
  "strength": ..., "criticalrating": ..., "dodgerating": ...,
  "weapondamage": ..., "hitpoints": ..., "damage_normal": ..., "damage_bonus": ... }
```
- `LC.get_npcIdentifier()` deriva o NPC do campo `profile`: parte entre o
  primeiro `_` e o `-` → `Hl.fromId(npcIdentifier)` no catálogo
  `constants.npcs` (**identificadores tipo `"npc_adventurer"`**; catálogo local:
  `data/reference/quest_templates.json`, chave `npcs`, 107 entradas).
- Se o identificador não existir no catálogo → `fromId(...)` devolve null →
  crash `get_type of null`.
- Implementação atual: `profile = "<npc_team_identifier>_<npcId>-<i>"`, com
  npcId escolhido deterministicamente do catálogo via
  `crc32(npc_team_identifier) % count(npcs)` (não temos a composição real das
  teams oficiais).

**`rounds`** — lista de golpes; em luta de GUILDA os lados são IDS DE PERFIL
(oficial `Fight.php:101`), não 'a'/'b':
```json
[ {"a": "25295", "d": "team17_npc_x-0", "r": 2, "v": 1749}, ... ]
```
- `a` = atacante, `d` = defensor; `r`: 1=esquiva, 2=golpe, 3=crítico;
  `v` = dano.
- `HeroZero\Fight` tem modo guilda (3.º argumento do construtor) que emite
  este formato; nos duelos/missões mantém 'a'/'b'.

**Visibilidade da luta** — o cliente só abre o diálogo se TUDO bater:
```
character.finished_guild_dungeon_battle_id == finished_guild_dungeon_battle.id
   && finished_guild_dungeon_battle.status == 3
   && painel ativo == "guild"
   && !Vl.wasShown(id)
```
(`Y.get_finishedGuildDungeonBattle` + `Vl` dialog). A recompensa do próprio
personagem vai em `guild_dungeon_battle_rewards` (alimenta `get_isWon`/
`get_reward`); some da resposta quando reclamada.

---

## 2.1 Recompensas por vitória (BUG-000105, 2026-09-06)

Ao vencer a gangue cada participante recebe **moedas e XP** na tabela
`guild_battle_rewards` (type=3). Os valores são calculados em
`GuildBattles::simulateDungeonBattle()` e entregues no `claimGuildDungeonBattleReward`.

### Fórmulas

**Moedas:**
```
coins = floor((nível + 10)² × mult)
```

**XP:**
```
xpStep = xpForLevel(nível + 1) - xpForLevel(nível)
xp     = floor(xpStep × xpPct)
```
onde `xpStep` é o XP necessário para subir **do nível actual para o seguinte**
(tabela oficial, `data/levels_xp.json`) e `xpPct` é a percentagem por dificuldade:

| Dificuldade | `mult` | `xpPct` |
|---|---|---|
| Fácil (1) | 0.8 | 15% |
| Médio (2) | 1.2 | 25% |
| Difícil (3) | 1.5 | 50% |

Exemplo nível 305 (xpStep = xpForLevel(306) − xpForLevel(305) = 577 372):

| Dificuldade | Moedas | XP |
|---|---|---|
| Fácil | — | 86 605 |
| Médio | — | 144 343 |
| Difícil | — | 288 686 |

### Como o cliente recebe o XP

O diálogo de recompensa lê o array `guild_dungeon_battle_rewards` enviado no
`accountState` (via `Live::attachGuildBattleState`). Cada entrada é um
`DOGuildDungeonBattleReward` — o cliente só adiciona XP ao diálogo se
`get_xp() > 0` (via `get_genericRewards()`). O campo `xp` tem de vir preenchido
neste array; se vier `0` o XP não aparece na UI mesmo que tenha sido aplicado
à conta (ver BUG-000106).

### Schema

A coluna `xp INT NOT NULL DEFAULT 0` foi adicionada à tabela `guild_battle_rewards`
(`docker/initdb/01_schema.sql`). Em BDs existentes é necessário migrar:
```sql
ALTER TABLE `guild_battle_rewards` ADD COLUMN `xp` INT NOT NULL DEFAULT 0 AFTER `game_currency`;
```

---

## 3. Bloqueador atual

Ao clicar "ver a batalha" o cliente ainda crasha:

```
line 14698 col ~257: TypeError: can't access property "get_name", b is null
F.updateGuildDungeonBattleCharacters(a):
    b = F.get_guildDungeonBattle().getCharacter(a.get_attacker())   // ok?
    c = ....getCharacter(a.get_attacked())                          // <- suspeito
    ... refreshFromGuildDungeonBattleProfile(a, b, a.get_name(), b.get_name())
```

`b` (ou o resultado de `getCharacter`) vem **null** — ou seja, algum id
referenciado nos rounds não existe na lista `_characters` construída a partir
de `joined_character_profiles` + `npc_team_character_profiles`.

### Hipóteses a verificar (por ordem)

1. **Chave de lookup do NPC**: `Oe.getCharacter(id)` compara o id pedido com o
   que resulta do parse de cada entrada (`parseInt(profile)` para membros; para
   NPCs o parse faz `substr(0, indexOf("_"))` → **"team17"**, não o id
   completo!). Verificar no bundle (linha ~2114) COMO o NPC é registado em
   `_characters` e qual chave o round usa. É provável que o formato oficial do
   `profile` do NPC seja outro (ex.: só o índice, ou `<idx>-<npcId>`), apesar
   de `LC.get_npcIdentifier` sugerir o formato `<team>_<npc>-<n>`.
2. **NPCs mortos**: pode ser preciso incluir TODOS os 5 NPCs mesmo os
   derrotados (já incluímos) e/ou campos `start_hitpoints` /
   `total_hitpoints` nas entradas (`LC.get_startHitpoints` le
   `start_hitpoints` se presente).
3. **Cache do cliente**: confirmar sempre com F5 — o objeto
   `_finishedGuildDungeonBattle` só refresca via `updateData` de respostas que
   tragam a chave.

### Como depurar

- Erro completo do cliente: `server-laravel/storage/logs/last_error.json`
  (o paste do utilizador costuma truncar o topo do stack — é aí que está a
  informação útil).
- Tráfego: `.logs/server.log`; consola do browser também reporta.
- Repor a batalha 11 para testar de novo:
  ```sql
  DELETE FROM guild_battle_rewards WHERE guild_battle_id=11;
  UPDATE guild_dungeon_battle SET status=2, rounds='', joined_character_profiles='[]',
         npc_team_character_profiles='[]' WHERE id=11;
  UPDATE guild SET pending_guild_dungeon_battle_attack_id=11 WHERE id=1;
  UPDATE `character` SET finished_guild_dungeon_battle_id=0 WHERE id IN (25295,25296);
  ```
  Depois basta um syncGame/login que a luta volta a resolver sozinha.
- Testar o parser do cliente isoladamente: extrair as funções
  `Oe.getCharacter`/`LC` (linhas ~2113-2129 do min.js) e correr com o JSON real
  da DB para descobrir a chave exata do lookup.

---

## 4. Correções já feitas nesta sequência (resumo cronológico)

1. `lastInsertId()` resetado por UPDATE intercalado → duelo com shape do
   template → LOC/heróis invisíveis (ver BUG-000037).
2. `joined_character_profiles` era lista de aparências sem `profile` →
   crash `"c is undefined"` (linha 2114) → convertido para mapa indexado por
   character id (formato oficial `characterAppearance`).
3. Falta de `damage_bonus` nos perfis → exceção "unknown field
   damage_bonus" no tooltip → adicionado (= força + weapondamage).
4. NPC `profile` sem identificador válido → `Hl.fromId(...) is null` →
   formato `<team>_<npcId>-<indice>` com npcId do catálogo.
5. Rounds com lados 'a'/'b' → convertidos para ids de perfil
   (`Fight` modo guilda, `'a'`=atacante, `'d'`=defensor).
6. `finished_guild_dungeon_battle_id` não era gravado nos participantes →
   diálogo nunca abria → gravado na resolução.
7. Resolução corria depois dos overlays no accountState → flag saía velha →
   movida para o topo (com reload do Character).
8. (BUG-000053, 2026-08-22) Gangue não derrotada não persistia: NPCs
   regenerados de raiz a cada batalha (stats novos, HP cheio), reroll livre,
   HP do NPC curava entre membros da mesma luta → persistência completa em
   `guild_dungeon.npc_team_character_profiles`, reroll bloqueado, barra de HP
   danificada no preview (ver secção 1.1).
9. (2026-08-22) Threshold do "verde online" nos classificados do torneio de
   gangue (`Tournament::leaderboard`) unificado para 300s no relógio de jogo
   (ver BUG-000051).

## 5. Ficheiros envolvidos

| Ficheiro | Papel |
|---|---|
| `server-laravel/app/HeroZero/GuildBattles.php` | Simulação/resolução partilhada |
| `server-laravel/app/HeroZero/Fight.php` | Combate (modo guilda: rounds com ids) |
| `server-laravel/app/HeroZero/Live.php` | accountState: resolve + anexa resultado |
| `server-laravel/actions/guildDungeon.php` | Actions do fluxo (marcar/juntar/claim) |
| `server-laravel/data/reference/quest_templates.json` | Catálogo de npcs (`npcs`) |
| `server-laravel/public/js/HeroZero.min.js` | Contrato (linhas ~2113-2129: Oe/LC; ~13428: tooltip; ~14698: visualização) |

---

## 6. Sessão 2026-08-22/23 — resumo do que foi feito (debug com relógio avançado)

Testado ponta-a-ponta com o relógio de jogo avançado (+24h, +24h) via
`GameTime::addHours(24)`: agendamento → resolução lazy → derrota parcial da
gangue → persistência → re-agendamento.

Resultados confirmados na DB (guilda 1, team17):
- batalha #14: 5 NPCs (`_0`..`_4`), guilda PERDEU;
- NPC `_0` morto (hp=0), NPC `_1` ferido (hp=1612), `_2`..`_4` cheios
  (hp=24260 = média stamina participantes ×1.x ×10);
- seleção seguinte mostra os 4 vivos ("vivos/total" do cliente) com HP
  persistido; reroll bloqueado.

Correções adicionais desta sessão:
- BUG-000054: `npc_team_character_profiles` enviado como STRING JSON em TODAS
  as shapes (dungeonShape, pending no get/join/attack, attachGuildBattleState)
  — array rebentava o JSON.parse do cliente.
- Espelho de texturas: CDN oficial devolve 404 para `ui.atlas`/`ui.ktx2`
  (hashes antigos do html5_257). Ficheiros copiados para
  `server-laravel/public/assets/textures/` e rewrite de XHR no `index.html`
  (lista MIRRORED) para servirem da origem local.
- Patch `min.js`: botão "novo inimigo" condicionado a
  `getIntSettingsValue("reroll_blocked",0)`.

---

## 7. ✅ RESOLVIDO (2026-08-22) — geração dos NPCs: escadinha fraco→forte

Investigação: os valores batiam certos com a fórmula ("média × fator") — o
"problema" era que os 5 NPCs eram gerados IDÊNTICOS. Nos oficiais vêm
ordenados do mais fraco para o mais forte.

Fórmula implementada em `GuildBattles::simulateDungeonBattle()`:
- Base = média dos stats TOTAIS (`battleProfile('a')`) de **todos os membros da guilda** (ver BUG-000105);
- O ÚLTIMO NPC (mais forte) = média × fator de dificuldade
  (facil ×0.8, medio ×1.2, dificil ×1.5);
- Cada passo para trás reduz **5% da média** (não do anterior):
  fator(pos i) = top − 0.05 × (N − i);
- Quantidade sorteada: facil 3–5, medio 3–6, dificil 4–8;
- HP = stamina × battle_hp_scale(10); nível = média dos níveis;
- Exemplo (facil, 3 NPCs): 0.70 / 0.75 / 0.80 da média.

A persistência de HP/stats entre batalhas (BUG-000053) mantém-se igual.
