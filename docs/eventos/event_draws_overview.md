# Pirâmide da Sorte — event_draw_pools

Catálogo dos **55 temas** da Pirâmide da Sorte nos assets locais do cliente v257. Contém **825 entradas de prémios**, quinze por tema. A implementação e scripts estão em [event_draws_implementacao.md](event_draws_implementacao.md).

## Fontes e interpretação

- `assets/data/constants_json.data` e cópia pública equivalente: JSON comprimido com zlib, tabela `event_draw_pools`.
- `server-laravel/public/assets/i18n/pt_BR/text.data`: nomes e textos da interface.
- `server-laravel/public/js/HeroZero.min.js`: `DODrawEvent` (`QA`), `DrawEvent` (`mm`), constantes `CEventDrawPool` (`po`), interface da pirâmide e diálogo de recolha.
- O nome localizado é Pirâmide da Sorte; não pertence à Caça às Toupeiras (`event_treasures`).
- Custos, referências e pesos vêm das constantes. A normalização dos pesos e a escolha do custo por número de sorteios vêm de `DrawEvent.refresh`.
- Os fatores dos itens não descrevem uma fórmula oficial de atributos. Geração de equipamento/modificações usa balanceamento local identificado na implementação.
- Não foi fornecido HAR oficial desta mecânica. Os pedidos e campos foram verificados no JavaScript local; testes Node não equivalem a renderização num navegador.

## Mecânica confirmada

O primeiro sorteio é gratuito. Os seguintes custam donuts conforme o número de prémios já sorteados, independentemente da posição do prémio escolhido. Cada entrada só pode ser sorteada uma vez por ocorrência e por herói. O conjunto é reduzido a cada sorteio; o peso de cada prémio restante é dividido pela soma dos pesos restantes.

A interface apresenta o conteúdo do catálogo, mas só há plano de recompensa e preview individual depois de o servidor sortear aquele prémio. Sorteio e recolha são ações distintas. Um prémio pendente pode ser recolhido após o fim do evento. Não há reset da pirâmide no conjunto de ações analisado.

## Custos por sorteio

| Sorteio | Donuts |
| --- | --- |
| 1 | 0 |
| 2 | 3 |
| 3 | 6 |
| 4 | 9 |
| 5 | 12 |
| 6 | 25 |
| 7 | 35 |
| 8 | 39 |
| 9 | 45 |
| 10 | 49 |
| 11 | 59 |
| 12 | 69 |
| 13 | 79 |
| 14 | 89 |
| 15 | 99 |

Completar os quinze sorteios custa **618 donuts** no catálogo atual, com o primeiro gratuito. Estes são preços de sorteios, não preços vinculados ao prémio da mesma linha.

## Tipos de prémio

| reward_type | Conteúdo | Entradas no catálogo |
| --- | --- | --- |
| 3 | Pontos livres de atributos; fator direto | 52 |
| 5 | Item: equipamento, munição ou pet, conforme referência | 520 |
| 12 | Modificação com slot e raridade explícitos | 253 |

O enum foi confirmado em `UiDrawEventReward.refresh` (`lr`). Essa classe também reconhece outros tipos, mas os 55 pools atuais só usam 3, 5 e 12. Não se aplica automaticamente o enum do casino ou dos bónus de células de tesouro.

## Índice dos temas

