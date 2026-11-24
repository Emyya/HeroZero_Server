# Patamares diários de ataques a esconderijos

Patamares de 5/10/20 ataques, com as mesmas cinco opções dos duelos/liga:
épico, cupão de 10/20/30 energias, cupão de 5/10/15 treinos, 5/10/20 pontos
livres ou 3%/5%/10% da diferença de XP entre níveis. Um prémio sorteado e
um ponto de temporada por patamar resgatado, até três pontos por dia.

Conta no `collectHideoutFightRewards` de ataques a outros esconderijos reais,
ganhos ou perdidos. Tutorial, defesas, pedidos inválidos e retries não contam.
O resgate usa lock do herói e da batalha, transação/savepoints para incluir
recursos, fecho da batalha e contador. A resposta atualiza a barra imediatamente.

Tipo 4 do cliente, campo `hideout_battle_won`, patamares 5/10/20; os nomes
oficiais são preservados apesar da regra local contar também derrotas.
Local 11 de temporada anuncia um ponto em todos os temas. Pontos livres usam
`statPoints` na recompensa para aparecerem no diálogo (BUG-000150).

Sorteios estáveis, reset à meia-noite do jogo, expiração dos prémios não
resgatados e cupões persistentes seguem as regras de
[patamares de combates](../missoes/patamares_combates.md).
Não há reconstrução do histórico nem compensação retroativa.

```bash
php scripts/testing/migrate_daily_hideout.php
php scripts/testing/test_daily_combats.php --db "Nome do herói"
node scripts/testing/test_daily_energy_client.js
node scripts/testing/test_daily_milestone_rewards_client.js
```

Migração idempotente `11_daily_hideout.sql`: duas tabelas, fora das transações
de jogo; aplicada nesta instalação. Integração MySQL com rollback inclui todas
as 15 opções do esconderijo e resgates reais de vitórias/derrotas, tutorial,
defesas, IDs inválidos e retries. Testes Node verificam barra e diálogo reais
com gráficos simulados. Confirmação visual no browser pendente.
