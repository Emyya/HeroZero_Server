# Bónus de conjuntos de itens (set bonus) — funcionamento e implementação

Implementado a 2026-08-25 · Registado como [BUG-000074](../bugs_encontrados.md)
· Lista completa dos conjuntos: [sets.md](sets.md)

---

## 1. O que é a mecânica

No Hero Zero, itens que partilham o mesmo `item_set_identifier` formam um
**conjunto** (ex.: `mask_sci_fi_week_1`, `suit_sci_fi_week_1`, … → conjunto
`sci_fi_week_1`). Ao EQUIPAR várias peças do mesmo conjunto ativam-se bónus por
limiar: tipicamente **2 peças → +7% a um atributo**, **3 peças → +7% a outro**,
e assim sucessivamente até esgotar as peças do conjunto (a maioria tem 5; há
conjuntos de armas com só 2).

Regras exatas:

1. **Contagem**: só contam peças EQUIPADAS (slots de equipamento), nunca itens
   no saco/banco.
2. **Limiares acumulativos**: com 5 peças estão ativos os limiares 2, 3, 4 E 5.
   Cada limiar acrescenta o SEU bónus (por isso um herói com 5 peças tem quatro
   linhas de bónus).
3. **Semântica do %**: aplica-se à **HABILIDADE BASE do herói**, não aos stats
   das peças nem ao total. Fórmula oficial do cliente:
   `bónus = ceil(stat_base_X × valor)` — ex.: base Intuição 27 × 0.07 = 1.89 →
   **+2** Intuição.

> Nota histórica: esta semântica foi validada contra três fontes independentes
> quando surgiu dúvida: (a) o código do bundle html5_257
> (`ItemSet.getStatChanges` multiplica `get_statBaseStamina()` = campo cru
> `stat_base_*` da resposta), (b) o tooltip de atributos do próprio jogo, que
> mostra "+X do conjunto" como linha ADITIVA separada (fora de "+ itens"), e
> (c) o guia oficial do fórum BR ("bónus às habilidades base").

## 2. Tipos de atributo e valores

Cada bónus de cada limiar tem um `type` e um `value`:

| type | Atributo | Coluna BD |
|---|---|---|
| 1 | Vigor | `stat_base_stamina` |
| 2 | Força/Dano | `stat_base_strength` |
| 3 | Cérebro (crítico) | `stat_base_critical_rating` |
| 4 | Intuição (esquiva) | `stat_base_dodge_rating` |
| 5 | Todos os atributos (as 4 anteriores) | — |
| 6 | Sorte no Cassino (casino luck) | sem efeito em combate |

Valores observados nos 290 conjuntos oficiais: **+1%, +2% ou +7%**. Tipo 6
existe nos dados mas NÃO está implementado no servidor (não há mecânica de
sorte no cassino para aplicar).

## 3. De onde vêm os dados

O cliente html5 carrega as constants do jogo de
`public/assets/data/constants_json.data` — espelho local do ficheiro oficial do
CDN (o index.html reescreve os pedidos a `hz-static-2.akamaized.net/assets/
(i18n|data)/…` para a nossa origem). Esse ficheiro traz as duas chaves de que a
mecânica precisa:

- `item_set_templates` — 290 conjuntos, cada um com:
  ```json
  "baywatch_set": {
      "identifier_short": "bws",
      "item_count": 5,
      "bonus": { "2": {"type": 1, "value": 0.07},
                 "3": {"type": 3, "value": 0.07},
                 "4": {"type": 4, "value": 0.07},
                 "5": {"type": 2, "value": 0.07} }
  }
  ```
  (chave do objeto `bonus` = nº de peças do limiar)
- `item_templates[].item_set_identifier` — 1334 itens pertencem a algum
  conjunto (`identifier` → `set_id`). Todos os identificadores do catálogo da
  loja do servidor têm template oficial, portanto o mapa é completo para tudo
  o que o servidor distribui. Variantes `_ns` ("non-set") são o mesmo visual
  SEM pertencerem ao conjunto.

Como o servidor (PHP) também precisa destes dados e não os tinha, extraíram-se
para **`server-laravel/data/item_sets.php`** (2026-08-25):

```php
return [
  'sets'  => ['<set_id>' => ['item_count' => 5, 'bonus' => ['2' => ['type'=>1,'value'=>0.07], …]], …],
  'items' => ['<item_identifier>' => '<set_id>', …],
];
```

É um ficheiro gerado (JSON embutido); se um dia se atualizar o
`constants_json.data`, regenerá-lo. **Não é preciso servir `item_set_templates`
ao cliente** — ele já carrega o ficheiro local.

## 4. Implementação no servidor (`app/HeroZero/Character.php`)

### 4.1 Contagem de peças — `equipBonus()`

`equipBonus()` já somava os stats dos itens equipados (ids nas slots
`mask_item_id`, `cape_item_id`, … do `inventory`). Passou a trazer o
`identifier` na mesma query e a contar peças por conjunto usando o catálogo:

```php
$setId = $catalogItems[(string)$r['identifier']] ?? null;
if ($setId !== null) $bonus['sets'][$setId] = ($bonus['sets'][$setId] ?? 0) + 1;
```

Resultado em cache (`$equipBonusCache`) — uma única leitura por request serve
combate, totais e resposta. É a **fonte única**: o que conta para lutar é o
mesmo mapa que vai ao cliente, logo nunca divergem.

### 4.2 Cálculo do bónus — `itemSetBonus(string $stat): int`