| # | Identificador | Nome localizado | Prémios |
| --- | --- | --- | --- |
| 1 | `beetle_draw_event` | Pirâmide da Sorte: Besouro | 15 |
| 2 | `bear_draw_event` | Pirâmide da Sorte: Urso | 15 |
| 3 | `fox_draw_event` | Pirâmide da Sorte: Raposa | 15 |
| 4 | `boar_draw_event` | Pirâmide da Sorte: Javali | 15 |
| 5 | `science_draw_event` | Pirâmide da Sorte: Ciência | 15 |
| 6 | `lightning_draw_event` | Pirâmide da Sorte: Relâmpago | 15 |
| 7 | `ghost_draw_event` | Pirâmide da Sorte: Hora das Bruxas | 15 |
| 8 | `revenge_draw_event` | Pirâmide da Sorte: Vingança | 15 |
| 9 | `happiness_draw_event` | Pirâmide da Sorte: Felicidade | 15 |
| 10 | `buffalo_draw_event` | Pirâmide da Sorte: Búfalo | 15 |
| 11 | `hawk_draw_event` | Pirâmide da Sorte: Falcão | 15 |
| 12 | `ant_draw_event` | Pirâmide da Sorte: Formiga | 15 |
| 13 | `fire_draw_event` | Pirâmide da Sorte: Fogo | 15 |
| 14 | `water_draw_event` | Pirâmide da Sorte: Água | 15 |
| 15 | `air_draw_event` | Pirâmide da Sorte: Ar | 15 |
| 16 | `earth_draw_event` | Pirâmide da Sorte: Terra | 15 |
| 17 | `creativity_draw_event` | Pirâmide da Sorte: Criatividade | 15 |
| 18 | `old_gods_draw_event` | Pirâmide da Sorte: Deuses Antigos | 15 |
| 19 | `pumpkins_draw_event` | Pirâmide da Sorte: Monstro | 15 |
| 20 | `arcade_draw_event` | Pirâmide da Sorte: Maravilhas Retrô | 15 |
| 21 | `video_games_draw_event` | Pirâmide da Sorte: Videogames | 15 |
| 22 | `internet_draw_event` | Pirâmide da Sorte: Internet | 15 |
| 23 | `dinosaurs_draw_event` | Pirâmide da Sorte: Dinossauros | 15 |
| 24 | `volcano_draw_event` | Pirâmide da Sorte: Vulcão | 15 |
| 25 | `mammoth_draw_event` | Pirâmide da Sorte: Mamute | 15 |
| 26 | `aliens_draw_event` | Pirâmide da Sorte: Alienígenas | 15 |
| 27 | `brawling_spirit_draw_event` | Pirâmide da Sorte: Espetáculo de Luta Livre | 15 |
| 28 | `distant_worlds_draw_event` | Pirâmide da Sorte: Mundos Infinitos | 15 |
| 29 | `country_life_draw_event` | Pirâmide da Sorte: Vida no Campo | 15 |
| 30 | `future_draw_event` | Pirâmide da Sorte: Futuro | 15 |
| 31 | `apocalypse_draw_event` | Pirâmide da Sorte: Apocalipse | 15 |
| 32 | `post_apocalypse_draw_event` | Pirâmide da Sorte: Pós-Apocalipse | 15 |
| 33 | `magical_circus_draw_event` | Pirâmide da Sorte: Circo Mágico | 15 |
| 34 | `pool_party_draw_event` | Pirâmide da Sorte: Festa na Piscina | 15 |
| 35 | `zombies_draw_event` | Pirâmide da Sorte: Zumbis | 15 |
| 36 | `rome_draw_event` | Pirâmide da Sorte: Roma Antiga | 15 |
| 37 | `greek_draw_event` | Pirâmide da Sorte: Deuses Gregos | 15 |
| 38 | `steampunk_draw_event` | Pirâmide da Sorte: Steampunk | 15 |
| 39 | `corruption_draw_event` | Pirâmide da Sorte: Corrupção | 15 |
| 40 | `ice_kingdom_draw_event` | Pirâmide da Sorte: Reino de Gelo | 15 |
| 41 | `bounties_of_seven_seas_draw_event` | Pirâmide da Sorte: Sete Mares | 15 |
| 42 | `spooky_xmas_draw_event` | Pirâmide da Sorte: [m:Bárbaro Festivo|f:Bárbara Festiva] | 15 |
| 43 | `wild_west_draw_event` | Pirâmide da Sorte: Faroeste | 15 |
| 44 | `bounty_hunter_draw_event` | Pirâmide da Sorte: [m:Caçador|f:Caçadora] de Recompensas | 15 |
| 45 | `egypt_draw_event` | Pirâmide da Sorte: Egito Antigo | 15 |
| 46 | `arctic_exploration_draw_event` | Pirâmide da Sorte: Ártico | 15 |
| 47 | `wild_savannah_draw_event` | Pirâmide da Sorte: Savana | 15 |
| 48 | `heroic_feast_main_course_draw_event` | Pirâmide da Sorte: Prato Principal | 15 |
| 49 | `wasteland_draw_event` | Pirâmide da Sorte: Ermos | 15 |
| 50 | `heroic_feast_dessert_draw_event` | Pirâmide da Sorte: Sobremesa | 15 |
| 51 | `shadow_draw_event` | Pirâmide da Sorte: Mundo das Sombras | 15 |
| 52 | `holy_light_draw_event` | Pirâmide da Sorte: Cruzadas da Luz | 15 |
| 53 | `elk_draw_event` | Pirâmide da Sorte: Alce | 15 |
| 54 | `elk_2_draw_event` | Pirâmide da Sorte: Alce II | 15 |
| 55 | `shang_dynasty_draw_event` | Pirâmide da Sorte: Dinastias do Passado | 15 |

## Fichas e definições completas

## Pirâmide da Sorte: Besouro

Identificador: `beetle_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_beetle` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_sidekick_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_beetle` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_cape_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_beetle` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_gadget_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_beetle` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_beetle` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_beetle` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_beetle` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_beetle` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_beetle` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_beetle",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_beetle",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_beetle",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_beetle",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_beetle",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_beetle",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_beetle",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_beetle",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_beetle",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Urso

Identificador: `bear_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_bear` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_bear` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_bear` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_bear` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_sidekick_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_bear` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_bear` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_bear` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_bear` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_bear` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_bear",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_bear",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_bear",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_bear",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_bear",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_bear",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_bear",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_bear",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_bear",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Raposa

