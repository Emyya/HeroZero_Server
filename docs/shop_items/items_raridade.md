---
name: items-raridade-atributos
description: Regras de raridade de items — quantidade de atributos e distribuição de pontos por qualidade
metadata:
  type: reference
---

# Raridade de Items — Atributos e Distribuição

| Raridade | Quantidade de Atributos | Distribuição dos Pontos |
|----------|------------------------|------------------------|
| **Comum** | 1 a 2 atributos | Concentra todos os pontos num só atributo (ou divide em 2). |
| **Raro** | 2 a 3 atributos | Bom equilíbrio entre 2 ou 3 atributos. |
| **Épico / Set / Lendário** | 3 a 4 atributos | Máximo de pontos possíveis. Pode ser lavado para focar tudo num só atributo. |

## Multiplicadores locais e borda dourada

A qualidade lendária é `quality=4`; épica é `quality=3`. A borda é independente
da qualidade e não acrescenta atributos. `Character::shopItemMultiplier()` usa
×2,3 para épico, ×2,7 para épico dourado, ×3,0 para lendário e ×3,5 para lendário
dourado. São fatores totais sobre a base, não percentagens adicionais acumuladas.
Para épicos/lendários, a geração partilhada escolhe três atributos em 40% dos
casos e quatro em 60%. A soma respeita o orçamento, incluindo níveis baixos.

Estas regras aplicam-se aos novos itens dos métodos de geração partilhados;
não recalculam inventários existentes nem substituem automaticamente fórmulas
próprias como a do casino. Ver [fórmula e âmbito](geracao_itens.md) e
[equipamento da academia](../tower_events_implementacao.md#9-equipamento-lendário-dourado-e-fórmula).
