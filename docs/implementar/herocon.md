# HeroCon — referências no cliente e nas constantes

Referência do funcionamento atual: [HeroCons — overview](../herocons/overview.md).
Este ficheiro conserva também a análise histórica do catálogo e do cliente.

Análise estática das fontes locais, revista em **2026-10-07**, seguida da implementação local descrita no final deste documento. Os comportamentos descritos são os observados no cliente; as decisões que pertencem ao servidor são assinaladas como não confirmadas.

## Fontes

- JavaScript: `server-laravel/public/js/HeroZero.min.js`.
- Constantes: `assets/data/constants_json.data`, descomprimido com zlib e interpretado como JSON.
- A cópia em `server-laravel/public/assets/data/constants_json.data` é igual à da raiz.

As referências necessárias estão neste documento, sem dependência de ficheiros temporários. As linhas referem-se à versão local analisada; os símbolos e nomes de métodos permitem reencontrá-las se o bundle mudar.

- SHA-256 das duas cópias de constantes: `eee7c3228c00485df032262eb7173081eed339fbda4739cf6e858ccf1f0adad2`.
- SHA-256 do JavaScript: `4d0ea895bf6fab7808a210919d3001b89f7def3889884d5e9719d6c77f1b5d52`.
- Nas referências às constantes, os caminhos são relativos à raiz do JSON **descomprimido**.

## Nomes internos e classificações

O nome interno principal é `guild_competition`. Existem duas classificações:

- **HeroCon de equipa:** aba/tipo de torneio `5`.
- **HeroCon individual:** `solo_guild_competition`, aba/tipo `6`.

## Temas e objetivos

Nas constantes existem **19 temas**:

| Tema | `needed_fractions` | Multiplicadores dos objetivos |
|---|---:|---|
| Vender objetos | 20 | Comum 1; raro 3; épico 9; lendário 27; reforjado 3 |
| Completar missões | 100 | 1 |
| Gastar energia em missões | 20 | 1 |
| Comprar pontos de habilidade | 100 | 1 |
| Recolher pontos do esconderijo | 10 | 1 |
| Missões de combate | 40 | Vitória 3; derrota 1 |
| Completar missões especiais | 150 | 1 |
| Completar HeroBook diário | 100 | 1 |
| Fazer duelos | 50 | Vitória 3; derrota 1 |
| Vender objetos — variante | 50 | Comum 1; raro 2; épico 3; lendário 4; reforjado 1 |
| Construir bots | 100 | 1 |
| Produzir modificações no esconderijo | 300 | Comum 3; rara 10; épica 20 |
| Recolher objeto do evento 1 | 350 | 1 |
| Gastar energia em missões de tempo | 20 | 1 |
| Atacar vilões | 800 | Ataque terminado 1; vilão derrotado 5 |
| Subir o nível de acompanhantes | 300 | 1 |
| Completar missões de treino | 50 | 1 |
| Recolher estrelas de treino | 120 | 1 |
| Recolher objeto do evento 2 | 100 | 2 |

Mantém-se o nome técnico `needed_fractions`: o cliente distingue **frações** de **pontos**, através de `fraction` e `score`.

### Identificadores e enums dos objetivos

Fonte: `guild_competitions.<identifier>`. A primeira coluna omite apenas o prefixo comum `default_guild_competition_`. Os valores de `type` são os das constantes, associados ao respetivo `objective_identifier`; não são uma enumeração deduzida pela ordem dos temas.

