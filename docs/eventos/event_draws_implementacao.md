# Pirâmide da Sorte — implementação e testes

Motor para os **55 templates `event_draw_pools`**, implementado em 2026-10-05. Cada template tem quinze prémios; o catálogo completo com pesos, probabilidades iniciais, referências e JSON está em [event_draws_overview.md](event_draws_overview.md). Não utiliza o motor da Caça às Toupeiras nem os contadores dos eventos de coleção.

## Evento global, participação individual

O administrador agenda uma ocorrência global com início e fim. Todos os heróis de nível 5 ou superior podem participar durante esse intervalo. Cada conta tem os seus próprios sorteios e resgates; um prémio sorteado por um herói não desaparece da pirâmide de outro.

O cliente aceita automaticamente o convite quando atualiza o botão da pirâmide, chamando `assignDrawEvent`. A definição partilhada tem um identificador como `revenge_draw_event`; a ocorrência global tem ID e prazo próprios; cada participação tem outro ID, enviado em `character.draw_event_id`.

A opção `--hero` do script só aceita também numa conta de teste. O evento mantém-se global. Um jogador offline recebe o convite ao voltar enquanto a ocorrência estiver disponível. Aceitar tarde mantém o fim global, sem prolongar individualmente o evento.

Existe uma Pirâmide da Sorte de cada vez por intervalo. É uma política local de calendário; o motor rejeita agendamentos sobrepostos. O calendário é independente do casino, eventos de coleção e Caça às Toupeiras, pelo que podem coexistir. Não existe agendamento anual automático.

## Componentes e schema

| Componente | Responsabilidade |
| --- | --- |
| `server-laravel/app/HeroZero/EventDraws.php` | Catálogo, calendário, participação, sorteios, planos de prémios, resgates e estado |
| `server-laravel/actions/drawEvents.php` | As três ações reais do cliente |
| `Character.php` | Overlay do ID de participação, substituindo o zero provisório |
| `Live.php` | Estado normal inclui objeto real e previews, retirando a fixture antiga |
| `Router.php` | Mapeamento das ações e atualização do login/respostas parciais da própria conta |
| `getEventHubEntries.php` | Acrescenta a pirâmide e preserva os restantes eventos |
| `docker/initdb/14_event_draws.sql` | Schema inicial |
| `public/assets/draw_event/` | Espelho local dos 55 ícones, 55 fundos e cabeçalho, copiados dos assets existentes |

A migração cria as seguintes tabelas sem ativar eventos:

| Tabela | Conteúdo |
| --- | --- |
| `event_draw_control` | Linha única `id=1`, bloqueada no calendário e aceitação |
| `event_draw_occurrences` | `id`, `identifier`, `ts_start`, `ts_end`, `enabled`; índice de disponibilidade |
| `event_draw_progress` | Associação herói/ocorrência, `identifier`, `status`, `payload`, `closed` |

A associação `character_id`/`occurrence_id` é única. A participação não pode ser aceite outra vez na mesma ocorrência, mesmo depois de completar os quinze prémios. O apontador `draw_event_id` é produzido no overlay e não foi criado como coluna da personagem.

O payload contém três mapas por índice baseado em zero: `drawn` guarda timestamps de sorteio, `claimed` guarda os resgates e `rewards` guarda os planos dos prémios sorteados. Os planos referenciam previews em `items` quando existe item. Os prémios ainda não sorteados não geram equipamento antecipadamente.

A migração é idempotente, aplica-se também a instalações existentes e deve executar fora de transações de jogo. As operações do evento não executam DDL. Dados e fixtures anteriores são preservados, mas não são usados como participações válidas ou compensados retroativamente.

## Calendário, relógio e datas

Uma ocorrência está disponível quando `enabled=1` e `ts_start <= GameTime::now() < ts_end`. O fim é exclusivo. Os scripts recebem `--start` em UTC do relógio do jogo; esse relógio pode estar numa data diferente do computador.

As datas textuais enviadas ao cliente usam `EventQuests::serverDate`, preservando o timestamp com o offset atual do parser do cliente. Cancelar desativa globalmente a ocorrência sem apagar prémios pendentes. O estado enviado inclui um fim efetivo já atingido para que o cliente não permita novos sorteios.

