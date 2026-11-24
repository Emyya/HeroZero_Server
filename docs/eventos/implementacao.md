# Eventos de coleção — implementação e testes

Implementação local em 2026-10-05 dos **35 templates de `event_quests`** listados no [overview](overview.md). Inclui o mesmo motor para Natal, Halloween, Carnaval, Olimpíadas, Páscoa e os restantes eventos do catálogo.

As famílias de sorteios, tesouros, objetos escondidos e torres são sistemas distintos. A Caça às Toupeiras tem agora [implementação própria](event_treasures_implementacao.md) e [catálogo próprio](event_treasures_overview.md). Foram inventariadas no overview; esta implementação não substitui os seus handlers nem declara essas famílias completas. O motor de vilões existente é reutilizado para os objetivos de derrotar vilões.

## Modelo global e percurso do jogador

**O evento é aberto para todo o servidor.** O calendário não contém um filtro por herói. Cada ocorrência torna o mesmo template disponível aos heróis elegíveis, mas cada jogador aceita o convite, cumpre os seus objetivos e recebe os seus próprios prémios.

Há três identificadores diferentes:

| Identificador | Exemplo | Significado |
| --- | --- | --- |
| Template | `halloween` | Definição partilhada: objetivos, referências e tipos de prémio |
| Ocorrência | ID de `event_quest_occurrences` | Uma edição global desse template, com início e fim próprios |
| Progresso | ID de `event_quest_progress` | Participação de um herói nessa edição; é o valor de `character.event_quest_id` |

Dois heróis que aceitam Halloween partilham a ocorrência global, mas têm IDs de progresso, contadores e previews distintos. Uma segunda edição de Halloween reutiliza a definição e cria outra ocorrência; não reutiliza os contadores da primeira.

O percurso normal é:

1. O administrador agenda o evento global através de `start_event_quest.php`.
2. Dentro do intervalo, o estado enviado ao cliente inclui o convite para heróis de nível 5 ou superior, desde que não tenham outro evento pendente nem participação anterior nessa ocorrência.
3. O jogador aceita no cliente; `assignEventQuest` cria o progresso individual e prepara os previews de prémios.
4. As atividades elegíveis fazem avançar os contadores dessa conta. Antes de aceitar, não há contagem retroativa.
5. Cada objetivo completo fica pronto para resgate. O jogador recolhe cada prémio individual.
6. Depois de recolher todos os objetivos, escolhe uma das três opções finais. O servidor entrega essa opção e os dois prémios base na mesma transação.
7. O progresso fica guardado como histórico fechado e o apontador do herói volta a zero. A mesma edição não volta a ser oferecida a esse herói.

O convite é produzido quando o cliente pede estado; o agendamento não escreve uma participação em todas as contas nem envia, por si só, uma notificação por socket. Um jogador offline recebe o convite ao voltar ao jogo enquanto a ocorrência estiver ativa. Um herói que atinja o nível mínimo durante o evento pode receber o convite numa atualização posterior. Aceitar perto do fim mantém o deadline global: não inicia sete dias privados para essa conta.

### O que significa `--hero`

O comando normal de abertura para o servidor é:

```bash
php scripts/testing/start_event_quest.php halloween --days=7
```

Acrescentar `--hero="Nome do herói"` mantém o evento global e aceita automaticamente o convite nessa conta, para facilitar a preparação de testes. Os outros heróis continuam a poder aceitar no jogo. Se essa aceitação falhar, a transação do comando também reverte o novo agendamento.

Para aceitar numa conta de teste um evento que já está aberto, usar `prepare_event_quest.php "Nome do herói" --accept`; não voltar a executar o comando de abertura, pois isso tentaria criar outra ocorrência sobreposta.

## Ficheiros e persistência

