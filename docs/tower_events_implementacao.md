# Academia Heroica — funcionamento, operação e implementação

A Academia Heroica corresponde a `tower`/`tower_event` no cliente HTML5 257.
O servidor implementa calendário global, aceitação individual, construção,
economia, gestores, objetivos, inventário, boosters e entrega de recompensas.
As definições e referências vêm das constantes; a integração temporal e o
balanceamento descritos abaixo são regras locais, não fórmulas confirmadas do
backend oficial.

## 1. Como se inicia

Há três operações diferentes:

1. **Arrancar o servidor:** o processo HTTP tem de estar ligado para jogar.
   Os scripts da academia não arrancam esse processo.
2. **Abrir uma ocorrência global:** o administrador agenda um tema com início e
   fim. Todos os heróis elegíveis podem receber o convite durante esse intervalo.
3. **Aceitar com um herói:** o convite envia `startTowerEvent`. Só então nasce o
   progresso individual. O script `join_tower_event.php` permite esta aceitação
   administrativa pelo nome, sem conceder prémios ou recursos adicionais.

A migração não abre eventos. Não há calendário anual automático nem necessidade
de um cron para ativar uma ocorrência agendada: os pedidos consultam o relógio
e o intervalo. O fim é exclusivo: `ts_start <= GameTime::now() < ts_end`.
Aceitar mais tarde não prolonga o evento.

O nível mínimo do catálogo é 5. Só pode existir uma ocorrência habilitada em cada
intervalo. Um herói não pode aceitar duas vezes a mesma ocorrência; uma nova
ocorrência do mesmo tema permite uma nova participação depois de fechar a anterior.

## 2. Scripts de administração

Executar na raiz do projeto. Usam a configuração Laravel/MySQL e não exigem que
o processo HTTP esteja ligado. Os comandos de consulta e `--dry-run` não gravam
dados; a migração altera schema e os comandos de arranque/entrada/cancelamento
sem `--dry-run` são persistentes.

| Script | Responsabilidade |
|---|---|
| [migrate_tower_events.php](../scripts/testing/migrate_tower_events.php) | Cria/verifica tabelas de forma idempotente. Executar fora de transações de jogo; não agenda eventos. |
| [start_tower_event.php](../scripts/testing/start_tower_event.php) | Lista temas, consulta calendário e agenda uma ocorrência global. Opcionalmente aceita também com `--hero`. |
| [join_tower_event.php](../scripts/testing/join_tower_event.php) | Aceita a ocorrência atualmente ativa para um herói identificado pelo nome. Não agenda nem reinicia. |
| [stop_tower_event.php](../scripts/testing/stop_tower_event.php) | Cancela globalmente uma ocorrência, conservando histórico e prémios desbloqueados por resgatar. |

Preparação e consulta:

```bash
php scripts/testing/migrate_tower_events.php
php scripts/testing/start_tower_event.php --list
php scripts/testing/start_tower_event.php --calendar
```

Simular e abrir Terra por sete dias a partir do relógio atual do jogo:

```bash
php scripts/testing/start_tower_event.php earth_towerevent --days=7 --dry-run
php scripts/testing/start_tower_event.php earth_towerevent --days=7
```

O segundo comando abre o evento para o servidor inteiro. Não o limita a um herói.
Para aceitar também numa conta, usar `--hero="Nome do herói"` no arranque imediato,
ou aceitar separadamente:

```bash
php scripts/testing/join_tower_event.php "Nome do herói" --dry-run
php scripts/testing/join_tower_event.php "Nome do herói"
```

A aceitação exige nome único, nível mínimo e ausência de participação anterior
aberta. Repetir para um herói que já participou na ocorrência não cria outra linha.
Se houver prémios antigos pendentes, recolhê-los e atualizar o jogo primeiro.

Para agendar o futuro, consultar `--calendar` e escolher uma data **UTC de
GameTime**, não a data real do computador:

```bash
php scripts/testing/start_tower_event.php earth_towerevent --days=7 --start="2027-07-01 12:00:00" --dry-run
```

A data é apenas um exemplo: substituir por uma data futura relativamente ao valor
mostrado no calendário e retirar `--dry-run` para gravar. Duração: 1–365 dias,
por omissão 7. Datas anteriores ao relógio do jogo e intervalos sobrepostos são
recusados. `--hero` exige início imediato.

Cancelamento:

```bash
php scripts/testing/stop_tower_event.php --current --dry-run
php scripts/testing/stop_tower_event.php --current
php scripts/testing/stop_tower_event.php --id=123 --dry-run
```

Substituir `123` pelo ID mostrado no calendário. Sem alvo, usa `--current`.
Também aceita um identificador de tema, desde que corresponda a uma única
ocorrência habilitada ativa/futura. Para várias ocorrências do mesmo tema, usar
`--id=N`. O arranque mantém a opção legada `--cancel=N`; preferir o script de
cancelamento dedicado. Cancelar afeta todos os participantes.

Depois de mudar a edição ou cancelar, recarregar o cliente. Os scripts não
apagam participações, não distribuem automaticamente prémios e não repõem os
níveis de heróis que já tenham participado.

## 3. Catálogo e identificadores

| Tema | `event_id`/identificador | Torre | Patamares finais | Tokens para o último |
|---|---|---:|---:|---:|
| Teste | `test_towerevent_2020` | 200 | 15 | 120 |
| Terra | `earth_towerevent` | 206 | 25 | 150 |
| Sete Mares | `bounties_of_seven_seas_towerevent` | 208 | 25 | 150 |
| Sci-Fi | `sci_fi_week_towerevent` | 207 | 32 | 160 |
| Ermos | `wasteland_towerevent` | 209 | 25 | 150 |
| Zumbis | `zombies_towerevent` | 205 | 25 | 150 |

Os constantes são JSON comprimido com zlib. `TowerEvents::reference()` lê e
coloca em cache as entradas `tower*`, `item_templates` e `optical_changes` de
`server-laravel/public/assets/data/constants_json.data`.

Não confundir identificadores:

- `occurrence_id`: inteiro da abertura global no calendário;
- `event_id`: string do tema; enviada nas ações do evento;
- `tower_id`: inteiro da torre do catálogo;
- ID de progresso: chave interna da participação;
- ID de inventário da academia: ID virtual dessa participação, não ID da mochila.

## 4. Componentes e persistência

| Ficheiro/componente | Função |
|---|---|
| `app/HeroZero/TowerEvents.php` | Calendário, participação, estado/protocolo, ações, inventário e prémios. |
| `app/HeroZero/TowerEconomy.php` | Cálculo económico, níveis, regiões, gestores e verificação de tarefas. |
| `actions/towerEvents.php` e `Router.php` | Encaminham as ações autenticadas para o motor e completam a resposta. |
| `Live.php` | Inclui estado no login e em `accountState`, além dos caminhos de atualização existentes. |
| `actions/getEventHubEntries.php` | Inclui o convite elegível no hub. |
| `Character.php` | Geração de equipamento, qualidade, borda, espaço e orçamento de atributos. |
| `public/js/HeroZero.min.js` | Ponte entre a simulação local original e comandos validados pelo servidor. |
| `docker/initdb/17_tower_events.sql` | Schema para instalações novas; a migração aplica-o às existentes. |

| Tabela | Conteúdo |
|---|---|
| `tower_event_control` | Linha `id=1` para serializar agendamento, cancelamento e aceitação. |
| `tower_event_occurrences` | Tema, início, fim e `enabled`; cancelar muda a disponibilidade e preserva o registo. |
| `tower_event_progress` | Personagem, ocorrência, JSON `payload` e `closed`; unicidade personagem/ocorrência impede segunda participação. |
| `tower_event_optical_unlocks` | Aparências desbloqueadas, únicas por personagem/aparência. |