É possível recolher um prémio sorteado a tempo depois do fim/cancelamento. Não é possível fazer outro sorteio, obter outro prémio gratuito ou trocar a edição enquanto existir participação anterior com prémio por recolher. Após recolher as pendências, a participação fecha e fica guardada como histórico.

Ao abrir outra ocorrência do mesmo tema, recarregar o cliente. `Y.refreshDrawEvent` pode reutilizar o objeto por identificador e manter um convite anterior na sessão; a atualização completa da participação envia todos os campos, mas a receção de um convite de uma nova edição no mesmo browser deve ser confirmada com recarregamento. Não foi alterado o bundle nesta implementação.

## Sorteio sem reposição

O motor segue `DrawEvent.refresh` (`mm`) do JavaScript:

1. Remove do conjunto todos os índices que já têm timestamp de sorteio.
2. Soma os pesos `chance` dos restantes prémios.
3. A probabilidade de cada prémio restante é `chance / soma`.
4. Sorteia um inteiro uniforme de 1 até à soma dos pesos com `random_int`.
5. Escolhe o índice correspondente ao intervalo acumulado desse inteiro.

Os pesos são valores como 60, 70 ou 500; não são percentagens nem probabilidades já normalizadas. A seleção não depende de qual índice foi sorteado anteriormente, apenas de quais ainda restam. Com um único prémio restante, a probabilidade é 100%.

Cada índice só pode ser sorteado uma vez por ocorrência e herói. Não existe reset da pirâmide nas ações analisadas. Outra ocorrência do mesmo tema começa com o conjunto completo e primeiro sorteio gratuito.

O backend permite um prémio por recolher de cada vez, em linha com o botão do cliente, que fica desativado enquanto apresenta o diálogo de prémio. Um segundo pedido de sorteio enquanto existe pendência é recusado antes de debitar recursos. Essa regra evita que um pedido repetido volte a sortear/cobrar antes de recolher o resultado anterior.

## Custos confirmados no cliente

O custo depende de **quantos prémios já foram sorteados**, não do índice ou da raridade do prémio escolhido. O cliente obtém o preço da entrada com índice igual a esse número. O servidor recalcula a mesma quantidade com o progresso bloqueado; o pedido de sorteio não escolhe o preço.

Os quinze custos atuais são:

```text
0, 3, 6, 9, 12, 25, 35, 39, 45, 49, 59, 69, 79, 89, 99 donuts
```

O total para os quinze sorteios é **618 donuts**. O primeiro é gratuito, mas não garante o pet da primeira linha: usa os pesos de todos os prémios disponíveis. Por exemplo, se o primeiro sorteio escolher o índice 12, continua a custar zero; o segundo custa três donuts.

O débito só ocorre no sorteio, nunca na aceitação ou resgate. Falta de saldo devolve `errRemovePremiumCurrencyNotEnough` e não guarda índice, preview ou timestamp. O motor não altera o saldo do herói para tornar os testes manuais possíveis; os testes MySQL preparam saldo dentro da transação revertida.

## Prémios e geração local

O enum foi confirmado em `UiDrawEventReward.refresh` (`lr`). Os 55 catálogos atuais usam apenas:

| Tipo | Prémio | Implementação |
| --- | --- | --- |
| 3 | Pontos livres de atributos | Fator arredondado como quantidade direta; os exemplos atuais dão cinco |
| 5 | Item | Referência exata do template, incluindo equipamento, munição e pet |
| 12 | Modificação | Referência exata, incluindo slot e qualidade, com lendários de pet |

Há 520 entradas de item, 253 de modificação e 52 de atributos. O cliente reconhece outros enums, mas não foi adicionada interpretação de tipos que não existem nestes catálogos.

Equipamento e pets usam `Character::createEventRewardItem`, com a fórmula local da loja e bases de pets já existente. O tipo vem do template; equipamento e munição são agora sempre épicos. O preview é criado no sorteio e não é ressorteado no resgate. A entrega de pet copia as bases para `Sidekick::create`, preservando as habilidades fixas, e remove o preview do item. Munição usa `Missiles::PACKAGE_CHARGES`.

Modificações usam `createImprovementItem`, resolvendo slot/qualidade da referência. O orçamento local é `max(4, ceil(nível × 0,8))`, repartido pelos quatro atributos, com resto em resistência, como nos auxiliares do casino/tesouros. Uma referência `improvement_sidekick_legendary` produz uma modificação lendária de pet, não equipamento épico aleatório.