| Componente | Responsabilidade |
| --- | --- |
| `server-laravel/app/HeroZero/EventQuests.php` | Catálogo, ocorrências, aceitação, progresso, previews, resgates e estado |
| `server-laravel/actions/eventQuests.php` | Resgate de objetivo/final e conclusão instantânea |
| `server-laravel/actions/assignEventQuest.php` | Aceitação via `Character::assignEventQuest` |
| `Character.php` | Integração com missões, energia, treinos, loja e lavagem |
| `actions/claimDuelRewards.php` | Contagem de duelos efetivamente resgatados |
| `Worldboss.php` | Contagem de vilão derrotado após resgate do prémio |
| `Live.php` e `Router.php` | Estado real no login, estado normal e respostas parciais |
| `actions/getEventHubEntries.php` | Evento atribuído no hub, preservando o casino |
| `docker/initdb/12_event_quests.sql` | Tabelas novas e controlo de calendário |

Tabelas novas:

- `event_quest_control`: linha única para serializar alterações de calendário.
- `event_quest_occurrences`: identificador do template, início/fim e ativação. O fim é exclusivo.
- `event_quest_progress`: um registo por herói/ocorrência, com restrição única; estado, limite temporal, flag de resgate final e payload de contadores/prémios.
- `event_quest_unlocks`: alterações visuais entregues, preservadas independentemente do calendário.

A tabela antiga `event_quests` é preservada e não é usada por este motor. Isso evita reinterpretar registos incompletos do antigo sistema. `character.event_quest_id` passa a apontar para o registo em `event_quest_progress`. Um apontador antigo sem progresso real é normalizado no estado da conta; não recebe prémios retroativos.

A migração é idempotente e não ativa eventos, não elimina tabelas antigas nem reseta heróis. Não existe DDL em operações de jogo.

## Calendário

Há um evento de coleção ativo de cada vez. É uma política local, compatível com o apontador único do cliente. Agendamentos sobrepostos são rejeitados; cancelamentos desativam a ocorrência e preservam progresso/prémios pendentes.

Não se usa o intervalo antigo `2012-01-01`–`2012-01-02` das constantes como calendário real. O administrador agenda ocorrências explicitamente. Não foi inventado um calendário anual oficial para Carnaval/Olimpíadas nem ativado um evento permanentemente nesta tarefa.

Todos os timestamps vêm de `GameTime`. O relógio simulado pode estar noutra data que o computador. Consultar `--calendar` antes de escolher um início. Os scripts aceitam `--start` em UTC; os campos de data enviados ao cliente são formatados com o offset atual de `Live::gameTimezone()` porque o parser `DateTime.fromStringServer` usa esse offset. Assim, o deadline em timestamp mantém-se exato, incluindo quando o servidor volta a enviar estado após mudança de hora.

Uma nova ocorrência do mesmo template tem ID e progresso distintos. Recarregar o cliente ao trocar uma ocorrência por outra do mesmo template: o bundle funde eventos por identificador e mantém caches da ocorrência anterior numa sessão já aberta. Os scripts indicam este recarregamento. A mesma ocorrência não volta a ser aceite depois do resgate final. Um evento anterior pendente deve ser fechado antes de aceitar o próximo.

## Progresso por atividade

| Tipo | Gatilho real | Critério |
| --- | --- | --- |
| 1 | Resgate de missão de tempo | Missão concluída; `reference` compatível |
| 2 | Resgate de missão de combate ganha | Resultado da batalha `winner = a` |
| 4 | Início de missão | Energia efetivamente debitada |
| 5 | Início de treino | Unidades motivacionais efetivamente consumidas, não número de pequenas missões/estrelas |
| 6 | Resgate de prémio do vilão | Vida global zero e participação do herói; template compatível |
| 7 | Resgate de missão ganha | Objeto anunciado em `rewards.event_item`, ligado à mesma ocorrência |
| 8 | Resgate de missão ganha | Referência temática compatível |
| 9 | Resgate de duelo | Duelo concluído e ainda não resgatado, independentemente da vitória |
| 10 | Lavagem de item bem-sucedida | Operação concluída sem erro |
| 11 | Renovação explícita da loja | Operação concluída sem erro; não conta preenchimento automático |

O tipo 5 é apoiado pelos textos das constantes, por exemplo `event_quest/objective/carnival_event_training_completed/description`: a meta refere unidades motivacionais utilizadas. Não se altera o protocolo do sistema de treino.