| Sufixo do identificador | Objetivo (`type`; multiplicador) | `reference` não vazia |
|---|---|---|
| `sell_items` | `sellCommonItem` (2; ×1); `sellRareItem` (3; ×3); `sellEpicItem` (4; ×9); `sellLegendaryItem` (32; ×27); `sellWarforgedItem` (12; ×3) | — |
| `quest_won` | `questCompleted` (18; ×1) | — |
| `quest_energy_used` | `questEnergyUsed` (19; ×1) | — |
| `stat_points_bought` | `statPointsBought` (17; ×1) | — |
| `collect_hideout_points` | `collectHideoutPoints` (1; ×1) | — |
| `fight_quest_won` | `fightQuestWon` (20; ×3); `fightQuestLost` (21; ×1) | — |
| `complete_dungeon_quest` | `completeDungeonQuest` (5; ×1) | — |
| `daily_herobook_completed` | `dailyHerobookCompleted` (22; ×1) | — |
| `duel_fought` | `duelsLost` (24; ×1); `duelsWon` (11; ×3) | — |
| `sell_items2` | `sellCommonItem` (2; ×1); `sellRareItem` (3; ×2); `sellEpicItem` (4; ×3); `sellLegendaryItem` (32; ×4); `sellWarforgedItem` (12; ×1) | — |
| `hideout_bots_build` | `hideoutBotsBuild` (25; ×1) | — |
| `hideout_improvement_build` | `hideoutCommonImprovementsBuild` (26; ×3); `hideoutRareImprovementsBuild` (27; ×10); `hideoutEpicImprovementsBuild` (28; ×20) | — |
| `collect_item_1` | `itemCollected` (15; ×1) | `collect_item_1_award` |
| `time_quest` | `timeQuestEnergyUsed` (6; ×1) | — |
| `worldboss_attack` | `worldbossAttackFinished` (8; ×1); `worldbossDefeated` (9; ×5) | — |
| `sidekick_levelup` | `sidekickLevelup` (29; ×1) | — |
| `training_missions` | `trainingMissionCompleted` (30; ×1) | — |
| `training_stars` | `trainingStarCollected` (31; ×1) | — |
| `collect_item_2` | `itemCollected` (15; ×2) | `collect_item_2` |

### Frações e objetos de recolha

O diálogo apresenta `fraction / needed_fractions` e calcula as frações em falta por subtração (`Bo.refreshCurrentGuildCompetition`, JS 5099–5100). Isto sustenta a leitura de `needed_fractions` como limiar da barra de progresso. **A conversão autoritativa para `score`, o transporte do excedente e a contabilização de cada ação não são implementados neste trecho do cliente**; precisam de evidência do servidor/capturas antes de serem apresentados como regras oficiais.

A tabela `guild_competition_items` contém:

| Identificador | Tema (sufixo) | `reward_type` | `reward_chance` | `reward_reference` |
|---|---|---:|---:|---|
| `collect_item_1_award` | `collect_item_1` | 1 | 0.2 | `""` |
| `collect_item_2` | `collect_item_2` | 1 | 0.1 | `""` |

São valores brutos de configuração. Estes registos, isoladamente, não identificam todas as ações elegíveis nem provam uma tentativa de obtenção por ação. `af.hasObjectiveWithItem` procura objetivos de `type == 15` e aceita referências separadas por `;`, vazias ou `all` (JS 2090–2091). No sistema genérico de recompensas, o cliente trata o **tipo de recompensa 41** como objeto de HeroCon (JS 416–417); não o confundir com `type == 15` dos objetivos ou `reward_type == 1` desta tabela.

## Recompensas individuais

Os pontos de habilidade definidos nas constantes são:

| Classificação | Pontos de habilidade |
|---|---:|
| 1.º–3.º | 10 |
| 4.º–10.º | 9 |
| 11.º–20.º | 8 |
| 21.º–30.º | 7 |
| 31.º–40.º | 6 |
| 41.º–50.º | 5 |
| 51.º–100.º | 4 |
| 101.º–150.º | 3 |
| 151.º–200.º | 2 |
| 201.º–300.º | 1 |

Há ainda referências a caixas:

- Primeiro lugar: `surprise_box_solo_herocon_big_1`.
- 2.º–5.º lugares: `surprise_box_solo_herocon_small_1`.

O cliente escolhe a caixa grande para o 1.º lugar **ou** a pequena até ao 5.º; não apresenta ambas no 1.º lugar (`jy.refresh`, JS 12021–12022). Os pontos de habilidade são zero acima do 300.º (`jy.getStatPointsRewardForRank`, JS 12023–12024).

