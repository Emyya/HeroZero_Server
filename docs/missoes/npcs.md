# NPCs das missões de combate

Estado: 2026-10-07. Correção da associação entre missão, nome e imagem.

## Causa e contrato do cliente

O cliente HTML5 usa fontes diferentes para nome e imagem:

- `Qe.get_npcName()` traduz `npc/<quest.identifier>/name`.
- `Qe.get_npcImageUrl()` e thumbnail usam `quest.fight_npc_identifier`.
- `Qe.get_npcVoiceType()` consulta esse NPC em `constants.npcs`.

O nome não estava em falta: o backend sorteava `fight_npc_identifier` do catálogo
global, independentemente do texto da missão. Podia atribuir turistas, hippies
ou criaturas de outro evento a uma missão com um nome totalmente diferente.

A captura local `captura.har`, juntamente com `1.png` e `2.png`, confirmou:

| Missão | Nome da missão | NPC errado | Associação corrigida |
|---|---|---|---|
| `quest_stage3_fight14` | Vendedor de Balões Evasivo | `npc_olympia_hippies` | `npc_shady_balloon_seller` |
| `quest_stage3_fight13` | Policial Fajuta | `npc_thief` | `npc_overzealous_policewoman` |

As imagens corretas já existem em `assets/npc/` e na cópia pública, em `.webp`
e `_t2.webp`. Os nomes traduzidos e briefings estão no `text.data` comprimido.
Nenhum asset ou código do bundle foi alterado.

## Fonte e limites do mapa

`server-laravel/data/reference/quest_npcs.json` contém 229 associações: as 195
missões normais dos 14 stages e 34 temáticas existentes nas constantes.

É um **mapa local inferido** dos nomes, descrições e assets, não uma tabela
extraída do backend oficial. Os nomes das missões podem ser variantes do mesmo
personagem visual: três policiais distintas no texto usam a mesma policial,
e os vendedores de balões usam o mesmo vendedor.

Associações com nomes explícitos (policial, balões, freira, músicos, ciclistas,
NPCs de Yokyo e Egito, entre outras) têm evidência visual/textual forte.
Nos casos genéricos, a seleção usa a descrição e a família visual mais próxima.
Isso elimina a aleatoriedade global, mas não prova a imagem exata escolhida pelo
oficial. Merecem confirmação em capturas oficiais sobretudo:

- NPCs humanos genéricos das zonas 1–6: hoodies, homens de negócios e crianças.
- Mensageiro (`quest_stage3_fight4`), associado localmente ao homem de negócios
  descolado; não existe asset identificado como mensageiro no catálogo local.
- Variantes de personagens de Paris/Yollywood cujo nome não coincide com o asset.
- Natal, Páscoa e os dois personagens genéricos de Halloween: associações locais
  por descrição. As restantes temáticas têm correspondências diretas por nome.

Não há renomeação artificial do NPC para esconder a divergência: a tradução
específica da missão continua a ser usada pelo cliente. Para tornar estas escolhas
oficiais, comparar `identifier` e `fight_npc_identifier` em respostas do oficial.

## Funções e fluxos

`QuestNpcs::catalog()` carrega o mapa JSON. `identifier(missão, fallback)` devolve
a associação; uma missão desconhecida conserva apenas o fallback explicitamente
fornecido, sem sortear um NPC global.

`Character::insertQuestPool()` consulta o mapa depois de escolher o identificador
final, incluindo tematização por evento. Se surgir uma missão temática ainda sem
mapa, usa a associação da missão normal de origem. Missões de tempo ficam sem NPC.

`EventQuests::refreshQuestOffers()` atualiza a associação ao tematizar ofertas
não iniciadas. Uma temática desconhecida conserva a imagem anterior.

`QuestNpcs::repairOffer()` corrige apenas `type=2, status=1`. O UPDATE exige o
mesmo identificador e NPC lidos, além de dono/estado/tipo, protegendo contra uma
alteração ou início concorrente. Se a condição falhar, relê a linha. É idempotente.

