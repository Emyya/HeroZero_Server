# Caça às Toupeiras — event_treasures

Catálogo dos **47 templates** existentes nos assets locais do cliente HTML5 v257. Os 35 templates `event_quests` são outra família. Implementação e comandos em [event_treasures_implementacao.md](event_treasures_implementacao.md).

## Fontes e grau de certeza

- Constantes: `assets/data/constants_json.data` e cópia pública equivalente, JSON comprimido com zlib.
- Textos: `server-laravel/public/assets/i18n/pt_BR/text.data`; nomes abaixo mantêm a localização disponível.
- Cliente: `server-laravel/public/js/HeroZero.min.js`, classes `DOEventTreasure` (`Lq`), `EventTreasure` (`Um`), `EventTreasureLevel` (`NH`) e `EventTreasureCell` (`cX`).
- Os assets fornecem catálogo, metas, tipos, referências, dimensão dos tabuleiros, quantidade de toupeiras/bónus, esperas, pás gratuitas e custos.
- Não fornecem a distribuição oficial do tabuleiro nem fórmulas oficiais para quantidades escaladas dos bónus e atributos dos prémios. As regras locais estão identificadas no documento de implementação.
- Não foi fornecido um HAR oficial desta família; compatibilidade foi verificada nos getters, métodos e pedidos do JavaScript local.

## Mecânica

O jogador escava células com pás. Cada célula aberta revela uma toupeira (tipo 1), uma recompensa bónus (tipo 2) ou uma pista (tipo 3). A pista indica quantas das oito células adjacentes contêm uma toupeira ou um bónus. As toupeiras contam imediatamente para patamares; os bónus são recolhidos num pedido separado. Depois de encontrar todas as toupeiras do quintal, pode desbloquear o seguinte, mesmo com bónus ainda por encontrar. Os quintais anteriores continuam acessíveis.

O calendário é global e a partida é individual. O cliente aceita automaticamente o convite através de `assignTreasureEvent` ao atualizar o botão do evento; a aceitação não depende de um diálogo de confirmação.

## Parâmetros comuns

| Constante | Valor | Significado |
| --- | --- | --- |
| `event_treasure_min_level` | `5` | Nível mínimo |
| `event_treasure_open_cell_premium_cost` | `1` | Donuts por célula aberta com premium |
| `event_treasure_free_reveal_item_cooldown` | `10800` | Intervalo de pás gratuitas, em segundos |
| `event_treasure_free_reveal_item_amount` | `20` | Pás por recolha gratuita |
| `event_treasure_reset_progress_premium_cost` | `49` | Donuts para reiniciar |
| `event_treasure_unique_reward_alternative_type` | `3` | Enum da recompensa alternativa |
| `event_treasure_unique_reward_alternative_factor` | `10` | Fator da alternativa |
| `event_treasure_unique_reward_alternative_identifier` | `""` | Referência da alternativa |

## Índice das variantes

| # | Template | Nome localizado | Patamares | Meta final |
| --- | --- | --- | --- | --- |
| 1 | `revenge_treasure` | Caça às Toupeiras: Vingança | 10 | 120 |
| 2 | `happiness_treasure` | Caça às Toupeiras: Felicidade | 10 | 120 |
| 3 | `buffalo_treasure` | Caça às Toupeiras: Búfalo | 10 | 120 |
| 4 | `ice_kingdom_treasure` | Caça às Toupeiras: Reino de Gelo | 10 | 120 |
| 5 | `hawk_treasure` | Caça às Toupeiras: Falcão | 10 | 120 |
| 6 | `elk2_treasure` | Caça às Toupeiras: Alce | 14 | 120 |
| 7 | `ghost_treasure` | Caça às Toupeiras: Hora das Bruxas | 10 | 120 |
| 8 | `lightning_treasure` | Caça às Toupeiras: Relâmpago | 10 | 120 |
| 9 | `wild_west_treasure` | Caça às Toupeiras: Faroeste | 10 | 120 |
| 10 | `bounties_of_seven_seas_treasure` | Caça às Toupeiras: Sete Mares | 10 | 120 |
| 11 | `boar_treasure` | Caça às Toupeiras: Javali | 10 | 120 |
| 12 | `guardians_of_nature_treasure` | Caça às Toupeiras: Guardiões da Natureza | 10 | 120 |
| 13 | `bbq_treasure` | Caça às Toupeiras: Churrasco | 10 | 120 |
| 14 | `pumpkins_treasure` | Caça às Toupeiras: Monstro | 10 | 120 |
| 15 | `flower_power_treasure` | Caça às Toupeiras: Poder das Flores | 10 | 120 |
| 16 | `zombies_treasure` | Caça às Toupeiras: Zumbis | 10 | 120 |
| 17 | `science_treasure` | Caça às Toupeiras: Ciência | 10 | 120 |
| 18 | `creativity_treasure` | Caça às Toupeiras: Criatividade | 10 | 120 |
| 19 | `steampunk_treasure` | Caça às Toupeiras: Steampunk | 10 | 120 |
| 20 | `fox_treasure` | Caça às Toupeiras: Raposa | 10 | 120 |
| 21 | `apocalypse_treasure` | Caça às Toupeiras: Apocalipse | 10 | 120 |
| 22 | `arcade_treasure` | Caça às Toupeiras: Maravilhas Retrô | 10 | 120 |
| 23 | `volcano_treasure` | Caça às Toupeiras: Vulcão | 10 | 120 |
| 24 | `aliens_treasure` | Caça às Toupeiras: Alienígenas | 10 | 120 |
| 25 | `future_treasure` | Caça às Toupeiras: Futuro | 10 | 120 |
| 26 | `water_treasure` | Caça às Toupeiras: Água | 10 | 120 |
| 27 | `air_treasure` | Caça às Toupeiras: Ar | 10 | 120 |
| 28 | `shadow_treasure` | Caça às Toupeiras: Mundo das Sombras | 15 | 120 |
| 29 | `holy_light_treasure` | Caça às Toupeiras: Cruzadas da Luz | 17 | 120 |
| 30 | `bounty_hunter_treasure` | Caça às Toupeiras: [m:Caçador|f:Caçadora] de Recompensas | 10 | 120 |
| 31 | `magical_circus_treasure` | Caça às Toupeiras: Circo Mágico | 10 | 120 |
| 32 | `fire_treasure` | Caça às Toupeiras: Fogo | 10 | 120 |
| 33 | `empire_of_the_sun_treasure` | Caça às Toupeiras: Reino do Sol | 10 | 120 |
| 34 | `internet_treasure` | Caça às Toupeiras: Internet | 10 | 120 |
| 35 | `empire_of_the_stars_treasure` | Caça às Toupeiras: Reino Estelar | 10 | 120 |
| 36 | `post_apocalypse_treasure` | Caça às Toupeiras: Pós-Apocalipse | 10 | 120 |
| 37 | `witchcraft_treasure` | Caça às Toupeiras: Caldeirão da Bruxa | 10 | 120 |
| 38 | `greek_treasure` | Caça às Toupeiras: Deuses Gregos | 10 | 120 |
| 39 | `vampire_treasure` | vampire_treasure | 10 | 120 |
| 40 | `beetle_treasure` | Caça às Toupeiras: Besouro | 10 | 120 |
| 41 | `empire_of_the_moon_treasure` | Caça às Toupeiras: Reino da Lua | 10 | 120 |
| 42 | `day_of_the_dead_treasure` | Caça às Toupeiras: Reino dos Mortos | 10 | 120 |
| 43 | `mayan_empire_treasure` | Caça às Toupeiras: Reino Maia | 10 | 120 |
| 44 | `heroic_feast_appetizer_treasure` | Caça às Toupeiras: Petiscos de Sushi | 10 | 120 |
| 45 | `distant_worlds_treasure` | Caça às Toupeiras: Mundos Infinitos | 10 | 120 |
| 46 | `armoured_beasts_1_treasure` | Caça às Toupeiras: Louva-a-Deus | 10 | 120 |
| 47 | `corruption_treasure` | Caça às Toupeiras: Corrupção | 10 | 120 |

## Quintais e recompensas bónus

Todas as variantes atuais usam `standard_balancing`, com 20 quintais e 120 toupeiras no total.

O enum de **bónus das células** vem de `UiEventTreasureLevelRewardLine` (`L6`): 0 donuts, 1 moedas, 2 XP, 3 energia, 4 unidades de treino, 5 pontos de atributos. Não é o mesmo enum dos patamares.

| Quintal | Grelha | Toupeiras | Tipos de bónus | Fatores | Espera em segundos |
| --- | --- | --- | --- | --- | --- |
| 1 | 3 × 3 | 3 | `[5]` | `[3]` | 0 |
| 2 | 3 × 3 | 4 | `[1]` | `[1]` | 60 |
| 3 | 4 × 4 | 4 | `[2]` | `[1]` | 60 |
| 4 | 4 × 4 | 5 | `[0]` | `[1]` | 60 |
| 5 | 5 × 5 | 5 | `[5, 2]` | `[3, 1]` | 300 |
| 6 | 5 × 5 | 5 | `[5, 3]` | `[3, 25]` | 300 |
| 7 | 5 × 5 | 5 | `[1, 2]` | `[1, 1]` | 300 |
| 8 | 5 × 5 | 6 | `[5, 2]` | `[3, 1]` | 600 |
| 9 | 6 × 6 | 6 | `[0, 2]` | `[1, 1]` | 600 |
| 10 | 6 × 6 | 6 | `[5, 1]` | `[3, 1]` | 600 |
| 11 | 6 × 6 | 7 | `[5, 2]` | `[5, 1]` | 900 |
| 12 | 6 × 6 | 7 | `[1, 2]` | `[1.1, 1.1]` | 900 |
| 13 | 7 × 7 | 7 | `[5, 4]` | `[4, 15]` | 900 |
| 14 | 7 × 7 | 7 | `[5, 2]` | `[4, 1.2]` | 1200 |
| 15 | 7 × 7 | 7 | `[5, 2, 1]` | `[4, 1.2, 1.3]` | 1200 |
| 16 | 7 × 7 | 7 | `[5, 2, 1]` | `[5, 1.3, 1.5]` | 1200 |
| 17 | 8 × 8 | 7 | `[3, 2, 1]` | `[50, 1.4, 1.7]` | 1500 |
| 18 | 8 × 8 | 7 | `[5, 2, 1]` | `[5, 1.5, 1.9]` | 1500 |
| 19 | 8 × 8 | 7 | `[0, 2, 1]` | `[3, 1.6, 2]` | 1500 |
| 20 | 8 × 8 | 8 | `[5, 2, 1]` | `[5, 1.7, 2.2]` | 1800 |

Há um bónus por entrada em `bonus_reward`, com o fator da mesma posição em `bonus_reward_factor`. Os arrays de referências têm algumas entradas vazias adicionais; não se devem criar células extra a partir delas. A espera é iniciada quando o jogador cria o quintal, não no início global do evento.