O fator 20 dos assets não é tratado como vinte itens ou vinte atributos. Não foi encontrada a fórmula oficial de geração de atributos no cliente; os atributos do equipamento e das modificações são balanceamento local documentado, enquanto referência, custos e pesos vêm dos assets; a raridade épica do equipamento é uma escolha local.

Os pontos de atributos são entregues no resgate. `discard_item=true` descarta o item sorteado, mas não elimina um prémio numérico de atributos. O índice fica resgatado e não volta ao conjunto de sorteio.

## Entrega, capacidade e atomicidade

A aceitação, sorteio, resgate e atualização de estado usam `StoryDungeon::transaction`: bloqueio da personagem/utilizador e savepoints quando há transação exterior. A participação é lida com `FOR UPDATE`. Calendário e cancelamento usam `Missiles::transaction` e a linha única de controlo.

No sorteio, disponibilidade e pendências são validadas, o custo é calculado/debitado, o preview é criado e o timestamp/plano são guardados dentro da mesma transação. Falhar a criação do prémio reverte também o débito. Não se sorteia outro prémio num pedido de atualização de estado.

No resgate, o servidor verifica que o índice foi sorteado e ainda não foi recolhido. Entrega e marcação são atómicas. IDs de itens não vêm do cliente: o servidor resolve-os no plano persistido da própria participação.

Equipamento e modificações exigem vaga desbloqueada na mochila, respeitando os limites de 6/12/18 posições do sistema existente. Pets vão para a coleção, sem consumir uma vaga da mochila. Mochila cheia devolve `errInventoryNoEmptySlot`, conservando o prémio para repetir depois de libertar espaço ou usar descarte.

Resgates repetidos ou índices inválidos devolvem `errClaimDrawEventRewardInvalidReward`, que o diálogo do cliente trata fechando-se. Os testes verificam repetições e falhas, incluindo ausência de saldo e capacidade. Não foi realizado um ensaio com dois processos simultâneos a resgatar a mesma participação.

## Estados e fecho da participação

| Estado | Significado utilizado |
| --- | --- |
| 1 | Ocorrência ativa; pode sortear, se houver prémios restantes e nenhuma pendência |
| 2 | Todos os quinze prémios sorteados e recolhidos; participação fechada |
| 3 | Ocorrência expirada/cancelada, com prémio sorteado ainda por recolher |
| 4 | Ocorrência expirada/cancelada, sem resgates pendentes; participação fechada |

Quando os quinze índices estão sorteados mas o último prémio ainda não foi recolhido, mantém-se estado 1 durante disponibilidade. O cliente deteta conjunto esgotado por `get_isComplete` e desativa o sorteio, permitindo recolher o último resultado.

A flag `closed` é separada do enum. Participações fechadas têm `character.draw_event_id=0`. O último objeto fechado pode continuar a ser enviado como `data.draw_event` para atualizar/fechar um diálogo que já esteja aberto. Isso não equivale a um convite nem permite outra aceitação na mesma ocorrência.

Uma nova ocorrência usa novo ID e progresso. O evento não prolonga prazos ao aceitar/recolher e não transfere o sorteio gratuito entre edições.

## Protocolo e respostas

| Ação | Parâmetros específicos | Efeito |
| --- | --- | --- |
| `assignDrawEvent` | `draw_event_id` textual | Aceita o template ativo na própria conta |
| `drawEventReward` | Nenhum | Sorteia um índice disponível e debita o custo da etapa |
| `claimDrawEventReward` | `reward_index`, `discard_item` | Recolhe ou descarta o prémio sorteado |

Os handlers resolvem o herói do `user_id` autenticado e devolvem `Live::accountState`, mantendo o envelope normal `{data,error}`. O convite contém identificador e datas, sem `id`. A participação contém ID, estado e datas, mais três campos por índice N de zero a catorze:

- `ts_rewardN_drawn`: timestamp positivo se foi sorteado, zero caso contrário.
- `rewardN_collected`: booleano de resgate.
- `rewardN`: string JSON do plano de prémio real, ou `{}` antes do sorteio.