`reference` vazio ou `all` aceita todas as referências dessa atividade. Os padrões com `*` são ancorados ao identificador completo. O matching por wildcard é uma decisão local baseada nos padrões existentes, não uma implementação recuperada do servidor oficial.

Não se criam eventos/progressos retroativamente ao fazer uma atividade. Os gatilhos de progresso partilham a transação da atividade. Retries de resgate/início recusados pelo sistema original não acrescentam progresso.

### Missões temáticas e objetos

Ao aceitar um evento, as missões disponíveis (`status = 1`) são atualizadas: recebem um identificador temático quando um objetivo exige esse tema e têm o drop de evento sorteado. Missões em curso e os seus prémios são preservados. As novas missões geradas pelo pool usam o mesmo mecanismo.

Para os objetivos temáticos de Halloween e Mundial, são usados identificadores existentes em `quest_templates`, conservando o tipo tempo/combate. **Escolher sempre uma missão compatível enquanto o objetivo temático estiver pendente é balanceamento local.**

Por missão, escolhe-se uniformemente um objeto elegível e aplica-se a sua `reward_chance` das constantes. Há no máximo um objeto anunciado por missão. Esta seleção é local; as chances por objeto vêm dos assets. O resultado fica persistido, não é ressorteado no resgate.

A renovação global de missões (`generateNewQuests`, `all_stages=true`) gera
os pools até à última zona desbloqueada (`max_quest_stage`), mesmo quando o
herói está numa zona inferior. Os sorteios de objetos de evento são feitos
para as novas missões de todas essas zonas. O bug que limitava a renovação
à zona selecionada foi corrigido em BUG-000163; não era uma regra dos eventos.
A renovação local continua a trocar apenas o pool da zona atual.

O reward inclui `event_quest_occurrence`, metadado local ignorado pelo cliente e validado pelo backend. Isso impede que um objeto de uma edição anterior avance uma edição nova. O cliente usa `event_item` para desenhar o ícone apenas quando o evento/objetivo está ativo.

## Objetivos, prémios e atomicidade

Contador abaixo da meta: progresso. Igual à meta: pronto. **Meta + 1: resgatado.** O progresso normal fica limitado à meta para não simular um resgate acidental. O backend nunca volta a entregar um objetivo resgatado e responde `errFinishObjectiveAlreadyCollected`, tratado pelo cliente.

A conclusão instantânea só deixa o objetivo pronto. O jogador reclama depois o respetivo prémio. O custo é `ceil((1 − min(progresso/meta, 1)) × 29)`, como no JavaScript, recalculado dentro da transação. Falta de saldo preserva o contador e o saldo.

As fórmulas numéricas dos objetivos são as de `Q.getEventQuestObjectiveRewardAmount`. Os testes executam os métodos reais do bundle e comparam 120 combinações com PHP, incluindo arredondamento de fatores e nível máximo.

### Recompensas finais: decisões locais explícitas

O enum final foi confirmado em `UiEventQuestReward.refreshReward`:

- 1: moedas — escala local `factor × 5000`.
- 2: donuts — fator arredondado, quantidade direta.
- 3: pontos livres de atributos — fator arredondado, quantidade direta.
- 4: XP — escala local `factor × 10000`.
- 5: energético — cupão com o identificador e duração em dias; não substitui automaticamente o energético ativo.
- 6: unidades motivacionais — cupão.
- 7: item — template indicado ou equipamento épico sorteado para `random_epic`.

Os dois prémios base são entregues uma vez, além de **uma** das três opções finais. Os previews finais são fixados à aceitação. O cliente escolhe por `reward_item_id`; opções sem item usam os seletores locais negativos `-1`, `-2`, `-3`, evitando `0`, que o diálogo interpreta como ausência de seleção. O teste do cliente verifica que o getter transmite o seletor numérico corretamente. Esta convenção não foi confirmada num HAR oficial.

