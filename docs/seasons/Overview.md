# Estado Actual das Seasons (2026-10-04)

Temporada inicial: **gods_1**. Rotação disponível: 80 catálogos oficiais.

---

## O que está a funcionar

### Progresso e painel
- `openSeasonPanel` devolve `season_progress` e `season_rewards` (104 recompensas em `gods_1`; quantidade derivada do catálogo ativo)
- `season_points` avança quando se desbloqueiam rewards via donuts — o marcador laranja no cliente move-se
- Faixas free e premium visíveis para todos. Prémios premium não resgatados
  têm estado 0 e exigem `premium_unlocked=true`, ativado pela compra local do
  passe. Pontos suficientes, avanço com donuts ou desbloqueio individual antigo
  não substituem essa compra. Prémios já resgatados mantêm o estado 2.

### Compra local do passe

O botão de compra usa agora o pacote da temporada atual, entregue gratuitamente
pelo fluxo de [compras locais](../compras/overview.md). Ativa
`premium_unlocked=true`, sem cobrar donuts nem resgatar prémios automaticamente.
O catálogo deixa de anunciar o passe depois da compra.

### Avançar com donuts (`unlockSeasonReward`)
- Encontra o próximo reward FREE ainda não acessível por pontos
- Debita `skip_cost` donuts (custo escalonado: 5 / 9 / 19 / 49 por grupo de tiers)
- Avança `season_points` para o threshold do tier desbloqueado
- Não debita donuts se não houver nada para desbloquear

### Resgatar recompensas (`claimSeasonReward`)

O login envia `season_progress` e `season_rewards` da conta. O cliente desenha
o painel antes da resposta de `openSeasonPanel`; sem pré-carregar as recompensas,
a primeira abertura mostrava cadeados falsos e a segunda já estava correta.
O `season.har` confirma estados iguais/corretos nas duas respostas de abertura.

- **Moedas (type 1)** — `factor × 5000` moedas → `character.game_currency` ✅
- **Donuts (type 2)** — `factor` donuts → `user.premium_currency`.
- **Pontos de atributos gratuitos (type 3)** — `factor` pontos → `character.stat_points_available`, como nos torneios semanais. Corrigido: antes eram entregues donuts indevidamente. O HAR `debug_pontos_stats_season.har` mostra o resgate 14 (10 pontos) com contador ainda a zero. Os pontos são consumidos por `Character::improveStat()` antes de cobrar moedas; não aumentam os atributos automaticamente.
- **XP (type 4)** — `factor × 10000` XP (escala local) → `character.xp` + `syncLevelFromXp()`.
- **Energia (type 8)** — `factor` guardado como cupão das mensagens.
  Patamar 8 pontos, faixa premium: 60 energias. O JS confirma a quantidade direta.
- **Unidades de treino (type 6)** — `factor` guardado como cupão das mensagens.
- **Supercola (type 11) / zenit (type 12)** — cupões; quantidade local provisória = factor.
  Fórmula oficial desconhecida; JS mostra `???`. Mapeamento confirmado no JS,
  não confundir com pontos de atributos/energia. Ver [cupões](../cupoes/mensagens.md).
- **Season items equipamento (type 7, sem "sidekick")** — criados em `items` com stats epic, atribuídos à bag ✅
- Equipamentos novos do passe recebem `ts_availability_start = 15` (melhoria +15),
  ativando a borda `warforged` no cliente (`warforged_effect_min_level = 15`).
  A raridade mantém-se; pets e modificações não recebem
  esta melhoria. Objetos já resgatados não são alterados retroativamente.
- Atributos do equipamento usam a fórmula do brechó com multiplicador
  `2.3 + 0.20 = 2.5`: `ceil((L × 1.6 + 2 + L² × 0.004) × 2.5) + item_flat`
  do laboratório cibernético. Distribuição igual aos épicos: três atributos
  em 40% dos casos, quatro em 60%. Mantém-se L igual ao nível do herói no
  resgate, sem o desconto aleatório de 0–4 níveis da loja. Não é fórmula oficial.
- Equipamentos novos têm `ts_availability_end = 1` e pets novos têm
  `sidekicks.item_improvement_id = 1`: espaço de modificação vazio, sem gema aplicada.
  Gemas não recebem espaço. A coluna dos pets deve existir antes do resgate;
  se faltar, executar `php scripts/testing/migrate_sidekick_improvements.php`.
  Não se faz DDL dentro da transação de resgate.
- **Season sidekick (type 7, identifier contém "sidekick")** — criado em `sidekicks` via `Sidekick::create()` ✅
- **Improvement items (type 10)** — criados em `items` como tipo 12, com identificador/raridade do catálogo oficial e atributos não nulos; colocados na primeira posição livre da mochila.