### Pontos de temporada

O diálogo individual (`LI.refresh`, JS 6563) e a classificação (`jy.refresh`, JS 12022) selecionam as localizações de pontos de temporada:

| Classificação | ID da localização |
|---|---:|
| 1.º–3.º | 3 |
| 4.º–10.º | 4 |
| 11.º–100.º | 5 |

Estes IDs **não são quantidades de pontos**. `ea.fromSeasonPoints` (JS 2787–2788) só produz uma recompensa quando existe temporada ativa, a localização está disponível nessa temporada e o valor calculado é positivo. A quantidade vem de `getSeasonPointLocationValue`. Não se deve fixar 30 pontos para todos os participantes a partir do texto do Event Hub.

## Recompensas de equipa

As constantes indicam:

- `guild_competition_min_score = 100`.
- `guild_competition_min_rank_reward = 100`.
- `guild_competition_reward_guild_booster = 20`.
- `guild_competition_reward_stat_points = 24`.

O JavaScript calcula os valores apresentados conforme a posição:

```text
fator = max(1 − posição / equipas_com_prémio × 1.1,
            0.05,
            1.625 − 0.15 × posição)

booster = ceil(fator × 20)
habilidades = ceil(fator × 24)
```

Portanto, **20 e 24 são bases de cálculo**, não prémios fixos para todas as posições.

A fórmula está em `lt.getGuildRewardForRank` e `lt.getStatPointsRewardForRank` (JS 11914), e também no tooltip `r1` (JS 10446–10450). `equipas_com_prémio` é o valor recebido em `guilds_with_rewards`; não se deve substituí-lo automaticamente por 100 ou pelo total de equipas. O booster é apresentado como percentagem.

O tooltip distingue `score < 100` de uma posição elegível; acima da posição 100 apresenta ausência de recompensa. A lista só calcula prémios quando `rank <= 100` e `guilds_with_rewards > 0`. O servidor tem de fornecer valores coerentes, incluindo um denominador positivo. Por exemplo, para `guilds_with_rewards = 100`, a fórmula apresenta 30% e 36 pontos de habilidade no 1.º lugar; 1% e 2 pontos no 100.º. São exemplos do cálculo visual, não confirmação de entregas do backend.

A equipa tem ainda os campos inteiros `guild_competition_reward_boost_factor` e `ts_guild_competition_reward_boost_expires` (`Ac`, JS 1548–1549). `Va.get_guildCompetitionBoostActive` exige fator positivo e expiração futura (JS 1966–1967). A existência do timestamp não demonstra, por si só, uma duração fixa do booster.

## Protocolo e ações

### Estado atual e próxima competição

`DOGuildCompetition` (`qw`, JS 1566–1567) expõe `status`, `identifier` e `ts_end`; a classe derivada `GuildCompetition` (`af`, JS 2089–2102) lê campos adicionais:

| Campo | Tipo lido | Utilização |
|---|---|---|
| `identifier` | String | Chave existente em `guild_competitions` |
| `status` | Int | `1` significa iniciada no teste `get_isStarted` |
| `ts_start`, `ts_end` | Int | Início e fim fornecidos pelo servidor |
| `rank`, `score`, `fraction` | Int | Posição, pontos e frações da equipa |
| `guilds_with_rewards` | Int | Denominador dos prémios; presença verificada pelo cliente |
| `guilds_same_score` | Int | Informação sobre equipas com a mesma pontuação |
| `guilds_higher_score`, `guilds_lower_score` | Objeto, opcional/nulo | Grupos imediatamente acima/abaixo |
| `worldboss_start_times` | Array, opcional | Horários de vilões; entradas com `startDateTime` |

Os objetos acima/abaixo usam `score`, `guild_count`, `guild_name` e os campos de emblema `emblem_background_shape`, `emblem_background_color`, `emblem_background_border_color`, `emblem_icon_shape`, `emblem_icon_color`, `emblem_icon_size`.

