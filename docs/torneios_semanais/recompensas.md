# Recompensas dos Torneios Semanais

Fonte: `assets/data/constants_json.data` (`tournament_rewards` + `global_tournament_rewards`) + fonte oficial.

---

## Tipos de recompensa (`reward_type`)

| Valor | O que é |
|:---:|---|
| 1 | Donuts (`premium_currency` no `user`) |
| 2 | Moedas de guilda premium |
| 3 | Pontos de habilidade para gastar (`stat_points_available` no `character`) |
| 4 | XP de personagem (% do XP do nível actual) |

---

## Torneio de Experiência (`tournament_type 1`, key `1`)

Competição: XP ganho durante a semana no servidor local.

| Rank | Donuts | Pontos de Habilidade | Pontos do passe |
|:---:|:---:|:---:|:---:|
| 1º | 75 | 10 | 30 |
| 2º | 60 | 7 | 30 |
| 3º | 40 | 5 | 30 |
| 4º–10º | 20 | 3 | 20 |
| 11º–100º | 5 | 2 | 10 |

---

## Torneio de Pontos de Habilidade (`tournament_type 2`, key `2`)

Competição: pontos de habilidade gastos (stat_base total) durante a semana no servidor local.

| Rank | Pontos de Habilidade | Pontos do passe |
|:---:|:---:|:---:|
| 1º | 40 | 30 |
| 2º | 35 | 30 |
| 3º | 30 | 30 |
| 4º–10º | 20 | 20 |
| 11º–100º | 10 | 10 |

---

**Pontos do passe:** valores oficiais de `gods_1`, sujeitos à elegibilidade da temporada.
As condições de entrega estão na secção de pontos do passe abaixo.

## Torneio PvP (`tournament_type global`, key `4`)

Competição: número de duelos realizados.
Recompensa: **X% do XP do nível actual** do herói.

Fórmula: `(levels[nivel+1].xp - levels[nivel].xp) * percentagem / 100`

| Rank | % do XP do nível actual |
|:---:|:---:|
| 1º–30º | 50% |
| 31º–100º | 45% |
| 101º–500º | 40% |
| 501º–1000º | 35% |
| 1001º–2500º | 30% |
| 2501º–5000º | 25% |
| 5001º–7500º | 20% |
| 7501º–10000º | 10% |

---

## Implementação

- O diálogo de resultados apresenta o torneio PvP (key `4`) com o ícone de
  honra `icon_honor`, a 30×32. O campo visual `iconGuildHonor` mantém o nome
  esperado pelo cliente, mas usa `SymbolIconHonorGeneric`. Removida a substituição
  pela textura de emblema `fist` (BUG-000139).
- `Tournament::synchronize()` — guarda no JSON de recompensas os campos correctos por tipo (`donuts`, `stat_points`, `xp_percentage`)
- `claimTournamentRewards.php` — distribui cada campo ao sítio certo:
  - `donuts` → `user.premium_currency`
  - `stat_points` → `character.stat_points_available`
  - `xp_percentage` → fixa `xp_amount` na primeira leitura e adiciona-o a `character.xp`

### Apresentação e resgate (BUG-000140)

A janela apresenta a classificação e os prémios antes do OK. Os novos ciclos
independentes mostram apenas o tipo que terminou. Registos antigos conjuntos
continuam a mostrar os três blocos. O OK resgata o registo apresentado, reproduz
os efeitos e abre o próximo registo pendente, quando existe.

`getTournamentRewards` adapta também registos antigos: conserva os campos
locais e acrescenta `reward: {type, amount, type_2, amount_2}` por torneio.
Tipos deste contrato: 1 donuts, 3 atributos e 4 XP absoluto. O XP percentual é
convertido na primeira leitura e guardado como `xp_amount`; leituras seguintes
e o resgate usam esse valor, mesmo que o nível mude entretanto.

O diálogo do cliente soma donuts e atributos dos torneios e passa também a
apresentar XP usando a recompensa genérica de tipo 2. Este tipo genérico é
uma numeração diferente da usada dentro de `reward`.

Leitura/resgate usam locks no personagem e no registo da recompensa, transação
ou savepoint no PDO do jogo. Pagamento e marcação são atómicos; o estado da
resposta é recarregado após creditar. Prémios já resgatados não são repostos.

Validação: `php scripts/testing/test_tournament_rewards.php ["Herói"]`
(integração MySQL com rollback). Confirmação visual no browser pendente.

### Pontos do passe de temporada (BUG-000142)

XP e habilidades também dão pontos do passe segundo `season_locations`:

| Posição | XP (locations 6/7/8) | Habilidades (18/19/20) |
|---|---|---|
| 1–3 | 30 | 30 |
| 4–10 | 20 | 20 |
| 11–100 | 10 | 10 |
| Outras | 0 | 0 |

Estes valores são os oficiais de `gods_1`; o servidor lê o valor pelo identifier
da temporada nas constantes. PvP não tem este prémio.

O ranking verifica a data do próprio torneio em cada refresh. Os componentes
são criados quando a temporada está ativa, evitando uma decisão definitiva
antes de conhecer o tipo/prazo da linha. O prémio aparece apenas se a temporada
estiver ativa e terminar depois do torneio, conforme o cliente original.

No get, `season_reward` guarda `amount`, `identifier`, `progress_id` e `ts_start`
da temporada. O fecho deve pertencer à janela dessa temporada. A janela de
resgate usa este prémio explícito em vez de recalcular um valor diferente.
O claim credita-o na mesma transação e devolve progresso/recompensas atualizados.
Se a temporada expirar/renovar entretanto, os pontos não são transferidos para
a nova janela. Registos já resgatados não são pagos retroativamente.

Testes: `test_tournament_rewards.php` valida posições, preview, entrega e
rejeição de duplicados; `test_tournament_calendar_client.js` executa as linhas
reais dos dois layouts e o componente real de pontos de temporada.