O resgate bloqueia personagem/inventário/progresso e só grava a recompensa como
resgatada depois de entregar o resultado. Uma falha reverte as escritas. Para
gemas, mochila cheia devolve `errInventoryNoEmptySlot` e permite repetir o resgate
depois de libertar espaço. `discard_item=true` continua a descartar explicitamente.

### Constantes CDN (`constantsData`)
- Dados reais de `gods_1` de `assets/data/constants_json.data`
- 52 tiers com `skip_price` reais: 5 (1–10pt), 9 (15–30pt), 19 (40–100pt), 49 (125–1000pt)
- `sidekick_battle_effect_id: 1074` correcto

---

## Problemas conhecidos / não resolvidos

### Gemas antigas entregues antes da correção
- O caminho antigo guardava-as em `pattern_items`, sem atributos e sem posição na mochila.
- O caminho novo usa o sistema de modificações descrito em `docs/modificações/implementacao.md`.
- Registos antigos não são convertidos automaticamente. Para repetir os resgates de teste:
  `php scripts/testing/reset_season_for_testing.php "skel"`.
- A geração de atributos da gema usa balanceamento local provisório:
  orçamento = `max(4, ceil(nível × max(1, factor)/10 × multiplicador))`, com
  multiplicadores 0.5/0.65/0.8/1.2 por qualidade 1–4. O orçamento é distribuído
  pelos quatro atributos, todos positivos. Não é uma fórmula oficial capturada.

### Pontos por atividades — integração parcial
- `season_locations` é servido a partir das constantes oficiais, com a
  sobreposição local das chamadas descrita abaixo.
- Chamadas de emergência dão pontos ao resgatar: 1 nas diárias e 5 nas
  semanais em todos os 80 temas (localizações 14/13). Prémio, pontos e status são
  gravados na mesma transação. Repetição não duplica; descarte não dá pontos.
- Testes offline e MySQL com rollback confirmam mapeamento e resgate,
  incluindo `beetle`/`bear` e rejeição de duplicados (BUG-000144).

### Ganho de season points nas restantes atividades
- `Season::givePoints()` já está integrado nos resgates de chamadas.
- Patamares de energia gasta (100/200/300) dão 1 ponto por resgate, até 3/dia.
  A energia só conta ao recolher as recompensas de missões concluídas;
  missões canceladas não avançam os patamares. O ponto é entregue ao resgatar
  o patamar, não ao iniciar a missão nem ao usar o cupão.
  Local 17 anuncia esse valor em todos os temas; prémio/ponto/estado são atómicos.
  Ver [patamares de energia](../missoes/patamares_energia.md).
- Patamares de duelos (10/20/50) e liga (5/10/20) dão um ponto por
  patamar resgatado, até três por sistema/dia. Locais 9/10 anunciam um ponto
  em todos os temas. Contam resultados resgatados de ataques, ganhos ou
  perdidos, sem contar defesas nem retries.
  Ver [patamares de combates](../missoes/patamares_combates.md).
- Torneios XP/habilidades creditam pontos oficiais por posição no resgate atómico; ranking e diálogo mostram o prémio. Ver [recompensas dos torneios](../torneios_semanais/recompensas.md).
- Ganho direto em quests, duelos e restantes locais ainda está pendente.

### Tipos de reward não implementados
- **Type 5** (energéticos) — sem entrega implementada no passe; cupões de
  energéticos das mensagens funcionam pelo seu caminho próprio.
- **Type 9** (sidekick especial/surprisebox) — ignorado
- **Type 13** (título) — ignorado, o sistema de títulos existe mas não está ligado

### Mísseis do passe
- `missiles_*` gera agora munição tipo 8 com cargas, através de `Missiles::give()`.
- Não recebe atributos de arma, borda +15 nem espaço de modificação.
- Quantidade local por pacote: 100 cargas; não há quantidade nas constantes HTML5.
- Os dois objetos antigos encontrados foram convertidos com backup e IDs preservados.
- Resgate e rejeição de duplicados validados em MySQL com rollback em
  `test_season_rewards.php`; comportamento de combate em `test_missiles.php`.
- Funcionamento e reparação: [mísseis](../inventario/misseis.md).

### Correção do mapeamento e histórico de resgates
- `et.refreshWithoutReward()` confirma: 4 XP, 6 treino, 8 energia, 11 cola, 12 zenit.
- `resgate.har`: seis resgates tipo 8 não criaram cupões (340 energias);
  seis do tipo 12 criaram cupões de energia erradamente. Os dois de treino
  geraram corretamente cupões de 5 e 10 unidades.
- Recuperação explícita e idempotente:
  `php scripts/testing/repair_season_coupons_from_har.php "skel" resgate.har`.
  Não remove XP/atributos antigos nem altera cupões já usados; não usar após reset.