A condição visual de atividade é `status == 1 && ts_end > agora`; os restantes estados numéricos não ficam definidos por este teste. `identifier` é usado diretamente para consultar as constantes: não enviar um tema inventado.

O processamento de respostas (JS 3866–3868) recebe `guild_competition_data`, depois `next_guild_competition_data`, e também `current_guild_competition_identifier`. Os dois primeiros são aplicados quando a personagem tem equipa; o segundo pressupõe que o objeto da competição atual já existe. `guild_competition_tournament_end_timestamp` serve o contador das classificações, incluindo a individual.

Em `character` (`$a`, JS 586–587):

- `guild_competition_points_gathered`: Int.
- `guild_competition_point_fractions_gathered`: Int.
- `pending_guild_competition_tournament_rewards`: Int.
- `pending_solo_guild_competition_tournament_rewards`: Int.
- `new_guild_competition_tournament`: Boolean.

Os membros da equipa (`Nd`, JS 1585) também expõem os dois contadores de contribuição. Estes contadores não devem ser confundidos com o agregado `score`/`fraction` da equipa.

### Pedidos e respostas

Os parâmetros seguintes são os específicos da ação, além do envelope normal de autenticação.

| Ação | Parâmetros observados | Dados consumidos |
|---|---|---|
| `getGuildCompetitionTournamentReward` | `{}` | `tournament_guild_competition_reward` |
| `claimGuildCompetitionTournamentReward` | `{}` | Atualização normal do estado |
| `getSoloGuildCompetitionTournamentReward` | `{}` | `tournament_solo_guild_competition_reward` |
| `claimSoloGuildCompetitionTournamentReward` | `discard_item: false` ou `true` | Atualização normal do estado |
| `retrieveGuildCompetitionTournamentLeaderboard` | `{}`, `rank` ou `guild_name` | `leaderboard_guild_competitions`, `max_guild_competitions`, `guilds_with_rewards`, `centered_rank`, `guild_competition_tournament_end_timestamp` |
| `retrieveSoloGuildCompetitionTournamentLeaderboard` | `{}`, `rank` ou `character_name` | `leaderboard_solo_guild_competitions`, `max_solo_guild_competitions`, `centered_rank`, `guild_competition_tournament_end_timestamp` |
| `retrieveGuildCompetitionTournamentLeaderboardGuildsAroundOwnGuild` | `{}` | Atualização do estado usada pelo diálogo de progresso |
| `setNewGuildCompetitionViewed` | `{}` | Atualização normal do estado |

Referências: `Bo` (JS 5083, 5108), `cu` (5110–5116), `LI` (6559–6565), `Gv` (11896–11906), `Hv` (12002–12013), `xf` (14998–15033).

As linhas da classificação de equipa (`Ms`, JS 1647–1649) usam `rank`, `id`, `n`, `s`, `ebs`, `ebc`, `ebbc`, `eis`, `eic`, `eiz`. `n` é o nome, `s` a pontuação e os seis últimos campos definem o emblema (`xw`, JS 2194–2196).

As individuais (`yo`, JS 1649–1652) usam `rank` **e** `r`, `id`, `g`, `n`, `os`, `gi`, `gn`, `s` e os mesmos campos de emblema. Preservar ambos os campos de posição: a navegação/centragem lê `r`, mas o cálculo dos prémios lê `rank`.

### Formato dos prémios e erros tratados

Os dois diálogos leem `identifier` quando presente e desserializam **a string JSON** `rewards`. Dentro desse JSON leem `rank` (Int), `value` (Int, pontuação) e, quando presente, `reward` (String, entregue ao parser genérico `ec`). Não substituir estas strings por objetos JSON sem validar o contrato do cliente (`cu` e `LI`).

Erros explicitamente tratados nos fluxos analisados:

- `errTournamentLocked`: consultas/classificações e resgate de equipa.
- `errClaimGuildCompetitionTournamentRewardsNoRewards`: fecha o diálogo de equipa.
- `errClaimSoloGuildCompetitionTournamentRewardsNoRewards`: fecha o diálogo individual.
- `errInventoryNoEmptySlot`: no resgate individual, permite libertar espaço ou repetir com `discard_item: true`.
- `errRetrieveLeaderboardEmpty_<timestamp>`, `errRetrieveLeaderboardInvalidRank`, `errRetrieveLeaderboardInvalidGuild` (equipa) e `errRetrieveLeaderboardInvalidCharacter` (individual).

O cliente pode atualizar estado após consultar/resgatar. A marcação de prémios pendentes e a entrega real são responsabilidades do servidor; estes diálogos não demonstram atomicidade nem proteção contra resgate duplicado.

## Interface, esconderijo e colecionáveis

- **Interface:** evento `herocon`, progresso atual, próximo HeroCon, tooltips, ordenação dos membros por pontos/frações e diálogos de recompensa. O Event Hub abre o progresso quando existe equipa com competição; caso contrário, abre a classificação individual (JS 1847). Há também referências visuais à produção de HeroCon na sinergia da equipa (`UiGuildSynergyMemberListHeader`, JS 10468–10473); a sua presença não estabelece uma fórmula adicional de pontuação.
- **Esconderijo:** sala `guild_competition_point_production`, desbloqueada com edifício principal de nível 5, com 10 níveis.
- **Colecionáveis:** conjunto `solo_herocon_set`, acompanhante `sidekick_solo_herocon`, troféus visuais das posições 1–5, feitos e títulos.

### Produção no esconderijo

Fonte: `hideout_rooms.guild_competition_point_production`. Além do desbloqueio já referido, a sala tem `limit = 1`, `size = 1`, `type = "collect"` e `index = 28`.

| Nível | `min_till_max_resource` | `resource_production_max` |
|---|---:|---:|
| 1 | 15 | 5 |
| 2 | 30 | 10 |
| 3 | 60 | 20 |
| 4 | 90 | 30 |
| 5 | 120 | 40 |
| 6 | 150 | 50 |
| 7 | 180 | 60 |
| 8 | 225 | 75 |
| 9 | 270 | 90 |
| 10 | 360 | 120 |

Os campos descrevem a produção configurada da sala. Não equivaler automaticamente cada unidade produzida a um ponto final de classificação: existe o objetivo separado `collectHideoutPoints`, além dos contadores de frações.

### Localização das referências de colecionáveis

- `item_templates`: equipamentos `*_solo_herocon`, incluindo variantes `*_ns` e `*-effect-shc`, com referência ao conjunto `solo_herocon_set`.
- `sidekick_data.sidekick_solo_herocon`: metadados visuais do acompanhante; não demonstra entrega garantida em cada caixa.
- `optical_changes`: registos com `guild_competition_rank` de 1 a 5, associados aos troféus `herocon_rank_*_trophy`.
- `goals`: `guild_competition_visit`, `guild_competition_top_100`, `guild_competition_first_place`, `guild_competition_score_3000`, `solo_guild_competition_top_1`, `solo_guild_competition_top_10`, `solo_guild_competition_top_50`, `solo_guild_competition_top_100`, `solo_guild_competition_participated`.
- `titles`: `title_goal_solo_guild_competition_participated_1`, `_2` e `_3`; nos feitos de participação, os títulos estão associados aos valores 40, 105 e 235.

## Calendário e limites da análise

O cliente recebe `ts_start`/`ts_end` do servidor. Estas referências não estabelecem, por si só, o dia e a hora semanal de encerramento. Existe ainda a configuração opcional `speedserver_guild_competition_times` (JS 207), com entradas `startDateTime` e `identifier` (`M$`, JS 1567–1568); não deve ser generalizada como calendário dos servidores normais.

