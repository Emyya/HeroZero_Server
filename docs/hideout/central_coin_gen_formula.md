# Fórmula de produção de moedas da central (main_building)

## Fórmula completa

```
max_moedas = 14.3 × nível_personagem^1.39 × rpm × bónus_guilda × bónus_energético
```

### Componentes

| Componente | Descrição |
|---|---|
| `14.3` | Constante base (definida pelo servidor oficial) |
| `nível_personagem^1.39` | Expoente do nível do herói — a capacidade escala com o nível |
| `rpm` | `resource_production_max` da master data para o nível da central (1.0 no nível 1, +0.1 por nível) |
| `bónus_guilda` | `1 + (stat_quest_game_currency_reward_boost × 2) / 100` — 0% se sem guilda |
| `bónus_energético` | `1 + amount / 100` onde `amount` vem da master data (`boosters[id].amount`) |

### Energético de trabalho (booster_work)

| ID | Bónus | Multiplicador |
|---|---|---|
| Sem booster | 0% | × 1.00 |
| `booster_work1` | 10% | × 1.10 |
| `booster_work2` | 25% | × 1.25 |
| `booster_work3` | 50% | × 1.50 |

O booster só é aplicado enquanto `ts_active_work_boost_expires > GameTime::now()`.

### Gerador adjacente

O resultado da fórmula é multiplicado adicionalmente por `generatorCapacityFactor` se
houver um gerador colocado imediatamente à esquerda ou à direita da central na grelha.

```
max_final = floor(max_moedas × fator_gerador)
```

## Exemplos

| Nível herói | Nível central | Guilda (boost) | Energético | Resultado |
|---|---|---|---|---|
| 58 | 20 (rpm=2.9) | oxAlpha (boost=2 → 4%) | Nenhum | ~11.719 |
| 302 | 20 (rpm=2.9) | sonnet46 (boost=50 → 100%) | Nenhum | ~232.260 |
| 302 | 20 (rpm=2.9) | sonnet46 (boost=50 → 100%) | work3 (50%) | ~348.390 |

## Implementação

- `Hideout::mainBuildingGoldMax(int $characterId, float $rpm)` — calcula o valor base + guilda + energético
- `Hideout::effectiveMaxResourceAmount(array $room, ...)` — aplica o fator do gerador por cima
- `statePayload` — actualiza `max_resource_amount` na BD e no payload enviado ao cliente a cada `accountState`

## Fonte

Fórmula base confirmada no servidor flash 129 (2018):
`HideoutUtils::getMainBuildingGoldMax` em `server/class/Utils/HideoutUtils.php`.
Bónus do energético adicionado na reimplementação HTML5 (build 257) — 2026-08-27.
