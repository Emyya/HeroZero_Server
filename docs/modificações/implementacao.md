# Modificações — objetos do herói e pets/sidekicks

**Estado: implementado**, com as limitações de produção descritas na secção 8.
Esta documentação descreve o código atual, o contrato do cliente HTML5 e a
persistência na BD. «Gema», «modificação» e «melhoria» referem-se ao mesmo objeto;
no código, o sistema chama-se `ItemImprovement`.

## 1. Mecânica e tipos de objeto

Cada equipamento ou sidekick suporta um espaço de modificação. O espaço pode
estar ausente, vazio ou ocupado. Aplicar uma melhoria acrescenta os seus quatro
atributos: resistência (`stamina`), força (`strength`), crítico
(`critical_rating`) e esquiva (`dodge_rating`). Não acrescenta dano de arma.

Abrir o espaço e aplicar a modificação são operações distintas:

| `items.type` | Objeto | Comportamento |
|---|---|---|
| 12 | Modificação/gema | Aplica atributos a um destino compatível que já tenha espaço |
| 13 | `special_add_improvement_slot` | Abre um espaço num equipamento sem espaço |
| 14 | `special_add_sidekick_improvement_slot` | Abre um espaço num sidekick sem espaço |

Os consumíveis 13/14 são gastos ao abrir o espaço. A gema aplicada sai da mochila,
mas mantém o registo na BD para guardar os seus atributos. Substituir exige
confirmação e destrói a gema anterior: o espaço não acumula várias melhorias.

**Exemplo:** a «corda de escalar elástica» observada no HAR era
`improvement_belt_common`, tipo 12. É uma melhoria para cinto, não o consumível
que abre um espaço. Se o cinto não tiver espaço, não aparece no seletor dessa
gema. A escolha dos destinos é feita pelo cliente antes de enviar a aplicação.

### Compatibilidade e raridades

O catálogo é extraído de `constants_json.data`, com cache persistente através de
`AssetCache`. O `type` do catálogo indica o destino; é diferente de `items.type`,
que é sempre 12 para as gemas.

| Tipo do destino no catálogo | Destino | Identificador base |
|---|---|---|
| 1 | Máscara | `improvement_mask_*` |
| 2 | Capa | `improvement_cape_*` |
| 3 | Fato | `improvement_suit_*` |
| 4 | Cinto | `improvement_belt_*` |
| 5 | Botas | `improvement_boots_*` |
| 6 | Arma | `improvement_weapon_*` |
| 7 | Gadget | `improvement_gadget_*` |
| 9 | Sidekick | `improvement_sidekick_*` |

Mísseis (tipo 8) não são destinos suportados. O catálogo contém 32 definições:
oito destinos × quatro raridades.

| `quality` | Raridade | Sufixo |
|---|---|---|
| 1 | Comum | `common` |
| 2 | Rara | `rare` |
| 3 | Épica | `epic` |
| 4 | Lendária | `legendary` |

Criação e aplicação suportam as quatro raridades. A oficina atual oferece três
opções de produção de equipamento: comum, rara e épica.

As recompensas de season do tipo 10 também criam gemas tipo 12 na mochila,
através de `Season::giveImprovementItem()`. Usam o catálogo e o criador comuns,
mas a geração de atributos tem um orçamento local provisório, descrito em
`docs/seasons/atual.md`. Não usam os valores fixos dos scripts de preparação.
O resgate é transacional: mochila cheia não marca a recompensa como recebida.

## 2. Base de dados e associação

Não existe uma tabela separada para gemas aplicadas: todas ficam em `items`, com
`character_id`, `identifier`, `quality` e os quatro campos `stat_*`.

O cliente usa uma associação com sinal negativo:

| Estado | Equipamento: `items.ts_availability_end` | Sidekick: `sidekicks.item_improvement_id` |
|---|---|---|
| Sem espaço, no fluxo normal | 0 | 0 |
| Espaço vazio | 1 | 1 |
| Gema aplicada, por exemplo ID 123 | -123 | -123 |

