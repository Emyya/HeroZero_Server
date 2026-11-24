# Lojas — Como Funcionam

Existem duas lojas no jogo, cada uma com 9 slots. Os itens são gerados dinamicamente ao refrescar e guardados na tabela `inventory`.

---

## Slots na BD

| Loja | Colunas em `inventory` |
|------|------------------------|
| Loja 1 (donuts) | `shop_item1_id` … `shop_item9_id` |
| Loja 2 (desportiva) | `shop2_item1_id` … `shop2_item9_id` |

Os itens existem como registos reais na tabela `items` (com `character_id`). Ao refrescar, os antigos são apagados e novos inseridos.

---

## Custo de Refresh

- **Loja 1:** 1 donut (`usePremium = true` → debita 1 de `user.premium_currency`)
- **Loja 2:** sem custo (ou moeda de jogo, dependendo da chamada)

O método responsável é `Character::refreshShopItems(int $shopIndex, bool $usePremium)` em `app/HeroZero/Character.php`.

O executor dinâmico dos bots usa `shop_index=1`, `use_premium=true` apenas
quando não existe melhoria elegível na montra atual e o orçamento diário de
donuts permite. Compara o ganho das ofertas sucessivas e pára perante
resultados estagnados. A resposta de `refreshShopItems` contém só estado real
atualizado (`user`, `character`, `inventory`, `items`), sem coleções de treino
vindas da fixture de login. As formas dos quatro objetos vêm do template de
`autoLoginUser`; os valores vêm da BD. Em particular, os nove IDs de
`inventory.shop_item*_id` e os respetivos registos em `items` devem chegar na
própria resposta de renovação, para o cliente redesenhar a montra imediatamente.

---

## Loja 1 — Donuts (shop_index=1)

### Catálogo
Usa **todos os identifiers disponíveis** (~3700 itens do CDN oficial), carregados de `data/shop_item_identifiers.php` via `shopIdentifierCatalog()`.

### Distribuição de Qualidade
```
roll = random_int(1, 100)
≤ 40  → Comum  (quality=1)   40%
≤ 70  → Raro   (quality=2)   30%
> 70  → Épico  (quality=3)   30%
```

### Preço
- 50% de chance de ser item premium (pago em donuts):
  `buy = 3 + floor(reqLevel / 5) + quality * 2`
- 50% pago em moeda de jogo:
  `buy = max(8, round(8 × reqLevel^1.5 × (1 + 0.3 × (quality−1))))`

### Martelo de Redistribuição (special_reskill)
A cada refresh da loja 1, existe **5% de chance** de aparecer o martelo num slot aleatório:

```php
if ($shopIndex === 1 && random_int(1, 100) <= 5) {
    $hammerSlot = $slots[array_rand($slots)];
}
```

Quando aparece, o slot escolhido recebe o item `special_reskill` em vez de um item de equipamento normal:

| Campo | Valor |
|-------|-------|
| `identifier` | `special_reskill` |
| `type` | 11 (consumível) |
| `quality` | 3 (épico) |
| `buy_price` | 79 donuts |
| `charges` | 1 |
| `premium_item` | true |

Ao comprar, vai para a mochila (não para um slot de equipamento). Ao usar, redistribui os `stat_base_*` do personagem.
Os contadores `stat_bought_*` mantêm-se em cada atributo: representam o histórico
de compras que determina o preço das próximas melhorias, independentemente da
nova distribuição. A mesma regra aplica-se a `reskillCharacterStats`, usando
um martelo ou pagando 25 donuts (BUG-000137).


---

## Loja 2 — Desportiva (shop_index=2)

### Catálogo
Usa apenas identifiers com palavras-chave desportivas/mundiais, filtrados via `shopSportsCatalog()`:

```php
$keywords = ['worldcup', 'olympia', 'olympic', 'football', 'soccer', 'xp_tournament', 'winterolympics'];
```

O catálogo base é o mesmo (`shop_item_identifiers.php`), mas filtrado — só ficam identifiers cujo nome contenha uma das palavras-chave acima.

### Distribuição de Qualidade
```
roll = random_int(1, 100)
≤ 70  → Comum  (quality=1)   70%
≤ 95  → Raro   (quality=2)   25%
> 95  → Épico  (quality=3)    5%
```

### Preço
- 12% de chance de ser premium (donuts): `buy = 3 + floor(reqLevel / 5) + quality * 2`
- 88% pago em moeda de jogo: fórmula igual à loja 1

---

## Geração de Stats dos Itens

O método `generateShopItem(bool $premiumShop)` escala os stats ao nível do personagem:

```
reqLevel = max(1, level − random_int(0, 4))
budget   = ceil((reqLevel × 1.6 + 2 + reqLevel² × 0.004) × qualityMult) + kyberBonus
```

| Qualidade | `qualityMult` | Atributos |
|-----------|---------------|-----------|
| Comum     | 1.0           | 1 (55%) ou 2 (45%) |
| Raro      | 1.7           | 2 (40%) ou 3 (60%) |
| Épico     | 2.3           | 3 (40%) ou 4 (60%) |