O `payload` contém `started`, `last`, `bank`, `idle`, `idle_duration`, `tokens`,
`round`, `tower`, `items`, `next_item`, `boosts`, `claimed`, `rewards` e
`active_tower_id`. A participação começa com banco 8, tokens 0 e ronda 1.
A torre começa com 30 salas fechadas, elevador/cofre de nível 1, pools de gestores
vazios e primeira região aberta.

## 5. Funções de `TowerEvents`

| Função | Responsabilidade e efeito |
|---|---|
| `reference()` | Carrega/cacheia as referências comprimidas necessárias. |
| `definition(id)` | Obtém um tema conhecido ou rejeita o identificador. |
| `installed()` | Verifica a existência do schema antes de integrar o estado. |
| `schedule(id,start,end)` | Valida duração e tema, bloqueia controlo e recusa sobreposição antes de inserir ocorrência. |
| `cancel(id)` | Bloqueia controlo e desabilita uma ocorrência existente. |
| `current()` | Procura a ocorrência habilitada dentro do intervalo atual. |
| `pending(cid,lock)` | Obtém a participação aberta; opcionalmente bloqueia-a para escrita. |
| `occurrence(row)` / `available(e)` | Recuperam a abertura global e verificam disponibilidade no relógio do jogo. |
| `fresh(id,now)` | Cria o payload inicial individual, sem entregar prémios. |
| `save(row,p)` | Persiste o payload restringindo a atualização à participação/personagem. |
| `assign(cid,identifier)` | Aceita o evento ativo, valida nível, bloqueia personagem/controlo e impede participações repetidas ou simultâneas. |
| `objectives(id)` | Resolve a coleção de patamares finais do tema. |
| `debit(char,cost)` | Valida e consome donuts; não usa o banco da academia. |
| `addItem(p,id,amount)` | Valida referência/quantidade; tokens aumentam o contador, outros itens ganham IDs virtuais. |
| `inventory(p)` | Serializa inventário: token virtual de ID 1 e restantes itens da participação. |
| `eventData(id,e,p)` | Constrói datas, patamares, prémios resgatados, ganho offline e `tower_data`. |
| `clientState(id,e,p)` | Compõe `tower_event_data`: ocorrência, tutorial, torres, inventário, boosts, áreas e eventos. |
| `attach(data,cid)` | Atualiza rendimento e participação sob lock, fecha estados sem prémios pendentes e integra dados/aparências na resposta. |
| `hub(cid)` | Cria convite para um herói elegível que ainda não tenha participado. |
| `action(cid,a)` | Executa uma ação validada numa transação e devolve estado autoritativo atualizado. |
| `useItem(p,a,now)` | Consome boosters, rendimento instantâneo ou redução de espera de região; impede reutilização. |
| `reward(char,p,d,discard)` | Resolve e entrega recursos, equipamento, pets, modificações, aparências e itens da academia. |

`attach()` também pode escrever rendimento calculado e fechar a participação;
não é apenas um serializador. Login, estado normal e respostas parciais devem
conter as coleções referenciadas antes de o cliente desenhar a academia.

## 6. Funções de `TowerEconomy` e economia

| Função | Responsabilidade |
|---|---|
| `fresh(towerId,now)` | Inicializa salas, elevador, cofre, regiões e gestores. |
| `number(v,min,max)` | Aceita inteiros dentro dos limites do protocolo. |
| `spend(p,cost)` | Valida custo finito/não negativo e debita o banco se houver saldo. |
| `target(tower,kind,index)` | Obtém por referência sala, elevador ou cofre; valida alvo. |
| `pool(tower,kind)` | Obtém o pool de gestores correspondente ao componente. |
| `effect(tower,kind,index,now)` | Resolve o efeito de um gestor atribuído, apenas durante a ativação. |
| `flows(p,now,managedOnly,effects)` | Calcula capacidades separadas de salas, elevador e cofre, com efeitos/boosters. |
| `advanceProduction(p,now,seconds)` | Produz, transporta e entrega moedas em etapas, mantendo filas persistentes; apenas entrega aumenta o banco. |
| `rate(p,now,managedOnly,effects)` | Calcula rendimento por segundo limitado pelo menor fluxo entre produção, transporte e armazenamento. |
| `accrue(p,now)` | Integra ganhos desde `last`, respeitando expiração de boosters/efeitos e avanço do relógio. |
| `objectiveReached(tower,task)` | Verifica salas abertas, soma de níveis e nível específico de sala/cofre/elevador. |
| `command(p,a,now)` | Valida e executa melhorias, desbloqueios, regiões e operações de gestores. |

