# Casino: máquinas, eventos e calendário

Implementação local do protocolo HTML5 v257. A instalação/migração cria tabelas
sem eventos. O **primeiro arranque pelo `start.sh`** prepara automaticamente
`no_cash_and_xp`, máquina **slots**, por **3 dias do relógio do jogo**.

O script `scripts/setup/ensure_opening_casino.php` usa `.run/epoch`: início no
nascimento do servidor, fim exatamente 259200 segundos depois. Corre após a BD
ficar pronta, antes de iniciar o HTTP. A preparação é idempotente e protegida pelo
lock transacional `casino_control`: qualquer evento já existente, incluindo
cancelado/terminado, impede nova criação. Reiniciar não prolonga a janela nem
reativa um evento cancelado. Sem calendário, uma tentativa posterior recupera a
preparação apenas enquanto a janela inicial ainda estiver aberta; depois não cria
um novo casino. Num servidor existente ainda dentro dos primeiros três dias e com
calendário vazio, o próximo arranque aplica a mesma janela original (não três dias
novos). Limpar apenas o calendário não reinicia a idade do servidor.

A disponibilidade individual continua sujeita ao nível mínimo do casino.
Teste: `php scripts/testing/test_opening_casino.php` usa MySQL e rollback, exige
calendário vazio e verifica tema, máquina, duração, idempotência, cancelamento e
fronteiras temporais. Validado em 2026-10-19, sem reiniciar a stack.

## Agendar pelo relógio interno

Na raiz do projeto, para uma instalação existente:

```bash
php scripts/testing/migrate_casino.php
php scripts/testing/casino_event.php catalogo
php scripts/testing/casino_event.php listar
```

Os comandos seguintes alteram persistentemente o agendamento global:

```bash
php scripts/testing/casino_event.php ligar normal 3 slots
php scripts/testing/casino_event.php ligar go_crazy 3 roleta
php scripts/testing/casino_event.php ligar christmas 3 spine --em-dias=5
php scripts/testing/casino_event.php desligar
php scripts/testing/casino_event.php desligar 123
```

`ligar EVENTO DIAS [MÁQUINA] [--em-dias=N]`: 1–365 dias, máquina por defeito
`slots`, início imediato ou adiado 0–365 dias. Não são permitidas janelas
sobrepostas. `desligar` cancela todos os eventos ativos/futuros; com ID cancela
apenas esse evento. `listar` mostra datas no fuso do jogo e estado atual.

Toda a disponibilidade usa `GameTime::now()`, intervalo `[início,fim)`.
Um dia de duração corresponde a 86400 segundos do jogo, incluindo mudanças
de hora. Não há cron, nem tarefas que dependam de contas fazerem login.
Por exemplo, ao ligar por três dias e avançar dois dias, resta um dia; avançar
30 dias fecha esse evento. Avançar até uma janela futura abre essa janela.
Recuar manualmente o relógio pode tornar uma janela novamente ativa; o reset
diário não retrocede a data já registada. O script nunca altera o próprio relógio.
Recarregar o jogo depois de agendar/cancelar; o estado também é atualizado nas
respostas normais. Saltos do relógio ficam sujeitos à próxima sincronização HTTP.

## Máquinas e catálogos

| Comando | Enum do cliente | Máquina |
|---|---|---|
| `slots` | 1 | Slot machine clássica |
| `roleta` | 2 | Lucky wheel / roda da sorte |
| `spine` | 3 | Slot machine com animação Spine |

Máquina e tema de prémios são independentes: qualquer catálogo pode usar
qualquer máquina. A roda usa categorias de recompensas, sem apostas em
números/cores. Os temas também têm imagens e descrições do cliente.

Os 33 catálogos seguintes vêm de
`server-laravel/data/initEnvironment.json`, `extendedConfig.content_mapping`:

```text
normal, item_casino, birthday, quest_for_glory, great_rewards,
no_cash_and_xp, go_crazy, praise_the_sidekicks, only_items, christmas,
newyear, blast_from_the_past, season_optical_changes, carnival,
video_games, vampire, environmental_week_optical_changes, complete_your_collection,
old_gods, empire_of_the_moon, dinosaurs, volcano, mammoth, aliens, rome,
black_friday, video_games_week, flower_power, xmas_outro, lightning,
dark_ages, bear, witchcraft
```

O comando `catalogo` é a referência dos nomes exatos e quantidades; não necessita
de BD. O catálogo adicional `optical_changes` é **local**: aparências oficiais
compráveis e não predefinidas, com os itens de `normal`. A tabela
`constants_json.data` fornece identificadores, tipos, aparências, custos e limites.
Listas capturadas não comprovam fórmulas de probabilidades do servidor oficial.

## Oferta de fichas na loja

A compra local de fichas é anunciada apenas durante um evento ativo identificado
por `Casino::current()`, usando `GameTime` e a janela `[início,fim)`.
O backend recusa novas compras fora dessa janela, incluindo cartões ainda
abertos no cliente. Consultar um recibo ou repetir um pedido já entregue não
volta a dar fichas. As fichas já adquiridas não são apagadas ao fechar o casino.

## Giros e prémios

- Nível mínimo 12, teaser nível 5; **giros ilimitados** em todos os eventos.
  Regra local: `Casino::MAX_DAILY_SPINS=0`, enviado também ao cliente como
  `casino_max_daily_spins`. Os dados oficiais continuam a indicar 30; não foram
  alterados. A contagem diária continua visível e é reposta na mudança de data.
  Os lotes de cinco/dez não desaparecem por atingir 30 ou 99999 giros. Ainda
  podem ser ocultados pelo cliente quando há uma quantidade insuficiente de
  giros gratuitos/jetons para pagar um lote pela fonte prioritária.
- Lotes de 1, 5 ou 10; cada lote tem um prémio por giro.
- Uma única fonte paga o lote: recurso 2 (um giro grátis por unidade), depois
  recurso 4 (dez jetons por giro), depois donuts (um por giro).
  São custos das constantes. Não se misturam fontes num lote.
- O calendário diário usa `BonusEvents::today()`/`Live::gameTimezone()` e GameTime.
  Não são atribuídos giros grátis automaticamente pelo reset.
- Símbolos suportados: moedas, equipamento, energético, pet, atributos, XP,
  energia de missões, unidades de treino, cola, pedra, aparência e melhoria.
- Energia, treino, cola, pedra e energético são entregues como cupões pessoais;
  equipamento/melhorias vão para a mochila; pets entram na coleção;
  aparências são persistidas e expostas em `current_optical_changes.unlocked_options`.
- Giros debitam recursos, geram previews e guardam o resultado numa transação.
  Resgate bloqueia a personagem, valida os itens e entrega todos os prémios
  atomicamente. Mochila cheia preserva o resultado inteiro para nova tentativa.
  Os previews não podem ser movidos/utilizados antes de resgatar.
- `discard_item=true` descarta o lote inteiro, incluindo prémios numéricos,
  e elimina os previews. Não devolve o custo do giro.
- Um só resultado pendente por personagem; repetição do resgate é rejeitada.
  Resultados pagos sobrevivem ao fim/cancelamento e a mudanças de evento.
  Os resultados do backend anterior, sem snapshot de evento, continuam resgatáveis.
- `complete_your_collection` exclui itens já guardados/possuídos;
  aparências não se repetem, incluindo dentro de um lote. Pool esgotado entrega
  atributos. Pets podem repetir. A seleção de aparências para equipar pertence
  ao sistema de aparências, cujo handler continua separado desta entrega de prémios.
- Sala/histórico persistem no servidor, atualizados por polling HTTP. Últimas
  30 mensagens dos últimos três dias do jogo; o cliente define mensagens para
  moedas/itens/pets/energéticos/atributos/XP/energia/treino (não para símbolos 9–12).

### Balanceamento local explícito