## Patamares e tipos de prémio

Os 47 templates contêm 486 patamares: 447 do tipo 5 (item, incluindo equipamento, munição e pet) e 39 do tipo 12 (modificação de equipamento/pet). Os tipos são acompanhados por referências explícitas. O fator 20 não é apresentado como 20 atributos nem 20 exemplares do item: não há evidência para essa interpretação.

Os índices enviados em `reward_index` são baseados em zero. As tabelas seguintes mostram o índice exato, o template e o nome do item quando existe.

## Caça às Toupeiras: Vingança

Identificador: `revenge_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_revenge` | Sapatos Descalços de Tecido de Titânio Peso Pluma da Vingança |
| 1 | 15 | 5 | 20 | `belt_season_revenge` | Cinturão de Ninja Flexível com Facas de Arremesso da Vingança |
| 2 | 25 | 5 | 20 | `weapon_season_revenge` | Espada Ninja Perfeitamente Balanceada da Vingança |
| 3 | 40 | 5 | 20 | `gadget_season_revenge` | Bomba de Fumaça da Visibilidade Embaçada da Vingança |
| 4 | 55 | 5 | 20 | `missiles_season_revenge` | Estrela Ninja Planador Silencioso da Vingança |
| 5 | 70 | 5 | 20 | `cape_season_revenge` | Braços Agarradores Robóticos Controlados por Voz da Vingança |
| 6 | 85 | 5 | 20 | `suit_season_revenge` | Traje de Combate de Seda de Aranha Artificial da Vingança |
| 7 | 100 | 5 | 20 | `mask_season_revenge` | Visor da Visão Noturna de Alta Tecnologia da Vingança |
| 8 | 110 | 12 | 20 | `improvement_weapon_epic` | improvement_weapon_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_revenge` | Acompanhante “Kido” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_revenge"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_revenge"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_revenge"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_revenge"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_revenge"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_revenge"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_revenge"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_revenge"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_revenge"
    }
  ]
}
```

## Caça às Toupeiras: Felicidade

Identificador: `happiness_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_happiness` | Tênis Voadores da Felicidade |
| 1 | 15 | 5 | 20 | `belt_season_happiness` | Jatos de Manobras Laterais com GPS da Felicidade |
| 2 | 25 | 5 | 20 | `weapon_season_happiness` | Pistola Deco 400 Carregada da Felicidade |
| 3 | 40 | 5 | 20 | `gadget_season_happiness` | Drone Entregador de Presentes da Felicidade |
| 4 | 55 | 5 | 20 | `missiles_season_happiness` | Sinos Badalantes da Felicidade |
| 5 | 70 | 5 | 20 | `cape_season_happiness` | Jet Pack Cheio de Presentes da Felicidade |
| 6 | 85 | 5 | 20 | `suit_season_happiness` | Traje de Voo Aerodinâmico com Revestimento de Lã da Felicidade |
| 7 | 100 | 5 | 20 | `mask_season_happiness` | Chapéu Festivo com Óculos de Aviador da Felicidade |
| 8 | 110 | 12 | 20 | `improvement_mask_epic` | improvement_mask_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_happiness` | Acompanhante “Joy” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_happiness"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_happiness"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_happiness"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_happiness"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_happiness"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_happiness"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_happiness"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_happiness"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_happiness"
    }
  ]
}
```

## Caça às Toupeiras: Búfalo

Identificador: `buffalo_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_buffalo` | Botas de pradaria anti-areia do Búfalo |
| 1 | 15 | 5 | 20 | `belt_season_buffalo` | Cinto de balas cheio (até acabar) do Búfalo |
| 2 | 25 | 5 | 20 | `weapon_season_buffalo` | Revólver carregado que atira lasers do Búfalo |
| 3 | 40 | 5 | 20 | `gadget_season_buffalo` | Cartaz de “Procura-se” com recompensa considerável do Búfalo |
| 4 | 55 | 5 | 20 | `missiles_season_buffalo` | Estrela de xerife brilhante do Búfalo |
| 5 | 70 | 5 | 20 | `cape_season_buffalo` | Pistola de raio laser grande para todas as ocasiões do Búfalo |
| 6 | 85 | 5 | 20 | `suit_season_buffalo` | Sobretudo extremamente estiloso do Búfalo |
| 7 | 100 | 5 | 20 | `mask_season_buffalo` | Chapéu de guarda florestal com visão noturna do Búfalo |
| 8 | 110 | 12 | 20 | `improvement_suit_epic` | improvement_suit_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_buffalo` | Acompanhante “Bill” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_buffalo"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_buffalo"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_buffalo"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_buffalo"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_buffalo"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_buffalo"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_buffalo"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_buffalo"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_buffalo"
    }
  ]
}
```

## Caça às Toupeiras: Reino de Gelo

Identificador: `ice_kingdom_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_ice_kingdom` | Botas Resistentes a Gelo dos Governantes do Reino Mágico Congelado |
| 1 | 15 | 5 | 20 | `belt_season_ice_kingdom` | Fivela de Cinto de Ouro e Cristal do Reino Mágico Congelado |
| 2 | 25 | 5 | 20 | `weapon_season_ice_kingdom` | Maça Estrela Pesada do Reino Mágico Congelado |
| 3 | 40 | 5 | 20 | `gadget_season_ice_kingdom` | Amuleto Suntuosamente Esculpido do Reino Mágico Congelado |
| 4 | 55 | 5 | 20 | `missiles_season_ice_kingdom` | Cristais de Gelo Estilhaçados do Reino Mágico Congelado |
| 5 | 70 | 5 | 20 | `cape_season_ice_kingdom` | Capa Revestida de Pele Sintética do Reino Mágico Congelado |
| 6 | 85 | 5 | 20 | `suit_season_ice_kingdom` | Túnica Bufante de Nobre do Reino Mágico Congelado |
| 7 | 100 | 5 | 20 | `mask_season_ice_kingdom` | Coroa da Labareda de Gelo Congelada do Reino Mágico Congelado |
| 8 | 110 | 12 | 20 | `improvement_cape_epic` | improvement_cape_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_ice_kingdom` | Acompanhante “Roy” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_ice_kingdom"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_ice_kingdom"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_ice_kingdom"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_ice_kingdom"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_ice_kingdom"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_ice_kingdom"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_ice_kingdom"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_ice_kingdom"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_ice_kingdom"
    }
  ]
}
```

## Caça às Toupeiras: Falcão

Identificador: `hawk_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_hawk` | Garras com Suporte Pneumático do Falcão |
| 1 | 15 | 5 | 20 | `belt_season_hawk` | Cinto de Corda Cheio de Troféus do Falcão |
| 2 | 25 | 5 | 20 | `weapon_season_hawk` | Machadinha de Guerra Entalhada a Mão do Falcão |
| 3 | 40 | 5 | 20 | `gadget_season_hawk` | Cachimbo da Paz Borbulhante do Falcão |
| 4 | 55 | 5 | 20 | `missiles_season_hawk` | Penas de Arremesso Afiadas do Falcão |
| 5 | 70 | 5 | 20 | `cape_season_hawk` | Asas Possivelmente Habilitadas para Voo do Falcão |
| 6 | 85 | 5 | 20 | `suit_season_hawk` | Poncho com Revestimento de Pele Artificial do Falcão |
| 7 | 100 | 5 | 20 | `mask_2_season_hawk` | Cocar que Vai Ser Tradicional no Futuro do Falcão |
| 8 | 110 | 5 | 20 | `mask_season_hawk` | Viseira com Decorações Elaboradas do Falcão |
| 9 | 120 | 5 | 20 | `sidekick_season_hawk` | Acompanhante “Peter” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_hawk"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_hawk"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_hawk"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_hawk"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_hawk"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_hawk"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_hawk"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_2_season_hawk"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_hawk"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_hawk"
    }
  ]
}
```

## Caça às Toupeiras: Alce

Identificador: `elk2_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_elk_winter` | Botas de Inverno Impermeabilizadas do Alce Migratório |
| 1 | 15 | 5 | 20 | `belt_season_elk_bl` | Cinto de Pano Azul Amarrado do Alce Migratório |
| 2 | 20 | 5 | 20 | `weapon_season_elk` | Maçã do Amor no Palito do Alce Migratório |
| 3 | 25 | 5 | 20 | `gadget_season_elk` | Chocolate Quente com Marshmallows do Alce Migratório |
| 4 | 30 | 5 | 20 | `missiles_season_elk` | Castanhas Incandescentes do Alce Migratório |
| 5 | 40 | 5 | 20 | `cape_season_elk` | Montes de Neve Suaves do Alce Migratório |
| 6 | 50 | 5 | 20 | `suit_season_elk_jacket_b` | Casaco de Inverno Azul com Revestimento Pesado do Alce Migratório |
| 7 | 60 | 5 | 20 | `suit_season_elk_jacket_r` | Casaco de Inverno Vermelho com Revestimento Pesado do Alce Migratório |
| 8 | 70 | 5 | 20 | `suit_season_elk_jacket_y` | Casaco de Inverno Amarelo com Revestimento Pesado do Alce Migratório |
| 9 | 80 | 5 | 20 | `mask_season_elk_bobble_r` | Chapéu Bobblehead Vermelho do Alce Migratório |
| 10 | 90 | 5 | 20 | `mask_season_elk_knitted_y` | Chapéu Amarelo Tricotado do Alce Migratório |
| 11 | 100 | 5 | 20 | `mask_season_elk_knitted_b` | Chapéu Azul Tricotado do Alce Migratório |
| 12 | 110 | 5 | 20 | `mask_season_elk_knitted_r` | Chapéu Vermelho Tricotado do Alce Migratório |
| 13 | 120 | 5 | 20 | `sidekick_season_elk` | Acompanhante “Randy” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_elk_winter"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_elk_bl"
    },
    {
      "required_token_amount": 20,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_elk"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_elk"
    },
    {
      "required_token_amount": 30,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_elk"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_elk"
    },
    {
      "required_token_amount": 50,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_jacket_b"
    },
    {
      "required_token_amount": 60,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_jacket_r"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_elk_jacket_y"
    },
    {
      "required_token_amount": 80,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_bobble_r"
    },
    {
      "required_token_amount": 90,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_knitted_y"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_knitted_b"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_elk_knitted_r"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_elk"
    }
  ]
}
```

## Caça às Toupeiras: Hora das Bruxas

