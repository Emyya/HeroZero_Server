# Caça às Toupeiras — implementação e testes

Implementação dos **47 templates `event_treasures`** presentes nos assets locais, em 2026-10-05. O catálogo completo, com nomes, patamares, referências e definições JSON, está em [event_treasures_overview.md](event_treasures_overview.md). Os eventos de coleção continuam documentados em [implementacao.md](implementacao.md).

## Disponibilidade global e partida individual

O administrador abre uma ocorrência global com início e fim definidos por `GameTime`. Todos os heróis elegíveis recebem o mesmo template durante esse intervalo; cada um tem tabuleiros, pás, toupeiras e resgates próprios. A partir do nível 5, o cliente aceita automaticamente o convite quando atualiza o botão de Caça às Toupeiras, através de `assignTreasureEvent`.

A opção `--hero` do script de calendário apenas aceita também numa conta de teste. Não restringe o evento àquela conta. Um herói offline recebe o convite quando voltar ao jogo, se o evento ainda estiver ativo. Aceitar tarde não prolonga o prazo global.

Existe uma Caça às Toupeiras habilitada por intervalo; ocorrências sobrepostas são recusadas. Esta política é local e separada do calendário de eventos de coleção: os dois sistemas podem funcionar ao mesmo tempo, assim como o casino. Não existe calendário anual automático nem evento permanentemente ativo por instalação da migração.

Ao repetir o template noutra edição, cria-se uma nova ocorrência e novo progresso. Os campos `id` da participação e `occurrence_id` identificam coisas diferentes. `character.treasure_event_id` enviado ao cliente é o ID da participação. Esse campo é produzido no overlay; não foi acrescentada uma coluna com esse nome à tabela `character`.

## Componentes

| Ficheiro/componente | Responsabilidade |
| --- | --- |
| `server-laravel/app/HeroZero/EventTreasures.php` | Catálogo, calendário, tabuleiros, pás, pistas, prémios, reset e estado |
| `server-laravel/actions/treasureEvents.php` | Handler comum das oito ações |
| Seis handlers antigos de tesouros | Delegam no mesmo handler para manter os pontos de entrada |
| `Character.php` | Métodos públicos delegam no motor; overlay usa a participação real |
| `Live.php` | Login/estado normal substituem a fixture por convite ou participação real |
| `Router.php` | Liga as oito ações e atualiza também respostas parciais da própria conta |
| `getEventHubEntries.php` | Preserva casino/eventos de coleção e acrescenta a caça |
| `HeroZero.min.js` | Limpa o cache de quintais ao reiniciar o progresso |
| `docker/initdb/13_event_treasures.sql` | Schema inicial do calendário e progresso |

O motor anterior gravava dados provisórios em `collected_item_pattern` e `current_item_pattern_values`, dava moedas fixas e somava toupeiras sem tabuleiro real. Esses blobs são preservados, mas não são interpretados como participação do motor novo nem dão compensações retroativas. A migração não reseta personagens, não apaga dados antigos e não ativa eventos.

## Schema e persistência

| Tabela | Campos principais e proteção |
| --- | --- |
| `event_treasure_control` | Linha única `id=1`, bloqueada nas alterações do calendário e aceitação |
| `event_treasure_occurrences` | `id`, `identifier`, `ts_start`, `ts_end`, `enabled`; índice de disponibilidade |
| `event_treasure_progress` | `id`, `character_id`, `occurrence_id`, `identifier`, `payload`, `closed`; associação herói/ocorrência única |

O payload contém:

- `tokens`: total de toupeiras encontradas na ronda atual.
- `shovels`: pás disponíveis.
- `last_free`: instante da última recolha gratuita.
- `level`: último quintal criado; começa em zero.
- `levels`: tabuleiros criados, células abertas e informações de cada quintal.
- `rewards`: planos dos patamares já desbloqueados, por índice baseado em zero.
- `claimed`: mapa dos patamares resgatados.
- `unique`: referências de modificações únicas já entregues nesta ocorrência.
- `round`: número da ronda; aumenta no reinício.

`closed` separa histórico de participação pendente. Depois da expiração/cancelamento, uma participação sem recompensas abertas/desbloqueadas por recolher fecha automaticamente ao atualizar o estado. Não há segunda aceitação na mesma ocorrência. Uma participação anterior com prémios pendentes continua visível para os recolher antes de aceitar outra edição.