Os prémios de equipamento (tipos 1–7) e pets dos casinos já chegam com espaço
livre (`1`). Equipamento recebe também +15/borda warforged; mísseis e gemas não
são destinos de modificação. Ver [casino](../casino/README.md).
O espaço é enviado no preview (`items.ts_availability_end=1`), mantendo-se no
equipamento entregue ou sendo guardado em `sidekicks.item_improvement_id=1`
ao criar o pet. A regra abrange resultados ainda pendentes, mas não altera
objetos anteriormente resgatados. `test_casino.php --db` verifica a aplicação
real de gemas aos dois destinos com rollback.

No equipamento, `ts_availability_end` é um campo existente reutilizado pelo
contrato oficial. Não foi necessário alterar o schema de `items`.

Nos sidekicks foi acrescentada a coluna assinada:

```sql
item_improvement_id INT NOT NULL DEFAULT 0
```

O schema inicial em `server-laravel/docker/initdb/01_schema.sql` já contém essa
coluna. Para uma BD existente:

```bash
php scripts/testing/migrate_sidekick_improvements.php
```

O script verifica a existência da coluna antes do `ALTER TABLE` e corrige os
tipos dos consumíveis antigos de sidekick. A alteração do schema é feita antes
das transações de teste, porque MySQL faz commit implícito em DDL.

A reparação dos tipos antigos das gemas de equipamento e dos abridores está em
`scripts/testing/item_improvements.sql`. Não altera os atributos dos objetos.
`Character::createSpecialItem()` também determina automaticamente os tipos 13/14
a partir do identificador, para novas criações não voltarem a usar tipo 0.

Antes da migração dos sidekicks, o equipamento continua a funcionar;
`ItemImprovement::hasSidekickSchema()` evita consultas à coluna inexistente.
A aplicação a sidekicks sem essa coluna devolve `errActionNotSupported`.

## 3. Protocolo e respostas

O cliente usa a ação existente `useInventoryItem` para abrir espaços e aplicar
gemas. O handler está em `actions/inventoryOps.php`.

| Parâmetro | Significado |
|---|---|
| `item_id` | ID da gema ou do consumível abridor |
| `target_item_id` | Equipamento de destino |
| `target_sidekick_id` | Sidekick de destino |
| `replace_existing` | Confirmação para substituir uma gema aplicada |

Só um destino deve ser enviado. A aplicação recusa pedidos com os dois destinos.
`replace_existing` é interpretado como booleano, incluindo valores textuais
enviados por formulário.

Exemplo dos campos específicos de uma aplicação a sidekick:

```json
{
  "action": "useInventoryItem",
  "item_id": 123,
  "target_sidekick_id": 9,
  "replace_existing": false
}
```

Os campos comuns de autenticação/sessão continuam a ser exigidos pelo protocolo
do jogo. A resposta mantém o envelope `{data, error}` e o estado da conta.

### Coleções enviadas ao cliente

- Antes da aplicação, a gema está em `items` e num `inventory.bag_itemN_id`.
- Após a aplicação, sai da mochila e da coleção `items`.
- A gema aplicada é enviada em `item_improvements`, com `id`, `identifier`,
  `quality` e os quatro atributos.
- O equipamento ou sidekick referencia-a através do ID negativo.
- `Live::overlayAccount()` e `Live::accountState()` incluem as melhorias reais.
- Na consulta de outro herói, `getCharacter.php` envia
  `requested_character_inventory_improvement_items`, além do equipamento e dos
  dados do acompanhante ativo.

O cliente resolve a referência negativa nessa coleção para mostrar a gema e os
seus atributos. Não se deve apagar o registo da gema quando ela é aplicada.

## 4. Aplicação, validação e transações