Para um stat (`stamina`, `strength`, `critical_rating`, `dodge_rating`):

1. Converte o stat no tipo oficial (`SET_STAT_TYPE`: stamina=1, strength=2,
   critical_rating=3, dodge_rating=4).
2. Lê a coluna `stat_base_X` do personagem (só a BASE — ver §4.5).
3. Para cada conjunto com peças equipadas, soma todos os limiares
   `peças <= count` cujo `type` seja igual ao do stat OU `5` (todos):
   `total += ceil(stat_base_X × value)`.

Exemplo real (verificação de 2026-08-25): char com `base_dodge ≈ 27` e 5 peças
de `sci_fi_week_1` (limiares 2→cérebro, 3→intuição, 4→vigor, 5→força, +7%):

- Intuição: `ceil(27 × 0.07) = ceil(1.89) = +2`
- Vigor/Força/Cérebro: `ceil(base × 0.07) = +1` cada
- Com só 2 peças: apenas o limiar 2 está ativo → só Cérebro +1.

### 4.3 Combate — `battleProfile()`

As 4 stats do perfil de batalha passaram a incluir o termo:

```
stat = base + treino + equipamento + boost(booster+guilda+liga) + CONJUNTO
```

Tal como o equipamento, o bónus de conjunto é **aditivo e nunca multiplicado**
pelo multiplicador de booster/guilda/liga — igual às linhas separadas do
cliente. Como duelo, liga, guilda vs guilda, gangues e missões calculam tudo a
partir do `battleProfile()` (ou do `equipBonus()`), a correção vale para todas
as lutas automaticamente.

### 4.4 Totais mostrados — `overlayCharacter()`

Os campos `stat_total_*` enviados ao cliente também somam
`itemSetBonus($s)`. O painel de atributos do cliente compara o total com a
própria decomposição (`base + itens + conjunto + time + acompanhante + liga +
esconderijo`); sem isto o total ficava atrás da soma das linhas.

### 4.5 Porquê `stat_base_X` e não `base + treino`

O cliente calcula a linha do conjunto sobre o CAMPO `stat_base_*` que recebe na
resposta (neste servidor o treino vive separado em `stat_trained_*`; no oficial,
o treino era escrito dentro de `stat_base_*`). Usar `base+treino` aqui fazia as
lutas baterem certo com um número DIFERENTE do que o painel mostra. Decisão:
espelhar o cliente exatamente (efeito prático: treinar não aumenta o bónus % —
mas é isso que o jogador vê). Se um dia o treino passar a ser escrito em
`stat_base_*` (como no oficial), este método fica correto sem alterações.

### 4.6 Resposta ao cliente — `inventoryData()`

`inventoryData()` preenche agora o campo `inventory.item_set_data`:

```php
$sets = $this->equipBonus()['sets'] ?? [];   // {set_id: n_peças}
$out['item_set_data'] = $sets === [] ? '{}' : json_encode($sets);
// ex.: '{"sci_fi_week_1":5}'
```

Detalhes importantes:

- O formato é **JSON de objeto** porque `zf.encode/decode` do cliente são
  `JSON.stringify/parse`. Com ZERO conjuntos tem de ser `'{}'` e nunca `'[]'`
  (`json_encode([])` daria array e rebentaria o parse do lado do cliente).
- Calculado AO VIVO (não persistido); a coluna continua vazia na BD, tal como
  estava. Todas as respostas passam pelo chokepoint
  `Live::accountState()` → `inventoryData()` (incluindo equipar via
  `inventoryOps`), logo o campo chega atualizado logo a seguir a equipar/tirar.
- Inventários de OPONENTES (liga/duelos) usam a mesma função, portanto os
  totais deles também incluem os conjuntos deles.

## 5. O lado cliente (para referência)

- `getEquippedItemSetCount(setId)` lê `inventory.item_set_data` e devolve o nº
  de peças — alimenta os tooltips "X/Y peças" e os bónus mostrados.
- `Y.itemSetStatValue(tipo)` percorre os conjuntos equipados, pede
  `get_characterBonusData()` (= soma dos limiares ativados) e converte com
  `getStatChanges` → é a linha "+X do conjunto" do painel de atributos.
- O painel RECALCULA o total sozinho; o servidor envia `stat_total_*`
  coerente (§4.4) para as vistas que leem o campo direto.

## 6. Limitações conhecidas

- **Tipo 6 (Sorte no Cassino)** existe nos dados mas não tem efeito (não há
  sorte de cassino server-side para afetar).
- **Trajes/outfits** (`selectedOutfit`): o cliente usa contagens do traje
  ativo em pré-visualização; o servidor não implementa troca de traje, por isso
  o bónus reflete sempre o equipamento real.
- **Costura (sewing machine)** não está implementada no servidor; não existem
  identifiers `-set-` gerados, logo não precisaram de tratamento no mapa.
- Itens de conjunto que cheguem por vias ainda não implementadas (eventos,
  torres…) funcionam desde que o identifier conste do catálogo `data/
  item_sets.php`.

## 7. Verificação

- Tinker com transação+rollback (char 24764): 5 peças `sci_fi_week_1` → bónus
  vigor=1 força=1 cérebro=1 intuição=2; tirar 2 peças → só cérebro;
  `item_set_data = {"sci_fi_week_1":5}`; rollback limpo. Detalhes em
  [BUG-000074](../bugs_encontrados.md).
- Teste em jogo pelo utilizador: confirmado a funcionar (2026-08-25).