Continuam por confirmar: rotação dos temas, regras de desempate, requisitos de participação/permanência na equipa, efeito de sair/mudar de equipa, fórmula de contabilização e transporte de frações, duração/âmbito do booster e política de expiração/acumulação dos prémios.

O Event Hub (`u7`, JS 8698–8700) contém textos fixos distintos: **equipa: 30% de booster e 36 pontos de habilidade**; **individual: 10 pontos de habilidade e 30 pontos de temporada**. Esses textos, isoladamente, não provam que esses valores sejam entregues em todas as classificações.

A análise acima descreve referências do cliente e das constantes. O estado do backend e as decisões locais estão na secção seguinte.

## Implementação local (2026-10-07)

O backend implementa HeroCon de equipa e individual. A competição
`default_guild_competition_complete_dungeon_quest` foi excluída da rotação e
os eventos de objetivo tipo 5 são ignorados, por pedido do utilizador.
A rotação contém 13 temas ativos da lista corrigida pelo utilizador. Os
identificadores e pesos vêm das constantes; os limiares não dividem a pontuação. O JavaScript e os assets comprimidos não foram alterados.

### Regras locais adotadas

Estas regras completam os pontos que a análise do cliente não confirma;
não são apresentadas como regras do servidor oficial:

- Períodos semanais de quinta-feira às 21:00 até quinta-feira às 21:00,
  `Europe/Lisbon`, usando `GameTime`. A mudança de hora respeita dias civis.
- Rotação determinística na ordem dos 13 temas do catálogo; referência inicial
  de calendário: 2026-10-01 às 21:00. O fecho e a abertura seguintes acontecem
  no primeiro pedido que sincroniza HeroCon; não dependem de cron.
- Cada atividade acrescenta `quantidade × multiplier` pontos diretamente.
  Não existe divisão por `needed_fractions`: `score` contém os pontos e
  `fraction` permanece zero. A coluna histórica `herocon_contributions.fractions`
  guarda este total de pontos; o nome foi preservado para compatibilidade.
- Só ações praticadas enquanto o herói pertence a uma equipa contam. Sair ou
  mudar de equipa conserva a contribuição na equipa de origem; a classificação
  individual conserva o total das contribuições da semana. Os contadores dos
  membros mostram apenas a contribuição à equipa consultada.
- Desempate: primeiro a atingir a pontuação final; em igualdade de timestamp,
  ID menor. Os campos de frações não desempatam a classificação.
- Só equipas com pelo menos 100 pontos e posição até 100 recebem prémios.
  `guilds_with_rewards` conta essas equipas. Pontos de habilidade de equipa
  são atribuídos aos membros que contribuíram e continuam nessa equipa no fecho.
- O booster é ativado automaticamente na equipa no fecho e dura até ao fim
  da competição seguinte. Não acumula percentagens. Aumenta XP e moedas das
  missões normais geradas enquanto está ativo; não modifica prémios já fixados.
  A aplicação a trabalho não foi adicionada: não há um fluxo de trabalho funcional
  correspondente neste backend.
- Cada participante com pelo menos um ponto recebe o resultado individual,
  mesmo quando a sua posição não dá prémios. Resultados de equipa sem prémio
  também podem ser consultados/resgatados, sem conceder recursos.
- Resultados pendentes são guardados por semana, entregues do mais antigo para
  o mais recente e não expiram. Sair da equipa depois do fecho não elimina um
  direito já criado.

### Momentos de pontuação

