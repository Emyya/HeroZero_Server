# Inventário e cofre do herói

**Estado:** correção implementada e validada em MySQL com rollback.
Confirmação visual no cliente pendente.

## 1. Organização e persistência

Os objetos pertencem ao herói através de `items.character_id`. A posição é
guardada como referência ao ID do objeto nas tabelas `inventory` e
`bank_inventory`. Transferir um objeto entre mochila e cofre altera essas
referências; não apaga nem recria o registo em `items`.

| Área | Campos na BD | Posições do protocolo |
|---|---|---|
| Equipamento | `mask_item_id`, `cape_item_id`, `suit_item_id`, `belt_item_id`, `boots_item_id`, `weapon_item_id`, `gadget_item_id`, `missiles_item_id` em `inventory` | 1..8 |
| Mochila | `bag_item1_id` até `bag_item18_id` em `inventory` | 9..26 |
| Cofre | `bank_item1_id` até `bank_item450_id` em `bank_inventory` | Página e posição |

O valor zero representa uma posição vazia. O cofre disponibiliza inicialmente
uma página de 18 posições. Se ainda não existir linha na BD, a leitura devolve
um objeto vazio com `id=0`; a primeira operação cria a linha persistente.

## 2. Páginas e posições do cliente

O cliente HTML5 257 identifica o destino com `bank_index` (página, a partir de
zero) e `target_slot_index` (posição 0..17). A coluna correspondente é
`bank_item{18 * bank_index + target_slot_index + 1}_id`.
`max_bank_index` é o índice da última página desbloqueada: zero disponibiliza
18 posições. O cliente consulta até 450 posições (25 páginas).

## 3. Estado enviado ao cliente

`bank_inventory` acompanha o estado normal e o `autoLoginUser`, com os IDs dos
itens; a coleção `items` contém os objetos referenciados. Contas sem registo
recebem um cofre vazio, sem herdar o template de outra conta.

## 4. Operações

| Ação | Parâmetros principais | Efeito |
|---|---|---|
| `addInventoryItemToBank` | `item_id`, `bank_index`, `target_slot_index` | Transfere da mochila para o cofre |
| `moveBankInventoryItem` | `item_id`, `bank_index`, `target_slot_index` | Move entre posições do cofre |
| `removeBankItemToInventory` | `item_id`, `target_slot` | Transfere do cofre para a mochila |
| `lockBankItem` / `unlockBankItem` | `item_id` | Guarda/remove proteção contra venda |
| `sellBankInventoryItem` | `item_id` | Vende um objeto desbloqueado |
| `sellAllBankInventoryItems` | `bank_index` | Vende objetos desbloqueados da página indicada |
| `upgradeBankInventory` | Sem parâmetros específicos | Desbloqueia mais uma página |

Depósito e movimentos respeitam o destino e trocam o item que o ocupa com a
origem. A retirada usa `target_slot` da mochila (9..26). Operações inválidas
não retiram itens. Transferências, vendas, bloqueios e expansões usam uma
transação ou savepoint e bloqueiam a linha do personagem antes das coleções.
Vendas em lote restringem-se à página pedida e preservam itens bloqueados.
`locked_item_ids` guarda um array JSON de IDs.

As transferências validam que o objeto pertence ao herói e está na origem
indicada. Páginas bloqueadas e posições fora do intervalo são rejeitadas.
Na retirada, `target_slot=0` escolhe a primeira posição vazia da mochila;
se não existir, devolve `errInventoryNoEmptySlot`.

A venda individual de um objeto bloqueado devolve `errSellItemItemIsLocked`.
A venda apaga o objeto e a modificação associada através de
`ItemImprovement::deleteAttached()`, e credita o preço calculado por
`ItemImprovement::sellPrice()` em `character.game_currency`.

## 5. Expansões e custos

Expansões desbloqueiam uma página: 25 donuts na primeira e 50 nas seguintes,
conforme `bank_upgradeN_premium_amount` das constantes disponíveis. Limite
local: 25 páginas, correspondente às 450 posições consultadas pelo cliente.

## 6. Migração de instalações existentes

Executar explicitamente `php scripts/testing/migrate_bank_inventory.php`.
Acrescenta as posições até 450, valores vazios por omissão e os bloqueios.
Guarda as linhas antigas em `bank_inventory_legacy_backup` e marca a migração
em `herozero_bank_migration`; repetir não volta a converter os índices.

O handler antigo guardava capacidade em posições (9, 18, 27...), e não índices
de páginas. A conversão arredonda essa capacidade para páginas completas de
18, preservando acesso a todas as posições ocupadas. Não cobra expansões
retroativamente nem recria/desloca itens. O initdb já contém o novo schema.
DDL executa fora das transações de jogo.

## 7. Validação

`php scripts/testing/test_bank_inventory.php [nome-do-herói]` verifica operações,
bloqueios, custos, saldo insuficiente, resposta normal e autoLoginUser na BD.
O herói precisa de um item no cofre. Todos os efeitos do teste, incluindo a
rotação da sessão no login, são revertidos. A confirmação visual exige abrir
o cliente e verificar depósito, retirada e novo login.

## 8. Ficheiros responsáveis e referências

| Ficheiro | Responsabilidade |
|---|---|
| `server-laravel/app/HeroZero/BankInventory.php` | Leitura do cofre e operações transacionais |
| `server-laravel/actions/inventoryOps.php` | Encaminhamento das ações de cofre; outras operações de inventário |
| `server-laravel/app/HeroZero/Live.php` | Cofre no estado normal de conta |
| `server-laravel/actions/autoLoginUser.php` | Cofre persistido no login automático |
| `server-laravel/docker/initdb/01_schema.sql` | Schema para instalações novas |
| `scripts/testing/migrate_bank_inventory.php` | Migração explícita de instalações existentes |
| `scripts/testing/test_bank_inventory.php` | Integração MySQL com rollback |

O contrato de páginas e posições foi confirmado em
`server-laravel/public/js/HeroZero.min.js`, sobretudo em
`Uy.prototype.getItemByIndex`, `Uy.prototype.handleMessages` e
`Y.prototype.refreshBankInventory`. Os custos foram consultados em
`assets/data/constants_json.data` (JSON comprimido com zlib).

O sintoma original foi investigado no `lock_inventario.har` e está registado
como [BUG-000132](../bugs_encontrados.md#bug-000132-2026-10-04--itens-desapareciam-visualmente-ao-guardar-no-cofre).
Para compras e colocação de itens nas posições do inventário, consultar
[lojas](../shop_items/lojas.md); para gemas associadas a equipamento, consultar
[modificações](../modificações/implementacao.md).

O equipamento de arremesso e o cinto de reservas são descritos em
[mísseis e armas de arremesso](misseis.md).
