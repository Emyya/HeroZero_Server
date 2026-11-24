# Compras gratuitas no servidor local

Implementação de 2026-10-07. No servidor privado, clicar em comprar entrega o
conteúdo do pacote imediatamente, sem débito de dinheiro, moedas ou donuts.
Os preços do catálogo continuam a identificar o produto enviado pelo cliente;
não são cobrados. A loja normal/brechó mantém os seus custos habituais.

## Problema observado em compras.har

A captura local usa `initConsumableOfferPayment`, com
`offer_identifier=full_speedserver_int2_offer_with_sidekick`, método 1,
preço de referência 207.90, moeda BRL e locale pt_BR. Esse pacote contém:

- `mask_speedserver_int2`, `suit_speedserver_int2`, `belt_speedserver_int2`,
  `boots_speedserver_int2` e `cape_speedserver_int2`;
- acompanhante `sidekick_speedserver_int2`.

O antigo handler só incrementava donuts quando recebia `premium_amount` e
abria uma confirmação local. O pedido da captura não contém esse campo;
a confirmação não entregava nenhuma peça nem o pet. Nos donuts normais do
standalone, o cliente também pode identificar o pacote apenas pelo preço.

## Conteúdo e entrega

`LocalPayments::resolve()` valida o pacote pelo catálogo de
`data/getStandalonePaymentOffers.json`, incluindo identificador, método,
preço de referência, moeda e locale. Ignora quantidades inventadas pelo cliente;
se `premium_amount` for enviado num pacote normal, deve corresponder ao catálogo.
O catálogo inclui também o produto local `hideout_worker`, necessário ao diálogo
de compra do cliente. O pacote precisa de respeitar o nível mínimo e o limite de compras anunciados.
Presentes (`is_gift=true`) não são suportados.

- Donuts: soma o `content.premium_amount` em `user.premium_currency`.
- Fichas: soma `slotmachine_jeton_amount` ao recurso 4 de `unused_resources`.
  A descrição tipo 39 representa as mesmas fichas e não duplica a entrega.
- 72 ofertas únicas de equipamentos/pets: usa `display_info.item_identifiers`.
  Equipamento entra nas posições livres/desbloqueadas da mochila; não substitui
  o equipamento vestido. Épicos usam `Character::createEventRewardItem()`, com
  a fórmula local do brechó, borda dourada e espaço de modificação nos tipos 1–7.
  Mísseis têm cargas do template e não recebem espaço de modificação.
- Pets: criados diretamente em `sidekicks`, adicionados às ordens da conta,
  com bases locais do gerador existente e habilidades fixas do identificador.
  Recebem espaço de modificação gratuito/vazio (`item_improvement_id=1`).
  Não ocupam a mochila nem são equipados automaticamente.
- Conjuntos especiais: entregam os objetos de `detail_page_rewards.items`.
- Passe: desbloqueia `premium_unlocked` da temporada atual. A faixa premium
  exige esta compra; pontos ou desbloqueio individual não contornam a exigência.
  Não resgata automaticamente os prémios.
- Segundo robô: produto especial `hideout_worker`, preço visual local 34,90 R$, uma compra
  por conta. O esconderijo inicial tem um robô. A compra aumenta o máximo para
  dois e acrescenta um robô livre, preservando trabalhos em curso.
- Pás (descrição tipo 45): soma a quantidade à participação ativa da Caça às
  Toupeiras, com `EventTreasures::grantPurchaseShovels()`.

Equipamento (tipos 1–7): nível `L=max(1,nível do herói−rand(0,4))`, orçamento
`ceil((L×1.6+2+L²×0.004)×2.3)+item_flat` do esconderijo, distribuído em
três ou quatro atributos. Pets: bases pela qualidade (épico: nível do herói
×2.5), proporções 28%/43%/18%/11%, arredondamento e variação ±15% por atributo,
entregues ao nível 1. Reutiliza os geradores do brechó, sem nova fórmula.
Ver [geração](../shop_items/geracao_itens.md) e [pets](../sidekicks/scaling.md).
Aplica-se às novas compras; não recalcula objetos já entregues.

As fórmulas de atributos são o balanceamento local já existente; esta captura
não fornece os atributos oficiais das peças compradas.

## Catálogo local e calendário

`getStandalonePaymentOffers` usa agora `paymentOffers.php`. As datas fixas da
captura de julho são retiradas nas ofertas locais. Limites positivos são
aplicados pelo histórico; zero significa sem limite. Ofertas esgotadas deixam
de ser anunciadas. A disponibilidade individual por nível continua no catálogo.