| Atividade | Momento local de contagem |
|---|---|
| Venda individual/em lote/mísseis | Venda efetivamente aceite; raridade comum +1, rara +3, épica +9; moldura dourada (`ts_availability_start=15`) acrescenta +3 |
| Missões concluídas | Resgate apenas de missões bem-sucedidas; missões de combate contam separadamente vitória/derrota |
| Energia geral e de tempo | Débito ao iniciar a missão; cancelar não remove os pontos; repetir o início não duplica |
| Habilidades | Apenas a parte paga com moedas, depois de gastar os pontos gratuitos disponíveis |
| HeroBook diário | Tema desativado; não pontua |
| Duelo | Resgate do atacante, com o resultado real da batalha |
| Vilão | Fecho de cada ataque terminado; derrota do vilão conta no resgate do prémio de cada participante elegível |
| Acompanhante | Cada nível efetivamente ganho; uma subida de vários níveis conta esse incremento |
| Treinos | Resgate de cada exercício e conclusão do bloco inteiro (terceira estrela) |
| Esconderijo | Glória positiva ganha em ataques ou defesas; produção automática e bots não pontuam |
| Modificações de equipamento | Produção ou aplicação: comum +3, rara +10; cada ação aceite conta separadamente |
| Objetos de coleção | Temas desativados; sem novos objetos nas ofertas |

Ofertas ainda não iniciadas são atualizadas uma vez por período, sem alterar
missões ativas. Os antigos objetos de HeroCon são retirados das ofertas ainda não iniciadas.
O sorteio em missões normais e a elegibilidade no resgate são políticas locais.

### Prémios e protocolo

A classificação individual entrega as faixas de habilidades do catálogo e
as referências exatas de caixa para as posições 1 e 2–5. A caixa tem preview
persistente em `items`, entregue à mochila no resgate. Mochila cheia devolve
`errInventoryNoEmptySlot`; `discard_item=true` descarta só a caixa, conservando
os pontos de habilidade e de temporada. O preview não pode ser usado/movido
antes do resgate.

Pontos de temporada vêm das localizações 3/4/5 da temporada que cobre o fecho;
são entregues apenas enquanto essa mesma temporada continua ativa. Não são
transferidos para a temporada seguinte. O booster não é entregue novamente
quando se resgata o resultado de equipa.

As oito ações documentadas estão ligadas a `actions/herocon.php`. Os estados
atual/próximo, contribuições, resultados pendentes e contador do fim são enviados
no login, `Live::accountState` e respostas parciais do próprio herói. As
classificações enviam os nomes compactos do cliente e preservam `rank` e `r`
na lista individual. O diálogo de progresso recebe grupos com pontuação acima,
abaixo e igual à da própria equipa. O prémio mantém as duas strings JSON
aninhadas exigidas pelo cliente.

Os contadores dos feitos de HeroCon são atualizados no resgate dos resultados,
e a visita uma vez por período. São enviados em `current_goal_values`.
A seleção de aparências e o resgate genérico de feitos/títulos dependem dos
respetivos sistemas do jogo. As caixas solo HeroCon já podem ser abertas por
`useInventoryItem`: grande entrega duas peças, pequena uma, segundo as descrições
oficiais em `assets/i18n/pt_BR/text.data`. As peças pertencem ao `solo_herocon_set`;
a hipótese de efeito `shc` é 20%, conforme `item_templates.effect_chance`.
A seleção entre peças elegíveis é uniforme (política local). Dá prioridade às
peças em falta, considerando itens existentes e histórico de aberturas guardado
em `character.goal_stats` (`herocon_box_collected_<identifier>`). Não reconstrói
peças vendidas antes desta implementação. Todos os equipamentos recebem borda dourada (`ts_availability_start=15`) e
espaço gratuito de modificação (`ts_availability_end=1`). Os atributos usam
`Character::shopItemStats`, a mesma fórmula da loja: nível do herói menos 0–4,
qualidade épica e bónus plano do esconderijo, com distribuição por 3–4 atributos. O acompanhante Orion tem referências de origem, mas
não há probabilidade de entrega confirmada: não foi acrescentado ao sorteio.
A abertura valida propriedade, mochila desbloqueada, nível e capacidade; usa
locks/transação/savepoint e só consome a caixa com a entrega dos prémios.
O espaço da caixa conta para a capacidade quando é consumida. Caixas antigas
com `charges=0` são de uso único. A resposta final da abertura contém apenas os prémios em `items`, como
o diálogo `DialogSurpriseBoxContent` exige. O filtro ocorre no Router depois
dos sistemas de eventos que podem reconstruir essa coleção; evita mostrar
equipamento antigo ou um terceiro objeto que não saiu na caixa. As restantes
coleções do estado mantêm-se e os objetos anteriores já existem no cliente.
Testes MySQL com rollback cobrem entrega, retry, mochila cheia, caixa pequena
sem espaço adicional e prioridade às peças em falta. Confirmação visual pendente.