`ItemImprovement::useItem()` centraliza o comportamento dos dois destinos.
Bloqueia os registos do personagem, inventário, banco, consumível e destino com
`FOR UPDATE`. Usa transação própria ou um savepoint quando já existe transação.

Valida:

- Propriedade do consumível e do destino pelo mesmo personagem.
- Presença do consumível numa posição da mochila.
- Nível do herói suficiente para usar o consumível.
- Equipamento suportado, equipado, na mochila ou no banco.
- Sidekick possuído com `status=1`, ativo ou não.
- Correspondência entre o tipo de destino e o catálogo da gema.
- Existência de espaço para aplicar; ausência de espaço para usar um abridor.
- Confirmação explícita quando já existe gema aplicada.

Abrir: grava 1 no destino, apaga o abridor e liberta a posição da mochila.
Aplicar: grava `-item_id` no destino e liberta a posição da mochila.
Substituir: faz a nova associação e apaga a gema anterior.
Se uma validação ou escrita falhar, a transação é revertida.

Erros principais: `errInventoryInvalidItem`, `errUseItemItemReqLevel`,
`errActionNotSupported` quando falta o schema de sidekicks. A movimentação de
inventário também impede que uma gema já associada seja movida como item solto.

## 5. Cálculo de atributos e remoção

### Equipamento do herói

`Character::equipBonus()` soma os atributos originais do equipamento e procura a
gema referenciada por `ts_availability_end < 0`. Os quatro atributos da gema
entram no bónus de equipamento, que participa no estado do herói e nos combates.

Os atributos originais guardados do equipamento não são alterados. Desequipar
remove também a contribuição da sua gema; voltar a equipar restaura-a.

Ao vender equipamento, `ItemImprovement::deleteAttached()` elimina a gema
associada. O preço de venda inclui o aumento usado pelo cliente, consoante a
raridade da gema: comum +25%, rara +50%, épica +100%, lendária +150% do preço base.
Estão abrangidas a venda individual, a venda de vários objetos da mochila e as
vendas do banco.

### Pets/sidekicks

`Sidekick::withImprovement()` acrescenta os atributos da gema às bases numa cópia
do registo e recalcula os atributos com `Sidekick::statAtLevel()`:

```text
base_exposta = base_original + atributo_da_gema
atributo_final = jsRound(base_exposta + base_exposta × fator_do_sidekick × (nível - 1))
```

O fator depende da raridade do sidekick, não da raridade da gema. A gema determina
os atributos que entram na base. A função segue a fórmula e o arredondamento do
cliente através das constantes oficiais.

`doFromRow()` expõe essas bases e atributos ao cliente. `activeRow()` fornece os
atributos modificados ao cálculo do contributo do acompanhante ativo ao herói.
Um sidekick não ativo conserva a gema, mas não contribui para os atributos do herói.

As bases originais permanecem intactas na BD. Subir de nível não acumula a gema
uma segunda vez; os valores expostos são recalculados na leitura. Na fusão, o
destino mantém a associação e o cálculo usa as bases originais ao reiniciar o
nível. A gema do sidekick consumido na fusão é eliminada.

Libertar/vender um sidekick elimina a sua gema. O preço inclui separadamente o
valor da melhoria conforme a fórmula de `Sidekick::sellPrice()`, reproduzindo o
ramo respetivo de `Cd.get_sellPrice` no cliente.

## 6. Oficina do esconderijo

A sala `gem_production` faz produção manual. O fluxo é:

1. `refreshHideoutItemImprovementSlot`: sorteia o destino (1–7) ou um abridor (13/14).
2. Para uma gema, `refreshHideoutItemImprovementRarity`: gera três opções.
3. `startHideoutRoomProduction`: inicia a opção escolhida.
4. Depois do temporizador, `collectHideoutRoomActivityResult`: cria o objeto e
   coloca-o na primeira posição livre da mochila.

`hideout_rooms.additional_value_2` é uma string JSON com:

```json
{
  "improvement_slot": 4,
  "ts_slot_refresh": 1793911892,
  "improvement_rarity": {
    "0": {"id": 0, "identifier": "improvement_belt_common", "quality": 1,
          "stat_stamina": 50, "stat_strength": 0,
          "stat_critical_rating": 0, "stat_dodge_rating": 0}
  },
  "ts_rarity_refresh": 1793911900
}
```

O exemplo mostra uma opção; a geração normal inclui as chaves `0`, `1` e `2`.
`Hideout::improvementData()` normaliza os dados, acrescenta identificadores para
as pré-visualizações e garante que as opções são serializadas como objeto JSON.
Converte também a chave antiga `slot` para `improvement_slot`.

`productionCount` é o índice da opção (0–2) quando se produz uma gema; para os
abridores 13/14 deve ser 1. A duração usa `item_improvement_duration` (12 horas
nas constantes consultadas). A recolha copia os atributos da opção escolhida;
não cria silenciosamente uma gema vazia se essa opção não existir.

A recolha usa transação e bloqueios. Se a mochila estiver cheia, devolve
`errInventoryNoEmptySlot` e mantém a produção disponível para recolher depois.
Uma produção manual impede novos sorteios durante essa atividade.
A oficina partilha a transação exterior de `collectHideoutRoomActivityResult`
(`Missiles::transaction`), sem abrir outra transação PDO. Criação do item,
entrega na mochila e conclusão da produção são revertidas em conjunto em caso
de erro; quando já existe transação, o auxiliar usa savepoint.

Regressão MySQL com preparação temporária e rollback:
`php scripts/testing/test_hideout_improvement_collection.php "skel"`.
Verifica abridores 13/14, gema com atributos, item na resposta, rejeição de
recolha duplicada e preservação da produção com mochila cheia.

Os sorteios têm um primeiro uso gratuito diário. Novos pedidos com `free=true`
no mesmo dia devolvem `errRefreshHideoutItemImprovementSlotNotFree` ou
`errRefreshHideoutItemImprovementRarityNotFree`; pedidos pagos usam o custo
`hideout_item_improvement_refresh_cost`. A interpretação textual de `free=false`
foi corrigida para não ser tratada como verdadeiro pelo cast PHP.

### Fórmula da oficina (revista em 2026-10-07)

`ItemImprovement::workshopMaxStats()` reproduz o máximo apresentado por
`Cf.updateBonusInfo` no JS (linha 5695):

```text
bonus_equipa_pct = jsRound(valor_bruto / (20 × max(membros, 5)) × 10000) / 100
maximo = jsRound(nivel_heroi × (1 + bonus_oficina/100 + bonus_equipa_pct/100) / 2 × 1.6)
```

`1.6` é lido de `item_improvement_epic_stat_increase`; `bonus_oficina` vem de
`gem_production.levels.<nivel>.passiv_bonus_amount_1`. A percentagem de equipa
é arredondada antes do cálculo do máximo, como em `Cf.get_guildBonus` (JS 5705).
Exemplo: herói 300, oficina 10 (50%), valor bruto 100 e 10 membros → bónus de
equipa 50% → máximo 480. Sem bónus de equipa, o máximo é 360.

Geração e `getHideoutItemImprovementGuildBonus` partilham agora
`workshopGuildData()`. A fonte local existente é o campo opcional
`guild.gem_production_level`; sem esse campo, sem equipa ou sem registo de equipa,
o valor é zero. O número de membros é contado em `character.guild_id`, tal como
a coleção de membros exposta ao cliente. Não se criou uma fórmula para preencher
o agregado bruto: a regra oficial da sua origem continua por confirmar. O schema
inicial consultado não define essa coluna opcional; nessa instalação o bónus
continua zero. Não se acrescentou schema nem se recalcularam gemas existentes.