Pesos base por símbolo 1–12: `25,8,7,4,8,20,10,8,4,4,10,4`.
Categorias sem catálogo são removidas. `only_items`, `item_casino` e
`complete_your_collection` usam apenas equipamento; `no_cash_and_xp` usa apenas equipamento, pets, energéticos, habilidades, energia e treino; `praise_the_sidekicks` dá peso 60 aos pets; `go_crazy` dá peso 40
aos itens; catálogos com aparências dão-lhes peso 40. A configuração capturada
define os itens/pets/aparências, não estes pesos.

Qualidade visual 1/2/3 com pesos locais 75/20/5. Bónus de conjunto tipo 6,
confirmado como sorte no casino, acrescenta `round(sorte × 100)` aos pesos
2 e 3. A fórmula do efeito na probabilidade é local. O melhor resultado do
lote decide os símbolos/qualidade da animação; todos os prémios são entregues.

Variantes base confirmadas pelo utilizador, presentes tanto no sorteio como
na lista de prémios possíveis dos temas que incluem essas categorias:

| Categoria | Todas as variantes |
|---|---|
| Energético de missões 50% | 3 dias; 14 dias |
| Energético de habilidades/atributos 50% | 3 dias; 14 dias |
| Energético de trabalho 50% | 3 dias; 14 dias |
| Energia de missões | 10; 60 |
| Unidades de treino | 4; 40 |

São seis variantes de energéticos e duas de cada recurso; todas podem sair.
O valor do prémio é determinado pela qualidade do respetivo giro:

| Resultado dos slots | Qualidade | Energético 50% | Energia | Unidades de treino |
|---|---|---|---|---|
| Três símbolos diferentes | 1 | 3 dias | 10 | 4 |
| Dois símbolos iguais | 2 | 3 dias | 10 | 4 |
| Três símbolos iguais | 3 | 14 dias | 60 | 40 |

Um prémio é escolhido por giro e não é duplicado em `great_rewards`.
Qualidade 3 (três símbolos iguais) garante a variante máxima: energético
por 14 dias, energia 60 ou treino 40. Qualidades 1/2 (sem três iguais) dão
energético por 3 dias, energia 10 ou treino 4. O tipo do energético é sorteado
uniformemente entre missões/habilidades/trabalho (regra local, probabilidades
oficiais desconhecidas). Nos multigiros, a animação representa o melhor
resultado; cada prémio do lote conserva a sua própria qualidade/quantidade.
Por exemplo, um lote pode incluir um energético de 14 dias e outro de 3 dias:
os três símbolos da animação representam o melhor giro, não a qualidade de
todos os prémios. Na roda da sorte, que não mostra três rolos, a qualidade do
resultado usa a mesma regra de seleção do valor. Ver BUG-000146.
Energéticos usam `booster_quest3`, `booster_stats3` e
`booster_work3`; o resgate cria o cupão pessoal do tipo correspondente,
com 50% e a duração fixada no resultado. Resultados antigos já pagos mantêm
o prémio guardado, incluindo energéticos antigos de 25%.

As regras de disponibilidade por tema mantêm-se: atualmente `item_casino`,
`only_items` e `complete_your_collection` só sorteiam equipamento, sem estas
categorias base. As restantes variantes incluem os dez prémios acima, exceto `no_cash_and_xp`, cujo catálogo específico está descrito abaixo.

Fora da exceção `no_cash_and_xp`, para os outros prémios, nível `L`, qualidade `Q`, fator `F=Q`
(dobrado em `great_rewards`): moedas `100×L×F`, XP `200×L×F`, atributos `3×F`,
cola/pedra `max(1,floor(L/2))×F`.
Equipamento épico usa gerador local da ferraria com offset +5 e fica utilizável
ao nível atual. Recebe `ts_availability_start=15` (borda dourada warforged,
sem alterar a raridade) e, nos tipos 1–7, `ts_availability_end=1` (espaço de
modificação livre). Mísseis tipo 8 recebem borda mas não suportam modificação;
gemas tipo 12 não são destinos e não recebem borda warforged nem espaço.
Pets recebem `item_improvement_id=1`, também representado no preview tipo 9
por `ts_availability_end=1`. Aplica-se aos novos giros e aos resultados ainda
pendentes no resgate; não altera prémios já resgatados.
Pets épicos usam bases locais 60/100/40/23 e recebem as habilidades fixas
definidas para o seu identificador através de `Sidekick::create()` (BUG-000153); melhorias épicas
têm orçamento `max(4,ceil(L×0,8))`, distribuído pelos quatro atributos.
XP partilha 2% com o pet ativo e passa pelo level-up habitual.