Identificador: `fox_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_fox` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_gadget_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_fox` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_fox` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_fox` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_sidekick_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_fox` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_fox` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_fox` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_fox` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_fox` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_fox",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_fox",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_fox",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_fox",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_fox",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_fox",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_fox",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_fox",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_fox",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Javali

Identificador: `boar_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_boar` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_boar` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_boar` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_boar` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_boar` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_boar` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_boots_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_boar` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_boar` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_boar` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_boar",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_boar",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_boar",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_boar",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_boar",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_boar",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_boar",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_boar",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_boar",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Ciência

Identificador: `science_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_science` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_science` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_science` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_mask_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_science` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_science` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_science` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_science` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_science` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_science` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_science",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_science",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_science",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_science",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_science",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_science",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_science",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_science",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_science",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Relâmpago

Identificador: `lightning_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_lightning` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_lightning` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_lightning` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_lightning` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_weapon_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_lightning` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_lightning` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_lightning` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_lightning` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_lightning` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_lightning",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_lightning",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_lightning",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_lightning",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_lightning",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_lightning",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_lightning",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_lightning",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_lightning",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Hora das Bruxas

Identificador: `ghost_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_ghost` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_ghost` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_weapon_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_ghost` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_ghost` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_ghost` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_ghost` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_belt_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_ghost` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_ghost` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_ghost` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_ghost",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_ghost",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_ghost",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_ghost",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_ghost",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_ghost",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_ghost",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_ghost",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_ghost",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Vingança

Identificador: `revenge_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_revenge` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_revenge` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_revenge` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_revenge` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_belt_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_revenge` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_revenge` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_revenge` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_revenge` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_revenge` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_revenge",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_revenge",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_revenge",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_revenge",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_revenge",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_revenge",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_revenge",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_revenge",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_revenge",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Felicidade

Identificador: `happiness_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_happiness` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_happiness` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_suit_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_happiness` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_happiness` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_mask_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_happiness` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_happiness` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_happiness` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_happiness` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_happiness` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_happiness",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_happiness",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_happiness",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_happiness",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_happiness",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_happiness",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_happiness",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_happiness",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_happiness",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Búfalo

Identificador: `buffalo_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_buffalo` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_gadget_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_buffalo` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_buffalo` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_buffalo` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_buffalo` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_buffalo` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_mask_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_buffalo` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_buffalo` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_buffalo` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_buffalo",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_buffalo",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_buffalo",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_buffalo",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_buffalo",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_buffalo",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_buffalo",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_buffalo",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_buffalo",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Falcão

Identificador: `hawk_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_hawk` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_hawk` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_hawk` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_hawk` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_mask_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_hawk` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_hawk` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_weapon_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_hawk` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_hawk` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_hawk` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_hawk",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_hawk",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_hawk",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_hawk",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_hawk",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_hawk",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_hawk",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_hawk",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_hawk",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Formiga

Identificador: `ant_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_ant` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_boots_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_ant` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_ant` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_ant` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_ant` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_ant` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_ant` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_ant` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_ant` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_ant",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_ant",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_ant",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_ant",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_ant",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_ant",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_ant",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_ant",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_ant",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Fogo

Identificador: `fire_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_fire` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_gadget_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_fire` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_fire` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_fire` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_belt_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_fire` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_fire` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_fire` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_fire` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_fire` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_fire",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_fire",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_fire",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_fire",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_fire",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_fire",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_fire",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_fire",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_fire",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Água

Identificador: `water_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_water` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_water` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_water` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_water` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_water` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_water` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_boots_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_water` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_water` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_water` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_water",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_water",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_water",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_water",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_water",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_water",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_water",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_water",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_water",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Ar

Identificador: `air_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_air` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_air` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_air` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_air` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_air` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_air` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_air` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_air` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_air` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_air",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_air",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_air",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_air",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_air",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_air",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_air",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_air",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_air",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Terra

Identificador: `earth_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_earth` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_earth` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_earth` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_earth` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_earth` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_earth` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_earth` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_earth` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_earth` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_earth",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_earth",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_earth",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_earth",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_earth",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_earth",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_earth",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_earth",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_earth",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Criatividade

Identificador: `creativity_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_creativity` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_creativity` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_creativity` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_seas_creativi` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_creativity` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_creativity` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_creativity` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_creativity` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_creativity` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_creativity",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_creativity",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_creativity",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_seas_creativi",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_creativity",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_creativity",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_creativity",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_creativity",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_creativity",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Deuses Antigos

Identificador: `old_gods_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_old_gods` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_old_gods` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_old_gods` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_seas_old_gods` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_old_gods` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_old_gods` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_old_gods` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_old_gods` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_old_gods` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_old_gods",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_old_gods",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_old_gods",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_seas_old_gods",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_old_gods",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_old_gods",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_old_gods",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_old_gods",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_old_gods",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Monstro

