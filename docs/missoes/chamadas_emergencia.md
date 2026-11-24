# Chamadas de emergência (Herobook)

Também referidas como chamadas heroicas. Integram a
[visão geral das missões](geral.md); bug associado: BUG-000128 em
[bugs_encontrados.md](../bugs_encontrados.md).

Implementação inicial em 2026-10-03. Evidência: `missoes.har`, `missoes.png`,
`HeroZero.min.js` e catálogo `herobook_*` em `constants_json.data`.

## Estado e geração

- Nível mínimo 40 (teaser 25), segundo constantes do jogo.
- Três objetivos diários e dois semanais, gerados ao carregar a conta.
- Objetivos compatíveis com nível, duração e atividades implementadas;
  identificadores e variância vêm das constantes oficiais.
- Renovação à meia-noite/segunda-feira no calendário do jogo (`GameTime`, fuso de `Live`).
- Objetivos expirados incompletos ficam abortados; concluídos não resgatados
  são preservados para resgate. Concluídos/resgatados ocupam a vaga até fim do período.
- Layout 3+2, escala de metas e fórmulas dos prémios escalados são regras locais provisórias,
  não uma reprodução confirmada da geração oficial.

## BD e protocolo

A tabela `herobook_objectives` já existe em `docker/initdb/01_schema.sql`.
Não foi necessária alteração de colunas. Estados do cliente: 1 em curso,
2 pronto a resgatar, 3 resgatado, 4 abortado. Duração: 1 diária, 2 semanal.

Login e respostas da conta incluem `herobook_objectives`, objeto com chaves
`objective_<id>` e campo `index` derivado de `objective_index`. As constantes
do sistema também são enviadas. Nas respostas parciais, `Router` completa
a coleção quando há personagem. O aviso da imagem era o fallback de objetivo
ausente, não um agendamento real para a próxima segunda-feira.

## Atividades ligadas

| Tipo | Atividade | Ponto de contagem |
|---|---|---|
| 4 | Lavar equipamento | Lavagem bem-sucedida |
| 8 | Renovar loja | Renovação bem-sucedida |
| 10 | Comprar atributos | Só pontos pagos, não gratuitos |
| 11 | Fazer duelos | Resgate de um duelo real |
| 12 | Completar missão de tempo | Resgate da missão tipo 1 |
| 13 | Completar missões | Resgate da missão |
| 16 | Usar energia de missão | Débito ao iniciar missão |

O progresso é limitado à meta e não avança após expirar/terminar.
Abrir o painel não conta como atividade; chamadas são geradas antes das atividades
normais, no carregamento da conta. Os outros tipos do catálogo não são sorteados
enquanto não houver ligação segura à respetiva atividade.

## Recompensas e donuts

Novas chamadas sorteiam um prémio usando `chance` em `herobook_daily_rewards`
ou `herobook_weekly_rewards`, não apenas XP. Os nove tipos são mapeados para
XP, donuts, pontos gratuitos, equipamento, energia de missão, treino, zenite,
supercola e moedas. Quantidades fixas e bónus de nível do equipamento vêm
do catálogo. Exemplos: donuts 1/2, equipamento +5/+10, treino 3–10/10–20.

As fórmulas oficiais do backend para `scaling=1` não estão no JS. Escalas locais:
XP = intervalo de XP do nível / 100 × reward_factor × fator do objetivo;
moedas = 113 × nível × max(1, (nível/300)²) × ambos os fatores;
zenite/supercola = nível × ambos os fatores. Valores arredondados, mínimo 1.
Energia, unidades de treino, zenit e supercola são guardados como
[cupões das mensagens](../cupoes/mensagens.md), sem perder quantidades por barra
ou depósito cheio. No resgate do cupão, treino aumenta `training_count`, não
`training_energy`; recursos exigem espaço para toda a quantidade.

Prémios de equipamento são gerados como preview persistente antes do resgate,
com qualidade épica e o bónus +5/+10. Só entram na mochila no resgate. Com mochila
cheia, a transação falha sem perder prémio ou pontos. Troca, expiração de objetivo
incompleto e descarte limpam previews não entregues, preservando itens que já
estejam no inventário/banco. Chamadas antigas mantêm o prémio já mostrado.

- `collectHerobookReward(id, discard_item)`: exige status 2, entrega o prémio,
  sincroniza nível quando dá XP e marca status 3 na mesma transação.
  Energia/unidades/recursos são entregues como cupões persistentes nas mensagens.
  Repetição é recusada.
- `instantFinishHerobookObjective(id)`: cobra 9 donuts (diária) ou 19
  (semanal) e deixa pronto a resgatar. Não entrega antecipadamente o XP.
- `renewHerobookObjective(id)`: cobra 1 donut, aborta o anterior e cria
  substituto na mesma vaga/duração. Só aceita objetivo em curso não expirado.
- Propriedade, estado, saldo e atualizações são protegidos por locks/transação,
  com savepoint se já existir transação.

## Pontos do passe de temporada

`season_locations` é servido ao cliente e consultado no resgate: localização
14 para diárias, 13 para semanais. A regra local mantém **1 ponto por diária e
5 por semanal nos 80 temas da rotação**, tanto no ícone como no resgate.
As constantes originais dão zero em `beetle` e `bear`; nos outros 78 temas
já dão 1/5. `Herobook::constants()` sobrepõe apenas essas duas localizações
para as temporadas conhecidas, e a mesma fatia alimenta cliente e backend.
Os assets comprimidos originais e as restantes localizações permanecem iguais.
Regressão após ativar a rotação: BUG-000144, comprovada em `1.png` e
`regressao_chamadas.har` (temporada ativa `beetle`).
O crédito usa `Season::givePoints()` na mesma transação da recompensa/status,
apenas com temporada ativa. Também funciona para chamadas antigas ainda por
resgatar, mas não compensa automaticamente as já resgatadas.
Conclusão normal ou por donuts deixa status 2; pontos são entregues ao resgatar,
não no simples preenchimento da barra. Descarte não entrega prémio/pontos.

## Testes

```bash
php scripts/testing/test_herobook.php --offline
php scripts/testing/test_herobook.php
node scripts/testing/test_herobook_season_client.js
```

O primeiro verifica catálogo, pools, limites temporais, todos os intervalos
do sorteio dos nove tipos de prémio e o contrato cliente/backend de pontos 1/5
nos 80 temas, através da resposta real de `initGame`, sem MySQL.
O segundo exige BD, usa rollback e verifica geração sem duplicados, progresso,
resgate, pontos da temporada sem duplicação, repetição, troca,
conclusão paga/saldo insuficiente e renovação de vagas expiradas. Inclui rotação
para `beetle`/`bear`, resgates diários/semanais e descarte sem pontos.
O teste Node executa `eq.refresh`, `ea.fromSeasonPoints` e getters reais do
bundle com componentes gráficos simulados, verificando os cinco cartões em
todos os temas e ocultação dos pontos quando o passe está inativo.
O utilizador confirmou o funcionamento após a correção dos prémios e dos pontos
da temporada. Essa confirmação não cobre individualmente os nove tipos de prémio
nem todos os cenários de falha. Integração MySQL automatizada passou com
rollback após a correção BUG-000144; confirmação visual dessa correção pendente.

Para testar no jogo, recarregar com herói nível 40+, abrir as chamadas e executar
a atividade indicada. Não é preciso resetar a season. Se a instalação antiga
não tiver a tabela, é necessário criá-la com o esquema acima antes de usar.

## Pendências

Restantes atividades do catálogo, bónus diário por três chamadas, regras oficiais
de escala das metas e limites adicionais de troca. Não são anunciados como implementados.