`workshopRarities()` preserva a escolha **local**, não comprovada como oficial:
orçamentos de 1/3, 2/3 e total do máximo para comum/rara/épica, distribuídos por
até 1/2/3 atributos. A distribuição conserva exatamente o orçamento arredondado,
incluindo valores pequenos (antes podia criar pontos adicionais ao forçar um
mínimo de 1 em cada atributo). O máximo anunciado pelo cliente não demonstra,
por si só, estas proporções nem o algoritmo de sorteio oficial.

As gemas de temporada continuam a usar `Season::improvementStats()`, declarado
como balanceamento local provisório. Não há evidência para lhes impor a fórmula
da oficina. O cálculo de aplicação/crescimento das gemas dos pets, descrito na
secção 5, já estava conforme e não foi alterado.

Validação: `php scripts/testing/test_improvement_scaling.php` testa os valores
de referência, arredondamento, conservação de orçamento e, com PDO simulado,
a fonte do bónus e a aplicação repetida de uma gema a um pet. Não é integração
MySQL nem confirmação visual no cliente.

### Correções de estados da oficina

- A oficina vazia observada em `debug_oficina.har` resultava da chave `slot`, que
  o cliente não reconhecia. A chave esperada é `improvement_slot`.
- Ao concluir construção, evolução ou colocação, a oficina fica disponível
  (`status=1`), não a produzir (`status=6`). Só salas automáticas retomam produção.
- Os estados antigos de produção manual com `ts_activity_end=0` são recuperados
  ao ler as salas, preservando nível e opções. Produções reais têm temporizador
  positivo, mesmo depois de terminado, e são preservadas.
- O caso de `oficina_evol.har` era uma evolução 8→9 que deixava a oficina no
  estado 6 com temporizador zero, originando um resgate fictício.

## 7. Ficheiros principais

Os caminhos da tabela são relativos à raiz do projeto.

| Ficheiro | Responsabilidade |
|---|---|
| `server-laravel/app/HeroZero/ItemImprovement.php` | Catálogo, schema de sidekicks, aplicação transacional, coleção e venda de equipamento |
| `server-laravel/app/HeroZero/Character.php` | Criação de gemas/abridores, exclusão das gemas aplicadas de `items`, bónus de equipamento |
| `server-laravel/app/HeroZero/Sidekick.php` | Associação no protocolo, atributos escalados e valor da gema na libertação |
| `server-laravel/app/HeroZero/Live.php` | Melhorias nos estados/respostas da conta |
| `server-laravel/app/HeroZero/Hideout.php` | Normalização dos dados da oficina e conclusão/recuperação dos estados das salas |
| `server-laravel/actions/inventoryOps.php` | Abertura/aplicação via `useInventoryItem`, movimentação e vendas individuais/banco |
| `server-laravel/actions/sellInventoryItems.php` | Venda de vários objetos com preço e eliminação das gemas |
| `server-laravel/actions/sidekicks.php` | Limpeza das gemas na libertação e fusão |
| `server-laravel/actions/getCharacter.php` | Melhorias e atributos do acompanhante na consulta de outro herói |
| `server-laravel/actions/hideoutExtra.php` | Sorteios e recolha da produção |
| `server-laravel/actions/startHideoutRoomProduction.php` | Validação da opção, duração e início da produção |
| `server-laravel/docker/initdb/01_schema.sql` | Coluna de associação nos sidekicks em novas bases |

## 8. Limitações atuais

- A oficina sorteia destinos 1–7 e abridores 13/14. Não produz diretamente gemas
  de sidekick (tipo de destino 9) nem gemas lendárias, apesar de criação/aplicação
  as suportarem. Os scripts permitem testar essas variantes.
- O máximo da oficina segue a fórmula do cliente, incluindo a conversão do
  bónus bruto de equipa. A origem desse agregado e o sorteio/distribuição oficial
  dos atributos continuam por confirmar; ver a fórmula na secção 6.
- Sidekicks de destino têm de existir na tabela `sidekicks` com `status=1`.
  A abertura/aplicação sobre um objeto de sidekick ainda não ativado na mochila
  (um item tipo 9) não está implementada neste fluxo.
