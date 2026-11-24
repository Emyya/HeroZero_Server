# Torneios Semanais — Visão Geral da Implementação

Ficheiro principal: `server-laravel/app/HeroZero/Tournament.php`  
Actions: `server-laravel/actions/getTournamentRewards.php`, `claimTournamentRewards.php`

---

## Tipos de torneio

| Tipo interno | Aba no cliente | Métrica |
|:---:|---|---|
| 1 (`TYPE_XP`) | Experiência | XP ganho desde o snapshot |
| 2 (`TYPE_STATS`) | Pontos de Habilidade | stat_base total gasto desde o snapshot |
| 3 (`TYPE_PVP`) | PvP / Honra | Duelos jogados desde `ts_start` (tabela `duel`) |

O cliente usa uma numeração diferente nos payloads de recompensa: PvP é enviado como chave `4` (não `3`). Ver `Tournament::rewardKey()`.

---

## Calendário semanal

Cada torneio tem o seu próprio ciclo, alinhado ao relógio `GameTime` e ao fuso
`Live::gameTimezone()` (`HZ_GAME_TZ`, por defeito `Europe/Lisbon`):

| Torneio | Fim e início do ciclo seguinte |
|---|---|
| Experiência | Domingo às 21:00 |
| PvP | Terça-feira às 21:00 |
| Pontos de habilidade | Quarta-feira às 21:00 |

`Tournament::period()` calcula o limite anterior e o seguinte no calendário
local. As 21:00 mantêm-se nas mudanças de hora; a semana pode ter 167, 168 ou
169 horas. `week` é ano/semana ISO do início (`oW`), não o dia de uptime.

## Ciclo de vida e concorrência

`Tournament::synchronize()` fecha ciclos vencidos e cria o ciclo atual de cada
tipo. Corre no login, estado normal/polls, ranking e ao avançar o relógio pelo
endpoint `/servertime`. O encerramento é lazy no primeiro pedido que deteta o
limite; as datas permanecem os limites exatos do calendário.

Toda a operação usa transação/savepoint no PDO do jogo e o lock único da tabela
`tournament_calendar_lock`. Criação, snapshots, prémios pendentes e conclusão
ficam na mesma transação. Índices únicos impedem repetir `(type, ts_start)`,
snapshot por personagem ou prémio do mesmo torneio/personagem.

O lock só é pedido quando há algo para fechar ou criar. Com os três ciclos ativos,
sem torneios antigos por migrar e com mais de 5 s até ao fim de cada um,
`Tournament::synchronize()` responde com leituras simples, sem transação nem
`FOR UPDATE` (`currentUnlocked()`); caso contrário segue o caminho com lock, o
único que fecha/cria. Medido com 1000 bots: o lock global em cada pedido era o
principal ponto de serialização. Teste: `scripts/testing/test_calendar_lock_free.php`.

Ao fechar XP, só se guarda a key `1`; ao fechar habilidades, só `2`; ao fechar
PvP, só `4`. Cada limite dá origem à sua janela de classificação e recompensa.
Não há um resgate conjunto dos três para os novos ciclos.

Se o relógio saltar várias semanas, encerra-se uma vez cada ciclo existente e
abre-se o período atual. Não se inventam prémios para semanas sem snapshots.
XP/habilidades continuam a usar deltas de snapshots; não existe histórico para
reconstruir exatamente esses ganhos por semana durante um salto temporal.
PvP limita os duelos a `ts_start <= ts_creation < ts_end`.

| status | Significado |
|---|---|
| 1 | Ativo |
| 2 | A processar |
| 3 | Terminado |
| 4 | Cancelado na transição do calendário antigo |

## Ranking e protocolo

O cliente recebe timestamps Unix, não um número de dias:

- `tournament_end_timestamp`: fim do XP (campo legado).
- `global_tournament_end_timestamp`: fim do PvP.
- `tournament_end_timestamps`: objeto com `"1"` (XP) e `"2"` (habilidades).

O bundle guarda as datas locais por tipo; cada aba usa a sua, mesmo ao alternar
entre abas já carregadas. HeroCon mantém o seu sistema de datas. Boot, estado
normal e respostas parciais usam as mesmas datas do calendário.

## Migração de instalações existentes

Executar `php scripts/testing/migrate_tournament_calendar.php`.
É persistente e idempotente; guarda backup das três tabelas e contadores
pendentes em `server-laravel/storage/backups/` antes de aplicar DDL.