Equipamento e previews usam o gerador local já usado pelos vilões (`createWorldbossRewardItem`), com tipo/raridade do template e atributos do sistema de loja. Não se apresenta `reward_itemN_factor = 20` como qualidade ou fórmula oficial de atributos. Munição recebe a quantidade local `Missiles::PACKAGE_CHARGES`. Os pets são criados com `Sidekick::create`, que preserva habilidades fixas.

Os prémios de objetivo em item/aparência têm preview preparado à aceitação; os numéricos são calculados no nível do herói no resgate, como a pré-visualização do cliente. Os pontos de temporada usam a localização 2 de `season_locations` e são creditados uma vez por objetivo resgatado.

### Inventário e falhas

O resgate bloqueia personagem/utilizador/progresso/inventário. Respeita 6/12/18 espaços desbloqueados conforme o nível. A falta de espaço devolve `errInventoryNoEmptySlot` e reverte as alterações, permitindo libertar espaço e repetir. Pets são entregues à coleção de pets.

`discard_item=true` descarta o item selecionado, conservando prémios numéricos/base. Ao fechar o evento são limpos apenas previews não entregues; itens presentes na mochila/cofre são preservados. Aparências ficam em `event_quest_unlocks` e são acrescentadas ao estado no login e após os overlays do casino.

Transações usam `StoryDungeon::transaction`, com locks e savepoints quando já existe transação exterior. Débito, entrega e marcação de resgate são atómicos. Os testes verificam repetição de pedidos e preservação de transações exteriores; não foi executado nesta tarefa um ensaio com dois processos a resgatar simultaneamente.

## Expiração e estados

- 1: em curso.
- 2: todos os objetivos resgatados; prémio final disponível, ou histórico já fechado (`claimed = 1`).
- 3: expirado/cancelado sem todos os objetivos completos.
- 5: expirado com todos os objetivos completos.

Estado 4 não é usado por este motor. O estado de resgate final é separado em `claimed`, sem inventar outro enum do cliente.

Objetivos já prontos continuam resgatáveis após expiração. Objetivos incompletos deixam de avançar e não podem ser concluídos com donuts. Se todos tiverem sido completados a tempo, podem ser resgatados depois e desbloquear o prémio final. Fechar um evento incompleto através da ação final abandona as pendências e não entrega os prémios base/opção final; recolher os objetivos prontos antes de o fechar.

No hub usa-se `<identifier>_eventQuest`, conforme `EventHubEntry.get_type`. O convite sem `id` chega no estado da conta. O hub pode anunciar tanto esse convite como o evento já atribuído: `hub()` usa o resultado de `attach()` e acrescenta o sufixo `_eventQuest`. `worldboss_start_times` é uma coleção vazia: esta implementação não inventa horários de vilões. Os vilões são iniciados pelos scripts já existentes ou pelo auxiliar abaixo.

## Scripts PHP de utilização e preparação

Executar a partir da raiz. Substituir `Nome do herói` pelo nome real.

```bash
php scripts/testing/migrate_event_quests.php
php scripts/testing/start_event_quest.php --list
php scripts/testing/start_event_quest.php --calendar
php scripts/testing/start_event_quest.php halloween --days=7
php scripts/testing/prepare_event_quest.php "Nome do herói" --accept
php scripts/testing/prepare_event_quest.php "Nome do herói" --show
```

Agendar é persistente e afeta o convite global. `--accept` só aceita na conta escolhida. Também é possível fazer ambos atomicamente:

```bash
php scripts/testing/start_event_quest.php natal --days=7 --hero="Nome do herói"
```

Aliases: `natal`, `natal2`, `carnaval`, `pascoa`/`páscoa`, `olimpiadas`, `inverno`. Todos os identificadores do overview são aceites.

Para testar o diálogo de resgates sem completar as atividades:

```bash
php scripts/testing/prepare_event_quest.php "Nome do herói" --ready
php scripts/testing/prepare_event_quest.php "Nome do herói" --ready --objective=halloween_quest_completed
```