As salas produzem; o elevador transporta; o cofre recebe. `rate()` usa os valores
por nível do catálogo e calcula `min(soma_das_salas, elevador, cofre)`. Melhorar só
uma parte pode deixar outra como estrangulamento. Boosters ativos somam os seus
fatores, com mínimo 1; efeitos de gestores alteram o atributo correspondente.

Por intervalo entre pedidos, os primeiros 60 segundos passam pelo circuito online:
produção nas salas, transporte pelo elevador e entrega pelo cofre. Só a entrega
entra no banco disponível. O restante gera rendimento offline a `TowerIdleFactor=0,1`,
exigindo gestores atribuídos nas salas participantes, elevador e cofre.
`collectTowerIdleReward` transfere o acumulado para o banco. A integração é
limitada ao fim global e não cria ganho para relógio que retrocede. O limite de
60 segundos e esta integração são decisões locais.

O rendimento instantâneo usa a taxa com gestores, sem efeitos/boosters,
multiplicada pelo tempo do item. As moedas da academia são independentes das
moedas normais e dos donuts da conta.

Limites do catálogo: 30 salas, nível máximo 1000 por sala, 3000 no elevador e
cofre. O desbloqueio é sequencial; a sala 2 exige sala 1 de nível 5. As melhorias
de elevador/cofre e contratação exigem sala 1 de nível 3. Há seis regiões:

| Região | Salas | Custo base | Espera |
|---:|---|---:|---:|
| 0 | 1–5 | Grátis | Sem espera |
| 1 | 6–10 | 20 750 000 | 1 h |
| 2 | 11–15 | 41 500 000 000 000 | 2 h |
| 3 | 16–20 | 1,328 × 10²¹ | 4 h |
| 4 | 21–25 | 6,3744 × 10²⁸ | 8 h |
| 5 | 26–30 | 2,0584 × 10³⁷ | 8 h |

Contratar sorteia gestor comum/raridade 1 (52,5%), raro/2 (35%) ou épico/3
(12,5%) e um efeito compatível. O primeiro gestor de sala é comum com efeito 2.
A primeira contratação de cada pool custa 5; as seguintes usam a tabela de
custos. Limites: 200 gestores de sala e 100 por elevador/cofre. O nome local é
`Gestor N`. Não é possível atribuir o mesmo gestor a dois alvos, vender um gestor
atribuído ou ativá-lo durante cooldown. A venda devolve 50% do custo de compra.

## 7. Ações e protocolo

As ações são autenticadas pelo router normal. IDs de evento/torre enviados têm
de corresponder à participação. A resposta inclui `event`, `tower_items`,
`active_boosts` e `tower_authoritative` (torre, banco, boosts e ronda), além dos
campos específicos. A resposta completa integra também `tower_event_data`.

