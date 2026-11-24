# Respostas reais capturadas (servidor oficial)

> Fonte: ficheiros em `server-laravel/data/*.json` — respostas reais do servidor oficial
> do Hero Zero (capturas HAR), personagem nível ~94 no servidor `br27`.
> Este documento complementa a análise do cliente (`HeroZero.min.js`) — é a prova real
> do formato das respostas que o nosso servidor tem de reproduzir.

## Envelope geral

Todas as respostas seguem o envelope `{data, error}` e o `data` contém **apenas o delta**
dos objetos alterados (partial updates), mais `time_correction` e `server_time`:

```json
{
  "user":      { "id": 26941, "premium_currency": 1, "blocked_premium_currency": 0 },
  "character": { "...campos alterados do personagem..." },
  "hideout":   { "...só os campos do hideout que mudaram..." },
  "time_correction": 0.02246403694152832,
  "server_time": 1783947913
}
```

Notas:
- `character` inclui sempre os stats totais (`stat_total_stamina`, etc.), boosters activos,
  energias (`quest_energy`, `duel_stamina`, ...) e `tutorial_flags` quando muda.
- `tutorial_flags` é uma **string JSON** dentro de `character`. Flags relacionadas com hideout
  observadas numa conta que acabou o tutorial:
  `hideout_first_building`, `hideout_first_collect`, `hideout_first_upgrade`,
  `hideout_build_stone`, `hideout_collect_stone`, `hideout_tutorial_show_attacker`,
  `hideout_build_glue`, `hideout_collect_attacker_units`, `hideout_first_attack`,
  `hideout_tutorial_completed`.

## Chaves hideout por ação (observado nas capturas)

| Ação | Chaves devolvidas além de `user`/`character` |
|---|---|
| `checkHideoutRoomActivityFinished` | `hideout` (parcial), `hideout_rooms` (array parcial) |
| `collectHideoutRoomActivityResult` | `hideout`, `hideout_room` (objeto único), `current_goal_values` |
| `storeHideoutRoom` | `hideout`, `hideout_room` |
| `collectHideoutFightRewards` | `hideout`, `hideout_battle` (parcial), `current_goal_values`, `daily_bonus_lookup` |
| `startHideoutTutorialFight` | `hideout`, `hideout_battle` (completo) |
| `finishHideoutTutorial` | `hideout` (recursos) |
| `getHideoutOpponent` | `hideout`, `hideout_opponent` (completo) |
| `getHideoutBattleHistoryFights` | `hideout_history_battles` (array; vazio nesta captura) |
| boot `autoLoginUser` | `missed_hideout_attacks`, `guild_gem_production_level`, `constants{7 chaves hideout}` |

Chaves `constants` do boot relacionadas com hideout:
`hideout_rooms`, `hideout_units`, `hideout_expansion`, `hideout_room_upgrade`,
`hideout_generator_upgrades`, `hideout_room_blacksmith_items`, `hideout_room_blacksmith_product`.

## Objeto `hideout` (campos observados nas respostas parciais)

```json
{
  "id": 9711,
  "hideout_points": 1,
  "current_level": 5,
  "idle_worker_count": 1,
  "current_resource_glue": 200,
  "current_resource_stone": 343,
  "current_attacker_units": 1,
  "ts_last_opponent_refresh": 0,
  "active_battle_id": 4853805,
  "current_opponent_id": 21487,
  "current_opponent_server_id": "br27"
}
```
(As respostas só trazem os campos alterados; a lista completa de campos suportados
está na análise da classe `DOHideout` no JS.)

## Objetos `hideout_room` / array `hideout_rooms`

```json
{ "id": 75958, "status": 6, "current_resource_amount": 150, "max_resource_amount": 150,
  "ts_last_resource_change": 1783947888 }
```

Valores de `status` observados: `6` = produção automática a correr (stone production),
`5` = storing (a guardar sala), `1` = idle após collect.
Outro exemplo (`storeHideoutRoom`): `{ "id": 75955, "status": 5,
"ts_last_resource_change": ..., "ts_activity_end": ... }`.

## Objeto `hideout_battle` (estrutura completa — `startHideoutTutorialFight`)

```json
{
  "id": 4853805,
  "attacker_hideout_id": 9711,
  "defender_hideout_id": -1,            // -1 = luta tutorial (sem alvo real)
  "defender_server_id": "br27",
  "attacker_status": 1,
  "defender_status": 1,
  "attacker_count": 1,                  // nº de robôs atacantes enviados
  "attacker_profiles": "{...}",         // STRING JSON
  "defender_count": 0,
  "defender_profiles": "{...}",         // STRING JSON
  "hideout_winner_id": 9711,
  "server_winner_id": "br27",
  "rounds": "[...]",                    // STRING JSON
  "attacker_rewards": "{...}",
  "attacker_bonus_rewards": "{...}",
  "defender_rewards": "{...}",
  "destroyed_attacker": 0,
  "destroyed_defender": 0,
  "attacker_character_name": "...",
  "defender_character_name": "..."
}
```

### `attacker_profiles` / `defender_profiles` (strings JSON)