A migração é idempotente e deve executar fora de transações do jogo. Os scripts de jogo nunca executam DDL. Foi aplicada e repetida nesta instalação.

## Calendário, datas e expiração

Uma ocorrência está disponível quando `enabled=1` e `ts_start <= GameTime::now() < ts_end`. O fim é exclusivo. Os scripts recebem datas UTC do relógio do jogo, que pode diferir da data real do computador. Consultar `--calendar` antes de agendar.

As datas textuais do protocolo são formatadas por `EventQuests::serverDate`, com o offset atual usado pelo parser do cliente. O prazo guardado permanece um timestamp. O cancelamento envia uma data final efetiva já atingida para impedir que a interface continue a apresentar a caça como disponível.

Depois do fim/cancelamento:

- Não se pode escavar, recolher pás gratuitas, criar quintais, saltar espera ou reiniciar.
- Bónus já revelados e patamares já desbloqueados continuam resgatáveis.
- Células escondidas não são abertas automaticamente e não dão prémios.
- A participação é fechada quando não restarem resgates pendentes, preservando o histórico.
- As pás pertencem à ocorrência e não são transferidas para uma edição nova.

O estado enviado é 1 durante disponibilidade e 3 após expiração enquanto houver pendências. O enum 2 existe no cliente, mas esta implementação não o usa para impedir exploração: atingir a meta final não bloqueia a recolha dos bónus restantes dos quintais.

## Tabuleiros e pistas

Cada quintal usa `grid_size`, `tokens`, `bonus_reward`, `bonus_reward_factor` e `unlock_duration` de `event_treasure_locations`. O catálogo atual tem vinte quintais, desde grelhas 3×3 até 8×8, com 120 toupeiras no total.

Ao criar o quintal, o servidor distribui as toupeiras e os bónus em posições distintas e guarda o resultado completo. A distribuição uniforme é uma regra local; não foi recuperado o algoritmo oficial. Cada entrada de `bonus_reward` cria uma célula bónus, com o fator da mesma posição. Entradas vazias adicionais em `bonus_reward_reference` não criam células.

O tabuleiro completo fica em `levels[N].hidden`, apenas na BD. A resposta pública serializa `levels[N].cells`, contendo exclusivamente as células já abertas. Não envia as posições das toupeiras/bónus escondidos. Atualizar o estado, voltar a um quintal ou repetir login não volta a sortear o tabuleiro.

As coordenadas são baseadas em zero e validadas contra a dimensão da grelha. Os tipos públicos são:

| Tipo | Resultado |
| --- | --- |
| 1 | Toupeira; aumenta `found` e `event_tokens` em um na abertura |
| 2 | Recompensa bónus; aumenta `boni_found` e abre diálogo de recolha |
| 3 | Pista; `neighbours` indica toupeiras e bónus nas oito células vizinhas |

Uma célula aberta não pode ser paga nem contada outra vez: devolve `errAlreadyOpened`, que o cliente trata. Uma pista não conta como toupeira. A recolha repetida de uma célula bónus devolve `errCollectTreasureEventCellRewardAlreadyCollected`.

## Pás e donuts

Uma abertura normal consome **uma pá**, sem débito de moedas. Sem pás, devolve `errUseItemNotEnough`. Uma abertura com `premium=true` consome **um donut**, segundo a constante, sem consumir uma pá. O cliente apresenta essa alternativa quando não há pás disponíveis.

`claimFreeTreasureRevealItems` dá **20 pás a cada 10800 segundos**, ou três horas. A primeira recolha pode ocorrer imediatamente depois da aceitação. As pás acumulam; as recolhas perdidas não são acumuladas retroativamente. Repetir antes do prazo devolve `errClaimFreeTreasureRevealItemsCooldownActive`. Não se dá uma pá por toupeira nem se confundem pás com o contador de prémios.

O pacote de pás do catálogo pode ser obtido gratuitamente pelo fluxo de [compras locais](../compras/overview.md), com participação ativa: soma a quantidade anunciada sem débito, dentro da transação da compra. Não existe cobrança real por fornecedor. As pás gratuitas e aberturas individuais com donuts mantêm as suas regras.