| Ação | Parâmetros relevantes / comportamento |
|---|---|
| `startTowerEvent` | `event_id`: cria a participação elegível. |
| `enterTower` | `new_tower_id`: valida a torre e torna-a ativa. |
| `syncTowerEvent` | `tower_data` JSON: aceita apenas avanço de `tutorial_step` entre 1 e 100; rejeita snapshots demasiado grandes. |
| `towerCommand` | Comando individual validado; ver tabela seguinte. |
| `unlockTowerFloor` | `floor_index`: desbloqueio premium da sala, com custo do catálogo e verificações de sequência/região. |
| `collectTowerIdleReward` | Transfere ganho offline para banco; retorna `reward_amount`. |
| `collectTowerObjectiveReward` | `objective_key` e `objective_id`: exige tarefa interna atingida e ainda não resgatada; entrega itens/tokens. |
| `skipTowerEventObjective` | `objective_index`: patamar válido com tokens insuficientes e custo de salto definido; consome donuts e sobe tokens até ao limiar. |
| `collectTowerEventObjectiveRewards` | `objective_indices` JSON: lote único de patamares desbloqueados, ainda não resgatados; `discard_item` permite abdicar de equipamento/modificação. |
| `resetTowerEventProgress` | Exige todos os patamares finais resgatados; custa 49 donuts e inicia nova ronda no mesmo prazo global. |
| `activateAllTowerManagers` | Ativa gestores atribuídos elegíveis; exige pelo menos um e custa 1 donut. |
| `buyTowerBoosterGachaPackage` | `booster_gacha_package_id`: valida pacote e sorteia booster pelos pesos do catálogo. |
| `buyTowerTimeSkipItem` / `buyTowerInstantIncomeItem` | `timeskip_item_id` / `instant_income_item_id`: compra referência do tipo correto e marcada como comprável, debitando donuts. |
| `useTowerInventoryItem` | `item_id`, `amount`: exige item próprio não consumido; quantidades múltiplas apenas para boosters. |

`syncTowerEvent` nunca aceita como autoridade níveis, dinheiro, gestores, tokens,
prémios ou boosters enviados pelo cliente.

| `towerCommand.command` | Operação |
|---|---|
| `level` / `unlock` | Melhora ou abre alvo `kind` (`floor`, `elevator`, `vault`), usando `index` e `count` (quantidade de níveis). |
| `region` | Paga e inicia espera de uma região `region` entre 1 e 5. |
| `completeRegion` | Abre região `region` cujo prazo já venceu. |
| `hire` | Compra gestor do pool compatível, atribuindo-o se o alvo estiver vazio. |
| `assign` / `unassign` | Atribui/remove gestor próprio pelo ID. |
| `activate` | Inicia efeito do gestor atribuído quando o cooldown permite. |
| `sell` | Vende gestor não atribuído e devolve parte do custo. |

Os nomes exatos dos campos de comando são definidos por `TowerEconomy::command`
e pela ponte do cliente; não enviar snapshots completos para substituir estas
operações.

## 8. Objetivos, inventário e recompensas

Existem **tarefas internas da torre**, que dão tokens/itens, e **patamares finais
do evento**, desbloqueados pelo total de tokens. Cada resgate tem um marcador
persistente. `claimed` guarda patamares finais; `tower.objectives` guarda tarefas
internas. Um lote de prémios é atómico: se um item não couber, nenhum prémio desse
pedido fica marcado/entregue. Libertar espaço e repetir permite o resgate.

| Tipo de prémio do evento | Entrega |
|---|---|
| 0–5 | Recursos numéricos: donuts, moedas, XP, energia, treinos e pontos de atributos, conforme enum do catálogo. |
| 6 | Item de template; equipamento para mochila ou pet para coleção. |
| 14 | Aparência persistente, integrada em `current_optical_changes.unlocked_options`. |
| 16 | Modificação compatível com o slot e raridade da referência. |
| 46 | Booster no inventário da academia. |
| 50 | Item de redução de espera no inventário da academia. |

Valores numéricos explícitos do catálogo são respeitados. Valores sentinela
negativos usam o conversor local de `EventTreasures::bonus`: moedas dependem do
nível, XP da diferença para o próximo nível, e outros recursos do fator definido.
Estas conversões não são uma fórmula oficial comprovada.

