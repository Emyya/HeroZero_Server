# Mísseis — armas de arremesso pessoais

**Estado:** implementado no backend. Testes offline e integração MySQL com
rollback passaram; confirmação visual no cliente pendente.

## 1. Objetos e posições

Armas normais são `items.type=6`. Armas de arremesso são `items.type=8`,
identificadas no catálogo oficial por `missiles_*`. Não são os melões de
`guild.missiles`, que continuam fora desta implementação.

| Campo de `inventory` | Significado | Índice `m` na batalha |
|---|---|---|
| `missiles_item_id` | Pacote equipado | 1 |
| `missiles1_item_id` | Primeira reserva do cinto | 2 |
| `missiles2_item_id` | Segunda reserva | 3 |
| `missiles3_item_id` | Terceira reserva | 4 |
| `missiles4_item_id` | Quarta reserva | 5 |

No cinto, `-1` significa posição bloqueada, `0` posição vazia e um ID positivo
identifica o pacote guardado. O cliente mostra munições a partir do nível 10 e
o cinto a partir do nível 25, conforme as constantes disponíveis.

Cada pacote novo recebe **100 cargas como regra local**. As constantes HTML5
consultadas não especificam a quantidade por pacote; não tratar este número
como fórmula oficial. Os atributos de resistência, força, crítico, esquiva e
dano de arma ficam a zero. O dano de arremesso é calculado separadamente.

## 2. Aquisição e equipamento

O gerador do brechó pode produzir munição a partir do nível mínimo. A seleção
introduz uma tentativa com probabilidade local de 1/8; mantém os filtros dos
catálogos geral/desportivo, nível, datas, evento e disponibilidade. O preço usa
o balanceamento local existente de moedas, sem inventar uma fórmula oficial.
Prémios da temporada cujo identifier começa por `missiles_` usam o mesmo tipo 8
com cargas, em vez do antigo fallback para arma tipo 6.

| Ação | Contrato |
|---|---|
| `moveInventoryItem` | `target_slot=8` equipa; 9..26 guardam na mochila |
| `moveAmmoBeltItem` | `equip=true`, `target_slot=1..4`: mochila → vaga desbloqueada e vazia do cinto |
| `moveAmmoBeltItem` | `equip=false`, `target_slot=9..26`: cinto → mochila |
| `buyShopItem` | `target_slot=101`: compra para a primeira vaga desbloqueada e vazia do cinto |
| `upgradeAmmoBelt` | Desbloqueia a próxima posição, por ordem |

As operações verificam propriedade, origem, tipo, cargas, nível e capacidade.
Não é permitido equipar munição na posição de arma normal. Uma troca entre o
pacote equipado e uma posição ocupada da mochila exige que o objeto deslocado
seja também munição compatível. Retirar do cinto exige destino vazio.

Desbloqueios custam **10, 15, 20 e 25 donuts**, respetivamente, lidos de
`ammo_belt_slotN_unlock_premium_currency_amount`. Débito e desbloqueio são
atómicos, com rollback se faltar saldo. `useInventoryItem` rejeita munição:
o consumo pertence ao combate, não a esse handler de consumíveis.

## 3. Dano e rondas

O cliente HTML5 define o dano base em `Za.prototype.getMissileDamage`:

```text
Math.round(item_level × item_missile_damage_factor)
```

O fator nas constantes atuais é **3,9**. `Missiles::damage()` reproduz esse
arredondamento e acrescenta os bónus de skills desbloqueadas do pet associados
a `battle_effect=104`. O motor também aplica a redução de arremesso das skills
associadas a `battle_effect=14`, usando chance e percentagem do catálogo.
O bónus opcional da habilidade sazonal apresentado pelo cliente não é calculado
por este helper; não confundir este suporte com todos os efeitos de temporada.

O motor considera primeiro o pacote equipado, seguido das quatro reservas.
Na simulação, cada ataque efetivamente executado utiliza uma carga em memória, mesmo quando o adversário
se esquiva. Ataques adicionais de skills também contam; turnos em que o herói
está atordoado não gastam cargas. Estas regras de consumo e de combinação do
dano são a implementação local do combate, não uma fórmula extraída do JS.