- As gemas antigas já criadas com atributos zero não são recalculadas pelas
  correções da oficina. Podem ser alteradas com o script indicado abaixo.

## 9. Scripts de preparação e testes

Executar os comandos na raiz do projeto, com o MySQL disponível. Nos exemplos,
`skel` deve ser substituído pelo nome exato do herói pretendido.

### Preparação persistente para testar pela interface

```bash
# Consumível para abrir espaço pelo jogo; não modifica diretamente o destino.
php scripts/testing/give_improvement_slot_item.php "skel"
php scripts/testing/give_improvement_slot_item.php "skel" sidekick

# Abre espaço num equipamento equipado elegível e dá uma gema épica compatível.
php scripts/testing/prepare_item_improvement.php "skel"
# Opcional: escolher o ID do equipamento equipado.
php scripts/testing/prepare_item_improvement.php "skel" 11607253

# Abre espaço num sidekick elegível e dá uma gema lendária compatível.
php scripts/testing/prepare_sidekick_improvement.php "skel"
# ID do sidekick (0=automático), seguido de raridade 1–4.
php scripts/testing/prepare_sidekick_improvement.php "skel" 9 3

# Alterar uma gema existente: valor único para os quatro atributos.
php scripts/testing/update_item_improvement.php "skel" ID_DA_GEMA 1000
# Ou resistência, força, crítico e esquiva separadamente.
php scripts/testing/update_item_improvement.php "skel" ID_DA_GEMA 1000 2000 500 800

# Encher zénite e supercola até à capacidade atual do esconderijo.
php scripts/testing/fill_hideout_resources.php "skel"
```

Os scripts `prepare_*` dão atualmente **1000 em cada atributo base**. Estes
valores são de teste e não alteram o balanceamento da produção normal da oficina.
Preservam gemas já aplicadas, recusam mochila cheia e só aplicam a nova gema
quando o jogador a usa no jogo. O script de sidekick prefere o ativo elegível e
efetua a migração da coluna quando necessário.

O script de atualização exige o ID da gema, não o ID do equipamento ou do pet.
Funciona com gemas na mochila ou já aplicadas. Nos sidekicks, os valores são
escalados pelo nível. Recarregar o jogo depois de executar os scripts.

### Verificação automática

```bash
# Integração com a BD e rollback das alterações de teste.
php scripts/testing/test_item_improvements.php
php scripts/testing/test_sidekick_improvements.php

# Contrato/estados da oficina sem acesso à BD real.
php scripts/testing/test_workshop_contract.php
php scripts/testing/test_workshop_upgrade.php

# Opcional: reproduzir HARs, se esses ficheiros estiverem disponíveis.
php scripts/testing/test_workshop_contract.php debug_oficina.har
php scripts/testing/test_workshop_upgrade.php oficina_evol.har
```

Os testes de integração pressupõem dados existentes para preparar os casos e,
nos sidekicks, a migração já executada. Os testes da oficina cobrem conversão de
dados antigos, identificadores, serialização das opções, conclusão de atividades
e recuperação de estados inválidos. A conclusão de atividades é testada com PDO
simulado para o contador de objetivos.

Na implementação foram validadas a sintaxe PHP, as constantes e as regressões
dos HARs fornecidos. Os testes de integração contra o MySQL não foram concluídos
na sessão de desenvolvimento, por falta de ligação à BD; não se deve confundir
os testes do contrato com uma validação completa do fluxo contra a BD real.

## 10. Documentação relacionada

- [Modificações de equipamento](../shop_items/modificacoes.md).
- [Modificações dos sidekicks](../sidekicks/modificacoes.md).
- [Oficina e salas especiais: extração do cliente](../hideout/extract/salas_especiais.md).
- [Dados e constantes dos sidekicks](../sidekicks/extract/02_constantes_dados.md).
- [Protocolo geral](../PROTOCOL.md).
