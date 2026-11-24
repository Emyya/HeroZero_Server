# Patamares diários de duelos, liga e esconderijo

Extensão dos [patamares de energia](patamares_energia.md), com regras locais
pedidas pelo utilizador. `DailyMilestones.php` partilha sorteio, entrega e
protocolo dos quatro sistemas; `DailyEnergy.php` mantém a API das missões.

## Patamares e prémios

Cada patamar sorteia uma das cinco opções, com probabilidade de 20% cada.
Os sorteios são independentes e persistidos por herói/data/patamar/sistema.

| Duelos | Liga | Esconderijo | Épico | Energia em cupão | Treinos em cupão | Pontos livres | XP |
|---|---|---|---|---|---|---|---|
| 10 | 5 | 5 | Um objeto | 10 | 5 | 5 | 3% |
| 20 | 10 | 10 | Um objeto | 20 | 10 | 10 | 5% |
| 50 | 20 | 20 | Um objeto | 30 | 15 | 20 | 10% |

XP usa `ceil((xpForLevel(nível + 1) - xpForLevel(nível)) × percentagem / 100)`
no nível do resgate. Pontos livres vão para `stat_points_available`.
Energia e treinos geram cupões nas mensagens. Épicos usam o gerador existente,
o nível atual com offset zero e preview persistente. Mochila cheia preserva
prémio e ponto; `discard_item=true` vende o item e resgata o patamar.

Cada patamar resgatado dá um ponto de temporada: até três por dia nos duelos
e três na liga e no esconderijo, além dos três de energia.
Locais 9 (duelos), 10 (liga) e 11 (esconderijo)
anunciam um ponto em todos os temas, conforme o mapeamento do cliente.
O cupão não dá outro ponto quando usado.

## Contagem

Conta cada combate iniciado pelo herói, ganho ou perdido, apenas ao recolher
o resultado em `claimDuelRewards`, `claimLeagueFightRewards` ou
`collectHideoutFightRewards`. No esconderijo, só ataques a outro esconderijo
real contam; o combate fictício de tutorial fica excluído. Não conta
no início ou ao ver a batalha. Retomas, retries e pedidos inválidos não
contam duas vezes; defesas recebidas/resgatadas não contam.

Esta é uma regra local de **combates realizados**. O protocolo original chama
os campos `duel_battle_won`, `league_battle_won` e `hideout_battle_won`;
os nomes são preservados
por compatibilidade, mas representam aqui todos os resultados resgatados.
O cliente oficial pode continuar a usar texto de vitórias nos seus assets.

Os contadores são independentes e limitam-se a 50 duelos, 20 lutas de liga
e 20 ataques ao esconderijo.
O limite de ataques da liga e os custos de adrenalina mantêm as regras próprias.
Não se reconstrói histórico anterior à instalação nem há prémios retroativos.

À meia-noite do calendário do jogo (`GameTime`, fuso de `Live`), a primeira
consulta/atividade zera os contadores e renova os sorteios. Prémios por
resgatar expiram, previews são eliminados e IDs antigos são enviados inativos
para o mapa incremental do cliente. Cupões já entregues permanecem.
Lutas que atravessam a meia-noite contam no dia do resgate.

## Protocolo e persistência

- Tipo 2: `daily_bonus_lookup.duel_battle_won`, definições em
  `daily_bonus_reward_data["2"]` com chaves 10/20/50.
- Tipo 3: `daily_bonus_lookup.league_battle_won`, definições em
  `daily_bonus_reward_data["3"]` com chaves 5/10/20.
- Tipo 4: `daily_bonus_lookup.hideout_battle_won`, definições em
  `daily_bonus_reward_data["4"]` com chaves 5/10/20.
- `daily_bonus_rewards`: IDs persistentes, `type`, `value`, `status` e
  `rewards` como string JSON. Status 0 bloqueado, 1 disponível, 2 resgatado/expirado.
- `claimDailyBonusRewardReward(id,discard_item)` resolve o sistema pelo ID
  e valida dono, data, contador e estado. Erros e tipos de prémio iguais aos de energia.
  Os bots enviam também `type` para identificar o sistema quando a BD existente
  tem IDs iguais nas quatro tabelas. Sem `type`, o servidor seleciona um
  patamar disponível para manter o cliente original funcional.

Login, estado completo/parcial, configuração e respostas de resgate incluem
contadores, patamares e itens referenciados. A barra atualiza na resposta do
resgate, sem esperar por login ou por outra consulta.

`daily_duel_progress` e `daily_league_progress` guardam data/contador;
`daily_duel_rewards` e `daily_league_rewards` guardam sorteios/IDs/estado.
O esconderijo usa `daily_hideout_progress` e `daily_hideout_rewards`.
Índices únicos impedem duplicação por data/patamar. Locks do herói e
transações/savepoints incluem contagem, prémios, cupões, temporada e resgate.
DDL só corre na migração/initdb.

## Instalação e verificação

```bash
php scripts/testing/migrate_daily_energy.php
php scripts/testing/migrate_daily_combats.php
php scripts/testing/migrate_daily_hideout.php
php scripts/testing/test_daily_combats.php
php scripts/testing/test_daily_combats.php --db "Nome do herói"
php scripts/testing/test_daily_energy.php --db "Nome do herói"
node scripts/testing/test_daily_energy_client.js
```

Migração idempotente em `docker/initdb/10_daily_combats.sql`, executada na
instalação atual. Cria quatro tabelas sem modificar saldos ou dar prémios.
`11_daily_hideout.sql` e `migrate_daily_hideout.php` acrescentam duas tabelas,
também com migração idempotente executada nesta instalação.

Integração MySQL com rollback passou: todas as 45 opções dos nove patamares,
limiares, sorteios/IDs estáveis, dono errado, duplicados, cupões, pontos livres,
XP, épicos/previews, mochila cheia/descarte, expiração, resgates reais de
vitórias e derrotas, tutorial/defesas sem contagem e estado/login. A regressão de
energia passou. O teste Node usa as funções reais do bundle para as quatro
barras, legendas, máscaras e reset. Confirmação visual no browser pendente.

## Pontos livres no diálogo (BUG-000150)

O JSON `rewards` envia `statPoints` (camelCase), campo lido por
`Reward.get_statPoints()` e pelo diálogo de resgate do cliente. A coluna do
herói continua a ser `stat_points_available`. Linhas antigas com `stat_points`
são normalizadas ao apresentar/resgatar, sem duplicar o crédito nem alterar o
sorteio. O campo incorreto permitia creditar pontos mas ocultava-os no diálogo.

```bash
node scripts/testing/test_daily_milestone_rewards_client.js
```

O teste executa o parser de recompensas e o diálogo reais do bundle e verifica
5/10/20 pontos mais temporada nos quatro sistemas. Integração MySQL com rollback
verifica a normalização de previews antigos e a entrega única do prémio.
Confirmação visual no browser pendente.