Quando há munição, o dano base do golpe passa a incluir força, dano da arma e
dano do pacote utilizado, antes das variações/críticos/efeitos do motor.
A ronda inclui `m=1..5`; sem munição, não inclui `m`. O cliente usa esse índice
para escolher o identifier de `missiles`/`missiles1`..`missiles4` da aparência.

As aparências e os perfis de batalha são capturados antes do consumo. Não se
renumeram os índices a meio da luta quando um pacote acaba. Após gravar a
batalha de duelo ou liga iniciada pelo jogador, as cargas do atacante são
debitadas, os pacotes esgotados são eliminados e a
primeira reserva restante passa para `missiles_item_id` se este ficar vazio.

## 4. Integração e atomicidade

As cargas só são debitadas quando o jogador inicia um ataque contra outro
jogador: duelo ou liga, apenas do lado atacante. O defensor não perde munição.
Missões de combate, treinos, missões especiais/dungeon de história e batalhas
de guilda (incluindo gangues NPC) não debitam cargas. O dano e as rondas do
motor mantêm o comportamento existente; simular uso não implica débito na BD.
Regra corrigida em BUG-000138.

`Fight` simula em memória e devolve consumo por lado e ID. Os handlers gravam a
batalha; nos ataques PvP iniciados pelo jogador, chamam `Missiles::consume()`
apenas para o atacante, na mesma transação. Este método recusa
execução fora de uma transação. A transação usa savepoint se já existir outra,
e os locks dos participantes conhecidos são adquiridos por ordem de ID.

Retomas de duelo/liga e missões já concluídas devolvem a batalha persistida,
sem novo consumo. Um pedido para iniciar uma quest de treino quando já existe
outra ativa é rejeitado antes de gastar energia ou munição.

O inventário e `items` são relidos nas respostas após o consumo. Login e estado
normal já carregam esses objetos da BD; não exigem alterações no bundle.

## 5. BD existente e reparação

O schema já contém `charges`, `type` e as quatro posições do cinto. Não foi
necessária uma migração de schema para implementar munição.

Alguns prémios antigos tinham identifier de munição, mas tipo 6 e atributos de
arma. O script mostra primeiro os efeitos:

```bash
php scripts/testing/repair_missiles.php "nome-do-herói"
php scripts/testing/repair_missiles.php "nome-do-herói" --apply
```

Sem nome, considera todas as contas. Conserva IDs, preços e nível do objeto;
converte apenas identifiers presentes no catálogo de munição e ainda com tipo
incorreto. Guarda os registos anteriores em `missiles_items_backup` e
`missiles_inventory_backup`. Gemas aplicadas voltam para a mochila; objetos
em posições de equipamento incompatíveis também. Se faltar espaço ou existir
uma associação inválida, a reparação reverte.

Repetir não repõe cargas em pacotes já convertidos. Os dois objetos encontrados
na BD desta sessão foram reparados com backup, sem serem recriados.

## 6. Testes e ficheiros

```bash
php scripts/testing/test_missiles.php "nome-do-herói"
```

O teste usa uma transação exterior com rollback e verifica dano, bónus de pet,
limites de cargas/índices, entrega, movimentos, níveis/destinos, desbloqueios,
saldos, recarga, compra direta, prémios da temporada, duelos, liga e missões.
Requer uma conta com inventário/missões e outra conta para os testes PvP.
As alterações de saldos, atributos, objetos e batalhas dos testes são revertidas.

| Ficheiro | Responsabilidade |
|---|---|
| `app/HeroZero/Missiles.php` | Constantes, pacotes, movimentos, transações e consumo |
| `app/HeroZero/Fight.php` | Uso de munição nas rondas e dano |
| `app/HeroZero/Character.php` | Loja, compra, perfis/aparências, missões e treino |
| `app/HeroZero/Season.php` | Prémios tipo 8 |
| `app/HeroZero/StoryDungeon.php` | Consumo na dungeon de história |
| `actions/inventoryOps.php` | Encaminhamento das ações de munição |
| `actions/startDuel.php` / `actions/leagueMissed.php` | Persistência e consumo PvP |

Os caminhos da tabela são relativos a `server-laravel/`.
Fontes: `public/js/HeroZero.min.js` (`Za.getMissileDamage`,
`Gi.acceptsDragSource`, `Gi.moveAmmoBeltItem`, `Hm.showMissile`) e o catálogo
zlib `assets/data/constants_json.data` da raiz.
Ver também [inventário e cofre](implementacao.md).