Identificador: `ghost_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_ghost` | Botas de Ferro com Barulhos Sinistros da Hora das Bruxas |
| 1 | 15 | 5 | 20 | `belt_season_ghost` | Cinto de Ferro Forjado na Lava da Hora das Bruxas |
| 2 | 25 | 5 | 20 | `weapon_season_ghost` | Arco Longo com Filigranas da Hora das Bruxas |
| 3 | 40 | 5 | 20 | `gadget_season_ghost` | Machado Facilmente Acessível da Hora das Bruxas |
| 4 | 55 | 5 | 20 | `missiles_season_ghost` | Flechas Carregadas de Energia da Hora das Bruxas |
| 5 | 70 | 5 | 20 | `cape_season_ghost` | Espada de Samurai Incandescente da Hora das Bruxas |
| 6 | 85 | 5 | 20 | `suit_season_ghost` | Armadura Impenetrável da Hora das Bruxas |
| 7 | 100 | 5 | 20 | `mask_season_ghost` | Labaredas Tremeluzentes da Hora das Bruxas |
| 8 | 110 | 12 | 20 | `improvement_sidekick_epic` | improvement_sidekick_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_ghost` | Acompanhante “Casimir” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_ghost"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_ghost"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_ghost"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_ghost"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_ghost"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_ghost"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_ghost"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_ghost"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_ghost"
    }
  ]
}
```

## Caça às Toupeiras: Relâmpago

Identificador: `lightning_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_lightning` | Meia Bota com Aterramento do Relâmpago |
| 1 | 15 | 5 | 20 | `belt_season_lightning` | Cinturão Estroboscópico Auto-recarregável do Relâmpago |
| 2 | 25 | 5 | 20 | `weapon_season_lightning` | Chicote Carregado do Relâmpago |
| 3 | 40 | 5 | 20 | `gadget_season_lightning` | Para-raios Portátil do Relâmpago |
| 4 | 55 | 5 | 20 | `missiles_season_lightning` | Raios de Arremesso Lustrados do Relâmpago |
| 5 | 70 | 5 | 20 | `cape_season_lightning` | Capa Heroica Auto-flamulante do Relâmpago |
| 6 | 85 | 5 | 20 | `suit_season_lightning` | Traje Heroico Brilhante do Relâmpago |
| 7 | 100 | 5 | 20 | `mask_season_lightning` | Máscara Especial Feita Sob Medida do Relâmpago |
| 8 | 110 | 12 | 20 | `improvement_gadget_epic` | improvement_gadget_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_lightning` | Acompanhante “Jolt” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_lightning"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_lightning"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_lightning"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_lightning"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_lightning"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_lightning"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_lightning"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_lightning"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_lightning"
    }
  ]
}
```

## Caça às Toupeiras: Faroeste

