# Onde ficam guardados os nomes dos NPCs das Gangues

> Os nomes NÃO vêm da base de dados nem do servidor — o cliente resolve-os a
> partir de **ficheiros de idioma** (textos de localização). Atualizado em
> 2026-08-28.

## 1. Ficheiros de texto (fonte oficial)

```
assets/i18n/<locale>/text.data        ex.: assets/i18n/pt_BR/text.data
```

- Formato: JSON comprimido com zlib (header `78da`)
- ~20.000 chaves por idioma; `pt_BR` é o locale do personagem de teste

## 2. Chaves usadas pela gangue

| O quê | Chave | Exemplo (team11) |
|---|---|---|
| Nome da gangue | `guild_dungeon_team/<team>/name` | "Colegas do Curso de Esqui" |
| Descrição | `guild_dungeon_team/<team>/description` | |
| Texto vitória | `guild_dungeon_team/<team>/won` | |
| Texto derrota | `guild_dungeon_team/<team>/lost` | |
| Nome de um membro NPC | `guild_dungeon_team/<team>/<secção>/<npcId>/name` | "Professor de esqui desesperado" |

Secções possíveis: `always`, `first_half`, `second_half`, `end`.

## 3. Como o cliente chega ao nome

O servidor envia cada NPC com um campo `profile`:

```
profile = "<idx>_<npcId>-<secção>"      ex.: "3_npc_dungeon3_npc4-end"
```

No cliente (`HeroZero.min.js`, classe `LC`):

```js
get_npcIdentifier() → substring de profile ENTRE "_" e "-"   → npcId
get_name()          → text("guild_dungeon_team/" + team + "/"
                           + substring APÓS o primeiro "-"   → secção
                           + "/" + npcId + "/name")
```

Se a chave não existir, o `LocText` devolve a própria chave — por isso é que
um formato errado no `profile` mostra o "caminho" como nome.

A imagem/thumbnail vem de `ma.assetURL("npc/<npcId>", "webp")` e a voz de
`constants.npcs[npcId].voice` (catálogo em `data/reference/npcs.json`).

## 4. Cópia de referência no servidor

Extração dos nomes/composição das 28 gangues (feita do `pt_BR/text.data`):

```
server-laravel/data/reference/guild_dungeon_teams.json
{
  "team11": {
    "name": "Colegas do Curso de Esqui",
    "description": "...", "won": "...", "lost": "...",
    "sections": {
      "first_half":  {"npc_dungeon3_npc1": "Novato Menor de Idade",
                      "npc_dungeon3_npc5": "Professor de esqui desesperado"},
      "second_half": {"npc_dungeon3_npc2": "...", "npc_dungeon3_npc3": "..."},
      "end":         {"npc_dungeon3_npc4": "Alucinação de vertigem de altura"}
    }
  }, ...
}
```

É este ficheiro que `GuildBattles::simulateDungeonBattle()` usa para escolher
o npcId/secção corretos de cada membro gerado.

## 5. Outros nomes NPC (fora das gangues)

- NPCs de quests: chave `npc/<npcId>/name` no mesmo `text.data`
- Lista completa de identificadores (270): `data/reference/npcs.json`