Os tipos dos **itens da academia** são um enum separado: 1 booster, 2 rendimento
instantâneo, 5 redução de espera e 6 token. Os tipos 3/4 de rendimento duplo do
catálogo não têm consumo implementado. O token ocupa o ID virtual 1; outros itens
começam no ID 2. Usar marca `status=2`; não se pode reutilizar. Booster prolonga
a duração quando já existe o mesmo efeito. Redução de espera atua numa região
em construção e não no cooldown de gestores; exige `type=1` e
`reference_id="TORRE_REGIÃO"`, por exemplo `206_1`.

Não são pré-gerados equipamentos persistentes para o preview: o cliente mostra
as referências genéricas do catálogo; os dados do prémio efetivo são registados
quando o resgate é concluído.

## 9. Equipamento lendário dourado e fórmula

`Character::createTowerRewardItem()` cria equipamento dos tipos 1–7 com
`quality=4`, borda dourada (`ts_availability_start=15`) e espaço de modificação
vazio/gratuito (`ts_availability_end=1`). Mísseis/tipo 8 têm qualidade 4 e borda,
mas não espaço de modificação nem atributos de equipamento. Pets/tipo 9 mantêm
qualidade e estatísticas próprias do template e entram na coleção.

`Character::shopItemMultiplier()` centraliza o balanceamento pedido:

| Qualidade | Multiplicador sem borda | Com borda dourada |
|---|---:|---:|
| Comum | 1,0 | 1,0 |
| Raro | 1,7 | 1,7 |
| Épico | 2,3 | 2,7 |
| Lendário | 3,0 | 3,5 |

O valor `3.x` pedido foi concretizado como 3,0. O fator 3,5 substitui a ideia
inicial de acrescentar 20%: não se aplica outro +20% por cima.

```text
nível requerido = max(1, nível do herói − aleatório entre 0 e 4)
base = nível requerido × 1,6 + 2 + nível requerido² × 0,004
orçamento = ceil(base × multiplicador) + item_flat do esconderijo
```

Exemplo: nível requerido 130 dá base 277,6; lendário dourado dá `ceil(971,6)=972`
antes do bónus `item_flat`. O orçamento é dividido por três ou quatro atributos
(40%/60% para épicos/lendários); o distribuidor limita cada parcela ao restante,
para não criar pontos extra em orçamentos pequenos. A borda não altera o número
de atributos. O dano de arma tem cálculo próprio, não recebe ×3,5.

A fórmula partilhada afeta novos itens gerados por estes métodos, incluindo os
prémios épicos dourados que usam `createEventRewardItem` (×2,7). Não recalcula
itens antigos. O casino tem um caminho próprio de geração; não presumir que
esta alteração lhe substitui a fórmula. Modificações usam orçamento próprio,
não a fórmula de equipamento. Ver [geração de itens](shop_items/geracao_itens.md)
e [modificações](shop_items/modificacoes.md).

## 10. Fim, cancelamento e nova ronda

Ao terminar/cancelar, deixam de ser aceites melhorias, compras, uso de itens,
resgates internos e recolha de rendimento offline. Continua permitido
`collectTowerEventObjectiveRewards` para patamares finais já desbloqueados e
não resgatados. Cancelar não apaga equipamentos já entregues ou aparências.
Não existe entrega automática de prémios nem conversão do banco/tokens restantes.

`attach()` fecha a participação terminada quando já não há patamares finais
resgatáveis. Até lá permanece pendente, impedindo outra aceitação. O mesmo tema
pode voltar em outra ocorrência; é o ID da ocorrência que distingue as edições.

O reset pago dentro de uma ocorrência mantém inventário de itens e sequência de
IDs, incrementa a ronda e reinicializa banco, tokens, construção, gestores,
boosters, ganhos offline e marcadores de objetivos. Mantém o fim global e não
remove prémios já entregues à conta.

## 11. Transações e proteção

`StoryDungeon::transaction` bloqueia personagem/utilizador; a participação é
lida com `FOR UPDATE`. `Missiles::transaction` protege operações administrativas
e a linha de controlo serializa calendário/aceitação. Ambos respeitam uma
transação exterior com savepoints. Não se executa DDL nestas transações.