Identificador: `wild_west_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_wild_west` | Botinas de Couro Desgastadas pelo Deserto [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 1 | 15 | 5 | 20 | `belt_season_wild_west` | Fivela de Cinto Esculpida [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 2 | 25 | 5 | 20 | `weapon_season_wild_west` | Pistola de Brinquedo com Selo da Inspeção de Segurança [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 3 | 40 | 5 | 20 | `gadget_season_wild_west` | Mini Cacto Verde [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 4 | 55 | 5 | 20 | `missiles_season_wild_west` | Ferradura de Ferro [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 5 | 70 | 5 | 20 | `cape_season_wild_west` | Capa, Balançando Gentilmente com o Vento, [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 6 | 85 | 5 | 20 | `suit_season_wild_west` | Traje de Faroeste [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 7 | 100 | 5 | 20 | `mask_season_wild_west` | Chapéu Caubói Bacana de 40 Litros [m:do Atirador Destemido / f:da Atiradora Destemida] |
| 8 | 110 | 12 | 20 | `improvement_belt_epic` | improvement_belt_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_wild_west` | Acompanhante “Ssspencer” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_wild_west"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_wild_west"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_wild_west"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_wild_west"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_wild_west"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_wild_west"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_wild_west"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_wild_west"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_wild_west"
    }
  ]
}
```

## Caça às Toupeiras: Sete Mares

Identificador: `bounties_of_seven_seas_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_bounties_of_seven_seas` | Bota de Pirata de Couro Ornamentada com Correntes do Corsário Fantasma |
| 1 | 15 | 5 | 20 | `belt_season_bounties_of_seven_seas` | Corrente Pesada com Fivela de Cinto de Crânio de Ouro do Corsário Fantasma |
| 2 | 25 | 5 | 20 | `weapon_season_bounties_of_seven_seas` | Tentáculos da Barba do Javy Dones do Corsário Fantasma |
| 3 | 40 | 5 | 20 | `gadget_season_bounties_of_seven_seas` | Mensagem na Garrafa Cheia de Areia do Corsário Fantasma |
| 4 | 55 | 5 | 20 | `missiles_season_bounties_of_seven_seas` | Ducados Dourados do Corsário Fantasma |
| 5 | 70 | 5 | 20 | `cape_season_bounties_of_seven_seas` | Âncora de Navio Levantada do Corsário Fantasma |
| 6 | 85 | 5 | 20 | `suit_season_bounties_of_seven_seas` | Capa de Pirata Decorada do Corsário Fantasma |
| 7 | 100 | 5 | 20 | `mask_season_bounties_of_seven_seas` | Chapéu Pirata Estiloso do Corsário Fantasma |
| 8 | 110 | 12 | 20 | `improvement_boots_epic` | improvement_boots_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_bounties_of_seven_seas` | Acompanhante “Angelo” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_bounties_of_seven_seas"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_bounties_of_seven_seas"
    }
  ]
}
```

## Caça às Toupeiras: Javali

Identificador: `boar_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_boar` | Coturno com Spikes do Javali Robusto |
| 1 | 15 | 5 | 20 | `belt_season_boar` | Cinto com Reforço Mecânico do Javali Robusto |
| 2 | 25 | 5 | 20 | `weapon_season_boar` | Canhão de Raios Reciclado do Javali Robusto |
| 3 | 40 | 5 | 20 | `gadget_season_boar` | Spray de Graxa Gosmenta do Javali Robusto |
| 4 | 55 | 5 | 20 | `missiles_season_boar` | Engrenagens Douradas do Javali Robusto |
| 5 | 70 | 5 | 20 | `cape_season_boar` | Girocóptero Caseiro do Javali Robusto |
| 6 | 85 | 5 | 20 | `suit_season_boar` | Uniforme Rebelde Steampunk do Javali Robusto |
| 7 | 100 | 5 | 20 | `mask_season_boar` | Presas Polidas do Javali Robusto |
| 8 | 110 | 12 | 20 | `improvement_weapon_epic` | improvement_weapon_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_boar` | Acompanhante “Rudy” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_boar"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_boar"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_boar"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_boar"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_boar"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_boar"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_boar"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_boar"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_boar"
    }
  ]
}
```

## Caça às Toupeiras: Guardiões da Natureza

Identificador: `guardians_of_nature_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_guardians_of_nature` | Botas Cobertas de Cogumelos da Raiz de Ferro |
| 1 | 15 | 5 | 20 | `belt_season_guardians_of_nature` | Galho de Crescimento Natural da Raiz de Ferro |
| 2 | 25 | 5 | 20 | `weapon_season_guardians_of_nature` | Sabre de Madeira da Raiz de Ferro |
| 3 | 40 | 5 | 20 | `gadget_season_guardians_of_nature` | Soro de Clorofila Brilhante da Raiz de Ferro |
| 4 | 55 | 5 | 20 | `missiles_season_guardians_of_nature` | Bomba de Amanita muscaria da Raiz de Ferro |
| 5 | 70 | 5 | 20 | `cape_season_guardians_of_nature` | Esferas de Clorofila Flutuantes da Raiz de Ferro |
| 6 | 85 | 5 | 20 | `suit_season_guardians_of_nature` | Armadura de Madeira da Raiz de Ferro |
| 7 | 100 | 5 | 20 | `mask_season_guardians_of_nature` | Máscara Esculpida Habilidosamente da Raiz de Ferro |
| 8 | 110 | 12 | 20 | `improvement_mask_epic` | improvement_mask_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_guardians_of_nature` | Acompanhante “Tato” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_guardians_of_nature"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_guardians_of_nature"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_guardians_of_nature"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_guardians_of_nature"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_guardians_of_nature"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_guardians_of_nature"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_guardians_of_nature"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_guardians_of_nature"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_guardians_of_nature"
    }
  ]
}
```

## Caça às Toupeiras: Churrasco

Identificador: `bbq_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_bbq` | Papete Tradicional de Jardim com Meias [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 1 | 15 | 5 | 20 | `belt_season_bbq` | Cinto Totalmente Equipado com Utensílios [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 2 | 25 | 5 | 20 | `weapon_season_bbq` | Costela Tomahawk Grelhada e Apimentada [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 3 | 40 | 5 | 20 | `gadget_season_bbq` | Espátula de Ouro [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 4 | 55 | 5 | 20 | `missiles_season_bbq` | Espetinhos de Madeira Afiados [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 5 | 70 | 5 | 20 | `cape_season_bbq` | Defumador de Churrasco “Fogueira 5000” [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 6 | 85 | 5 | 20 | `suit_season_bbq` | Avental Estiloso de Churrasco [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 7 | 100 | 5 | 20 | `mask_season_bbq` | Chapéu de Chef Defumado Indiretamente [m:do Mestre Churrasqueiro / f:da Mestra Churrasqueira] |
| 8 | 110 | 12 | 20 | `improvement_suit_epic` | improvement_suit_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_bbq` | Acompanhante “Porky” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_bbq"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_bbq"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_bbq"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_bbq"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_bbq"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_bbq"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_bbq"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_bbq"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_bbq"
    }
  ]
}
```

## Caça às Toupeiras: Monstro

Identificador: `pumpkins_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `missiles_season_pumpkins` | Doces Particularmente Aerodinâmicos |
| 1 | 15 | 5 | 20 | `boots_season_pumpkins` | Botas de Couro Furadas do Monstro Perambulante |
| 2 | 25 | 5 | 20 | `belt_season_pumpkins` | Cinto de Couro Robusto do Monstro Perambulante |
| 3 | 40 | 5 | 20 | `weapon_season_pumpkins` | Martelo Caseiro Anti-abóboras do Monstro Perambulante |
| 4 | 55 | 5 | 20 | `gadget_season_pumpkins` | Abóbora Cheia de Doces do Monstro Perambulante |
| 5 | 70 | 5 | 20 | `cape_season_pumpkins` | Bobinas de Tesla Portáteis do Monstro Perambulante |
| 6 | 85 | 5 | 20 | `suit_season_pumpkins_2` | Casaco Remendado do Monstro Perambulante |
| 7 | 100 | 5 | 20 | `suit_season_pumpkins_1` | Casaco de Usar Todo Dia Remendado do Monstro Perambulante |
| 8 | 110 | 5 | 20 | `mask_season_pumpkins` | Máscara Assustadora do Monstro Perambulante |
| 9 | 120 | 5 | 20 | `sidekick_season_pumpkins` | Acompanhante “Frankie” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_pumpkins"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_pumpkins"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_pumpkins"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_pumpkins"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_pumpkins"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_pumpkins"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_pumpkins_2"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_pumpkins_1"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_pumpkins"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_pumpkins"
    }
  ]
}
```

## Caça às Toupeiras: Poder das Flores

Identificador: `flower_power_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_flower_power` | Sandália Arco-íris Confortável [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 1 | 15 | 5 | 20 | `belt_season_flower_power` | Faixa Azul de Veludo [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 2 | 25 | 5 | 20 | `weapon_season_flower_power` | Pistola d’Água com Aroma de Flores [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 3 | 40 | 5 | 20 | `gadget_season_flower_power` | Cumbuca Cantante Dourada [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 4 | 55 | 5 | 20 | `missiles_season_flower_power` | Flores Delicados de Arremesso |
| 5 | 70 | 5 | 20 | `cape_season_flower_power` | Nuvens Arco-íris Felizes [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 6 | 85 | 5 | 20 | `suit_season_flower_power` | Traje Florido Hippie [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 7 | 100 | 5 | 20 | `mask_season_flower_power` | Óculos de Sol Extra Grandes [m:do Dançarino dos Sonhos Super Maneiro / f:da Dançarina dos Sonhos Super Maneira] |
| 8 | 110 | 12 | 20 | `improvement_cape_epic` | improvement_cape_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_flower_power` | Acompanhante “Todd” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_flower_power"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_flower_power"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_flower_power"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_flower_power"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_flower_power"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_flower_power"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_flower_power"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_flower_power"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_flower_power"
    }
  ]
}
```

## Caça às Toupeiras: Zumbis

Identificador: `zombies_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_zombies` | Boot de Couro Reformado d[m:o / f:a] Caça-Zumbis |
| 1 | 15 | 5 | 20 | `belt_season_zombies` | Pochete cheia de Rações de Emergência d[m:o / f:a] Caça-Zumbis |
| 2 | 25 | 5 | 20 | `weapon_season_zombies` | Armadilha Sofisticada para Smumbis d[m:o / f:a] Caça-Zumbis |
| 3 | 40 | 5 | 20 | `gadget_season_zombies` | Kit de Primeiros Socorros Não Vencido d[m:o / f:a] Caça-Zumbis |
| 4 | 55 | 5 | 20 | `missiles_season_zombies` | Dardos Poderosos com Antissoro d[m:o / f:a] Caça-Zumbis |
| 5 | 70 | 5 | 20 | `cape_season_zombies` | Lança Improvisada d[m:o / f:a] Caça-Zumbis |
| 6 | 85 | 5 | 20 | `suit_season_zombies` | Traje de Emergência Apocalíptico d[m:o / f:a] Caça-Zumbis |
| 7 | 100 | 5 | 20 | `mask_season_zombies` | Capacete de Futebol Americano com Espinhos d[m:o / f:a] Caça-Zumbis |
| 8 | 110 | 12 | 20 | `improvement_sidekick_epic` | improvement_sidekick_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_zombies` | Acompanhante “Hubert” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_zombies"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_zombies"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_zombies"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_zombies"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_zombies"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_zombies"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_zombies"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_zombies"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_zombies"
    }
  ]
}
```

## Caça às Toupeiras: Ciência

Identificador: `science_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_science` | Perneiras de Proteção [m:do / f:da] Cientista |
| 1 | 15 | 5 | 20 | `belt_season_science` | Cinto de Ferramentas Prático [m:do / f:da] Cientista |
| 2 | 25 | 5 | 20 | `weapon_season_science` | Maçarico Ultra Quente [m:do / f:da] Cientista |
| 3 | 40 | 5 | 20 | `gadget_season_science` | Microscópio de Alta Resolução [m:do / f:da] Cientista |
| 4 | 55 | 5 | 20 | `missiles_season_science` | Pipetas Anti-estilhaço [m:do / f:da] Cientista |
| 5 | 70 | 5 | 20 | `cape_season_science` | Incubadora Portátil [m:do / f:da] Cientista |
| 6 | 85 | 5 | 20 | `suit_season_science` | Jaleco Resistente a Ácido [m:do / f:da] Cientista |
| 7 | 100 | 5 | 20 | `mask_season_science` | Óculos de Proteção Resistentes ao Calor [m:do / f:da] Cientista |
| 8 | 110 | 12 | 20 | `improvement_gadget_epic` | improvement_gadget_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_science` | Acompanhante “Gary” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_science"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_science"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_science"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_science"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_science"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_science"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_science"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_science"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_science"
    }
  ]
}
```

## Caça às Toupeiras: Criatividade

Identificador: `creativity_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_creativity` | Tênis Esportivos do Gênio Criativo |
| 1 | 15 | 5 | 20 | `belt_season_creativity` | Cinto de Couro Casualmente Pendurado do Gênio Criativo |
| 2 | 25 | 5 | 20 | `weapon_season_creativity` | Spray de Tinta Bem Usado do Gênio Criativo |
| 3 | 40 | 5 | 20 | `gadget_season_creativity` | Skate Autoral do Gênio Criativo |
| 4 | 55 | 5 | 20 | `missiles_season_creativity` | Bicos de Spray de Cores Fortes do Gênio Criativo |
| 5 | 70 | 5 | 20 | `cape_seas_creativi` | Caixa de Som Portátil do Gênio Criativo |
| 6 | 85 | 5 | 20 | `suit_season_creativity` | Traje Heroico Estiloso do Gênio Criativo |
| 7 | 100 | 5 | 20 | `mask_2_season_creativity` | Corte de Cabelo Recém Cortado do Gênio Criativo |
| 8 | 110 | 5 | 20 | `mask_season_creativity` | Máscara de Pintura de Proteção do Gênio Criativo |
| 9 | 120 | 5 | 20 | `sidekick_season_creativity` | Acompanhante “Benny” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_creativity"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_creativity"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_creativity"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_creativity"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_creativity"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_seas_creativi"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_creativity"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_2_season_creativity"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_creativity"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_creativity"
    }
  ]
}
```

## Caça às Toupeiras: Steampunk

Identificador: `steampunk_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_steampunk` | Botas Práticas d[m:o Mecânico / f:a Mecânica] Steampunk |
| 1 | 15 | 5 | 20 | `belt_season_steampunk` | Pochete com Fecho Duplo d[m:o Mecânico / f:a Mecânica] Steampunk |
| 2 | 25 | 5 | 20 | `weapon_season_steampunk` | Pistola Criogênica Precisa d[m:o Mecânico / f:a Mecânica] Steampunk |
| 3 | 40 | 5 | 20 | `gadget_season_steampunk` | Relógio de Bolso Precisamente Ajustado d[m:o Mecânico / f:a Mecânica] Steampunk |
| 4 | 55 | 5 | 20 | `missiles_season_steampunk` | Drone-Inseto de Corda d[m:o Mecânico / f:a Mecânica] Steampunk |
| 5 | 70 | 5 | 20 | `cape_season_steampunk` | Gerador de Vapor Portátil d[m:o Mecânico / f:a Mecânica] Steampunk |
| 6 | 85 | 5 | 20 | `suit_season_steampunk` | Exoesqueleto Parcial Desenvolvido Detalhadamente d[m:o Mecânico / f:a Mecânica] Steampunk |
| 7 | 100 | 5 | 20 | `mask_season_steampunk` | Cartola Multifuncional d[m:o Mecânico / f:a Mecânica] Steampunk |
| 8 | 110 | 12 | 20 | `improvement_belt_epic` | improvement_belt_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_steampunk` | Acompanhante “4-R4-NH4” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_steampunk"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_steampunk"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_steampunk"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_steampunk"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_steampunk"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_steampunk"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_steampunk"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_steampunk"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_steampunk"
    }
  ]
}
```

## Caça às Toupeiras: Raposa

Identificador: `fox_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_fox` | Botas de Couro Costuradas à Mão da Raposa Esperta |
| 1 | 15 | 5 | 20 | `belt_season_fox` | Cinto de Alquimia Prática da Raposa Esperta |
| 2 | 25 | 5 | 20 | `weapon_season_fox` | Cajado de Alquimia Mágica da Raposa Esperta |
| 3 | 40 | 5 | 20 | `gadget_season_fox` | Rolo de Pergaminho Misterioso da Raposa Esperta |
| 4 | 55 | 5 | 20 | `missiles_season_fox` | Poção Caseira de Mísseis da Raposa Esperta |
| 5 | 70 | 5 | 20 | `cape_season_fox` | Cajado-Bengala Xamânico da Raposa Esperta |
| 6 | 85 | 5 | 20 | `suit_season_fox` | Roupas de Tecido Reforçado com Couro da Raposa Esperta |
| 7 | 100 | 5 | 20 | `mask_season_fox` | Máscara Mística da Raposa Esperta |
| 8 | 110 | 12 | 20 | `improvement_boots_epic` | improvement_boots_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_fox` | Acompanhante “Nox” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_fox"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_fox"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_fox"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_fox"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_fox"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_fox"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_fox"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_fox"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_fox"
    }
  ]
}
```

## Caça às Toupeiras: Apocalipse

Identificador: `apocalypse_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_apocalypse` | Botas com Ponta de Aço e Cadarço do Apocalipse |
| 1 | 15 | 5 | 20 | `belt_season_apocalypse` | Pochete de Quadril Útil do Apocalipse |
| 2 | 25 | 5 | 20 | `weapon_season_apocalypse` | Bico de Bunsen Modificado do Apocalipse |
| 3 | 40 | 5 | 20 | `gadget_season_apocalypse` | Bobblehead Incógnito do Apocalipse |
| 4 | 55 | 5 | 20 | `missiles_season_apocalypse` | Tampinhas de Garrafa Úteis como Moeda de Troca do Apocalipse |
| 5 | 70 | 5 | 20 | `cape_season_apocalypse` | Barril de Lixo Tóxico com Vazamento do Apocalipse |
| 6 | 85 | 5 | 20 | `suit_season_apocalypse` | Traje de Sobrevivência Improvisado do Apocalipse |
| 7 | 100 | 5 | 20 | `mask_season_apocalypse` | Máscara de Gás com Spikes do Apocalipse |
| 8 | 110 | 12 | 20 | `improvement_weapon_epic` | improvement_weapon_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_apocalypse` | Acompanhante “Binky” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_apocalypse"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_apocalypse"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_apocalypse"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_apocalypse"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_apocalypse"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_apocalypse"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_apocalypse"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_apocalypse"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_apocalypse"
    }
  ]
}
```

## Caça às Toupeiras: Maravilhas Retrô

Identificador: `arcade_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_arcade` | Tênis Heroico Estiloso [m:do / f:da] Gamer Retrô Futurista |
| 1 | 15 | 5 | 20 | `belt_season_arcade` | Cinto de Presilha de Tecido Preto [m:do / f:da] Gamer Retrô Futurista |
| 2 | 25 | 5 | 20 | `weapon_season_arcade` | Martelo de Arcade de Pancada na Toupeira [m:do / f:da] Gamer Retrô Futurista |
| 3 | 40 | 5 | 20 | `gadget_season_arcade` | Videogame Portátil [m:do / f:da] Gamer Retrô Futurista |
| 4 | 55 | 5 | 20 | `missiles_season_arcade` | Controle Remoto Preciso do Whee [m:do / f:da] Gamer Retrô Futurista |
| 5 | 70 | 5 | 20 | `cape_season_arcade` | Hologramas Flutuantes [m:do / f:da] Gamer Retrô Futurista |
| 6 | 85 | 5 | 20 | `suit_season_arcade` | Traje de Gamer Casual [m:do / f:da] Gamer Retrô Futurista |
| 7 | 100 | 5 | 20 | `mask_season_arcade` | Óculos de VR de Ponta [m:do / f:da] Gamer Retrô Futurista |
| 8 | 110 | 5 | 20 | `mask_season_arcade_hologram` | Óculos de VR de Ponta com Hologramas [m:do / f:da] Gamer Retrô Futurista |
| 9 | 120 | 5 | 20 | `sidekick_season_arcade` | Acompanhante “Le King” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_arcade"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_arcade"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_arcade"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_arcade"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_arcade"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_arcade"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_arcade"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_arcade"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_arcade_hologram"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_arcade"
    }
  ]
}
```

## Caça às Toupeiras: Vulcão

Identificador: `volcano_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_volcano` | Botas de Aço Forjadas em Lava do Vulcão |
| 1 | 15 | 5 | 20 | `belt_season_volcano` | Cinto Brilhante do Vulcão |
| 2 | 25 | 5 | 20 | `weapon_season_volcano` | Punhal Afiado de Obsidiana do Vulcão |
| 3 | 40 | 5 | 20 | `gadget_season_volcano` | Corrente Potente de Obsidiana do Vulcão |
| 4 | 55 | 5 | 20 | `missiles_season_volcano` | Bola de Magma Irradiando Calor do Vulcão |
| 5 | 70 | 5 | 20 | `cape_season_volcano` | Maça de Magma com Espinhos |
| 6 | 85 | 5 | 20 | `suit_season_volcano` | Armadura de Magma Ardente do Vulcão |
| 7 | 100 | 5 | 20 | `mask_season_volcano` | Capacete com Espinhos do Vulcão |
| 8 | 110 | 5 | 20 | `sidekick_season_volcano_2` | Acompanhante “Pietro” |
| 9 | 120 | 5 | 20 | `sidekick_season_volcano_1` | Acompanhante “Drummy” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_volcano"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_volcano"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_volcano"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_volcano"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_volcano"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_volcano"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_volcano"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_volcano"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_volcano_2"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_volcano_1"
    }
  ]
}
```

## Caça às Toupeiras: Alienígenas

Identificador: `aliens_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_aliens` | Botas Magnéticas Desmagnetizáveis do Terror Alienígena |
| 1 | 15 | 5 | 20 | `belt_season_aliens` | Cinto Alado do Terror Alienígena |
| 2 | 25 | 5 | 20 | `weapon_season_aliens` | Canhão Antimatéria de Alta Tecnologia do Terror Alienígena |
| 3 | 40 | 5 | 20 | `gadget_season_aliens` | Chapéu de Papel Alumínio Polido com Antenas Receptoras do Terror Alienígena |
| 4 | 55 | 5 | 20 | `missiles_season_aliens` | Travesseiro Fofinho que Abraça sua Cara do Terror Alienígena |
| 5 | 70 | 5 | 20 | `cape_season_aliens` | Antena Parabólica Multifuncional do Terror Alienígena |
| 6 | 85 | 5 | 20 | `suit_season_aliens` | Traje de Caça Testado no Espaço do Terror Alienígena |
| 7 | 100 | 5 | 20 | `mask_season_aliens` | Capacete com Antena Extraterrestre do Terror Alienígena |
| 8 | 110 | 12 | 20 | `improvement_mask_epic` | improvement_mask_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_aliens` | Acompanhante “Flerky” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_aliens"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_aliens"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_aliens"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_aliens"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_aliens"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_aliens"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_aliens"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_aliens"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_aliens"
    }
  ]
}
```

## Caça às Toupeiras: Futuro

Identificador: `future_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_future` | Tênis Holográficos de Alta Tecnologia [m:do Herói / f:da Heroína] do Futuro |
| 1 | 15 | 5 | 20 | `belt_season_future` | Cinto de Pano Autoajustável com Bolsos [m:do Herói / f:da Heroína] do Futuro |
| 2 | 25 | 5 | 20 | `weapon_season_future` | Adaga de Empurrão a Laser [m:do Herói / f:da Heroína] do Futuro |
| 3 | 40 | 5 | 20 | `gadget_season_future` | Drone Voador Delator de Crimes [m:do Herói / f:da Heroína] do Futuro |
| 4 | 55 | 5 | 20 | `missiles_season_future` | Disco de Arremesso Brilhante e Estiloso [m:do Herói / f:da Heroína] do Futuro |
| 5 | 70 | 5 | 20 | `cape_season_future` | Braços Mecânicos de Boombox Controlados por App [m:do Herói / f:da Heroína] do Futuro |
| 6 | 85 | 5 | 20 | `suit_season_future` | Roupa Estilosa de Fazer Cooper com Pelo Sintético [m:do Herói / f:da Heroína] do Futuro |
| 7 | 100 | 5 | 20 | `mask_season_future` | Óculos de Sol com Estilo Atemporal [m:do Herói / f:da Heroína] do Futuro |
| 8 | 110 | 12 | 20 | `improvement_suit_epic` | improvement_suit_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_future` | Acompanhante “Hamilton” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_future"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_future"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_future"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_future"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_future"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_future"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_future"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_future"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_future"
    }
  ]
}
```

## Caça às Toupeiras: Água

Identificador: `water_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_water` | Tornozeleiras Molhadas da Água Corrente |
| 1 | 15 | 5 | 20 | `belt_season_water` | Cinto Esculpido a Mão da Água Corrente |
| 2 | 25 | 5 | 20 | `weapon_season_water` | Tridente Possante da Água Corrente |
| 3 | 40 | 5 | 20 | `gadget_season_water` | Pérola da Sorte Lustrada da Água Corrente |
| 4 | 55 | 5 | 20 | `missiles_season_water` | Estrela-ninja-do-mar Reutilizável da Água Corrente |
| 5 | 70 | 5 | 20 | `cape_season_water` | Bolhas de Água Flutuantes da Água Corrente |
| 6 | 85 | 5 | 20 | `suit_season_water` | Traje Fisicamente Impossível da Água Corrente |
| 7 | 100 | 5 | 20 | `mask_season_water` | Máscara Lendária da Água Corrente |
| 8 | 110 | 12 | 20 | `improvement_cape_epic` | improvement_cape_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_water` | Acompanhante “Wobble” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_water"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_water"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_water"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_water"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_water"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_water"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_water"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_water"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_water"
    }
  ]
}
```

## Caça às Toupeiras: Ar

Identificador: `air_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_air` | Sandálias de Madeira de Yokio da Corrente de Ar |
| 1 | 15 | 5 | 20 | `belt_season_air` | Cinto Peso Pena da Corrente de Ar |
| 2 | 25 | 5 | 20 | `weapon_season_air` | Lança-bolas com Sinos Dracônicos da Corrente de Ar |
| 3 | 40 | 5 | 20 | `gadget_season_air` | Nuvem Voadora Conjurada da Corrente de Ar |
| 4 | 55 | 5 | 20 | `missiles_season_air` | Facas de Arremesso Perfeitamente Balanceadas da Corrente de Ar |
| 5 | 70 | 5 | 20 | `cape_season_air` | Mar de Nuvens Rodopiantes da Corrente de Ar |
| 6 | 85 | 5 | 20 | `suit_season_air` | Robe de Combate Elaboradamente Decorado da Corrente de Ar |
| 7 | 100 | 5 | 20 | `mask_season_air` | Máscara de Madeira Detalhadamente Esculpida da Corrente de Ar |
| 8 | 110 | 12 | 20 | `improvement_sidekick_epic` | improvement_sidekick_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_air` | Acompanhante “Draco” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_air"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_air"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_air"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_air"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_air"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_air"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_air"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_air"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_air"
    }
  ]
}
```

## Caça às Toupeiras: Mundo das Sombras

Identificador: `shadow_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_shadow` | Botas Revestidas Impermeabilizadas com Água Benta [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 1 | 10 | 5 | 20 | `belt_season_shadow_y` | Cinto Amarrado [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 2 | 15 | 5 | 20 | `belt_season_shadow_b` | Cinto Amarrado [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 3 | 20 | 5 | 20 | `belt_season_shadow_g` | Cinto Amarrado [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 4 | 25 | 5 | 20 | `weapon_season_shadow` | Besta Pesada [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 5 | 30 | 5 | 20 | `gadget_season_shadow` | Granada Anti-profana Cheia de Água Benta [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 6 | 40 | 5 | 20 | `missiles_season_shadow` | Cartas Marcadas [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 7 | 50 | 5 | 20 | `cape_season_shadow` | Sombra Sussurrante [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 8 | 60 | 5 | 20 | `suit_season_shadow_y` | Sobretudo Sombrio [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 9 | 70 | 5 | 20 | `suit_season_shadow_b` | Sobretudo Sombrio [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 10 | 80 | 5 | 20 | `suit_season_shadow_g` | Sobretudo Sombrio [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 11 | 90 | 5 | 20 | `mask_season_shadow_y` | Chapéu Extremamente Maneiro [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 12 | 100 | 5 | 20 | `mask_season_shadow_b` | Chapéu Extremamente Maneiro [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 13 | 110 | 5 | 20 | `mask_season_shadow_g` | Chapéu Extremamente Maneiro [m:do Caça-demônios Ousado / f:da Caça-demônios Ousada] |
| 14 | 120 | 5 | 20 | `sidekick_season_shadow` | Acompanhante “Crowley” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_shadow"
    },
    {
      "required_token_amount": 10,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shadow_y"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shadow_b"
    },
    {
      "required_token_amount": 20,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_shadow_g"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_shadow"
    },
    {
      "required_token_amount": 30,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_shadow"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_shadow"
    },
    {
      "required_token_amount": 50,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_shadow"
    },
    {
      "required_token_amount": 60,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shadow_y"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shadow_b"
    },
    {
      "required_token_amount": 80,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_shadow_g"
    },
    {
      "required_token_amount": 90,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shadow_y"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shadow_b"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_shadow_g"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_shadow"
    }
  ]
}
```

