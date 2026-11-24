# Conjuntos de itens (sets)

Registo de TODOS os conjuntos conhecidos pelo cliente html5_257 e usados pelo
servidor. Fonte: `public/assets/data/constants_json.data` (chave
`item_set_templates`, espelho local do CDN oficial), extraído a 2026-08-25 para
`server-laravel/data/item_sets.php`. Os nomes vêm dos textos oficiais
`assets/i18n/pt_BR/text.data`.

Como se lê: cada limiar indica o número de peças EQUIPADAS do mesmo conjunto a
partir do qual o bónus fica ativo (acumulativo: com 5 peças estão ativos os
limiares 2, 3, 4 e 5). O % aplica-se à HABILIDADE BASE do herói — ver
[conjunto_set_bonus.md](conjunto_set_bonus.md).

Tipos de atributo (mapeamento oficial): **Vigor** = stamina · **Força/Dano** =
strength · **Cérebro** = critical rating · **Intuição** = dodge rating ·
**Todos os atributos** = as quatro anteriores · **Sorte no Cassino** = casino
luck (sem efeito em combate; não implementado no servidor).

| Identificador | Nome (pt_BR) | Peças | Bónus por limiar |
|---|---|---|---|
| `baywatch_set` | Conjunto de heróis Baywatch | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `birthday_event_set` | Conjunto heroico da Festa de Aniversário | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `black_friday_set` | Conjunto Heroico do Caçador Misterioso | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `bounties_of_seven_seas_tower_set` | Conjunto Heroico [m:do Capitão Destemido|f:da Capitã Destemida] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `bounties_of_seven_seas_tower_weapon_set` | Conjunto de Armas [m:do Capitão Destemido|f:da Capitã Destemida] | 2 | 2 peças → +7% Cérebro (crítico) |
| `bttf_set` | Conjunto de herói do viajante no tempo | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `captain_planet_set` | Conjunto [m:do Herói|f:da Heroína] de Heroísmo | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `captain_planet_weapon_set` | Conjunto de Armas Chamativo NARF de Heroísmo | 2 | 2 peças → +7% Cérebro (crítico) |
| `carnival_casino_set1` | Conjunto heroico de Cospobre | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `carnival_set1` | Conjunto de batalha a ar comprimido da chuva de confete | 2 | 2 peças → +7% Força/Dano |
| `carnival_set2` | Conjunto de carnaval colorido do folião doido | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `carnival_set3` | Conjunto heroico do [m:Bobo Mascarado|f:Boba Mascarada] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `carnival_set4` | Conjunto Heroico do Carnaval | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `casino_luck_set` | Conjunto heroico do Toque de Sorte | 5 | 2 peças → +1% Sorte no Cassino · 3 peças → +1% Sorte no Cassino · 4 peças → +1% Sorte no Cassino · 5 peças → +2% Sorte no Cassino |
| `custom_set1` | Conjunto genérico de herói | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `custom_set10` | Conjunto Heroico de Poder Brilhante | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set11` | Conjunto Heroico de Poder Flamejante | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set12` | Conjunto Heroico de Poder Natural | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set13` | Conjunto Heroico de Poder Sombrio | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set14` | Conjunto Heroico do Poder da Maçã Verde | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set15` | Conjunto Heroico do Poder Florido | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set16` | Conjunto Heroico do Poder de Jade | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set17` | Conjunto Heroico do Poder Náutico | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set18` | Conjunto Heroico do Poder Escuro como a Tinta | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set19` | Conjunto Heroico do Poder Ensolarado | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set2` | Conjunto do poder supremo | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `custom_set20` | Conjunto Heroico do Poder Nebuloso | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set21` | Conjunto Heroico do Poder Laranja | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set3` | Conjunto de herói moderno | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `custom_set4` | Conjunto de herói cheio de clichês | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `custom_set5` | Conjunto de herói retrô | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `custom_set6` | Conjunto do poder supremo do acerto de sorte | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `custom_set8` | Conjunto do poder supremo do desvio | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `custom_set9` | Conjunto Heroico de Poder Alegre | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `darkwing_duck_set` | Conjunto Heroico do Caçador Criminoso Atrapalhado | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `darkwing_duck_weapon_set` | Conjunto de Armas do Caçador Criminoso Atrapalhado | 2 | 2 peças → +7% Cérebro (crítico) |
| `dungeon_hardmode` | Conjunto Heroico [m:do Guerreiro Solitário Apocalíptico|f:da Guerreira Solitária Apocalíptica] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `earth_tower_set` | Conjunto Heroico da Alma Telúrica | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `earth_tower_weapon_set` | Conjunto de Armas da Alma Telúrica | 2 | 2 peças → +7% Cérebro (crítico) |
| `easter_set1` | Conjunto de arremesso de ovos da surpresa bem-sucedida | 2 | 2 peças → +7% Força/Dano |
| `easter_set2` | Conjunto Heroico dos Pulos | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `easter_set3` | Conjunto Heroico da Gargalhada Alegre | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `flashgordon_set` | Set de herói Flash Gordon | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `frogman_set1` | Conjunto anfíbio para herói | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `fyocd_set` | Set de tricô DIY bandeiras nacionais | 2 | 2 peças → +7% Cérebro (crítico) |
| `ghostbusters_set` | Conjunto de herói Ghostbusters | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `global_pvp_set` | Conjunto Heroico [m:do Guerreiro|f:da Guerreira] do Mosh Pit Galático | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `go_crazy_casino_set` | Conjunto Heroico [m:do Campeão|f:da Campeã] de Poker | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `grind_it_out_set` | Conjunto de Herói para Treino Pesado | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `halloween_set1` | Conjunto de Herói do Bobo da Corte Medonho | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `halloween_set2` | Conjunto de Armas Gosmento do Gosto Residual Gosmento | 2 | 2 peças → +7% Força/Dano |
| `halloween_set3` | Conjunto Heroico do Grito de Halloween | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `heroic_design1` | Conjunto Heroico de Grife by Hugo Ross | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `heroic_feast_appetizer_weapon_set` | Conjunto de Armas do Ninja do Sushi | 2 | 2 peças → +7% Cérebro (crítico) |
| `hideout_blacksmith_1` | Conjunto Heroico [m:do|f:da] Mestre da Forja | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `ironman_set1` | Conjunto irônico para herói | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `item_casino_set_1` | Conjunto Heroico do Crupiê Insubornável | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `jumpstartserver_int_2_set` | Conjunto Heroico d[m:o Campeão|f:a Campeã] da Corrida de Rolimã | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `jumpstartserver_int_set` | Conjunto Heroico do [m:Campeão|f:Campeã] dos Gladiadores | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `ld_drwhyfi_set` | Conjunto Heroico do Sinal Sem Fio | 7 | 2 peças → +7% Força/Dano · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Intuição (esquiva) · 6 peças → +1% Todos os atributos · 7 peças → +2% Todos os atributos |
| `ld_halloween_set` | Conjunto Heroico do Jogo de Sombras Amaldiçoado | 7 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) · 6 peças → +1% Todos os atributos · 7 peças → +2% Todos os atributos |
| `ld_inconvenience_set` | Conjunto Heroico da Inconveniência | 7 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) · 6 peças → +1% Todos os atributos · 7 peças → +2% Todos os atributos |
| `ld_xmas_set` | Conjunto Heroico do Quebra-nozes | 7 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) · 6 peças → +1% Todos os atributos · 7 peças → +2% Todos os atributos |
| `leagueset_bronze` | Conjunto de Super-heróis da Liga de Bronze | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `leagueset_champion` | Conjunto transformável da Liga dos Campeões | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `leagueset_custom1` | Set de herói antimagnético das habilidades extraordinárias | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `leagueset_custom2` | Set de herói funcional do lutador justo | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `leagueset_gold` | Conjunto de Super-heróis Justos da Liga de Ouro | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `leagueset_silver` | Conjunto de morcegos aerodinâmico da Liga de Prata | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `leagueset_superhero` | Conjunto de Super-herói da Liga de Super-heróis | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `lumberjack_set_1` | Conjunto Heroico do Lenhador Garantidamente Autêntico | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `mib_set` | Conjunto de herói Men in Black | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `midsummer_set` | Conjunto Heroico do Festival do Solstício de Verão | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `new_year_set` | Conjunto heroico do Amuleto da Sorte | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `octoberfest_set` | Conjunto Heroico [m:do Herói|f:da Heroína] da Barraca de Festa | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `phantom_set` | Conjunto de herói fantasma | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `pirate_day_set` | Conjunto Heroico [m:do|f:da] Pirata dos Sete Mares | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `sci_fi_week_1` | Conjunto heroico [m:do|f:da] nerd de de ficção científica | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `sci_fi_week_2` | Conjunto heroico de cabeça para baixo do nerd invertido | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `sci_fi_week_tower_set` | Conjunto heroico d[m:o|f:a] Cosplayer de Sci-Fi | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `sci_fi_week_tower_weapon_set` | Conjunto de Armas [m:do|f:da] Cosplayer de Ficção Científica | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_air_set` | Conjunto heroico da Corrente de Ar | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `season_air_weapon_set` | Conjunto de Armas da Corrente de Ar | 2 | 2 peças → +7% Intuição (esquiva) |
| `season_aliens_set` | Conjunto Heroico do Terror Alienígena | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_aliens_weapon_set` | Conjunto de Armas do Terror Alienígena | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_ant_set` | Conjunto Heroico da Formiga | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_ant_weapon_set` | Conjunto de Armas da Formiga | 2 | 2 peças → +7% Força/Dano |
| `season_apocalypse_weapon_set` | Conjunto Heroico do Apocalipse | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_arc_dystopia_set` | Conjunto Heroico d[m:o Salvador Distópico|f:a Salvadora Distópica] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_arc_primal_set` | Conjunto Heroico d[m:o|f:a] Caçador[m:|f:a] Primordial | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_arc_utopia_set` | Conjunto Heroico [m:do Caçador|f:da Caçadora] de Alienígenas do Futuro | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_arcade_set` | Conjunto Heroico [m:do|f:da] Gamer Retrô Futurista | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_arcade_weapon_set` | Conjunto de Armas [m:do|f:da] Gamer Retrô Futurista | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_arctic_exploration_set` | Conjunto Heroico d[m:o Defensor|f:a Defensora] do Ártico | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_arctic_exploration_weapon_set` | Conjunto de Armas d[m:o Defensor|f:a Defensora] do Ártico | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_armoured_beasts_1_set` | Conjunto Heroico [m:do Caçador|f:da Caçadora] à Espreita | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `season_armoured_beasts_1_weapon_set` | Conjunto de Armas [m:do Caçador|f:da Caçadora] à Espreita | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_armoured_beasts_2_set` | Conjunto heroico [m:do Guerreiro Chifrudo|f:da Guerreira Chifruda] | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_armoured_beasts_2_weapon_set` | Conjunto de armas [m:do Guerreiro Chifrudo|f:da Guerreira Chifruda] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_armoured_beasts_3_set` | Conjunto Heroico do Colosso do Oceano | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_armoured_beasts_3_weapon_set` | Conjunto de Armas do Colosso do Oceano | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_bbq_set` | Conjunto Heroico [m:do Mestre Churrasqueiro|f:da Mestra Churrasqueira] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_bbq_weapon_set` | Conjunto de Armas [m:do Mestre Churrasqueiro|f:da Mestra Churrasqueira] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_bear_set` | Conjunto Heroico de Força do Urso | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_bear_weapon_set` | Conjunto de Armas de Força do Urso | 2 | 2 peças → +7% Força/Dano |
| `season_beetle_set` | Conjunto Heroico do Besouro Dinâmico | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_beetle_weapon_set` | Conjunto de Armas do Besouro Dinâmico | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_boar_set` | Conjunto Heroico do Javali Robusto | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_boar_weapon_set` | Conjunto de Armas do Javali Robusto | 2 | 2 peças → +7% Vigor |
| `season_bounties_of_seven_seas_weapon_set` | Conjunto de Armas do Corsário Fantasma | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_bounty_hunter_set` | Conjunto Heroico d[m:o Caçador|f:a Caçadora] de Recompensas | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_bounty_hunter_weapon_set` | Conjunto de Armas d[m:o Caçador|f:a Caçadora] de Recompensas | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_brawling_spirit_set` | Conjunto Heroico da Futura Lenda de Luta Livre | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_brawling_spirit_weapon_set` | Conjunto de Armas da Futura Lenda de Luta Livre | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_buffalo_set` | Conjunto Heroico do Búfalo | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_buffalo_weapon_set` | Conjunto de armas do Búfalo | 2 | 2 peças → +7% Vigor |
| `season_corruption_set` | Conjunto Heroico d[m:o Aniquilador|f:a Aniquiladora] de Corrupção | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_corruption_weapon_set` | Conjunto de Armas d[m:o Aniquilador|f:a Aniquiladora] de Corrupção | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_country_life_set` | Conjunto Heroico [m:do Fazendeiro Moderno|f:da Fazendeira Moderna] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_country_life_weapon_set` | Conjunto de Armas [m:do Fazendeiro Moderno|f:da Fazendeira Moderna] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_creativity_set` | Conjunto Heroico do Gênio Criativo | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_creativity_weapon_set` | Conjunto de Armas do Gênio Criativo | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_cyberpunk_set` | Conjunto Heroico [m:do|f:da] Ciberninja | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_cyberpunk_weapon_set` | Conjunto de Armas [m:do|f:da] Ciberninja | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_dark_ages_set` | Conjunto Heroico [m:do Cavaleiro Honroso|f:da Cavaleira Honrosa] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_dark_ages_weapon_set` | Conjunto de Armas [m:do Cavaleiro Honroso|f:da Cavaleira Honrosa] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_day_of_the_dead_set` | Conjunto Heroico da Memória dos Mortos | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_day_of_the_dead_weapon_set` | Conjunto de Armas da Memória dos Mortos | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_dinosaurs_set` | Conjunto Heroico do Triceratope | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_dinosaurs_weapon_set` | Conjunto de Armas do Triceratope | 2 | 2 peças → +7% Força/Dano |
| `season_distant_worlds_set` | Conjunto Heroico d[m:o Astrobiólogo|f:a Astrobióloga] | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_distant_worlds_weapon_set` | Conjunto de Armas d[m:o Astrobiólogo|f:a Astrobióloga] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_earth_set` | Conjunto heroico do prospector | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `season_earth_weapon_set` | Conjunto de armas do prospector | 2 | 2 peças → +7% Intuição (esquiva) |
| `season_egypt_set` | Conjunto Heroico do Chacal Mítico | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_egypt_weapon_set` | Conjunto de Armas do Chacal Mítico | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_elk_set` | Conjunto heroico do Alce Migratório | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_elk_weapon_set` | Conjunto de Armas do Alce Migratório | 2 | 2 peças → +7% Vigor |
| `season_empire_of_the_moon_set` | Conjunto Heroico d[m:o Mago|f:a Maga] da Lua | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_empire_of_the_moon_weapon_set` | Conjunto de Armas d[m:o Mago|f:a Maga] da Lua | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_empire_of_the_stars_set` | Conjunto Heroico d[m:o Defensor|f:a Defensora] Estelar | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_empire_of_the_stars_weapon_set` | Conjunto de Armas d[m:o Defensor|f:a Defensora] Estelar | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_empire_of_the_sun_set` | Conjunto Heroico d[m:o Templário|f:a Templária] do Sol | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_empire_of_the_sun_weapon_set` | Conjunto de Armas d[m:o Templário|f:a Templária] do Sol | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_event_apocalypse_set` | Conjunto de Armas do Apocalipse | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_event_arctic_exploration_set` | Conjunto Heroico d[m:o|f:a] Líder da Expedição | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_blooming_nature_set` | Conjunto Heroico dos Dedos Verdes | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_event_bounties_of_seven_seas_set` | Conjunto Heroico do Corsário Fantasma | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_event_doom_and_darkness_set` | Conjunto Heroico do Dungeon Master | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_dystopia_set` | Conjunto Heroico d[m:o Defensor Distópico|f:a Defensora Distópica] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_exotic_places_set` | Conjunto Heroico do Reino Congelado Perdido | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_fantastic_adventures_set` | Conjunto Heroico d[m:o Aventureiro Fantástico|f:a Aventureira Fantástica] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_event_future_set` | Conjunto Heroico [m:do Guerreiro|f:da Guerreira] do Futuro | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_event_ice_kingdom_set` | Conjunto Heroico do Reino Mágico Congelado | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_mayan_empire_set` | Conjunto Heroico do Grande Chocoatl | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_mythical_gods_set` | Conjunto heroico d[m:o Acadêmico|f:a Acadêmica] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_event_scorching_heat_set` | Conjunto Heroico [m:do Aspirante a Gladiador|f:da Aspirante a Gladiadora] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_event_sun_moon_stars_set` | Conjunto Heroico d[m:o Piloto|f:a Pilota] de Rolimã | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_tech_set` | Conjunto Heroico do Administrador de VR | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_event_utopia_set` | Conjunto Heroico [m:do Exterminador|f:da Exterminadora] de Alienígenas | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_wild_west_set` | Conjunto Heroico [m:do Atirador Destemido|f:da Atiradora Destemida] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_event_zombies_set` | Conjunto de Armas d[m:o|f:a] Caça-Zumbis | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_fairytale_1_set` | Conjunto Heroico [m:do Domador|f:da Domadora] de Lobos | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_fairytale_1_weapon_set` | Conjunto de Armas [m:do Domador|f:da Domadora] de Lobos | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_fire_set` | Conjunto Heroico do Fogo | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_fire_weapon_set` | Conjunto de Armas do Fogo | 2 | 2 peças → +7% Força/Dano |
| `season_flower_power_set` | Conjunto Heroico [m:do Dançarino dos Sonhos Super Maneiro|f:da Dançarina dos Sonhos Super Maneira] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_flower_power_weapon_set` | Conjunto de Armas [m:do Dançarino dos Sonhos Super Maneiro|f:da Dançarina dos Sonhos Super Maneira] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_fox_set` | Conjunto Heroico da Raposa Esperta | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_fox_weapon_set` | Conjunto de Armas da Raposa Esperta | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_future_set` | Conjunto Heroico [m:do Herói|f:da Heroína] do Futuro | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_future_weapon_set` | Conjunto de Armas [m:do Herói|f:da Heroína] do Futuro | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_gangsta_1_set` | Conjunto Heroico do Roubo Hipercomplexo | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_gangsta_1_weapon_set` | Conjunto de Armas do Roubo Hipercomplexo | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_gangsta_2_set` | Conjunto Heroico [m:do|f:da] Detetive Particular | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_gangsta_2_weapon_set` | Conjunto de Armas [m:do|f:da] Detetive Particular | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_gangsta_3_set` | Conjunto Heroico [m:do Guardião|f:da Guardiã] da Lei | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_gangsta_3_weapon_set` | Conjunto de Armas [m:do Guardião|f:da Guardiã] da Lei | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_ghost_set` | Conjunto Heroico da Hora das Bruxas | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_ghost_weapon_set` | Conjunto de Armas da Hora das Bruxas | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_gods_1_set` | Conjunto Heroico da Aurora Divina | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_gods_1_weapon_set` | Conjunto de Armas da Aurora Divina | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_gods_2_set` | Conjunto Heroico da Deusa Felina do Deserto | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_gods_2_weapon_set` | Conjunto de Armas da Deusa Felina do Deserto | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_gods_3_set` | Conjunto Heroico [m:do Dançarino Cósmico|f:da Dançarina Cósmica] dos Mundos | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_gods_3_weapon_set` | Conjunto de Armas [m:do Dançarino Cósmico|f:da Dançarina Cósmica] dos Mundos | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_greek_set` | Conjunto heroico d[m:o Guerreiro Helênico|f:a Guerreira Helênica] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_greek_weapon_set` | Conjunto de armas d[m:o Guerreiro Helênico|f:a Guerreira Helênica] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_guardians_of_nature_set` | Conjunto Heroico da Raiz de Ferro | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_guardians_of_nature_weapon_set` | Conjunto de Armas da Raiz de Ferro | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_happiness_set` | Conjunto Heroico da Felicidade | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_happiness_weapon_set` | Conjunto de Armas da Felicidade | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_hawk_set` | Conjunto Heroico do Falcão | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_hawk_weapon_set` | Conjunto de Armas do Falcão | 2 | 2 peças → +7% Força/Dano |
| `season_heroic_feast_appetizer_set` | Conjunto Heroico d[m:o|f:a] Ninja do Sushi | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `season_heroic_feast_appetizer_weapon_set` | Conjunto de Armas d[m:o|f:a] Ninja do Sushi | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_heroic_feast_dessert_set` | Conjunto Heroico da Doce Sobremesa | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `season_heroic_feast_dessert_weapon_set` | Conjunto de Armas da Doce Sobremesa | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_heroic_feast_main_course_set` | Conjunto Heroico da Lenda do Fast Food | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `season_heroic_feast_main_course_weapon_set` | Conjunto de Armas da Lenda do Fast Food | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_holy_light_set` | Conjunto Heroico das Cruzadas da Luz | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_holy_light_weapon_set` | Conjunto de Armas das Cruzadas da Luz | 2 | 2 peças → +7% Força/Dano |
| `season_ice_kingdom_weapon_set` | Conjunto de Armas do Reino Mágico Congelado | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_internet_set` | Conjunto Heroico do Meme da Internet | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_internet_weapon_set` | Conjunto de Armas do Meme da Internet | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_lightning_set` | Conjunto Heroico do Relâmpago | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_lightning_weapon_set` | Conjunto de Armas do Relâmpago | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_magical_circus_set` | Conjunto Heroico d[m:o Circense Mágico|f:a Circense Mágica] | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_magical_circus_weapon_set` | Conjunto de Armas d[m:o Circense Mágico|f:a Circense Mágica] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_mammoth_set` | Conjunto Heroico d[m:o|f:a] Caçador[m:|f:a] de Mamutes | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `season_mammoth_weapon_set` | Conjunto de Armas d[m:o|f:a] Caçador[m:|f:a] de Mamutes | 2 | 2 peças → +7% Força/Dano |
| `season_mayan_empire_set` | Conjunto Heroico da Cultura Avançada | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_mayan_empire_weapon_set` | Conjunto de Armas da Cultura Avançada | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_old_gods_set` | Conjunto Heroico dos Deuses Antigos | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_old_gods_weapon_set` | Conjunto de Armas dos Deuses Antigos | 2 | 2 peças → +7% Vigor |
| `season_overgrown_1_set` | Conjunto Heroico do Cristal Impenetrável | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `season_overgrown_1_weapon_set` | Conjunto de Armas do Cristal Impenetrável | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_overgrown_2_set` | Conjunto Heroico [m:do Mestre|f:da Mestra] do Micélio | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `season_overgrown_2_weapon_set` | Conjunto de Armas [m:do Mestre|f:da Mestra] do Micélio | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_overgrown_3_set` | Conjunto Heroico do Recife de Coral Exuberante | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Força/Dano · 5 peças → +7% Vigor |
| `season_overgrown_3_weapon_set` | Conjunto de Armas do Recife de Coral Exuberante | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_pool_party_set` | Conjunto Heroico d[m:o Capitão|f:a Capitã] da Festa na Piscina | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_pool_party_weapon_set` | Conjunto de Armas d[m:o Capitão|f:a Capitã] da Festa na Piscina | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_post_apocalypse_set` | Conjunto Heroico do Pós-Apocalipse | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Força/Dano |
| `season_post_apocalypse_weapon_set` | Conjunto de Armas do Pós-Apocalipse | 2 | 2 peças → +7% Vigor |
| `season_pumpkins_set` | Conjunto Heroico do Monstro Perambulante | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_pumpkins_weapon_set` | Conjunto de Armas do Monstro Perambulante | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_revenge_set` | Conjunto Heroico da Vingança | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_revenge_weapon_set` | Conjunto de Armas da Vingança | 2 | 2 peças → +7% Intuição (esquiva) |
| `season_rome_set` | Conjunto heroico d[m:o Lutador|f:a Lutadora] de Arena | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_rome_weapon_set` | Conjunto de armas d[m:o Lutador|f:a Lutadora] de Arena | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_science_set` | Conjunto Heroico [m:do|f:da] Cientista | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_science_weapon_set` | Conjunto de Armas [m:do|f:da] Cientista | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_shadow_set` | Conjunto Heroico [m:do Caça-demônios Ousado|f:da Caça-demônios Ousada] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_shadow_weapon_set` | Conjunto de Armas [m:do Caça-demônios Ousado|f:da Caça-demônios Ousada] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_shang_dynasty_set` | Conjunto heroico d[m:o Lutador|f:a Lutadora] de Bronze | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_shang_dynasty_weapon_set` | Conjunto de armas d[m:o Lutador|f:a Lutadora] de Bronze | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_spooky_xmas_set` | Conjunto Heroico d[m:o Bárbaro Atencioso|f:a Bárbara Atenciosa] | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_spooky_xmas_weapon_set` | Conjunto de Armas d[m:o Bárbaro Atencioso|f:a Bárbara Atenciosa] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_steampunk_set` | Conjunto Heroico d[m:o Mecânico|f:a Mecânica] Steampunk | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_steampunk_weapon_set` | Conjunto de Armas d[m:o Mecânico|f:a Mecânica] Steampunk | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_vampire_set` | Conjunto Heroico d[m:o Vampiro Destemido|f:a Vampira Destemida] | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_vampire_weapon_set` | Conjunto de Armas d[m:o Vampiro Destemido|f:a Vampira Destemida] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_video_games_set` | Conjunto Heroico d[m:o|f:a] Mestre de RPG | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_video_games_weapon_set` | Conjunto de Armas d[m:o|f:a] Mestre de RPG | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_volcano_set` | Conjunto Heroico do Vulcão | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `season_volcano_weapon_set` | Conjunto de Armas do Vulcão | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_wasteland_set` | Conjunto Heroico da Raposa do Deserto | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `season_wasteland_weapon_set` | Conjunto de Armas da Raposa do Deserto | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_water_set` | Conjunto Heroico da Água Corrente | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `season_water_weapon_set` | Conjunto de Armas da Água Corrente | 2 | 2 peças → +7% Força/Dano |
| `season_wild_savannah_set` | Conjunto Heroico d[m:o Senhor|f:a Senhora] das Estepes | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `season_wild_savannah_weapon_set` | Conjunto de Armas d[m:o Senhor|f:a Senhora] das Estepes | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_wild_west_weapon_set` | Conjunto de Armas [m:do Atirador Destemido|f:da Atiradora Destemida] | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_witchcraft_set` | Conjunto Heroico do Bruxo | 5 | 2 peças → +7% Vigor · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `season_witchcraft_weapon_set` | Conjunto de Armas do Bruxo | 2 | 2 peças → +7% Cérebro (crítico) |
| `season_zombies_weapon_set` | Conjunto Heroico d[m:o|f:a] Caça-Zumbis | 2 | 2 peças → +7% Cérebro (crítico) |
| `server_launch_2025_october_set` | Conjunto Heroico [m:do Domador|f:da Domadora] de Feras Blindadas | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `server_launch_2026_cube_set` | Conjunto Heroico [m:do Cultista Geométrico|f:da Cultista Geométrica] | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `server_launch_2026_gangsta1_set` | Conjunto Heroico [m:do Rei|f:da Rainha] do Rock’n’Roll | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `server_launch_2026_overgrown_set` | Conjunto Heroico do [m:Herói|f:Heroína] da Zeronita | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `slotmachine_set1` | Conjunto de Equipamento de Cassino do Crupiê Insubornável | 2 | 2 peças → +7% Cérebro (crítico) |
| `solo_herocon_set` | Conjunto Heroico [m:do|f:da] Protetor[m:|f:a] Intergalátic[m:o|f:a] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `sonic_screwdriver_weapon_set` | Jogo de ferramentas sônicas do viajante no tempo | 2 | 2 peças → +7% Cérebro (crítico) |
| `speedserver_globefish_set` | Conjunto heroico do Baiacu | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `speedserver_int2_set` | Conjunto Heroico [m:do Guerreiro|f:da Guerreira] do Futuro | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `speedserver_shark_set` | Conjunto Heroico da Isca de Tubarão | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `squid_costume1` | Conjunto Heroico do Calamar Artístico | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `st_patricks_day_set` | Conjunto Heroico do Dia da Sorte Verde | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `summer_event_set` | Conjunto Heroico da Diversão de Verão | 5 | 2 peças → +7% Força/Dano · 3 peças → +7% Vigor · 4 peças → +7% Cérebro (crítico) · 5 peças → +7% Intuição (esquiva) |
| `syfy_set` | Epischer Raumanzug der unendlichen Weiten | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `sysadminday_set` | Conjunto Heroico [m:do Administrador|f:da Administradora] de Sistemas | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `tuxedos_set` | Conjunto Heroico da Tranquilidade Autoconfiante | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `valentinesday_set1` | Conjunto de armas de namorados do Cúpido do ataque romântico | 3 | 3 peças → +7% Força/Dano |
| `video_games_week1` | Conjunto heroico da hora [m:do|f:da] gamer profissional | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Intuição (esquiva) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `wasteland_tower_set` | Conjunto Heroico [m:do Náufrago|f:da Náufraga] no Deserto | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Cérebro (crítico) |
| `wasteland_tower_weapon_set` | Conjunto de Armas [m:do Náufrago|f:da Náufraga] no Deserto | 2 | 2 peças → +7% Cérebro (crítico) |
| `worldcup_set` | Conjunto Heroico do Torcedor de Verdade | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Força/Dano · 5 peças → +7% Intuição (esquiva) |
| `xmas_set1` | Conjunto sazonal de herói da festa feliz | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
| `xmas_set2` | Conjunto térmico d[m:o Expedicionário e Pesquisador|f:a Expedicionária e Pesquisadora] do Ártico | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `xmas_set3` | Conjunto semiderretido de batalha de bolas de neve de precisão | 2 | 2 peças → +7% Força/Dano |
| `xmas_set4` | Traje de sílfide festivo do pequeno ajudante de Natal | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `xmas_set5` | Defensor do Natal 2.0 | 2 | 2 peças → +7% Força/Dano |
| `xmas_set6` | Conjunto Heroico do Boneco de Pão de Mel | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `xmas_set7` | Conjunto Heroico do Jogador Festivo | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Vigor · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `xmas_set8` | Conjunto Heroico da Peça de Teatro do Nascimento de Jesus | 5 | 2 peças → +7% Intuição (esquiva) · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Vigor · 5 peças → +7% Força/Dano |
| `xp_tournament_set` | Conjunto Heroico [m:do Mago Sábio|f:da Maga Sábia] | 5 | 2 peças → +7% Cérebro (crítico) · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Vigor |
| `zombies_tower_set` | Conjunto Heroico [m:do|f:da] Maratonista do Fim do Mundo | 5 | 2 peças → +7% Vigor · 3 peças → +7% Cérebro (crítico) · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Força/Dano |
| `zombies_tower_weapon_set` | Conjunto de Armas [m:do|f:da] Maratonista do Fim do Mundo | 2 | 2 peças → +7% Cérebro (crítico) |
| `zorro_set` | Conjunto de Zorro para herói | 5 | 2 peças → +7% Vigor · 3 peças → +7% Força/Dano · 4 peças → +7% Intuição (esquiva) · 5 peças → +7% Cérebro (crítico) |
