# Login diário e Dias de Bónus

Implementação: `DailyLogin.php`, `BonusEvents.php` e painel admin `/bonuses`.
Ambos usam `GameTime` e o calendário de `Live::gameTimezone()` (Lisboa por defeito).
O número «Dia N» da barra é uptime acumulado; não é o contador de prémios do herói.

## Login diário normal

- Um prémio por data do calendário do jogo. Disponível no login e em `syncGame`/
  `Live::accountState`, incluindo quando o jogador permanece ligado à meia-noite.
- A resposta contém `daily_login_bonus_day` (inteiro) e
  `daily_login_bonus_rewards: {id, rewards}` (`rewards` é JSON numa string).
- `claimDailyLoginBonus` exige esse ID, proprietário e data actual. Entrega e
  marcação são atómicas, com lock do personagem e savepoint se já houver transacção.
- O primeiro resgate é o dia 1. O contador avança apenas ao resgatar. Dias sem
  resgate não dão prémios acumulados nem reiniciam o contador (regra local).
- O catálogo de tipos e factores vem de `daily_login_rewards` nas constantes zlib.
  O cliente suporta dias acima de 30. Este servidor repete a tabela a cada 30 dias
  mantendo o número absoluto do dia: regra local, não confirmação do servidor oficial.
- Preview de equipamento persistido antes de abrir a janela e incluído em `items`.
  Mochila cheia devolve `errInventoryNoEmptySlot` sem consumir o prémio. O cliente
  pode enviar `discard_item=true`: vende o item pelo `sell_price`, atomicamente.
- Previews não resgatados expiram na mudança de data e são eliminados. Não são
  colocados na mochila antes de reclamar.

### Quantidades

Donuts e pontos de atributos usam a quantidade directa das constantes. As escalas
restantes são **balanceamento local**, porque não encontramos a fórmula oficial:

| Tipo | Quantidade local |
|---|---|
| Moedas | factor × 50 × nível no momento da criação |
| XP | factor × 100 × nível no momento da criação |
| Equipamento épico `random_epic` | um item, reutiliza o gerador das histórias; factor como offset de nível/poder, utilizável ao nível do herói |
| Cola / pedra | factor como quantidade; cupão pessoal nas mensagens para evitar perder recursos por capacidade |

XP sincroniza subida de nível. A chave JSON dos donuts é `premium`, conforme
`Reward.get_premiumCurrency()` no cliente. A tabela `daily_login_rewards` da BD
guarda o prémio final; não confundir com o catálogo homónimo nas constantes.

## Eventos configuráveis

Em **Admin → Dias de Bónus**, escolher nome, primeiro dia, duração (1–365 dias),
multiplicador (×2–×5) e uma ou mais vantagens. É possível agendar, listar e cancelar.
A data inicial conta na duração: hoje + 3 dias significa hoje, amanhã e depois de
amanhã; se criado a meio do dia, o primeiro dia é parcial.

| Tipo do cliente | Vantagem | Aplicação real |
|---|---|---|
| 1 | Energia de missões | máximo diário e acréscimo inicial; limites de recarga/depósito permanecem normais |
| 5 | Unidades de treino | máximo diário e acréscimo inicial, mantendo troféus/energéticos |
| 7 | XP de missões | multiplicação no resgate, incluindo XP do pet derivado desse prémio |
| 8 | Moedas de missões | multiplicação no resgate |
| 10 | Energia de treino | capacidade da barra, início de treino, regeneração limitada ao novo máximo e recarga |

XP e moedas aplicam-se às missões normais resgatadas durante o evento, mesmo se
geradas antes; os prémios persistidos continuam base. Não multiplicam login,
treinos, histórias, trabalho, liga ou prémios da temporada.

O cliente multiplica a apresentação por si próprio através de `BonusInfo`: não
enviar prémios/máximos já multiplicados. A energia e as unidades de treino recebem
o acréscimo uma vez por data; sincronizar ou comprar energia não volta a concedê-lo.
Cancelar e reactivar no mesmo dia não concede novamente o acréscimo já recebido.
No mesmo dia, a sincronização preserva recursos acima do máximo, incluindo
cupões resgatados. A mudança de dia mantém a reposição normal e o armazenamento
de energia até 100; unidades de treino restantes não transitam (BUG-000147).