A mesma transação valida estado, propriedade, compatibilidade, saldo e capacidade,
consome recursos, entrega prémios e grava os marcadores. Repetir um resgate,
forjar nível/saldo em sincronização ou reutilizar um item não concede recursos.
Custos de academia usam o banco; custos premium validam o saldo de donuts.

Erros relevantes: `errRequestInvalidParameter` para referências/formatos inválidos,
`errInvalidFunction` para operações fora do estado permitido,
`errNotEnoughGameCurrency` para banco insuficiente e
`errRemovePremiumCurrencyNotEnough` para donuts insuficientes. Não inferir um
resgate concluído apenas da animação: verificar a resposta do servidor.

## 12. Ponte JavaScript e erro `occurrence_id`

O cliente original simula várias operações localmente. O bloco delimitado por
`HZ_TOWER_SERVER_BRIDGE_BEGIN`/`END` envia comandos ao backend e aplica a torre,
banco, inventário e boosters devolvidos. Atualiza pools e liga gestores aos alvos
antes de reconstruir os modelos visuais. Aplicar o banco autoritativo evita somar
novamente uma recompensa já contabilizada pelo servidor.

`Y.refreshTowerEventData` verifica `hasData("occurrence_id")` no estado novo e
anterior antes de ler/comparar o campo. O estado vazio sem academia não tem esse
campo; a leitura incondicional causava a exceção reportada durante o login ou
atualizações parciais. A ocorrência permite distinguir duas edições do mesmo
tema. O `index.html` usa a versão de cache `20261009-tower-events-v4`; depois de
atualizar assets, fazer Ctrl+F5 para carregar o bundle corrigido.

## 13. Verificação e diagnóstico

```bash
php scripts/testing/test_tower_events.php
php scripts/testing/test_tower_events.php --mysql
node scripts/testing/test_tower_events_client.js
```

O teste offline verifica catálogo, multiplicadores, soma de atributos, economia,
gestores e cooldowns. A opção MySQL percorre seis variantes, patamares/prémios,
duplicados, snapshots forjados e reset, preparando dados dentro de uma transação
com rollback. Ler a saída completa; não executar migração dentro desse teste.
O teste JavaScript executa métodos reais do bundle com UI simulada, incluindo
estado sem `occurrence_id`, troca de ocorrência e comandos da ponte. Não prova
renderização visual no browser.

| Sintoma | Verificação |
|---|---|
| Convite não aparece | Schema instalado, calendário/GameTime, nível 5, edição já aceite ou participação anterior pendente. |
| Data parece errada | Consultar `--calendar`; usar UTC do jogo. |
| Não consegue abrir nova ocorrência | Verificar sobreposição com ocorrências habilitadas, incluindo futuras. |
| Moedas não aumentam offline | Verificar gestores nas salas/elevador/cofre e recolha do ganho offline. |
| Prémio não entra na mochila | Libertar slot e repetir; o pedido deve ter revertido por inteiro. |
| Erro `occurrence_id` persiste | Verificar asset servido e Ctrl+F5; consultar [BUG-000174](bugs_encontrados.md). |

A confirmação manual deve cobrir convite, entrada, melhorias, contratação,
ativação, compras/uso, recolha offline, resgate e aspeto lendário/dourado com slot.
Os testes automatizados passados não substituem essa confirmação. Não há editor
de calendário no painel, calendário anual, preview pré-gerado dos equipamentos
ou reprodução comprovada de todos os detalhes temporais do servidor oficial.

### Validação dos scripts administrativos (2026-10-09)

Verificados com MySQL numa transação exterior revertida: arranque global,
aceitação por nome, repetição sem duplicar, simulação de entrada/cancelamento,
cancelamento efetivo, repetição de cancelamento e preservação do histórico.
Verificado também arranque imediato com `--hero` e simulação de agendamento.
O calendário foi consultado depois: não ficou nenhuma ocorrência dos testes.
PHP lint, teste da academia offline/MySQL e contrato JavaScript passaram.
A confirmação visual continua pendente.