Identificador: `pumpkins_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_pumpkins` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_pumpkins` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_pumpkins_1` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_pumpkins` | 140 | 4.2683% | 35 |
| 7 | 5 | 20 | `suit_season_pumpkins_2` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_pumpkins` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_pumpkins` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_belt_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_pumpkins` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_pumpkins` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_pumpkins` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_pumpkins",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_pumpkins",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_pumpkins_1",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_pumpkins",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_pumpkins_2",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_pumpkins",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_pumpkins",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_pumpkins",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_pumpkins",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_pumpkins",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Maravilhas Retrô

Identificador: `arcade_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_arcade` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_arcade` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_arcade` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_gadget_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_arcade` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_weapon_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_arcade` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_arcade` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_arcade` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_arcade` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_arcade` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_arcade",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_arcade",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_arcade",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_arcade",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_arcade",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_arcade",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_arcade",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_arcade",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_arcade",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Videogames

Identificador: `video_games_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_video_games` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_video_games` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_cape_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_video_games` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_video_games` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_video_games` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_video_games` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_belt_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_video_games` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_video_games` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_video_games` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_video_games",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_video_games",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_video_games",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_video_games",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_video_games",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_video_games",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_video_games",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_video_games",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_video_games",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Internet

Identificador: `internet_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_internet` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_boots_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_internet` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_internet` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_internet` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_internet` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_internet` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_internet` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_internet` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_internet` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_internet",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_internet",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_internet",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_internet",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_internet",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_internet",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_internet",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_internet",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_internet",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Dinossauros

Identificador: `dinosaurs_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_dinosaurs` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_dinosaurs` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_dinosaurs` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_mask_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_dinosaurs` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_dinosaurs` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_dinosaurs` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_boots_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_dinosaurs` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_dinosaurs` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_dinosaurs` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_dinosaurs",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_dinosaurs",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_dinosaurs",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_dinosaurs",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_dinosaurs",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_dinosaurs",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_dinosaurs",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_dinosaurs",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_dinosaurs",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Vulcão

