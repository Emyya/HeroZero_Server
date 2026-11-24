# Scripts de preparação, manutenção e testes

Catálogo de scripts PHP, JavaScript e SQL em `scripts/testing/`, incluindo
`trofeus/`. Revisão inicial em 2026-10-04; documentação dos scripts de eventos
completada por leitura do código em 2026-10-05. Descrever um teste aqui não
significa que passou ou que foi executado.

## Como utilizar

Executar os comandos a partir da raiz do repositório. Nas tabelas, o caminho do
ficheiro é relativo a `scripts/testing/`; a coluna «Argumentos» mostra o que deve
seguir o nome do ficheiro. `[...]` indica um argumento opcional e não deve ser
escrito literalmente.

```bash
php scripts/testing/give_donuts.php "Nome do herói" 1000
php scripts/testing/test_daily_bonus.php --db "Nome do herói"
node scripts/testing/test_daily_bonus_client.js
```

- **Persistente:** guarda alterações na BD. Repetir pode voltar a dar recursos,
  criar itens ou limpar progresso, conforme a operação.
- **Migração/reparação:** altera schema ou dados existentes; não é um teste com rollback.
- **MySQL + rollback:** prepara cenários e reverte alterações de dados na transacção
  do teste. Isso não reverte DDL, ficheiros, caches nem alterações por outra ligação.
- **Offline:** lê código, constantes ou HARs. **PDO simulado:** testa operações com
  uma BD em memória simulada. Nenhum dos dois prova integração com MySQL.

PHP requer o `vendor/` do servidor; os testes do painel requerem o do admin.
Scripts MySQL usam a configuração Laravel respectiva. O compose local expõe a BD
na porta 3308; verificar se está acessível. Os scripts não exigem, em geral, que o
servidor HTTP esteja ligado. O teste JavaScript requer Node.js.

Os scripts com nome de herói aceitam o nome real, entre aspas. Alguns testes
antigos usam IDs fixos ou escolhem um personagem automaticamente: isso está
assinalado abaixo. Ler toda a saída: alguns handlers de excepção ou testes antigos
podem apresentar falhas mesmo com código de saída zero.

## Recursos e preparação manual — alterações persistentes

| Ficheiro | Argumentos | Finalidade e efeito |
|---|---|---|
| [advance_game_time.php](../scripts/testing/advance_game_time.php) | `DIAS` ou `--help` | Avança o relógio global em `DIAS × 86400` segundos, somando ao offset actual em `server-laravel/storage/game_time_offset`. Aceita inteiro positivo, mostra datas antes/depois e persiste entre reinícios. Não requer MySQL. O número «Dia N» de uptime não muda, e não resgata prémios automaticamente; afecta os prazos e calendários de todo o jogo. |
| [give_donuts.php](../scripts/testing/give_donuts.php) | `"Herói" quantidade` ou `--help` | Acrescenta donuts ao saldo da conta, sem o substituir. Valida nome único, conta, inteiro positivo e limite da coluna; locks e transacção. Cada execução credita novamente. Mostra saldo antes/depois. `--help` não acede à BD. |
| [fill_hideout_resources.php](../scripts/testing/fill_hideout_resources.php) | `"Herói"` | Enche cola e pedra até aos máximos efectivos do esconderijo, recalculados a partir das salas. Exige esconderijo e capacidade de depósito; não aumenta os máximos. |
| [give_improvement_slot_item.php](../scripts/testing/give_improvement_slot_item.php) | `"Herói" [equipment\|sidekick]` | Coloca na mochila um consumível para abrir espaço de modificação em equipamento ou pet. Por defeito `equipment`; não abre o espaço automaticamente. `sidekick` pode também aplicar a migração de pets. |
| [prepare_item_improvement.php](../scripts/testing/prepare_item_improvement.php) | `"Herói" [ID_EQUIPAMENTO]` | Selecciona equipamento equipado elegível, abre um espaço vazio e cria uma gema épica compatível na mochila, com +1000 nos quatro atributos. Sem ID, escolhe automaticamente; preserva melhorias já aplicadas e exige vaga. A aplicação final é manual no jogo. |
| [prepare_sidekick_improvement.php](../scripts/testing/prepare_sidekick_improvement.php) | `"Herói" [ID_PET] [RARIDADE]` | Abre espaço num pet sem melhoria aplicada e cria a gema correspondente na mochila. ID `0` escolhe automaticamente; raridade 1–4, por defeito 4. Atributos base +1000 cada, depois escalados pelo nível do pet. Aplica/verifica schema antes da transacção. |
| [update_item_improvement.php](../scripts/testing/update_item_improvement.php) | `"Herói" ID_GEMA [VALOR]` ou `"Herói" ID_GEMA resistência força crítico esquiva` | Substitui os quatro atributos de uma gema do herói, mesmo já aplicada. Sem valores usa 1000 em todos; um valor é repetido nos quatro. Aceita 0–8388607 por atributo. Não soma aos atributos anteriores. |
| [prepare_user_vouchers.php](../scripts/testing/prepare_user_vouchers.php) | `"Herói"` | Cria cinco cupões pessoais: 20 energia, 5 unidades de treino, 10 cola, 10 pedra e energético de missões 50% por um dia. Repetir cria mais cinco. Disponíveis em Mensagens → Cupões. |
| [set_season_points.php](../scripts/testing/set_season_points.php) | `"Herói" PONTOS` | Define o total de pontos da temporada atual (0–2147483647), sem somar nem limpar resgates/desbloqueios. Operação persistente com transação e lock; avança temporadas expiradas pela regra normal. `--help` não acede à BD. |
| [reset_season_for_testing.php](../scripts/testing/reset_season_for_testing.php) | `"Herói" [PONTOS\|keep] [0\|1\|keep]` | Limpa resgates e desbloqueios da temporada para voltar a testar. Pontos e premium mantidos por defeito; `0/1` desliga/liga premium. Conserva prémios já recebidos, permitindo recebê-los novamente. Guarda o progresso anterior em `server-laravel/storage/season-debug-backups/`; pode renovar a janela expirada. |

### Avançar o relógio interno

```bash
php scripts/testing/advance_game_time.php 3
```

Avança três dias de 24 horas: **259200 segundos**. O relógio usa segundos, não
milissegundos. Soma o avanço ao desvio já existente, sem alterar o relógio do
computador. A alteração é global e persistente; cada execução avança novamente.
Recarregar o cliente sincroniza imediatamente; a barra também consulta o relógio
periodicamente. O número «Dia N» da barra continua baseado no uptime e não no offset.
Ao atravessar a mudança da hora, três períodos de 24h podem mudar a hora local uma
hora. O script não entrega logins de dias saltados: aplica-se a regra de resgate
do [login diário](logins/login_diario_e_bonus.md).