### Contrato de abertura da primeira região — BUG-000175

Para o cliente, `ts_unlocked` sozinho não abre uma região. `ij.get_state()` exige
`ts_unlock_started != 0`, temporizador concluído e `ts_unlock_end=-1` para devolver
estado 4 (aberto). A primeira região tem tempo de espera zero; o estado inicial
usa o timestamp de criação tanto para início como para desbloqueio.

Participações antigas com primeira região desbloqueada mas início zero são
normalizadas em `TowerEconomy::accrue()` antes da verificação de tempo decorrido.
`attach()` e ações guardam a alteração sob lock, preservando saldos/progresso.
Após recarregar o jogo, `UiTowerFloorLocked.refresh()` mostra o botão de compra
por 8 moedas, pago pelo banco inicial de 8; não exige donuts. A placa vazia da
textura `tower_new_room_marker` é decoração e não o botão.

A regressão executa os métodos reais do cliente para estado, visibilidade, custo
e clique, além de verificar no MySQL a reparação de progresso existente nas seis
variantes com rollback. A renderização visual continua a exigir confirmação no
browser. Não é preciso cancelar o evento nem reiniciar o herói.

### Atualização dos funcionários — BUG-000176

O callback `managers` do pool deve obter a coleção tipada através de
`getTypedObject("managers").get_asArray()` antes de chamar `syncManagers`.
`syncVectorFromArray` precisa de objetos com `hasData/getData`; um array de JSON
recebido por HTTP não satisfaz esse contrato. A primeira versão da ponte expôs
esta incompatibilidade no listener original, causando erro ao contratar.

A conversão aplica-se aos três pools (salas, elevador e cofre), preservando objetos
existentes e sincronizando inclusões/remoções. A regressão JavaScript usa agora
os listeners/sincronizadores reais, também dentro da aplicação do snapshot pela
ponte, em vez de um pool simulado que já fazia a conversão automaticamente.
Não é preciso modificar prémios, saldo ou progressos persistentes para reparar
a renderização de um funcionário cuja contratação já foi confirmada pelo servidor.

### Acumulados de produção e transporte — BUG-000177

A economia anterior somava `min(produção, transporte, cofre)` diretamente ao banco
online e deixava os `bank_amount` intermédios permanentemente em zero. Isso não
correspondia aos indicadores da UI: estes mostram moedas acumuladas, não taxas.

Agora `flows()` calcula as capacidades separadas. `advanceProduction()` usa passos
de até um segundo: primeiro entrega moedas já acumuladas no elevador, depois
transporta moedas já produzidas nas salas e finalmente acrescenta produção nova.
O fluxo é sala → elevador → banco; uma moeda não atravessa as três etapas no
mesmo passo. Filas e saldo são persistidos e enviados nos campos existentes.
O transporte percorre as salas por índice crescente, limitado pela capacidade
calculada do elevador. Filas podem crescer quando uma etapa é mais lenta.

Este é um modelo local por capacidades, não uma reprodução exata dos movimentos
individuais dos funcionários. A janela online de 60 segundos mantém-se; ganho
offline continua separado, com gestores e fator 0,1, sem consumir as filas online.
Não são aceites saldos ou filas forjados enviados pelo cliente.

O cliente continua a animar produção/transporte, mas `Rq.addBankAmount` não credita
as entregas de origem `vaultWorker`: o banco autoritativo já contabiliza essas
moedas. Outras origens de crédito mantêm o comportamento anterior. Sem esta
proteção, a animação poderia somar ao saldo a mesma entrega duas vezes.

Participações existentes usam os campos já presentes, sem reset/schema novo.
Os acumulados começam a mudar após novo tempo decorrido e atualização do estado;
os valores antigos do saldo não são recalculados retroativamente. A versão de
cache é `20261009-tower-events-v4`. Um indicador pode legitimamente chegar a zero
após recolha; não deve ficar fixo em zero enquanto existe produção/transporte.