## Avanço de quintais e espera

A participação começa com `current_level=0`; a primeira chamada a `createNextTreasureEventLevel` cria o quintal 1. O próximo só pode ser criado depois de encontrar todas as toupeiras do anterior. Não é obrigatório encontrar ou recolher todos os bónus antes de avançar. Os quintais anteriores permanecem acessíveis para exploração/recolha.

Cada quintal criado recebe `ts_unlock = GameTime + unlock_duration`. O primeiro não tem espera. Os outros usam entre 60 e 1800 segundos, conforme a definição. Enquanto houver espera, não se pode escavar aquele quintal.

`skipTreasureEventLevelUnlock` recalcula o custo a partir do tempo restante, usando a fórmula `Q.getQuestInstantFinishCost` do cliente. O campo `premium_cost` enviado pelo cliente não é confiado:

| Tempo restante | Custo |
| --- | --- |
| Menos de 450 segundos | 1 donut |
| De 450 até menos de 750 | 2 donuts |
| De 750 até menos de 1050 | 3 donuts |
| A partir de 1050 | 4 donuts |

Só é possível saltar a espera do último quintal criado e ainda bloqueado. Repetir depois de desbloquear não volta a debitar. Não existe quintal 21 no catálogo atual.

## Recompensas bónus das células

O enum foi confirmado no JavaScript em `UiEventTreasureLevelRewardLine`. As quantidades de energia, treino, donuts e atributos são interpretadas localmente como fatores diretos arredondados. As fórmulas escaladas são locais e não são apresentadas como fórmulas oficiais:

| Tipo | Prémio | Quantidade local |
| --- | --- | --- |
| 0 | Donuts | `max(1, round(factor))` |
| 1 | Moedas | `max(1, round(nível × 100 × factor))` |
| 2 | XP | `round(XP entre níveis × 0,1 × factor)`, mínimo 1; zero no nível máximo |
| 3 | Energia de missão | `max(1, round(factor))`, em cupão |
| 4 | Unidades motivacionais | `max(1, round(factor))`, em cupão |
| 5 | Pontos de atributos | `max(1, round(factor))` |

O plano do bónus é fixado ao criar o quintal, usando o nível desse momento. Não é ressorteado ao abrir ou recolher a célula. As quantidades dos quintais incluem energia 25 e 50, e treino 15. Cupões permitem resgate posterior pelo sistema de mensagens e evitam substituir recursos/limites ativos.

Uma célula bónus só entrega prémio em `collectTreasureCellReward`. Encontrá-la torna-a disponível; não credita silenciosamente o saldo. Os bónus atuais são numéricos e não exigem vaga na mochila.

## Prémios por total de toupeiras

Os 486 patamares usam os thresholds e referências exatos do catálogo. Ao atingir cada threshold, o motor cria o plano/preview uma única vez. Antes desse threshold, o prémio não entra na lista `rewards` consumida pelo cliente.

Tipo 5 aponta para um template de equipamento, munição ou pet. Usa `Character::createEventRewardItem`, o gerador local com atributos da loja e bases locais dos pets. O tipo vem do template; equipamento e munição são agora sempre épicos. Munição usa `Missiles::PACKAGE_CHARGES`. Pets são criados através de `Sidekick::create`, copiando as bases do preview e respeitando as habilidades fixas.

Tipo 12 aponta para uma modificação específica, incluindo slot e qualidade. O motor usa `createImprovementItem` com a referência correta. O orçamento de atributos é local, alinhado com o auxiliar do casino: `max(4, ceil(nível × 0,8))`, repartido pelos quatro atributos, com resto em resistência. Não interpreta o fator 20 como fórmula oficial nem como quantidade de itens.

O equipamento/modificação entra numa vaga desbloqueada da mochila. A capacidade segue os limites existentes de 6/12/18 posições. Pets vão para a coleção de pets e não ocupam mochila. `discard_item=true` permite descartar um preview através do diálogo do cliente; o patamar fica resgatado e não entrega o item descartado.

Sem espaço, o erro é `errInventoryNoEmptySlot`: a transação reverte e o jogador pode libertar uma vaga e repetir. O resgate duplicado devolve `errCollectTreasureEventRewardAlreadyCollected`.

## Reinício de progresso