Identificador: `volcano_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_volcano_1` | 60 | 1.8293% | 0 |
| 1 | 5 | 20 | `sidekick_season_volcano_2` | 70 | 2.1341% | 3 |
| 2 | 12 | 20 | `improvement_gadget_legendary` | 80 | 2.4390% | 6 |
| 3 | 5 | 20 | `mask_season_volcano` | 90 | 2.7439% | 9 |
| 4 | 12 | 20 | `improvement_sidekick_epic` | 100 | 3.0488% | 12 |
| 5 | 5 | 20 | `suit_season_volcano` | 110 | 3.3537% | 25 |
| 6 | 12 | 20 | `improvement_cape_epic` | 140 | 4.2683% | 35 |
| 7 | 5 | 20 | `cape_season_volcano` | 180 | 5.4878% | 39 |
| 8 | 12 | 20 | `improvement_weapon_epic` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `missiles_season_volcano` | 250 | 7.6220% | 49 |
| 10 | 5 | 20 | `gadget_season_volcano` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_volcano` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_volcano` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_volcano` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_volcano_1",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_volcano_2",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_legendary",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_volcano",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_volcano",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_volcano",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_volcano",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_volcano",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_volcano",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_volcano",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_volcano",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Mamute

Identificador: `mammoth_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_mammoth` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_cape_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_mammoth` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_mammoth` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_mammoth` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_mammoth` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_mammoth` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_belt_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_mammoth` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_mammoth` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_mammoth` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_mammoth",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_mammoth",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_mammoth",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_mammoth",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_mammoth",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_mammoth",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_mammoth",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_mammoth",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_mammoth",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Alienígenas

Identificador: `aliens_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_aliens` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_boots_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_aliens` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_aliens` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_aliens` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_aliens` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_aliens` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_aliens` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_aliens` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_aliens` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_aliens",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_aliens",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_aliens",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_aliens",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_aliens",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_aliens",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_aliens",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_aliens",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_aliens",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Espetáculo de Luta Livre

Identificador: `brawling_spirit_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_brawling_spirit` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_brawling_spirit` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_suit_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_brawling_spirit` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_mask_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_brawling_spirit` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_brawling_spirit` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_brawling_spirit` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_brawling_spirit` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_brawling_spirit` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_brawling_spirit` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_brawling_spirit",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_brawling_spirit",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_brawling_spirit",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_brawling_spirit",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_brawling_spirit",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_brawling_spirit",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_brawling_spirit",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_brawling_spirit",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_brawling_spirit",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Mundos Infinitos

Identificador: `distant_worlds_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_distant_worlds` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_gadget_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_distant_worlds` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_boots_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_distant_worlds` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_distant_worlds` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_weapon_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_distant_worlds` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_distant_worlds` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_distant_worlds` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_distant_worlds` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_distant_worlds` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_distant_worlds",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_distant_worlds",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_distant_worlds",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_distant_worlds",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_distant_worlds",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_distant_worlds",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_distant_worlds",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_distant_worlds",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_distant_worlds",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Vida no Campo

Identificador: `country_life_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_country_life` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_country_life` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_weapon_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_country_life` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_country_life` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_country_life` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_country_life` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_belt_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_country_life` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_country_life` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_country_life` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_country_life",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_country_life",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_country_life",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_country_life",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_country_life",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_country_life",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_country_life",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_country_life",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_country_life",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Futuro

Identificador: `future_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_future` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_future` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_future` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_future` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_belt_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_future` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_future` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_future` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_future` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_future` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_future",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_future",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_future",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_future",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_future",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_future",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_future",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_future",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_future",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Apocalipse

Identificador: `apocalypse_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_apocalypse` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_cape_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_apocalypse` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_boots_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_apocalypse` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_apocalypse` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_belt_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_apocalypse` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_apocalypse` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_apocalypse` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_apocalypse` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_apocalypse` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_apocalypse",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_apocalypse",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_apocalypse",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_apocalypse",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_apocalypse",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_apocalypse",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_apocalypse",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_apocalypse",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_apocalypse",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Pós-Apocalipse

Identificador: `post_apocalypse_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_post_apocalypse` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_post_apocalypse` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_weapon_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_post_apocalypse` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_post_apocalypse` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_post_apocalypse` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_post_apocalypse` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_boots_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_post_apocalypse` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_post_apocalypse` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_post_apocalypse` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_post_apocalypse",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_post_apocalypse",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_post_apocalypse",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_post_apocalypse",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_post_apocalypse",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_post_apocalypse",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_post_apocalypse",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_post_apocalypse",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_post_apocalypse",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Circo Mágico

Identificador: `magical_circus_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_magical_circus` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_magical_circus` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_cape_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_magical_circus` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_magical_circus` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_magical_circus` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_magical_circus` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_weapon_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_magical_circus` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_magical_circus` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_magical_circus` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_magical_circus",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_magical_circus",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_magical_circus",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_magical_circus",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_magical_circus",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_magical_circus",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_magical_circus",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_magical_circus",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_magical_circus",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Festa na Piscina

Identificador: `pool_party_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_pool_party` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_sidekick_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_pool_party` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_suit_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_pool_party` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_pool_party` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_weapon_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_pool_party` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_pool_party` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_pool_party` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_pool_party` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_pool_party` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_pool_party",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_pool_party",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_pool_party",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_pool_party",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_pool_party",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_pool_party",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_pool_party",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_pool_party",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_pool_party",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Zumbis

Identificador: `zombies_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_zombies` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_zombies` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_cape_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_zombies` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_zombies` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_weapon_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_zombies` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_zombies` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_suit_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_zombies` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_zombies` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_zombies` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_zombies",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_zombies",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_zombies",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_zombies",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_zombies",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_zombies",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_zombies",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_zombies",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_zombies",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Roma Antiga

Identificador: `rome_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_rome` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_cape_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_rome` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_rome` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_belt_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_rome` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_rome` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_rome` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_rome` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_rome` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_rome` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_rome",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_rome",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_rome",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_rome",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_rome",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_rome",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_rome",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_rome",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_rome",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Deuses Gregos

Identificador: `greek_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_greek` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_greek` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_greek` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_greek` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_greek` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_greek` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_greek` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_greek` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_greek` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_greek",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_greek",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_greek",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_greek",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_greek",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_greek",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_greek",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_greek",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_greek",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Steampunk

Identificador: `steampunk_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_steampunk` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_mask_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_steampunk` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_steampunk` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_steampunk` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_weapon_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_steampunk` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_steampunk` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_steampunk` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_steampunk` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_steampunk` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_steampunk",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_steampunk",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_steampunk",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_steampunk",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_steampunk",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_steampunk",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_steampunk",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_steampunk",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_steampunk",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Corrupção

Identificador: `corruption_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_corruption` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_corruption` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_corruption` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_gadget_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_corruption` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_mask_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_corruption` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_corruption` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_belt_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_corruption` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_corruption` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_corruption` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_corruption",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_corruption",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_corruption",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_corruption",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_corruption",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_corruption",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_corruption",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_corruption",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_corruption",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Reino de Gelo

Identificador: `ice_kingdom_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_ice_kingdom` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_ice_kingdom` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_ice_kingdom` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_cape_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_ice_kingdom` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_belt_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_ice_kingdom` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_ice_kingdom` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_weapon_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_ice_kingdom` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_ice_kingdom` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_ice_kingdom` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_ice_kingdom",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_ice_kingdom",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_ice_kingdom",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_ice_kingdom",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_ice_kingdom",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_ice_kingdom",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_ice_kingdom",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_ice_kingdom",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_ice_kingdom",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Sete Mares

Identificador: `bounties_of_seven_seas_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_bounties_of_seven_seas` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_bounties_of_seven_seas` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_suit_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_bounties_of_seven_seas` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_mask_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_bounties_of_seven_seas` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_bounties_of_seven_seas` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_bounties_of_seven_seas` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_sidekick_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_bounties_of_seven_seas` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_bounties_of_seven_seas` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_bounties_of_seven_seas` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_bounties_of_seven_seas",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_bounties_of_seven_seas",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_bounties_of_seven_seas",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_bounties_of_seven_seas",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_bounties_of_seven_seas",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_bounties_of_seven_seas",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_bounties_of_seven_seas",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_bounties_of_seven_seas",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_bounties_of_seven_seas",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: [m:Bárbaro Festivo|f:Bárbara Festiva]

Identificador: `spooky_xmas_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_spooky_xmas` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_boots_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_spooky_xmas` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_belt_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_spooky_xmas` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_spooky_xmas` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_spooky_xmas` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_spooky_xmas` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_mask_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_spooky_xmas` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_spooky_xmas` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_spooky_xmas` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_spooky_xmas",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_spooky_xmas",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_spooky_xmas",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_spooky_xmas",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_spooky_xmas",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_spooky_xmas",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_spooky_xmas",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_spooky_xmas",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_spooky_xmas",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Faroeste

Identificador: `wild_west_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_wild_west` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_boots_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_wild_west` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_wild_west` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_belt_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_wild_west` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_gadget_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_wild_west` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_wild_west` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_wild_west` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_wild_west` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_wild_west` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_wild_west",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_wild_west",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_wild_west",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_wild_west",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_wild_west",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_wild_west",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_wild_west",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_wild_west",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_wild_west",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: [m:Caçador|f:Caçadora] de Recompensas

Identificador: `bounty_hunter_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_bounty_hunter` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_boots_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_bounty_hunter` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_weapon_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_bounty_hunter` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_belt_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_bounty_hunter` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_mask_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_bounty_hunter` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_bounty_hunter` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_bounty_hunter` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_bounty_hunter` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_bounty_hunter` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_bounty_hunter",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_bounty_hunter",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_bounty_hunter",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_bounty_hunter",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_bounty_hunter",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_bounty_hunter",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_bounty_hunter",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_bounty_hunter",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_bounty_hunter",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Egito Antigo

Identificador: `egypt_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_egypt` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_belt_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_egypt` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_egypt` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_egypt` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_egypt` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_egypt` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_weapon_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_egypt` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_egypt` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_egypt` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_egypt",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_egypt",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_egypt",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_egypt",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_egypt",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_egypt",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_egypt",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_egypt",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_egypt",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Ártico

Identificador: `arctic_exploration_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_arctic_exploration` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_arctic_exploration` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_cape_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_arctic_exploration` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_arctic_exploration` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_belt_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_arctic_exploration` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_arctic_exploration` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_weapon_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_arctic_exploration` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_arctic_exploration` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_arctic_exploration` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_arctic_exploration",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_arctic_exploration",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_arctic_exploration",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_arctic_exploration",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_arctic_exploration",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_arctic_exploration",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_arctic_exploration",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_arctic_exploration",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_arctic_exploration",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Savana

Identificador: `wild_savannah_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_wild_savannah` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_weapon_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_wild_savannah` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_suit_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_wild_savannah` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_gadget_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_wild_savannah` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_mask_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_wild_savannah` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_wild_savannah` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_cape_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_wild_savannah` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_wild_savannah` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_wild_savannah` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_wild_savannah",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_wild_savannah",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_wild_savannah",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_wild_savannah",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_wild_savannah",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_wild_savannah",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_wild_savannah",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_wild_savannah",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_wild_savannah",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Prato Principal

Identificador: `heroic_feast_main_course_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_heroic_feast_main_course` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_cape_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_heroic_feast_main_course` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_sidekick_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_heroic_feast_main_course` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_boots_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_heroic_feast_main_course` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_suit_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_heroic_feast_main_course` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_heroic_feast_main_course` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_heroic_feast_main_course` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_heroic_feast_main_course` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_heroic_feast_main_course` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_heroic_feast_main_course",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_heroic_feast_main_course",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_heroic_feast_main_course",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_heroic_feast_main_course",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_heroic_feast_main_course",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_heroic_feast_main_course",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_heroic_feast_main_course",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_heroic_feast_main_course",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_heroic_feast_main_course",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Ermos

Identificador: `wasteland_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_wasteland` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_suit_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_wasteland` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_gadget_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_wasteland` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_weapon_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_wasteland` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_wasteland` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_wasteland` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_boots_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_wasteland` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_wasteland` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_wasteland` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_wasteland",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_wasteland",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_wasteland",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_wasteland",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_wasteland",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_wasteland",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_wasteland",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_wasteland",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_wasteland",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Sobremesa

Identificador: `heroic_feast_dessert_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_heroic_feast_dessert` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_gadget_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_heroic_feast_dessert` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_weapon_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_heroic_feast_dessert` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_sidekick_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_heroic_feast_dessert` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_boots_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_heroic_feast_dessert` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_heroic_feast_dessert` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_mask_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_heroic_feast_dessert` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_heroic_feast_dessert` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_heroic_feast_dessert` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_heroic_feast_dessert",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_heroic_feast_dessert",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_heroic_feast_dessert",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_heroic_feast_dessert",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_heroic_feast_dessert",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_heroic_feast_dessert",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_heroic_feast_dessert",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_heroic_feast_dessert",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_heroic_feast_dessert",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Mundo das Sombras

Identificador: `shadow_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_shadow` | 60 | 1.8293% | 0 |
| 1 | 5 | 20 | `mask_season_shadow_g` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_shadow_b` | 80 | 2.4390% | 6 |
| 3 | 5 | 20 | `mask_season_shadow_y` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_shadow_g` | 100 | 3.0488% | 12 |
| 5 | 5 | 20 | `suit_season_shadow_b` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `suit_season_shadow_y` | 140 | 4.2683% | 35 |
| 7 | 5 | 20 | `cape_season_shadow` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_shadow` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_shadow` | 250 | 7.6220% | 49 |
| 10 | 5 | 20 | `weapon_season_shadow` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `belt_season_shadow_g` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_shadow_b` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `belt_season_shadow_y` | 450 | 13.7195% | 89 |
| 14 | 5 | 20 | `boots_season_shadow` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_shadow",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shadow_g",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shadow_b",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shadow_y",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shadow_g",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shadow_b",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shadow_y",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_shadow",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_shadow",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_shadow",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_shadow",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shadow_g",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shadow_b",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shadow_y",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_shadow",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Cruzadas da Luz