Perfil de cada participante (robôs):

```json
{"a": {"level": 1, "stamina": 100, "strength": 100, "criticalrating": 10,
       "dodgerating": 10, "weapondamage": 0, "damage_bonus": 100}}
```

Defesa (tutorial) tem ainda parede e lista extra:
```json
{"w": {"level": 1, "stamina": 100, "strength": 10, "criticalrating": 10,
       "dodgerating": 10, "weapondamage": 0, "damage_bonus": 10},
 "d": {"level": 1, "stamina": 100, "strength": 100, "criticalrating": 10,
       "dodgerating": 10, "weapondamage": 0, "damage_bonus": 100},
 "oc": [2, 39, 49, 50, 51, 52, 53, 54, 55, 56, 57]}
```

- Prefixos dos perfis: `a_N` = atacante N, `w_N` = parede N, `d_N` = defensor N
  (as chaves `rounds` usam `a_0`, `w_0`, ...).
- `oc` — array de ids/tipos usado pelo cliente para a apresentação (personagens
  decorativas/oponentes visuais).
- Campos do perfil: `level`, `stamina` (HP), `strength` (ataque),
  `criticalrating`, `dodgerating`, `weapondamage`, `damage_bonus` (%).

### `rounds` (string JSON)

Array de rondas; cada ronda é um golpe:

```json
[{"a":"a_0","d":"w_0","r":2,"v":81},
 {"a":"w_0","d":"a_0","r":2,"v":9},
 ...
 {"a":"a_0","d":"w_0","r":1},          // sem "v"
 {"a":"w_0","d":"a_0","r":3,"v":172}]
```

- `a` — chave do atacante (`a_0`, `w_0`, `d_0`, ...)
- `d` — chave do defensor
- `r` — tipo de resultado observado: `2` = golpe normal, `3` = crítico (valores
  de dano maiores), `1` = falhou/esquivou (ronda sem campo `v`)
- `v` — dano causado

## Objeto `hideout_opponent` (resposta de `getHideoutOpponent`)

```json
{
  "opponent": {
    "id": 21487, "name": "JiminaSeinen", "gender": "m", "level": 94,
    "stat_base_stamina": 400, "stat_base_strength": 372,
    "stat_base_critical_rating": 518, "stat_base_dodge_rating": 553,
    "stat_total_stamina": 1491, "stat_total_strength": 1498,
    "stat_total_critical_rating": 1559, "stat_total_dodge_rating": 1435,
    "stat_weapon_damage": 178, "honor": 1971,
    "league_points": 0, "league_group_id": -100002, "online_status": 2,
    "appearance_skin_color": 1, "appearance_hair_color": 1, "appearance_hair_type": 51,
    "appearance_head_type": 4, "appearance_eyes_type": 8, "appearance_eyebrows_type": 7,
    "appearance_nose_type": 2, "appearance_mouth_type": 5, "appearance_facial_hair_type": 1,
    "appearance_decoration_type": 0, "show_mask": true, "show_cape": true,
    "hideout_points": 9,
    "defender_units": 0,
    "wall_level": 1,
    "barracks_level": 1,
    "rewards":        "{\"hideout_points\":4,\"hideout_glue\":1108,\"hideout_stone\":2215}",
    "bonus_rewards":  "{\"hideout_glue\":4869,\"hideout_stone\":9737}"
  },
  "opponent_inventory": {
    "id": 21146, "mask_item_id": 14435572, "cape_item_id": 14464395,
    "suit_item_id": 14463259, "belt_item_id": 14464393, "boots_item_id": 14464394,
    "missiles_item_id": 13184243, "sidekick_id": 72523
  },
  "opponent_inventory_items": [
    { "id": 13184243, "identifier": "missiles_season_air" }, ...
  ],
  "opponent_sidekick": {
    "identifier": "sidekick_season_dark_ages", "sewed_identifier": "", "sewed_level": 0,
    "name": "", "quality": 3, "level": 9,
    "stat_stamina": 0, "stat_strength": 103, "stat_critical_rating": 123,
    "stat_dodge_rating": 107,
    "stage1_skill_id": 246, "stage2_skill_id": 12, "stage3_skill_id": 18, "id": 72523
  }
}
```

- `rewards` / `bonus_rewards` são **strings JSON** com `hideout_points`, `hideout_glue`,
  `hideout_stone` — preview do que o atacante ganha ao vencer (o `bonus_*` é a parte
  extra; ver análise do combate para a diferença exacta).
- O oponente vem com projeção completa de personagem (aparência + inventário +
  sidekick) para o cliente poder mostrar quem está a atacar.

## Outros dados do boot relevantes

- `missed_hideout_attacks`: 0 (contador de ataques sofridos enquanto offline).
- `guild_gem_production_level`: 0 (nível da sala guild_gem_production?).
- `daily_bonus_lookup.hideout_battle_won`: contador diário de batalhas ganhas.
- `current_goal_values` pode conter objetivos de hideout, ex.:
  `"hideout_hideout_points_reached": {"value": 1, "current_value": 1}`,
  `"hideout_units_produced": {"value": 1, "current_value": 1}`.