A reparação é chamada em `Character::questsData()` e antes de iniciar a missão
em `startQuestLocked()`. Assim, as ofertas antigas recebem a associação sem gastar
moedas/donuts e sem obrigar a renovar o pool. A atualização acontece no próximo
estado/login/início de missão; não foi executado um UPDATE global na BD.

Missões em curso (`status=2`) e concluídas (`status=4`) são preservadas. Não se
reescrevem batalhas guardadas. Custos, dificuldade, duração e recompensas não são
alterados. A entrada de missão continua a enviar os campos existentes; não há
alteração do protocolo, schema ou migração obrigatória.

## Verificação

```bash
php scripts/testing/test_quest_npcs.php
php scripts/testing/test_quest_npcs.php --mysql --hero="skel"
php scripts/testing/test_quest_generation.php
```

Offline: todas as missões normais têm associação; os 229 identificadores existem
nas constantes, têm nome de missão e assets públicos completos; os dois casos
reportados apontam às imagens certas.

MySQL com rollback: reparação de oferta antiga, preservação de todos os outros
campos, idempotência, exclusão de missões iniciadas/concluídas e geração real.
O teste aceita nome do herói; evitar jogar com esse herói durante a preparação.
Sem `--hero`, usa `skel`. É necessário existir uma oferta normal de combate.

A geração também passou os testes offline/PDO simulado existentes, e HeroCon
passou a regressão MySQL com rollback. Os 35 eventos e resgates também passaram
a regressão MySQL com rollback (`test_event_quests.php --db --hero=skel`).
Confirmação visual pelo utilizador pendente.
Validação de existência de assets não prova a correspondência oficial de todos
os nomes ambíguos.

## Mapa atual

Todos os identificadores da coluna NPC têm prefixo `npc_` no JSON.
A tabela seguinte descreve associações locais, sujeitas aos limites acima.