`resetTreasureEventProgress` custa **49 donuts** e conserva pás e prazo da ocorrência. Limpa toupeiras, tabuleiros e patamares da ronda anterior, e aumenta `round`. O cooldown das pás não é reiniciado.

A implementação local permite reiniciar quando todos os patamares estiverem desbloqueados e não houver prémios de patamares ou células abertas por recolher. Isso evita abandonar previews pendentes sem consentimento. Bónus ainda escondidos podem ser abandonados no reinício.

As modificações do tipo 12 entregues são tratadas como únicas por ocorrência. Depois de reiniciar, um patamar com a mesma referência entrega a alternativa configurada: atualmente 10 pontos de atributos. Esta escolha de quais prémios são únicos é política local, porque os assets não marcam explicitamente todos os casos; os parâmetros da alternativa vêm das constantes. Equipamento e pets podem voltar a ser ganhos numa nova ronda. Uma nova ocorrência começa com outro histórico de únicos.

Foi necessário ajustar `Um.update` no cliente: ao passar de um quintal positivo para zero, limpa o mapa de quintais em memória. Sem isso, o backend limpava a ronda mas o cliente mantinha células antigas. A alteração está limitada a esse reset; não muda os assets nem as definições de recompensas. Após atualizar o servidor, recarregar o cliente sem cache para receber o bundle alterado.

## Atomicidade e segurança de resgates

Operações individuais usam `StoryDungeon::transaction`, que bloqueia a personagem e o utilizador, e usa savepoints quando já existe transação exterior. O registo de progresso também é bloqueado. Agendamento/cancelamento usam o auxiliar `Missiles::transaction` e a linha de controlo de calendário.

A ordem de abertura é validar ocorrência/quintal/coordenadas, verificar que a célula ainda está fechada, debitar pá ou donuts, revelar a célula, desbloquear patamares e guardar. Uma falha ao criar um preview reverte também o consumo e a contagem.

No resgate, o servidor valida a participação da própria conta, o prémio desbloqueado e a ausência de resgate anterior. Entrega o prémio e marca o resgate dentro da mesma transação. Falta de espaço ou saldo não deixa alterações parciais. Não se aceitam IDs arbitrários de prémios enviados pelo cliente: o pedido contém apenas o índice, que é resolvido no payload guardado.

Os testes verificam retries e savepoints de operações dentro de uma transação exterior. Não foi executado um teste com dois processos a recolher simultaneamente o mesmo prémio. O mapa privado permanece na BD e pode aparecer num backup administrativo de preparação; não é enviado nas respostas do jogo.

## Estado do cliente e hub

O convite tem `identifier`, `start_date`, `end_date`, sem `id`. A participação acrescenta:

| Campo | Conteúdo |
| --- | --- |
| `id` | ID do progresso individual |
| `status` | 1 ativo ou 3 expirado com pendências |
| `event_tokens` | Total de toupeiras da ronda |
| `event_reveal_items` | Pás disponíveis |
| `ts_reveal_item_collected` | Timestamp da última recolha gratuita |
| `current_level` | Último quintal criado |
| `rewards` | String JSON de planos dos patamares desbloqueados, ordenados por índice |
| `collected_rewards` | String JSON de mapa índice → resgatado |
| `levels` | String JSON de quintal → `info` e `cells` públicas |

Cada célula bónus tem o campo `reward` também como string JSON. Os mapas vazios são objetos `{}`, não arrays, para o parser do cliente. Cada quintal tem `info.found`, `needed`, `boni_found`, `boni_amount` e `ts_unlock`.

A coleção `items` acompanha o estado da participação para resolver os previews por ID. Login, `Live::accountState` e respostas parciais da conta recebem a mesma forma real. A fixture antiga é removida, mesmo quando não existe evento ativo. O hub usa `<template>_treasure`, reconhecido por `EventHubEntry.get_type`.

## Scripts PHP

Executar a partir da raiz do projeto. Listar e consultar não ativa eventos. Agendar, cancelar, aceitar e preparar são operações persistentes explícitas.

```bash
php scripts/testing/migrate_event_treasures.php
php scripts/testing/start_event_treasure.php --list
php scripts/testing/start_event_treasure.php --calendar
php scripts/testing/start_event_treasure.php revenge_treasure --days=7
```