`--ready` **guarda** o progresso na meta, sem entregar prémios e sem tocar nos objetivos já resgatados. Guarda previamente um backup em `scripts/testing/backups/event_quests/`, contendo o progresso anterior e o instante do jogo; não inclui sessões/tokens. Não fornece donuts nem altera o nível. Recarregar o jogo, resgatar cada objetivo e escolher o prémio final.

Para testar o objetivo de vilão pelo combate real:

```bash
php scripts/testing/start_event_quest_worldboss.php "Nome do herói" --hp=1 --seconds=3600
```

Este comando cria persistentemente um vilão compatível com o evento aceite, na zona atual do herói, e associa-o à conta. Referência vazia usa `default_event_stage2`. Não força vitória nem dá prémios. Não substitui vilões/prémios pendentes; uma associação recusada reverte a criação. Após combater, recolher o prémio do vilão para contar no evento de coleção. Repetir para cumprir metas que exigem vários vilões.

Para cancelar uma ocorrência, usar o ID mostrado por `--calendar`:

```bash
php scripts/testing/start_event_quest.php --cancel=1
```

O cancelamento não apaga progresso. Não há reset silencioso do evento do herói nem restauração automática de backups: restaurar contadores depois de entregar prémios poderia duplicar resgates.

## Testes automáticos e limites

```bash
php scripts/testing/test_event_quests.php
node scripts/testing/test_event_quests_client.js
php scripts/testing/test_event_quests.php --db --hero="Nome do herói"
php scripts/testing/test_event_quests_integration.php --hero="Nome do herói"
```

- Offline: catálogo dos 35 eventos, referências, traduções, metas, fórmulas e custo premium.
- JavaScript: execução de métodos reais do bundle, 120 comparações PHP/JS, estado de objetivo, convite, seleção numérica e tipo do hub. Não é renderização num navegador.
- MySQL: todos os 35 eventos, metas, previews, resgates, repetições, calendário e falhas premium, com rollback.
- Integração de atividades: handlers reais, inventário cheio, entrega de equipamento/pet/cupão/aparência, missões, loja, treinos, lavagem, duelos, vilões, login, hub, expiração e associação entre ID/objeto, com rollback.

Os testes MySQL preparam o herói/calendário dentro de uma transação e revertem tudo, incluindo uma rotação de sessão exercitada pelo login. Sem `--hero`, escolhem a primeira conta real com personagem. Executar fora de uma sessão de jogo em atividade para evitar que operações concorrentes confundam os resultados.

A migração foi aplicada nesta instalação e repetida para verificar idempotência. Não foi ativado um evento de produção durante a implementação. A confirmação visual completa no navegador continua a cargo do utilizador com os scripts de preparação. Não há teste com PDO simulado específico desta funcionalidade.

## Contrato das ações e estado enviado ao cliente

As ações mantêm o envelope normal `{data, error}` e usam a personagem do utilizador autenticado. Os parâmetros abaixo são específicos da funcionalidade; não substituem os campos comuns de autenticação do protocolo.

| Ação | Parâmetros específicos | Resultado |
| --- | --- | --- |
| `assignEventQuest` | `event_quest_identifier` | Aceita o template atualmente ativo para a própria conta |
| `claimEventQuestObjectiveRewards` | `objective_identifier`, `discard_item` opcional | Entrega o prémio de um objetivo pronto e marca-o como resgatado |
| `instantFinishEventQuestObjective` | `objective_identifier` | Debita donuts e coloca o objetivo na meta, sem entregar o prémio |
| `claimEventQuestRewards` | `reward_item_id`, `discard_item` opcional | Entrega a escolha final e os prémios base, ou fecha um evento expirado incompleto |

Os handlers de aceitação e resgate devolvem `Live::accountState`. Para as restantes ações, a integração em `Router` repõe o estado do evento quando a resposta contém a personagem da conta. Isso cobre respostas parciais que poderiam deixar a interface com contadores ou referências antigos.

### Forma de `event_quest`

O convite contém a definição do template, `identifier`, `start_date` e `end_date`, mas ainda não contém um ID de participação. Uma participação acrescenta os campos seguintes à definição:

| Campo | Origem e utilização |
| --- | --- |
| `id` | ID de progresso individual, não ID da ocorrência global |
| `status` | Estado compatível com o cliente, descrito acima |
| `start_date` | Instante em que o herói aceitou; no convite corresponde ao início global |
| `end_date` | Fim global copiado para a participação |
| `rewards` | String JSON com o plano dos prémios base |
| `reward_item1_id` a `reward_item3_id` | IDs dos previews, ou seletores negativos para opções sem item |
| `reward_item1_rewards` a `reward_item3_rewards` | Strings JSON com cada plano de recompensa |
| `objectiveN_value` | Contador persistido de cada objetivo, incluindo a marca de resgate |
| `objectiveN_reward_item_id` | Preview de item do objetivo; zero quando não existe |
| `worldboss_start_times` | Lista vazia; sem calendário automático de vilões nesta implementação |

As strings JSON de recompensas são intencionais e seguem a forma consumida pelo cliente. A coleção `items` acompanha a participação para o cliente resolver os IDs dos previews. Criar um preview em `items` não equivale a entregar equipamento: a entrega só ocorre ao colocá-lo numa posição da mochila, ou ao criar o pet na sua coleção.

`attach()` remove primeiro o `event_quest` antigo que possa vir de uma fixture. Depois procura a participação real ou o convite atual, normaliza o apontador da personagem e acrescenta as aparências desbloqueadas. A atualização de expiração pode ocorrer ao ler o estado; a consulta `prepare_event_quest.php --show` evita esse caminho e é apenas de leitura.

### Exemplo de um contador com meta 10

| Valor guardado | Apresentação/ação |
| --- | --- |
| 0 | Ainda não houve progresso |
| 7 | Sete atividades/unidades válidas; objetivo incompleto |
| 10 | Meta atingida; prémio individual por recolher |
| 11 | Prémio individual já recolhido; novos resgates são recusados |

Com progresso 7/10, a conclusão instantânea custa `ceil(0,3 × 29) = 9` donuts. O pedido deixa o contador em 10. Só o resgate seguinte entrega o prémio e escreve 11. Esta separação permite repetir uma atualização de estado sem voltar a atribuir recompensas.

## Detalhe da persistência e das transações

`event_quest_occurrences` guarda `identifier`, `ts_start`, `ts_end` e `enabled`. Uma ocorrência está ativa quando está habilitada e `ts_start <= GameTime::now() < ts_end`. Duas ocorrências podem tocar nos limites, por exemplo a segunda começar exatamente quando termina a primeira, mas não podem sobrepor intervalos habilitados.

`event_quest_progress` guarda a associação `character_id`/`occurrence_id`, o identificador do template, o estado, `ts_creation`, `ts_end`, `claimed` e `payload`. A chave única dessa associação impede uma segunda aceitação da mesma edição. A seleção de pendências procura `claimed = 0`; por isso, uma participação expirada continua a bloquear uma nova aceitação até ser fechada.

O payload organiza-se em quatro partes:

- `values`: contadores por índice de objetivo.
- `objectives`: planos de prémios não numéricos dos objetivos, preparados à aceitação.
- `choices`: três planos de escolha final, preparados à aceitação.
- `base`: plano agregado dos dois prémios base.

As quantidades numéricas dos objetivos não ficam congeladas nesse payload: são calculadas no resgate com o nível atual. As escolhas e os prémios base ficam fixados à aceitação. Subir de nível durante o evento pode, portanto, alterar a recompensa numérica de um objetivo, mas não gera outro equipamento de escolha final.

No calendário, o lock da linha única de `event_quest_control` serializa agendamentos e cancelamentos. A aceitação também bloqueia essa linha antes de validar a ocorrência. Nas operações individuais, o motor usa `StoryDungeon::transaction`; o calendário usa `Missiles::transaction`. São os auxiliares de transação existentes, com suporte a transações exteriores por savepoint.

