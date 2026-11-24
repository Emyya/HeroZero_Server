# Patamares diários de energia gasta

Implementação partilhada em `DailyMilestones.php`, com API em `DailyEnergy.php`. Regras de balanceamento local definidas
pelo utilizador; não são apresentadas como tabela oficial das constantes.

## Prémios

Cada patamar sorteia **uma** das cinco opções, independentemente dos outros,
com probabilidade igual de 20%. O sorteio fica persistido por herói/data/patamar:
consultas, login e atualização de missões não permitem voltar a sortear.

| Energia gasta | Épico | Energia em cupão | Unidades em cupão | Pontos livres | XP |
|---|---|---|---|---|---|
| 100 | Um objeto | 10 | 5 | 5 | 3% |
| 200 | Um objeto | 20 | 10 | 10 | 5% |
| 300 | Um objeto | 30 | 15 | 20 | 10% |

XP = `ceil((xpForLevel(nível + 1) - xpForLevel(nível)) × percentagem / 100)`.
Usa o nível atual no resgate e a tabela de XP do cliente; não a XP que ainda
falta ao herói nem o valor acumulado do próximo nível. O preview acompanha
o nível atual. A entrega sincroniza a subida de nível na mesma transação.

Cada patamar resgatado dá **1 ponto de temporada**, até 3 por dia. O local 17
de `season_locations` anuncia 1 em todos os temas. O ponto é entregue no
resgate do patamar, não no resgate posterior do cupão.

Energia e unidades geram `UserVouchers` e ficam nas mensagens; não alteram
imediatamente o saldo. Pontos vão para `stat_points_available` para gastar.
Objetos são épicos de equipamento (tipos 1–7), com preview persistente,
gerador existente e offset de nível zero. Mochila cheia preserva o prémio e
o ponto. `discard_item=true` vende o objeto pelo seu preço e resgata o patamar,
incluindo o ponto de temporada. São respeitadas as bolsas desbloqueadas por nível.

## Contagem e mudança de dia

Conta o `energy_cost` apenas ao resgatar a missão concluída em
`claimQuestRewards`, na mesma transação das recompensas. Iniciar, terminar
o temporizador ou cancelar não avança o contador. Assim, não é possível
resgatar um patamar com energia de uma missão que ainda pode ser cancelada.
Retries, falta de energia e pedidos inválidos não avançam (BUG-000149).
Cancelar continua sem devolver energia ao saldo do herói. Comprar energia,
resgatar cupões e gastar energia de treino não contam. O contador apresentado
limita-se a 300: não há quarto prémio.

Na meia-noite do calendário do jogo (`GameTime`, fuso de `Live`), o contador
volta a zero e os sorteios renovam na primeira consulta/atividade. Prémios
anteriores por resgatar expiram e os seus previews são eliminados; cupões já
entregues permanecem nas mensagens. Não se reconstrói energia gasta antes da
instalação nem se concedem prémios retroativos com base em fixtures/HARs.

## Protocolo e persistência

- `daily_bonus_lookup.quest_energy_spent`: contador vivo, de 0 a 300.
- `daily_bonus_reward_data["5"]`: mapas 100/200/300 com `reward_type`,
  `reward_value` e `reward_identifier`. Tipos: 1 energia, 3 objeto, 4 treino,
  5 pontos livres, 9 percentagem de XP.
- `daily_bonus_rewards`: `{id,type:5,value,status,rewards}`; `rewards` é string
  JSON, status 0 bloqueado, 1 disponível, 2 resgatado/expirado.
- `claimDailyBonusRewardReward(id,discard_item)`: valida dono, data, contador
  e estado; falhas usam `errCollectDailyBonusRewardInvalidStatus` ou
  `errInventoryNoEmptySlot` conforme o caso.
  O pedido pode incluir `type` (2/3/4/5) para resolver IDs repetidos entre
  tabelas numa instalação antiga. Sem `type`, o servidor escolhe um patamar
  disponível com esse ID; o cliente HTML5 continua compatível.

Login, estado completo, `Live::overlayAccount` e `getDailyBonusRewardData`
incluem os campos reais e os itens referenciados.
As respostas diretas de `startQuest`, `instantFinishQuest`,
`checkForQuestComplete` e `claimQuestRewards` também enviam contador,
patamares e previews. A barra avança na resposta ao resgate da missão,
sem aguardar a próxima sincronização (BUG-000148/BUG-000149).
IDs antigos são enviados inativos, com recompensas vazias após expirar,
para atualizar o mapa incremental
do cliente sem reabrir diálogos antigos. Duelos (tipo 2), liga (tipo 3) e esconderijo (tipo 4) também usam regras locais: ver
[patamares de combates](patamares_combates.md). O tipo 1 mantém a
configuração capturada.

`daily_energy_progress` guarda a data/contador atual por herói;
`daily_energy_rewards` guarda os sorteios/IDs/estado. Índice único impede
duplicar um patamar da mesma data. Locks do herói serializam contagem,
criação e resgate; transações/savepoints incluem prémio, cupão, ponto e marcação.
DDL só acontece na migração, nunca durante pedidos de jogo.

## Instalação e verificação

```bash
php scripts/testing/migrate_daily_energy.php
php scripts/testing/migrate_daily_combats.php
php scripts/testing/migrate_daily_hideout.php
php scripts/testing/test_daily_energy.php
php scripts/testing/test_daily_energy.php --db "Nome do herói"
node scripts/testing/test_daily_energy_client.js
```

Migração idempotente também incluída em `docker/initdb/09_daily_energy.sql`.
Cria duas tabelas; não concede prémios nem altera saldos ou contadores existentes.
Foi executada na instalação atual.

Lint PHP, catálogo offline e integração MySQL com rollback passaram: todas as
15 opções, sorteio/IDs estáveis, limiares, duplicados, dono errado, cupões,
pontos livres, XP/subida de nível, épicos/previews, mochila cheia/descarte,
três pontos por dia, expiração, início/cancelamento sem contagem antecipada,
resgate real da missão e retry, login e estados
normal/parcial. Testes existentes de cupões e bónus diários também executados.
O teste Node executa o merge e a barra reais do bundle: contador, legenda
e máscara acompanham cada resposta e o reset diário sem outro pedido HTTP.
Confirmação visual no browser continua pendente.

Contagens e prémios anteriores à correção não são recalculados automaticamente.
Missões que atravessam a meia-noite contam no dia do resgate.

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