- `test_season_stat_points.php` verifica só tipo 3: tipo 11 é cola, não pontos.
- `test_season_coupon_mapping.php` compara tipos com o JS e analisa o HAR.
- `test_user_vouchers_mock.php` executa o dispatch real de 6/8/11/12 com PDO simulado.
- `php scripts/testing/test_season_rewards.php --stats-only` valida a geração de
  atributos sem BD; passou nos níveis 1/50/335 e nas quatro raridades.

## Reset e testes

`resetSeasonProgress` limpa pontos, resgates e desbloqueios pagos, marca
`restarted=1` e cobra o custo de reset pela action. O problema antigo de manter
`unlocked_rewards` já não corresponde ao código atual.

O script de testes não cobra donuts nem remove objetos/moedas recebidos:

```bash
php scripts/testing/reset_season_for_testing.php "skel"
php scripts/testing/reset_season_for_testing.php "skel" 1000 1
```

Sem opções mantém pontos e premium; com `1000 1` define 1000 pontos e premium
ativo. Limpa `claimed_rewards`/`unlocked_rewards`, renova uma janela expirada
e guarda backup em `server-laravel/storage/season-debug-backups`.
Repetir resgates pode duplicar recompensas já recebidas; é ferramenta de teste.

Validação disponível:

```bash
php scripts/testing/test_season_stat_points.php
php scripts/testing/test_season_boot_contract.php season.har
php scripts/testing/test_login_improvement_contract.php problema.har
php scripts/testing/test_season_rewards.php --stats-only
php scripts/testing/test_season_rewards.php
```

Os quatro primeiros são verificações offline (HARs opcionais nos testes de
contrato); o último exige MySQL e reverte as alterações. Inclui orçamento
de equipamento ×2.5, distribuição 3/4 stats, borda +15, espaços vazios e gemas.
`--stats-only` testa atributos das gemas, não a fórmula de equipamento.

O utilizador confirmou os pontos gratuitos do tipo 3 no jogo. A fórmula nova
dos equipamentos, os espaços, a borda e as correções de login/painel ainda
precisam de confirmação específica no jogo; testes de contrato não a substituem.

`autoLoginUser` e `Live::accountState` alinham `active_season_progress_id` com
o ID real do progresso. O boot também envia `item_improvements`, evitando
crash ao relogar com gemas aplicadas; `problema.har` confirmou que as gemas
existiam e faltavam apenas na resposta de login.

Registo dos bugs: [BUG-000118 e BUG-000125–127](../bugs_encontrados.md).
Implementação das modificações: [documentação consolidada](../modificações/implementacao.md).

## Renovação automática (BUG-000143)

O ciclo local dura **30 × 86400 segundos do relógio interno `GameTime`**.
Todas as personagens partilham o mesmo tema e os mesmos limites de tempo.
Ao atingir `ts_end` (incluindo igualdade), a próxima leitura cria logicamente
um novo ciclo na mesma linha de progresso: status 2, pontos/premium/restarted
zerados e listas de resgates/desbloqueios vazias. Mantém ID e `ts_created`;
`ts_start` identifica a nova janela. Objetos e saldos já recebidos permanecem.
O catálogo muda automaticamente segundo a rotação descrita abaixo.

Os limites são calculados desde `season_calendar.ts_anchor`, em períodos fixos de 30 dias.
Um salto de vários períodos abre apenas o ciclo que contém o relógio atual,
sem prémios retroativos e sem conceder 30 dias adicionais desde o pedido.
A renovação é executada no próximo pedido que consulta o progresso, com
lock da personagem/progresso e transação ou savepoint. Não exige cron.
A tabela global é criada pelo schema inicial ou pela migração antes de usar
a funcionalidade; não se executa DDL durante pedidos de jogo.
Login, painel e estado normal enviam progresso e recompensas atualizados.
Pré-visualizações de torneios de outra janela não transferem pontos ao novo passe.

Teste MySQL com rollback e relógio apenas em memória:
`php scripts/testing/test_season_cycles.php ["Herói"]`.
Confirmação visual no navegador pendente.

## Rotação de temas

Cada ciclo de 30 dias avança um catálogo. Ordem local: ordem das entradas em
`constants.seasons`, rodada para começar em `gods_1`: gods_1 → gods_2 → gods_3
→ fairytale_1 → beetle → bear → … → gangsta_3 → gods_1. Não é calendário oficial.
Saltos de vários ciclos avançam o mesmo número de temas; após 80 repete.
Todas as personagens partilham tema e janela, definidos pelo calendário global persistente.

`identifier` seleciona catálogo, efeitos, textos e imagens; `season_id` é a
posição local (1–80). Patamares e as duas faixas vêm das constantes comprimidas,
sem substituir nomes de objetos. IDs de recompensa distintos por tema evitam
usar um pedido de resgate antigo no tema seguinte; gods_1 mantém IDs 1–104.
O cliente limpa o cache de recompensas ao mudar o identificador.
Tipos de prémio ainda não suportados mantêm as limitações documentadas acima.