Os campos são enviados para todos os quinze índices em cada atualização, para não manter timestamps antigos na interface. Previews referenciados acompanham a coleção `items`. O login real, estado normal e respostas parciais da própria conta são atualizados pelo mesmo motor; a fixture antiga é removida.

O hub usa `<template>_draw`, reconhecido por `EventHubEntry.get_type`, e omite participações fechadas. Mantém casino, eventos de coleção e Caça às Toupeiras.

## Scripts de calendário e preparação

Executar na raiz do projeto:

```bash
php scripts/testing/migrate_event_draws.php
php scripts/testing/start_event_draw.php --list
php scripts/testing/start_event_draw.php --calendar
php scripts/testing/start_event_draw.php revenge_draw_event --days=7
```

O último comando abre a Pirâmide da Sorte: Vingança **para o servidor inteiro**. Qualquer um dos 55 identificadores é aceite. A duração aceita 1–365 dias. Consultar o calendário antes de usar início futuro com `--start="AAAA-MM-DD HH:MM:SS"`, em UTC de GameTime.

Para agendar e aceitar também numa conta de teste:

```bash
php scripts/testing/start_event_draw.php revenge_draw_event --days=7 --hero="Nome do herói"
```

O nome deve resolver uma conta real única com nível mínimo. `--hero` exige início imediato. Se a aceitação falhar, a transação reverte o novo agendamento global. Não aceita novamente uma edição já aceite.

Para aceitar ou consultar um evento já aberto:

```bash
php scripts/testing/prepare_event_draw.php "Nome do herói" --accept
php scripts/testing/prepare_event_draw.php "Nome do herói" --show
```

`--accept` é persistente e não sorteia/entrega prémios nem muda saldo. `--show` apenas consulta, mostra índices sorteados/recolhidos e custo da próxima etapa; não normaliza expiração. Não há comando que force um pet ou atribua donuts. Para testar o diálogo basta o primeiro sorteio gratuito pelo cliente.

Cancelar globalmente pelo ID do calendário:

```bash
php scripts/testing/start_event_draw.php --cancel=1
```

O cancelamento preserva prémios por recolher. Não é um reset do progresso nem elimina a participação do herói.

## Testes automáticos e validação

```bash
php scripts/testing/test_event_draws.php
node scripts/testing/test_event_draws_client.js
php scripts/testing/test_event_draws.php --mysql --hero="Nome do herói"
```

O teste offline verifica os 111 assets públicos da pirâmide, 55 definições, 825 entradas, referências, pesos, custos, probabilidades normalizadas e seleção sem reposição até esgotar cada catálogo. Não altera a BD e não utiliza um PDO simulado específico desta família.

O teste Node executa os métodos reais de `DrawEvent` do bundle, compara custos e probabilidades com PHP em **880 estados** (55 temas × 16 etapas, incluindo conjunto esgotado), e verifica parsing de prémios, convites, pendências, estados de resgate e tipo do hub. Não renderiza o cliente num navegador.

O teste MySQL faz os quinze sorteios e resgates dos 55 catálogos, com descarte de itens para validar todos os previews, sem deixar equipamento de teste na conta. Verifica o débito total de cada edição e ausência de repetição. Cenários adicionais preparam os outros índices como resgatados dentro da transação para testar entrega de um alvo exato: pet, equipamento com mochila cheia/retry, modificação lendária de pet e cinco pontos de atributos.

Também exercita convite global e progresso independente em duas contas quando existe outra conta real, os três handlers reais, login com rotação de sessão revertida, `accountState`, hub, nível mínimo, sobreposição de calendário, falta de saldo, cancelamento, expiração por GameTime, resgate tardio, nova edição e recusa de aceitação antes do início. Todo o teste é revertido por rollback, incluindo preparação de saldo/nível/inventário e sessões. Sem `--hero`, escolhe a primeira conta real. Evitar correr sobre um herói que esteja a jogar durante o teste.

A migração foi aplicada e repetida para verificar idempotência. Os 111 assets estão espelhados no caminho público e um pedido real pelo kernel HTTP ao fundo de Vingança devolveu 200/image-webp. As regressões MySQL de eventos de coleção e Caça às Toupeiras também passaram. Testes offline, Node e MySQL passaram; a confirmação visual completa no navegador continua pendente. Não foi ativado permanentemente um evento nem deixada preparação de conta pelos testes.

### Guião de confirmação visual

