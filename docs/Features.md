# FEATURES & IMPLEMENTAÇÕES — HeroZero Server
# Correções de bugs maiores e implementações de funcionalidades novas.
# Para bugs pontuais ver: bugs_encontrados.md

----------------------------------------------------------------------------
## FEAT-001 (claude opus 4.6) — Sistema de tempo do servidor (GameTime)

[Descrição]
Implementação de um relógio de jogo centralizado que substitui todas as
chamadas a `time()` do PHP por `GameTime::now()`. Permite:
- Contador de dias do servidor baseado em uptime acumulado (só conta
  enquanto o servidor está a correr)
- Offset de debug para avançar/recuar o tempo sem esperar
- Relógio visível na barra do jogo (hora + dia do servidor)

[Componentes]
1. `GameTime.php` — classe central
   - `now()` → timestamp com offset de debug
   - `offset()` / `setOffset()` / `addDays()` / `addHours()` / `reset()`
   - `uptime()` → segundos acumulados de server-up (sessões anteriores + atual)
   - `gameDay()` → dia do servidor (1-based, avança a cada 24h de uptime)

2. `start.sh` — preserva/cria o epoch inicial e arranca a stack.
3. `scripts/setup/game_clock.php` / `ActiveGameClock.php` — processo supervisionado
   pelo entrypoint do container do jogo; acumula `hrtime(true)` em checkpoints
   atómicos `.run/game_clock.json`. Parar o container congela o tempo, mesmo sem
   `stop.sh`. Reiniciar não soma o tempo desligado. A data civil do host não
   participa no cálculo. `stop.sh` limita-se a parar os serviços.
4. `/servertime` endpoint (GET/POST) — consultar e manipular tempo
   - GET: retorna server_time, game_day, uptime, offset, formatted
   - POST add_days=N / add_hours=N / reset=1: altera offset de debug

5. `index.html` — relógio na topbar
   - Linha 1 (grande): hora do servidor (fuso Europe/Lisbon)
   - Linha 2 (pequena): "Dia X · dd/mm/yyyy"
   - Sincroniza com `/servertime` a cada 60s; interpolação com `performance.now()`
     independente de mudanças da hora do PC, limitada até à próxima sincronização.

[Ficheiros alterados]
- server-laravel/app/HeroZero/GameTime.php (NOVO)
- server-laravel/app/HeroZero/Live.php (gameNow usa offset)
- server-laravel/routes/web.php (endpoint /servertime)
- server-laravel/bootstrap/app.php (CSRF exception)
- server-laravel/public/index.html (relógio na topbar)
- start.sh / stop.sh (uptime tracking)
- Todos os ficheiros com time() → GameTime::now() (~36 ficheiros)

----------------------------------------------------------------------------

## FEAT-002 (2026-10-04 GPT6.1 SOL) — Login diário e eventos de Dias de Bónus

[Descrição]
Login diário com prémios persistentes, catálogo de 30 dias das constantes,
contador contínuo, resgates atómicos, previews de épicos e recursos em cupões.
Eventos globais agendáveis/canceláveis no admin em «Dias de Bónus»: 1–365 dias,
×2–×5, XP/moedas de missões, energia de missões, unidades e energia de treino.
Calendário e concessões sincronizados com GameTime e o fuso do jogo.

[Regras locais]
Escalas de moedas/XP/recursos, offset do épico, repetição após dia 30 e ausência
de reset por faltar um dia estão documentados; não são fórmulas oficiais confirmadas.

[Validação]
Offline PHP e funções reais do JavaScript; integração MySQL com rollback para
resgates, inventário, moedas/XP de missão, recursos, eventos, login real e estado
normal. Admin: middleware, página, criação/sobreposição/cancelamento com rollback.
Regressão da geração de missões (offline/PDO simulado) e contratos de boot passou.
Teste antigo de troféus apresenta duas expectativas de +5 na energia de treino;
o código actual atribui o troféu às unidades de treino (não alterado nesta funcionalidade).
Confirmação visual no browser pendente.

[Documentação]
- docs/logins/login_diario_e_bonus.md
- scripts/testing/migrate_daily_bonus.php
- scripts/testing/test_daily_bonus.php
- scripts/testing/test_daily_bonus_client.js
- scripts/testing/test_bonus_admin.php

----------------------------------------------------------------------------
## FEAT-003 (2026-10-04 GPT6.1 SOL) — Casino agendável: três máquinas e catálogos de eventos

[Descrição]
Slots clássicos, roda da sorte e slots Spine, com 33 catálogos capturados e
um catálogo local de aparências. Agendamento global pelo GameTime, sem eventos
ativos por defeito; script para listar, ligar 1–365 dias, adiar e cancelar.
Giros/lotes pagos e resgates atómicos, contagem diária sem limite local, previews, equipamento,
pets, aparências, melhorias, moedas/XP/atributos, cupões e histórico HTTP.
Resultados pagos sobrevivem ao fim/cancelamento; duplicados são rejeitados.
Equipamento recebe borda dourada warforged (+15); os tipos 1–7 e os pets
recebem espaço de modificação livre, já visível no preview.
Prémios base: energéticos de missões/habilidades/trabalho de 50% por 3 e 14
dias, energia 10 e 60, treino 4 e 40, com todas as variantes no catálogo.
Três símbolos iguais garantem a variante máxima (14 dias/60 energias/40 unidades);
qualidades 1/2 entregam a inferior (3 dias/10 energias/4 unidades).