O passe é anunciado para a temporada ativa de `Season::currentCycle()`, com
identificador que inclui o início do ciclo (`GameTime`), imagem correspondente
e texto «Passe da temporada atual». Depois de desbloqueado, deixa de aparecer.
O segundo robô é anunciado nas ofertas especiais da loja e no diálogo «+ robô»
após desbloquear o esconderijo, enquanto o máximo é um robô. O cartão usa
`special_offers/hideout_worker.webp`, mostra 34,90 R$ e mantém o botão de compra.
Este preço é uma referência visual local, não um preço oficial confirmado:
a compra continua sem qualquer cobrança e entrega o robô imediatamente. Esconderijos antigos com dois robôs não são reduzidos automaticamente.
As fichas do casino só aparecem e podem ser compradas quando `Casino::current()`
encontra um evento ativo pelo relógio do jogo, no intervalo `[início,fim)`.
Eventos futuros, terminados ou desativados não disponibilizam a oferta.
As duas caixas históricas `pool_party_season_set_sale_*` foram retiradas do
catálogo local: não representam a temporada atual. Recibos antigos mantêm-se.
O pacote de pás só aparece com evento atual e participação ativa. Os restantes
pacotes capturados ficam disponíveis localmente, respeitando os limites.
Esta disponibilidade é uma regra local, não o calendário do servidor oficial.

## Protocolo, transação e repetição

`initPayment` e `initConsumableOfferPayment` chamam `LocalPayments::purchase()`.
`StoryDungeon::transaction()` bloqueia personagem e conta, usando savepoint
quando existe transação exterior. A entrega inteira e o recibo são atómicos:
sem espaço suficiente, nenhuma peça/pet/saldo é entregue e nenhum recibo fica.
Não há DDL durante as compras.

`local_payments` guarda ID de pagamento, conta, personagem, chave do pedido,
pacote, timestamp do jogo e IDs/quantidades entregues. A chave única
`(user_id, request_id)` permite repetir a mesma compra sem nova entrega.
`getLastPaymentStatus` só confirma um recibo pertencente à conta autenticada;
a consulta nunca entrega recompensas.

O transporte em `public/js/local-payments.js` acrescenta `purchase_request_id`
aos pedidos de compra, preservando a chave enquanto o resultado está incerto.
Após sucesso ou rejeição confirmada, uma nova tentativa recebe outra chave.
Clientes antigos sem chave consideram cada `init*Payment` uma nova compra;
não é possível distinguir duas compras intencionais de um retry sem essa chave.
A chave pendente permanece apenas na memória da página, não sobrevive a reload.

A resposta inicial devolve o estado completo. Na consulta `getLastPaymentStatus`,
`LocalPayments::paymentStatus()` valida o recibo da conta e só inclui `hideout`
para compras de robô e `season_progress`/`season_rewards` para o passe.
Isto evita que o cliente classifique outras compras como um robô pela presença
do esconderijo. As coleções de itens/pets continuam incluídas. A confirmação dos
restantes produtos tem `local_payment_message`, derivada do produto no recibo,
e não do catálogo filtrado (o produto pode já estar esgotado).

A resposta devolve `payment_id` inteiro, `payment_link` relativo,
`last_payment_confirmed=true` e estado real completo (`Live::accountState`).
A página confirma a ligação local através do callback de billing, sem carregar
um iframe. O cliente chama então `getLastPaymentStatus` e atualiza as coleções.
O bundle recebe uma alteração limitada a `Sd.handlePurchaseComplete()`: quando
existe `local_payment_message` na confirmação local, mostra esse texto e mantém
a atualização do estado, tracking, sincronização e renovação do catálogo.
A compra do robô mantém a mensagem e notificação nativas.

O bloqueio existente em `HZ_ENV=production` mantém-se, salvo configuração
explícita `HZ_ALLOW_FAKE_PAYMENTS=1`. Este fluxo local não integra cobranças reais.

## Migração e validação

Schema inicial: `server-laravel/docker/initdb/16_local_payments.sql`.
Instalações existentes:

```bash
php scripts/testing/migrate_local_payments.php
php scripts/testing/test_local_payments.php
php scripts/testing/test_local_payments.php --mysql --hero=skel
node scripts/testing/test_local_payments_bridge.js
node scripts/testing/test_payment_confirmation_client.js
```

A migração idempotente foi aplicada e repetida sem alterar saldos/inventários.
PHP lint e Node syntax passaram. Offline: 72 ofertas, templates, pacote do HAR,
donuts normais e pedidos inválidos. MySQL com rollback: entrega pelo Router,
coleções, cinco peças + pet, 72 ofertas, zero débito, repetição idempotente,
recibos de outras contas, mochila cheia, limites, fichas, passe, pás, login e
nível, acesso premium antes/depois da compra, rejeição de bypass/descartes,
segundo robô inicial/ocupado/retry/login, orçamento/distribuição épica, bases dos pets e espaços no estado/login. Bridge em Node: chave de retry/nova compra, resposta binária/JSON,
ações normais preservadas e callback imediato. Confirmação visual no browser
permanece pendente.