### Persistência e migração

Novas tabelas: `herocon_control`, `herocon_cycles`, `herocon_contributions`,
`herocon_rank_times`, `herocon_views`, `herocon_rewards`.

```bash
php scripts/testing/migrate_herocon.php
```

Migração aplicada nesta sessão. É idempotente, cria as tabelas e acrescenta os
campos do booster a bases antigas que ainda não os tenham. Novas instalações
usam `docker/initdb/15_herocon.sql` e os campos já definidos no schema inicial.
Não há DDL nos pedidos de jogo. A transação da atividade abrange o progresso;
resgates usam locks e savepoints, e o fecho é serializado pelo controlo global.
O lock `herocon_control` só é pedido quando há ciclo por fechar/criar ou faltam
5 s ou menos para o fim do ciclo (`currentUnlocked()`); com o ciclo ativo,
`sync()` e `record()` leem o ciclo sem lock nem transação própria. A margem
mantém pontuação e fecho serializados na fronteira. Teste:
`scripts/testing/test_calendar_lock_free.php`.
Uma restrição única impede criar dois direitos para o mesmo herói/período/tipo.

### Validação

```bash
php scripts/testing/test_herocon.php
node scripts/testing/test_herocon_client.js
php scripts/testing/test_herocon.php --mysql --hero="Nome do herói"
```

O último prepara dados temporários e termina com rollback. Sem `--hero`, escolhe
um herói existente e identifica-o na saída. Não jogar com esse herói durante o teste.

Nesta sessão passaram: sintaxe de todos os PHP alterados; catálogo/pontos/
calendário offline; 300 combinações de prémios de equipa e 301 posições
individuais comparadas com as funções reais do JavaScript; integração MySQL
nos 13 temas, produtores reais de compra de habilidades/início de missão,
fecho idempotente, preview estável, proteção do preview, mochila cheia,
descarte, duplicados, booster e marcação de visita.

Regressões passaram: geração de missões offline/PDO simulado, HeroBook,
modificações de acompanhantes, recolha da oficina e histórias das zonas com
MySQL/rollback. O mock antigo de histórias falha no SQL do reset diário antes
de chegar aos hooks de HeroCon; não foi alargado nesta funcionalidade.
Confirmação visual no navegador e teste de concorrência entre processos ficam pendentes.

### Correção do catálogo e pontuação (2026-10-07)

A análise histórica do cliente acima inclui temas retirados. A rotação ativa inclui
duelos, combate em missões normais, vilões, energia, missões concluídas, energia
de missões de tempo, exercícios, blocos de treino, vendas, modificações, níveis
de acompanhantes, glória do esconderijo e compra de habilidades. Missões especiais,
HeroBook/emergências, produção de robôs, as duas coleções e a variante `sell_items2`
ficam fora. Um ciclo ativo desativado é fechado, preservando resultados e
contribuições, e substituído por um tema válido. Os totais acumulados passam a
ser pontos diretos; ações antigas não são reconstruídas. Os pesos não especificados
pelo utilizador (venda lendária +27 e modificação épica +20) mantêm as constantes.

Validação desta correção: `php -l` nos PHP alterados; HeroCon offline e
MySQL com rollback (13 temas, pontos diretos, bónus dourado aditivo e substituição
de tema desativado); comparação dos prémios com o cliente; geração de missões
offline/PDO simulado; regressões MySQL de modificações de acompanhantes e
recolha da oficina. A pontuação de glória em combate e a conclusão de blocos
de treino ainda precisam de confirmação manual no cliente; esses fluxos não
foram exercitados por estes testes de integração.