O resgate só marca o contador depois de a entrega ter sucesso. Se a mochila estiver cheia, se o seletor final não pertencer às opções guardadas ou se faltar saldo premium, o pedido não deve deixar uma entrega parcial. O resgate final entrega primeiro a opção e depois o plano base, dentro da mesma transação; a falha de uma das entregas reverte ambas.

A proteção contra duplicação combina o lock da participação com os contadores acima da meta e a flag `claimed`. Uma repetição após fechar o evento já não encontra participação pendente. Os testes exercitam pedidos repetidos; a validação com dois processos simultâneos continua por executar.

### Fórmulas numéricas dos objetivos

Para moedas, o motor calcula primeiro a taxa por tempo com as constantes `coins_per_time_*`, arredondada a três casas. Multiplica-a pela percentagem base do evento, pelo fator e pelo tempo de referência; arredonda esse produto, acrescenta o termo de nível e arredonda o total. A ordem dos arredondamentos faz parte da compatibilidade com o JavaScript.

Para donuts e pontos de atributos, arredonda-se o fator antes de multiplicar pela quantidade base. Nos assets usados, as bases são 1 donut e 3 pontos. Assim, um fator de 0,4 dá zero pontos e um fator de 0,5 dá três; não se arredonda simplesmente `3 × fator`.

Para XP, usa-se a diferença entre o XP necessário para o nível seguinte e o nível atual, multiplicada pela percentagem base e pelo fator. No nível máximo, o resultado é zero. Estas fórmulas dizem respeito aos prémios de objetivos; as escalas locais dos prémios finais estão identificadas na secção de recompensas.

## Guião de teste manual completo

### Abrir e aceitar em duas contas

1. Executar a migração se ainda não tiver sido aplicada e consultar `--calendar`.
2. Abrir Halloween com o comando global, sem `--hero`.
3. Recarregar duas contas reais de nível 5 ou superior, sem evento anterior pendente.
4. Confirmar o convite nas duas e aceitar em cada uma.
5. Consultar cada herói com `prepare_event_quest.php "Nome" --show`.
6. Fazer uma atividade elegível apenas na primeira conta e confirmar que o contador da segunda não mudou.

Este teste confirma visualmente a disponibilidade global e a independência de progresso. O facto de um script trabalhar sobre uma conta específica não limita a ocorrência a essa conta.

### Testar os prémios sem cumprir todas as metas

```bash
php scripts/testing/prepare_event_quest.php "Nome do herói" --show
php scripts/testing/prepare_event_quest.php "Nome do herói" --ready
```

Guardar o caminho do backup mostrado pelo comando. Recarregar o cliente, recolher os objetivos individualmente e verificar as alterações relevantes: saldo, pontos, mochila, coleção de pets, cupões ou opções de aparência. Quando todos estiverem resgatados, selecionar uma opção final e confirmar os prémios base. Recarregar novamente para confirmar que a participação não é oferecida outra vez.

`--ready` prepara a interface de resgate, mas não comprova os gatilhos das atividades. Para validar esses gatilhos, usar o percurso normal do jogo ou o teste de integração. Preparar um único objetivo com `--objective=...` permite testar o seu resgate mantendo os restantes incompletos.

### Testar capacidade e conclusão instantânea

Para um prémio de equipamento, encher os espaços desbloqueados da mochila e tentar resgatá-lo. Confirmar que o pedido falha sem alterar o contador de resgate ou creditar outros prémios dessa operação. Libertar um espaço e repetir; o item deve ser entregue uma vez.

Para conclusão instantânea, escolher um objetivo ainda incompleto num evento ativo. Registar progresso e saldo, verificar o custo mostrado e concluir com donuts. O objetivo deve ficar pronto, com o débito correspondente; o prémio continua por recolher até ao pedido de resgate.

### Testar vilões

O auxiliar de vilão exige uma participação já aceite com um objetivo de vilão ainda incompleto. A criação é global, na zona atual do herói escolhido; a associação feita pelo script é individual. Outros jogadores podem participar segundo as regras normais do sistema de vilões.