Identificador: `holy_light_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_holy_light` | 60 | 1.8293% | 0 |
| 1 | 5 | 20 | `mask_season_holy_light` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `suit_season_holy_light_4` | 80 | 2.4390% | 6 |
| 3 | 5 | 20 | `suit_season_holy_light_3` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_holy_light_2` | 100 | 3.0488% | 12 |
| 5 | 5 | 20 | `suit_season_holy_light_1` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_holy_light_5` | 140 | 4.2683% | 35 |
| 7 | 5 | 20 | `cape_season_holy_light_4` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `cape_season_holy_light_3` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `cape_season_holy_light_2` | 250 | 7.6220% | 49 |
| 10 | 5 | 20 | `cape_season_holy_light_1` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_holy_light` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_holy_light_2` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `belt_season_holy_light_1` | 450 | 13.7195% | 89 |
| 14 | 5 | 20 | `boots_season_holy_light` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_holy_light",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_holy_light",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_4",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_3",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_2",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_1",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_5",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_4",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_3",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_2",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_1",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_holy_light",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_holy_light_2",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_holy_light_1",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_holy_light",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Alce

Identificador: `elk_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_elk` | 60 | 1.8293% | 0 |
| 1 | 5 | 20 | `mask_season_elk_bobble_b` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_elk_bobble_y` | 80 | 2.4390% | 6 |
| 3 | 5 | 20 | `mask_season_elk_fur_bl` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `mask_season_elk_fur_r` | 100 | 3.0488% | 12 |
| 5 | 5 | 20 | `mask_season_elk_fur_b` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `suit_season_elk_fur_bl` | 140 | 4.2683% | 35 |
| 7 | 5 | 20 | `suit_season_elk_fur_r` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `suit_season_elk_fur_b` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `cape_season_elk` | 250 | 7.6220% | 49 |
| 10 | 5 | 20 | `gadget_season_elk` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_elk` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_elk_r` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `belt_season_elk_b` | 450 | 13.7195% | 89 |
| 14 | 5 | 20 | `boots_season_elk_rain` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_elk",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_bobble_b",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_bobble_y",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_fur_bl",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_fur_r",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_fur_b",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_fur_bl",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_fur_r",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_fur_b",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_elk",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_elk",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_elk",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_elk_r",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_elk_b",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_elk_rain",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Alce II

Identificador: `elk_2_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_elk` | 60 | 1.8293% | 0 |
| 1 | 5 | 20 | `mask_season_elk_knitted_r` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_elk_knitted_b` | 80 | 2.4390% | 6 |
| 3 | 5 | 20 | `mask_season_elk_knitted_y` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `mask_season_elk_bobble_r` | 100 | 3.0488% | 12 |
| 5 | 5 | 20 | `suit_season_elk_jacket_y` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `suit_season_elk_jacket_r` | 140 | 4.2683% | 35 |
| 7 | 5 | 20 | `suit_season_elk_jacket_b` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `cape_season_elk` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `missiles_season_elk` | 250 | 7.6220% | 49 |
| 10 | 5 | 20 | `gadget_season_elk` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_elk` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_elk_bl` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_elk_winter` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_elk",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_knitted_r",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_knitted_b",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_knitted_y",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_bobble_r",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_jacket_y",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_jacket_r",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_jacket_b",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_elk",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_elk",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_elk",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_elk",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_elk_bl",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_elk_winter",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Pirâmide da Sorte: Dinastias do Passado

Identificador: `shang_dynasty_draw_event`. Soma inicial dos pesos: **3280**. As datas das constantes são nulas; as datas reais vêm da ocorrência agendada.

| Índice | Tipo | Fator | Referência | Peso | Probabilidade inicial | Custo na etapa desse índice |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | 5 | 20 | `sidekick_season_shang_dynasty` | 60 | 1.8293% | 0 |
| 1 | 12 | 20 | `improvement_sidekick_legendary` | 70 | 2.1341% | 3 |
| 2 | 5 | 20 | `mask_season_shang_dynasty` | 80 | 2.4390% | 6 |
| 3 | 12 | 20 | `improvement_mask_epic` | 90 | 2.7439% | 9 |
| 4 | 5 | 20 | `suit_season_shang_dynasty` | 100 | 3.0488% | 12 |
| 5 | 12 | 20 | `improvement_suit_epic` | 110 | 3.3537% | 25 |
| 6 | 5 | 20 | `cape_season_shang_dynasty` | 140 | 4.2683% | 35 |
| 7 | 12 | 20 | `improvement_cape_epic` | 180 | 5.4878% | 39 |
| 8 | 5 | 20 | `missiles_season_shang_dynasty` | 200 | 6.0976% | 45 |
| 9 | 5 | 20 | `gadget_season_shang_dynasty` | 250 | 7.6220% | 49 |
| 10 | 12 | 20 | `improvement_gadget_rare` | 300 | 9.1463% | 59 |
| 11 | 5 | 20 | `weapon_season_shang_dynasty` | 350 | 10.6707% | 69 |
| 12 | 5 | 20 | `belt_season_shang_dynasty` | 400 | 12.1951% | 79 |
| 13 | 5 | 20 | `boots_season_shang_dynasty` | 450 | 13.7195% | 89 |
| 14 | 3 | 5 | `` | 500 | 15.2439% | 99 |

A última coluna mostra o custo após tantos sorteios quantos o índice, e não o preço de ganhar aquele prémio. A probabilidade inicial muda quando outros prémios saem do conjunto.

### Definição JSON bruta

```json
{
  "start_date": null,
  "end_date": null,
  "reward_index": [
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_shang_dynasty",
      "chance": 60,
      "cost_premium_currency": 0
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_legendary",
      "chance": 70,
      "cost_premium_currency": 3
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shang_dynasty",
      "chance": 80,
      "cost_premium_currency": 6
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic",
      "chance": 90,
      "cost_premium_currency": 9
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shang_dynasty",
      "chance": 100,
      "cost_premium_currency": 12
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic",
      "chance": 110,
      "cost_premium_currency": 25
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_shang_dynasty",
      "chance": 140,
      "cost_premium_currency": 35
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic",
      "chance": 180,
      "cost_premium_currency": 39
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_shang_dynasty",
      "chance": 200,
      "cost_premium_currency": 45
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_shang_dynasty",
      "chance": 250,
      "cost_premium_currency": 49
    },
    {
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_rare",
      "chance": 300,
      "cost_premium_currency": 59
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_shang_dynasty",
      "chance": 350,
      "cost_premium_currency": 69
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shang_dynasty",
      "chance": 400,
      "cost_premium_currency": 79
    },
    {
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_shang_dynasty",
      "chance": 450,
      "cost_premium_currency": 89
    },
    {
      "reward_type": 3,
      "reward_factor": 5,
      "reward_reference": "",
      "chance": 500,
      "cost_premium_currency": 99
    }
  ]
}
```

## Ações e estado

| Ação | Parâmetros específicos |
| --- | --- |
| `assignDrawEvent` | `draw_event_id`: identificador textual do template |
| `drawEventReward` | Nenhum; o servidor escolhe o prémio e recalcula custo |
| `claimDrawEventReward` | `reward_index`, `discard_item` opcional |

O convite não tem `id`. A participação inclui `id`, `identifier`, `status`, `start_date`, `end_date`, e para cada índice N: `ts_rewardN_drawn`, `rewardN_collected`, `rewardN`. O último é uma string JSON com o prémio real. Os índices começam em zero. A coleção `items` acompanha os previews referenciados.

Estados lidos pelo cliente: 1 em curso, 2 terminado, 3 abortado com prémios pendentes, 4 abortado com prémios recolhidos. O apontador da personagem é `draw_event_id`. No hub, o sufixo é `_draw`.

## Relação com outros eventos

Os temas podem coincidir com temporadas, coleção, Caça às Toupeiras e Academia Heroica. Isso não prova que os calendários oficiais são comuns. Este motor tem calendário independente e pode coexistir com os outros sistemas. Fontes de pets identificam prémios da Pirâmide da Sorte; a entrega usa a coleção de pets.