O último comando abre o evento para o servidor todo. Qualquer um dos 47 identificadores do catálogo é aceite. A duração permite 1–365 dias. Para início futuro, usar `--start="AAAA-MM-DD HH:MM:SS"` em UTC de GameTime. Não usar o exemplo de uma data real sem consultar o relógio do jogo.

Para aceitar também numa conta de teste ao agendar:

```bash
php scripts/testing/start_event_treasure.php revenge_treasure --days=7 --hero="Nome do herói"
```

O nome deve resolver uma conta real única com nível mínimo. `--hero` exige início imediato. Agendamento e aceitação partilham uma transação: uma aceitação recusada reverte o novo evento global.

Para trabalhar sobre um evento já aberto:

```bash
php scripts/testing/prepare_event_treasure.php "Nome do herói" --accept
php scripts/testing/prepare_event_treasure.php "Nome do herói" --show
php scripts/testing/prepare_event_treasure.php "Nome do herói" --shovels
php scripts/testing/prepare_event_treasure.php "Nome do herói" --unlock
```

`--show` apenas consulta. `--accept` cria participação, sem prémios. `--shovels` coloca pelo menos 1000 pás, sem entregar moedas/donuts/prémios. `--unlock` termina a espera dos quintais que já tenham sido criados; não cria quintais novos nem revela células. Os dois modos de preparação guardam primeiro backup em `scripts/testing/backups/event_treasures/`, com progresso e GameTime, sem sessões/tokens de autenticação.

Os backups incluem o tabuleiro privado para diagnóstico administrativo. Não existe restauração automática: restaurar um payload depois de entregar prémios poderia permitir duplicações. Recarregar o jogo após preparação.

Cancelar uma ocorrência pelo ID mostrado no calendário:

```bash
php scripts/testing/start_event_treasure.php --cancel=1
```

Cancelar não apaga participações nem recompensas desbloqueadas. Não é um reset silencioso das contas.

## Testes e validação

```bash
php scripts/testing/test_event_treasures.php
node scripts/testing/test_event_treasures_client.js
php scripts/testing/test_event_treasures.php --mysql --hero="Nome do herói"
```

Sem `--mysql`, o teste PHP verifica offline os 47 catálogos, patamares crescentes, capacidade/tipos dos vinte tabuleiros, metas finais e custos. Não usa PDO simulado específico desta família.

Com `--mysql`, aceita as 47 variantes e prepara os totais necessários dentro de transação para criar e resgatar todos os 486 patamares com descarte. Além disso, joga uma edição `revenge_treasure` através das operações reais dos vinte quintais, escavando as células e validando pistas e resgates. Não afirma que foram jogadas manualmente as 47 variantes.

A integração verifica pás gratuitas e cooldown, ausência de pás/saldo, custo premium e retries, espera e salto, limites de coordenadas, progressão de quintais, mochila cheia/recuperação, equipamento, pet, referência da modificação, cupões de energia/treino, reset, alternativa única, cancelamento, expiração por GameTime com resgate tardio, ausência de convite/aceitação antes do início, nova edição, estado normal, hub e login real. Toda a preparação e eventual rotação de sessão são revertidas. Sem `--hero`, escolhe a primeira conta real. Executar fora de uma sessão de jogo em atividade para evitar interferências.

O teste Node executa métodos extraídos do bundle real: parsing de células e recompensas, leitura de quintais, custos PHP/JS, limpeza de cache no reset e tipo do hub. Não constitui renderização no navegador.

### Confirmação visual

1. Abrir uma ocorrência global e recarregar uma conta elegível.
2. Confirmar o botão e a aceitação automática.
3. Recolher vinte pás gratuitas e confirmar o contador/cooldown.
4. Criar o primeiro quintal e escavar células; confirmar toupeiras, pistas e diálogo de bónus.
5. Recolher os bónus e verificar saldos/cupões conforme o tipo.
6. Atingir um patamar, abrir o painel de prémios e confirmar preview/resgate.
7. Encontrar as toupeiras do quintal e criar o seguinte; confirmar espera e custo de salto.
8. Verificar que um quintal anterior continua acessível para bónus restantes.
9. Testar reset após concluir/recolher os patamares, confirmando pás conservadas e novo tabuleiro fechado.
10. Cancelar/expirar uma ocorrência de teste e confirmar que os prémios já desbloqueados continuam recolhíveis.