Com `--hp=1`, o combate pode terminar rapidamente, mas o objetivo de coleção só avança após recolher o prémio do vilão, com vida global zero e participação válida. Voltar a recolher o mesmo prémio não deve aumentar o contador. Se a meta exigir vários vilões, é necessário criar e derrotar várias ocorrências de vilão.

### Testar cancelamento e mudança de edição

Consultar `--calendar` e cancelar a ocorrência pretendida. Após atualizar o estado, verificar que os objetivos incompletos deixaram de avançar e que os já prontos continuam resgatáveis. Recolher primeiro os prémios disponíveis e só depois fechar uma participação incompleta, pois o fecho abandona as restantes pendências.

Ao abrir outra edição do mesmo template, fechar a participação anterior e recarregar o cliente. A nova participação deve começar com contadores próprios. Missões antigas que tragam um drop ligado à ocorrência anterior não devem contar para a edição nova.

## Diagnóstico de problemas frequentes

| Sintoma | Verificação e resolução |
| --- | --- |
| O convite não aparece | Verificar `--calendar`, relógio GameTime, nível mínimo, participação pendente e participação anterior na mesma ocorrência; recarregar o cliente |
| Agendamento recusado por sobreposição | Consultar os intervalos habilitados; escolher outro intervalo ou cancelar explicitamente a ocorrência que já não se pretende |
| `--hero` falha | Confirmar nome único, conta real, nível mínimo, início imediato e ausência de participação pendente; o novo agendamento é revertido se a aceitação falhar |
| A atividade não aumenta o contador | Confirmar aceitação, prazo, tipo e referência do objetivo; verificar o gatilho exato na tabela de atividades |
| O vilão morreu mas o contador não mudou | Recolher o prémio de vilão e confirmar participação e template compatível |
| A missão não dá objeto de evento | Verificar se foi anunciado um drop e se o objetivo estava pendente; há sorteio, não garantia de objeto em cada missão |
| Um drop antigo não conta | Confirmar a ocorrência associada; o bloqueio entre edições é intencional |
| Não consigo recolher equipamento | Libertar espaço na mochila desbloqueada ou usar a opção de descarte disponibilizada pelo cliente |
| Os objetivos estão completos mas falta o prémio final | Recolher os prémios individuais primeiro; meta atingida e objetivo resgatado são estados diferentes |
| Energético recebido não aparece como ativo | Consultar os cupões: a recompensa não substitui automaticamente o energético atual |
| `--ready` recusa a preparação | Verificar participação aceite e ativa; o comando também recusa quando não há objetivos por avançar |
| O cliente mostra dados da edição anterior | Fechar a participação antiga quando aplicável e recarregar o cliente, sobretudo ao repetir o mesmo template |

O modo `--show` apresenta o estado persistido e pode ainda mostrar estado 1 numa participação cujo prazo já passou, antes de um pedido de jogo aplicar a expiração. Interpretar também a data de fim e o calendário; a consulta não normaliza o estado por desenho.

## Cobertura atual e trabalho ainda separado

O motor comum suporta as definições dos 35 eventos de coleção, as atividades listadas, a aceitação e os resgates. As fichas com os objetivos, narrativas, referências e recompensas específicas de cada template permanecem no [overview](overview.md), evitando duas cópias divergentes do catálogo.

Não há agendamento anual automático, ativação automática de vilões por evento, editor de calendário no painel administrativo ou comando de restauração de progresso. O calendário é operado pelos scripts PHP descritos. Objetos escondidos e torres continuam fora deste motor e precisam da sua própria implementação/validação. A Pirâmide da Sorte tem motor independente, documentado em [event_draws_implementacao.md](event_draws_implementacao.md). Os tesouros têm motor independente, documentado em [event_treasures_implementacao.md](event_treasures_implementacao.md).

A validação registada distingue quatro níveis: testes offline PHP, execução de métodos reais do JavaScript em Node, integração MySQL com rollback e confirmação visual manual. Os três primeiros foram executados durante a implementação; o quarto deve ser confirmado com o cliente aberto. A edição deste documento amplia a explicação e não constitui uma nova execução dos testes nem uma alteração ao estado dos eventos na BD.
