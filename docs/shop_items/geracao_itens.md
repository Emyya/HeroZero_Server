# Geração de Itens — Fórmulas

---

## Fonte

`server-laravel/app/HeroZero/Character.php` → `generateShopItem()`

---

## Nível Requerido

```
reqLevel = nivel_jogador - rand(0, 4)   (mínimo 1)
```

---

## Raridade (quality)

### Loja normal
```
rand(1–100):
    1–70  → Comum  (quality=1)   70%
   71–95  → Raro   (quality=2)   25%
   96–100 → Épico  (quality=3)    5%
```

### Loja premium (donuts)
```
rand(1–100):
    1–40  → Raro   (quality=2)   40%
   41–100 → Épico  (quality=3)   60%
```

---

## Budget de Stats

A fórmula tem componente linear + quadrática para que os stats esclem mais agressivamente nos níveis altos.

```
qualityMult:
    Comum:  ×1.0
    Raro:   ×1.7
    Épico:                 ×2.3
    Épico + borda dourada: ×2.7 (regra local)
    Lendário:              ×3.0 (regra local)
    Lendário + borda ouro: ×3.5 (regra local)

A qualidade 4 identifica lendário. A borda dourada é independente da raridade;
quando presente usa o multiplicador combinado acima. Estas quatro combinações
são aplicadas pelo gerador partilhado quando a origem pede borda dourada.

budget = ceil( (reqLevel × 1.6 + 2 + reqLevel² × 0.004) × qualityMult )
```

**Valores de referência (épico / raro / comum):**

| Nível | Comum | Raro | Épico |
|------:|------:|-----:|------:|
|    50 |    92 |  157 |   212 |
|   130 |   278 |  472 |   639 |
|   200 |   482 |  820 |  1109 |
|   250 |   652 | 1109 |  1500 |
|   300 |   842 | 1432 |  1937 |

---

## Distribuição de Atributos

Os atributos disponíveis são: `stamina`, `strength`, `critical_rating`, `dodge_rating`.  
A ordem é embaralhada aleatoriamente antes de distribuir.

| Raridade | Nº de atributos | Distribuição |
|----------|:---------------:|--------------|
| **Comum** | 55% → 1 attr / 45% → 2 attrs | Concentrado — 1 attr leva 50–75% do budget |
| **Raro** | 40% → 2 attrs / 60% → 3 attrs | Equilibrado — split em partes razoáveis |
| **Épico** | 40% → 3 attrs / 60% → 4 attrs | Máximo — todos os pontos distribuídos |

*(ver especificação completa em [items_raridade.md](items_raridade.md))*

---

## Weapon Damage (só armas — type=6)

```
weapon_damage = ceil(reqLevel × 1.035) + 2
```

Itens de outros tipos têm `weapon_damage = 0`.

---

## Preço

### Loja premium (donuts)
```
buy_price  = 3 + floor(reqLevel / 5) + quality × 2   (donuts)
sell_price = round(4 × reqLevel^1.5)                  (moedas)
```

### Loja normal (moedas)
```
buy_price  = max(8, round(8 × reqLevel^1.5 × (1 + 0.3 × (quality - 1))))
sell_price = round(buy_price × 0.3)
```

---

## Ficheiros de Referência

| Ficheiro | Função |
|----------|--------|
| `app/HeroZero/Character.php` → `generateShopItem()` | Geração completa do item |
| [items_raridade.md](items_raridade.md) | Regras de raridade e contagem de atributos |

## Reutilização nos prémios de vilão

O cálculo de orçamento/distribuição é partilhado por `generateShopItem()` e
`createWorldbossRewardItem()` através de `Character::shopItemStats()`. Na
implementação atual, soma-se ao orçamento o bónus `item_flat` do esconderijo.
Os prémios de equipamento do vilão mantêm qualidade épica (×2.3), sem borda dourada, com nível
requerido entre o nível do herói menos 4 e o nível do herói, mínimo 1.
Não passam pelo sorteio de raridade ou de catálogo da loja.

Pets têm uma fórmula própria por nível/qualidade, descrita em
[scaling de pets](../sidekicks/scaling.md). As bases dos pets de vilão usam a
mesma variação de ±15% da compra na loja e ficam persistidas no preview. Ver
[documentação dos vilões](../vilao%20-%20worldboss/worldboss.md).


## Recompensas da Academia Heroica

Equipamento da Academia Heroica usa qualidade lendária (quality 4), multiplicador
×3,5, borda dourada e espaço gratuito para modificação. A fórmula partilhada
está em `Character::createTowerRewardItem()`. Mísseis recebem raridade lendária
e borda, mas não têm espaço de modificação. A implementação e o estado local
estão descritos em [Academia Heroica](../tower_events_implementacao.md).

### Âmbito da alteração de multiplicadores (2026-10-09)

O fator combinado substitui a proposta inicial de +20% para lendário dourado;
não se aplica esse acréscimo sobre ×3,5. `shopItemMultiplier()` recebe qualidade
e indicação de borda, e `shopItemStats()` calcula/distribui o orçamento.
`createWorldbossRewardItem()` aceita a indicação de borda para o cálculo;
`createEventRewardItem()` pede-a para os prémios épicos dourados (×2,7), e
`createTowerRewardItem()` pede lendário dourado (×3,5) para equipamento 1–7.
A borda e o espaço são campos independentes que os criadores do prémio atribuem.
A divisão de pontos limita cada parcela ao orçamento restante, evitando um
ponto excedente em itens de nível baixo. Estas alterações só afetam itens novos.

Mísseis, pets e modificações mantêm cálculos próprios. O casino usa outro caminho
de geração. O ×3,5 não multiplica dano de arma nem o número de atributos.