[Regras locais]
Probabilidades, qualidades, efeito da sorte, atributos gerados e fórmulas dos
outros prémios são balanceamento local documentado. Variantes base de energia,
treino e energéticos e a regra de três símbolos iguais foram confirmadas pelo
utilizador (BUG-000145/146). Custos, nível mínimo, tipos e listas de
prémios vêm do bundle/constantes/configuração capturada. O limite oficial de
30 foi substituído localmente por `Casino::MAX_DAILY_SPINS=0` (ilimitado),
partilhado pelo backend/cliente. A contagem diária mantém o reset, sem impedir
lotes de cinco/dez giros. Borda +15 e espaço gratuito são regras locais;
aplicam-se também ao resgate de resultados pendentes, sem alterar os já resgatados.
Mísseis recebem borda, mas não suportam modificação; gemas não recebem borda
warforged nem espaço. Pets mantêm a raridade épica e recebem espaço gratuito.
Símbolos 13–18 sem
pools confirmados não são sorteados. A entrega de aparências desbloqueia-as;
o sistema de seleção de aparências é uma funcionalidade separada.

[Validação]
Offline e funções reais do JavaScript com gráficos simulados. MySQL com
rollback: 34 catálogos × três máquinas, janelas, custos, limites, 12 classes
de prémios, inventário cheio, duplicados, cancelamento, login e estado normal.
Inclui mais de 99999 giros, lotes de cinco/dez no cliente, campos da borda/espaço
nos previews e aplicação real de gemas ao equipamento compatível e ao pet.
As dez variantes de prémios base são verificadas na entrega e utilização
dos cupões pessoais, preservando tipo, percentagem, duração e quantidade.
Não altera o relógio persistente nem deixa eventos ativos. Confirmação visual
no browser pendente.

[Documentação]
- docs/casino/README.md
- docs/modificações/implementacao.md
- docs/shop_items/modificacoes.md
- docs/sidekicks/modificacoes.md
- scripts/testing/casino_event.php
- scripts/testing/migrate_casino.php
- scripts/testing/test_casino.php
- scripts/testing/test_casino_client.js

---------------------------------------------------------------------------

## FEAT-004 (2026-10-07) — HeroCon de equipa e individual

[Descrição]
Rotação semanal de 13 temas, excluindo missões especiais. Contribuições reais
das atividades, pontos diretos, classificações, resultados e resgates atómicos.
Booster de equipa, habilidades, caixas individuais e pontos de temporada por
classificação. Login e respostas parciais incluem o estado e as contribuições.
Regras de calendário, participação e prémios pendentes são locais
e estão explicitadas em [docs/herocons/overview.md](herocons/overview.md).

[Componentes]
- `server-laravel/app/HeroZero/HeroCon.php`, `HeroConBox.php` e `actions/herocon.php`.
- `server-laravel/docker/initdb/15_herocon.sql`.
- `scripts/testing/migrate_herocon.php`: migração idempotente aplicada.
- Hooks nos produtores de missões, vendas, duelos, habilidades,
  treinos, vilões, acompanhantes e esconderijo.

[Validação]
Sintaxe PHP, offline, funções reais do cliente e integração MySQL com rollback.
Inclui os 13 temas ativos, produtores reais, fecho, previews, mochila cheia,
descarte e resgates duplicados. Confirmação visual no navegador pendente.

[Documentação]
- [Overview HeroCons](herocons/overview.md).
- `docs/implementar/herocon.md`.
- `scripts/testing/test_herocon.php`.
- `scripts/testing/test_herocon_client.js`.



----------------------------------------------------------------------------
## FEAT-005 (2026-10-09) — Academia Heroica

[Descrição]
Calendário global das seis variantes e progresso individual da Academia Heroica.
Inclui construção de salas, elevador e cofre, regiões, gestores, rendimento,
objetivos, inventário e prémios. A migração não inicia eventos.

[Componentes]
- `server-laravel/app/HeroZero/TowerEvents.php` e `TowerEconomy.php`.
- `server-laravel/actions/towerEvents.php` e rotas no `Router.php`.
- `server-laravel/docker/initdb/17_tower_events.sql`.
- Ponte cliente para aplicar estado autoritativo e cache do bundle atualizado.
- Scripts de migração, arranque global, entrada por nome, cancelamento e testes em `scripts/testing/`; operações administrativas suportam simulação.

[Validação]
PHP lint, Node syntax, teste offline, contrato JavaScript com funções reais do
bundle e MySQL 8.4 com rollback. Cobrem as seis variantes, todos os prémios,
equipamento lendário dourado, progresso forjado, mochila, cooldowns e duplicados.
Confirmação visual manual no navegador pendente.

[Documentação]
- `docs/tower_events_implementacao.md`.
- `docs/shop_items/geracao_itens.md`.