No passe, `php scripts/testing/advance_game_time.php 30` avança exatamente um
ciclo global: todas as contas passam ao tema seguinte no próximo acesso, com
as mesmas datas e aproximadamente o mesmo tempo restante. O novo ciclo limpa
pontos/premium/resgates/desbloqueios, conservando os prémios já recebidos.
Detalhes em [temporadas](seasons/atual.md#relação-com-o-relógio-interno-e-avanço-de-30-dias).

Sintaxe, argumentos e persistência verificados com ficheiros temporários;
o relógio real do projecto não foi avançado durante a verificação.

### Creditar donuts

```bash
php scripts/testing/give_donuts.php "Nome do herói" 1000
```

A quantidade é acrescentada a `user.premium_currency` da conta associada ao herói.
Recarregar o jogo actualiza a interface. A operação é persistente e cada execução
acrescenta a quantidade novamente. O script usa parâmetros SQL, transacção e locks;
falhas revertem o crédito. No registo original da criação, sintaxe e argumentos
foram verificados offline; a integração MySQL deste script ficou pendente.

## Migrações e reparações — alterações persistentes

| Ficheiro | Argumentos | Finalidade e efeito |
|---|---|---|
| [migrate_bank_inventory.php](../scripts/testing/migrate_bank_inventory.php) | Nenhum | Actualiza o cofre para 450 posições, capacidade/índices novos e `locked_item_ids`. Guarda dados anteriores em `bank_inventory_legacy_backup` e marca a conversão em `herozero_bank_migration` para não reconverter na repetição. DDL fora da transacção de conversão. |
| [migrate_sidekick_improvements.php](../scripts/testing/migrate_sidekick_improvements.php) | Nenhum | Cria/verifica `sidekicks.item_improvement_id` e corrige tipos de consumíveis antigos: gemas de pets para 12 e consumível de espaço para 14. |
| [migrate_user_vouchers.php](../scripts/testing/migrate_user_vouchers.php) | Nenhum | Acrescenta, se faltarem, campos de energético do esconderijo no personagem. Verifica existência de `vouchers` e `voucher_redemptions`; **não cria essas tabelas** se faltarem. |
| [migrate_season_calendar.php](../scripts/testing/migrate_season_calendar.php) | Nenhum | Cria/verifica `season_calendar` e fixa a origem pelo primeiro progresso existente ou pelo relógio interno se não houver progressos. Idempotente; exige ausência de transação. Não altera progressos diretamente: no próximo acesso, contas sincronizam tema/datas, podendo limpar progresso de um tema diferente ou expirado. Objetos/saldos permanecem. |
| [migrate_daily_bonus.php](../scripts/testing/migrate_daily_bonus.php) | Nenhum | Executa o schema idempotente `05_daily_bonus.sql`: tabelas de login diário, eventos, calendário e marcadores de recursos. Exige ausência de transacção; não activa eventos nem atribui prémios. |
| [repair_missiles.php](../scripts/testing/repair_missiles.php) | `["Herói"] [--apply]` | Sem `--apply`, apenas lista candidatos. Com a opção, converte munições antigas reconhecidas para tipo 8, 100 cargas e atributos correctos, devolve gemas à mochila e retira equipamento incompatível. Sem nome abrange todos os heróis. Guarda backups em `missiles_items_backup` e `missiles_inventory_backup`. Repetir não recarrega objectos já convertidos. |
| [repair_season_coupons_from_har.php](../scripts/testing/repair_season_coupons_from_har.php) | `"Herói" [CAMINHO_HAR]` | Repara resgates antigos comprovados pelo HAR: cria cupões de energia em falta e corrige cupões de pedra que tinham sido criados como energia. HAR por defeito: `resgate.har` na raiz. Valida correspondência do herói e progresso; ignora cupões usados/já tratados. Não retira XP/atributos anteriormente recebidos. Guarda as alterações. |
| [item_improvements.sql](../scripts/testing/item_improvements.sql) | Ficheiro SQL, não executar com PHP | Reparação idempotente dos tipos de gemas de equipamento (12), consumível de espaço de equipamento (13) e de pet (14). Não altera schema ou atributos. Aplicar através de uma ligação MySQL configurada para a BD do jogo; não contém rollback automático. |

## Testes offline e com PDO simulado

Executar com `php scripts/testing/FICHEIRO.php`, salvo o teste Node.js.

| Ficheiro | Argumentos | O que verifica |
|---|---|---|
| [test_quest_generation.php](../scripts/testing/test_quest_generation.php) | Nenhum | Fórmulas de XP/moedas, dificuldade, distribuição de missões e energéticos. Sorteios estatísticos e geração real com PDO simulado, preservação de missões e expiração de boosters. |
| [test_shop_purchase.php](../scripts/testing/test_shop_purchase.php) | Nenhum | Compra para mochila/equipamento com PDO simulado: destino, propriedade, capacidade, saldo e savepoints. |
| [test_story_dungeon_mock.php](../scripts/testing/test_story_dungeon_mock.php) | Nenhum | Histórias com PDO simulado: progresso, objectivos, recompensas, erros, rollback e savepoints. SQL desconhecido faz o teste falhar. |
| [test_user_vouchers_mock.php](../scripts/testing/test_user_vouchers_mock.php) | Nenhum | Cupões com PDO simulado: propriedade, resgates repetidos, recursos/capacidade e rollback/savepoints. |
| [test_herobook_season_client.js](../scripts/testing/test_herobook_season_client.js) | Nenhum; executar com Node | Offline: usa as constantes do backend via PHP e executa funções reais do bundle para os cinco cartões das chamadas nos 80 temas. Confirma ícone/quantidade 1/5 e ocultação com passe inativo; componentes gráficos simulados, sem BD. |
| [test_season_stat_points.php](../scripts/testing/test_season_stat_points.php) | Nenhum | Entrega de pontos gratuitos dos tipos da temporada, sem creditar donuts indevidamente, com PDO simulado. |
| [test_season_coupon_mapping.php](../scripts/testing/test_season_coupon_mapping.php) | `[CAMINHO_HAR]` | Confere no JS os tipos de XP, treino, energia, cola e pedra e a serialização no backend. Se existir HAR indicado ou `resgate.har` por defeito, conta resgates sem cupão e recursos trocados. Não repara dados. |
| [test_login_improvement_contract.php](../scripts/testing/test_login_improvement_contract.php) | `[CAMINHO_HAR]` | Verifica no código se o login inclui modificações. Com HAR, confirma que as gemas referenciadas pelo equipamento existem nas respostas. Não executa login contra MySQL. |
| [test_season_boot_contract.php](../scripts/testing/test_season_boot_contract.php) | `[CAMINHO_HAR]` | Verifica pré-carregamento de recompensas no código do login. Com HAR, exige duas aberturas do painel da temporada com estados iguais e sem bloqueios falsos. |
| [test_workshop_contract.php](../scripts/testing/test_workshop_contract.php) | `[CAMINHO_HAR]` | Contrato da oficina: `improvement_slot`, migração da forma antiga, catálogo e pré-visualizações. HAR opcional para verificar as respostas capturadas. |
| [test_workshop_upgrade.php](../scripts/testing/test_workshop_upgrade.php) | `[CAMINHO_HAR]` | Construção/evolução da oficina e normalização de salas manuais, preservando produção real/automática. Usa PDO simulado para os objectivos. HAR opcional para verificar estados das salas. |
| [test_daily_bonus_client.js](../scripts/testing/test_daily_bonus_client.js) | `[CAMINHO_HAR]`; usar `node` | Executa funções extraídas do bundle: meia-noite em datas simuladas, mudanças de hora, numeração num dia de 23 horas e ausência de diálogo com calendário vazio usando o `DataObjectArray` real. Com HAR, valida os calendários das respostas sem expor pedidos/sessões. |

## Testes com modo offline e integração MySQL

| Ficheiro | Modo offline | Integração e efeitos temporários |
|---|---|---|
| [test_daily_bonus.php](../scripts/testing/test_daily_bonus.php) | Sem argumentos: catálogo, quantidades locais, continuação após 30 dias e calendário. | `--db ["Herói"]`: login real/estado normal, resgate único, prémios, inventário, eventos, missões, energia e treinos. Sem nome escolhe a primeira conta com herói. Usa rollback e partilha PDO com Laravel para abranger torneios; relógio alterado apenas em memória. |
| [test_herobook.php](../scripts/testing/test_herobook.php) | `--offline`: fórmulas e validações das chamadas heroicas. | Sem argumentos: escolhe automaticamente herói de nível 40+, testa objectivos/progresso/resgates com rollback. Não aceita nome de herói. |
| [test_story_dungeon.php](../scripts/testing/test_story_dungeon.php) | `--offline`: 14 histórias, etapas, tipos e fórmulas. | `--hero="Herói"` ou sem argumentos para seleccionar automaticamente um herói de nível 9+. Prepara progresso, prémios e consumos dentro de transacção com rollback. |
| [test_user_vouchers.php](../scripts/testing/test_user_vouchers.php) | `--offline`: parsing, validação, catálogo de boosters e tipos da temporada. | Sem argumentos: usa o primeiro personagem, cria/resgata cupões e verifica duplicação/propriedade/capacidade com rollback. Não aceita nome de herói. |
| [test_season_rewards.php](../scripts/testing/test_season_rewards.php) | `--stats-only`: atributos e orçamento de gemas por nível/raridade. | `["Herói"]`: prepara pontos/premium/resgates e testa entrega de atributos, equipamento, mísseis, pets e gemas. Sem nome escolhe um personagem com inventário, por nível descendente. Usa rollback. |

## Testes de integração MySQL

Estes scripts acedem à BD logo que necessários; não têm uma opção offline isolada,
mesmo quando incluem algumas verificações iniciais sem BD.

| Ficheiro | Argumentos/alvo | O que verifica e efeito |
|---|---|---|
| [test_bank_inventory.php](../scripts/testing/test_bank_inventory.php) | `["Herói"]`; sem nome procura cofre com primeiro item ocupado | Movimentos, propriedade, bloqueios e operações do cofre; exige itens para o cenário. Usa rollback e compara o cofre original. |
| [test_item_improvements.php](../scripts/testing/test_item_improvements.php) | Nenhum; primeiro personagem com inventário e item existente como template | Aplicação/substituição de gemas, compatibilidade, venda e eliminação da melhoria associada. Cria itens temporários e usa rollback. |
| [test_sidekick_improvements.php](../scripts/testing/test_sidekick_improvements.php) | Nenhum; primeiro pet activo existente | Aplicação de raridades, escalamento, rejeições e eliminação da melhoria ao libertar/fundir. Exige schema de melhorias de pets já migrado; usa rollback. |
| [test_missiles.php](../scripts/testing/test_missiles.php) | `["Herói"]`; sem nome escolhe conta com inventário | Dano, cargas, cinto, recarga, compras, temporada, duelos/liga e retomas, missões, treino e histórias. Exige duas contas existentes para PvP e uma missão para o herói. Prepara nível/saldos/inventário dentro da transacção e reverte no fim. |
| [test_bonus_admin.php](../scripts/testing/test_bonus_admin.php) | Nenhum; BD/configuração do **admin** | Middleware de autenticação, renderização da página e controladores de criação/sobreposição/cancelamento de eventos. Usa sessão em memória e rollback; não é teste visual num browser. |
| [trofeus/test_trophies.php](../scripts/testing/trofeus/test_trophies.php) | Nenhum; guildas fixas 1/2 e personagens do cenário | Roubo, emissão, falta de capacidade e action `releaseArtifact`, com custos/permissões/cooldown. Usa rollback. |
| [trofeus/test_battle_trophies.php](../scripts/testing/trofeus/test_battle_trophies.php) | Nenhum; guildas fixas 1/2 | Cria batalha fictícia e verifica o gancho de troféus durante a resolução. Altera capacidades/troféus apenas no cenário; usa rollback. |
| [trofeus/test_effects.php](../scripts/testing/trofeus/test_effects.php) | Nenhum; guildas/personagens fixos no código | Efeitos agregados, máximos, custo/recarga de duelos e penalizações em batalha. Usa rollback. Há duas expectativas antigas de +5 na barra de energia de treino que divergem do código actual, que aplica o troféu às unidades de treino; ler as linhas `FAIL`, não apenas o exit code. |

## Ficheiros auxiliares e índices

| Ficheiro | Utilização |
|---|---|
| [season_coupon_har.php](../scripts/testing/season_coupon_har.php) | Biblioteca, não comando autónomo. Define `seasonCouponHar($path)` para analisar resgates da temporada e devolver apenas dados relevantes de prémios, sem tokens/pedidos completos. Usada pelo teste de mapeamento e pela reparação de cupões. |
| [README.md](../scripts/testing/README.md) | Entrada rápida da pasta, com ligação a este catálogo. |
| [trofeus/README.md](../scripts/testing/trofeus/README.md) | Descrição detalhada dos três cenários de troféus e dos respectivos pré-requisitos. |

## Exemplos de utilização

Preparação persistente para testes manuais:

```bash
php scripts/testing/fill_hideout_resources.php "Nome do herói"
php scripts/testing/give_improvement_slot_item.php "Nome do herói" equipment
php scripts/testing/prepare_item_improvement.php "Nome do herói"
php scripts/testing/prepare_sidekick_improvement.php "Nome do herói" 0 4
php scripts/testing/prepare_user_vouchers.php "Nome do herói"
```

Pré-visualização e aplicação explícita de uma reparação de mísseis:

```bash
php scripts/testing/repair_missiles.php "Nome do herói"
php scripts/testing/repair_missiles.php "Nome do herói" --apply
```

Testes offline e integração com alvo escolhido:

```bash
php scripts/testing/test_quest_generation.php
php scripts/testing/test_story_dungeon.php --offline
php scripts/testing/test_story_dungeon.php --hero="Nome do herói"
php scripts/testing/test_daily_bonus.php --db "Nome do herói"
```

Não existe um comando de «executar todos» nesta pasta. A selecção deve respeitar
o tema, o modo e os pré-requisitos; não executar automaticamente os scripts
persistentes como se fossem uma suite de testes.

| Script | Argumentos | Efeitos |
|---|---|---|
| [test_tournament_rewards.php](../scripts/testing/test_tournament_rewards.php) | `["Herói"]` | Integração MySQL com rollback: adapta prémios antigos ao contrato visual, fixa XP, resgata donuts/atributos/XP, valida estado da resposta e rejeita resgate repetido. Inclui pontos do passe por posição, preview e crédito sem duplicação. Usa torneio existente; sem nome escolhe personagem com conta válida. |

| Script | Argumentos | Efeitos |
|---|---|---|
| [migrate_tournament_calendar.php](../scripts/testing/migrate_tournament_calendar.php) | Nenhum | Persistente: backup das tabelas/contadores, DDL idempotente e transição para XP domingo, PvP terça e habilidades quarta, às 21:00. Cancela ciclos legados ativos sem novos prémios e conserva uma base de progresso. Preserva prémios anteriores. |
| [test_tournament_calendar.php](../scripts/testing/test_tournament_calendar.php) | `--offline` ou `["Herói"]` | Calendário/DST; sem `--offline`, integração MySQL com rollback: transição, lock concorrente, fechos e resgates separados, boot/estado normal/partial e saltos temporais. Não altera ficheiros do relógio. |
| [test_calendar_lock_free.php](../scripts/testing/test_calendar_lock_free.php) | Nenhum | Integração MySQL com duas ligações: com os locks de HeroCon e calendário de torneios seguros por A, a ligação B não espera com ciclos ativos, e espera (timeout) quando faltam ≤ 5 s para o fim. Rollback; não altera o relógio persistente. Se não houver ciclos ativos, a sincronização normal cria-os. |
| [test_tournament_calendar_client.js](../scripts/testing/test_tournament_calendar_client.js) | Nenhum | Offline: funções reais do bundle para receção de timestamps, alternância entre abas e pontos do passe nas linhas dos dois layouts do ranking. Executar com Node. |

### Ciclos do passe

`php scripts/testing/test_season_cycles.php ["Herói"]` verifica expiração exata,
limpeza de progresso, saltos de vários ciclos, idempotência, resgate antigo,
login e estado normal, 80 catálogos e rotação completa. Exige pelo menos três
personagens: reproduz as janelas dos HARs, testa convergência e ordem inversa
dos logins, conta nova e conta inativa. Integração MySQL com rollback, incluindo
alterações ao calendário; relógio simulado só em memória, sem modificar o desvio
persistente. Aplicar `migrate_season_calendar.php` previamente em instalações antigas.

### Definir pontos do passe — `set_season_points.php`

Executar na raiz do projeto:

```bash
php scripts/testing/set_season_points.php "skel" 500
php scripts/testing/set_season_points.php "skel" 0
php scripts/testing/set_season_points.php --help
```

O primeiro exemplo substitui o total por 500 pontos; o segundo coloca-o a zero.
O nome deve identificar exatamente um herói existente. O total deve ser um
inteiro entre 0 e 2147483647. Nome inexistente/ambíguo ou argumentos inválidos
terminam com erro, sem aplicar alterações.

A operação é persistente e usa transação com lock. Não limpa resgates nem
recompensas desbloqueadas anteriormente, não entrega prémios e não cobra donuts.
Se a temporada estiver expirada, aplica primeiro a renovação normal do jogo,
com mudança de tema e limpeza do progresso anterior; depois define os pontos
no novo ciclo. Recarregar o jogo para atualizar o passe.

`--help` apresenta as instruções sem aceder à base de dados.


### Regressão de pontos nas chamadas — BUG-000144

```bash
php scripts/testing/test_herobook.php --offline
php scripts/testing/test_herobook.php
node scripts/testing/test_herobook_season_client.js
```

Offline verifica as constantes reais de `initGame` e o valor de resgate nos
80 temas. MySQL usa uma personagem nível 40+ escolhida automaticamente e rollback;
inclui rotação para `beetle`/`bear`, crédito de diárias/semanais, duplicados e
descarte. O teste Node verifica os cinco cartões reais com gráficos simulados.
Nenhum altera o relógio persistente nem aplica compensações a resgates antigos.

### Casino: migração, eventos e testes

Consultar [casino/README.md](casino/README.md) para catálogos, custos e regras locais.

```bash
php scripts/testing/migrate_casino.php
php scripts/testing/casino_event.php catalogo
php scripts/testing/casino_event.php listar
php scripts/testing/casino_event.php ligar normal 3 roleta
php scripts/testing/casino_event.php ligar go_crazy 3 spine --em-dias=5
php scripts/testing/casino_event.php desligar
php scripts/testing/test_casino.php
php scripts/testing/test_casino.php --db "Nome do herói"
node scripts/testing/test_casino_client.js
```

Migração idempotente sem ativação. `ligar`/`desligar` alteram persistentemente
o agendamento global; nenhuma janela sobreposta. Duração e atraso usam dias
de 86400 segundos do relógio interno. Abertura/fecho automáticos sem cron,
com resgate dos resultados pagos preservado após expiração/cancelamento.
`catalogo` e `--help` não usam BD. Testes MySQL usam rollback e relógio só
em memória; não deixam eventos ativos. Recarregar o jogo após agendar/cancelar.

Casino ilimitado é a regra atual (`Casino::MAX_DAILY_SPINS=0`), independente
do evento/máquina; não é um argumento de `ligar`. Os giros continuam pagos e
o reset diário mantém apenas a contagem. Equipamento novo/pendente recebe +15
(borda warforged) e espaço gratuito nos tipos 1–7; pets recebem espaço gratuito.
Mísseis/gemas não são destinos de modificação. O teste MySQL verifica mais de
99999 giros, previews e aplicação de gemas nos prémios; Node verifica que os
lotes de cinco/dez continuam disponíveis. Testes preservam os eventos reais.

BUG-000145: `test_casino.php` verifica as dez variantes base (seis energéticos
de 50% por 3/14 dias, energia 10/60 e treino 4/40), lista de prémios por tema
e entrega/utilização dos cupões reais. Mesmo comando MySQL com rollback;
não compensa nem altera resultados antigos já pagos.

BUG-000146: o mesmo teste verifica que três símbolos iguais garantem o
energético de 14 dias, 40 unidades de treino ou 60 energias. Inclui os 34
temas, qualidades 1/2/3, geração real dos prémios e contratos dos giros.
Também verifica que qualidades 1/2 entregam os valores inferiores de
3 dias/4 unidades/10 energias; a lista de prémios continua a incluir todas
as variantes. Nos lotes, a animação representa o melhor giro, não todos.

### Patamares diários de energia gasta

```bash
php scripts/testing/migrate_daily_energy.php
php scripts/testing/test_daily_energy.php
php scripts/testing/test_daily_energy.php --db "Nome do herói"
node scripts/testing/test_daily_energy_client.js
```

A migração idempotente cria as duas tabelas fora de transação; não concede
prémios nem modifica saldos. O teste sem argumentos verifica catálogo/XP/pontos
anunciados. `--db` escolhe o herói pelo nome, ou a primeira conta se omitido,
e verifica todas as opções, contagem, resgate, cupões, inventário, temporada,
mudança de dia e protocolo usando rollback; relógio alterado só em memória.
Verifica que iniciar/cancelar não avança os patamares e que resgatar uma missão
concluída atualiza o contador na mesma resposta, sem duplicar em retries.
Regras em [patamares de energia](missoes/patamares_energia.md).
O teste Node executa as funções reais do bundle para verificar que contador,
barra, legenda e máscara atualizam com cada resposta, sem aguardar outro pedido.

### Patamares diários de duelos, liga e esconderijo

```bash
php scripts/testing/migrate_daily_combats.php
php scripts/testing/migrate_daily_hideout.php
php scripts/testing/test_daily_combats.php
php scripts/testing/test_daily_combats.php --db "Nome do herói"
node scripts/testing/test_daily_energy_client.js
```

Migração idempotente: quatro tabelas, fora de transação, sem saldos/prémios.
O teste offline verifica os seis catálogos; `--db` usa rollback e aceita nome
opcional do herói. Cobre todas as 45 opções dos três sistemas de combate, limiares, proprietário, retries,
cupões, temporada, épicos, mochila/descarte, mudança de dia e handlers de
resgate de vitórias/derrotas. Defesas não contam. Relógio alterado só em memória.
O teste Node verifica também as barras reais de duelos, liga e esconderijo.
Ver [patamares de combates](missoes/patamares_combates.md).

### Diálogo de prémios dos patamares

```bash
node scripts/testing/test_daily_milestone_rewards_client.js
```

Executa `Reward.get_statPoints`, `Reward.get_genericRewards` e o refresh do
diálogo reais do cliente, com componentes gráficos simulados. Confirma
5/10/20 pontos livres junto da temporada em energia, duelos, liga e esconderijo. Os testes
PHP de patamares verificam também previews antigos normalizados para `statPoints`.

### Patamares de ataques ao esconderijo

```bash
php scripts/testing/migrate_daily_hideout.php
php scripts/testing/test_daily_combats.php --db "Nome do herói"
```

Migração idempotente de duas tabelas, sem saldos/prémios. O teste partilhado
cobre também as 15 opções do esconderijo e o handler real de resgate:
vitórias/derrotas, tutorial, defesas, batalha inválida e retry, com rollback.
Os testes Node de barras e diálogos incluem o tipo 4.

### Energético do esconderijo

`php scripts/testing/migrate_user_vouchers.php` atualiza também o início da
janela do energético. `php scripts/testing/test_hideout_booster.php "Herói"`
verifica produção, cupões, compra e recolha em MySQL com rollback.
`node scripts/testing/test_hideout_booster_client.js` verifica getters reais
do cliente. Regras: [energeticos.md](hideout/energeticos.md).

## Scripts de eventos — regras comuns

Existem três calendários independentes: **35 eventos de coleção**, **47 variantes
da Caça às Toupeiras** e **55 temas da Pirâmide da Sorte**. Os números identificam
catálogos, não eventos que o script ativa simultaneamente. Cada família permite
uma ocorrência por intervalo, rejeitando agendamentos sobrepostos na mesma família.

Os comandos `start_event_quest.php`, `start_event_treasure.php` e
`start_event_draw.php` seguem estas regras:

- Sem argumentos, `--list` ou `--help`: listam identificadores e instruções sem MySQL.
- `--calendar`: consulta o relógio e as ocorrências, incluindo IDs para cancelamento;
  requer MySQL e as tabelas da migração, mas não guarda alterações.
- `IDENTIFICADOR [--days=7]`: cria um **evento global para o servidor**, acessível
  aos heróis elegíveis de nível 5 ou superior. A duração é de 1–365 dias,
  por defeito 7; cada dia representa 86400 segundos.
- `--start="AAAA-MM-DD HH:MM:SS"`: início em UTC, segundo `GameTime`, que pode
  estar avançado em relação ao relógio real. Sem esta opção, começa imediatamente.
  Não aceita datas passadas. Consultar `--calendar` antes de escolher uma data.
- `--hero="Nome do herói"`: aceita também o convite nessa conta, sem restringir
  o evento aos seus dados. Exige nome único, nível elegível e início imediato;
  não pode ser usado para aceitar antecipadamente uma edição futura. Agendamento
  e aceitação fazem parte da mesma transação.
- `--cancel=ID`: cancela persistentemente a ocorrência global da família
  correspondente, sem apagar o progresso ou os prémios já desbloqueados.

Não há calendário anual automático. Recarregar o jogo após agendar, cancelar ou
preparar progresso. As migrações são idempotentes e executam DDL fora de transações;
não ativam eventos, entregam prémios ou limpam progresso. Aplicá-las antes do
primeiro uso numa instalação existente. Os testes exigem o schema já migrado e
não substituem essas migrações.

Os testes com MySQL preparam calendário, nível, saldos, inventário e progresso
**dentro de uma transação com rollback**; executar sequencialmente para evitar
concorrência com testes que usam o mesmo herói. Os testes JavaScript executam
métodos reais extraídos do bundle com componentes simulados; não confirmam a
aparência num navegador. O relógio persistente não é avançado pelos testes.

## Eventos de coleção

Catálogo: [overview.md](eventos/overview.md).
Regras e protocolo: [implementacao.md](eventos/implementacao.md).

| Ficheiro em `scripts/testing/` | Argumentos | Finalidade e efeitos |
|---|---|---|
| [migrate_event_quests.php](../scripts/testing/migrate_event_quests.php) | Nenhum | Executa `12_event_quests.sql`: controlo, ocorrências, progresso e desbloqueios temáticos. Migração idempotente persistente, sem ativação nem reset. |
| [start_event_quest.php](../scripts/testing/start_event_quest.php) | `--list`, `--help`, `--calendar`, `IDENTIFICADOR [--days=7] [--start="AAAA-MM-DD HH:MM:SS"] [--hero="Nome"]`, `--cancel=ID` | Lista os 35 templates com nomes traduzidos; consulta ou altera o calendário global. Aceita também aliases: `natal`, `natal2`, `carnaval`, `pascoa`, `páscoa`, `olimpiadas`, `inverno`. |
| [prepare_event_quest.php](../scripts/testing/prepare_event_quest.php) | `"Nome" [--show \| --accept \| --ready] [--objective=IDENTIFICADOR]` | Por defeito consulta. `--accept` aceita o convite ativo. `--ready` avança objetivos incompletos até à meta, preservando os resgatados; com `--objective`, limita o avanço ao objetivo indicado. Guarda backup antes de alterar. Não entrega prémios, modifica saldos/nível ou agenda eventos. |
| [start_event_quest_worldboss.php](../scripts/testing/start_event_quest_worldboss.php) | `"Nome" [--hp=100000] [--seconds=3600]` ou `--help` | Cria persistentemente um vilão compatível com um objetivo incompleto do evento já aceite e associa o herói. Usa a zona selecionada; referência vazia usa `default_event_stage2`. Não substitui vilões/prémios pendentes, não mata o vilão e não entrega recompensas. |
| [test_event_quests.php](../scripts/testing/test_event_quests.php) | `[--db] [--hero="Nome"]` | Offline por defeito: 35 catálogos, traduções, referências, tipos e fórmulas locais. Com `--db`, testa atribuição, objetivos e resgates dos 35 eventos em MySQL com rollback. Sem nome escolhe a primeira conta real. |
| [test_event_quests_integration.php](../scripts/testing/test_event_quests_integration.php) | `[--hero="Nome"]` | MySQL obrigatório: handlers e atividades reais, inventário, pets, cupões, aparências, missões, treinos, loja, vilões, login, hub e expiração. Preparação e alterações de sessão são revertidas. Sem nome escolhe a primeira conta real. |
| [test_event_quests_client.js](../scripts/testing/test_event_quests_client.js) | Nenhum; executar com Node.js | Offline: métodos reais do cliente, parsing de objetivos/prémios e comparação das fórmulas PHP/JS. Não precisa de MySQL nem abre navegador. |

### Agendar e testar manualmente um evento de coleção

```bash
php scripts/testing/migrate_event_quests.php
php scripts/testing/start_event_quest.php --list
php scripts/testing/start_event_quest.php halloween --days=7
php scripts/testing/start_event_quest.php --calendar
php scripts/testing/prepare_event_quest.php "Nome do herói" --show
php scripts/testing/prepare_event_quest.php "Nome do herói" --accept
php scripts/testing/prepare_event_quest.php "Nome do herói" --ready
```

A migração e o agendamento são persistentes. `--show` só consulta; `--accept`
cria participação; `--ready` altera o progresso e guarda o estado anterior em
`scripts/testing/backups/event_quests/`. Exige evento atribuído, ativo e não
cancelado. Os identificadores de objetivos aparecem em `--show`:

```bash
php scripts/testing/prepare_event_quest.php "Nome do herói" --ready --objective=IDENTIFICADOR_DO_OBJETIVO
```

Substituir `IDENTIFICADOR_DO_OBJETIVO` pelo valor apresentado, sem inventar IDs.
Depois de preparar, recarregar o jogo, resgatar cada objetivo e escolher o prémio
final. O script não faz estes resgates automaticamente.

Para testar normalmente um objetivo de vilão sem o marcar artificialmente:

```bash
php scripts/testing/start_event_quest_worldboss.php "Nome do herói" --hp=1 --seconds=3600
```

Requer um objetivo de vilão ainda incompleto no evento aceite. Combater e
recolher o prémio do vilão pelo jogo; só criar o vilão não conclui o objetivo.
Este script não é o mesmo que `start_worldboss_event.php`, que inicia um vilão
por catálogo, sem selecionar automaticamente o objetivo sazonal do herói.

Testes automáticos, separados da preparação persistente:

```bash
php scripts/testing/test_event_quests.php
php scripts/testing/test_event_quests.php --db --hero="Nome do herói"
php scripts/testing/test_event_quests_integration.php --hero="Nome do herói"
node scripts/testing/test_event_quests_client.js
```

## Caça às Toupeiras

Catálogo dos 47 templates: [event_treasures_overview.md](eventos/event_treasures_overview.md).
Regras: [event_treasures_implementacao.md](eventos/event_treasures_implementacao.md).

| Ficheiro em `scripts/testing/` | Argumentos | Finalidade e efeitos |
|---|---|---|
| [migrate_event_treasures.php](../scripts/testing/migrate_event_treasures.php) | Nenhum | Executa `13_event_treasures.sql`: controlo, ocorrências e progresso. Migração idempotente persistente, sem ativação nem reset. |
| [start_event_treasure.php](../scripts/testing/start_event_treasure.php) | `--list`, `--help`, `--calendar`, `IDENTIFICADOR [--days=7] [--start="AAAA-MM-DD HH:MM:SS"] [--hero="Nome"]`, `--cancel=ID` | Lista os 47 identificadores, consulta ou altera o calendário global. Exemplo de template: `revenge_treasure`. |
| [prepare_event_treasure.php](../scripts/testing/prepare_event_treasure.php) | `"Nome" [--show \| --accept \| --shovels \| --unlock]` | Por defeito consulta. `--accept` aceita o convite. `--shovels` coloca o saldo de pás em pelo menos 1000, preservando valores superiores. `--unlock` termina a espera apenas dos quintais já criados. Estes dois modos guardam backup e exigem participação ativa. Não entrega prémios/donuts nem cria novos quintais. |
| [test_event_treasures.php](../scripts/testing/test_event_treasures.php) | `[--mysql] [--hero="Nome"]` | Offline: 47 definições, patamares, conteúdo privado dos tabuleiros e custos. MySQL: todos os 486 patamares/previews, 20 quintais reais, pistas, pás, cooldown, pagamentos, inventário/retry, pets, modificações, cupões, reset, cancelamento, nova edição, login e hub; rollback. Sem nome escolhe a primeira conta real. |
| [test_event_treasures_client.js](../scripts/testing/test_event_treasures_client.js) | Nenhum; executar com Node.js | Offline: custos PHP/JS, parsing de células/pistas/recompensas, estado, limpeza do cache no reset e tipo do evento no hub; métodos reais do bundle, sem navegador. |

### Abrir uma edição e preparar pás ou espera

```bash
php scripts/testing/migrate_event_treasures.php
php scripts/testing/start_event_treasure.php --list
php scripts/testing/start_event_treasure.php revenge_treasure --days=7
php scripts/testing/prepare_event_treasure.php "Nome do herói" --show
php scripts/testing/prepare_event_treasure.php "Nome do herói" --accept
php scripts/testing/prepare_event_treasure.php "Nome do herói" --shovels
php scripts/testing/prepare_event_treasure.php "Nome do herói" --unlock
```

`--shovels` não soma 1000 a cada execução: aplica `max(1000, saldo atual)`.
`--unlock` altera a data de desbloqueio dos quintais já guardados para terminar
a espera, sem revelar células, aumentar toupeiras ou desbloquear patamares de
prémios. Os backups são guardados em `scripts/testing/backups/event_treasures/`;
contêm o progresso anterior, incluindo tabuleiros privados. Nenhum destes modos
resgata prémios. Recarregar o jogo e continuar pelo fluxo normal.

Testes automáticos:

```bash
php scripts/testing/test_event_treasures.php
php scripts/testing/test_event_treasures.php --mysql --hero="Nome do herói"
node scripts/testing/test_event_treasures_client.js
```

## Pirâmide da Sorte

Catálogo dos 55 temas: [event_draws_overview.md](eventos/event_draws_overview.md).
Regras: [event_draws_implementacao.md](eventos/event_draws_implementacao.md).

| Ficheiro em `scripts/testing/` | Argumentos | Finalidade e efeitos |
|---|---|---|
| [migrate_event_draws.php](../scripts/testing/migrate_event_draws.php) | Nenhum | Executa `14_event_draws.sql`: controlo, ocorrências e progresso. Migração idempotente persistente, sem ativação nem reset. |
| [start_event_draw.php](../scripts/testing/start_event_draw.php) | `--list`, `--help`, `--calendar`, `IDENTIFICADOR [--days=7] [--start="AAAA-MM-DD HH:MM:SS"] [--hero="Nome"]`, `--cancel=ID` | Lista os 55 temas, consulta ou altera o calendário global. Exemplo de template: `revenge_draw_event`. |
| [prepare_event_draw.php](../scripts/testing/prepare_event_draw.php) | `"Nome" [--show \| --accept]` | Por defeito consulta participação, índices sorteados/recolhidos e próximo custo; não normaliza expiração. `--accept` aceita o convite global ativo. Não sorteia, entrega prémios, altera saldos ou força índices. |
| [test_event_draws.php](../scripts/testing/test_event_draws.php) | `[--mysql] [--hero="Nome"]` | Offline: 55 catálogos/825 prémios, referências, assets, pesos normalizados, custos e seleção sem reposição. MySQL: 825 sorteios/resgates, débitos, primeiro grátis, duplicação, mochila cheia/retry, equipamento, pets, gemas lendárias, atributos, handlers, login/hub, calendário, expiração e estados finais; rollback. Sem nome escolhe a primeira conta real. |
| [test_event_draws_client.js](../scripts/testing/test_event_draws_client.js) | Nenhum; executar com Node.js | Offline: métodos reais do bundle e comparação PHP/JS de custos/pesos nos 880 estados dos 55 temas; pendências, resgates, parsing dos prémios, convite e hub. Sem navegador. |

### Abrir uma edição e aceitar o convite

```bash
php scripts/testing/migrate_event_draws.php
php scripts/testing/start_event_draw.php --list
php scripts/testing/start_event_draw.php revenge_draw_event --days=7
php scripts/testing/prepare_event_draw.php "Nome do herói" --show
php scripts/testing/prepare_event_draw.php "Nome do herói" --accept
```

O evento é global. Em alternativa, `start_event_draw.php revenge_draw_event
--days=7 --hero="Nome do herói"` agenda e aceita também para esse herói numa única
execução; não executar as duas alternativas para o mesmo intervalo.

O primeiro sorteio é gratuito. Os seguintes debitam donuts pela progressão do
catálogo; os 15 sorteios custam 618 no total. É necessário resolver o prémio
pendente antes do seguinte. A preparação não dá donuts nem salta essa sequência.
`give_donuts.php`, se usado para testes manuais, é uma operação persistente separada.

Testes automáticos:

```bash
php scripts/testing/test_event_draws.php
php scripts/testing/test_event_draws.php --mysql --hero="Nome do herói"
node scripts/testing/test_event_draws_client.js
```

## Prémios dos eventos — auxiliar de testes e reparação de mísseis

| Ficheiro em `scripts/testing/` | Argumentos/utilização | Finalidade e efeitos |
|---|---|---|
| [event_reward_assertions.php](../scripts/testing/event_reward_assertions.php) | Biblioteca incluída pelos testes de draw e toupeiras; não é comando autónomo | Define `assertEventRewardEquipment($item, $cid)`. Verifica qualidade épica, borda dourada e espaço vazio nos tipos 1–7, orçamento da loja com bónus do esconderijo, 3–4 atributos e dano de armas. Para tipo 8 exige atributos nulos, ausência de espaço, nível da munição igual ao do herói na geração, 100 cargas e dano calculável por `Missiles::damage`. Pets/gemas usam as verificações específicas dos testes. A função consulta MySQL; não altera dados. |
| [repair_event_missiles.php](../scripts/testing/repair_event_missiles.php) | `"Nome" [--show \| --apply]` | Por defeito consulta os mísseis com atributos indevidos identificados pelos IDs guardados nos prémios de draw/toupeiras, incluindo prémios já recolhidos. `--apply` guarda backup e coloca os quatro atributos e `stat_weapon_damage` a zero, com transação/locks. Preserva nível, cargas restantes, qualidade, borda, posição e saldos. Repetir após a reparação encontra zero candidatos. |

### Consultar e reparar mísseis antigos dos eventos

```bash
php scripts/testing/repair_event_missiles.php "Nome do herói" --show
php scripts/testing/repair_event_missiles.php "Nome do herói" --apply
php scripts/testing/repair_event_missiles.php "Nome do herói" --show
```

Exige nome único, MySQL e as tabelas de progresso de **ambas** as famílias já
migradas. `--show` abre uma transação com locks para a consulta, mas não modifica
dados nem cria backups. `--apply` afeta exclusivamente itens existentes do herói,
de tipo 8, referenciados nos prémios guardados dos dois eventos e com algum
atributo indevido. Não procura munições genéricas da loja nem cria novas.

O backup JSON contém o ID do herói, IDs dos itens e valores anteriores dos cinco
campos corrigidos. É guardado no diretório temporário do PHP (`sys_get_temp_dir()`;
normalmente `/tmp`) com nome `hz-event-missiles-ID-ALEATORIO.json`, permissões
`0600` e caminho apresentado na saída. Guardar uma cópia se for necessária
retenção: o diretório temporário pode ser limpo. Falha no backup impede as
alterações na BD. Não existe comando de restauro automático neste script.

O dano das munições é calculado a partir de `item_level` e das habilidades
compatíveis do pet, não de `stat_weapon_damage`; por isso esse campo fica a zero.
Recarregar o jogo depois de aplicar. A reparação não substitui
`repair_missiles.php`, que trata a conversão mais abrangente de munições antigas.

### Verificação realizada nesta sessão

Os testes dos eventos de coleção foram executados offline, em MySQL com rollback
e através dos métodos JavaScript do cliente. Os testes de draw e toupeiras
passaram offline e em MySQL com rollback, incluindo os 55/47 catálogos, entrega
real, fórmula épica do equipamento e contrato corrigido das munições. Os testes
JavaScript destas duas famílias também passaram. As migrações foram aplicadas
de forma idempotente; não implicam eventos ativos após os testes.

A reparação de mísseis foi aplicada a um item afetado do herói `skel`, com backup,
preservando nível e cargas; a consulta posterior encontrou zero candidatos.
A confirmação visual no navegador é distinta destas verificações automáticas.


## Compras locais gratuitas

- `migrate_local_payments.php`: migração idempotente da tabela de recibos; não altera saldos/inventários.
- `test_local_payments.php [--mysql] [--hero="Nome"]`: catálogo offline; com MySQL prepara nível/saldos/mochila e evento temporários, verifica entrega/recibos/login e reverte por rollback.
- `node scripts/testing/test_local_payments_bridge.js`: bridge da página local, retries e callback imediato em ambiente simulado.

Ver [compras locais](compras/overview.md).

## Energia de treino

- `php scripts/testing/repair_training_energy.php [--hero="Nome"]`: simula a reparação da base antiga 100 para 30, preservando energia gasta e regeneração.
- Acrescentar `--apply` para gravar; guarda primeiro backup em `server-laravel/storage/backups/` e usa transação/locks. Uma segunda execução não altera contas já corrigidas.
- `php scripts/testing/test_training_energy.php`: testes offline das regras de reparação.
- `php scripts/testing/test_training_energy.php --mysql --hero="Nome"`: verifica criação, login, estado e treino com conta temporária, revertendo por rollback.

Ver [energia de treino](treinos/implementacao.md).

- `node scripts/testing/test_payment_confirmation_client.js`: executa a função de confirmação do cliente real; verifica mensagens locais, atualização do estado e ausência de falsas notificações de robô.
- `test_local_payments.php --mysql` cobre também o calendário da oferta de fichas e exclusão das duas caixas antigas da Festa da Piscina, com rollback.

## Academia Heroica — calendário global e participação

Guia completo: [Academia Heroica](tower_events_implementacao.md). Estes scripts
não arrancam o processo HTTP: abrem/cancelam o evento global ou aceitam com um
herói. A migração não abre uma ocorrência. Nível mínimo: 5; datas UTC de GameTime.

| Script | Argumentos | Efeito |
|---|---|---|
| [migrate_tower_events.php](../scripts/testing/migrate_tower_events.php) | Nenhum | Migração idempotente; cria/verifica schema, sem evento ou prémios. |
| [start_tower_event.php](../scripts/testing/start_tower_event.php) | `--list`, `--help`, `--calendar`, `IDENTIFICADOR [--days=7] [--start="AAAA-MM-DD HH:MM:SS"] [--hero="Nome"] [--dry-run]`, `--cancel=ID` | Consulta ou agenda evento global; recusa sobreposições. `--hero` aceita também nessa conta, com início imediato. `--dry-run` valida sem gravar, incluindo cancelamento legado. |
| [join_tower_event.php](../scripts/testing/join_tower_event.php) | `"Nome do herói" [--dry-run]`, `--help` | Aceita ocorrência ativa; exige nome único, nível e ausência de participação anterior aberta. Repetição na mesma ocorrência não cria outra participação. |
| [stop_tower_event.php](../scripts/testing/stop_tower_event.php) | `[--current ou IDENTIFICADOR ou --id=N] [--dry-run]`, `--help` | Cancela globalmente; sem alvo usa a ocorrência ativa. Conserva histórico/prémios desbloqueados. Tema com várias ocorrências exige ID. |
| [test_tower_events.php](../scripts/testing/test_tower_events.php) | `[--mysql] [--hero="Nome"]` | Offline: referências, atributos, economia/gestores. MySQL: seis variantes, prémios, duplicação, snapshot forjado e reset com rollback; sem nome escolhe primeiro herói real. |
| [test_tower_events_client.js](../scripts/testing/test_tower_events_client.js) | Nenhum; usar `node` | Métodos reais do bundle com UI simulada: contrato, gestores/comandos, saldos e estado sem `occurrence_id`. Não confirma renderização no browser. |

```bash
php scripts/testing/start_tower_event.php --calendar
php scripts/testing/start_tower_event.php earth_towerevent --days=7 --dry-run
php scripts/testing/start_tower_event.php earth_towerevent --days=7
php scripts/testing/join_tower_event.php "Nome do herói" --dry-run
php scripts/testing/stop_tower_event.php --current --dry-run
```

Retirar `--dry-run` da entrada/cancelamento para gravar. Cancelar afeta todos os
heróis. Nenhum script dá recursos de teste, apaga participações ou faz reset de
heróis. Recarregar o jogo após mudar edição. Consultar o guia antes de agendar
uma data futura: GameTime pode diferir da data real.

## Adversário de tutorial no arranque

`start.sh` executa `scripts/setup/ensure_tutorial_player.php` depois de a BD ficar
healthy e antes de iniciar o servidor HTTP. Cria **AjudanteTutorial**, nível 1,
numa conta própria passiva (`user_id` diferente de zero, necessário nos duelos).
Não é executado como bot nem tem credenciais divulgadas. Não altera flags de
outras contas: o jogador continua a completar os tutoriais normalmente.

A preparação é idempotente em cada arranque: reutiliza a conta reservada e não
repõe nível, honra ou estado de uma personagem existente. Após limpeza completa
da BD, o próximo arranque volta a criá-la. Usa lock MySQL e transação para impedir
duplicados entre arranques concorrentes; colisões com nomes/contas existentes
interrompem a preparação sem os substituir. A personagem é criada pela rotina
normal `Character::createNew`, incluindo inventário, aparência e missões iniciais.

Execução explícita: `php scripts/setup/ensure_tutorial_player.php`.
Teste com rollback: acrescentar `--dry-run` (pode consumir valores de
AUTO_INCREMENT, mesmo sem persistir registos). Não redefine o epoch do servidor.

Validação em 2026-10-19: sintaxe PHP e Bash; criação em MySQL com rollback;
criação persistente de um herói de nível 1 e segunda execução sem duplicação.
Não foi realizado combate no cliente nem reiniciada a stack para este teste.

## Casino de abertura

`start.sh` chama `scripts/setup/ensure_opening_casino.php`: prepara o tema
`no_cash_and_xp`, slots, desde `.run/epoch` até epoch + 3 dias de GameTime.
Não prolonga eventos nos reinícios nem substitui calendários existentes.
Regras e recuperação de arranque em [casino/README.md](casino/README.md).
`php scripts/testing/test_opening_casino.php` valida em MySQL com rollback;
exige calendário vazio e não elimina eventos preexistentes.

## Arranque Docker (2026-10-20)

`start.sh`/`stop.sh` controlam a stack Docker completa; bots permanecem locais.
Ver [DOCKER.md](DOCKER.md) para portas, volumes e comandos. Preparadores de
tutorial/casino executam dentro do container do jogo, não com PHP no host.