O teste de ciclos valida os 80 catálogos, IDs exclusivos, gods_2 no primeiro
fim, salto até beetle, repetição após rotação completa, login e estado normal.
Integração MySQL com rollback passou; confirmação visual pendente.

## Definir pontos sem reset

`php scripts/testing/set_season_points.php "skel" 500` define o total em 500
na temporada atual, mantendo resgates e desbloqueios existentes. Aceita zero.
É persistente; se a temporada expirou, aplica primeiro a renovação normal.
Recarregar o jogo após executar. Não usar o script de reset para este efeito,
pois esse também limpa o histórico de resgates.

## Sincronização global das contas (complemento BUG-000143)

Os HARs `sonnet46.har`, `laliene.har` e `skel.har` confirmaram temas e datas
incompatíveis no mesmo instante. O calendário individual anterior foi substituído
por uma origem única persistente em `season_calendar` (linha 1).

Instalações existentes: `php scripts/testing/migrate_season_calendar.php`.
Migração idempotente, sem alterar progressos: a origem é o menor `ts_created`
positivo existente; sem progressos, usa `GameTime::now()`. Não usa relógio real.
A origem fica guardada e não muda com contas novas, eliminações ou ordem de login.
A migração foi executada nesta instalação.

No próximo acesso, todas as contas recebem tema, `season_id`, início e fim iguais.
Contas novas entram na janela corrente, sem 30 dias adicionais. Na adaptação das
janelas antigas, o mesmo tema ainda activo conserva pontos, premium e resgates;
tema diferente, janela expirada ou futura limpa o progresso do passe.
IDs do progresso, `ts_created`, objetos e saldos recebidos permanecem.
Saltos de vários ciclos continuam sem prémios retroativos; após 80 temas repete.

Validação MySQL com rollback em `test_season_cycles.php`: três personagens com
as janelas dos HARs, login em ordem inversa, criação tardia, expiração exata,
saltos, rotação completa, login e estado normal. Regressões de recompensas e
calendário/resgates de torneios passaram. Confirmação visual pendente.

### Relação com o relógio interno e avanço de 30 dias

`season_calendar` guarda uma referência de início, não um segundo relógio.
O instante atual vem exclusivamente de `GameTime::now()`:

```text
duração = 30 × 86400 segundos
ciclo = max(0, intdiv(GameTime::now() − ts_anchor, duração))
ts_start = ts_anchor + ciclo × duração
ts_end = ts_start + duração
tema = rotação[ciclo % 80]
```

Avançar o relógio interno exatamente 30 dias passa todas as contas para o tema
seguinte no próximo pedido que consulte o passe, independentemente da ordem dos
logins. O tempo restante fica aproximadamente igual ao anterior, descontando o
tempo decorrido durante a operação. Exemplo: faltavam 12 dias; após avançar
30 dias, faltam cerca de 12 dias no tema seguinte.

O novo ciclo começa com pontos, premium e `restarted` a zero, listas de resgates
e desbloqueios vazias e status 2. Prémios já entregues permanecem.
Os períodos são de 24 horas fixas, não meses civis; a mudança da hora pode
alterar a hora local apresentada sem alterar a duração em segundos.

## Pontos das chamadas em todos os temas (BUG-000144)

A rotação revelou que as constantes originais de `beetle` e `bear` atribuem
zero às localizações 14/13, escondendo os pontos das cinco chamadas e deixando
de os creditar. Evidência: `1.png` e `regressao_chamadas.har`, com passe `beetle` ativo.

A regra local mantém 1 ponto por diária e 5 por semanal em toda a rotação.
`Herobook::constants()` fornece a mesma sobreposição ao cliente e ao resgate;
as constantes comprimidas originais não foram editadas. Os patamares locais de energia/duelos/liga também anunciam um ponto;
as restantes atividades mantêm os valores originais. Não há compensação automática de resgates passados.

Validação: `test_herobook.php --offline`, integração MySQL com rollback em
`test_herobook.php` (incluindo `beetle`/`bear`, duplicados e descarte) e
`node scripts/testing/test_herobook_season_client.js` (cinco cartões reais,
componentes gráficos simulados, 80 temas e passe inativo). Confirmação visual pendente.
Recarregar completamente o jogo para voltar a receber as constantes corrigidas.

### Pontos dos patamares do esconderijo

Ataques a outros esconderijos resgatados desbloqueiam patamares 5/10/20,
ganhos ou perdidos. Cada patamar resgatado dá um ponto, até três/dia; tutorial
e defesas não contam. Local 11 anuncia um ponto em todos os temas. Usar o
cupão não dá outro ponto. Ver [patamares do esconderijo](../hideout/patamares_combates.md).