Os ciclos legados (`type IS NULL`) ainda ativos/a processar são cancelados sem
produzir prémios adicionais. A base de XP/atributos do mais antigo é copiada
para os três ciclos novos, preservando uma só cadeia de progresso na transição.
As datas dos novos períodos seguem o calendário; a base transportada pode não
coincidir exatamente com o seu início. PvP conta duelos pelas datas do seu ciclo.
Prémios antigos já emitidos, reclamados ou pendentes são preservados.

`tools/tournament.php status` mostra os três calendários sem alterações.
`start`/`end` sincronizam apenas ciclos vencidos, sem forçar fecho antecipado.
O reset antigo foi removido para não reabrir o mesmo período e repetir prémios.

## Validação

- `php scripts/testing/test_tournament_calendar.php --offline`: limites e DST.
- `php scripts/testing/test_tournament_calendar.php ["Herói"]`: integração MySQL
  com rollback, transição antiga, lock numa segunda ligação, fechos separados,
  resgate, login, estado normal, respostas parciais e saltos temporais.
- `node scripts/testing/test_tournament_calendar_client.js`: getters/setters
  reais do bundle, receção do mapa e troca das datas entre abas.

Confirmação visual no browser pendente.

---

## Snapshots

Ao criar um torneio, é feito um snapshot de **todos os personagens existentes** (`snapshotAll`), gravando:

- `xp_start` — XP atual
- `honor_start` — honor atual
- `guild_honor_start` — honor da guilda
- `stat_base_start` — soma de stat_base_stamina + strength + critical_rating + dodge_rating

Jogadores que entram **depois** do torneio ter começado recebem snapshot no primeiro login (`ensureSnapshot`, chamado em `autoLoginUser.php`).

---

## Recompensas

Geradas como pendentes ao encerrar cada tipo em `synchronize()`. São inseridas em `tournament_rewards` e incrementa `pending_tournament_rewards` no personagem. Detalhes completos em [recompensas.md](recompensas.md).

### Torneio XP (key 1): donuts + habilidades + passe

| Rank | Donuts | Pontos de Habilidade | Pontos do passe |
|---|:---:|:---:|:---:|
| 1º | 75 | 10 | 30 |
| 2º | 60 | 7 | 30 |
| 3º | 40 | 5 | 30 |
| 4º–10º | 20 | 3 | 20 |
| 11º–100º | 5 | 2 | 10 |

### Torneio Stats (key 2): habilidades + passe

| Rank | Pontos de Habilidade | Pontos do passe |
|---|:---:|:---:|
| 1º | 40 | 30 |
| 2º | 35 | 30 |
| 3º | 30 | 30 |
| 4º–10º | 20 | 20 |
| 11º–100º | 10 | 10 |


### Torneio PvP (key 4): XP (% do nível actual)

Fórmula: `(xpForLevel(nivel+1) - xpForLevel(nivel)) * percentagem / 100`

| Rank | % do XP do nível actual |
|---|:---:|
| 1º–30º | 50% |
| 31º–100º | 45% |
| 101º–500º | 40% |
| 501º–1000º | 35% |
| 1001º–2500º | 30% |
| 2501º–5000º | 25% |
| 5001º–7500º | 20% |
| 7501º–10000º | 10% |

### Resgatar recompensas

Action `claimTournamentRewards`:
1. Verifica `pending_tournament_rewards > 0`
2. Lê a recompensa mais antiga não reclamada de `tournament_rewards`
3. Usa o XP fixado na primeira apresentação e distribui por tipo:
   - `donuts` → `user.premium_currency`
   - `stat_points` → `character.stat_points_available`
   - `xp_percentage` → usa `xp_amount` apresentado, adiciona a `character.xp`
4. Credita `season_reward.amount` em `season_progress.season_points` se a janela
   de temporada apresentada continuar válida.
5. Marca `claimed = 1`, decrementa `pending_tournament_rewards`; pagamento e
   marcação usam a mesma transação. Devolve o progresso e recompensas do passe
   atualizados, além dos saldos do personagem/conta.


## Inscrição nos dois logins (2026-10-21)

`loginUser` e `autoLoginUser` usam `Tournament::ensureCharacterSnapshot` para
inscrever a personagem nos três ciclos ativos. Repetições preservam as bases
existentes. Personagens criadas depois do início do ciclo não dependem de
relogin automático para entrar. Bases em falta começam no próximo login;
XP/habilidades anteriores não podem ser reconstruídos sem histórico. PvP usa
os duelos do ciclo, conforme a métrica existente.
Teste MySQL com rollback: `docker compose exec -T game php
/workspace/scripts/testing/test_bot_login_boosters.php PedroPipoca` (comando
numa só linha; aceita nome de herói).