Não se permitem dois eventos com o mesmo tipo na mesma data (índice único, criação
atómica). Eventos de tipos diferentes podem coexistir. Só eventos activos entram
em `bonus_info.bonus_datas`; sem evento é enviado um calendário vazio, substituindo
os dois dias antigos de Julho da fixture. O cliente foi ajustado para não abrir uma
janela vazia. Os timestamps do calendário representam o meio-dia local, evitando
deslocar dias históricos/futuros por mudança de hora; os limites reais são datas locais.

Os outros tipos que o cliente conhece (honra, liga, regeneração de duelos, etc.)
não são expostos neste painel: precisam de integração própria nos respectivos sistemas.

## Relógio e mudança de hora

`Oc.get_utcLocalTimestampOffset()` calcula agora o fuso do browser na **data
simulada**, usando a correcção de tempo. Antes usava a data real: simular Novembro
enquanto o computador estava em Outubro atrasava o calendário do cliente uma hora.
Os números dos Dias de Bónus usam arredondamento da diferença de datas para não
repetir um número na passagem para a hora de verão (dia com 23 horas).

## Instalação e verificação

Schema inicial: `server-laravel/docker/initdb/05_daily_bonus.sql`.
Instalações existentes: `php scripts/testing/migrate_daily_bonus.php` (idempotente,
fora das transacções do jogo). Não activa eventos nem altera recompensas existentes.

```bash
php scripts/testing/test_daily_bonus.php
node scripts/testing/test_daily_bonus_client.js
php scripts/testing/test_daily_bonus.php --db "Nome do herói"
php scripts/testing/test_bonus_admin.php
```

Os dois testes MySQL usam rollback; o relógio simulado é alterado apenas em memória.
Teste do admin verifica middleware, renderização e controlador, não um browser real.
Confirmação visual pendente: relogar, resgatar prémio e criar um evento no painel;
verificar a janela, pré-visualizações e a mudança de data no cliente.

Para avançar o relógio manualmente, usar `php scripts/testing/advance_game_time.php 3`
(exemplo: três dias de 24h). Converte dias para segundos e guarda o offset global.
Não acumula resgates dos dias saltados nem altera o contador de uptime.
Ver [catálogo de scripts](../scripts.md).

## Correcção do calendário vazio (ui.har)

O cliente converte `bonus_datas` para `DataObjectArray`, com a lista interna em
`.a`; não é um array JavaScript directo. `BonusInfo.get_needsDisplay()` verifica
agora `.a.length`, impedindo abrir `DialogBonusInfoNotification` sem dias.
A verificação anterior usava `.length` no wrapper e deixava passar a lista vazia,
causando `a[0] is undefined` durante `autoLoginUser`.

O teste executa a conversão e o wrapper reais do bundle, com lista vazia, evento
activo, evento visto e evento terminado. Pode também analisar o HAR:

```bash
node scripts/testing/test_daily_bonus_client.js ui.har
```

Validado contra os dois calendários vazios de `ui.har`; a recompensa de login
normal do dia 2 não era a causa. O URL do bundle na página principal inclui uma
versão nova para renovar a cache do browser. Confirmar visualmente após recarregar.

## Patamares de atividade diária

Os patamares de energia e combates são um sistema separado do prémio de login:
100/200/300 energias de missões concluídas resgatadas, 10/20/50 duelos e
5/10/20 combates de liga ou ataques a outros esconderijos. Contam ataques
resgatados, ganhos ou perdidos; tutorial do esconderijo, defesas e retries
ficam excluídos. Os quatro sistemas aparecem no login e nos estados completos
ou parciais, com sorteios persistentes e reset à meia-noite do calendário do jogo.

Cada patamar resgatado concede um dos cinco prémios locais e um ponto de
temporada; energia e treinos geram cupões. Pontos livres usam `statPoints`
no JSON para aparecerem no diálogo. Detalhes em
[patamares de energia](../missoes/patamares_energia.md) e
[patamares de combates](../missoes/patamares_combates.md).
