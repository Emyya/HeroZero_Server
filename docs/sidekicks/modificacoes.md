# Modificações dos acompanhantes

Executar `php scripts/testing/migrate_sidekick_improvements.php` nas bases
existentes. Acrescenta `sidekicks.item_improvement_id INT NOT NULL DEFAULT 0`
se estiver ausente e corrige os tipos dos consumíveis antigos. Novas bases
já incluem a coluna no schema inicial. Os equipamentos continuam a funcionar
antes da migração; modificar sidekicks exige a coluna.

Valores: 0 = sem espaço; 1 = espaço vazio; -ID = melhoria aplicada.
`useInventoryItem` recebe `item_id`, `target_sidekick_id`, `replace_existing`.
O consumível 14 abre o espaço; a melhoria tipo 12 tem um identificador
`improvement_sidekick_common/rare/epic/legendary` (quatro raridades).

Pets recebidos nos [casinos](../casino/README.md) são criados com
`item_improvement_id=1` (espaço gratuito, vazio), sem gastar o consumível 14.
O preview tipo 9 usa `items.ts_availability_end=1`; no resgate o espaço é
transferido para o sidekick criado. Mantém-se a raridade épica e não se aplica
borda warforged ao pet. Abrange novos giros e resultados ainda pendentes;
os pets já resgatados não são alterados retroativamente. A coluna de melhoria
é obrigatória: na sua ausência o resgate falha atomicamente, sem DDL de jogo.
O teste `php scripts/testing/test_casino.php --db` verifica o espaço no preview,
no pet entregue e a aplicação real de uma gema de sidekick, com rollback.

Os atributos guardados do sidekick mantêm os seus valores originais. O protocolo
e o contributo do acompanhante ativo ao herói incluem a melhoria: acrescenta-se
o valor base da melhoria e aplica-se `Sidekick::statAtLevel` com o nível e a
raridade do sidekick. Subidas de nível e fusões recalculam os atributos originais;
a melhoria é acrescentada quando os dados são lidos, sem acumular bónus.

A melhoria aplicada sai da mochila e é enviada em `item_improvements`, incluindo
na consulta de outros personagens. Substituir destrói a anterior. Libertar ou
consumir o sidekick numa fusão elimina a melhoria; o preço de libertação inclui
o seu valor de acordo com a fórmula do cliente.

Teste manual persistente (migra a coluna automaticamente):

```bash
php scripts/testing/prepare_sidekick_improvement.php "skel"
```

Escolhe preferencialmente o ativo sem melhoria; abre o espaço e coloca uma
melhoria lendária compatível na primeira posição livre da mochila.
Argumentos adicionais: ID do sidekick (0 = automático), raridade 1–4.

Teste com rollback após migração:
`php scripts/testing/test_sidekick_improvements.php`.