O bónus `kyberBonus` vem de `Hideout::kyberneticBonuses()['item_flat']`.

---

## Código Relevante

| Ficheiro | O que faz |
|----------|-----------|
| `app/HeroZero/Character.php` | `refreshShopItems()`, `generateShopItem()`, `shopSportsCatalog()`, `buyShopItem()` |
| `actions/buyShopItem.php` | Recebe `item_id` + `target_slot` + `shop_index` e chama `$char->buyShopItem()` |
| `data/shop_item_identifiers.php` | Catálogo completo de identifiers por tipo (1–7) |
| `docker/initdb/01_schema.sql` | Colunas `shop_item*_id` e `shop2_item*_id` na tabela `inventory` |

## Destino da compra (`buyShopItem`)

O `target_slot` usa o mapa do cliente: 1–8 equipamento e 9–26 mochila
(`bag_item1_id`–`bag_item18_id`). Arrastar para a mochila coloca o item na
posição escolhida, sem equipar pelo tipo. Equipar exige slot compatível e nível
requerido. Se o destino estiver ocupado, o item anterior vai para outra vaga
livre da mochila; se não houver vaga, a compra falha sem débito.

Transferência, débito e tutorial partilham transação e locks do herói, conta,
inventário e item. Uma transação exterior usa savepoint; repetir a compra de
um item já retirado da loja é rejeitado. A moeda debitada respeita `premium_item`.
Consumíveis também respeitam destinos da mochila; sem destino (`0`), usam a
primeira vaga livre. Sidekicks continuam a ser entregues à coleção.

Reprodução: `bug_brecho.har`, destinos 9 e 11 equipavam indevidamente; destino
6 era uma compra para equipamento. Corrigido no backend (BUG-000131).

```bash
php scripts/testing/test_shop_purchase.php
```

Validação com PDO simulado: mochila, equipamento, destino ocupado, capacidade,
saldo, compatibilidade, nível, repetição, rollback e savepoint. Integração MySQL
e confirmação visual no cliente pendentes. Não altera saldos reais.

## Pets do brechó — restrição a época normal (2026-10-05)

Por decisão do utilizador, a geração atual só admite pets com a fonte
`shop_general` no catálogo corrigido de `Sidekick::official()`.
`Character::normalShopSidekickPool()` agrupa-os pela qualidade do template: são
28 candidatos no asset atual, 27 épicos e um comum (`sidekick_dog3`).
Não utiliza a antiga lista manual que misturava pets básicos, eventos e covis.

Ficam fora os pets de conquistas (incluindo Kylie), desbloqueio (como
`sidekick_dog1`), passes, eventos festivos/desportivos, ofertas, indisponíveis
e covis lendários. O critério é a fonte efetiva, não uma lista de exclusões
baseada apenas no nome. Na loja 1 mantêm-se as faixas de 2% de épicos e 1%
de comuns por tentativa de geração. A faixa antiga de 0,5% de lendários não
tem candidatos elegíveis e passa para geração de equipamento, sem aumentar
as chances das outras qualidades.

A loja 2 intersecta o seu catálogo desportivo com o pool normal; atualmente
a interseção está vazia, pelo que não gera pets enquanto vigorar esta
restrição. Os preços e fórmulas de atributos dos pets elegíveis mantêm-se.

Aplica-se aos próximos refreshes. Não remove pets existentes da coleção
nem apaga retroativamente ofertas já geradas nos slots. Não ativa eventos
ou altera a distribuição dos prémios de vilão.

```bash
php scripts/testing/test_shop_sidekick_pool.php --offline
php scripts/testing/test_shop_sidekick_pool.php --hero="skel"
```

O teste offline verifica candidatos e exclusões. Em MySQL com rollback,
exercita os 1000 resultados possíveis do sorteio e refreshes reais das duas
lojas, verificando os objetos persistidos nos slots. Não deixa refreshes ou
consumos persistidos nem constitui confirmação visual no navegador.

## Atributos persistidos nas ofertas de pet (2026-10-05)

Os quatro atributos do pet são sorteados na geração da oferta, com a variação
de ±15% do [scaling de pets](../sidekicks/scaling.md). São guardados nos campos
`items.stat_*`, usados no preview e copiados para `sidekicks.stat_base_*` na
compra. O pet nasce ao nível 1 com os valores mostrados, sem novo sorteio.
Subir o nível do herói depois de gerar a oferta não muda esses atributos.

Ofertas antigas com atributos preenchidos conservam os valores anunciados.
Só o caso antigo de quatro atributos a zero usa o sorteio na compra como
compatibilidade. As novas ofertas passam a variar a partir do próximo refresh.
A correção não altera as proporções de referência, chances, preços, habilidades
nem a restrição a pets de época normal.

```bash
php scripts/testing/test_shop_pet_stats.php --hero="skel"
```

Teste MySQL com rollback de geração/compra comum e épica, subida de nível,
preservação do preview, compra duplicada e oferta antiga sem atributos. Os
preços das fixtures são zero para isolar os atributos; débitos e destinos
continuam cobertos pelo teste da compra com PDO simulado.
