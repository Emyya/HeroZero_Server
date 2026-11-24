# Cupões das mensagens

Sistema de prémios pessoais guardados para resgate posterior. É distinto da
janela de códigos promocionais, embora o cliente partilhe `redeemVoucher`.

## Persistência e protocolo

`UserVouchers.php` reutiliza as tabelas existentes `vouchers` e
`voucher_redemptions`. Códigos internos `msg_<character_id>_<aleatório>` têm
utilizador proprietário, um único uso e JSON de recompensas. Não são campanhas
públicas. O prefixo inclui o herói; nem outro herói da mesma conta os pode usar.
O sistema não precisa de uma tabela nova.

Login, estado completo, overlays parciais e `getStreamMessages` enviam:

- `user_vouchers`: lista de `{id, code, rewards}`, apenas não resgatados;
  `rewards` é uma string JSON, como exige `UserVoucher.get_reward()` no JS.
- `streams_info.v.<character_id>`: identificação do stream e contador não lido.
- `character.new_user_voucher_ids`: string JSON de IDs não lidos.
- `voucher_rewards`: objeto agregado após o resgate.

Abrir o stream `v` marca como lido, sem consumir os cupões. Para esses registos
internos, `ts_start` guarda a leitura (zero = não lido); não é início de campanha.
Não se aplica esta convenção aos códigos promocionais existentes.

`redeemVoucher(code)` resgata um; `redeemUserVouchers(codes)` recebe **array
JSON**, não texto separado por vírgulas. Cliente agrupa cupões equivalentes,
filtra tipos e usa-os nas janelas de falta de energia/unidades de treino.

## Recompensas e segurança

Energia `quest_energy`, unidades `training_sessions`, supercola `hideout_glue`
e zenit `hideout_stone` ficam guardados nas mensagens até serem resgatados.
Treino aumenta `character.training_count`: não reduz esse contador nem aumenta
a barra `training_energy`. Energia e unidades podem ultrapassar o máximo diário
(prémio extra); limite técnico das colunas SMALLINT é verificado sem truncar.
As sincronizações `syncDailyReset()` e `syncTrainingForLevel()` preservam esse
excedente no mesmo dia, incluindo após resgate, login e consultas de estado.
A passagem de dia mantém as regras existentes de reposição e armazenamento.

`cupoes.har` demonstrou 117/120 + cupão de 50 reduzidos a 120, e 22 unidades
+ cupão de 4 mantidas em 22. O resgate somava corretamente, mas os dois métodos
de sincronização truncavam o saldo ao gerar a resposta. Foram removidos esses
cortes; o teste MySQL reproduz 167 energias e 26 unidades pelo handler real e
confirma a persistência no estado seguinte, com rollback de todos os dados.

Supercola/zenit só são consumidos se o depósito tiver espaço para todo o lote.
Sem depósito/espaço, o pedido falha com o erro reconhecido pelo JS, preservando
todos os cupões. Não existe consumo parcial silencioso.

Energéticos: `quest_booster`, `stats_booster`, `work_booster`, `sense_booster`,
`league_booster`, `multitasking_booster` e `hideout_booster`. JSON tem
`{type: quantidade_percentual_do_catalogo, duration: quantidade}`; dias para
missão/atributos/trabalho/liga/esconderijo, horas para sentido/multitarefas,
conforme interpretação do JS. O energético é resolvido no catálogo servido
por `Live`, ativado gratuitamente e prolonga a validade existente. Não se
substitui um energético superior ainda ativo por um inferior: fica por resgatar.
O energético de esconderijo requer as duas colunas criadas pela migração abaixo;
esta alteração não reimplementa todos os seus efeitos no backend.

Resgate bloqueia herói e cupões, valida dono/estado/tipo, entrega e marca uso
numa única transação; usa savepoint quando já existe transação. Duplicados,
lotes inválidos e falhas revertem as alterações. A janela de códigos promocionais
mantém o caminho anterior para códigos que não pertençam ao sistema pessoal.

## Fontes dos prémios

Patamares diários de energia gasta nas missões (100/200/300) podem gerar
cupões de 10/20/30 energias ou 5/10/15 unidades de treino. O ponto de temporada
é entregue ao resgatar o patamar; usar o cupão não concede outro ponto.
O contador só avança ao resgatar missões concluídas; iniciar ou cancelar
não permite desbloquear os prémios destes patamares.
Ver [patamares de energia](../missoes/patamares_energia.md).