## Caça às Toupeiras: Cruzadas da Luz

Identificador: `holy_light_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_holy_light` | Botas Forjadas para o Combate das Cruzadas da Luz |
| 1 | 10 | 5 | 20 | `belt_season_holy_light_1` | Cinto de Couro Nodoso das Cruzadas da Luz |
| 2 | 15 | 5 | 20 | `belt_season_holy_light_2` | Cinto de Couro Nodoso com Adaga de Espuma das Cruzadas da Luz |
| 3 | 20 | 5 | 20 | `weapon_season_holy_light` | Espada Reluzente das Cruzadas da Luz |
| 4 | 25 | 5 | 20 | `gadget_season_holy_light` | Coroa de Louros Tecida a Mão das Cruzadas da Luz |
| 5 | 30 | 5 | 20 | `missiles_season_holy_light` | Esfera Cospe-faíscas das Cruzadas da Luz |
| 6 | 35 | 5 | 20 | `cape_season_holy_light_1` | Capa Heroica Tremulante das Cruzadas da Luz |
| 7 | 40 | 5 | 20 | `cape_season_holy_light_2` | Bandeira Azul Tremulante ao Vento das Cruzadas da Luz |
| 8 | 45 | 5 | 20 | `cape_season_holy_light_3` | Bandeira Branca Tremulante ao Vento das Cruzadas da Luz |
| 9 | 50 | 5 | 20 | `cape_season_holy_light_4` | Bandeira Azul e Capa Tremulante ao Vento das Cruzadas da Luz |
| 10 | 60 | 5 | 20 | `cape_season_holy_light_5` | Bandeira Branca e Capa Tremulante ao Vento das Cruzadas da Luz |
| 11 | 70 | 5 | 20 | `suit_season_holy_light_1` | Armadura Resplandecente das Cruzadas da Luz |
| 12 | 80 | 5 | 20 | `suit_season_holy_light_2` | Armadura Resplandecente Reforçada das Cruzadas da Luz |
| 13 | 90 | 5 | 20 | `suit_season_holy_light_3` | Armadura Resplandecente com Saia Blindada Azul das Cruzadas da Luz |
| 14 | 100 | 5 | 20 | `suit_season_holy_light_4` | Armadura Resplandecente Reforçada com Saia Blindada Azul das Cruzadas da Luz |
| 15 | 110 | 5 | 20 | `mask_season_holy_light` | Capacete Lustrado com Visor das Cruzadas da Luz |
| 16 | 120 | 5 | 20 | `sidekick_season_holy_light` | Acompanhante “Richard” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_holy_light"
    },
    {
      "required_token_amount": 10,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_holy_light_1"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_holy_light_2"
    },
    {
      "required_token_amount": 20,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_holy_light"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_holy_light"
    },
    {
      "required_token_amount": 30,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_holy_light"
    },
    {
      "required_token_amount": 35,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_1"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_2"
    },
    {
      "required_token_amount": 45,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_3"
    },
    {
      "required_token_amount": 50,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_4"
    },
    {
      "required_token_amount": 60,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_holy_light_5"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_1"
    },
    {
      "required_token_amount": 80,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_2"
    },
    {
      "required_token_amount": 90,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_3"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_holy_light_4"
    },
    {
      "required_token_amount": 110,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_holy_light"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_holy_light"
    }
  ]
}
```

## Caça às Toupeiras: [m:Caçador|f:Caçadora] de Recompensas

Identificador: `bounty_hunter_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_bounty_hunter` | Botinas Funcionais d[m:o Caçador / f:a Caçadora] de Recompensas |
| 1 | 15 | 5 | 20 | `belt_season_bounty_hunter` | Cinto Resistente com Coldre d[m:o Caçador / f:a Caçadora] de Recompensas |
| 2 | 25 | 5 | 20 | `weapon_season_bounty_hunter` | Espada Resplandecente d[m:o Caçador / f:a Caçadora] de Recompensas |
| 3 | 40 | 5 | 20 | `gadget_season_bounty_hunter` | Chave de Espaçonave com Trava Central d[m:o Caçador / f:a Caçadora] de Recompensas |
| 4 | 55 | 5 | 20 | `missiles_season_bounty_hunter` | Minas de Arremesso com Espinhos d[m:o Caçador / f:a Caçadora] de Recompensas |
| 5 | 70 | 5 | 20 | `cape_season_bounty_hunter` | Drone Rastreador Bem Útil d[m:o Caçador / f:a Caçadora] de Recompensas |
| 6 | 85 | 5 | 20 | `suit_season_bounty_hunter` | Armadura de Batalha Balanceada d[m:o Caçador / f:a Caçadora] de Recompensas |
| 7 | 100 | 5 | 20 | `mask_season_bounty_hunter` | Capacete de Liga Leve com Proteção Integrada Anti-Chuva d[m:o Caçador / f:a Caçadora] de Recompensas |
| 8 | 110 | 12 | 20 | `improvement_gadget_epic` | improvement_gadget_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_bounty_hunter` | Acompanhante “Yogu” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_bounty_hunter"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_bounty_hunter"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_bounty_hunter"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_bounty_hunter"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_bounty_hunter"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_bounty_hunter"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_bounty_hunter"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_bounty_hunter"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_bounty_hunter"
    }
  ]
}
```

## Caça às Toupeiras: Circo Mágico

Identificador: `magical_circus_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_magical_circus` | Tênis com Cadarços Auto-amarráveis d[m:o Circense Mágico / f:a Circense Mágica] |
| 1 | 15 | 5 | 20 | `belt_season_magical_circus` | Baralho Flutuante d[m:o Circense Mágico / f:a Circense Mágica] |
| 2 | 25 | 5 | 20 | `weapon_season_magical_circus` | Varinha Mágica Testada em Espetáculos d[m:o Circense Mágico / f:a Circense Mágica] |
| 3 | 40 | 5 | 20 | `gadget_season_magical_circus` | Colher Torta d[m:o Circense Mágico / f:a Circense Mágica] |
| 4 | 55 | 5 | 20 | `missiles_season_magical_circus` | Pomba Explosiva Surpreendentemente Realista d[m:o Circense Mágico / f:a Circense Mágica] |
| 5 | 70 | 5 | 20 | `cape_season_magical_circus` | Capa Esvoaçante d[m:o Circense Mágico / f:a Circense Mágica] |
| 6 | 85 | 5 | 20 | `suit_season_magical_circus` | Traje do Picadeiro Misterioso d[m:o Circense Mágico / f:a Circense Mágica] |
| 7 | 100 | 5 | 20 | `mask_season_magical_circus` | Chapéu Mágico Chamativo d[m:o Circense Mágico / f:a Circense Mágica] |
| 8 | 110 | 12 | 20 | `improvement_belt_epic` | improvement_belt_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_magical_circus` | Acompanhante “Sr. Botões” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_magical_circus"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_magical_circus"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_magical_circus"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_magical_circus"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_magical_circus"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_magical_circus"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_magical_circus"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_magical_circus"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_magical_circus"
    }
  ]
}
```

## Caça às Toupeiras: Fogo

Identificador: `fire_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_fire` | Botas de Zeronita com Joelheiras Embutidas do Fogo |
| 1 | 15 | 5 | 20 | `belt_season_fire` | Cinto de Zeronita Fácil de Abrir do Fogo |
| 2 | 25 | 5 | 20 | `weapon_season_fire` | Decididamente Não é um Utensílio Lança-chamas do Fogo |
| 3 | 40 | 5 | 20 | `gadget_season_fire` | Extintor Prático do Fogo |
| 4 | 55 | 5 | 20 | `missiles_season_fire` | Marshmallows Grelhados na Brasa do Fogo |
| 5 | 70 | 5 | 20 | `cape_season_fire` | Cravos Flamejantes Carregados do Fogo |
| 6 | 85 | 5 | 20 | `suit_season_fire` | Armadura Forjada de Zeronita Endurecida do Fogo |
| 7 | 100 | 5 | 20 | `mask_season_fire` | Capacete Ergonômico com Assistente de Voz Integrado do Fogo |
| 8 | 110 | 12 | 20 | `improvement_boots_epic` | improvement_boots_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_fire` | Acompanhante “Faísca” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_fire"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_fire"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_fire"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_fire"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_fire"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_fire"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_fire"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_fire"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_fire"
    }
  ]
}
```

## Caça às Toupeiras: Reino do Sol

Identificador: `empire_of_the_sun_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_empire_of_the_sun` | Botas Blindadas Leves d[m:o Templário / f:a Templária] do Sol |
| 1 | 15 | 5 | 20 | `belt_season_empire_of_the_sun` | Cinto de Ouro d[m:o Templário / f:a Templária] do Sol |
| 2 | 25 | 5 | 20 | `weapon_season_empire_of_the_sun` | Canhão Solar Carregado com Raios de Sol d[m:o Templário / f:a Templária] do Sol |
| 3 | 40 | 5 | 20 | `gadget_season_empire_of_the_sun` | Protetor Solar Antirraios d[m:o Templário / f:a Templária] do Sol |
| 4 | 55 | 5 | 20 | `missiles_season_empire_of_the_sun` | Refresco Refrescante d[m:o Templário / f:a Templária] do Sol |
| 5 | 70 | 5 | 20 | `cape_season_empire_of_the_sun` | Núcleo Solar Flutuante d[m:o Templário / f:a Templária] do Sol |
| 6 | 85 | 5 | 20 | `suit_season_empire_of_the_sun` | Armadura Lustrada d[m:o Templário / f:a Templária] do Sol |
| 7 | 100 | 5 | 20 | `mask_season_empire_of_the_sun` | Capacete Anti-brilho d[m:o Templário / f:a Templária] do Sol |
| 8 | 110 | 12 | 20 | `improvement_weapon_epic` | improvement_weapon_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_empire_of_the_sun` | Acompanhante “Ra” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_empire_of_the_sun"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_empire_of_the_sun"
    }
  ]
}
```

## Caça às Toupeiras: Internet

Identificador: `internet_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_internet` | Pantufas de Gatinho de Pelúcia do Meme da Internet |
| 1 | 15 | 5 | 20 | `belt_season_internet` | Pochete Retrô do Meme da Internet |
| 2 | 25 | 5 | 20 | `weapon_season_internet` | Carta de Jogo da Reversão Absoluta do Meme da Internet |
| 3 | 40 | 5 | 20 | `gadget_season_internet` | Chaveiro do Cachorrinho Fofinho do Meme da Internet |
| 4 | 55 | 5 | 20 | `missiles_season_internet` | Emojis Voadores do Meme da Internet |
| 5 | 70 | 5 | 20 | `cape_season_internet` | Donuts Flutuantes do Meme da Internet |
| 6 | 85 | 5 | 20 | `suit_season_internet` | Traje Heroico Icônico do Meme da Internet |
| 7 | 100 | 5 | 20 | `mask_season_internet` | Olhos com Lasers Brilhantes do Meme da Internet |
| 8 | 110 | 12 | 20 | `improvement_mask_epic` | improvement_mask_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_internet` | Acompanhante “Mya” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_internet"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_internet"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_internet"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_internet"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_internet"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_internet"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_internet"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_internet"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_internet"
    }
  ]
}
```

## Caça às Toupeiras: Reino Estelar

Identificador: `empire_of_the_stars_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_empire_of_the_stars` | Tornozeleira de Ouro d[m:o Defensor / f:a Defensora] Estelar |
| 1 | 15 | 5 | 20 | `belt_season_empire_of_the_stars` | Cinto Sideral d[m:o Defensor / f:a Defensora] Estelar |
| 2 | 25 | 5 | 20 | `weapon_season_empire_of_the_stars` | Arco Celeste com Filigrana d[m:o Defensor / f:a Defensora] Estelar |
| 3 | 40 | 5 | 20 | `gadget_season_empire_of_the_stars` | Bússola Sideral Autolocalizadora d[m:o Defensor / f:a Defensora] Estelar |
| 4 | 55 | 5 | 20 | `missiles_season_empire_of_the_stars` | Flechas Cósmicas d[m:o Defensor / f:a Defensora] Estelar |
| 5 | 70 | 5 | 20 | `cape_season_empire_of_the_stars` | Aljava de Flechas de Nebulosa d[m:o Defensor / f:a Defensora] Estelar |
| 6 | 85 | 5 | 20 | `suit_season_empire_of_the_stars` | Túnica Mística d[m:o Defensor / f:a Defensora] Estelar |
| 7 | 100 | 5 | 20 | `mask_season_empire_of_the_stars` | Auréola Flutuante d[m:o Defensor / f:a Defensora] Estelar |
| 8 | 110 | 12 | 20 | `improvement_suit_epic` | improvement_suit_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_empire_of_the_stars` | Acompanhante “Nova” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_empire_of_the_stars"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_empire_of_the_stars"
    }
  ]
}
```

## Caça às Toupeiras: Pós-Apocalipse

Identificador: `post_apocalypse_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_post_apocalypse` | Botas de Inverno Revestidas do Pós-Apocalipse |
| 1 | 15 | 5 | 20 | `belt_season_post_apocalypse` | Pochete de Couro Modificada do Pós-Apocalipse |
| 2 | 25 | 5 | 20 | `weapon_season_post_apocalypse` | Manopla Caseira com Garras do Pós-Apocalipse |
| 3 | 40 | 5 | 20 | `gadget_season_post_apocalypse` | Lata de Sardinha Não Perecível do Pós-Apocalipse |
| 4 | 55 | 5 | 20 | `missiles_season_post_apocalypse` | Projétil Indestrutível Envolto por Lendas do Pós-Apocalipse |
| 5 | 70 | 5 | 20 | `cape_season_post_apocalypse` | Aljava Útil Coletora de Entulho do Pós-Apocalipse |
| 6 | 85 | 5 | 20 | `suit_season_post_apocalypse` | Traje de Sobrevivência Conectado com a Natureza do Pós-Apocalipse |
| 7 | 100 | 5 | 20 | `mask_season_post_apocalypse` | Galhada de Resina Sintética Altamente Tecnológica e Decorada do Pós-Apocalipse |
| 8 | 110 | 12 | 20 | `improvement_cape_epic` | improvement_cape_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_post_apocalypse` | Acompanhante “SCRAP-E” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_post_apocalypse"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_post_apocalypse"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_post_apocalypse"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_post_apocalypse"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_post_apocalypse"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_post_apocalypse"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_post_apocalypse"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_post_apocalypse"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_post_apocalypse"
    }
  ]
}
```

## Caça às Toupeiras: Caldeirão da Bruxa

Identificador: `witchcraft_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_witchcraft` | Calçados mágicos feitos à mão do Bruxo |
| 1 | 15 | 5 | 20 | `belt_season_witchcraft` | Grimório flutuante do Bruxo |
| 2 | 25 | 5 | 20 | `weapon_season_witchcraft` | Punhal recém-afiado do Bruxo |
| 3 | 40 | 5 | 20 | `gadget_season_witchcraft` | Caldeirão borbulhante do Bruxo |
| 4 | 55 | 5 | 20 | `missiles_season_witchcraft` | Sapo verde do Bruxo |
| 5 | 70 | 5 | 20 | `cape_season_witchcraft` | Abóboras assustadoras enfeitiçadas do Bruxo |
| 6 | 85 | 5 | 20 | `suit_season_witchcraft` | Traje mágico do Bruxo |
| 7 | 100 | 5 | 20 | `mask_season_witchcraft` | Grande chapéu mágico do Bruxo |
| 8 | 110 | 12 | 20 | `improvement_sidekick_epic` | improvement_sidekick_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_witchcraft` | Acompanhante “Mawu” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_witchcraft"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_witchcraft"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_witchcraft"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_witchcraft"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_witchcraft"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_witchcraft"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_witchcraft"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_witchcraft"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_sidekick_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_witchcraft"
    }
  ]
}
```

## Caça às Toupeiras: Deuses Gregos

Identificador: `greek_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_greek` | Sandália de Couro com Acabamento em Ouro d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 1 | 15 | 5 | 20 | `belt_season_greek` | Cinto Largo de Bronze d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 2 | 25 | 5 | 20 | `weapon_season_greek` | Caduceu Dourado d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 3 | 40 | 5 | 20 | `gadget_season_greek` | Baú de Pandora Muito Bem Trancado d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 4 | 55 | 5 | 20 | `missiles_season_greek` | Disco Dourado d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 5 | 70 | 5 | 20 | `cape_season_greek` | Asas Imponentes d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 6 | 85 | 5 | 20 | `suit_season_greek` | Armadura de Batalha com Decorações Nobres d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 7 | 100 | 5 | 20 | `mask_season_greek` | Elmo de Batalha Folheado em Ouro com Crista Alta d[m:o Guerreiro Helênico / f:a Guerreira Helênica] |
| 8 | 110 | 12 | 20 | `improvement_gadget_epic` | improvement_gadget_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_greek` | Acompanhante “Hyldi” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_greek"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_greek"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_greek"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_greek"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_greek"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_greek"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_greek"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_greek"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_gadget_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_greek"
    }
  ]
}
```

## vampire_treasure

Identificador: `vampire_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_vampire` | Botas de Couro Pesadas d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 1 | 15 | 5 | 20 | `belt_season_vampire` | Cinto Moderno com Fivela de Ouro d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 2 | 25 | 5 | 20 | `weapon_season_vampire` | Garras de Silicone Afiadas d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 3 | 40 | 5 | 20 | `gadget_season_vampire` | Cálice Cheio de Suco de Beterraba d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 4 | 55 | 5 | 20 | `missiles_season_vampire` | Projétil de Alho Fresco d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 5 | 70 | 5 | 20 | `cape_season_vampire` | Lua Forte Vermelho-escura d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 6 | 85 | 5 | 20 | `suit_season_vampire` | Capa de Couro Graciosa d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 7 | 100 | 5 | 20 | `mask_season_vampire` | Óculos com Filtro Solar d[m:o Vampiro Destemido / f:a Vampira Destemida] |
| 8 | 110 | 12 | 20 | `improvement_belt_epic` | improvement_belt_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_vampire` | Acompanhante “Conde Gátula” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_vampire"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_vampire"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_vampire"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_vampire"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_vampire"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_vampire"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_vampire"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_vampire"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_belt_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_vampire"
    }
  ]
}
```

## Caça às Toupeiras: Besouro

Identificador: `beetle_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_beetle` | Botas Antigravidade do Besouro Dinâmico |
| 1 | 15 | 5 | 20 | `belt_season_beetle` | Armadura de Quadril Protetora do Besouro Dinâmico |
| 2 | 25 | 5 | 20 | `weapon_season_beetle` | Canhão Cospe-gosma do Besouro Dinâmico |
| 3 | 40 | 5 | 20 | `gadget_season_beetle` | Alicate Indestrutível do Besouro Dinâmico |
| 4 | 55 | 5 | 20 | `missiles_season_beetle` | Inseto Preservado em Âmbar do Besouro Dinâmico |
| 5 | 70 | 5 | 20 | `cape_season_beetle` | Asas de Alta Velocidade do Besouro Dinâmico |
| 6 | 85 | 5 | 20 | `suit_season_beetle` | Armadura de Quitina Estável do Besouro Dinâmico |
| 7 | 100 | 5 | 20 | `mask_season_beetle` | Capacete Blindado do Besouro Dinâmico |
| 8 | 110 | 12 | 20 | `improvement_boots_epic` | improvement_boots_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_beetle` | Acompanhante “Herby” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_beetle"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_beetle"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_beetle"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_beetle"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_beetle"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_beetle"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_beetle"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_beetle"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_boots_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_beetle"
    }
  ]
}
```

## Caça às Toupeiras: Reino da Lua

Identificador: `empire_of_the_moon_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_empire_of_the_moon` | Bota de Pano Baixa d[m:o Mago / f:a Maga] da Lua |
| 1 | 15 | 5 | 20 | `belt_season_empire_of_the_moon` | Bolsa Caseira de Componentes Mágicos d[m:o Mago / f:a Maga] da Lua |
| 2 | 25 | 5 | 20 | `weapon_season_empire_of_the_moon` | Foice Lunar Luminosa d[m:o Mago / f:a Maga] da Lua |
| 3 | 40 | 5 | 20 | `gadget_season_empire_of_the_moon` | Elixir Misterioso d[m:o Mago / f:a Maga] da Lua |
| 4 | 55 | 5 | 20 | `missiles_season_empire_of_the_moon` | Grimório Voador d[m:o Mago / f:a Maga] da Lua |
| 5 | 70 | 5 | 20 | `cape_season_empire_of_the_moon` | Capa Constantemente Tremulante d[m:o Mago / f:a Maga] da Lua |
| 6 | 85 | 5 | 20 | `suit_season_empire_of_the_moon` | Nobre Traje de Batalha d[m:o Mago / f:a Maga] da Lua |
| 7 | 100 | 5 | 20 | `mask_season_empire_of_the_moon` | Coroa Encantada d[m:o Mago / f:a Maga] da Lua |
| 8 | 110 | 12 | 20 | `improvement_weapon_epic` | improvement_weapon_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_empire_of_the_moon` | Acompanhante “Arquimedes” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_empire_of_the_moon"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_empire_of_the_moon"
    }
  ]
}
```

## Caça às Toupeiras: Reino dos Mortos

Identificador: `day_of_the_dead_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_day_of_the_dead` | Botas [m:com Polainas Customizadas / f:Ornamentadas] da Memória dos Mortos |
| 1 | 15 | 5 | 20 | `belt_season_day_of_the_dead` | Cinto de Couro com Caveira Entalhada da Memória dos Mortos |
| 2 | 25 | 5 | 20 | `weapon_season_day_of_the_dead` | Espada Ancestral Antiga da Memória dos Mortos |
| 3 | 40 | 5 | 20 | `gadget_season_day_of_the_dead` | Doces Tradicionais da Memória dos Mortos |
| 4 | 55 | 5 | 20 | `missiles_season_day_of_the_dead` | Caveira Decorada da Memória dos Mortos |
| 5 | 70 | 5 | 20 | `cape_season_day_of_the_dead` | Capa de Ombro Costurada à Mão da Memória dos Mortos |
| 6 | 85 | 5 | 20 | `suit_season_day_of_the_dead` | [m:Terno de Alfaiataria / f:Vestido Colorido] da Memória dos Mortos |
| 7 | 100 | 5 | 20 | `mask_season_day_of_the_dead` | [m:Sombrero Chamativo / f:Grinalda de Flores Floridas] da Memória dos Mortos |
| 8 | 110 | 12 | 20 | `improvement_mask_epic` | improvement_mask_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_day_of_the_dead` | Acompanhante “Calavera” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_day_of_the_dead"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_day_of_the_dead"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_day_of_the_dead"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_day_of_the_dead"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_day_of_the_dead"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_day_of_the_dead"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_day_of_the_dead"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_day_of_the_dead"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_day_of_the_dead"
    }
  ]
}
```

## Caça às Toupeiras: Reino Maia

Identificador: `mayan_empire_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_mayan_empire` | Sandálias de Combate em Metal Fundido da Cultura Avançada |
| 1 | 15 | 5 | 20 | `belt_season_mayan_empire` | Cinto Elegantemente Decorado da Cultura Avançada |
| 2 | 25 | 5 | 20 | `weapon_season_mayan_empire` | Lâmina de Diamante Nobre com Cabo de Ouro da Cultura Avançada |
| 3 | 40 | 5 | 20 | `gadget_season_mayan_empire` | Relógio de Sol Portátil da Cultura Avançada |
| 4 | 55 | 5 | 20 | `missiles_season_mayan_empire` | Espiga de Milho Saborosa da Cultura Avançada |
| 5 | 70 | 5 | 20 | `cape_season_mayan_empire` | Manto de Seda Feito à Mão da Cultura Avançada |
| 6 | 85 | 5 | 20 | `suit_season_mayan_empire` | Armadura de Batalha Esculpida da Cultura Avançada |
| 7 | 100 | 5 | 20 | `mask_season_mayan_empire` | Adorno de Cabeça Esvoaçante da Cultura Avançada |
| 8 | 110 | 12 | 20 | `improvement_suit_epic` | improvement_suit_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_mayan_empire` | Acompanhante “Yax” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_mayan_empire"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_mayan_empire"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_mayan_empire"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_mayan_empire"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_mayan_empire"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_mayan_empire"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_mayan_empire"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_mayan_empire"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_mayan_empire"
    }
  ]
}
```

## Caça às Toupeiras: Petiscos de Sushi

Identificador: `heroic_feast_appetizer_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_heroic_feast_appetizer` | Tamancos de Madeira Artesanais [m:do / f:da] Sushimaster |
| 1 | 15 | 5 | 20 | `belt_season_heroic_feast_appetizer` | Corda Resistente [m:do / f:da] Sushimaster |
| 2 | 25 | 5 | 20 | `weapon_season_heroic_feast_appetizer` | Pistola de Wasabi Carregada [m:do / f:da] Sushimaster |
| 3 | 40 | 5 | 20 | `gadget_season_heroic_feast_appetizer` | Molho de Soja Decorativo [m:do / f:da] Sushimaster |
| 4 | 55 | 5 | 20 | `missiles_season_heroic_feast_appetizer` | Bolinhas de Arroz Grudentas e Saborosas [m:do / f:da] Sushimaster |
| 5 | 70 | 5 | 20 | `cape_season_heroic_feast_appetizer` | Irresistível Carrossel de Sushi [m:do / f:da] Sushimaster |
| 6 | 85 | 5 | 20 | `suit_season_heroic_feast_appetizer` | Traje Clássico [m:do / f:da] Sushimaster |
| 7 | 100 | 5 | 20 | `mask_season_heroic_feast_appetizer` | Faixa de Cabelo Super Merecida [m:do / f:da] Sushimaster |
| 8 | 110 | 12 | 20 | `improvement_cape_epic` | improvement_cape_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_heroic_feast_appetizer` | Acompanhante “Nori” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_heroic_feast_appetizer"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_cape_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_heroic_feast_appetizer"
    }
  ]
}
```

## Caça às Toupeiras: Mundos Infinitos

Identificador: `distant_worlds_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_distant_worlds` | Botas de Exploração Antibacterianas d[m:o Astrobiólogo / f:a Astrobióloga] |
| 1 | 15 | 5 | 20 | `belt_season_distant_worlds` | Cinto Científico com Estojo de Amostras d[m:o Astrobiólogo / f:a Astrobióloga] |
| 2 | 25 | 5 | 20 | `weapon_season_distant_worlds` | Coletor de Amostras com Trava Biométrica d[m:o Astrobiólogo / f:a Astrobióloga] |
| 3 | 40 | 5 | 20 | `gadget_season_distant_worlds` | Incubador de Esporos Botânicos d[m:o Astrobiólogo / f:a Astrobióloga] |
| 4 | 55 | 5 | 20 | `missiles_season_distant_worlds` | Mini Biosferas Super Práticas d[m:o Astrobiólogo / f:a Astrobióloga] |
| 5 | 70 | 5 | 20 | `cape_season_distant_worlds` | Tanque de Coleta de Amostras Hermeticamente Selado d[m:o Astrobiólogo / f:a Astrobióloga] |
| 6 | 85 | 5 | 20 | `suit_season_distant_worlds` | Jaqueta de Exploração Isolada e Estilosa d[m:o Astrobiólogo / f:a Astrobióloga] |
| 7 | 100 | 5 | 20 | `mask_season_distant_worlds` | Visor de Proteção com Biossensores d[m:o Astrobiólogo / f:a Astrobióloga] |
| 8 | 110 | 12 | 20 | `improvement_weapon_epic` | improvement_weapon_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_distant_worlds` | Acompanhante “Glubb” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_distant_worlds"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_distant_worlds"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_distant_worlds"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_distant_worlds"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_distant_worlds"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_distant_worlds"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_distant_worlds"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_distant_worlds"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_weapon_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_distant_worlds"
    }
  ]
}
```

## Caça às Toupeiras: Louva-a-Deus

Identificador: `armoured_beasts_1_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_armoured_beasts_1` | Botas de absorção acústica [m:do Caçador / f:da Caçadora] à Espreita |
| 1 | 15 | 5 | 20 | `belt_season_armoured_beasts_1` | Pochete blindada [m:do Caçador / f:da Caçadora] à Espreita |
| 2 | 25 | 5 | 20 | `weapon_season_armoured_beasts_1` | Spray natural repelente de insetos [m:do Caçador / f:da Caçadora] à Espreita |
| 3 | 40 | 5 | 20 | `gadget_season_armoured_beasts_1` | Lentes de contato multifacetadas [m:do Caçador / f:da Caçadora] à Espreita |
| 4 | 55 | 5 | 20 | `missiles_season_armoured_beasts_1` | Drones de nano-besouros hipervelozes [m:do Caçador / f:da Caçadora] à Espreita |
| 5 | 70 | 5 | 20 | `cape_season_armoured_beasts_1` | Pernas de escalada úteis [m:do Caçador / f:da Caçadora] à Espreita |
| 6 | 85 | 5 | 20 | `suit_season_armoured_beasts_1` | Traje blindado [m:do Caçador / f:da Caçadora] à Espreita |
| 7 | 100 | 5 | 20 | `mask_season_armoured_beasts_1` | Máscara com sensores [m:do Caçador / f:da Caçadora] à Espreita |
| 8 | 110 | 12 | 20 | `improvement_mask_epic` | improvement_mask_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_armoured_beasts_1` | Acompanhante “Manti” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_armoured_beasts_1"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_mask_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_armoured_beasts_1"
    }
  ]
}
```

## Caça às Toupeiras: Corrupção

Identificador: `corruption_treasure`. Localização: `standard_balancing`.

| Índice | Toupeiras necessárias | Tipo | Fator | Referência | Nome localizado |
| --- | --- | --- | --- | --- | --- |
| 0 | 5 | 5 | 20 | `boots_season_corruption` | Bota Pantaneira Exclusiva d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 1 | 15 | 5 | 20 | `belt_season_corruption` | Cinto Autoaderente d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 2 | 25 | 5 | 20 | `weapon_season_corruption` | Lâmina de Espada Espiral d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 3 | 40 | 5 | 20 | `gadget_season_corruption` | Cálice com Borda de Ouro d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 4 | 55 | 5 | 20 | `missiles_season_corruption` | Dados do Destino d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 5 | 70 | 5 | 20 | `cape_season_corruption` | Asas Majestosas d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 6 | 85 | 5 | 20 | `suit_season_corruption` | Traje de Batalha Decorado com Olhos d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 7 | 100 | 5 | 20 | `mask_season_corruption` | Capacete com Tentáculos d[m:o Aniquilador / f:a Aniquiladora] de Corrupção |
| 8 | 110 | 12 | 20 | `improvement_suit_epic` | improvement_suit_epic |
| 9 | 120 | 5 | 20 | `sidekick_season_corruption` | Acompanhante “Lu” |

### Definição bruta

```json
{
  "location": "standard_balancing",
  "reward_index": [
    {
      "required_token_amount": 5,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "boots_season_corruption"
    },
    {
      "required_token_amount": 15,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "belt_season_corruption"
    },
    {
      "required_token_amount": 25,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "weapon_season_corruption"
    },
    {
      "required_token_amount": 40,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "gadget_season_corruption"
    },
    {
      "required_token_amount": 55,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "missiles_season_corruption"
    },
    {
      "required_token_amount": 70,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "cape_season_corruption"
    },
    {
      "required_token_amount": 85,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "suit_season_corruption"
    },
    {
      "required_token_amount": 100,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "mask_season_corruption"
    },
    {
      "required_token_amount": 110,
      "reward_type": 12,
      "reward_factor": 20,
      "reward_reference": "improvement_suit_epic"
    },
    {
      "required_token_amount": 120,
      "reward_type": 5,
      "reward_factor": 20,
      "reward_reference": "sidekick_season_corruption"
    }
  ]
}
```

## Protocolo do cliente

| Ação | Parâmetros |
| --- | --- |
| `assignTreasureEvent` | `treasure_event_id`: identificador textual do template |
| `createNextTreasureEventLevel` | Sem parâmetros específicos |
| `openTreasureCell` | `level`, `x`, `y`, `premium` |
| `collectTreasureCellReward` | `level`, `x`, `y`, `discard_item` |
| `claimTreasureEventReward` | `reward_index`, `discard_item` |
| `claimFreeTreasureRevealItems` | Sem parâmetros específicos |
| `resetTreasureEventProgress` | Sem parâmetros específicos |
| `skipTreasureEventLevelUnlock` | `level`, `premium_cost`; servidor recalcula custo |

O convite não contém `id`. A participação contém ID, estado, datas, pás, toupeiras e três strings JSON: `rewards`, `collected_rewards`, `levels`. O tabuleiro público contém apenas células abertas. Os detalhes de persistência, entrega e validação estão no [documento de implementação](event_treasures_implementacao.md).

## Famílias associadas

A Caça às Toupeiras partilha temas com temporadas e prémios de equipamento/pets, mas não partilha os contadores dos eventos de coleção. Pode coexistir com `event_quests` e casino. As fontes de pets incluem referências `event_treasure_<template>`. A entrega de um pet usa a coleção de pets e não exige uma vaga da mochila.
