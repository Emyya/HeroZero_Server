# Geração dos NPCs das Gangues (Guild Dungeon)

> Fórmula atual implementada em `server-laravel/app/HeroZero/GuildBattles.php`
> (`simulateDungeonBattle()`). Atualizado em 2026-08-28.

---

## 1. Dificuldade

A dificuldade é sorteada quando a gangue é criada (`actions/guildDungeon.php`):

| Dificuldade | `settings.difficulty` | Probabilidade | Fator de stats |
|---|---|---|---|
| Fácil | 1 | 50% | ×0.8 |
| Médio | 2 | 30% | ×1.2 |
| Difícil | 3 | 20% | ×1.5 |

O fator fica guardado em `guild_dungeon.settings.difficulty` e é copiado para
a coluna `settings` da batalha no agendamento.

## 2. Quantidade de NPCs

Sorteada na resolução da batalha (gangue fresca), conforme a dificuldade:

| Dificuldade | NPCs gerados |
|---|---|
| Fácil | 3–5 |
| Médio | 3–6 |
| Difícil | 4–8 |

## 3. Base dos stats

Média dos stats **totais** (com equipamento, `Character::battleProfile('a')`)
dos membros agendados (`character_ids` da batalha):

```
avg(stat) = soma(stat de cada membro) / nº de membros
```

Aplica-se a: stamina, strength, criticalrating, dodgerating, weapondamage.
O nível = média arredondada dos níveis.

## 4. Escadinha de stats (fraco → forte)

Os NPCs são ordenados do **mais fraco para o mais forte**. O último NPC
(mais forte) usa o fator da dificuldade; cada passo para trás reduz **5% da
média** (não do anterior):

```
fator(pos i) = fator_dificuldade − 0.05 × ((N − 1) − i)     [i = 0..N−1]
```

Exemplo — fácil com 3 NPCs: 0.70 / 0.75 / 0.80 da média.
Exemplo — difícil com 8 NPCs: 1.15 / 1.20 / ... / 1.50.

Cada stat individual = `round(avg(stat) × fator(pos))`, mínimo 1.

## 5. HP e dano

```
hitpoints      = stamina × battle_hp_scale (10)
damage_normal  = strength + weapondamage
damage_bonus   = strength + weapondamage
chance_critical = 0.1
chance_dodge    = 0.1
```

## 6. Identidade visual/nome de cada NPC

Campo `profile` no formato oficial:

```
"<idx>_<npcId>-<secção>"
ex.: "0_npc_dungeon3_npc1-first_half"
```

- Secções: `always` | `first_half` | `second_half` | `end` (o cliente resolve
  nome/imagem a partir disto — ver `npcs_names.md`)
- O **último NPC** (mais forte) usa um NPC da secção `end`;
- Os restantes distribuem-se por `first_half`/`second_half` conforme a
  composição real da gangue (`data/reference/guild_dungeon_teams.json`);
- Fallback se a gangue não tiver definição: catálogo genérico por hash
  (`crc32(team) % 107`) com secção `always`.

## 7. Persistência (gangue ferida)

Se a gangue **sobreviver**, os perfis EXATOS (stats + HP restante) ficam
guardados em `guild_dungeon.npc_team_character_profiles` e a batalha seguinte
NÃO regenera nada (sem cura). `settings.reroll_blocked=1` até a gangue morrer;
ao vencer, `defeated=1` → nova gangue sorteada na próxima seleção.

## 8. Recompensas

Só se a guilda vencer (eliminar todos os NPCs):

- **Por membro participante** (`guild_battle_rewards`, type=3):
  ```
  coins = floor((nível_do_membro + 10)² × fator_dificuldade)
  ```
  (ex.: nível 135, médio → floor(145² × 1.2) = 25.230 moedas)

- **Objeto `rewards` da batalha** (formato `DOGuildDungeonBattleReward`,
  chaves obrigatórias ou o cliente crasha):
  ```json
  {"guild_dungeon_battle_id": X, "game_currency": 1, "xp": 0,
   "item_id": 0, "training": 0, "energy": 0}
  ```

- O claim (`claimGuildDungeonBattleReward`) credita as moedas ao personagem,
  apaga a linha de recompensa e limpa `finished_guild_dungeon_battle_id`.