Os testes offline, JavaScript e MySQL foram executados durante a implementação. A confirmação visual completa no navegador continua pendente. Não ficou um evento global ativo nem uma preparação persistente de herói feita pelos testes.

## Diagnóstico e limites

| Sintoma | Verificação |
| --- | --- |
| Botão não aparece | Verificar calendário/GameTime, nível 5, participação anterior pendente e recarregar o cliente |
| Aceitação recusada | Verificar identificador ativo, nome/nível e participação já aceite nessa edição |
| Não permite escavar | Confirmar disponibilidade global, espera do quintal, coordenadas e pás/saldo |
| Não permite avançar | Encontrar todas as toupeiras do último quintal; bónus não são requisito |
| Recompensa não aparece | Confirmar total de toupeiras do patamar; recompensas só existem quando desbloqueadas |
| Não permite resgatar item | Libertar vaga na mochila desbloqueada ou usar descarte no diálogo |
| Energia/treino não sobe diretamente | Consultar os cupões nas mensagens |
| Não permite reiniciar | Confirmar evento ativo, meta final atingida, todos os patamares recolhidos e nenhum bónus aberto pendente |
| Reinício mostra quintais antigos | Recarregar sem cache para obter o bundle com limpeza de cache de quintais |
| Nova edição não é oferecida | Recolher prémios da anterior e atualizar estado para permitir fecho automático |

Ficam fora deste motor os sorteios, objetos escondidos e torres. A Pirâmide da Sorte tem agora [motor independente](event_draws_implementacao.md); a Academia Heroica tem [motor independente](../tower_events_implementacao.md); objetos escondidos permanecem pendentes. Não existe editor de calendário no painel administrativo, compra real de pacotes pagos de pás, calendário anual automático ou reprodução confirmada das fórmulas do backend oficial. A distribuição do tabuleiro, fórmulas locais e regra de prémios únicos estão explicitadas acima.

## Equipamento épico com espaço gratuito

Nos novos prémios de equipamento dos tipos 1–8, a qualidade é sempre 3 (épica), por escolha de balanceamento local. Os tipos 1–7 chegam com borda dourada (`ts_availability_start=15`) e espaço de modificação vazio e gratuito (`ts_availability_end=1`), tanto no preview como na mochila. Munições recebem a borda, mas não aceitam modificações. Pets mantêm as bases e qualidade do catálogo; gemas mantêm a referência e raridade próprias, incluindo lendárias.

Para os equipamentos dos tipos 1–7, a geração partilha o cálculo do brechó/loja: nível requerido `max(1, nível do herói − aleatório(0..4))`, orçamento `ceil((nível requerido × 1,6 + 2 + nível requerido² × 0,004) × 2,3)` mais o bónus plano do esconderijo, distribuído por 3–4 atributos. Armas usam dano `ceil(nível requerido × 1,035) + 2`. A borda não acrescenta um multiplicador de atributos. O item é gerado uma única vez e entregue sem recalcular atributos. A alteração aplica-se aos previews gerados a partir desta atualização; itens já gerados ou entregues conservam os seus dados.

Os testes MySQL verificam a soma dos atributos contra a fórmula, raridade, borda, espaço e dano das armas em todos os catálogos, dentro de rollback. Não exige migração de schema.

### Correção das munições

Os mísseis usam `Missiles::template` com o nível atual do herói, requisitos do catálogo e 100 cargas iniciais. Os quatro atributos e `stat_weapon_damage` ficam a zero: o dano das munições é calculado por `Missiles::damage(item_level, pet ativo)`, usando o fator das constantes e habilidades compatíveis do pet. A fórmula épica dos equipamentos aplica-se apenas aos tipos 1–7. Mantêm raridade épica, borda dourada e ausência de espaço de modificação.

Para reparar mísseis já gerados pelos dois eventos: `php scripts/testing/repair_event_missiles.php "Nome do herói" --show`; depois `--apply`. O script identifica exclusivamente os IDs guardados nos prémios destes eventos, guarda backup dos atributos e corrige apenas atributos indevidos, preservando nível, cargas, qualidade e restantes campos.