Os símbolos 13–18 encontrados no bundle (objetos de eventos/histórias/chamadas/
guilda, caixas surpresa e pontos de temporada) não têm pools confirmados nestes
catálogos e não são sorteados artificialmente. Não se implementaram novas
atividades de eventos exteriores ao casino nem compras com dinheiro real.

## Exceção oficial capturada: `no_cash_and_xp`

O catálogo deste tema segue `getCasinoPossibleRewards` de `casino_ofc.har`,
capturado em 2026-10-04. A fixture sem dados de conta está em
`server-laravel/data/casino_no_cash_and_xp_rewards.json`; só os nomes das duas
secções foram traduzidos. Preserva os 25 prémios, tipos genéricos, qualidades,
níveis mínimos, texturas e `preventDuplicates=false` da captura.

| Prémio | Variante inferior (qualidades de giro 1/2) | Variante máxima (qualidade de giro 3) |
|---|---|---|
| Energia | 20 | 100 |
| Treinos | 4 | 40 |
| Habilidades | 2 | 20 |
| Energético de missões, habilidades, trabalho, esconderijo ou liga | 3 dias | 14 dias |

Energéticos: `booster_quest3`, `booster_stats3`, `booster_work3`,
`booster_hideout2` e `booster_league2`. Usam os valores do catálogo existente:
50% nos quatro primeiros tipos que têm esse valor; liga tem valor interno 120.
Energia, treino e energéticos são entregues em cupões pessoais. O energético
do esconderijo aplica o bónus real à produção de cola/pedra; ver
[energéticos do esconderijo](../hideout/energeticos.md). Habilidades
são creditadas diretamente. Para instalações existentes, executar também
`php scripts/testing/migrate_user_vouchers.php`, que acrescenta os campos de
ativação do energético do esconderijo quando faltarem.

Equipamento possível: `suit_casino_luck`, `mask_casino_luck`,
`cape_casino_luck`, `boots_casino_luck`, `belt_casino_luck`,
`weapon_slotmachine_set1` (qualidade épica) e `missiles_slotmachine_set1`
(qualidade comum neste tema, conforme a lista capturada). Há ainda
`random_epic` e `random_sidekick`, ambos apresentados como tipo genérico 11.
O acompanhante só entra no sorteio a partir do nível 60; continua listado com
`minLevel=60` nos níveis inferiores, como no HAR.

O épico aleatório usa o gerador local de equipamento. O acompanhante aleatório
usa os templates de tipo 9 não exclusivos premium. O HAR não identifica o
pool interno oficial destes prémios aleatórios: essa seleção continua local.
Não saem moedas, XP, cola, pedra, aparências ou melhorias neste tema.

Os pesos e a escolha uniforme do tipo de energético continuam a ser regras
locais: uma captura de um giro não prova probabilidades oficiais. O HAR
confirma energia 20 num giro com três símbolos diferentes e qualidade 1;
a aplicação da variante inferior à qualidade 2 mantém a regra local anterior.
O catálogo apresenta as variantes máximas como qualidade 3.

Resultados pagos antes desta alteração conservam os prémios guardados. O
calendário, as outras variantes e os giros ilimitados mantêm as suas regras.

Validação: teste offline do catálogo; integração MySQL com rollback para
resgate de 2/20 habilidades, dez cupões de energéticos e quatro de energia/treino,
incluindo utilização dos cinco tipos de energético. O teste geral percorre
as três máquinas e as duas secções deste tema. Teste Node do cliente disponível
na secção seguinte. Confirmação visual no browser pendente.

