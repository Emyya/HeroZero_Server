# Modificações de equipamento

Implementado para máscara, capa, fato, cinto, botas, arma e gadget (tipos 1–7).
A aplicação em acompanhantes está descrita em `docs/sidekicks/modificacoes.md`.

Os equipamentos recebidos nos [casinos](../casino/README.md) já têm espaço
gratuito (`ts_availability_end=1`) e borda dourada warforged
(`ts_availability_start=15`), mantendo a raridade. O espaço é mostrado no
preview e preservado na entrega, sem gastar o consumível tipo 13.
Aplica-se a novos giros e resultados pendentes de resgate; não atualiza itens
já resgatados. Mísseis tipo 8 podem ter borda, mas não aceitam modificação;
gemas tipo 12 não são destinos e não recebem espaço/borda warforged.
Regressão com rollback: `php scripts/testing/test_casino.php --db`.

Os novos equipamentos da Pirâmide da Sorte e da Caça às Toupeiras também recebem borda dourada e espaço gratuito, com raridade épica e atributos calculados pela fórmula da loja. Ver [Pirâmide](../eventos/event_draws_implementacao.md) e [Toupeiras](../eventos/event_treasures_implementacao.md).

Os equipamentos novos das caixas solo HeroCon (grande e pequena) também têm
borda dourada e espaço gratuito. Usam a fórmula de atributos da loja épica,
incluindo o bónus do esconderijo. Validação: `test_herocon.php --mysql`, com rollback.

As novas [compras de pacotes locais](../compras/overview.md) entregam equipamento
épico com borda dourada e espaço livre; pets recebem `item_improvement_id=1`.
Ambos usam os geradores de atributos do brechó.

O protocolo usa `ts_availability_end`: 1 = espaço vazio, -ID = melhoria aplicada.
A melhoria fica persistida na tabela `items`, sai da mochila e da coleção `items`
e é enviada em `item_improvements`. Os seus atributos são somados no cálculo do
equipamento; os atributos originais do equipamento não são alterados.

`useInventoryItem` recebe `item_id`, `target_item_id` e `replace_existing`.
O consumível tipo 13 abre o espaço; tipo 12 aplica a melhoria. Substituir destrói
a melhoria anterior. A operação valida propriedade, presença na mochila, nível,
compatibilidade e confirmação de substituição, numa transação com bloqueios.
O destino pode estar equipado, na mochila ou no banco. Vender o equipamento
também elimina a melhoria associada.

Não são necessárias colunas novas: os campos existentes são assinados e já
suportam o contrato. Em bases existentes, executar a reparação idempotente
`scripts/testing/item_improvements.sql` para corrigir os tipos dos consumíveis
antigos. A produção nova já cria os tipos corretos e coloca-os na mochila;
uma mochila cheia impede a recolha e mantém a produção disponível.

Teste de integração com rollback: `php scripts/testing/test_item_improvements.php`.

Para obter o consumível que abre um espaço pela interface do jogo, sem alterar
diretamente o equipamento:

```bash
php scripts/testing/give_improvement_slot_item.php "skel"
```

Recarregar o jogo e usar o consumível na mochila. O cliente lista os equipamentos
sem espaço (máscara, capa, fato, cinto, botas, arma e gadget). Confirmar consome o
item e abre um espaço. Só depois se aplica a gema/modificação compatível.
Para acompanhantes, acrescentar `sidekick` ao comando.

## Oficina: contrato do cliente

O tipo escolhido na oficina usa `additional_value_2.improvement_slot`.
O campo antigo `slot` é convertido ao enviar respostas e ao iniciar/recolher
produção, sem migração da BD. Novos sorteios guardam a chave correta.
As três opções de `improvement_rarity` são um objeto com chaves 0–2 e incluem
`id`, `identifier`, `quality` e os quatro atributos. A duração usa a constante
oficial `item_improvement_duration`. A recolha copia os atributos da opção
escolhida, em vez de criar silenciosamente uma melhoria sem atributos quando
essa opção falta. Sorteios gratuitos repetidos devolvem o erro previsto pelo
cliente; o parâmetro textual `free=false` é interpretado corretamente.

Regressão sem BD, incluindo reprodução do HAR:
`php scripts/testing/test_workshop_contract.php debug_oficina.har`.

Ao concluir construção, evolução ou colocação, a oficina e as outras salas de
produção manual ficam disponíveis (`status=1`), sem resultado para recolher.
Só as salas automáticas retomam produção imediatamente. Estados antigos com
produção manual (`status=6`) e temporizador zero são recuperados ao ler as salas,
preservando o nível e as opções da oficina. Produções reais têm um temporizador
positivo e não são alteradas, mesmo quando já terminaram.

Regressão: `php scripts/testing/test_workshop_upgrade.php oficina_evol.har`.

## Equipamento da Academia Heroica

Os novos prémios de equipamento dos tipos 1–7 são lendários e dourados, com
`ts_availability_end=1`: espaço de modificação gratuito, inicialmente vazio.
O espaço não significa que uma modificação já foi aplicada. Mísseis não aceitam
modificações; pets usam o sistema próprio. Os prémios de modificação do evento
são itens separados, com slot/raridade validados no resgate.
Ver [academia](../tower_events_implementacao.md) para entrega e fórmula.