| Missão | Nome no cliente | NPC |
|---|---|---|
| `quest_stage1_fight1` | Corretor de seguros | `npc_business_man_artless` |
| `quest_stage1_fight2` | Tocador de campainhas | `npc_kids_two_laughing` |
| `quest_stage1_fight3` | Segurança | `npc_hoody_very_evil` |
| `quest_stage1_fight4` | Pseudo dançador de break | `npc_hoody_scruffy` |
| `quest_stage1_fight5` | Gângster de meia tigela | `npc_hoody_evil` |
| `quest_stage1_fight6` | Ladrão jovem | `npc_kids_two_evil` |
| `quest_stage1_fight7` | Garoto ao telefone | `npc_kids_one_evil` |
| `quest_stage1_fight8` | Hacker malvado | `npc_hoody_scruffy` |
| `quest_stage1_fight9` | Fedelho | `npc_kids_one_evil` |
| `quest_stage1_fight10` | Falso prefeito | `npc_false_mayor` |
| `quest_stage1_fight11` | Vizinho aos berros | `npc_disturbing_neighbor` |
| `quest_stage1_fight12` | Freira Míope | `npc_shortsighted_nun` |
| `quest_stage1_fight13` | Freira Míope | `npc_shortsighted_nun` |
| `quest_stage1_fight14` | Freira Míope | `npc_shortsighted_nun` |
| `quest_stage1_fight15` | Freira Míope | `npc_shortsighted_nun` |
| `quest_stage1_fight16` | Musicófilo Irritante | `npc_music_enthusiast` |
| `quest_stage1_fight17` | Musicófilo Insistente | `npc_music_enthusiast` |
| `quest_stage1_fight18` | Musicófilo Apaixonado | `npc_music_enthusiast` |
| `quest_stage1_fight19` | Musicófilo Enganoso | `npc_music_enthusiast` |
| `quest_stage1_fight20` | Ladra de Torcida | `npc_nasty_cheerleader` |
| `quest_stage1_fight21` | Líder de Torcida Exagerada | `npc_nasty_cheerleader` |
| `quest_stage1_fight22` | Líder de Torcida Malvada | `npc_nasty_cheerleader` |
| `quest_stage1_fight23` | Líder de Torcida Mal-educada | `npc_nasty_cheerleader` |
| `quest_stage1_fight24` | Ciclista Arruaceiro | `npc_cyclist_rowdy` |
| `quest_stage1_fight25` | Ladrão de Bicicleta | `npc_cyclist_rowdy` |
| `quest_stage1_fight26` | Ciclista Braço | `npc_cyclist_rowdy` |
| `quest_stage1_fight27` | Ciclista Arruaceiro | `npc_cyclist_rowdy` |
| `quest_stage2_fight1` | Pseudo dançador de break | `npc_hoody_scruffy` |
| `quest_stage2_fight2` | Gângster de meia tigela | `npc_hoody_evil` |
| `quest_stage2_fight3` | Ladrão jovem | `npc_kids_two_evil` |
| `quest_stage2_fight4` | Grosseirão | `npc_casualguy` |
| `quest_stage2_fight5` | “Sanguessuga” | `npc_hoody_scruffy` |
| `quest_stage2_fight6` | Traficante | `npc_hoody_evil` |
| `quest_stage2_fight7` | Convidados da festa | `npc_kids_two_laughing` |
| `quest_stage2_fight8` | Esse sujeitinho aí | `npc_casualguy` |
| `quest_stage2_fight9` | Sujeito extremamente malvado | `npc_hoody_very_evil` |
| `quest_stage2_fight10` | Engomadinho | `npc_business_man_artless` |
| `quest_stage2_fight11` | Frentista malvado | `npc_nastier_employee` |
| `quest_stage2_fight12` | Pintor de bigodes | `npc_bad_artist` |
| `quest_stage2_fight13` | Cara Melancólico | `npc_lonely_satanist` |
| `quest_stage2_fight14` | Cara Desanimado Apaixonado | `npc_lonely_satanist` |
| `quest_stage2_fight15` | Cara Desanimado Gesticulante | `npc_lonely_satanist` |
| `quest_stage2_fight16` | Cara Desanimado Debatedor | `npc_lonely_satanist` |
| `quest_stage2_fight17` | Gêmeos da Dança de Rua | `npc_street_dance_twins` |
| `quest_stage2_fight18` | Gêmeos da Dança de Rua | `npc_street_dance_twins` |
| `quest_stage2_fight19` | Gêmeos da Dança de Rua | `npc_street_dance_twins` |
| `quest_stage2_fight20` | Gêmeos da Dança de Rua | `npc_street_dance_twins` |
| `quest_stage3_fight1` | “Sanguessuga” | `npc_hoody_scruffy` |
| `quest_stage3_fight2` | Traficante | `npc_hoody_evil` |
| `quest_stage3_fight3` | Convidados da festa | `npc_kids_two_laughing` |
| `quest_stage3_fight4` | Mensageiro | `npc_business_man_cool` |
| `quest_stage3_fight5` | Sujeito sombrio | `npc_hoody_very_evil` |
| `quest_stage3_fight6` | Homem de negócios atarefado | `npc_business_man_cool` |
| `quest_stage3_fight7` | Receptador | `npc_business_man_threatening` |
| `quest_stage3_fight8` | Cobrador de Yoyodyne | `npc_business_man_respectable` |
| `quest_stage3_fight9` | Ladrão de verduras | `npc_vegetable_thief` |
| `quest_stage3_fight10` | Madame com cachorro | `npc_doglady` |
| `quest_stage3_fight11` | Policial Exagerada | `npc_overzealous_policewoman` |
| `quest_stage3_fight12` | Policial Questionável | `npc_overzealous_policewoman` |
| `quest_stage3_fight13` | Policial Fajuta | `npc_overzealous_policewoman` |
| `quest_stage3_fight14` | Vendedor de Balões Evasivo | `npc_shady_balloon_seller` |
| `quest_stage3_fight15` | Vendedor de Balões Terrível | `npc_shady_balloon_seller` |
| `quest_stage3_fight16` | Vendedor de Balões Criminoso | `npc_shady_balloon_seller` |
| `quest_stage3_fight17` | Vendedor de Balões Desatento | `npc_shady_balloon_seller` |
| `quest_stage3_fight18` | Vendedor de Balões Malvado | `npc_shady_balloon_seller` |
| `quest_stage4_fight1` | Sujeito sombrio | `npc_hoody_very_evil` |
| `quest_stage4_fight2` | Homem de negócios atarefado | `npc_business_man_cool` |
| `quest_stage4_fight3` | Fanfarrão | `npc_kids_one_evil` |
| `quest_stage4_fight4` | Charlatão | `npc_hoody_very_evil` |
| `quest_stage4_fight5` | Cobrador de doações | `npc_business_man_respectable` |
| `quest_stage4_fight6` | Careca | `npc_business_man_threatening` |
| `quest_stage4_fight7` | Nerds jovens | `npc_kids_two_laughing` |
| `quest_stage4_fight8` | Sujeito que estaciona em vaga indevida | `npc_violator` |
| `quest_stage4_fight9` | Sujeito que estaciona em vaga indevida | `npc_violator` |
| `quest_stage4_fight10` | Distribuidores de panfletos da Yoyodyne | `npc_flyer_distributor` |
| `quest_stage4_fight11` | Hacker J0KER da Yoyodyne | `npc_joker_hacker` |
| `quest_stage4_fight12` | Fofoqueiro Ousado | `npc_cheeky_rumour_spreader` |
| `quest_stage4_fight13` | Músico de Rua Baterista | `npc_drummer_street_musician` |
| `quest_stage4_fight14` | Skatista Arrogante | `npc_arrogant_skateboarder` |
| `quest_stage4_fight15` | Gatuno Arranhão | `npc_scratchy_cat` |
| `quest_stage4_fight16` | Ladrão de Salsichas Fofinho | `npc_sausage_thief` |
| `quest_stage5_fight1` | Gângster da Yoyodyne | `npc_hoody_evil` |
| `quest_stage5_fight2` | Mafioso da Yoyodyne | `npc_hoody_very_evil` |
| `quest_stage5_fight3` | Capanga sórdido da Yoyodyne | `npc_hoody_scruffy` |
| `quest_stage5_fight4` | Aprendiz insosso | `npc_kids_two_evil` |
| `quest_stage5_fight5` | Pequeno geek | `npc_kids_one_evil` |
| `quest_stage5_fight6` | Diretor do curso | `npc_business_man_artless` |
| `quest_stage5_fight7` | Solapador de banco | `npc_hoody_scruffy` |
| `quest_stage5_fight9` | Neutralizador de queijo | `npc_cheese_technic` |
| `quest_stage5_fight10` | Comerciante duvidoso | `npc_dubious_trader` |
| `quest_stage5_fight11` | Max, o Maníaco por Mídia | `npc_media_maniac` |
| `quest_stage5_fight12` | Cantor de Ópera | `npc_opera_singer` |
| `quest_stage5_fight13` | Alienígenas Alpinos | `npc_alps_alien` |
| `quest_stage5_fight14` | Fantasma do Fondue | `npc_fondue_phantom` |
| `quest_stage5_fight15` | Cabra Gourmet | `npc_gourmet_geiss` |
| `quest_stage6_fight1` | Aprendiz insosso | `npc_kids_two_evil` |
| `quest_stage6_fight2` | Pequeno geek | `npc_kids_one_evil` |
| `quest_stage6_fight3` | Sujeito sórdido | `npc_hoody_scruffy` |
| `quest_stage6_fight4` | Consultor mal intencionado | `npc_business_man_cool` |
| `quest_stage6_fight5` | Ladrão de galinhas | `npc_kids_one_evil` |
| `quest_stage6_fight6` | Estraga-prazeres | `npc_hoody_very_evil` |
| `quest_stage6_fight7` | Capanga sórdido | `npc_business_man_threatening` |
| `quest_stage6_fight8` | Funcionário público insano | `npc_business_man_artless` |
| `quest_stage7_fight1` | Ralph | `npc_animator` |
| `quest_stage7_fight2` | Cozinheiro sem estrela | `npc_cook` |
| `quest_stage7_fight3` | Erwin ganancioso | `npc_tourist` |
| `quest_stage7_fight4` | Indivíduo com risco de queda | `npc_tourists` |
| `quest_stage7_fight5` | Recepcionista | `npc_concierge` |
| `quest_stage7_fight6` | Boby preguiçoso | `npc_tourist` |
| `quest_stage7_fight7` | Lucas suicida | `npc_adventurer` |
| `quest_stage7_fight8` | Cozinheiro sem estrela | `npc_cook` |
| `quest_stage7_fight9` | Recepcionista | `npc_concierge` |
| `quest_stage7_fight10` | Indivíduo com risco de afogamento | `npc_tourists` |
| `quest_stage7_fight11` | Ralph | `npc_animator` |
| `quest_stage7_fight12` | Turista sobrevivente | `npc_adventurer` |
| `quest_stage8_fight1` | Recepcionista de luxo | `npc_concierge` |
| `quest_stage8_fight2` | Jogo de conchas | `npc_cheater` |
| `quest_stage8_fight3` | Dublê de Elvis | `npc_elvis` |
| `quest_stage8_fight4` | Afortunado | `npc_sunnyboy` |
| `quest_stage8_fight5` | Siegfried e Boy | `npc_magicians` |
| `quest_stage8_fight6` | Willy | `npc_meterman` |
| `quest_stage8_fight7` | Mais um dublê de Elvis | `npc_elvis` |
| `quest_stage8_fight8` | Super cérebro | `npc_mastermind` |
| `quest_stage8_fight9` | Segurança amedrontador | `npc_hoody_very_evil` |
| `quest_stage8_fight10` | Mau perdedor | `npc_loser` |
| `quest_stage8_fight11` | Siegfried e Boy | `npc_magicians` |
| `quest_stage8_fight12` | Mau trapaceiro | `npc_cheater` |
| `quest_stage9_fight1` | Chefe da Corporação Software | `npc_sillycon_valley_boss` |
| `quest_stage9_fight2` | Tipão importante | `npc_sillycon_valley_killer_segway` |
| `quest_stage9_fight3` | Estevão | `npc_sillycon_valley_nerd` |
| `quest_stage9_fight4` | Vítima do café | `npc_sillycon_valley_stressed_manager` |
| `quest_stage9_fight5` | Nerd em tempo integral | `npc_sillycon_valley_nerd` |
| `quest_stage9_fight6` | CEO da Viciosoft | `npc_sillycon_valley_boss` |
| `quest_stage9_fight7` | Hacker Hugo | `npc_sillycon_valley_nerd` |
| `quest_stage9_fight8` | CEO OMFG da Abacaxi | `npc_sillycon_valley_boss` |
| `quest_stage9_fight9` | Perpetrador do café | `npc_sillycon_valley_stressed_manager` |
| `quest_stage9_fight10` | Dani | `npc_sillycon_valley_nerd` |
| `quest_stage10_fight1` | Floriana e Floriano | `npc_paris_couple` |
| `quest_stage10_fight2` | Marcel Duchlampé | `npc_paris_thief` |
| `quest_stage10_fight3` | Yoyofede responsável | `npc_paris_gredin` |
| `quest_stage10_fight4` | Padeiro de baguete | `npc_paris_frenchman` |
| `quest_stage10_fight5` | Klaut Monéêy | `npc_paris_thief` |
| `quest_stage10_fight6` | Floriano e Floriana | `npc_paris_couple` |
| `quest_stage10_fight7` | Ignorante em artes | `npc_paris_frenchman` |
| `quest_stage10_fight8` | Duble de francês | `npc_paris_frenchman` |
| `quest_stage10_fight9` | Yoyodyneur de Paris | `npc_paris_gredin` |
| `quest_stage10_fight10` | Vincent van Cook | `npc_paris_frenchman` |
| `quest_stage11_fight1` | Ladrões malvados | `npc_thief` |
| `quest_stage11_fight2` | Vândalos | `npc_kids_two_evil` |
| `quest_stage11_fight3` | Marrento pretencioso | `npc_hoody_scruffy` |
| `quest_stage11_fight4` | Ladrão esperto | `npc_thief` |
| `quest_stage11_fight5` | Passante com raiva | `npc_casualguy` |
| `quest_stage11_fight6` | Produtor cinematográfico | `npc_business_man_threatening` |
| `quest_stage11_fight7` | Gustavo “Gigante” Grekowski | `npc_westler` |
| `quest_stage11_fight8` | Ator | `npc_moviestar` |
| `quest_stage11_fight9` | Batedor de carteira | `npc_thief` |
| `quest_stage11_fight10` | Vendedor de caviar suspeito | `npc_dubious_trader` |
| `quest_stage11_fight11` | Copiador pirata | `npc_thief` |
| `quest_stage11_fight12` | Menino | `npc_kids_one_evil` |
| `quest_stage11_fight13` | Cozinheiro | `npc_cook` |
| `quest_stage11_fight14` | Figura fantasmagórica | `npc_halloween_ghost` |
| `quest_stage11_fight15` | Sujeito suspeito | `npc_hoody_very_evil` |
| `quest_stage12_fight1` | Canguru selvagem | `npc_kangaroo` |
| `quest_stage12_fight2` | Surfista gosmento | `npc_yoyosurfer` |
| `quest_stage12_fight3` | Grupo de turistas | `npc_tourists` |
| `quest_stage12_fight4` | Ladrão de bolsas | `npc_thief` |
| `quest_stage12_fight5` | Canguru boxeador | `npc_kangaroo` |
| `quest_stage12_fight6` | Sujeito metidinho | `npc_sunnyboy` |
| `quest_stage12_fight7` | Surfista yoyodynese | `npc_yoyosurfer` |
| `quest_stage12_fight8` | Fotógrafo-bomba | `npc_sunnyboy` |
| `quest_stage12_fight9` | Turistas que discutem | `npc_tourists` |
| `quest_stage12_fight10` | Sujismundo | `npc_tourist` |
| `quest_stage13_fight1` | Grosseirão sumo | `npc_yokyo_sumo` |
| `quest_stage13_fight2` | Ladrão rastejante | `npc_yokyo_ninja` |
| `quest_stage13_fight3` | Cozinheiro aos berros | `npc_yokyo_angry_cook` |
| `quest_stage13_fight4` | Mestre Wiagi | `npc_yokyo_mister_wiagi` |
| `quest_stage13_fight5` | Sabotador Ninja | `npc_yokyo_ninja` |
| `quest_stage13_fight6` | Monstro de oito tentáculos | `npc_yokyo_octopus` |
| `quest_stage13_fight7` | Lutador de sumo | `npc_yokyo_sumo` |
| `quest_stage13_fight8` | Ninja contratado | `npc_yokyo_ninja` |
| `quest_stage13_fight9` | Mestre Wiagi | `npc_yokyo_mister_wiagi` |
| `quest_stage13_fight10` | Cozinheiro chefe aos berros | `npc_yokyo_angry_cook` |
| `quest_stage13_fight11` | Samurai misterioso | `npc_yokyo_samurai` |
| `quest_stage13_fight12` | Samurai tropeçante | `npc_yokyo_samurai` |
| `quest_stage14_fight1` | Múmia apavorante | `npc_egypt_mummy` |
| `quest_stage14_fight2` | Grupo de turistas | `npc_egypt_tourists` |
| `quest_stage14_fight3` | Tubarão | `npc_egypt_shark` |
| `quest_stage14_fight4` | Guardas | `npc_egypt_guard` |
| `quest_stage14_fight5` | Limpador de janelas | `npc_egypt_window_cleaner` |
| `quest_stage14_fight6` | Meninos malvados | `npc_egypt_evil_kids` |
| `quest_stage14_fight7` | Animador esforçado | `npc_egypt_animator` |
| `quest_stage14_fight8` | Engenheiro dubioso | `npc_egypt_ingenieur` |
| `quest_stage14_fight9` | Monstro perigoso | `npc_egypt_crocodile` |
| `quest_stage14_fight10` | Faraó falso | `npc_egypt_pharao` |
| `quest_stage14_fight11` | Macaquinho | `npc_egypt_monkey` |
| `quest_olympia_fight1` | Yoyo-barco “SpecOp:Londres” | `npc_olympia_robot_small` |
| `quest_olympia_fight2` | Vencedor de medalha de ouro | `npc_olympia_winner` |
| `quest_olympia_fight3` | Hippie Guilherme Tell | `npc_olympia_hippies` |
| `quest_olympia_fight4` | Frederico Amassa | `npc_olympia_crusher` |
| `quest_olympia_fight5` | Jimmy | `npc_olympia_fencer` |
| `quest_olympia_2016_rio_fight1` | Sprinter falso | `npc_summer_olympia_sprinter` |
| `quest_olympia_2016_rio_fight2` | Robô cortador de grama | `npc_summer_olympia_robot` |
| `quest_halloween_fight2` | Fantasma fantasmagórico | `npc_halloween_ghost` |
| `quest_winterolympics_fight1` | Abominável Homem das Neves | `npc_winterolympics_yeti` |
| `quest_winterolympics_fight3` | Técnico | `npc_winterolympics_coach` |
| `quest_winterolympics_fight4` | Molequinho | `npc_winterolympics_littlerascal` |
| `quest_winterolympics_fight5` | Manifestantes | `npc_winterolympics_protestors` |
| `quest_worldcup_fight1` | Vendedor de cachorro-quente | `npc_worldcup_hotdog_vendor` |
| `quest_worldcup_fight2` | Ladrão de camiseta oficial | `npc_worldcup_shirt_thief` |
| `quest_worldcup_fight3` | Espetador de bola | `npc_ball_stabber` |
| `quest_worldcup_fight4` | Torcedor fanático | `npc_worldcup_fan` |
| `quest_worldcup_fight5` | Vigia esforçado | `npc_worldcup_security` |
| `quest_midsummer_fight1` | Gato Selvagem | `npc_midsummer_cat` |
| `quest_midsummer_fight2` | Grupo de Praieiros Desocupados | `npc_midsummer_rowdy` |
| `quest_midsummer_fight3` | Tubarão de Mentira | `npc_midsummer_fake_shark` |
| `quest_xmas_fight1` | Arremessador de spekulatius | `npc_kids_one_evil` |
| `quest_xmas_fight2` | Cortador de árvores | `npc_hoody_scruffy` |
| `quest_xmas_fight3` | Parasita de biscoitos | `npc_kids_two_evil` |
| `quest_xmas_fight4` | Claudio e Alex | `npc_kids_two_laughing` |
| `quest_xmas_fight5` | Vendedor de imitação de panetone | `npc_dubious_trader` |
| `quest_xmas_fight6` | Ebenezer Scrooge | `npc_business_man_threatening` |
| `quest_easter_fight1` | Garoto maroto | `npc_kids_one_evil` |
| `quest_easter_fight2` | Vendedor de maças | `npc_dubious_trader` |
| `quest_easter_fight3` | Malvado detestável | `npc_hoody_very_evil` |
| `quest_easter_fight4` | Rufus | `npc_tourist` |
| `quest_easter_fight5` | Homem sinistro | `npc_business_man_threatening` |
| `quest_easter_fight6` | Burocrata | `npc_business_man_artless` |
| `quest_halloween_fight1` | Monstro de Frankenstein?! | `npc_hoody_very_evil` |
| `quest_halloween_fight3` | Ladrão de sangue | `npc_hoody_evil` |