## Protocolo, persistência e cliente

`spinCasinoMachine {multi_spin}` e `getCasinoReward {}` devolvem
`casino_slot1/2/3`, `casino_reward_type`, `casino_reward_quality`, `rewards`
(array de strings JSON), com os previews na coleção `items` antes de desenhar.
`applyCasinoReward {discard_item}` entrega/descarta o resultado.
Pontos de habilidade usam `statPoints` no JSON de cada prémio, conforme o parser
real do cliente. Tanto o giro como `getCasinoReward` normalizam resultados antigos
com `stat_points` ao apresentar o diálogo; o prémio pago guardado e o saldo não
são recalculados. O resgate aceita ambos os campos, incluindo no histórico.
Ver BUG-000151.
`getCasinoPossibleRewards` devolve `sections`; os objetos genéricos preservam
`genericRewardType/Amount/Data`. Sala: `addUserToCasinoRoom`,
`removeUserFromCasinoRoom`, `getLastCasinoWins` (`messages`: strings JSON).
`getEventHubEntries` inclui o casino durante a janela ativa.

Boot e `Live::accountState` usam o calendário atual. `initGame` sem utilizador
serve constantes estáticas fechadas (sem BD); com utilizador sincroniza o evento.
Identificador
`casino_ID_TEMA` (normal: `casino_ID_default`); um resultado pendente fixa a
máquina/tema originais até ao resgate. Extensões
`casino_event_start_timestamp` e `casino_event_end_timestamp` permitem ao cliente
comparar timestamps diretamente com o seu relógio sincronizado, evitando datas
históricas/fusos do bundle. O bundle também troca máquinas quando muda o evento,
invalida o catálogo em cache e mantém polling do histórico sem socket específico.

Tabelas: `casino_control` (lock global), `casino_events` (janelas),
`casino_collections` (colecionáveis), `casino_wins` e `casino_rooms`.
Resultado pendente em `character.casino_state`. Schema inicial em `08_casino.sql`;
migração idempotente não é executada em transações de jogo.

## Validação

```bash
php scripts/testing/test_casino.php
php scripts/testing/test_casino.php --db
php scripts/testing/test_casino.php --db "Nome do herói"
node scripts/testing/test_casino_client.js
node scripts/testing/test_casino_rewards_client.js
```

Offline valida todos os identificadores/pools e sorteio ponderado. MySQL com
rollback verifica janelas e fronteiras, sobreposição, nível/saldo e ausência de limite,
recursos/lotes, 12 classes de prémios, previews, capacidade, duplicados, fecho,
cancelamento, histórico, 34 catálogos × três máquinas, boot e estado normal.
Inclui lotes de cinco/dez após 99999 giros, borda +15, espaço livre no preview
e no resgate, e aplicação real de modificações ao equipamento compatível e ao pet.
Verifica todas as variantes base nos catálogos, entrega dos dez cupões e
utilização real: 70 energias, 44 unidades e os três energéticos de 50% por
3+14 dias. Verifica em todos os temas as variantes máximas nos três símbolos
iguais e inferiores nas qualidades 1/2, incluindo os resultados reais dos giros.
Nenhuma duração/quantidade é multiplicada pelo evento.
O relógio é simulado apenas em memória; não cria eventos persistentes nem modifica
os ficheiros de tempo. Node executa funções reais do bundle com gráficos
simulados: datas, pendentes, três máquinas, símbolos, cache, polling e seletor
de cinco/dez giros acima de 99999. Os eventos reais existentes são preservados.
Confirmação visual das animações no browser fica pendente.

O teste `test_casino_rewards_client.js` combina a normalização PHP com o parser
e a agregação de prémios reais do diálogo do bundle: um lote de dez com dois
prémios de habilidades mostrava oito entradas antes da correção e mostra dez
depois; valida também a preservação e apresentação do energético de 14 dias.
Não substitui confirmação visual das animações no browser.