1. Abrir uma ocorrência global e recarregar uma conta de nível 5 ou superior.
2. Confirmar aceitação automática e botão da Pirâmide da Sorte.
3. Verificar o primeiro custo zero e as probabilidades apresentadas nos tooltips.
4. Sortear; confirmar que só um prémio é marcado e que surge o diálogo com o preview real.
5. Recolher; verificar item/pet/modificação/atributos e custo seguinte de três donuts.
6. Confirmar que o prémio recolhido deixa de participar nas probabilidades.
7. Testar mochila cheia com equipamento, libertar espaço e repetir sem voltar a pagar o sorteio.
8. Cancelar uma ocorrência de teste com prémio pendente; confirmar recolha tardia e ausência de novos sorteios.
9. Abrir outra edição, recarregar e confirmar que começa com quinze prémios disponíveis e custo zero.

## Diagnóstico e limites

| Sintoma | Verificação |
| --- | --- |
| Botão não aparece | Calendário/GameTime, nível mínimo, participação anterior pendente e recarregamento |
| Aceitação recusada | Identificador ativo, nível e participação já aceite na mesma ocorrência |
| Não permite sortear | Recolher prémio pendente; verificar prazo, conjunto esgotado e saldo |
| Primeiro sorteio não dá o pet | O primeiro é gratuito, mas a seleção usa os pesos de todos os prémios |
| Preço difere da linha do prémio | O preço corresponde à etapa do sorteio, não ao prémio escolhido |
| Recompensa de equipamento não entra | Libertar posição desbloqueada da mochila ou usar descarte |
| Estado 3 depois do cancelamento | Existe prémio sorteado por recolher; pode ser recolhido após o fim |
| Não aparece outra edição do mesmo tema | Fechar pendências anteriores e recarregar o cliente |

Não existe reset/recompra da mesma edição, calendário anual automático, editor no painel administrativo ou fórmulas oficiais confirmadas de atributos dos prémios. A implementação cobre os 55 pools atuais; novos tipos de prémio nos assets exigem implementação explícita. Busca de Objetos e Academia Heroica permanecem sistemas separados e pendentes.

## Equipamento épico com espaço gratuito

Nos novos prémios de equipamento dos tipos 1–8, a qualidade é sempre 3 (épica), por escolha de balanceamento local. Os tipos 1–7 chegam com borda dourada (`ts_availability_start=15`) e espaço de modificação vazio e gratuito (`ts_availability_end=1`), tanto no preview como na mochila. Munições recebem a borda, mas não aceitam modificações. Pets mantêm as bases e qualidade do catálogo; gemas mantêm a referência e raridade próprias, incluindo lendárias.

Para os equipamentos dos tipos 1–7, a geração partilha o cálculo do brechó/loja: nível requerido `max(1, nível do herói − aleatório(0..4))`, orçamento `ceil((nível requerido × 1,6 + 2 + nível requerido² × 0,004) × 2,3)` mais o bónus plano do esconderijo, distribuído por 3–4 atributos. Armas usam dano `ceil(nível requerido × 1,035) + 2`. A borda não acrescenta um multiplicador de atributos. O item é gerado uma única vez e entregue sem recalcular atributos. A alteração aplica-se aos previews gerados a partir desta atualização; itens já gerados ou entregues conservam os seus dados.

Os testes MySQL verificam a soma dos atributos contra a fórmula, raridade, borda, espaço e dano das armas em todos os catálogos, dentro de rollback. Não exige migração de schema.

### Correção das munições

Os mísseis usam `Missiles::template` com o nível atual do herói, requisitos do catálogo e 100 cargas iniciais. Os quatro atributos e `stat_weapon_damage` ficam a zero: o dano das munições é calculado por `Missiles::damage(item_level, pet ativo)`, usando o fator das constantes e habilidades compatíveis do pet. A fórmula épica dos equipamentos aplica-se apenas aos tipos 1–7. Mantêm raridade épica, borda dourada e ausência de espaço de modificação.

Para reparar mísseis já gerados pelos dois eventos: `php scripts/testing/repair_event_missiles.php "Nome do herói" --show`; depois `--apply`. O script identifica exclusivamente os IDs guardados nos prémios destes eventos, guarda backup dos atributos e corrige apenas atributos indevidos, preservando nível, cargas, qualidade e restantes campos.