Duelos (10/20/50) e liga (5/10/20) também sorteiam cupões de 10/20/30
energias ou 5/10/15 treinos, com as mesmas regras de entrega e temporada.
Ver [patamares de combates](../missoes/patamares_combates.md).

Chamadas heroicas: os quatro tipos numéricos acima geram cupões na mesma
transação de resgate e pontos da temporada. XP, moedas, donuts, atributos e
equipamento continuam a ser entregues pelos caminhos próprios. Recursos já
resgatados anteriormente não são convertidos nem compensados automaticamente.

Passe: mapeamento confirmado em `et.refreshWithoutReward()` do JavaScript:
tipo 4 = XP, 6 = unidades de treino, 8 = energia, 11 = supercola, 12 = zenit.
Energia e unidades usam `reward_factor` diretamente; não existe ×5 na energia.
As respostas enviam JSON com as mesmas chaves dos cupões. XP usa a escala
local anterior ×10000, agora no tipo correto. Supercola/zenit usam provisoriamente
`factor` como quantidade local: o JS mostra `???` para esses prémios e não
fornece a fórmula oficial do backend. Tipo 3 continua a dar pontos gratuitos.

`resgate.har` mostrou 6 resgates tipo 8 sem cupão (340 energias), indevidamente
tratados como XP, e 6 prémios tipo 12 entregues como energia em vez de zenit.
Treino tipo 6 gerou corretamente cupões de 5 e 10 unidades. A conclusão anterior
de que o factor 12 significava 60 energias era errada: no mesmo patamar o prémio
de 60 energias é o tipo 8 da faixa premium, não o tipo 12 da faixa gratuita.
Não se altera automaticamente o histórico da BD.

Recuperação explícita dos casos demonstrados no HAR:

```bash
php scripts/testing/repair_season_coupons_from_har.php "skel" resgate.har
```

Valida herói e prémios ainda marcados como resgatados; cria os cupões de energia
em falta e corrige os cupões de zenit identificados no HAR apenas se não usados.
Transação, marca estável por progresso/recompensa e verificações de estado
impedem repetir a compensação, mesmo com outro HAR do mesmo caso.
XP/atributos anteriormente entregues por erro não são removidos e cupões usados
não são alterados. Não utilizar HAR de antes de um reset do passe.

## Instalação e teste

```bash
php scripts/testing/migrate_user_vouchers.php
php scripts/testing/prepare_user_vouchers.php "skel"
php scripts/testing/test_user_vouchers.php --offline
php scripts/testing/test_user_vouchers_mock.php
php scripts/testing/test_season_coupon_mapping.php resgate.har
php scripts/testing/test_user_vouchers.php
# Opcional: escolher o herói; teste de integração usa rollback.
php scripts/testing/test_user_vouchers.php "skel"
```

Migração idempotente: acrescenta `active_hideout_booster_id` e
`ts_active_hideout_boost_expires` ao personagem, fora de transação; verifica
tabelas existentes. O schema inicial também inclui as colunas.

Preparação persiste cinco cupões: energia 20, treino 5, cola 10, zenit 10,
energético de missões 50% por um dia. Repetir acrescenta mais cinco.
Recarregar, abrir Mensagens → Cupões e resgatar. Recursos com depósito cheio
devem continuar disponíveis; cupões usados não reaparecem ao relogar.

Teste offline cobre parâmetros JSON, duplicados e catálogo de energéticos.
Integração usa rollback e cobre persistência/notificações, leitura sem consumo,
resgate conjunto, extra acima do máximo diário, repetição, dono errado,
falha do lote, depósito cheio e geração pelas chamadas/passe.
Offline, sintaxe e resgate com PDO simulado verificados nesta sessão. O teste
simulado cobre lote, rollback por depósito cheio, duplicados, proprietário,
extensão do energético e savepoint/rollback exterior, mas não valida o SQL
contra MySQL. MySQL inacessível, logo integração e confirmação visual do
novo fluxo ainda pendentes.

Os patamares de 5/10/20 ataques ao esconderijo também podem gerar cupões de
10/20/30 energias ou 5/10/15 treinos, seguindo as regras dos duelos/liga.
Ver [patamares do esconderijo](../hideout/patamares_combates.md).
