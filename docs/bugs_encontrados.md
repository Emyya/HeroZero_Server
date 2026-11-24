# BUGS ENCONTRADOS — HeroZero Server

Os bugs relacionados com os bots estão documentados em [bots/bugs.md](../bots/bugs.md).
Os IDs originais são mantidos e partilham a numeração com este registo.

# Formato de numeracao: BUG-00000X. Cabecalho = sintoma | causa | correcao | ficheiro.
# Mapa completo de funcoes e pastas: ver `mapping.txt`.

----------------------------------------------------------------------------
## BUG-000001 — Tutorial preso: flags de progresso nunca derivadas pelo servidor
[Sintoma]
Apos a 1a missao, a seta do tutorial ficava presa na missao e nunca ia p/ a loja.

[Causa]
O cliente HTML5 (build 257) avanca o tutorial lendo `character.tutorial_flags`.
Flag de PROGRESSO que o cliente NAO envia (sao o servidor que as deriva):
  - `first_mission`   (missao concluida)
  - `stats_spent`     (pontos de atributo gastos)
  - `first_item`      (comprou o 1o item)
  - `first_duel`      (ganhou o 1o duelo)
O cliente SO envia via `setTutorialFlags`:
  mission_shown, first_mission_opened, first_mission_started, shop_shown,
  duel_shown, tutorial_finished, first_visit, etc.
Sem `first_mission`, a seta ficava presa na missao.

[Correcao]
- `Character::setTutorialFlag($key, $value)` (novo helper) marca 1 flag persistida.
- `Character::claimQuestRewards()`      -> marca `first_mission`
- `Character::buyShopItem()`            -> marca `first_item`
- `Character::improveStat()`            -> marca `stats_spent`
- `actions/claimDuelRewards.php`        -> marca `first_duel`

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (const TUTORIAL_DONE_FLAGS ~L101;
  setTutorialFlag ~L463; claimQuestRewards ~L1360; buyShopItem ~L980; improveStat ~L2160)
- server-laravel/actions/claimDuelRewards.php

----------------------------------------------------------------------------
## BUG-000002 — setTutorialFlags devolvia resposta sem `character`
[Sintoma]
O cliente enviava `setTutorialFlags` (first_visit/mission_shown/...) mas nunca via
as flags de volta (updateData sem character -> tutorial_flags nao atualizava).

[Causa]
`setTutorialFlags.php` devolvia `Live::overlayAccount(Live::template('setTutorialFlags'))`.
Nao ha captura de `setTutorialFlags`, logo o template era VAZIO -> resposta sem
`character`. O cliente so marca a flag em memoria quando a resposta atualiza o
character; sem character ficava sempre na mesma etapa.

[Correcao]
`setTutorialFlags.php` passa a devolver `Live::accountState($userId)` (conta
completa com `character.tutorial_flags` atualizado).

[Ficheiro]
- server-laravel/actions/setTutorialFlags.php
- server-laravel/app/HeroZero/Live.php (accountState ~L298)

----------------------------------------------------------------------------
## BUG-000003 — Icone da loja/mercado nao aparece (cliente preso no nivel 1)
[Sintoma]
Apos concluir a 1a missao o item ja aparece na loja, mas o ICONE/mercado nao
aparece / fica cinzento.

[Causa]
O cliente so habilita o botao da loja com (refreshMainNavigationButtons):
    enabled = stats_spent
           || (level==2 && !hasAvailableStatPoints)
           || level>2
           || tutorialFinished
As nossas missoes dao pouquissimo XP (placeholder `{"xp":43}`); o novato cumpria
a missao e ficava em NIVEL 1 (nivel 2 exige 547 xp) -> loja trancada. No jogo
original a 1a missao leva ao nivel 2 (+ os 2 pontos de atributo).

[Correcao]
`Character::claimQuestRewards()`: ao concluir a 1a missao (first_mission), se ainda
abaixo do nivel 2, promove o XP ate ao limiar do nivel 2 e chama `syncLevelFromXp()`
(sobe a 2 e concede os 2 pontos de atributo). Ordem: setTutorialFlag('first_mission')
-> promocao de nivel -> so depois calcular $newLevel p/ o pool de quests.

[Sequencia correta do tutorial]
  missao (first_mission) -> nivel 2 + 2 pontos -> gastar stats (stats_spent)
  -> botao loja DESBLOQUEIA -> comprar item (first_item) -> duelo (first_duel) -> fim.

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (claimQuestRewards)

----------------------------------------------------------------------------
## BUG-000004 — Duelo: resposta malformada ("conta nao existe") + duelo preso
[Sintoma]
Na lista de duelo apareciam oponentes (NPCs) mas atacar dava erro ("conta nao
existe"), o jogo pedia reload, e depois atacar dizia "ainda estou num duelo".

[Causa]
1) `startDuel.php` usava `Live::template('startDuel')`, que NAO existe (sem
   captura) -> template VAZIO. A resposta vinha com `duel`/`battle`/`opponent`/
   `character` RASOS/malformados -> o painel de batalha do cliente crashava
   (falso "conta nao existe") e pedia reload.
2) O duelo era criado (`active_duel_id` setado) mas o cliente crashava antes de o
   concluir/reivindicar -> `active_duel_id` ficava preso para sempre -> ataque
   seguinte devolvia `errStartDuelActiveDuelFound` ("ainda estou num duelo").

[Correcao]
`startDuel.php`:
- Base da resposta = template do BOOT (`autoLoginUser`) -> shape completa de
  character, user, inventory, items, duel, battle e opponent.
- Valida o oponente: se `character_id` nao for um NPC valido (user_id=0 existente)
  -> `errStartDuelInvalidEnemy` (erro limpo, sem criar duelo orfao).
- Se `active_duel_id` aponta para um duelo ja reivindicado (status 3) ou inexistente,
  limpa-o para nao bloquear novos ataques.

[Ficheiro]
- server-laravel/actions/startDuel.php

----------------------------------------------------------------------------
## BUG-000005 — Batalha do duelo: lutadores com "LOC" + barra errada + crash/reload
[Sintoma]
Ao duelar, a pagina da batalha abre mas ambos os lutadores aparecem com as
habilidades a mostrar "LOC", a barra de combate caia de 100 para 10 (em vez de
-20/combate), e era dado erro a pedir reload. Depois atacar dizia "ainda estou
em combate" (active_duel_id preso).

[Causa]
O bloco `rounds` da battle (JSON em `battle.rounds`) NAO incluia
`profile_a_appearance` / `profile_b_appearance`. O cliente (`Battle.calculateRounds`)
le estas chaves para montar a aparencia dos lutadores; sem elas o painel de
batalha nao renderizava os lutadores (o "LOC") e crashava -> reload -> duelo
nunca concluido -> "ainda estou em combate".

[Correcao]
`startDuel.php`:
- Gera rounds REALISTAS com vencedor coerente (o jogador ganha): o jogador
  esgota o oponente (~esgota os hitpoints do oponente), o oponente deixa o
  jogador em ~80%.
- Adiciona `profile_a_appearance`/`profile_b_appearance` ao JSON de rounds,
  montados a partir da aparencia real dos dois personagens.
- Novo metodo `Character::battleAppearance()` que devolve o bloco de aparencia.

[Ficheiros]
- server-laravel/actions/startDuel.php
- server-laravel/app/HeroZero/Character.php (battleAppearance)

----------------------------------------------------------------------------
## BUG-000004 (ADDENDUM) — Duelo preso: active_duel_id nunca limpo + status errado

[Causa adicional descoberta em teste]
1) O check de duelo preso (`active_duel_id != 0`) so limpava se status=3 ou duelo
   inexistente. Um duelo com status=1 (iniciado, nunca reivindicado — ex: reload
   antes de claimDuelRewards) ficava preso para sempre sem trigger de limpeza.
2) `createNew()` criava personagens com duel_stamina=10/max=10 em vez de 100/100.
3) `DUEL_STAMINA_COST_PER_ATTACK=1` errado (devia ser 20, igual duel_stamina_cost
   do template). O "max observado de 10" era o valor CORRENTE esgotado, nao o max.
4) CAUSA RAIZ do "LOC" + crash: o template `autoLoginUser.json` tem `battles[]`
   (array de batalhas do skelletonx) e a resposta do startDuel herdava-o.
   O cliente, ao ver `battles` presente, prefere `getBattleFromId(battles,
   duel.battle_id)` em vez de ler `battle`. O id da nossa batalha nao existe
   nesse array antigo -> devolve null -> `refreshBattle(null)` -> `_battle=null`
   -> `TypeError: get_profileB() is undefined` -> painel crasha, pede reload e
   o duelo fica preso. (Este era o bug real; a hipotese do
   profile_a/b_appearance do BUG-000005 nao era suficiente sozinha.)

[Correcao adicional]
- `startDuel.php`:
    - Resume path: se active_duel_id aponta para status=1 ou status=2 (nao
      reivindicado), retorna os dados do duelo/batalha existente em vez de errar
      com errStartDuelActiveDuelFound.
    - `unset($data['battles'])` em ambos os paths (resume + novo) para o cliente
      cair no ramo `hasData("battle") && a.refreshBattle(data.battle)`.
    - `DUEL_STAMINA_COST_PER_ATTACK = 20` (corrigido de 1).
- `Character::createNew()`: duel_stamina=100, max_duel_stamina=100.

[ATENCAO — character_a_status controla a ANIMACAO, nao "estar pronto"]
Houve uma tentativa de criar o duelo ja com `character_a_status=2`. ERRADO.
O cliente (`ak.handleMessages`, ViewMessage.notifyBattleFinished) faz:
    status == 1 -> toca a animacao da luta, depois envia `checkForDuelComplete`
    status == 2 -> SALTA a animacao e abre logo o dialogo de recompensa (Lw)
Com status=2 a resposta do startDuel fazia o jogo mostrar moedas/honra sem
nunca exibir a luta. O duelo TEM de nascer com status=1; e o
`checkForDuelComplete` que o promove a 2.

[Ficheiro]
- server-laravel/actions/startDuel.php
- server-laravel/app/HeroZero/Character.php (createNew)

----------------------------------------------------------------------------
## BUG-000006 — Botao de continuar ausente ao voltar ao painel de duelos

[Sintoma]
Apos um duelo, se o jogador navegar para outra seccao (heroi, loja, etc) e voltar
aos duelos, a animacao de batalha e exibida mas o botao de continuar (claim rewards)
nunca aparece — fica preso na pagina de animacao.

[Fluxo pretendido]
  clicar lutar -> startDuel (duelo nasce status=1) -> painel `battle` com a
  animacao (+ botao de skip, ja nativo no cliente) -> no fim/skip o cliente
  dispara notifyBattleFinished -> `checkForDuelComplete` (status 1 -> 2) ->
  dialogo Lw com moedas/honra + botao continuar -> `claimDuelRewards`
  (status -> 3, active_duel_id -> 0).

[Causa]
1) `checkForDuelComplete` devolvia `duel` mas NAO `battle`. O cliente cria
   `new Lw(activeDuel)` e o `Lw.onShow` chama
   `duel.get_battle().get_profileAIsWinner()` — com `_battle` null isso rebenta
   em silencio e o botao de continuar nunca e renderizado.
2) `Live::accountState()` (usado por syncGame/boot) nunca incluia `duel`/`battle`.
   Apos um sync ou reload a meio do duelo, `_duel` ficava null,
   `get_activeDuel()` devolvia null e o duelo desaparecia do cliente.

[Correcao]
- `checkForDuelComplete.php`: inclui `battle` (e faz `unset($data['battles'])`),
  usando o `autoLoginUser` como template de shape para duel/battle/user.
- `Live::accountState()`: quando `character.active_duel_id > 0`, anexa
  `duel` + `battle` + `opponent` do banco e remove `battles[]`.

[Ficheiros]
- server-laravel/actions/checkForDuelComplete.php
- server-laravel/app/HeroZero/Live.php (accountState)

----------------------------------------------------------------------------
## BUG-000007 — Recompensa do duelo fixa (popup mostrava sempre 10 de honra)

[Sintoma]
Passando o rato por cima de um oponente, o tooltip prometia (p.ex.) +198 honra
ao atacar AlineeSantos. Mas no fim da batalha o popup de resultado dava sempre
5 moedas e 10 de honra, qualquer que fosse o oponente.

[Causa]
`startDuel.php` gravava as recompensas em texto FIXO:
    character_a_rewards = '{"coins":5,"honor":10}'
    character_b_rewards = '{"coins":1,"honor":-1}'
O popup (`Lw`) le `duel.character_a_rewards` — dai o 10 constante. Ja o tooltip
da lista NAO consulta o servidor: calcula localmente com
`Q.duelHonorWinReward()` a partir das constantes de balanceamento. Ou seja, os
dois numeros vinham de fontes diferentes e nunca podiam bater certo.

[Correcao]
Novo `HeroZero\DuelRewards` — porte 1:1 das formulas do cliente (GameUtil):
    ganho  = duelHonorWinReward(MINHA honra, honra do OPONENTE)
    perda  = duelHonorLostReward(honra do OPONENTE, MINHA honra)   <- invertido
    moedas = duelGameCurrencyWinReward(NIVEL do oponente)
`startDuel.php` passa a gravar o resultado destas formulas. Como o tooltip e o
servidor usam agora a MESMA formula e as MESMAS constantes, os valores coincidem.
`claimDuelRewards.php`: honra com piso 0 (GREATEST) — o bloco do perdedor e
negativo e a honra podia ficar abaixo de zero.
`Character`: novos acessores `level()` e `honor()`.

[Constantes oficiais — onde as ir buscar]
Os valores vivem em `T.current` no cliente, carregado do CDN oficial:
    https://hz-static-2.akamaized.net/assets/data/constants_json.data
E um blob ZLIB -> JSON com 620 chaves. ATENCAO ao caminho: e `assets/data/...`;
`assets/html5/data/...` devolve 404 (perdi bastante tempo nisso). Descomprimir:
    python3 -c "import zlib,json;print(json.load(...))"  # zlib.decompress
Os 494 valores escalares ficaram guardados em
`server-laravel/data/reference/balancing_constants.json` (fora de `data/` raiz de
proposito: `Replay::has()` mapeia `data/<action>.json` e um fixture com nome de
action era resolvido como resposta).
Valores usados: pvp_honor_win_exp_better=3, pvp_honor_win_exp_worse=3,
pvp_honor_lose_amount=0.75, pvp_honor_lose_max=0.05, pvp_waiting_time=600,
pvp_effectiveness_won=0.3, coins_per_time_base=0.02, coins_per_time_scale=0.01,
coins_per_time_level_scale=0.35, coins_per_time_level_exp=1.55.

[Ficheiros]
- server-laravel/app/HeroZero/DuelRewards.php  (novo)
- server-laravel/actions/startDuel.php
- server-laravel/actions/claimDuelRewards.php
- server-laravel/app/HeroZero/Character.php (level/honor)
- server-laravel/data/reference/balancing_constants.json (novo, referencia)

----------------------------------------------------------------------------
## BUG-000008 — Insta-skip da missao nao mostrava a batalha

[Sintoma]
Ao fazer instant finish numa missao de luta, a batalha nao era exibida. So
mudando de seccao e voltando as missoes e que o jogo mostrava a luta.

[Causa]
`instantFinishQuest.php` so devolvia `quest.ts_complete = 0`. Nao concluia a
missao nem devolvia `status`/`fight_battle_id`/`battle`. O cliente ficava a
espera de um `checkForQuestComplete`, que so era disparado quando o painel de
missoes era reaberto — dai a batalha aparecer apenas ao voltar a seccao.
(Mesma familia do BUG-000006 nos duelos: a accao que muda o estado tem de
devolver o `battle` na propria resposta.)

[Correcao]
`instantFinishQuest.php` chama `checkActiveQuestComplete()` logo a seguir ao
instant finish e devolve `status`, `fight_battle_id` e `battle` na mesma
resposta (sem `battles[]`).

[Ficheiro]
- server-laravel/actions/instantFinishQuest.php

----------------------------------------------------------------------------
## BUG-000009 — XP das missoes praticamente parado

[Sintoma]
O XP dado pelas missoes parecia sempre o mesmo e a barra quase nao andava.

[Causa]
As recompensas eram os valores CAPTURADOS de uma conta nivel 1
(`'{"coins":2,"xp":43}'`) multiplicados por um fator aproximado
(`scaleQuestRewards`/`xpRewardFactor`). Como o fator dependia so do nivel e as 3
missoes do pool partiam da mesma base, os valores saiam quase iguais entre si e
minusculos face a curva oficial (nivel 2->3 exige 911 xp; o ciclo dava ~250).

[Correcao — formula OFICIAL, portada do cliente]
Novo `HeroZero\QuestRewards`, porte de `Q.questXpReward`:
    if (nivel == 1) return xp do nivel 2;          // a 1a missao sobe sempre p/ o 2
    v = duracao_seg * xp_per_time * escala_tipo * mult;
    if (nivel < quest_reward_xp_adjust_end_level)  // 4
        v = (nivel == 2) ? v * nivel * start_pct   // start_pct = 2
                         : v * (start_pct - nivel/end_level * (start_pct - 1));
    return round(v * (1 + bonus));
Constantes: xp_per_time=1.5, adjust_end_level=4, adjust_start_percentage=2,
escalas xp 1/1.4/0.6/0.2 (balanced/xp_heavy/coin_heavy/item).
O cliente NAO tem formula de moedas (e so do servidor); usamos a mesma curva
`gameCurrencyPerTime` dos duelos, com as escalas de moeda 1/0.2/1.8/0.2.
`Character::insertQuestPool()` passa a calcular a partir de `duration_raw` e do
nivel, em vez de escalar os valores capturados.
Novo `Character::xpForLevel()`.

[Nota] A formula NAO cresce com o nivel acima do 4 — depende da DURACAO. No jogo
real os stages mais altos tem missoes mais longas. Como so temos templates de
stage 1 e 2 capturados, niveis altos reutilizam o stage 2 e por isso repetem
valores: isso e falta de conteudo, nao da formula.

[Efeito colateral] O empurrao manual de xp para o nivel 2 em
`claimQuestRewards()` (BUG-000003) ficou redundante — o caso `nivel 1` da formula
ja devolve exatamente o xp do nivel 2. Ficou como rede de seguranca para contas
com quests antigas ja gravadas no banco.

[Ficheiros]
- server-laravel/app/HeroZero/QuestRewards.php  (novo)
- server-laravel/app/HeroZero/Character.php (insertQuestPool, xpForLevel)

----------------------------------------------------------------------------
## BUG-000010 — Duelo ja reivindicado reaparecia ao voltar ao painel

[Sintoma]
Duelar funcionava (luta + recompensas corretas), mas ao mudar de seccao e voltar
aos duelos o jogo reexibia a ULTIMA luta ja concluida.

[Causa]
No banco estava tudo certo (`active_duel_id = 0`, duelo com status 3) — o estado
preso era no CLIENTE. `claimDuelRewards.php` montava a resposta assim:

    $data = Live::template('claimDuelRewards');
    if (isset($data['character'])) { $data['character'] = ...overlayCharacter... }

Nao existe `data/claimDuelRewards.json`, portanto o template vinha VAZIO e o
`isset()` era sempre falso: a resposta saia SEM `character`. O cliente nunca
recebia `active_duel_id = 0`, mantinha o duelo em memoria e `get_activeDuel()`
continuava a devolve-lo — reexibindo a luta antiga.

[Correcao]
`claimDuelRewards.php` monta a resposta a partir do template do BOOT
(`autoLoginUser`), garantindo `user` + `character` (com active_duel_id=0) sempre
presentes, e `duel` quando existe.

[PADRAO A EVITAR — a mesma armadilha do BUG-000002/4]
    $data = Live::template('<accao sem fixture>');
    if (isset($data['x'])) $data['x'] = ...;     // NUNCA executa -> campo some
Nao ha fixture para: checkForDuelComplete, checkForQuestComplete,
claimDuelRewards, claimQuestRewards, collectGoalReward,
createPrivateConversation, getCharacter, instantFinishQuest,
setCharacterAppearance, setUserSettings, startDuel, startQuest.
Para estas, usar SEMPRE o fallback explicito:
    $tpl['user'] ?? $boot['user'] ?? []
(as restantes actions ja seguem este padrao; so o claimDuelRewards e que nao).

REGRA: toda accao que MUDA estado tem de devolver `character`. O cliente so
atualiza o que a resposta traz — um campo omitido fica congelado em memoria.

[Ficheiro]
- server-laravel/actions/claimDuelRewards.php

----------------------------------------------------------------------------
## BUG-000011 — Barra de stamina de duelo enchia a olhos vistos

[Sintoma]
A barra reenchia ~3 pontos por segundo ate 20 e depois ~1 por segundo ate 100,
em vez de seguir o ritmo real de regeneracao.

[Causa]
O servidor NUNCA regenerava `duel_stamina` nem escrevia
`ts_last_duel_stamina_change` (estava a 0 = epoch 1970; a unica escrita no repo
inteiro estava em energyTraining.php).
O cliente nao recebe a stamina "ao vivo": extrapola-a com
    valor_mostrado = duel_stamina + _duelStaminaCorrectionValue
e, em `get_secondsToNextDuelStaminaRefill()` (chamado a cada tick do timer),
soma +1 a essa correcao sempre que `now - ts_last_duel_stamina_change` passa do
intervalo. Com ts=0 havia ~56 ANOS de atraso, entao somava +1 por tick a tentar
recuperar — dai o enchimento acelerado. Os "3/seg" vs "1/seg" sao so os dois
intervalos diferentes (30s abaixo de 20, 180s acima), nao dois bugs.

[Correcao]
Novo `Character::syncDuelStamina()` (lazy, corre no overlayCharacter e no inicio
do startDuel):
- credita os pontos ganhos desde `ts_last_duel_stamina_change`, alternando o
  intervalo em 20 tal como o cliente faz (`get_isFirstDuel()`);
- avanca `ts` pelos intervalos JA consumidos (nao salta para `now`), senao a
  fracao de progresso em curso era descartada a cada request;
- `ts <= 0` (conta antiga) comeca a contar agora, em vez de creditar decadas;
- barra cheia => `ts = now` (relogio parado).
`startDuel.php`: sincroniza antes de validar o custo (senao dava
errRemoveDuelStaminaNotEnough com stamina ja regenerada) e, se a barra estava
cheia ao gastar, arranca o relogio.

[Ritmo oficial vs. esperado — DECISAO EM ABERTO]
As constantes oficiais (data/reference/balancing_constants.json) dizem:
    duel_stamina_refresh_amount_per_minute            = 0.333334  -> 180 s/ponto
    duel_stamina_refresh_amount_per_minute_first_duel = 2         ->  30 s/ponto
O cliente troca de ritmo em `stamina < duel_stamina_cost` (20). Logo:
    0 -> 20   (1o duelo):  10 min      <- bate com o esperado
    20 -> 100 (restantes): ~4 h        <- NAO e 2%/min continuo
Um ritmo continuo de 2%/min (barra cheia em 50 min) exigiria
`duel_stamina_refresh_amount_per_minute = 2`. Implementei o OFICIAL porque o
cliente usa estes mesmos numeros para o contador regressivo: mudar so o servidor
poe os dois outra vez a discordar (a mesma classe do BUG-000007). Para o ritmo
continuo basta pôr DUEL_STAMINA_SECONDS_PER_POINT = 30 em Character.php.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (syncDuelStamina + constantes)
- server-laravel/actions/startDuel.php

----------------------------------------------------------------------------
## BUG-000012 — Missoes sempre as mesmas

[Sintoma]
O pool de missoes repetia sempre as mesmas 3 missoes, ciclo apos ciclo.

[Causa]
`Character::QUEST_TEMPLATES` tinha 3 templates HARDCODED para o stage 1 e 2 para
o stage 2 — os unicos identificadores conhecidos das capturas. `insertQuestPool`
sorteava 3 de um conjunto de 3: saia sempre o mesmo. Nao era aleatoriedade
partida, era falta de catalogo.
(Ver tambem a nota do BUG-000009: como so havia stage 1 e 2, niveis altos
reutilizavam o stage 2 e repetiam tambem as recompensas.)

[Correcao]
O catalogo oficial estava no mesmo asset do CDN ja usado para as constantes:
`constants.quest_templates` — 565 entradas, das quais 454 seguem o padrao
`quest_stage<N>_(fight|time)<n>`, cobrindo 14 stages. Mais `constants.npcs`
(270; 107 do tipo 1 = inimigos de missao).
Guardado em `server-laravel/data/reference/quest_templates.json`:
    {"quests": {"<stage>": {"fight": [...], "time": [...]}}, "npcs": [...]}
Cobertura por stage (fight/time): 1=27/16, 2=20/16, 3=18/16, 4=16/16, 5=14/15,
6=8/23, 7=12/20, 8=12/20, 9=10/20, 10=10/20, 11=15/30, 12=10/20, 13=12/15,
14=11/12.

`Character::insertQuestPool()` passa a sortear do catalogo INTEIRO do stage,
misturando ~1 luta para cada 2 de tempo, com NPC aleatorio nas de luta.

[Duracoes — derivadas, nao oficiais]
O asset do CDN so traz os IDENTIFICADORES (duracao/energia/npc sao server-side no
jogo oficial e nao estao expostos). `questDuration()` deriva a duracao das duas
capturas que temos (stage 1: 300-660s; stage 2: 240-1260s), crescendo 60% por
stage. Se algum dia aparecer captura com as duracoes reais, e so substituir esta
funcao.

ATENCAO: a duracao tem de ser SORTEADA, nao derivada do indice do slot. Na 1a
tentativa ela saia de `$slot` (0->240s, 1->420s, 2->540s): os identificadores
mudavam mas as 3 duracoes eram sempre as mesmas e, como a recompensa vem da
duracao (QuestRewards), o ciclo repetia xp/moedas na mesma — o sintoma parecia
nao ter sido corrigido. Agora e `random_int` em passos de 30s dentro da faixa do
stage.

[NAO INVENTAR IDENTIFICADORES]
O identifier tem de existir na localizacao (`quest/<id>/briefing`); um nome
inventado deixa o texto da missao vazio no cliente. Por isso o sorteio e sempre
sobre o catalogo, e um stage sem catalogo cai no maior stage conhecido.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (questCatalog, questDuration, insertQuestPool)
- server-laravel/data/reference/quest_templates.json (novo)

----------------------------------------------------------------------------
## BUG-000013 — Boosts da guilda ("team") nunca aplicados

[Sintoma]
As instalacoes da guilda que se evoluem de 0 a 50 (atributos, xp de missao,
moedas de missao) nao davam bonus nenhum.

[Causa]
As colunas existiam na tabela `guild` e o cliente ate as mostrava, mas o SERVIDOR
nunca as lia: `stat_total_*`, `battleProfile()` e as recompensas de missao eram
calculados sem qualquer referencia a guilda.

[Formulas — do cliente, DUAS ESCALAS DIFERENTES]
Atributos (`Y.get_guildBoosterStamina` e irmas):
    bonus = ceil(stat_base_X * stat_character_base_stats_boost / 100)
  -> o nivel E a percentagem: 50 = +50%. CEIL (nao round) e so sobre a BASE
     (sem treino nem equipamento).
Moedas de missao (`Y.get_guildBonusQuestWorkGameCurrencyRewardFactor`):
    fator = stat_quest_game_currency_reward_boost / 50
  -> exibido como `100 * fator` por cento: cada nivel vale +2%, 50 = +100%.
CUIDADO: /100 nos atributos mas /50 nas recompensas. Nao uniformizar.

XP de missao: o cliente NAO expoe formula. CONFIRMADO depois no servidor oficial
antigo (Utils.php: randomiseQuest), que usa a mesma escala das moedas:
    $coins *= (1 + ($guild->stat_quest_game_currency_reward_boost * 2) / 100);
    $xp    *= (1 + ($guild->stat_quest_xp_reward_boost * 2) / 100);
`boost*2/100` == `boost/50`, portanto a implementacao estava certa.

[Correcao]
`Character`:
- `guildBoosts()` le as 3 colunas da guilda (cache por instancia);
- `guildStatBonus($stat)` — formula dos atributos;
- `guildQuestXpFactor()` / `guildQuestCoinFactor()` — fatores de recompensa;
- `guildQuestFactorsFor($charId)` para os caminhos estaticos;
- bonus somado em `overlayCharacter()` (stat_total_*) E em `battleProfile()`
  (senao a batalha usava atributos menores do que os mostrados no perfil);
- `insertQuestPool()` aplica os fatores ao gerar a quest — a recompensa fica
  GRAVADA na linha, logo reflete a guilda no momento da criacao da missao.

[Ficheiro]
- server-laravel/app/HeroZero/Character.php

----------------------------------------------------------------------------
## BUG-000014 — Energeticos (boosters) nao aplicavam nada

[Sintoma]
Os energeticos comprados nao davam efeito nenhum em moedas, xp nem atributos.

[Causa]
O servidor lia as colunas `active_*_booster_id` so para as devolver ao cliente.
Nenhum calculo as consultava: `startQuest()` usava a duracao/energia cruas e os
`stat_total_*` nao tinham qualquer multiplicador.

[FONTE: servidor oficial antigo]
/home/laliene/Documentos/merdas docker/herodocker/herozero/server
  class/Player.php   -> calculateStats(), getBoosters()
  class/Utils.php    -> randomiseQuest(), randomiseDungeonQuest()
  request/startQuest.req.php, request/startWork.req.php
E a melhor referencia que temos: PHP legivel, com as formulas server-side que o
cliente nao expoe. Consultar SEMPRE antes de deduzir algo.

[Formulas — cada booster age num sitio DIFERENTE]
1. Booster de ATRIBUTOS (booster_stats*) — Player::calculateStats():
       $boosterVal = 1 + booster.amount/100 + guild.stat_character_base_stats_boost/100
       stat_total_X = ceil(stat_base_X * $boosterVal)   // equipamento somado DEPOIS
   Booster e instalacao da guilda somam num UNICO fator. Com booster 25 e guilda
   50 o correto e ceil(base*1.75), NAO ceil(base*1.25)+ceil(base*0.5).
   O equipamento (e o sidekick) entram fora do multiplicador, por soma.

2. Booster de MISSAO (booster_quest*) — startQuest.req.php:
       boostSum = booster_do_jogador + booster_da_guilda
       val = 1 - boostSum/100
       ts_complete *= val;  energy_cost *= val
   NAO aumenta a recompensa: corta DURACAO e ENERGIA. (Era a suposicao errada —
   vale a pena reter, porque "50%" aqui significa "metade do tempo".)

3. Booster de TRABALHO (booster_work*) — startWork.req.php:
       $coins *= (1 + booster.amount/100);
       $coins *= (1 + guild.stat_quest_game_currency_reward_boost*2/100);
   Este sim multiplica moedas. (Trabalho ainda nao implementado c/ boosters aqui.)

[Correcao]
`Character`:
- `boosterAmount($kind)` / `guildBoosterAmount($kind)` — le a coluna, valida a
  expiracao (`expires <= 0` = permanente) e devolve a % do catalogo do Live;
- `statMultiplier()` + `statBoostBonus($stat)` — formula 1, usada no
  `overlayCharacter()` e no `battleProfile()`;
- `startQuest()` — formula 2, sobre duracao e energia.

[Ficheiro]
- server-laravel/app/HeroZero/Character.php

----------------------------------------------------------------------------
## MUDANCA-000015 — Recompensa de missao 1:1 com o servidor oficial antigo

[FORMULA ANTERIOR — guardada]
Porte de `Q.questXpReward` do CLIENTE, dependia so da duracao:
    if (nivel == 1) return xpForLevel(2);
    v = duracao_seg * 1.5 (xp_per_time) * escala_tipo * mult;
    if (nivel < 4) v = (nivel == 2) ? v * nivel * 2 : v * (2 - nivel/4);
    return round(v * (1 + bonus));
  moedas: DuelRewards::gameCurrencyPerTime(nivel) * duracao * escala.

[FORMULA ATUAL — `Cls\Utils::randomiseQuest()`, copiada 1:1]
    lvl     = nivel do jogador
    dur_raw = mt_rand(min(4, lvl-1), min(lvl+5, 12))        // MINUTOS
    energy  = dur_raw * random(1, 1.3)
    duration = duration_raw = dur_raw * 60;  duration_type = 1

    coins = mt_rand(lvl+2, lvl+5) * (lvl/2)
    xp    = (10 * lvl * dur_raw) + (random(0.5, 1) * 550)
    se luta (20% de chance, `random() < 0.2`):
        dificuldade sorteada 0.6 facil / 0.3 media / 0.1 dificil
        coins *= fight_quest_reward_coin_scale_{easy 1.07|medium 1.35|hard 2.0}
        xp    *= fight_quest_reward_xp_scale_{iguais}
    se tem guilda:
        coins *= 1 + (stat_quest_game_currency_reward_boost * 2)/100
        xp    *= 1 + (stat_quest_xp_reward_boost * 2)/100

[O QUE FOI REMOVIDO — era o que inflacionava o XP]
Numa 1a tentativa acrescentei coisas que NAO existem no oficial:
  - "rolo de distribuicao" (quest_reward_xp_scale_balanced/xp_heavy/coin_heavy):
    essas constantes existem, mas nao sao usadas na geracao da missao;
  - energetico de missao a multiplicar a recompensa (ver abaixo);
  - Sentido Heroico a sortear 4 candidatos e ficar com o melhor;
  - moedas a escalar com energia/6;
  - `duration` = 45% de `duration_raw` (no oficial sao IGUAIS);
  - `duration_type` derivado da duracao (no oficial e sempre 1);
  - caso especial "nivel 1 -> xp do nivel 2" (isso e do cliente). Quem garante o
    nivel 2 no tutorial e a rede de seguranca do claimQuestRewards (BUG-000003).
  - 1 em cada 3 missoes ser de luta (no oficial e 20% aleatorio).

[Energetico de missao — conta UMA vez, no TEMPO]
1:1 com startQuest.req.php: corta duracao e energia, nao mexe na recompensa.
    boostSum = booster_jogador + booster_guilda;  val = 1 - boostSum/100
    ts_complete *= val;  energy_cost *= val
Chegou a estar nos dois sitios ao mesmo tempo (metade do tempo E mais premio).
Se um dia se quiser o contrario, e mover o fator — nunca ter os dois.

[Valores resultantes, missao de tempo sem guilda]
    nivel  1 -> xp  275..597    moedas   2..3
    nivel  2 -> xp  298..677    moedas   4..7
    nivel  5 -> xp  480..1048   moedas  18..25
    nivel 11 -> xp  738..1866   moedas  72..88
    nivel 25 -> xp 1278..3537   moedas 338..375

[Ficheiros]
- server-laravel/app/HeroZero/QuestRewards.php (reescrito 1:1)
- server-laravel/app/HeroZero/Character.php (rollQuest, insertQuestPool,
  questRewardMultipliers, startQuest)

----------------------------------------------------------------------------
## BUG-000016 — Missoes trocavam ao andar entre cidades

[Sintoma]
Sair de uma cidade e voltar mostrava missoes diferentes. So deviam mudar depois
de CONCLUIR uma missao (ou no refresh explicito), nunca por viajar.

[Causa]
`actions/charMisc.php` (setCharacterStage) chamava `regenerateQuests()` a cada
mudanca de cidade. E `regenerateQuests()`/`claimQuestRewards()` faziam
`DELETE FROM quests WHERE character_id = ?` — sem filtro de stage — apagando o
pool de TODAS as cidades e sorteando um novo.

[Como o cliente espera que funcione]
O cliente le `character.quest_pool`, um JSON {"<stage>":[quest_id,...]}:
    Y.get_stageQuestIds() -> getStageQuestIdsForStage(get_currentQuestStage())
Ou seja, cada cidade tem o SEU conjunto de ids, todos coexistindo. O nosso
`Character::questPoolJson()` ja montava isso a partir da tabela (agrupado por
`quests.stage`) — o que faltava era as linhas nao serem apagadas.

[Correcao]
- `setCharacterStage` -> `ensureQuestPool($stage)` (novo): so cria o pool se
  aquela cidade ainda nao tiver nenhum (1a visita). Nunca regenera.
- `regenerateQuests()` -> DELETE/INSERT filtrados por `stage = current_quest_stage`.
- `claimQuestRewards()` -> idem: renova so a cidade onde a missao foi concluida.
- `seedStarterQuests()` -> testa `stage = 1` em vez de "tem alguma quest".

[Ficheiros]
- server-laravel/actions/charMisc.php
- server-laravel/app/HeroZero/Character.php (ensureQuestPool, regenerateQuests,
  claimQuestRewards, seedStarterQuests)

----------------------------------------------------------------------------
## BUG-000017 — O jogador ganhava SEMPRE (duelos e missoes de luta)

[Sintoma]
Nunca se perdia um combate, fosse duelo ou missao de luta.

[Causa]
Nao havia simulacao nenhuma. O resultado estava escrito a mao:
- `startDuel.php`: gravava `winner = 'a'` fixo e 3 rounds fabricados
  (jogador tira 60% + 40% da vida do oponente, oponente devolve 20%).
- `Character::checkActiveQuestComplete()`: `winner = 'a'` fixo e UM round de 10
  de dano.
Os atributos dos dois lados nao entravam na conta — por isso subir stats, mudar
equipamento ou enfrentar um NPC de 500/500/500/500 dava exatamente no mesmo.

[Correcao — porte de `Cls\Fight` do servidor oficial antigo (class/Fight.php)]
Novo `HeroZero\Fight`:
  - sorteia quem comeca (50/50) e alterna golpes ate um lado ficar sem hitpoints;
  - por golpe: esquiva (r=1, sem dano) -> senao dano
    `random(0.8, 1.05) * damage_normal`, com critico (r=3) a multiplicar por
    `battle_critical_factor` = 2.5;
  - `hitpoints = stamina * battle_hp_scale (10)`;
    `damage_normal = forca + dano_de_arma`.
  - chances de critico/esquiva pela razao entre os ratings dos dois lados
    (`Utils::getCriticalHitPercentage` / `getDodgePercentage`), curva:
        razao <= 1 -> pow(razao, exp_low) * (base - min) + min
        razao >  1 -> (1 - pow(1/razao, exp_high)) * (max - base) + base
    critico: min .01 base .02 max 1  exp_low 20   exp_high 1.225
    esquiva: min .01 base .1  max .6 exp_low 5    exp_high 1.65
    (o exp_low 20 do critico penaliza MUITO quem tem rating inferior)
  - trava MAX_ROUNDS = 400: dois lados sem dano nao podem prender o servidor.
  - NAO porta sidekicks nem misseis de guilda (ainda nao existem aqui).

`startDuel.php`: vencedor e rounds saem do Fight; `DuelRewards::forDuel()` passa
a receber `$aWins` — o VENCEDOR leva moedas (pelo nivel do vencido) + honra, o
PERDEDOR leva 0 moedas e a penalizacao de honra (estrutura do `DuelBattle::start()`).

`Character::checkActiveQuestComplete()`: idem para missoes de luta. O NPC vem do
novo `questNpcProfile($difficulty)`, porte de `NPC::randomiseQuestStats` — o
inimigo e uma PERCENTAGEM dos atributos do proprio jogador:
    facil 89-94% | media 93-96% | dificil 97-98.5%
Perder a luta corta a recompensa para 10% (`fight_quest_reward_lost_xp/_coin`),
como no checkForQuestComplete.req.php oficial.
NOTA: nas missoes NAO se envia `profile_*_appearance` — o cliente desenha o
inimigo a partir de `quest.fight_npc_identifier`. O bloco de aparencia e so do
duelo (BUG-000005).

[Verificado] skel 27/27/44/27 (com boosts) em 200 combates:
    vs AlineeSantos 500/500/500/500 -> ganha   0.0%
    vs AlineeSantos 14/19/22/17     -> ganha 100.0%
    vs um clone dele proprio        -> ganha  47.0%

[Ficheiros]
- server-laravel/app/HeroZero/Fight.php (novo)
- server-laravel/actions/startDuel.php
- server-laravel/app/HeroZero/DuelRewards.php (forDuel com $aWins)
- server-laravel/app/HeroZero/Character.php (checkActiveQuestComplete, questNpcProfile)

----------------------------------------------------------------------------
## BUG-000018 — Lista de duelos mostrava so NPCs fake

[Sintoma]
A pagina de duelos listava sempre os mesmos 10 nomes do seed, nunca as contas
realmente registadas.

[Causa]
`Character::duelOpponents()` filtrava `WHERE user_id = 0` — as linhas `character`
com user_id 0 sao os NPCs semeados por docker/initdb/04_seed_opponents.sql, nao
jogadores. `startDuel.php` validava o alvo com o mesmo criterio, portanto ate
recusaria uma conta real (`errStartDuelInvalidEnemy`).

[Correcao]
- `Character::duelOpponents()` -> `WHERE user_id <> 0` (contas registadas).
- `startDuel.php` -> valida `user_id <> 0` em vez de `= 0`.
- Idem na liga, que tinha o mesmo filtro:
  `Live::leagueOpponents()` e `actions/leagueMissed.php`.
- A lista passa a enviar os atributos TOTAIS do oponente (via `battleProfile`,
  ou seja base + treino + equipamento + boosters/guilda) em vez de so a base —
  o tooltip subestimava o adversario, e agora o combate usa mesmo esses valores
  (BUG-000017).

[Nota] Os NPCs (user_id = 0) continuam na base; deixaram apenas de ser sorteados.
Se um dia se quiser voltar a inclui-los como enchimento, e trocar o filtro para
`user_id = 0 OR user_id <> 0` com um limite por origem.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (duelOpponents)
- server-laravel/actions/startDuel.php
- server-laravel/app/HeroZero/Live.php (leagueOpponents)
- server-laravel/actions/leagueMissed.php

----------------------------------------------------------------------------
## BUG-000019 — Perder a luta da missao mostrava a recompensa CHEIA

[Sintoma]
Ao perder o combate de uma missao, o jogo continuava a exibir o premio completo.

[Causa]
`checkActiveQuestComplete()` corta o premio para 10% quando o vencedor e 'b'
(constants.fight_quest_reward_lost_xp/_lost_coin = 0.1) e grava-o na linha da
quest — isso estava certo, e o `claimQuestRewards` creditava mesmo o valor
reduzido (o `activeQuest()` rele da BD).
O problema era a RESPOSTA: `checkForQuestComplete.php` e `instantFinishQuest.php`
devolviam so `id`, `status` e `fight_battle_id` do quest. Sem o campo `rewards`,
o cliente (`DOQuest.get_rewards()`) ficava com o valor antigo em memoria e
mostrava o premio cheio. Mesma familia do BUG-000010: o cliente so atualiza o
que a resposta traz.

[Correcao]
As duas actions passam a incluir `rewards` no bloco `quest`.

[Taxa de derrota real nas missoes de luta] (skel 27/27/44/27, 3000 combates)
    facil   (NPC a 89-94%)   -> perde  1.9%
    media   (NPC a 93-96%)   -> perde  7.6%
    dificil (NPC a 97-98.5%) -> perde 23.0%
Com 20% das missoes a serem de luta e a dificuldade sorteada 0.6/0.3/0.1, perde-se
~1% das missoes no total. E raro por desenho: `NPC::randomiseQuestStats` deixa o
inimigo SEMPRE abaixo do jogador.

[Ficheiros]
- server-laravel/actions/checkForQuestComplete.php
- server-laravel/actions/instantFinishQuest.php

----------------------------------------------------------------------------
## BUG-000020 — So a FORCA atualizava ao comprar pontos de atributo

[Sintoma]
Ao comprar um ponto, o valor da habilidade basica so mexia em tempo real na
forca. Stamina, critico e esquiva ficavam parados ate um reload.

[Causa]
`improveCharacterStat.php` montava a resposta sobre a captura
`data/improveCharacterStat.json`. Essa captura e de alguem a comprar FORCA, logo
o bloco `character` dela so tem `stat_base_strength` e `stat_bought_strength`.
`Character::overlayCharacter()` preenche apenas as chaves JA presentes no
template (e de proposito — "preserva a shape do template"), portanto os outros
tres `stat_base_*` nunca iam na resposta e o cliente mantinha o valor antigo.

[Correcao]
A shape do `character` passa a vir do template do BOOT (`autoLoginUser`), que tem
os quatro atributos, os `stat_bought_*`, `stat_points_available` e `game_currency`.

[Verificacao] Varridas todas as fixtures em data/: `improveCharacterStat.json` era
a UNICA com um `character` parcial nos atributos (1 de 4). As outras ou tem os
quatro ou nao tem `character`.

[Nota] Mesma raiz do BUG-000002: fixture capturada num caso particular usada como
shape geral. Ao usar `Live::template(...)`/`Replay::data(...)` para uma accao que
muda estado, confirmar que o template contem TODOS os campos que a accao pode
alterar — senao usar o do boot.

[Ficheiro]
- server-laravel/actions/improveCharacterStat.php

----------------------------------------------------------------------------
## BUG-000021 — Equipamento nao alterava os atributos

[Sintoma]
Equipar/desequipar itens nao mexia nos atributos, apesar de cada item ter os
seus `stat_*`.

[Causa]
`Character::equipBonus()` era ESTATICO e derivado da fixture de boot:
    equip_X = autoLoginUser.character.stat_total_X - .stat_base_X
Ou seja, um valor fixo (o do equipamento do skelletonx), em cache `static`, igual
para TODOS os personagens e imune a qualquer mudanca de inventario. O proprio
codigo tinha o TODO: "calcular a partir do inventario equipado quando ele vier do
banco" — e ja vinha: `inventory.<slot>_item_id` -> `items.stat_*`.

[Correcao]
`equipBonus()` passa a instancia (nao estatico, cache por objeto) e soma as
colunas `stat_*` da tabela `items` dos ids nos 8 slots equipados
(mask/cape/suit/belt/boots/weapon/gadget/missiles — `Item::$TYPE` do oficial),
filtrando por `character_id` para nao somar itens de outra conta.

Corrigido no mesmo lote (tudo consequencia do bonus fixo):
- `stat_weapon_damage` — vem da ARMA e nao e coluna do `character`, por isso o
  overlay das colunas nunca lhe tocava e ficava no 0 do template. Agora sai do
  equipamento.
- `battleProfile()` tinha `weapondamage => 0` e `damage_normal = forca`. No
  oficial (`Player::calculateEntity`) e `damage_normal = forca + dano_de_arma` —
  a arma nao fazia diferenca nenhuma no combate.
- `hitpoints` passou a usar `Fight::HP_SCALE` em vez do 10 hardcoded.
- `duelOpponents()` enviava `stat_weapon_damage => 0` para todos.

[Ordem correta] Como no `Player::calculateStats()` oficial, o equipamento e
SOMADO depois do multiplicador (booster + guilda), nunca multiplicado por ele:
    total = ceil(base_treinada * (1 + booster% + guilda%)) + equipamento

[Verificado] skel com 6 itens equipados (+12 sta, +9 forca, +6 crit, +4 esq, +3 arma)
e multiplicador 1.75:
    stamina 15 +12 +12 = 39 | forca 15 +9 +12 = 36
    critico 25 +6 +19 = 50  | esquiva 15 +4 +12 = 31   (stat_total 156)

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (equipBonus, overlayCharacter,
  battleProfile, duelOpponents)

----------------------------------------------------------------------------
## BUG-000022 — Item substituido desaparecia (parecia vendido)

[Sintoma]
Comprar um item e arrasta-lo para cima de outro ja equipado fazia o antigo
desaparecer, em vez de ir para a mochila.

[Causa]
DOIS caminhos escreviam por cima do slot de equipamento sem tratar do item que la
estava. Ele nao era apagado da tabela `items` — ficava ORFAO: nenhum slot do
`inventory` o referenciava, portanto sumia do jogo (o efeito e igual ao de ter
sido vendido, dai a confusao).

1) `actions/inventoryOps.php` (moveInventoryItem): o swap so acontecia com
       if ($source !== null && $source !== $target)
   Ao arrastar um item vindo da LOJA, ele ainda nao esta em nenhum slot do
   inventario, logo `$source` e null -> nao havia para onde devolver o
   equipamento antigo e o UPDATE simplesmente sobrescrevia-o.

2) `Character::buyShopItem()`: fazia direto
       UPDATE inventory SET `$slotCol` = ?, `$shopSlot` = 0
   ignorando o que ja estivesse em `$slotCol`.

[Correcao]
Nos dois caminhos, quando o slot de destino esta ocupado o item deslocado vai
para a primeira posicao livre da mochila (bag_item1..18). Sem posicao livre ->
`errInventoryNoEmptySlot` (codigo que o cliente ja conhece), em vez de perder o
item em silencio.

[Recuperacao dos itens ja perdidos]
Ficaram 9 itens orfaos na conta do skel; foram devolvidos a mochila. Query para
encontrar orfaos (itens sem nenhum slot a referencia-los):
    SELECT i.id, i.identifier FROM items i
     WHERE i.character_id = ?
       AND NOT EXISTS (SELECT 1 FROM inventory inv
                        WHERE inv.character_id = i.character_id
                          AND (inv.mask_item_id = i.id OR ... OR inv.shop_item9_id = i.id));

[Ficheiros]
- server-laravel/actions/inventoryOps.php (moveInventoryItem)
- server-laravel/actions/autoLoginUser.php (item_improvements no boot)
- scripts/testing/test_login_improvement_contract.php
- server-laravel/app/HeroZero/Character.php (buyShopItem)

----------------------------------------------------------------------------
## BUG-000023 — Honra inicial a 0: duelos nao davam nem tiravam honra

[Sintoma]
Contra jogadores com 0 de honra, o tooltip anunciava 0 (ou "nan") de honra a
ganhar e perder o duelo nao custava honra nenhuma.

[Causa]
`Character::createNew()` gravava `honor = 0`. Nas constantes oficiais e
`init_honor = 100` (config.php:88 do servidor antigo; `dbschema/Character.php`
tem `'honor' => 100` e `'score_honor' => 10`).
Com honra 0 de um dos lados a matematica colapsa:
    ganho:  duelHonorWinReward(577, 0) -> 577 > 0, logo 1 - pow(0/577, 3) = 1
            -> round(100 - 100*1) = 0
    perda:  duelHonorLostReward tem `if (a == 0) return 0`
Ou seja, a economia de honra ficava morta. Com honra 0 nos DOIS lados a razao e
0/0 = NaN e o tooltip mostra mesmo "NaN".

[Correcao]
`createNew()` passa a gravar `honor = 100`. As 7 contas ja registadas que
estavam a 0 foram atualizadas (`UPDATE character SET honor=100 WHERE user_id<>0
AND honor=0`).

[POR CONFIRMAR] Se o "NaN" persistir com honra 100 dos dois lados, a origem e
outra: `$c.getDuelResultTooltip` calcula
    d = 1 + guild.get_activeDuelBoosterAmount()/100
e `get_activeDuelBoosterAmount()` faz `Si.fromId(id).get_amount()` — se o id do
booster de duelo da guilda nao existir no catalogo `guild_boosters`, isso da
null/NaN e contamina todo o tooltip.

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (createNew)

----------------------------------------------------------------------------
## BUG-000024 — Roupa nao aparecia nos duelos entre jogadores

[Sintoma]
Nos duelos os dois lutadores apareciam sem equipamento nenhum.

[Causa]
`Character::battleAppearance()` devolvia so a aparencia do CORPO (pele, cabelo,
olhos, boca...). O cliente desenha a roupa a partir do IDENTIFIER de cada peca
equipada, dentro do mesmo bloco — a captura oficial tem, p.ex.,
`"belt": "belt_extension_cord1"`. Sem essas chaves, nada de equipamento.

[Correcao]
`battleAppearance()` le o `inventory` e junta os identifiers de
mask/cape/suit/belt/boots/missiles (de `items.identifier`).
Como no `DuelBattle::characterAppearance()` oficial, ARMA (type 6) e GADGET
(type 7) sao SALTADOS — nao se desenham no corpo:
    foreach($eqItems as $it){ if($it->type == 7 || $it->type == 6) continue; ... }

[Só afeta DUELOS] Nas missoes de luta nao se envia bloco de aparencia de
proposito: o cliente desenha o NPC a partir de `quest.fight_npc_identifier`
(ver BUG-000017).

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (battleAppearance)

----------------------------------------------------------------------------
## FIXES APLICADOS — resumo
[bug única] setTutorialFlags.php       -> devolve Live::accountState()  (BUG-000002)
[bug única] Character::setTutorialFlag() -> helper novo p/ marcar 1 flag (BUG-000001)
[bug única] Character::claimQuestRewards-> first_mission + promocao nivel 2 (BUG-000001/3)
[bug única] Character::buyShopItem()    -> first_item (BUG-000001)
[bug única] Character::improveStat()    -> stats_spent (BUG-000001)
[bug única] actions/claimDuelRewards.php-> first_duel (BUG-000001)
[bug única] actions/startDuel.php       -> shape do boot + validar oponente + limpar duelo preso (BUG-000004)
[bug única] actions/startDuel.php       -> rounds com profile_a/b_appearance + dano coerente (BUG-000005)
[bug única] Character::battleAppearance()-> aparencia dos lutadores p/ os rounds (BUG-000005)
[bug única] actions/startDuel.php       -> resume path + unset(battles) + custo stamina 20 (BUG-000004 addendum)
[bug única] Character::createNew()      -> duel_stamina/max=100 (BUG-000004 addendum)
[bug única] actions/checkForDuelComplete.php -> inclui battle, sem battles[] (BUG-000006)
[bug única] Live::accountState()        -> anexa duel/battle/opponent do duelo ativo (BUG-000006)
[bug única] DuelRewards (novo)          -> honra/moedas com as formulas do cliente (BUG-000007)
[bug única] actions/startDuel.php       -> grava recompensas calculadas, nao fixas (BUG-000007)
[bug única] actions/claimDuelRewards.php-> honra com piso 0 (BUG-000007)
[bug única] actions/instantFinishQuest.php -> conclui e devolve battle (BUG-000008)
[bug única] QuestRewards (novo)         -> formula oficial de xp/moedas (BUG-000009)
[bug única] Character::insertQuestPool() -> usa QuestRewards, nao valores capturados (BUG-000009)
[bug única] actions/claimDuelRewards.php-> devolve character (active_duel_id=0) (BUG-000010)
[bug única] Character::syncDuelStamina() -> regenera stamina + ts (BUG-000011)
[bug única] Character::insertQuestPool() -> sorteia do catalogo oficial, 454 ids (BUG-000012)
[bug única] Character::guildStatBonus()/guildQuest*Factor() -> boosts da guilda (BUG-000013)
[bug única] Character::createNew()      -> init_base_stat_value=10 nos 4 atributos
[bug única] Character::boosterAmount()/statMultiplier() -> boosters aplicados (BUG-000014)
[bug única] Character::startQuest()     -> booster corta duracao/energia (BUG-000014)
[mudanca]   QuestRewards reescrito     -> 1:1 com Utils::randomiseQuest do server antigo (MUDANCA-000015)
[bug única] Character::ensureQuestPool() -> missoes persistentes por cidade (BUG-000016)
[bug única] Fight (novo)                -> combate simulado, vencedor real (BUG-000017)
[bug única] Character::questNpcProfile() -> NPC de missao escalado ao jogador (BUG-000017)
[bug única] Character::duelOpponents()  -> lista contas reais, nao NPCs (BUG-000018)
[bug única] checkForQuestComplete/instantFinishQuest -> devolvem `rewards` (BUG-000019)
[bug única] improveCharacterStat.php    -> shape do boot, os 4 atributos (BUG-000020)
[bug única] Character::equipBonus()     -> le o inventario real, nao a fixture (BUG-000021)
[bug única] moveInventoryItem/buyShopItem -> item substituido vai p/ mochila (BUG-000022)
[bug única] Character::createNew()      -> init_honor = 100 (BUG-000023)
[bug única] Character::battleAppearance() -> identifiers da roupa equipada (BUG-000024)

----------------------------------------------------------------------------
## BUG-000025 — Custo de comprar pontos de atributo era sempre 5 moedas

[Sintoma]
O tooltip ao passar o rato no botão "+" mostrava o preço correcto (ex: ~2 milhões
por 10 pontos com stat_bought=850), mas ao comprar o servidor debitava sempre
50 moedas (5 por ponto) independentemente do valor já comprado.

[Causa]
`Character::nextStatCost()` devolvia sempre `return 5` (comentado como "modelo
simples e próximo do oficial"). O custo real escala com o `stat_bought` actual
do stat específico. A fórmula do cliente (Q.calcNeededGameCurrency) é:

    custo_por_ponto = round(base + scale × (base_scale × stat_bought)^exp)
    base=5, scale=0.12, base_scale=0.85, exp=2.15

Ao comprar 10 pontos de uma vez (fast skill), cada ponto seguinte custa mais,
logo tem de se somar ponto a ponto. Exemplos:
    stat_bought=0   ->    5 moedas/ponto  (50 por 10)
    stat_bought=100 -> 1693 moedas/ponto  (16930 por 10)
    stat_bought=850 -> ~168k moedas/ponto (~1.68M por 10)

O servidor também usava a soma de todos os stats comprados em vez do stat
específico — na realidade cada stat tem o seu próprio `stat_bought_*` e o custo
escala individualmente.

[Correcao]
Substituído `nextStatCost()` por `calcStatCost(int $currentBought, int $paidPoints)`
que implementa a fórmula exacta do cliente e soma ponto a ponto.
`improveStat()` passa a ler `stat_bought_<stat>` do stat específico como ponto
de partida, não a soma de todos os stats.

Constantes (de balancing_constants.json, iguais ao cliente):
    cost_stat_base=5, cost_stat_scale=0.12,
    cost_stat_base_scale=0.85, cost_stat_base_exp=2.15

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (calcStatCost, improveStat)

----------------------------------------------------------------------------
----------------------------------------------------------------------------
## BUG-000026 — Centrifugadora (washing machine) não fazia nada

[Sintoma]
Usar a centrifugadora num equipamento não alterava os seus stats — o item ficava
exactamente igual antes e depois.

[Causa]
`actions/inventoryOps.php` case `washInventoryItem` só actualizava
`ts_last_wash_item` e não fazia mais nada:

    Db::exec('UPDATE `character` SET ts_last_wash_item = ? WHERE id = ?', [time(), $cid]);
    break;

Nenhuma lógica de redistribuição de stats estava implementada.

[Como funciona]
A centrifugadora redistribui os stats de combate totais do item
(`stat_stamina + stat_strength + stat_critical_rating + stat_dodge_rating`)
por 1, 2, 3 ou 4 habilidades, escolhidas aleatoriamente.
`stat_weapon_damage` fica inalterado (é stat de arma, não de armadura).

Probabilidades do número de habilidades que recebem pontos:
    1 stat  → 15%
    2 stats → 25%
    3 stats → 40%
    4 stats → 20%

Custo: 1 uso gratuito por dia (verificado via `ts_last_wash_item`).
Se já usou hoje → custa 1 donut (2 donuts para itens de melhoria, type=12).
Constantes de referência (balancing_constants.json):
    washing_machine_premium_currency_amount: 1
    washing_machine_improvement_premium_currency_amount: 2
    washing_machine_req_level: 20

[Correcao]
Novo `Character::washItem(int $itemId, bool $usePremium)`:
- valida que o item pertence ao personagem;
- verifica o uso diário e debita donuts se necessário
  (`errRemovePremiumCurrencyNotEnough` se insuficientes);
- sorteia o número de stats (15/25/40/20%);
- distribui o total pelos stats escolhidos em partes aleatórias
  (método das ordens: gerar N−1 pontos de corte únicos);
- faz UPDATE na tabela `items` e actualiza `ts_last_wash_item`.

`inventoryOps.php` delega para `washItem()`. A resposta já vem via
`Live::accountState()` — o item actualizado chega ao cliente no `updateData`.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (washItem)
- server-laravel/actions/inventoryOps.php (washInventoryItem)

----------------------------------------------------------------------------
## REFERENCIA IMPORTANTE — servidor oficial antigo em PHP
/home/laliene/Documentos/merdas docker/herodocker/herozero/server
Versao mais antiga do jogo, mas com as formulas server-side que o cliente nao
expoe (recompensas, boosters, stats, guilda). Ver class/Player.php,
class/Utils.php e request/*.req.php. Consultar ANTES de deduzir formulas.
Nota: `Utils::randomiseQuest()` tem a geracao de quest oficial (duracao
`mt_rand(min(4,lvl-1), min(lvl+5,12))` minutos, coins `mt_rand(lvl+2,lvl+5)*(lvl/2)`,
xp `10*lvl*dur_raw + random(0.5,1)*550`) — mais fiel do que a formula derivada
que usamos hoje em QuestRewards; fica como melhoria futura.

## REGRA GERAL (aprendida nos duelos)
Qualquer resposta que traga `battle` NAO pode trazer `battles[]` junto: o cliente
da prioridade a `getBattleFromId(battles, id)` e falha quando o id nao esta la.
Sempre `unset($data['battles'])` depois de herdar a shape do `autoLoginUser`.

----------------------------------------------------------------------------
## DADOS / LOGS
- `.logs/server.log`              <- trafego real das actions
- server-laravel/data/*.json      <- templates de resposta (fixtures; autoLoginUser.json
                                     tem as shapes de duel/battle/opponent)
- docs/RESPONSE_SCHEMA.md         <- campos de nivel raiz
- `mapping.txt`                   <- mapa de pastas e funcoes relevantes
----------------------------------------------------------------------------
## BUG: Rota raiz (/) não redirecionava para o cliente do jogo
**Sintoma:** Aceder a `http://localhost:8000/` mostrava a página de embarque
(`index.html`) em vez de abrir o jogo diretamente.  
**Causa:** A rota `/` servia `public/index.html` estática.  
**Correção:** `routes/web.php` — rota `/` alterada para `redirect('/beta.html')`.  
**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG: Sistema de torneios semanais não implementado
**Sintoma:** Torneios não registavam progresso, leaderboards devolviam listas vazias,
`pending_tournament_rewards` sempre 0.  
**Causa:** Tabelas existiam (`tournaments`, `tournament_snapshots`, `tournament_rewards`)
mas não havia lógica de criação, tracking, leaderboard nem recompensas.  
**Correção:** Implementado sistema completo em `app/HeroZero/Tournament.php`:
- Torneio criado lazy no primeiro login da semana (termina domingo às 21h)
- Snapshot de XP, honor e stats base tirado por personagem no início
- Leaderboards reais por tipo: XP (delta xp), Stats (delta stat_base), PvP (contagem de ataques feitos)
- Actions `getTournamentRewards` e `claimTournamentRewards` implementadas
- CLI `tools/tournament.php start|end|status|reset` para gerir ciclos semanais
**Estado:** ✅ Corrigido

**⚠️ Porém — Torneio de Habilidades (tipo 2):**  
Está a contar apenas habilidades **compradas** (pontos de level-up gastos + moedas).
Habilidades melhoradas via **treino** (`finishTraining`) também deveriam contar,
mas o treino incrementa `stat_trained_*` separadamente e não está a ser somado
ao delta de `stat_base_*`. A corrigir futuramente.

----------------------------------------------------------------------------
## BUG-000027 — Ranking de guildas devolvia lista de personagens em vez de guildas
**Sintoma:** Ao abrir o painel de ranking de teams (guildas), o cliente crashava com
`TypeError: can't access property "iterator", a.get_appResponse().get_leaderboard_guilds() is null`.
O painel ficava inacessível.  
**Causa:** A action `retrieveGuildLeaderboard` não tinha handler específico e caía no
fallback genérico de `leaderboard.php`. Esse fallback, sem template com `leaderboard_guilds`,
entrava na condição que adiciona `leaderboard_characters` — shape completamente errada para
o painel de guildas. O cliente tentava `.get_leaderboard_guilds()` e recebia `null`.  
**Correção:** `server-laravel/actions/leaderboard.php` — adicionado handler específico para
`retrieveGuildLeaderboard` que faz query real à tabela `guild` com subquery de ranking via
variável MySQL `@rank`, join com `guild_battle` para `gbd` (defesa ativa), e devolve os campos
curtos que o cliente espera: `n`, `h`, `tp`, `r`, `ebs`, `ebc`, `ebbc`, `eis`, `eic`, `eiz`,
`ga`, `gbd`. Suporta paginação por `rank`, pesquisa por `guild_name`, e centra na guilda do
próprio jogador quando nenhum parâmetro é passado.  
**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000028 — Percentagem de expansão da guilda calculada com divisor errado
**Sintoma:** O campo `tp` (total_percentage) devolvido no ranking de guildas estava sempre
próximo de zero — uma guilda com 30/30, 50/50, 50/50, 50/50 mostrava ~1.0 em vez de 100%.  
**Causa:** O divisor usado no cálculo era `4` (número de colunas de stat), quando o correto é
`175` (soma dos valores máximos de todos os stats: 30+50+50+50). A fórmula é
`tp = (stat_guild_capacity + stat_character_base_stats_boost + stat_quest_xp_reward_boost + stat_quest_game_currency_reward_boost) / 175`,
tal como definido na versão original em `config.php` com `guild_percentage_total_base = 175`.  
**Correção:** `server-laravel/actions/leaderboard.php` — `$maxGuildBasePercentage` alterado de
`4` para `175`.  
**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000029 — Atacar gangue da guilda dava response_errorIO

[Sintoma]
`attackGuildDungeonNPCTeam` devolvia `{"data":{},"error":"response_errorIO"}`.
O jogador não conseguia agendar ataques a gangues (guild dungeon). As actions
anteriores do fluxo (`getGuildDungeonNPCTeam`, `rerollGuildDungeonNPCTeam`)
funcionavam normalmente — o erro só aparecia ao confirmar o ataque.

[Causa]
A tabela `guild` não tinha a coluna `pending_guild_dungeon_battle_attack_id`.
`guildDungeon.php` linha 415 fazia:
    Db::exec('UPDATE `guild` SET pending_guild_dungeon_battle_attack_id=? WHERE id=?', [$battleId, $guildId]);
MySQL lançava `SQLSTATE[42S22]: Column not found: 1054 Unknown column
'pending_guild_dungeon_battle_attack_id' in 'field list'`, que era apanhado pelo
catch genérico do `GameController` (linha 71) como `response_errorIO`.

O INSERT na `guild_dungeon_battle` (linhas 398-412) executava COM SUCESSO antes
do UPDATE à `guild` falhar — ficava uma battle órfã com status=2 na tabela.
Na próxima tentativa, o `$pendingBattle()` encontrava essa battle e tentava
apagá-la (linha 352-353), o que funcionava, mas o UPDATE à `guild` voltava a
falhar no mesmo sítio.

A coluna era referenciada em dois sítios:
1. `guildDungeon.php:415` — UPDATE (crash)
2. `guildDungeon.php:233` — UPDATE para limpar após resolve (crash)
3. `Live.php:413` — `shapeGuild()` leitura (sem crash por causa do `?? 0`)

O schema (`01_schema.sql`) foi criado a partir de um dump antigo do jogo que
não tinha esta coluna. Outros campos adicionados posteriormente pelo código
(locale, synergy_*, apply_open, battles_fought, officer_note, etc.) também
faltavam no schema — o `shapeGuild()` protegia-os com `?? default`, mas qualquer
UPDATE a essas colunas falharia da mesma forma.

[Correcao]
1. `ALTER TABLE guild ADD COLUMN pending_guild_dungeon_battle_attack_id INT NOT NULL DEFAULT 0`
   aplicado na DB ao vivo.
2. `01_schema.sql` — adicionadas ~20 colunas em falta à definição da tabela
   `guild`: locale, ts_last_synergy_calculation, ts_next_synergy_calculation,
   min_apply_hideout_level, min_apply_gem_level, apply_open, apply_open_mail,
   officer_note, pending_guild_battle_attack_id, pending_guild_battle_defense_id,
   pending_guild_dungeon_battle_attack_id, battles_fought, unlocked_rewards,
   guild_competition_reward_boost_factor, ts_guild_competition_reward_boost_expires,
   guildbook_objectives_renewed_today, synergy_energy, synergy_energy_storage,
   synergy_energy_storage_temp, synergy_energy_storage_max, active_synergy_energy,
   synergy_upgrades. Todas com DEFAULT para não quebrar registos existentes.
3. Logging do catch genérico melhorado para incluir ficheiro, linha e stack trace
   completo (`GameController.php` linha 72), em vez de só a mensagem — permite
   diagnosticar futuros `response_errorIO` sem ter de adivinhar.

[Lição — schema vs código]
O `shapeGuild()` é resiliente (usa `?? default` em todas as colunas novas), mas
os UPDATEs não são — se o código escreve numa coluna que não existe, o MySQL
rebenta. Ao adicionar campos novos ao código, verificar SEMPRE que a coluna
existe tanto na DB ao vivo como no `01_schema.sql` (para futuras recriações).

[Ficheiros]
- server-laravel/docker/initdb/01_schema.sql (schema da tabela guild)
- server-laravel/app/Http/Controllers/GameController.php (logging melhorado)
- server-laravel/actions/guildDungeon.php (código que usava a coluna em falta)

----------------------------------------------------------------------------
## BUG-000030 — Membros da guilda sempre offline + "last action" absurdo

[Sintoma]
No painel da team, todos os membros apareciam como offline, mesmo quem estava
a jogar activamente. O campo "última acção" mostrava valores absurdos (milhares
de dias).

[Causa]
1) `Live::guildMembers()` tinha `online_status => 2` HARDCODED. O cliente usa
   `get_isOnline()` que retorna `true` quando `online_status == 1`; com `2`
   fixo estava sempre offline.
2) O campo `last_action` estava a enviar o TIMESTAMP raw de `ts_last_action`
   (~1.7 mil milhões), mas o cliente espera MINUTOS desde a última acção. Com o
   timestamp raw o cliente mostrava milhares de dias.
3) `ts_last_action` nunca era actualizado — só algumas acções específicas
   (buyLeagueBooster, joinGuild, energyTraining) o escreviam. Um jogador que
   fizesse missões, duelos e syncs ficava com `ts_last_action` do momento do
   registo.

[Correcao]
1) `online_status` derivado de `ts_last_action`: `1` (online) se agiu nos
   últimos 5 minutos, `2` (offline) caso contrário.
2) `last_action` passa a devolver `floor((time() - ts_last_action) / 60)` — 
   minutos desde a última acção, como o cliente espera.
3) `GameController.php` — actualiza `ts_last_action` em CADA request autenticado,
   com throttle de 30 segundos (`WHERE ts_last_action < time() - 30`) para não
   martelar a DB a cada poll do syncGame.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (guildMembers: online_status + last_action)
- server-laravel/app/Http/Controllers/GameController.php (ts_last_action a cada request)

----------------------------------------------------------------------------
## BUG-000031 — Registos de batalha da guilda não apareciam no painel

[Sintoma]
No painel da team, não se via quem estava registado para ataques a gangues,
ataques a teams ou defesas. Os ícones de participação ficavam vazios.

[Causa]
O cliente lê os objectos `pending_guild_dungeon_battle`,
`pending_guild_battle_attack` e `pending_guild_battle_defense` da resposta e
cruza os `character_ids` com a lista de membros (`hasJoinedMember(charId)`).
`Live::accountState()` nunca anexava estes objectos — só o `guildDungeon.php`
devolvia o `pending_guild_dungeon_battle`, e apenas nas acções específicas de
gangue. No boot/sync o cliente nunca os recebia.

[Correcao]
`Live::accountState()` — após o bloco da guild, consulta as tabelas
`guild_dungeon_battle` (por `pending_guild_dungeon_battle_attack_id`) e
`guild_battle` (por `pending_guild_battle_attack_id` /
`pending_guild_battle_defense_id`) e anexa-os à resposta quando existem com
status=2 (pendente).
Nota: `guild_battle` usa `attacker_character_ids`/`defender_character_ids` em
vez de `character_ids` — mapeados para `character_ids` na resposta.

[Ficheiro]
- server-laravel/app/HeroZero/Live.php (accountState)

----------------------------------------------------------------------------
## BUG-000032 — Log da guilda: entrar mostrava "abandonou" em vez de "entrou"

[Sintoma]
Quando um herói entrava na team, o log da guilda mostrava que o herói ABANDONOU
a team.

[Causa]
Os tipos de log da guilda no servidor oficial antigo
(`class/Utils/GuildLogType.php`):
    MemberJoined = 1
    MemberLeft   = 2
`joinGuild.php` e `guildAdmin.php` (acceptGuildApplication) gravavam `type=2`
(MemberLeft) em vez de `type=1` (MemberJoined).

[Correcao]
Ambos os ficheiros corrigidos para `type=1`.

[Ficheiros]
- server-laravel/actions/joinGuild.php
- server-laravel/actions/guildAdmin.php (acceptGuildApplication)

----------------------------------------------------------------------------
## BUG-000033 — Energéticos da guilda: comprar um activava outro + donuts crashavam

[Sintoma]
1) Comprar um energético de treino ou duelo (versão moedas) activava sempre o
   de missão. O booster comprado não ficava seleccionado no painel.
2) Comprar qualquer energético de donuts (training2, quest2, duel2) crashava o
   jogo com `errRequestInvalidParameter`.

[Causa]
O cliente envia o identificador do booster no parâmetro `id`, mas o código lia
`$params['booster_id'] ?? $params['identifier']`, caindo sempre no default
`'guild_booster_quest1'`.

Consequências:
1) Para os de moedas: `boosterId` era sempre `guild_booster_quest1` →
   `str_contains('guild_booster_quest1', 'training')` = false →
   `str_contains('guild_booster_quest1', 'duel')` = false → caía no `default`
   → actualizava sempre as colunas `active_quest_booster_id` /
   `ts_active_quest_boost_expires`, independentemente do booster comprado.
   O custo era debitado correctamente (30000 moedas, igual para todos os `*1`),
   mas o booster errado era activado.
2) Para os de donuts: `boosterId` = `guild_booster_quest1` (default) mas a
   `duration` enviada (ex: 2592000 para 30 dias) não existe no catálogo de
   `guild_booster_quest1` (que só tem 604800) → `boosterCost()` retorna null →
   `errRequestInvalidParameter` → o cliente crashava e pedia reload.

[Correcao]
`boosterExtra.php` — `$boosterId` lê `$params['id']` primeiro (o que o cliente
envia), com fallback para `booster_id` / `identifier`.
Adicionalmente, o match para escolher as colunas da guild passou de
`str_contains($boosterId, ...)` para ler o `type` do catálogo
(`GUILD_BOOSTER_CATALOG[$boosterId][0]`): 1=training, 2=quest, 3=duel. Mais
robusto que inferir do nome.
Mesma correcção aplicada ao `buyLeagueBooster`.

[Causa adicional — custo fixo em vez de escalado]
O catálogo tinha custo fixo de 30000 moedas para todas as variantes `*1`. No
servidor oficial antigo (`class/Guild.php:110`):
    return Config::get("guild_booster_cost_game_currency_per_improvement") * $this->stat_guild_capacity;
Ou seja, o custo em moedas escala com o `stat_guild_capacity` da guilda:
    30000 × stat_guild_capacity
Uma guilda com capacidade 17 paga `30000 × 17 = 510000` moedas — o valor que o
tooltip do cliente mostrava correctamente. O servidor cobrava sempre 30000
(valor base), por isso:
1) Parecia que "não verificava o saldo" — na verdade verificava, mas contra
   30000, não contra 510000, e com 300k na caixa passava sempre.
2) Cada compra tirava 30000 em vez de 510000, fazendo a caixa descer lentamente
   em vez de debitar o montante correcto.

[Correcao adicional]
`boosterExtra.php` — para boosters de moedas (`!$isPremium`), o custo passa a
ser `baseCost × stat_guild_capacity` (lido da tabela `guild`). Para boosters de
donuts (`$isPremium`) o custo é fixo (69/130/265 conforme a duração), como no
original (`guild_booster_cost_premium_currency = 69` para 7 dias).

[Ficheiro]
- server-laravel/actions/boosterExtra.php

 ----------------------------------------------------------------------------
## BUG-000034 (ox-alpha) — Defensor de duelo nunca ganhava/perdia honra (fluxo "missed duels" inexistente)
[Sintoma]
Ao atacar um oponente, quem atacava ganhava/perdia honra normalmente, mas o
defensor nunca perdia honra ao ser derrotado nem ganhava ao vencer — a honra
dele ficava congelada independentemente do resultado.

[Causa]
O servidor GRAVAVA as recompensas dos dois lados (`character_a_rewards` e
`character_b_rewards`) ao criar o duelo, mas o lado do defensor nunca era
aplicado. O jogo oficial entrega o resultado ao defensor pelo fluxo de
"duelos perdidos": o cliente só mostra a notificação/painel se
`missed_duels > 0` na resposta, depois puxa `getMissedDuelsNew` (lista) e
`getMissedDuel` (detalhe), e fecha com `claimMissedDuelsRewards`. No nosso
servidor: o boot zerava `missed_duels` (ACCOUNT_STATE_KEYS + emptyLike),
nenhuma query calculava o contador, e `getMissedDuelsNew`/`getMissedDuel`
caíam no fallback genérico sem `missed_duel_data`/`missed_duel_opponents`.
Resultado: o defensor nunca via os duelos perdidos e as recompensas ficavam
presas para sempre na tabela.

[Correcao]
- `Live::missedCounters()` (novo): conta duelos/ligas pendentes do defensor
  (`character_b_status IN (1,2)`); aplicado no `accountState()` e no boot
  (depois do esvaziamento do template).
- `leagueMissed.php`: 4 actions novas:
  - `getMissedDuelsNew` / `getMissedLeagueFights` -> `missed_duel_data` +
    `missed_duel_opponents` (shapes `sw`/`Gl` extraídas do bundle). Na lista
    CRUA o defensor é o lado 'b': get_isWon() do cliente = winner=='b'.
  - `getMissedDuel` / `getMissedLeagueFight` -> detalhe re-servido DO PONTO DE
    VISTA DO DEFENSOR (lado 'a'): stats, winner, rounds (chaves a/d de cada
    golpe) e aparências trocados, porque `En`/`ep` leem isWon = battle.winner
    =='a' e a recompensa de character_a_rewards. Mais `decrease_counter=1`.
- Shapes extraídas do HeroZero.min.js: `sw` (id/opponent_id/ts_creation/
  unread/winner/character_b_rewards), `Yy` (id/battle_id/character_a_rewards/
  character_a_status), `Gl` (id/name/gender/level/honor/online_status/
  stat_total_*/stat_weapon_damage/league_group_id).

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (missedCounters + accountState)
- server-laravel/actions/autoLoginUser.php (contadores reais no boot)
- server-laravel/actions/leagueMissed.php

 ----------------------------------------------------------------------------
## BUG-000035 (ox-alpha) — Tooltip de honra do duelo não batia certo com a honra paga
[Sintoma]
Ao passar o rato sobre um oponente, o tooltip prometia X honra/moedas; após
ganhar o duelo, o valor recebido era menor.

[Causa]
O tooltip do cliente (`$c.getDuelResultTooltip`) multiplica o ganho por:
    d = 1 + guild.get_activeDuelBoosterAmount()/100        (energético de duelo)
    L = 1 + guild.get_activeDuelLeagueArtifactAmount()/100 (artefacto de liga)
O nosso `DuelRewards::forDuel()` chamava sempre honorWin/coinsWin com mult 1.0.
Com o energético de duelo da guilda ativo (+10% duel1 / +20% duel2), o tooltip
prometia ganho ×1.10/×1.20 e o servidor pagava ×1.00.

[Correcao]
- `DuelRewards::forDuel()` recebe `$mult1`/`$mult2` e aplica-os AO VENCEDOR
  (honra E moedas); a perda nunca leva mult (também não leva no tooltip).
- `startDuel.php`: multiplicador = booster de duelo da guilda do VENCEDOR
  (mesma escolha do DuelBattle::start oficial para moedas; coincide com o
  tooltip do atacante sempre que é ele a ganhar).
- Artefactos de liga não existem neste servidor -> mult2 = 1.0.
- NOTA: sem booster, mult = 1.0 e tooltip/servidor já coincidiam (fórmulas
  iguais); `Sb.getValue(14)` (_bonusValueDuelHonor) vale 0 para todos.

[Ficheiros]
- server-laravel/app/HeroZero/DuelRewards.php
- server-laravel/actions/startDuel.php

 ----------------------------------------------------------------------------
## BUG-000036 (ox-alpha) — Honra do defensor só atualizava ao reclamar (ranking desatualizado)
[Sintoma]
Sem clicar no ícone do soco (paineis de duelos perdidos), o ranking e o perfil
mostravam a honra antiga do defensor — ela só mudava quando ele reclamava.

[Causa]
As `character_b_rewards` só eram aplicadas em `claimMissedDuelsRewards`. O
oficial aplica-as NO MOMENTO DO ATAQUE (startDuel.req.php:
`$opponent->giveRewards($duel->character_b_rewards)`); o painel de duelos
perdidos é apenas INFORMATIVO (ver/apagar). Idem na liga
(startLeagueFight.req.php).

[Correcao]
- `startDuel.php`: aplica `character_b_rewards` ao defensor logo após criar o
  duelo (honra com piso 0).
- `leagueMissed.php` `startLeagueFight`: idem para moedas/pontos de liga.
- `claimMissedDuelsRewards` / `claimMissedLeagueFightsRewards`: deixam de
  aplicar valores (já aplicados no combate); apenas fecham as entradas
  (b_status=3).
- Migração one-off: entradas pendentes anteriores à correção tiveram as
  recompensas aplicadas via SQL (skelletonx: 18 duelos, −227 honra).

[Verificado]
Duelo skel -> sonnet46: b_rewards {"coins":2692,"honor":104} e a honra dela na
DB subiu +104 na hora, sem nenhum claim.

[Ficheiros]
- server-laravel/actions/startDuel.php
- server-laravel/actions/leagueMissed.php

 ----------------------------------------------------------------------------
## BUG-000037 (ox-alpha) — Batalha do duelo com heróis invisíveis + "LOC" + skip preso (lastInsertId resetado)
[Sintoma]
Ao clicar para duelar, os heróis não apareciam no painel de batalha,
habilidades/nível/HP mostravam "LOC", e o botão de skip saltava para o fim mas
nunca saía da animação/duelo.

[Causa]
Um UPDATE entre o `INSERT INTO duel` e `Db::pdo()->lastInsertId()` RESETA o
lastInsertId para 0 (reproduzido isoladamente: INSERT -> id 88; UPDATE ->
lastInsertId 0). Foi introduzido pelo próprio fix do BUG-000036, que colocou o
UPDATE das recompensas do defensor nesse intervalo. Com $duelId = 0: o SELECT
do duelo recém-criado falhava, a resposta levava o bloco `duel` do TEMPLATE
capturado (id 3622222, battle_id 12793623 — ids da captura oficial!) que não
batia certo com a `battle` real -> o cliente crashava a montar o painel.

[Correcao]
`startDuel.php`: `$duelId = lastInsertId()` capturado IMEDIATAMENTE após o
INSERT, antes de qualquer outro statement. REGRA GERAL: nunca intercalar
Db::exec entre um INSERT e o lastInsertId().

[Verificado]
Resposta consistente: duel.id=89, duel.battle_id=145 == battle.id=145,
character.active_duel_id=89.

[Ficheiro]
- server-laravel/actions/startDuel.php

 ----------------------------------------------------------------------------
## MUDANCA-000038 (ox-alpha) — Relógio de jogo centralizado (fuso, slots, torneios)
[Sintoma]
Ataque a gangue marcado para "hoje" ficava gravado para amanhã; horas dos slots
mostradas diferentes das escolhidas; ciclos diários do cliente fora de hora
(a cada ~24h o cliente avançava "dia" em momentos estranhos).

[Causa] Três problemas empilhados:
1) O template do boot manda `server_timestamp_offset = -10800` (UTC-3, captura
   do servidor oficial BRASILEIRO) e nós nunca sobrescrevíamos. O cliente
   calcula a hora do servidor como utcLocalTimestampOffset +
   server_timestamp_offset + timestampCorrection e decide "hoje/amanhã" com
   isso — para utilizadores em Portugal (WEST, UTC+1) o relógio parecia estar
   4h atrasado.
2) Os slots de ataque (12/16/18/20/22h) eram calculados com date()/strtotime()
   em UTC do servidor: gravava 12:00 UTC que o cliente mostra como 13:00 locais.
3) A constante `guild_dungeon_preparation_time` (=10, HORAS) estava disfarçada
   de minutos (`10*60`, dividido por 60 no limite — numericamente dava 10, mas
   por acidente e sem documentação).

[Correcao — relógio central]
- `Live::gameTimezone()` / `Live::gameNow()` / `Live::gameTimestampOffset()`:
  fonte ÚNICA da hora de jogo (env `HZ_GAME_TZ`, default Europe/Lisbon).
- `Response::ok()`: envia `server_timestamp_offset` = offset do fuso de jogo em
  TODAS as respostas (o cliente lê em qualquer resposta via eb.refreshData).
  Com S=+3600 e utcLocal=-3600, anulam-se: a hora do servidor == hora de
  Lisboa. NÃO copiámos o TIME_OFFSET do oficial (+7200 ao time()) porque isso
  aceleraria todas as expirações; epochs continuam absolutos e verdadeiros.
- `guildDungeon.php`: slots calculados no relógio de jogo; regra 1:1 com o
  cliente (`pe.getGuildDungeonBattleAttackTimestamp`):
      hora_atual >= hora_slot - prep(10h)  ->  amanhã
  Ou seja (OFICIAL): slot 12h só fica "hoje" até às 02:00, 16h até 06:00,
  18h até 08:00, 20h até 10:00, 22h até 12:00. Fora disso é sempre amanhã —
  e agora o diálogo do cliente mostra exactamente isso.
- `Tournament.php`: fim ("domingo 21:00") e semana ISO passam a usar gameNow()
  (antes UTC -> acabava às 20:00 locais).
- Auditoria: restante lógica de intervalos (stamina, energia, boosters,
  missões) usa epochs absolutos via time() — imune a fuso, intocada.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (gameTimezone/gameNow/gameTimestampOffset)
- server-laravel/app/HeroZero/Response.php (server_timestamp_offset global)
- server-laravel/actions/guildDungeon.php
- server-laravel/app/HeroZero/Tournament.php

 ----------------------------------------------------------------------------
## BUG-000039 (ox-alpha) — Estender energético de herói PERDIA o tempo restante
[Sintoma]
Na opção de estender energéticos a donuts, a opção de 7 dias "parecia"
funcionar, mas 30 e 90 dias não alteravam a data de expiração — apesar dos
donuts serem debitados.

[Causa]
`buyBooster.php` gravava `expires = time() + duração` (SOBRESCREVER). O oficial
(buyBooster.req.php) ACUMULA: base = expiração ativa actual (se ainda no
futuro), nova expiração = base + duração. Com tempo restante grande (ex.: 90
dias), estender 30 dias produzia uma data ANTERIOR à existente — parecia "não
mudar"; com ~7 dias restantes, hoje+7 coincidia com a data actual — parecia
funcionar. Ambos perdiam o tempo restante.

[Correcao]
`buyBooster.php`: base = max(time(), ts_active_*_boost_expires actual);
expires = base + duração (-1 = permanente). Igual ao oficial. Nota:
`buySenseBooster` e os boosters de guilda já acumulavam correctamente.

[NOTA — não é bug: opções de 10%/25% bloqueadas]
Com um energético +50% activo (quest3/stats3/work3), as variantes de moedas
(+10%/+25%) aparecem bloqueadas. É regra OFICIAL do cliente (downgrade
protection): `set_buttonEnabled(activeBoosterAmount <= hoveredAmount)` nas três
classes (quest/stats/work). Desbloqueiam quando o forte expirar. O utilizador
nota que o comportamento oficial difere ligeiramente — a revisar no futuro se
se confirmar que o oficial permite comprar por cima (param `overrideBooster`
existe no fluxo de GUILD; para herói não foi encontrado equivalente).

[Verificado]
Extensão passa a somar sobre a data existente (confirmado pelo utilizador).

[Ficheiro]
- server-laravel/actions/buyBooster.php

 ----------------------------------------------------------------------------
## BUG-000040 (claude opus 4.6) — NPC profile NaN crashava a batalha da guilda ("b is null")
[Sintoma]
Ao clicar "ver batalha" no resultado do ataque a gangue, o cliente crashava com
`TypeError: can't access property "get_name", b is null` na linha 14698.

[Causa]
O campo `profile` dos NPCs tinha o formato `"<npc_team_identifier>_<npcId>-<i>"`,
ex: `"team17_npc_adventurer-0"`. O cliente faz
`parseInt(substr(profile, 0, indexOf("_")))` para obter o id numérico do lutador.
`parseInt("team17")` devolve `NaN`. Como `NaN == NaN` é `false` em JavaScript,
`getCharacter(id)` nunca encontrava o NPC → devolvia `null` → crash ao ler
`.get_name()`.

[Correcao]
`GuildBattles::simulateDungeonBattle()` — formato do profile alterado de
`"<npc_team_identifier>_<npcId>-<i>"` para `"<i>_<npcId>-<i>"`, onde `<i>` é o
índice (0-4). `parseInt("0")` = `0`, `parseInt("1")` = `1`, etc. — ids numéricos
válidos que `getCharacter()` encontra.

[Ficheiro]
- server-laravel/app/HeroZero/GuildBattles.php (simulateDungeonBattle, ~L109)

 ----------------------------------------------------------------------------
## BUG-000041 (claude opus 4.6) — autoLoginUser não resolvia batalhas de guilda pendentes
[Sintoma]
Ao fazer login, a batalha de guilda pendente (status=2 com ts_attack já passado)
não era resolvida — o jogador nunca via o resultado da luta.

[Causa]
A resolução lazy (`GuildBattles::resolveDueDungeonBattle()`) corria apenas dentro
de `Live::accountState()`, que é usado pelo `syncGame`. Mas o `autoLoginUser` tem
a sua própria lógica de boot, separada do `accountState()` — não chamava a
resolução nem anexava o estado da batalha (`finished_guild_dungeon_battle`,
`guild_dungeon_battle_rewards`).

[Correcao]
`autoLoginUser.php`:
1. Resolve batalha pendente ANTES dos overlays:
   ```php
   $preGuildId = Db::value('SELECT guild_id FROM character WHERE id=?', [$charId]);
   if ($preGuildId > 0 && GuildBattles::resolveDueDungeonBattle($preGuildId))
       $char = Character::load($char->id());
   ```
2. Anexa estado da batalha via `Live::attachGuildBattleState($data, $guild, $char)`.

[Ficheiros]
- server-laravel/actions/autoLoginUser.php
- server-laravel/app/HeroZero/Live.php (attachGuildBattleState — novo método extraído)

 ----------------------------------------------------------------------------
## BUG-000042 (claude opus 4.6) — joined_character_profiles era lista em vez de mapa
[Sintoma]
Ao abrir a batalha da guilda, o cliente crashava com `"c is undefined"` na
linha 2114 do HeroZero.min.js.

[Causa]
`Oe.getCharacter()` faz `d.fromJsonString(joined_character_profiles).get_keys()`
e itera com `b.getTypedObject(L)` — espera um MAPA indexado pelo id do
personagem (`{"25295": {...}, "25296": {...}}`), não uma lista (`[{...}, {...}]`).
Com uma lista, `get_keys()` devolve índices numéricos e `getTypedObject` não
encontra os dados correctos.

[Correcao]
`GuildBattles::joinedProfiles()` — método novo que constrói o mapa no formato
oficial (`GuildDungeonBattle::characterAppearance`): chave = character id,
valor = objecto com profile, position, stats totais e aparência completa
(incluindo identifiers de roupa equipada).

[Ficheiro]
- server-laravel/app/HeroZero/GuildBattles.php (joinedProfiles)

 ----------------------------------------------------------------------------
## BUG-000043 (claude opus 4.6) — Falta de damage_bonus nos perfis → crash no tooltip
[Sintoma]
Ao passar o rato sobre um lutador durante a batalha da guilda, o cliente crashava
com `"Error in DataObject! Tried to retrieve unknown field: damage_bonus"`.

[Causa]
O tooltip da batalha (`pc.getStatGuildBattleTooltip`) faz `getInt("damage_bonus")`
no perfil do lutador. Os perfis gerados pelo servidor não incluíam esta chave.

[Correcao]
Adicionado `damage_bonus` (= strength + weapondamage) aos perfis NPC em
`simulateDungeonBattle()` e aos perfis dos atacantes em `joinedProfiles()`.

[Ficheiro]
- server-laravel/app/HeroZero/GuildBattles.php

 ----------------------------------------------------------------------------
## BUG-000044 (claude opus 4.6) — Rounds da guilda com 'a'/'b' em vez de ids de perfil
[Sintoma]
Ao reproduzir a batalha, os golpes não eram atribuídos aos lutadores correctos —
o cliente não reconhecia os lados.

[Causa]
`Fight.php` emitia rounds com chaves `'a'`/`'b'` (formato de duelo). Nas batalhas
de guilda, o formato oficial usa o ID DO PERFIL como chave do atacante/defensor
em cada round (ex: `{"a":"25295","d":"0_npc_adventurer-0","r":2,"v":1749}`).

[Correcao]
`Fight.php` — adicionado modo guilda (3.º argumento do construtor, `$guildMode`).
Quando activo, os rounds usam os `profile` dos dois lados como chaves `'a'`/`'d'`,
em vez de `'a'`/`'b'` fixos.

[Ficheiro]
- server-laravel/app/HeroZero/Fight.php

 ----------------------------------------------------------------------------
## BUG-000045 (claude opus 4.6) — finished_guild_dungeon_battle_id não gravado → diálogo nunca abria
[Sintoma]
Após a resolução da batalha, os membros entravam na team mas o diálogo de
resultado nunca aparecia.

[Causa]
O cliente só abre o diálogo se
`character.finished_guild_dungeon_battle_id == finished_guild_dungeon_battle.id
&& status == 3`. O servidor não gravava `finished_guild_dungeon_battle_id` nos
personagens participantes após a simulação.

[Correcao]
`GuildBattles::simulateDungeonBattle()` — após resolver a luta, grava
`finished_guild_dungeon_battle_id = <battle_id>` em cada personagem participante.
O `claimGuildDungeonBattleReward` repõe o valor a 0.

[Ficheiro]
- server-laravel/app/HeroZero/GuildBattles.php (simulateDungeonBattle, ~L181)

 ----------------------------------------------------------------------------
## BUG-000046 (claude opus 4.6) — Resolução corria depois dos overlays → flag desatualizada
[Sintoma]
No boot/sync, a resolução lazy da batalha corria, mas o `character` devolvido ao
cliente ainda tinha `finished_guild_dungeon_battle_id = 0` (valor antigo).

[Causa]
Em `Live::accountState()`, a resolução lazy corria DEPOIS dos overlays do
character. O `overlayCharacter()` já tinha sido chamado com o valor antigo (0) da
DB — a resolução actualizava a DB mas o character já estava montado.

[Correcao]
Resolução movida para o TOPO do `accountState()` (e do `autoLoginUser.php`),
ANTES dos overlays. Após resolver, o character é recarregado da DB
(`Character::load()`) para apanhar o novo `finished_guild_dungeon_battle_id`.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (accountState)
- server-laravel/actions/autoLoginUser.php

 ----------------------------------------------------------------------------
## BUG-000047 (claude opus 4.6) — Campo rewards em falta → crash ao pular batalha da guilda
[Sintoma]
A batalha da guilda abria e a animação tocava, mas ao clicar "pular" o cliente
crashava com `"Error in DataObject! Tried to retrieve unknown field: rewards"`.

[Causa]
Dois problemas:
1. `simulateDungeonBattle()` não gravava o campo `rewards` na tabela
   `guild_dungeon_battle` (o UPDATE não incluía a coluna).
2. `Live::attachGuildBattleState()` não incluía `rewards` no objecto
   `finished_guild_dungeon_battle` enviado ao cliente.

O cliente chama `xo.get_rewards()` → `getString("rewards")` ao clicar "pular"
(via `Oe.get_reward()` → `Oe.get_isWon()` → `F.battleFinished()`). Sem a chave,
o DataObject lança excepção.

O campo `rewards` é um JSON string que o cliente parseia com `new ec(a)` (classe
Reward): `{"coins":X,"guild_honor":Y}`. Se a string for vazia, `get_reward()`
devolve null e `get_isWon()` retorna false (batalha perdida).

[Correcao]
1. `GuildBattles::simulateDungeonBattle()` — constrói `$rewardsJson`:
   - Guilda venceu: `{"coins":1,"guild_honor":1}` (valores simbólicos; as
     recompensas reais por personagem vão em `guild_dungeon_battle_rewards`)
   - Guilda perdeu: string vazia
   Adicionado `rewards=?` ao UPDATE da batalha.
2. `Live::attachGuildBattleState()` — adicionado `'rewards' => (string)($fb['rewards'] ?? '')`
   ao objecto `finished_guild_dungeon_battle`.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php (simulateDungeonBattle)
- server-laravel/app/HeroZero/Live.php (attachGuildBattleState)

 ----------------------------------------------------------------------------
## BUG-000048 (claude opus 4.6) — Coluna stat_base_start em falta na tournament_snapshots
[Sintoma]
Ao fazer login, o servidor crashava com `SQLSTATE[42S22]: Column not found: 1054
Unknown column 'stat_base_start'` — erro apanhado como `response_errorIO`.

[Causa]
`Tournament::ensureSnapshot()` fazia INSERT com a coluna `stat_base_start`, que
não existia na tabela `tournament_snapshots` do schema.

[Correcao]
Adicionada a coluna `stat_base_start INT NOT NULL DEFAULT 0` à tabela
`tournament_snapshots` no `01_schema.sql` e via ALTER TABLE na DB ao vivo.

[Ficheiro]
- server-laravel/docker/initdb/01_schema.sql

----------------------------------------------------------------------------
## BUG-000049 (claude opus 4.6) — Admin panel perde sessão e redireciona para login

[Sintoma]
Após login no painel admin (porta 8001), ao navegar para qualquer sub-página
(ex: /chars/25295/items) o utilizador era redirecionado de volta para /login
passados poucos segundos.

[Causa]
O config `session.php` do admin-laravel tinha default `database` para o session
driver. Apesar de o `.env` definir `SESSION_DRIVER=file`, o Laravel não estava
a ler o valor do `.env` (config cacheado ou race condition no boot). Como a
tabela `sessions` não existe na base de dados, o handler falhava com
`SQLSTATE[42S02]: Table 'herozero.sessions' doesn't exist` e a sessão era
destruída a cada request, forçando re-login.

[Correcao]
Alterado o default do config de `database` para `file`:
  `'driver' => env('SESSION_DRIVER', 'file'),`
Executado `php artisan config:clear` para limpar cache.

[Ficheiro]
- admin-laravel/config/session.php (linha 21)

 ----------------------------------------------------------------------------
## BUG-000050 (ox-alpha) — Prêmios dos torneios semanais não apareciam / trocados
**Sintoma:** Na barra do torneio, os tabs Experiência e PVP não mostravam prêmios
e o tab Pontos de Habilidade mostrava valores errados.
**Causa:** O cliente desenha os prêmios APENAS a partir dos constants do cliente
`tournament_rewards` e `global_tournament_rewards` (Nn.getRewardString filtra por
tournament_type). Os templates (autoLoginUser/initGame) trazem essas chaves a
`null`, que sobrescrevia o catálogo do CDN; e o `Live::tournamentRewardConstants()`
era código morto (nunca chamado) com `REWARDS_PUBLIC` usando tipos errados —
o tipo 6 no cliente é "HeroCon Solo", NÃO habilidades (habilidades = tipo 2,
reward_type 3 = stat points; XP = tipo 1 com reward_type_2 = 3; PvP = aba global
tipo 7 e lê `global_tournament_rewards` tipo 1).
**Correção:**
- `Tournament.php`: `REWARDS_PUBLIC` substituído por `CLIENT_TOURNAMENT_REWARDS`
  (31 entradas, tipos 1–6) e `CLIENT_GLOBAL_TOURNAMENT_REWARDS` (8 entradas),
  valores oficiais extraídos de `assets/data/constants_json.data` (verificados
  iguais ao CDN).
- `Live.php`: `withLocalConstants()` injeta ambos os catálogos por atribuição
  direta (+= não sobrescreve as chaves null dos templates).
- `public/js/HeroZero.min.js` (patch cliente): o `Fc.refreshTicker` deste build
  só desenha prêmios na barra para a aba tipo 2 (Habilidades); XP (tipo 1) e
  PvP global (tipo 7) mostravam apenas "Final do torneio". Patch:
  caso 1 passa a usar `Nn.getRewardString(1,rank)` (top1/2/3/10/100, igual ao
  caso 2) e o caso 7 itera `CGlobalTournamentReward` (Pj) com o texto de locale
  `ticker/global_tournament1_reward` ("%1º-%2º colocados: %3% Pontos de
  Experiência"). Backup pré-patch: `HeroZero.min.js.bak-pre-ticker`.
**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000051 (ox-alpha) — Verde de "online" não acendia (team + rankings + perfis)

[Sintoma]
1) Painel da team: o ponto verde de "online" nunca acendia, mesmo com membros
   a jogar activamente (regressão do BUG-000030, que estava marcado como
   corrigido).
2) Ranking de players (leaderboard): TODOS os jogadores apareciam sempre
   offline.
3) Torneios semanais: ninguém aparecia online na lista de classificados.
4) Perfis/oponentes (duelos, liga): cartão do adversário mostrava sempre
   offline.

[Causa raiz — conflito de relógios após FEAT-001 (GameTime)]
O FEAT-001 converteu as leituras/comparações de tempo para `GameTime::now()`
(= epoch + uptime acumulado do servidor + offset de debug), mas ficou um
ESCRITOR com relógio cru:

  GameController.php (throttle de ts_last_action):
      UPDATE `character` SET ts_last_action = time() ...   ← RELÓGIO REAL

enquanto os LEITORES comparavam com o relógio de jogo:

  Live::guildMembers():
      'online_status' => (GameTime::now() - ts_last_action) < 300 ? 1 : 2

Como `GameTime::now()` = epoch + uptime ACUMULADO entre sessões, bastaram
~5 minutos de uptime somado do servidor para que `now() - ts_last_action`
ultrapassasse o threshold de 300s PARA SEMPRE — nenhum personagem voltaria
nunca a aparecer online, porque a diferença só cresce. O BUG-000030 corrigiu a
DERIVAÇÃO do status mas não unificou os relógios; a regressão silenciosa só se
manifestou quando o uptime acumulado passou os 5 minutos.

[Causas secundárias — hardcoded offline]
- `Live::leaderboardCharacters()`: `'online_status' => 2` HARDCODED → ranking
  de players (retrieveLeaderboard/fallbacks do leaderboard.php) sempre offline,
  independentemente do relógio.
- `Tournament::leaderboard()`: derivava o status mas com threshold de 60s
  (inconsistente com os 300s usados na team).
- `Live::requestedCharacter()` (perfis de outros players, oponentes de duelo/liga):
  `'online_status' => 2` HARDCODED.
- `Character::duelOpponents()`: `'online_status' => 2` HARDCODED.
- `actions/leagueMissed.php` ($opponentSummary): `'online_status' => 2` HARDCODED.

[Correção — regra: UM relógio (GameTime), UM threshold (300s) para presença]
1) `GameController.php`: gravação passou a
       `ts_last_action = GameTime::now()`, throttle `WHERE ts_last_action < GameTime::now() - 30`
   (throttle de 30s mantido para não martelar a DB a cada poll).
2) `Live::guildMembers()`: já correto (300s no GameTime) — sem alteração.
3) `Live::leaderboardCharacters()`: `online_status` derivado de
   `ts_last_action`; adicionados `ts_last_online` e `last_action` (minutos),
   como o cliente espera (`DOGuildMember.get_isOnline` = online_status==1).
4) `Live::requestedCharacter()`: deriva do `$char->row['ts_last_action']`.
5) `Character::duelOpponents()` e `leagueMissed.php`: idem (SELECT * já trazia
   a coluna).
6) `Tournament::leaderboard()`: threshold 60s → 300s.

[Efeitos colaterais conhecidos]
- Personagens que não logarem após o fix continuam com `ts_last_action` gravado
  no relógio antigo até à primeira acção → aparecem offline UMA vez, depois o
  valor é reescrito no relógio de jogo (comportamento correcto).
- Se alguém usar `/servertime add_days/add_hours` (offset de debug grande),
  TODOS os restantes parecem offline até reset ao offset — consequência natural
  do relógio de jogo avançar; não é bug.

[Ficheiros]
- server-laravel/app/Http/Controllers/GameController.php (ts_last_action com GameTime)
- server-laravel/app/HeroZero/Live.php (leaderboardCharacters + requestedCharacter)
- server-laravel/app/HeroZero/Tournament.php (threshold do leaderboard)
- server-laravel/app/HeroZero/Character.php (duelOpponents)
- server-laravel/actions/leagueMissed.php ($opponentSummary)

**Estado:** ✅ Corrigido


 ----------------------------------------------------------------------------
## BUG-000052 (ox-alpha) — Relógio da topbar ignorava o offset de debug do GameTime

[Sintoma]
Após avançar o tempo do servidor (ex.: `/servertime add_hours=13` ou script que
põe a hora de jogo nas 17:00), o relógio da topbar (`public/index.html`)
continuava a mostrar a hora antiga (ex.: "02:07") em vez da hora de jogo nova.

[Causa]
O `serverClock()` do `index.html` renderizava a hora a partir de
`uptime_seconds % 86400` devolvido pelo `/servertime`:
    var total = uptimeSec + elapsed;
    var inDay = total % 86400;  → hora
Mas `uptime_seconds` = `GameTime::uptime()` NÃO inclui o offset de debug — só
`GameTime::now()` (= epoch + uptime + offset) o inclui. O endpoint até devolvia
`server_time` e `offset_seconds`, mas o JS nunca os usava. Resultado: qualquer
manipulação de tempo por offset não se refletia no relógio visível (o jogo em si
ficava certo, só a topbar é que mentia).

[Correção]
- O relógio passa a basear-se em `server_time` (GameTime::now(), já com offset)
  + elapsed local desde o último sync: `new Date((server_time + elapsed) * 1000)`
  formatado no fuso Europe/Lisbon via `Intl.DateTimeFormat`.
- "Dia X" passou a derivar de `(server_time - epoch) / 86400` (inclui o offset,
  consistente com a data mostrada; sem offset coincide com `gameDay()`).
- Mostra também a data dd/mm/aaaa ao lado do dia.
- Re-render a cada 10s (antes 60s) para o minuto não ficar velho entre syncs.

[Ficheiros]
- server-laravel/public/index.html (função serverClock)

Nota: hard-refresh ao browser (Ctrl+Shift+R) — o index.html fica em cache.
**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000053 (ox-alpha) — Gangue NPC não persistia entre batalhas / reroll livre

[Sintoma]
Ao atacar uma gangue e NÃO a derrotar:
1) na seleção seguinte podia aparecer outra gangue (ou a mesma "curada");
2) o botão de rodar/renovar ("novo inimigo") continuava utilizável;
3) uma nova batalha contra ela gerava NPCs com stats novos e HP cheio,
   em vez de manter os stats/HP da última batalha.

[Causa]
- `GuildBattles::simulateDungeonBattle()` gerava os 5 NPCs SEMPRE de raiz
  (stats dos participantes × dificuldade) e gravava o resultado apenas na
  própria linha `guild_dungeon_battle` — nenhum estado voltava para
  `guild_dungeon`, logo a gangue "renascia" curada a cada agendamento.
- Dentro da MESMA batalha o HP do NPC também não persistia entre membros:
  ao morrer um atacante, o seguinte enfrentava o NPC outra vez com HP cheio
  (o `profile['hitpoints']` nunca era atualizado entre iterações).
- `rerollGuildDungeonNPCTeam` não tinha qualquer condição de bloqueio.
- O cliente (`vz.refresh`) mostra sempre `_btnNewEnemy.set_visible(!0)`.

[Correção]
Estado da gangue vive em `guild_dungeon.settings`:
    npc_state       → lista dos NPCs VIVOS com stats/HP exatos da última luta
    reroll_blocked  → 1 enquanto a gangue estiver viva (batalhada e não morta)
    defeated        → 1 quando a guilda eliminou a gangue
1) `simulateDungeonBattle`:
   - carrega `npc_state`; se existir, usa esses perfis EXATOS (sem regenerar);
   - NPCs com hitpoints<=0 não ressuscitam;
   - HP do NPC persiste entre membros DENTRO da batalha (no fim de cada luta,
     `hitpoints` do NPC é atualizado com `Fight::hitpointsB()`);
   - vitória → limpa `npc_state`/`reroll_blocked`, marca `defeated`;
   - derrota → grava sobreviventes em `npc_state` +
     `guild_dungeon.npc_team_character_profiles` (seleção mostra a gangue
     ferida) e seta `reroll_blocked=1`.
2) `actions/guildDungeon.php`:
   - `getGuildDungeonNPCTeam`: se `settings.defeated`, sorteia gangue nova
     (a gangue fixa mantém-se até ser derrotada);
   - `rerollGuildDungeonNPCTeam`: lança
     `errRerollGuildDungeonNPCTeamNotDefeated` se `reroll_blocked`.
3) Patch cliente (`HeroZero.min.js`, `vz.refresh`): o botão "novo inemigo"
   só aparece quando `getIntSettingsValue("reroll_blocked",0)==0`
   (o DO `Oe` já lia settings genéricos via getIntSettingsValue).

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php (persistência de estado da gangue)
- server-laravel/actions/guildDungeon.php (bloqueio de reroll + re-sorteio pós-derrota)
- server-laravel/public/js/HeroZero.min.js (botão condicional)

**Estado:** ✅ Corrigido

### BUG-000053 — Adenda (ox-alpha, 2026-08-22): barra de HP da gangue no preview
O preview da gangue (`DialogGuildDungeonBattlePreview`, classe `Px`) só abre
quando `npc_team_character_profiles` NÃO está vazio (dispatcher do
`getGuildDungeonNPCTeam`: profiles vazios → diálogo de seleção `vz`; com
perfis → `Px`). O `Px` mostra a barra de HP (`_npcBar` = GF(total, current)),
vivos/total (`get_npcTeamMemberAliveCount/Count`) e nível mais alto.
Corrigido em `GuildBattles::simulateDungeonBattle`:
- fonte única do estado = `guild_dungeon.npc_team_character_profiles`;
- sobreviventes gravados com `start_hitpoints` = `hitpoints` — sem "rounds"
  na seleção, `Oe.endHitPoints` usa start_hitpoints como HP corrente, logo a
  barra nasce DANIFICADA (com hitpoints só, aparecia cheia);
- escala validada: `battle_hp_scale = 10` = `Fight::HP_SCALE`.
Fluxo final: gangue fresca → seleção com reroll; batalhada e viva → preview
com barra de HP e reroll bloqueado; derrotada → nova gangue.

 ----------------------------------------------------------------------------
## BUG-000054 (ox-alpha) — SyntaxError do cliente ao abrir seleção de gangues

[Sintoma]
Após uma batalha de gangue não vencida, ao abrir a seleção para agendar novo
combate: `SyntaxError: JSON.parse: expected property name or '}'` (reportado
via gameReportError, last action getGuildDungeonNPCTeam).

[Causa]
O DO do cliente lê o campo com `d.fromJsonString(get_npcTeamCharacterProfiles())`
→ `JSON.parse(string)` — o campo TEM de ser STRING JSON. O servidor enviava
ARRAY (resultado de json_decode da coluna). Com a coluna vazia funcionava por
acidente (`""==[]` é true em JS → fromJsonString devolve objeto vazio); com os
perfis dos sobreviventes (não vazios, BUG-000053), JSON.parse rebenta.

[Correção]
Todas as shapes enviam agora a coluna CRUA (string JSON):
- actions/guildDungeon.php: dungeonShape + pending shape (get/join) +
  resposta do attack;
- Live.php::attachGuildBattleState: pending_guild_dungeon_battle.
O único json_decode restante é uso interno do servidor (GuildBattles), correto.

[Ficheiros]
- server-laravel/actions/guildDungeon.php
- server-laravel/app/HeroZero/Live.php

**Estado:** ✅ Corrigido

### BUG-000053 — Adenda 2 (ox-alpha, 2026-08-22): botão "novo inimigo" invertido

[Sintoma]
Depois de derrotar a gangue, o botão de reroll ("novo inimigo", pago com
donuts) NÃO aparecia na seleção — só aparecia quando a gangue estava viva
(exatamente o contrário do pretendido).

[Causa]
O patch do `HeroZero.min.js` (`vz.refresh`) usava
    set_visible(0==getIntSettingsValue("reroll_blocked",0))
Quando a chave NÃO existe (gangue nova/derrotada), `getSettingsValue` faz
`Reflect.field` → devolve null → `Std.parseInt("null")` → null, e em
JavaScript `0 == null` é **false** → botão escondido. Com a gangue viva
(`reroll_blocked:1`), `0==1` também é false → escondido (correto por acaso),
por isso durante os testes de bloqueio parecia tudo certo; só falhava no
estado livre, onde a chave está ausente.

[Correção]
Condição trocada para coerção booleana:
    set_visible(!getIntSettingsValue("reroll_blocked",0))
- ausente → null → !null → true (visível ✓)
- 0 → !0 → true (visível ✓)
- 1 → !1 → false (escondido ✓)

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js (vz.refresh)

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000055 (ox-alpha) — NPCs da gangue apareciam com o "caminho" como nome

[Sintoma]
Na batalha/preview da gangue, os NPCs mostravam o nome tipo
"guild_dungeon_team/team11/0/npc_adventurer-0/name" em vez do nome real.

[Causa]
O cliente monta a chave de texto do nome a partir do campo `profile`
(LC.get_name / LC.get_npcIdentifier):
    npcId  = substring entre "_" e "-"
    nome   = text("guild_dungeon_team/<team>/<pos primeiro "-">/<npcId>/name")
O formato oficial de `profile` é "<idx>_<npcId>-<seccao>" com seccao ∈
always|first_half|second_half|end. O servidor enviava
"<idx>_<npcId>-<idx>" → seccao "0" → chave inexistente → o cliente mostra
a propria chave (fallback do LocText).

[Correção]
1. Extraida a composicao REAL das 28 gangues dos textos oficiais
   (`guild_dungeon_team/team<N>/...` no assets/i18n/pt_BR/text.data) para
   `server-laravel/data/reference/guild_dungeon_teams.json`
   (nome, description, won, lost, e npcIds por seccao).
2. `GuildBattles::simulateDungeonBattle()`:
   - profile = "<idx>_<npcId>-<seccao>" usando os npcs REAIS da gangue;
   - ultimo NPC (mais forte) = seccao "end"; restantes distribuidos por
     first_half/second_half; fallback para catalogo hash se a gangue nao
     tiver definicao.
3. Catálogo completo constants.npcs (270 npcs) extraído do CDN para
   `data/reference/npcs.json`.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php
- server-laravel/data/reference/guild_dungeon_teams.json (novo)
- server-laravel/data/reference/npcs.json (novo)

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000056 (ox-alpha) — NPCs da gangue todos com stats iguais + nome errado

[Sintoma]
1. Os 5 membros da gangue vinham SEMPRE com exatamente os mesmos stats
   (média × fator, copiados para os 5).
2. O nome de cada NPC aparecia como o caminho da chave de texto
   (ver BUG-000055).

[Causa]
1. A geração usava um loop que criava 5 cópias do mesmo conjunto de stats;
   nos servidores oficiais os membros vêm ordenados do mais fraco para o
   mais forte.
2. Formato do campo `profile` incorreto (BUG-000055) → chaves de texto não
   resolviam.

[Correção]
Escadinha de stats em `GuildBattles::simulateDungeonBattle()`:
- último NPC (mais forte) = média dos stats totais da equipa × fator de
  dificuldade (0.8/1.2/1.5); cada passo para trás −5% da média;
- quantidade por dificuldade: fácil 3–5, médio 3–6, difícil 4–8;
- identidade/nome via composição real da gangue
  (`data/reference/guild_dungeon_teams.json`) e profile no formato oficial
  "<idx>_<npcId>-<secção>".

Documentação completa: docs/guild_npcs_gangs/gang_generation.md
Nomes: docs/guild_npcs_gangs/npcs_names.md

**Estado:** ✅ Corrigido (validado em jogo — batalhas #17+)

 ----------------------------------------------------------------------------
## BUG-000057 (ox-alpha) — Torneios semanais: recompensas, tipos e as DUAS numerações do cliente

### Sintomas (por ordem de descoberta)
1. Clicar no badge "1" do ranking/torneios → `response_errorIO` no
   `getTournamentRewards` (HAR torneio_semanal_recompensa.har).
2. Corrigido o 1), o `claimTournamentRewards` devolvia `response_errorIO`.
3. O diálogo "Torneio da semana finalizado" mostrava só 2 blocos
   (Experiência + Pontos de Habilidade); faltava o bloco de PvP.
4. Ao "corrigir" 3) mudando a constante interna TYPE_PVP para 4, o RANKING
   de PvP passou a mostrar XP total em vez do nº de ataques.

### As DUAS numerações do cliente (causa raiz de 3) e 4))
O cliente usa numerações DIFERENTES conforme o contexto:

**a) Pedidos de leaderboard** (`retrieveCharacterTournamentLeaderboard`):
   param `type`: **1 = XP, 2 = Pontos de Habilidade, 3 = PvP**
   (o servidor limita type ∈ [1,3]; ver actions/leaderboard.php:94).

**b) Payload de recompensas** (`getTournamentRewards` → chave
   `tournament_rewards.rewards`, objeto JSON):
   chaves: **1 = Experiência, 2 = Pontos de Habilidade, 4 = PvP/Honra**.
   O diálogo de resultados (classe `tD`) desenha EXATAMENTE três blocos
   fixos com os tipos 1, 2 e 4:
     - bba(info1, 1) → iconLevel      → caption_1 "Experiência"
     - bba(info2, 2) → iconStatPoints → caption_2 "Pontos de Habilidade"
     - bba(info4, 4) → iconGuildHonor → caption_4 "Honra do time"
   Tipo 3 é catálogo LEGADO e é IGNORADO pelo diálogo.
   Cada bloco lê `getValue(type)`, `getRank(type)` etc. do DO `fz`;
   `hasTournamentData(n)` == false esconde o bloco correspondente.

Conclusão: tipo interno/pedido 3 (PvP) ↔ chave de payload 4. NUNCA usar a
constante interna diretamente como chave do payload.

### Bugs corrigidos

**1) getTournamentRewards — response_errorIO**
[Causa] A ação acedia `$char->row['pending_tournament_rewards']`, mas
`Character::$row` é PRIVATE → Error "Cannot access private property" → o
wrapper converte em erro genérico response_errorIO.
[Correção] Novo acessor `Character::field(string $key)` e uso dele nas ações
(getTournamentRewards, claimTournamentRewards, Live.php, autoLoginUser.php).

**2) claimTournamentRewards — response_errorIO**
[Causa] SQL com backticks ESCAPADOS: "UPDATE \\`user\\` SET ..." — dentro de
string PHP double-quoted o \\` fica literal → MySQL rejeita (erro 1064).
Além disso `character` é palavra RESERVADA no MySQL 8 → precisa backticks
reais: "UPDATE \`character\` SET ...".
[Correção] Backticks limpos. NOTA: um claim que falhe A MEIO pode gravar
claimed=1/donuts pagos mas deixar pending_tournament_rewards > 0 — repor à
mão se acontecer.

**3) Bloco de PvP faltava no diálogo de recompensas**
[Causa] `end()` gravava as chaves do payload com a numeração INTERNA
(1, 2, 3) — o tipo 3 nunca é desenhado.
[Correção] `Tournament::rewardKey(int $type): int` — traduz o tipo interno
para a chave do cliente (PvP 3 → 4; restantes ficam iguais). Usado em
end() ao construir $rewardsByType.

**4) Ranking de PvP mostrava XP (regressão minha ao "corrigir" 3))**
[Causa] Mudei `TYPE_PVP` para 4 globalmente → o pedido de ranking com
type=3 deixou de bater com TYPE_PVP e caía no default (delta de XP) em
columnsForType()/leaderboard().
[Correção] TYPE_PVP mantém-se **3** (numeração dos pedidos); a conversão
para payload faz-se APENAS via rewardKey().

### Funções relevantes (mapa rápido)
- Tournament::active()/create()/end()       — ciclo de vida do torneio semanal
- Tournament::ensureSnapshot(...)           — snapshot xp/honor/stat_base no login
- Tournament::columnsForType($type)         — coluna DB + coluna snapshot por tipo
- Tournament::leaderboard($type,...)        — ranking; PVP conta duelos:
    "(SELECT COUNT(*) FROM duel d WHERE d.character_a_id = ch.id AND d.ts_creation >= ts_start)"
  XP = delta de xp desde snapshot; STATS = delta de stat_base total
- Tournament::rewardKey($type)              — interno(1/2/3) → payload(1/2/4)
- Tournament::donutsForRank($type,$rank)    — tabela REWARDS por tipo/rank
- actions/getTournamentRewards.php          — lê linha não reclamada (pending>0)
- actions/claimTournamentRewards.php        — paga donuts, claimed=1,
  decrementa pending_tournament_rewards

[Ficheiros]
- server-laravel/app/HeroZero/Tournament.php
- server-laravel/app/HeroZero/Character.php (field())
- server-laravel/actions/getTournamentRewards.php
- server-laravel/actions/claimTournamentRewards.php
- server-laravel/actions/autoLoginUser.php, app/HeroZero/Live.php ($char->row)

**Estado:** ✅ Corrigido (ranking PvP validado: conta ataques; payload com chaves 1/2/4)

 ----------------------------------------------------------------------------
## BUG-000058 (ox-alpha) — Crash no login com defesa de guilda pendente

[Sintoma]
Ao entrar na conta que tem uma DEFESA de guilda agendada:
"TypeError: can't access property get_name, a.get_pendingDefenseGuildBattle()
.get_attacker() is undefined" (team.har).

[Causa]
O cliente resolve os objetos de guilda dos dois lados da batalha a partir da
chave `guild_battle_guilds[]` da resposta. Só enviávamos essa chave para
batalhas TERMINADAS — nos pendentes faltava → get_attacker() devolve
undefined e o jogo crasha no autoLoginUser.

[Correção]
Live::attachGuildBattleState: ao enviar `pending_guild_battle_attack/defense`,
incluir também `guild_battle_guilds[]` com a shape da guilda ADVERSÁRIA.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000059 (ox-alpha) — Hora de agendamento desalinhada + diálogo de resultado nunca abria

[Sintoma]
1. No diálogo de ataque, o slot mostrava "hoje 22:00" mas a batalha ficava
   marcada para AMANHÃ 22:00.
2. A batalha guilda-vs-guilda resolvia no servidor mas o diálogo de resultado
   (Cj) nunca abria.

[Causa]
1. O cliente calcula os slots de ataque em frame **UTC**
   (`Uj.getGuildBattleAttackTimestamp`: `get_serverDateTime` aplica o offset
   do browser AO CONTRÁRIO → horas em UTC). O servidor PHP agendava em hora
   de Lisboa → 1h de diferença na fronteira da janela de preparação (12:00).
2. O gatilho do diálogo é `character.finished_guild_battle_attack_id` /
   `_defense_id` (`xf.checkForFinishedGuildBattles`): se != 0 e existir batalha
   terminada com esse id → abre Cj. `simulateGuildBattle` nunca preenchia
   esses campos.

[Correção]
1. `attackGuild` + `attackGuildDungeonNPCTeam`: decisão hoje/amanhã e timestamp
   em frame UTC (gmdate/gmmktime), replicando exatamente o cliente.
2. `simulateGuildBattle`: grava `finished_guild_battle_attack_id` nos
   atacantes participantes e `finished_guild_battle_defense_id` nos defensores;
   `claimGuildBattleReward` limpa o campo conforme o tipo da recompensa.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php
- server-laravel/actions/guildBattle.php
- server-laravel/actions/guildDungeon.php

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000060 (ox-alpha) — Expansão das teams no "Procurar team" errada (600%/900%)

[Sintoma]
No ranking (aba Teams) a coluna de expansão aparecia correta (sonnet46 ~80%,
oxAlpha 10%), mas ao abrir "Procurar team" com um herói SEM team os valores
vinham errados: oxAlpha ~900% e sonnet46 ~600%.

[Causa]
Duas coisas em `getGuildList` (actions/guildState.php):
1. MÉTRICA errada: enviava OCUPAÇÃO (membros/stat_guild_capacity) quando a
   coluna mostra a EXPANSÃO da guilda — a mesma métrica do
   `retrieveGuildLeaderboard`, onde tp = (stat_guild_capacity +
   stat_character_base_stats_boost + stat_quest_xp_reward_boost +
   stat_quest_game_currency_reward_boost) / 175.
2. ESCALA errada: o cliente lê o campo via `RH.get_fullTotalPercentage() =
   Math.round(100 * get_totalPercentage())` — espera FRAÇÃO 0-1 (como o `tp`
   do ranking), não percentagem inteira; 6 → 600%, 9 → 900%.
Confirmado no capture procurar_team.har.

[Correção]
`getGuildList`: total_percentage = soma dos upgrades / 175 em fração 0-1
(min(1, ...)), igual ao ranking. O campo continua obrigatório (sem ele o
cliente crasha com DataObject: unknown field total_percentage).

[Ficheiros]
- server-laravel/actions/guildState.php

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000061 (ox-alpha) — Estatísticas da team: médias erradas, contagens a 0 e expansão divergente

[Sintoma]
1. No diálogo de estatísticas da team, as médias de habs ficavam muito abaixo
   do real (ex.: crítico médio "mil e tal" quando um dos membros tem ~25K no
   perfil). A média correta é somar as habs visíveis de todos os membros e
   dividir pelo número deles.
2. Combates lutados/ataque/defesa, ganhos/perdidos, gangues derrotadas e
   vezes que lutámos contra gangues estavam TODOS a 0 — mas o botão de
   histórico de lutas mostra os dados.
3. A percentagem de expansão das estatísticas não batia certa com o
   leaderboard nem com "procurar team".

[Causa]
1. O cliente calcula as médias SOMANDO os `stat_total_*` de cada membro da
   lista `guild_members` (Va.get_averageStrength etc.). O servidor mandava
   `base + treinado` em Live::guildMembers() — faltava equipamento e bónus
   de booster/guilda (battleProfile), que no skel valem >20K de crítico.
2. O cliente lê os contadores diretamente do objeto da guilda
   (`getInt("battles_attacked")` etc.), mas as colunas da tabela `guild`
   nunca são incrementadas; os dados vivem em `guild_battle` /
   `guild_dungeon_battle` (status=3), como prova o histórico.
3. As estatísticas usam Va.totalImprovementPercentage com o divisor OFICIAL
   `guild_percentage_total_real` = 180 (balancing_constants.json); o ranking
   dividia por 175 (`guild_percentage_total_base`) e o "procurar team" por 175
   também.

[Correção]
1. Live::guildMembers(): stat_total_* agora vem de Character::battleProfile('a')
   (base + treino + equipamento + boosts), igual ao perfil.
2. Live::shapeGuild(): contadores calculados AO VIVO —
   battles_attacked/defended/won (+ lost por subtração) de `guild_battle`
   status=3; dungeon_battles_fought/won de `guild_dungeon_battle` status=3,
   com won = rewards pagos em moedas (mesmo critério do histórico). Com
   JSON_VALID porque há linhas com rewards='' (JSON_EXTRACT crashava).
3. Divisor 175 → 180 em retrieveGuildLeaderboard e getGuildList
   (total_percentage em fração 0-1), alinhando os três ecrãs com as
   estatísticas.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php
- server-laravel/actions/leaderboard.php
- server-laravel/actions/guildState.php

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000062 (ox-alpha) — Lutador da batalha de guilda continuava a lutar com 0 HP visível

[Sintoma]
Na batalha de guilda #15 (sonnet46 vs oxAlpha), o Debug (defensor) ficava com a
barra de HP a zero a meio da reprodução mas CONTINUAVA a lutar e só "morria"
muitos rounds depois. O servidor considerava-o vivo; o cliente mostrava-o vazio.
Regressão introduzida pelo sistema de troféus (penalização de skills nas
batalhas guilda-vs-guilda).

[Causa — dois problemas empilhados]

1) POOL DO SERVIDOR ≠ BARRA DO CLIENTE:
   `Character::battleProfile()` grava `'hitpoints' => stamina × Fight::HP_SCALE`
   usando a stamina BRUTA. O scaling dos troféus (`GuildBattles::loadSide`)
   reduzia os 4 stats (stamina/strength/criticalrating/dodgerating) MAS NUNCA
   recalculava o `hitpoints`. Como o cliente deriva a barra do perfil ENVIADO
   (`Q.getHitPoints = round(battle_hp_scale × stamina)` = stamina ESCALADA ×10),
   os dois lados discordavam:
       Debug: stamina bruta 15300 -> pool servidor 153000
              stamina escalada 13770 -> barra cliente 137700
   Dano total recebido nas 2 lutas: 146604 -> servidor deixa-o vivo até
   153000-146604 = 6396 (exatamente o valor na BD); a barra do cliente esvazia
   aos ~137700 (rounds 20-22) e ele continua visivelmente a lutar a 0.

2) CARRYOVER DE HP ENTRE LUTAS 1v1 INREPRESENTÁVEL NO CLIENTE:
   `simulateGuildBattle` carregava o HP restante do vencedor para a luta
   seguinte (`hitpoints = max(1, $fight->hitpointsA/B())`). Mas o cliente
   reinicia as barras ao TOTAL a cada matchup (`_barB.init(get_totalHitPoints())`,
   que cai no getHitPoints(stamina)) e reproduce TODOS os rounds somando o dano
   gravado — dano carregado de uma luta anterior não tem onde aparecer e a barra
   repassa pelo zero MESMO com as piscinas iguais (146604 > 137700 nesta
   batalha). O Debug entrou na 2ª luta com 6396/153000 no servidor; invisível
   para quem via a barra.

[Correção] (GuildBattles::simulateGuildBattle, e só ela)
1) No `$loadSide`, depois do loop que escala os 4 stats:
       $p['hitpoints'] = (int)$p['stamina'] * Fight::HP_SCALE;
   — o pool do servidor passa a ser IGUAL à barra desenhada pelo cliente.
2) Sem carryover: cada confronto 1v1 começa com os DOIS lados à vida cheia;
   no fim só se marcam os DERROTADOS (`hitpoints = 0`); sobreviventes mantêm o
   pool cheio derivado da própria stamina. Removidos `$aHp/$dHp` e os updates
   `max(1, hitpointsA()/B())` no loop de lutas.
   Invariante novo nos perfis persistidos: `hitpoints ∈ {0, stamina×10}`
   (assertado no cenário F de scripts/testing/trofeus/test_effects.php).

Nota: as batalhas JÁ resolvidas (como a #15) ficam como estão na BD — a
correção aplica-se às próximas resoluções.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php (loadSide + loop de lutas)
- scripts/testing/trofeus/test_effects.php (cenário F, regressão)
- docs/trofeus/implementacao.md (§9.2 + gotchas 6/6b)

**Estado:** ✅ Corrigido (verificado: test_effects FALHAS=0, perfis gravados
com hitpoints = stamina escalada ×10 nos sobreviventes e 0 nos derrotados)

### Reabertura — 2026-10-24: histórico ainda somava confrontos diferentes

[Sintoma]
Na batalha #8 da ASTRA, TripeiroUrbano_1992 aparecia a zero juntamente com
o último adversário, embora tivesse vencido e a laliene não tivesse entrado.

[Causa]
O backend já reiniciava a vida em cada confronto, mas `GuildBattle.endHitPoints`
no cliente descontava todos os golpes recebidos pelo mesmo herói no combate
inteiro. O Tripeiro recebeu 1.859 de dano em quatro confrontos, acima dos seus
1.320 HP; no último recebeu apenas 1.066, ficando com 254 HP.

[Correcção]
O cálculo do cliente reinicia os HP quando muda o adversário e limita a zero
o valor mínimo apresentado. Mantém o índice global de rounds para reproduções
parciais e todos os componentes de dano, recuo e cura. Atualizada a versão do
bundle em index.html para invalidar a cache do navegador.

[Validação]
Teste JavaScript com confrontos sucessivos, limite de rounds, dano, recuo, cura
e herói que não lutou; confronto real #8 validado por leitura da BD.
Confirmação visual após recarregar o navegador pendente.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- scripts/testing/test_guild_history_hp.js
- docs/Guilda/guild_vs_guild_battles.md

 ----------------------------------------------------------------------------
## BUG-000063 (claude sonnet 4.6) — Crash ao ver batalha de guilda com walkover (nenhum defensor registado)

[Sintoma]
Ao carregar o jogo após uma batalha de guilda onde a equipa defensora não
tinha nenhum membro registado, o cliente crashava imediatamente no
`autoLoginUser` com:

    TypeError: can't access property "get_attacker", a is undefined
    (HeroZero.min.js, linha 14678, coluna 405)

O crash acontecia antes de qualquer interação do utilizador.

[Causa]
Quando `defender_character_ids=[]`, o loop de `simulateGuildBattle` sai
imediatamente (`0 >= count([])` → true) com `$winner = 1` e `$rounds = []`.
O campo `rounds` fica gravado como `"[]"` na tabela `guild_battle`.

Na resposta ao `autoLoginUser`, `Live::attachGuildBattleState` enviava
`finished_guild_battle_attack` com `rounds="[]"`. O cliente tentava
aceder a `rounds[0].get_attacker()` → `undefined` → crash.

Adicionalmente, `defender_character_profiles` também ficava `"[]"` (array
JSON vazio em vez de objeto), mas o crash no campo `rounds` impedia de
chegar a ler os perfis.

[Correção]
Em `Live::attachGuildBattleState` (Live.php), antes de construir o shape da
batalha terminada, verifica-se se `rounds === '[]'`. Se sim:

1. As moedas da `guild_battle_rewards` são creditadas diretamente ao personagem.
2. A linha de reward é apagada da tabela.
3. `finished_guild_battle_attack_id` (ou `_defense_id`) é zerado no personagem.
4. A batalha é ignorada no response — sem `finished_guild_battle_attack` enviado.

O cliente não recebe o diálogo de resultado (não há batalha para mostrar),
mas as moedas são creditadas automaticamente. Comportamento correto para
walkover: vitória automática sem replay.

Batalhas walkover já existentes na BD (ex.: batalha #19) são tratadas da
mesma forma na próxima sincronização.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (attachGuildBattleState)
- docs/Guilda/guild_vs_guild_battles.md (§4, nota walkover)

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------
## BUG-000064 (claude sonnet 4.6) — Ganhar batalha contra guilda sem troféus criava troféu novo do servidor

[Sintoma]
Ao atacar e vencer uma guilda que não tinha troféus, o vencedor recebia
um troféu novo emitido do pool do servidor (artifact_stolen=false), mesmo
que não houvesse nada a roubar. Isso tornava possível aumentar
indefinidamente o número de troféus em circulação sempre que um fosse
apagado/libertado — contrariando o modelo de pool fixo.

[Causa]
`GuildArtifacts::resolveBattleTransfer` tinha dois passos:
1) Roubo: pegar um troféu ao perdedor (se tiver + vencedor tiver slot livre).
2) Emissão: se o ataque ganhou + slot livre + pool < 50 → criar troféu novo.

O passo 2 foi herdado do comportamento oficial (bootstrap do servidor do
zero), mas num servidor já em funcionamento com troféus distribuídos cria
troféus "extra" sempre que o perdedor não tem nenhum, quebrando o pool fixo.

[Correção]
Removido o bloco de emissão (passo 2) de `resolveBattleTransfer`.
Troféus apenas circulam entre guildas: vencedor rouba ao perdedor se este
tiver algum; caso contrário nenhum troféu muda de mãos.
A introdução de troféus no servidor faz-se exclusivamente via seed/admin.

[Ficheiros]
- server-laravel/app/HeroZero/GuildArtifacts.php (resolveBattleTransfer)
- docs/trofeus/implementacao.md (§9.9 gotcha 3, fluxo, testes)

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------
## BUG-000065 (ox-alpha) — Botão "Ingressar" da liga não fazia nada

[Sintoma]
Ao clicar no botão de ingressar na liga, nada acontecia: o painel continuava
no estado "não inscrito". O HAR (`liga_entrar.har`) mostrava que o
`enterLeagueDivision` respondia CORRETAMENTE (error:"", grupo 100000,
personagem atualizado) — o servidor não falhava.

[Causa]
O cliente guarda/faz parse de `character.league_opponents` como JSON ARRAY:
`get_leagueOpponentIds()` → `getFromJson(league_opponents, [])` → `JSON.parse`.
O servidor guardava a coluna como string separada por vírgulas
(`"25295,25294,25297"`), que não é JSON válido → lista vazia →
`get_hasLeagueOpponentIds()` falso → `get_canDoLeagueFights()` falso PARA
SEMPRE (a decisão é toda client-side; a resposta do enter nunca corrigia isto).

[Correção]
1. `Live::leagueOpponents()` escreve agora `"[25295,25294,25297]"` (com
   colchetes).
2. `Live::savedLeagueOpponentIds()` lê com `json_decode`, mantendo fallback
   para o formato legado sem colchetes.
3. Migração one-time na BD viva (idempotente):
   `UPDATE character SET league_opponents = CONCAT('[', ..., ']') WHERE ... NOT LIKE '[%'`.
Detalhe em docs/league/league.md (§1.3, §2.9, §3.5).

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (leagueOpponents, savedLeagueOpponentIds)

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------
## BUG-000066 (ox-alpha) — Barra de adrenalina não mudava ao comprar energéticos de liga

[Sintoma]
Depois de comprar um energético (booster_league1/booster_league2) na loja da
liga, a barra de adrenalina continuava com o máximo antigo (20) — nem relogin
atualizava.

[Causa]
`buyLeagueBooster` só empilhava o prazo (`ts_active_league_boost_expires`);
o novo máximo da barra (league1→60, league2→120) ficava para a "próxima
sincronização", mas essa só corria dentro do `startLeagueFight`
(`League::syncStamina`). E o cliente NÃO deriva o máximo do booster ativo:
lê diretamente o campo `character.max_league_stamina`
(`ep.get_maxLeagueStamina` no bundle). Logo qualquer resposta fora das ações
de liga devolvia o valor velho.

[Correção]
`Live::accountState()` (chokepoint de praticamente todas as respostas) passou
a correr `League::syncStamina` sobre a linha do personagem ANTES de montar os
overlays; se a sincronização gravou algo, o `Character` é recarregado. Isto
persiste o novo máximo logo na resposta da compra e auto-expira boosters em
qualquer pedido. Testado via HTTP comprando `booster_league1` com um
`league2` ativo: resposta trouxe 60/60 (substituição de booster + clamp da
barra 120→60) e o pedido seguinte manteve o valor persistido.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (accountState)
- server-laravel/actions/boosterExtra.php (buyLeagueBooster, inalterado — só
  empilha prazo mesmo, o sync é quem deriva o máximo)
- docs/league/league.md (§1.7, §2.2, §3.6)

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------

## BUG-000067 (ox-alpha) — Zona 2 não desbloqueava ao chegar ao nível 10

[Sintoma]
Conta nova chegou ao nível 10 e a zona 2 continuava trancada. O próprio
cliente contradizia o servidor: o ecrã de missões mostrava a zona com o
tooltip "desbloqueia ao nível 10" (e o ecrã do personagem contava os níveis
que faltavam), mas viajar era impossível — nem relogin abria a zona.

[Causa]
No servidor, `max_quest_stage` começava em 1 e tinha UM único ponto de
subida: o claim da dungeon de história (`claimStoryDungeonStepReward` fazia
`max_quest_stage = index+1`). O nível não influía em nada — abrir a zona 2
exigia completar a dungeon da zona 1.

O cliente, porém, trata o desbloqueio como coisa do NÍVEL:
- tranca a zona apenas quando `stage > max_quest_stage`, e o tooltip usa
  `Q.getQuestStageUnlockLevel(stage)` → `stages[stage].min_level` dos
  constants oficiais;
- os constants do CDN (`constants_json.data`) trazem
  `stages = {1:{min_level:0}, 2:{min_level:10}, 3:20, …, 14:300}`.

Ou seja: oficialmente a zona abre quando o nível chega ao `min_level`; a
cadeia `after_story_dungeon` liga as DUNGEONS entre si (a dungeon da zona N+1
exige a da zona N), nunca as zonas. A dungeon da zona 1 tem `min_level: 9` —
quase sobreposto ao nível da zona 2 (10), o que tornava o bug óbvio em jogo.

[Correção]
1. `Character::STAGE_UNLOCK_LEVELS` — tabela oficial dos 14 `min_level`
   (hardcode dos constants; mesmo padrão do `levels_xp.json` para dados
   extraídos do CDN).
2. `Character::syncLevelFromXp()` passou a fazer, depois do sync do nível,
   `max_quest_stage = GREATEST(max_quest_stage, maior zona com min_level <=
   nível)`. O early-return quando o nível não mudou foi removido APENAS para
   este bloco: o sync das zonas corre sempre, e como o overlay do personagem
   chama `syncLevelFromXp()` em todas as respostas (`Live::accountState`),
   personagens já travadas (ex.: nível 10 com `max_quest_stage=1`) ficam
   corrigidas no próximo pedido, sem migração na BD.
3. O bump no claim da story dungeon ficou como estava — o GREATEST nunca
   baixa zonas, e quem já desbloqueou assim não perde nada.

Confirmado em jogo: relogar com a conta ao nível 10 abriu a zona 2.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (STAGE_UNLOCK_LEVELS,
  maxStageForLevel, syncLevelFromXp)

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------

## BUG-000068 (ox-alpha) — Bónus de atributos da liga só valia nas lutas da liga

[Sintoma]
Herói inscrito numa divisão da liga recebia o bónus % aos stats/habilidades
base APENAS durante lutas da liga. Em missões, duelos normais, combates de
team e contra gangues — e nos totais visíveis no perfil — o bónus não
aparecia. No jogo real (sonnet46, Bronze II) subiu de divisão e os stats
subiram; noutro herói na mesma situação nada mudava fora das lutas de liga.

[Causa]
`League::applyAttributeBonus()` era chamado num único sítio, o
startLeagueFight, aplicando o fator SOBRE o perfil pronto. Nem o
`battleProfile` nem os totais do overlay sabiam da liga — logo todo o resto
do jogo usava stats sem bónus.

O contrato oficial é o contrário: o i18n pt_BR ("dialog/league_info/text2")
diz que o bónus vale "nas habilidades básicas", SEM restrição a combates de
liga; e o próprio cliente calcula a linha "+X% da liga" dos atributos
localmente (`leagueBaseStatBonus = ceil(stat_base × bonus)`) e soma-a ao
total exibido — ou seja, oficialmente é um multiplicador PERMANENTE da base,
não um modificador de combate.

[Correção]
1. O fator entrou no único ponto que alimenta tudo:
   `Character::statMultiplier() = 1 + booster/100 + guilda/100 +
   League::attributeFactor(divisão) − 1`. Como `battleProfile` (missoes,
   duelos, guildas, gangues, liga, médias de team…) e o `stat_total_*`
   do overlay usam este mesmo fator, o bónus ficou global e coerente com o
   que o cliente mostra.
2. Novo helper `Character::leagueDivision()` (espelho de
   `League::divisionFromGroup`).
3. `League::applyAttributeBonus()` ELIMINADO — reaplicar sobre o perfil
   pronto seria contar duas vezes; `attributeFactor()` documentado como
   consumido pelo statMultiplier ("NAO voltar a aplicar").
4. `leagueMissed.php` (startLeagueFight) usa perfis `battleProfile` puros,
   com comentário a explicar o porquê.
5. docs/league/league.md atualizado (§1.6 retitulado "global"; tabela §2.1).

Confirmado em jogo: subida de divisão do sonnet46 refletiu-se imediatamente
nos stats gerais; verificação tinker (div 1): mult 2.03 e
stat_total_stamina 3986 = 3938 + ceil(1600×0.03).

Nota de diagnóstico: um falso positivo à parte — o herói skel parecia "sem
bónus", mas o seu `301` era o NÍVEL (leitura desalinhada de colunas numa
consulta), estando o group id válido (500000). Servidor provou aplicar div=5,
mult 2.15.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (statMultiplier, leagueDivision)
- server-laravel/app/HeroZero/League.php (applyAttributeBonus removido;
  docblock do attributeFactor)
- server-laravel/actions/leagueMissed.php (perfis puros no startLeagueFight)
- docs/league/league.md

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------

## BUG-000069 (ox-alpha) — Liga bloqueada com "atingiu o número máximo" depois de virar o dia

[Sintoma]
Depois de virar o dia no relógio de jogo (reportado ao adiantar 24h o offset
de debug), o painel da liga mostrava "você não pode fazer mais duelos hoje,
atingiu o número máximo" e o botão de ataque nascia desativado após
refresh/relogin — mesmo com as lutas do novo dia disponíveis.

[Causa]
O cliente NÃO verifica datas neste limite. No bundle:
  get_leagueFightCountLeftToday = Math.max(get_maxDailyLeagueFights()
                                           − get_leagueFightCount(), 0)
  get_hasDailyLeagueFightLimitReached = 0 >= get_leagueFightCountLeftToday
(diferente de washItem/shopRefresh/leagueOpponentRefresh, que usam
`serverDateTime.isToday(ts)`.) Ou seja, cabe ao servidor servir o contador
JÁ ZERADO quando o dia muda. O nosso `League::syncDailyCount` só corria
dentro do startLeagueFight (`leagueMissed.php`) — inalcançável com o botão
desativado — enquanto o boot (`autoLoginUser`) e o `Live::accountState`
ecoavam o `league_fight_count` cru da BD (ex.: 20 de ontem).

[Correção]
1. `Live::accountState`: o SELECT da liga passou a incluir
   `league_fight_count`/`ts_last_league_fight` e corre `syncDailyCount` na
   MESMA passagem do `syncStamina`; se gravou em BD, relê o personagem
   antes dos overlays.
2. `autoLoginUser.php`: mesmo sync ANTES dos overlays — o boot não passa
   pelo accountState e era ele quem servia o contador velho num refresh.
(`syncDailyCount` já existia; apenas passou a correr onde o estado é
servido.)

Verificado via tinker: `count=20` marcado "ontem" → resposta devolve
`league_fight_count=0` e BD zerada, tanto no accountState (sonnet46) como no
boot (conta convidada 25328, cujo rotate de sessão é no-op, para não
perturbar sessões reais).

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (accountState)
- server-laravel/actions/autoLoginUser.php

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------

## BUG-000070 (ox-alpha) — Lutas da liga contra membros da mesma team davam/retiravam pontos

[Sintoma]
Ao atacar um membro da própria guilda ("team") na liga, quem ganhava recebia
pontos de liga e quem perdia perdia — oficialmente NENHUM dos dois pode
ganhar ou perder pontos nesses combates (as moedas mantêm-se).

[Causa]
O `startLeagueFight` calculava `eloWin`/`eloLoss` sem considerar as guildas.
A regra existe no cliente: `getLeagueFightResultTooltip` só invoca
`Dd.leagueFightLeaguePointsWinReward/LostReward` quando
`!opponent.get_isMyGuildMember()`; texto oficial pt_BR
("screen/league_fight/tooltip_same_team"): "Quando você lutar com um membro
do time, nenhum de vocês ganhará ou perderá pontos da liga." Colegas de
guilda aparecem legitimamente na lista de oponentes (o sorteio em
`Live::leagueOpponents` não filtra por guilda — exclui apenas o próprio),
logo o caso é frequente em guildas pequenas.

[Correção]
- SELECT do oponente no `startLeagueFight` passou a incluir `guild_id`.
- `$sameTeam` = mesma guilda não nula dos dois lados → `league_points = 0`
  nas recompensas de AMBOS os lados (vitória ou derrota); moedas e
  multiplicadores intactos. Como as recompensas são calculadas e gravadas no
  INÍCIO da luta, o claim e o crédito imediato ao defensor herdam a regra
  automaticamente; com +0 pontos a histerese de mudança de divisão não dispara.

[Ficheiros]
- server-laravel/actions/leagueMissed.php (regra same-team nas recompensas)
- docs/league/league.md (§1.5)

**Estado:** ✅ Corrigido
 ----------------------------------------------------------------------------

## BUG-000071 (ox-alpha) — Ordem de entrada nas batalhas de guilda ignorava nível/formação

[Sintoma]
Nas batalhas contra gangues, membros inscritos nunca apareciam nos rounds
(ex.: batalha #30: laliene inscrito em 2º lugar e sem um único golpe — entrou
em último porque o SQL devolvia os ids por ordem crescente de id). Nas
batalhas entre teams a ordem era sempre stat_total DESC, ignorando por
completo a formação escolhida no diálogo de táticas da guilda.

[Causa]
`GuildBattles::simulateDungeonBattle` ordenava os inscritos pela ordem de
retorno do `SELECT ... WHERE id IN (...)` (id crescente no InnoDB), que não
tem nada a ver com a inscrição nem com regra oficial nenhuma.
`GuildBattles::simulateGuildBattle` fazia `usort` por stat_total DESC.

Regra oficial:
- vs GANGUES: todos entram em nível MENOR → MAIOR ("Na sequência"), fixo,
  empate por pontos de habilidade; quando o último NPC cai a batalha acaba,
  mesmo que fiquem inscritos sem lutar;
- entre TEAMS: cada lado segue a FORMAÇÃO da própria guilda
  (`guild_battle_tactics_{attack,defense}_order`, constants
  "guild_battle_tactics"): 1="Na sequência" (menor nível primeiro),
  3="Fortaleza" (maior nível primeiro), 5="Confusão" (aleatória); empates
  pelos pontos de habilidade na direção da formação.

[Correção]
- Gangues: `ORDER BY level ASC, skill_points ASC, id ASC` direto no SELECT
  dos atacantes (skill_points = soma stat_base+stat_bought+stat_trained dos
  4 stats). Ordenado ANTES do `joinedProfiles`, que numera `position` pela
  mesma lista — posições do cliente coincidem com a ordem de combate.
- Teams: `loadSide()` recebe a formação lida da tabela `guild` (atacantes ←
  attack_order da guilda atacante; defensores ← defense_order da defensora)
  e aplica 1→ASC / 3→DESC / 5→shuffle; `_stat` substituído por `_skill`.
  `guildSideProfiles` continua a numerar positions pela ordem resultante.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php (simulateDungeonBattle,
  simulateGuildBattle/loadSide, guildSideProfiles)
- docs/Guilda/guild_atacar_gang_npcs.md, docs/Guilda/guild_vs_guild_battles.md

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000072 — Relogin com missão de luta pendente crashava o cliente

[Sintoma]
Ao fazer login na conta skel, o cliente crashava com
`TypeError: can't access property "getInt", a is undefined` em
`HeroZero.min.js:13455` (método `refreshFromBattleProfile`).
O erro era reproduzível a cada tentativa de login — a conta ficava
completamente inacessível. Só acontecia no skel; outras contas (laliene,
sonnet) funcionavam normalmente.

[Causa — dois problemas encadeados]
O skel tinha uma quest de luta concluída (`quest_stage13_fight3`, status=4,
`fight_battle_id=454`) cujas recompensas ainda não tinham sido reclamadas.
Isto aconteceu porque o browser foi fechado (ou fez reload) DEPOIS de a luta
ter terminado mas ANTES de o jogador reclamar as rewards.

No fluxo normal (sem interrupção), os dados da batalha chegam na resposta de
`checkForQuestComplete` e o cliente mostra a animação + recompensas de
imediato. O `autoLoginUser` nunca precisa de os ter.

Ao fazer relogin com a quest pendente:

1) **`battle` e `battles` limpos no boot** — Ambas as chaves estão no
   `ACCOUNT_STATE_KEYS` (autoLoginUser.php L28-37), que é zerado no boot para
   evitar herdar dados de outra conta. `battle` ficava `{id:0, ...}` e
   `battles` ficava `[]`.

2) **O cliente usa `battles` (plural, array), não `battle` (singular)** — No
   processamento do boot (HeroZero.min.js L3837), o cliente faz:

       if (hasData("quests"))
           if (hasData("battles"))                          // entra aqui
               for cada quest de luta:
                   quest.refreshBattle(getBattleFromId(battles, fightBattleId))
           else if (hasData("battle"))                      // nunca chega aqui
               ...

   O método `hasData("battles", true)` do TypedObject (L468) devolve `true`
   mesmo para um array vazio `[]` (porque `[] !== null`). O cliente entrava
   neste branch, procurava o battle_id 454 no array vazio via
   `getBattleFromId()`, não encontrava, e chamava `quest.refreshBattle(null)`.
   Isto guardava `null` como o objecto da batalha.

   Mais tarde, quando o cliente tentava mostrar o painel de batalha (active
   panel era "battle"), chamava
   `this._battle.get_profileA().getInt("stamina")`. Como `get_profileA()`
   delegava para `getTypedObjectFromJson("profile_a_stats")` num objecto
   `null`/`undefined`, o `a` do código minificado era `undefined` e
   `a.getInt(...)` crashava.

[Porque só no skel]
As outras contas não tinham nenhuma quest de luta com status >= 3 e
`fight_battle_id > 0` ao fazer login. No fluxo normal, o resultado da luta é
consumido pelo `checkForQuestComplete` -> `claimQuestRewards` na mesma sessão.
Só se houver interrupção (fechar browser, reload, perda de conexão) entre a
conclusão da luta e o claim é que a quest fica pendente para o próximo boot.

[Correcao]
`autoLoginUser.php`: depois do esvaziamento do `ACCOUNT_STATE_KEYS` (e antes
dos overlays de missed_duels/story_dungeon), iterar as quests do jogador. Para
cada quest de luta com status >= 3 e `fight_battle_id > 0`, carregar os dados
reais da tabela `battle` via `Character::battleData()` e acumulá-los num
array. Preencher `$data['battles']` com esse array e `$data['battle']` com o
primeiro elemento.

Isto garante que:
- `hasData("battles")` devolve `true` com dados REAIS (não array vazio);
- `getBattleFromId(battles, 454)` encontra a battle;
- `quest.refreshBattle()` recebe um objecto válido;
- `get_profileA().getInt("stamina")` funciona normalmente.

Segue o padrão já estabelecido para `missed_duels`, `season_progress` e
`story_dungeon_steps`: dados que têm tabela própria são repostos por cima do
esvaziamento do `ACCOUNT_STATE_KEYS`.

[Lição — REGRA GERAL actualizada]
A regra do BUG-000004-addendum ("qualquer resposta que traga `battle` NÃO
pode trazer `battles[]` junto") continua válida para respostas de ACÇÕES
individuais (startDuel, checkForQuestComplete, etc.).

No BOOT (autoLoginUser), o caminho correcto é o inverso: popular `battles[]`
(plural) com TODAS as batalhas pendentes. O cliente no boot percorre as quests
e procura cada `fight_battle_id` no array `battles` — se o array estiver
vazio mas existir como chave, o lookup falha silenciosamente.

[Ficheiros]
- server-laravel/actions/autoLoginUser.php (~L119-131, após ACCOUNT_STATE_KEYS)

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000073 (ox-alpha) — Replay de ataque recebido (duelo/liga) com lados trocados e combate que não resolve
[Sintoma]
Ao abrir o histórico de duelos/lutas de liga recebidas e ver o replay de um
ataque, o comportamento não era o esperado: o herói do espectador devia ficar
do lado ESQUERDO com os stats/habilidades dele e o atacante do lado DIREITO
(como num ataque normal), com uma barra a chegar a zero no fim. Em vez disso,
os lados apareciam trocados (o atacante surgia à esquerda como se fosse "o meu
herói") e o combate não resolvia corretamente as barras de vida.

[Causa]
`leagueMissed.php`: nas actions `getMissedDuel`/`getMissedLeagueFight`, a
batalha era pré-trocada pela closure `$swappedBattle` antes de ser servida ao
defensor (`missed_duel_battle`/`missed_league_fight_battle`) — stats, winner,
aparências e chaves `a`/`d` de cada round. A dedução ("o espectador é sempre o
lado 'a' porque `En`/`ep` leem isWon = winner=='a'") veio do BUG-000034 e estava
ERRADA. O bundle html5_257 espera a batalha CRUA e faz a inversão ele próprio:

1. `En.get_selfCharacter()` = profileB (espectador) e `get_missedOpponent()` =
   profileA (atacante); no painel, `barA`/`avatarA` (esquerda) recebem os
   hitpoints do profileB — com a batalha crua, o defensor fica à esquerda.
2. Todos os handlers de round têm branch explícito
   `if(F.get_isMissedDual()||F.get_isMissedLeagueFight())` que inverte
   avatares/barras — desenhado para batalha crua. Com a pré-troca, a inversão
   acontecia DUAS vezes -> lados trocados.
3. Rounds de duelo/liga só têm a chave `'a'` (não existe `'d'`; Fight::hit em
   modo não-guild grava `['a' => lado]`). O swap `$rd['a'] = $rd['d'] ?? null`
   punha TODOS os rounds com `'a' => null` -> o cliente deixava de saber quem
   ataca em cada golpe -> barras cruzadas, ninguém chegava a zero.
4. Bónus: o som de resultado também estava invertido quando o atacante ganhava
   (`F.get_missedDuel().get_isWon() ? lose : win` — com winner trocado, tocava
   "win" numa derrota).

[Correcao]
- `leagueMissed.php`: servir a batalha CRUA em `getMissedDuel`
  (`missed_duel_battle`) e `getMissedLeagueFight`
  (`missed_league_fight_battle`): `Live::shapeLike($boot['battle'], $battle)`
  sem transformação; closure `$swappedBattle` removida.
- Mantido o remapeamento de nomes da shape `Yy` no `missed_duel`/
  `missed_league_fight` (`character_a_rewards` <- character_b_rewards,
  `character_a_status` <- character_b_status) — isso é só contrato de campos,
  estava correto.
- Lista (`getMissedDuelsNew`/`getMissedLeagueFights`) intocada: já servia
  winner cru e `Ax.get_isWon() = winner=='b'` está certo.

[Verificação no bundle]
- `En.prototype.get_selfCharacter/get_missedOpponent/get_isWon` (duelo) e
  `ep.prototype.*` (liga).
- Painel de batalha: `onDamageDone`, `onBackfireDamageDone`,
  `onBonusDamageDone`, `onHealingAttacker/AttackedDone`, `onHitAnimationComplete`
  e skip-to-end — todos com branch de inversão para missed duel/league fight;
  skip-to-end mapeia `_barB=profileAEndHitPoints`, `_barA=profileBEndHitPoints`
  e `"b"==winner -> avatarA normal/avatarB defeated`.

[Lição]
Antes de reescrever dados do ponto de vista do jogador, confirmar no bundle SE
o cliente já faz essa adaptação — branches `isMissed*` no cliente indicam que
o contrato é a forma crua.

[Ficheiros]
- server-laravel/actions/leagueMissed.php

**Estado:** ✅ Corrigido

## BUG-000074 (ox-alpha) — Bónus de conjuntos de itens (sets) nunca aplicado
[Sintoma]
Itens da mesma "família" (ex.: `mask_sci_fi_week_1` + `suit_sci_fi_week_1` + ...)
dão bónus por número de peças equipadas no jogo oficial (2 peças → +7% a um
atributo, 3 → +7% a outro, 4 e 5 idem), mas neste servidor nada acontecia ao
equipar várias peças do mesmo conjunto: nem nos atributos mostrados, nem nas
lutas.

[Causa]
A mecânica existe TODA no cliente (`ItemSet.getBonusData`/`getStatChanges`/
`itemSetStatValue` em HeroZero.min.js) mas dependia de duas coisas que o
servidor nunca forneceu:

1. `inventory.item_set_data` — mapa `{set_id: peças_equipadas}` que o cliente lê
   em `getEquippedItemSetCount()`. A coluna existia na BD mas só recebia `''`
   (INSERT de criação) e nenhuma action a escrevia ou lia.
2. Cálculo server-side para as LUTAS: `battleProfile()` somava base + treino +
   equipamento + multiplicador (booster/guilda/liga), sem termo de conjunto. O
   painel do cliente recalcula os totais com a linha própria "+X do conjunto"
   (`get_itemSet*()`), logo mesmo que mostrasse algo, divergia das lutas.

Os dados oficiais estavam disponíveis localmente: `public/assets/data/
constants_json.data` (espelho do CDN, carregado pelo cliente via reescrita XHR
no index.html) traz `item_set_templates` (290 conjuntos; bónus de 1%/2%/7% por
limiar; tipos 1=vigor 2=força 3=cérebro/critical 4=intuição/dodge 5=todos
6=sorte casino) e `item_templates[].item_set_identifier` (1334 itens de
conjunto). Exemplo `sci_fi_week_1`: 2→cérebro, 3→intuição, 4→vigor, 5→força,
+7% cada — exatamente o padrão reportado.

[Semântica do %] (confirmada em 3 fontes)
O bónus é % da HABILIDADE BASE do herói, não dos stats das peças:
- Cliente: `getStatChanges` faz `ceil(get_statBaseStamina() * value)` sobre o
  campo cru `stat_base_*` da resposta;
- Tooltip de atributos do próprio jogo: linha aditiva "+X do conjunto", fora de
  "+ itens";
- Guia oficial do fórum BR ("bónus às habilidades base").

[Correcao]
- `data/item_sets.php` (NOVO): catálogo extraído das constants oficiais —
  `sets` (290 templates com `bonus`{peças:{type,value}} e `item_count`) +
  `items` (identifier→set_id). Carregado por `Character::itemSetCatalog()`.
- `Character::equipBonus()`: a query passou a trazer `identifier` e conta peças
  por conjunto (`$bonus['sets'][set_id] = n`) — fonte única para combate e
  resposta.
- `Character::itemSetBonus($stat)` (NOVO): soma `ceil(stat_base_X * value)` de
  todos os limiares ≤ peças equipadas que afetem o stat (tipo igual ou tipo 5 =
  todas). A base é a COLUNA `stat_base_X` (não base+treino como em
  statBoostBonus): é exatamente o que o cliente usa na sua decomposição, senão
  as lutas divergiam do painel. Tipo 6 (casino) não entra em combate.
- `battleProfile()`: as 4 stats somam `+ itemSetBonus(...)` como linha aditiva
  própria (nunca multiplicada pelo booster/guilda — igual às linhas separadas do
  cliente).
- `overlayCharacter()`: `stat_total_*` também inclui o bónus (o cliente compara
  o total com a sua própria soma base+itens+conjunto+...).
- `inventoryData()`: preenche `item_set_data` = JSON das contagens calculado AO
  VIVO dos slots equipados ('{}' quando vazio — o cliente faz JSON.parse e
  espera um objeto, não um array). Assim tooltip/painel usam a MESMA fonte que
  as lutas.

[Verificação]
Tinker com transação+rollback (char 24764, base_dodge≈27): 5 peças
sci_fi_week_1 → bónus vigor=1 força=1 cérebro=1 intuição=2 (=ceil(27*0.07));
tirar 2 peças → só cérebro=1 (limiar 2 é type 3); `item_set_data`
={"sci_fi_week_1":5}; rollback limpo.

[Lição]
Quando o cliente tem getters que somam uma linha ao total (aqui `get_itemSet*`),
procurar quem FORNECE o campo que alimenta essa linha — aqui era um campo de
inventário que o servidor oficial escreve e o nosso nunca escreveu.

[Ficheiros]
- server-laravel/data/item_sets.php (novo)
- server-laravel/app/HeroZero/Character.php

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000075 — Leaderboard da liga mostra todos os heróis (ignora grupo) e sem ordenação por pontos

[Sintoma]
Ao abrir o ranking da liga (ícone leaderboard → separador da liga do herói), a
tabela mostrava TODOS os heróis do servidor — incluindo os de outras ligas e os
da liga super-herói — em vez de mostrar apenas os do grupo/liga do jogador.
O mesmo acontecia no separador da liga super-herói: mostrava toda a gente em vez
de só os heróis nessa liga. A ordenação por pontos de liga estava também
incorrecta (não havia ordenação real por pontos dentro do grupo).

[Causa]
Em `actions/leaderboard.php`, o bloco `retrieveLeagueGroupLeaderboard` (~L199)
tem três problemas independentes:

1. **Filtro de grupo nunca aplicado na query principal.**
   O `$groupId` era calculado correctamente (para centrar o ranking no melhor
   do grupo), mas a query SQL que devolve os resultados fazia:
     `FROM character ch LEFT JOIN guild g ...`
   sem nenhuma cláusula WHERE a filtrar `league_group_id`. Resultado: 50
   heróis tirados do ranking GLOBAL, independentemente da liga.

2. **Branch `characterName`: rank calculado sem filtro de grupo.**
   Quando a pesquisa era por nome de personagem, a subquery de rank usava
   `SELECT @rank+1, id, name FROM character ORDER BY league_points DESC`
   sem filtro de grupo — portanto o rank devolvido era a posição global, não a
   posição dentro do grupo. Além disso, o `$groupId` não ficava guardado (o valor
   do parâmetro GET era 0), pelo que o filtro da query principal, mesmo que
   existisse, seria ignorado.

3. **`max_characters` contava o total global.**
   `Db::value('SELECT COUNT(*) FROM character')` contava todos os heróis do
   servidor; o cliente usava este valor para a paginação, mostrando páginas
   inexistentes.

[Correcção]
- Adicionado `$groupFilter = 'WHERE ch.league_group_id = {$groupId}'` à query
  principal (~L257), aplicado quando `$groupId > 0`.
- Branch `characterName` refactorizado: primeiro busca o character por nome e
  lê o seu `league_group_id`; depois calcula o rank dentro desse grupo com
  `WHERE league_group_id = ?` na subquery interna.
- `$maxCharacters` passa a contar `SELECT COUNT(*) FROM character WHERE
  league_group_id = ?` quando o grupo é conhecido, ou o total global como
  fallback (pesquisa por rank explícito sem grupo).
- A variável `$totalCharacters` (total global) mantém-se separada apenas para
  a validação do `$sortRank` recebido do cliente.

[Ficheiros]
- server-laravel/actions/leaderboard.php (bloco retrieveLeagueGroupLeaderboard, ~L199–L293)

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000076 (ox-alpha, 2026-08-26) — Jogo lento: constants_json.data era re-parseado em TODA a ação (~195ms/pedido)

[Sintoma]
Após a implementação dos sidekicks, o jogo ficou visivelmente mais lento —
notou-se sobretudo ao abrir o painel de missões, mas afetava QUALQUER ação.
Round-trip HTTP medido: ~195ms de média (10 pedidos) para ações triviais.

[Causa]
O servidor corre PHP clássico (`php artisan serve`): caches estáticas morrem a
cada pedido, logo qualquer trabalho caro REPETE-SE em toda a ação. O caminho
quente de praticamente tudo é
    Router::dispatch -> acção -> Live::accountState()
        -> Live::template('autoLoginUser') -> Live::withLocalConstants()
        -> League::officialConstants() + Sidekick::injectConstants()
E ali dentro havia DOIS parses completos do asset
`public/assets/data/constants_json.data` (789KB zlib):
    gzuncompress ~18ms + json_decode ~55ms ~= 80ms CADA -> ~160ms mortos/pedido
1. `League::officialConstants()` — custo PRÉ-existente (desde as ligas);
2. `Sidekick::official()` — REGRESSÃO nova da implementação dos sidekicks
   (parseava o mesmo asset para tirar sidekick_levels/skills/data/effects).

Serializar o array completo derivado (~11.9MB expandido) não era opção:
rebenta o memory_limit de 128M.

[Correcao]
Novo `app/HeroZero/AssetCache.php` — cache PERSISTENTE (ficheiro) apenas das
FATIAS derivadas (pequenas), nunca do array inteiro:
- validade validada por `mtime:size` do asset -> basta tocar no asset para as
  caches se invalidarem sozinhas;
- escrita ATÓMICA (tmp + rename) — nenhum pedido lê um ficheiro a meio;
- leitura endurecida: `unserialize($raw, ['allowed_classes' => false])`;
- ficheiros em `storage/framework/cache/hz_<nome>.cache`.
Ligado às três classes que parseavam o MESMO asset:
    Sidekick::official()          (buildOfficial)
    League::officialConstants()   (buildOfficialConstants)
    GuildArtifacts::registry()    (buildRegistry)

Medições (asset frio vs. quente):
    Sidekick::official()         80ms  -> 1.0ms
    League::officialConstants()  82ms  -> 0.4ms
    GuildArtifacts::registry()   ~80ms -> <1ms
Build da cache: 84ms UMA única vez (1.º pedido após tocar no asset).
Round-trip HTTP real (goalsState, média de 10): **195ms -> 19ms**.

Detalhe adicional: docs/sidekicks/sidekicks_implementados.md (§9.1).

[Ficheiros]
- server-laravel/app/HeroZero/AssetCache.php (novo)
- server-laravel/app/HeroZero/Sidekick.php (official -> AssetCache::slice)
- server-laravel/app/HeroZero/League.php (officialConstants -> AssetCache::slice)
- server-laravel/app/HeroZero/GuildArtifacts.php (registry -> AssetCache::slice)

**Estado:** ✅ Corrigido

 ----------------------------------------------------------------------------
## BUG-000077 (ox-alpha, 2026-08-26) — XP/nível do sidekick chegava tarde ao cliente após concluir missão

[Sintoma]
Ao terminar uma missão, o XP do HERÓI animava logo na barra e o nível subia na
hora; no SIDEKICK nada disto acontecia imediatamente — a barra ficava velha e o
diálogo de subida de nível demorava bastante a aparecer.

[Causa]
`actions/claimQuestRewards.php` devolvia resposta REDUZIDA (user, character,
quests) SEM a chave `sidekicks`. O contrato do cliente (build 257) é:
QUALQUER resposta com `data.sidekicks` é fundida por id no modelo
(`refreshSidekicks` -> `Cd.update`) e é ESSE merge que
1. atualiza o modelo da barra de XP do pet (o `Cd` espera XP CUMULATIVO contra
   `sidekick_levels`);
2. dispara `CharacterMessage.notifySidekickLevelUp` -> diálogo de level-up;
3. leva ao repinte das barras do HUD (`PanelBaseUser.refresh`).
Sem a chave, o modelo do pet ficava velho até ao próximo sync periódico
(~60s — timer que é SALTO quando o socket está ativo) ou até à próxima ação que
trouxesse `accountState`. O mecanismo de render é o MESMO para as duas barras
(herói e pet); a assimetria vinha da DISPONIBILIDADE dos dados: o `character`
ia na resposta, os `sidekicks` não.

As outras duas vias de XP partilhado não precisavam de alteração:
`claimTrainingStar` (trainingQuest.php) e casino.php já devolvem
`Live::accountState()`, que inclui `sidekicks`.

[Correcao]
`actions/claimQuestRewards.php` — acrescentado à resposta:
    'sidekicks' => Sidekick::ownedByCharacter($char->id())
Verificado em vivo: a resposta passa a transportar o pet fresco (level/xp
coerentes com a BD) e a barra/diálogo reagem logo no claim.
Bónus coberto pelo mesmo caminho: a concessão do primeiro sidekick (nível 60,
dentro de `syncLevelFromXp`) também aparecia com atraso.

[Nota de diagnóstico]
O nível "25" com xp=15000 visto durante os testes NÃO era bug: era estado
sintético inconsistente de testes anteriores. `refreshLevelAndStats()` recalcula
o nível SEMPRE pelo XP acumulado contra `sidekick_levels` (15060 xp -> nível 19),
que é o comportamento correto.

[Lição] Mesma família do BUG-000010/19: o cliente só atualiza o que a resposta
traz — campo omitido fica congelado em memória até um sync.

Detalhe adicional: docs/sidekicks/sidekicks_implementados.md (§9.2).

[Ficheiros]
- server-laravel/actions/claimQuestRewards.php

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000078 (ox-alpha, 2026-08-26) — Ações do esconderijo devolviam erro em vez de sincronizar o cliente

[Sintoma]
Armazenar uma sala -> instaskip -> tentar interagir outra vez dava erro
(`errInstantFinishActivityInvalidStatus`, `errStorePendingActivity*`,
`errInvalidHideoutRoom`, `errUnlockRoomSlotInvalidStatus`). O diálogo de erro
aparecia mesmo quando o servidor já tinha o estado correto.

[Causa]
Família de estados-antigos do cliente. O cliente (build 257) mantém o estado do
esconderijo em memória e só o atualiza quando uma resposta traz `hideout` /
`hideout_rooms`. Entre o clique e a resposta — ou após um `sweepExpiredSlotUnlocks`
do servidor — o cliente ficava com o estado anterior e reenviava um pedido já
concluído. Todos os handlers tratavam isso como erro fatal:
  - `instantFinishHideoutRoomActivity`: sala já IDLE, ou `ts_activity_end` já expirado
  - `storeHideoutRoom`: sala já em STATUS_STORING (duplo envio)
  - `placeHideoutRoom`: sala já posicionada na grelha
  - `instantFinishHideoutSlotUnlock`: slot já desbloqueado (valor >= 0)
  - `checkUnlockHideoutRoomSlotFinished`: slot já concluído pelo sweep

[Correcao]
Idempotência: quando o pedido já não faz sentido porque o efeito JÁ ESTÁ aplicado,
devolver `Live::accountState($userId)` (o cliente sincroniza e o diálogo fecha)
em vez de lançar GameError. O erro fica reservado para estados genuinamente
inválidos (ex.: slot = -1 = bloqueado, nunca esteve em progresso).
Extra em `instantFinishHideoutRoomActivity`: com `remaining <= 0` a atividade é
concluída GRÁTIS (sem debitar premium) — antes cobrava ou rebentava.

[Ficheiros]
- server-laravel/actions/instantFinishHideoutRoomActivity.php
- server-laravel/actions/storeHideoutRoom.php
- server-laravel/actions/placeHideoutRoom.php
- server-laravel/actions/hideoutExtra.php (instantFinishHideoutSlotUnlock ~L344;
  checkUnlockHideoutRoomSlotFinished ~L323)

[Lição] Num protocolo sem locks, "já está feito" não é erro — é sincronizar.

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000079 (ox-alpha, 2026-08-26) — Espaço fantasma no esconderijo: slot vazio mostrava atividade a decorrer (bug de CLIENTE)

[Sintoma]
Armazenar uma sala, instaskip, e depois fazer outra ação qualquer (colocar/armazenar
outra sala): no espaço ANTERIOR voltava a aparecer que "ainda está a ser armazenada",
com botão de instaskip. Clicar nele enviava `instantFinishHideoutSlotUnlock` para um
espaço já vazio e desbloqueado. Variante: espaço livre onde "não aparece mais nada".

[Causa]
Bug no cliente minificado (`HeroZero.min.js`), não no servidor. O widget de progresso
de cada espaço (classe `Uo`) guarda o fim da atividade em `_tsEnd`:
  1. `Uo.prototype.refresh()` esconde o botão e para o timer mas **nunca limpa `_tsEnd`**.
  2. `Ii.prototype.show()` chama `Uo.prototype.enable()`, que faz
     `0<this._tsEnd ? this.set_unlockTime(this._tsEnd) : this.refresh()`
     — ou seja RE-ARMA o widget a partir do `_tsEnd` velho.
  3. `Ii.prototype.refresh()` chama `this.show()` **ANTES** do early-return de cache
     (`null==this._room && ""==this._roomHash && !isSlotUnlockInProgress(...)`),
     por isso o widget re-armado nunca era corrigido.
`set_unlockTime(0)` — a única via que limpa `_tsEnd` — só é chamada no ramo de slot
BLOQUEADO; num slot vazio e DESBLOQUEADO o código só faz `_progress.set_room(null)`.
Como `_room` fica null, o ícone renderiza como "escavar" e o clique cai em
`Nr.openDialog(level,slot)` -> `instantFinishHideoutSlotUnlock`.
Confirmado no HAR: o pedido era `level=1 slot=1`, exatamente o espaço onde tinha
estado a wall (sala de 2 espaços, 1_0+1_1).

[Correcao]
Patch mínimo em `Uo.prototype.refresh` — zera `_tsEnd` quando não há sala no espaço:
    function(){null==this._room&&(this._tsEnd=0);this._btnInstantFinish.set_visible(!1); ...
Seguro: no caso legítimo de desbloqueio a decorrer, `Ii.refresh` chama
`set_room(null)` e LOGO A SEGUIR `set_unlockTime(tsEnd)`, portanto volta a armar.
Validado com `node --check`. Backup: `HeroZero.min.js.bak-pre-slotghost`.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js (Uo.prototype.refresh)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000080 (ox-alpha, 2026-08-26) — `response_errorIO` (HTTP 500) em qualquer ação quando o esconderijo tinha oponente selecionado

[Sintoma]
`Error Code: response_errorIO` / `Last Action: unlockHideout`. Não era um GameError —
era crash 500 do PHP. Afetava a conta `skel` (char 25296) e não a `laliene`.

[Causa]
`Hideout::buildOpponentProjection()` fazia
`SELECT * FROM character WHERE id = ?` e depois lia:
    $oppChar['stat_total_stamina'], $oppChar['stat_total_strength'],
    $oppChar['stat_total_critical_rating'], $oppChar['stat_total_dodge_rating'],
    $oppChar['stat_weapon_damage'], $oppChar['online_status']
Nenhuma destas é COLUNA da tabela `character` — a tabela só tem `stat_base_*`,
`stat_bought_*` e `stat_trained_*`. Os totais são CALCULADOS (base + treino +
equipamento + boosters/guilda) e `online_status` é derivado de `ts_last_action`.
Resultado: `Undefined array key "stat_total_stamina"` -> ErrorException -> HTTP 500
em QUALQUER ação (o `accountState` chama sempre `Hideout::attachState`).
Só rebentava com `current_opponent_id > 0`, por isso parecia específico da conta.

[Correcao]
Preencher os campos derivados via `battleProfile()`, exatamente como
`Character::duelOpponents()` já fazia:
    $oppProfile = Character::load($opponentCid)->battleProfile('a');
    $oppChar['stat_total_stamina']         = (int)$oppProfile['stamina'];
    $oppChar['stat_total_strength']        = (int)$oppProfile['strength'];
    $oppChar['stat_total_critical_rating'] = (int)$oppProfile['criticalrating'];
    $oppChar['stat_total_dodge_rating']    = (int)$oppProfile['dodgerating'];
    $oppChar['stat_weapon_damage']         = (int)$oppProfile['weapondamage'];
    $oppChar['online_status'] = (GameTime::now() - (int)($oppChar['ts_last_action'] ?? 0)) < 300 ? 1 : 2;

[Bónus] Antes destes campos chegarem, o cliente SUBESTIMAVA o adversário no
preview de ataque ao esconderijo.

[Reincidência — mesma função, 2026-08-26]
Depois de subir o esconderijo do `skel`, este passou a ser oponente da `laliene` e
o MESMO crash voltou noutro ramo da função: a projeção do sidekick do oponente lia
`$sidekick['sewed_identifier']` e `$sidekick['sewed_level']`, que TAMBÉM não são
colunas da tabela `sidekicks` (o `Sidekick.php` ~L758 já usava `?? ''`/`?? 0` para
estes dois — o `Hideout.php` não). Só disparava com um oponente que TIVESSE sidekick.
Corrigido: todos os campos da projeção do sidekick passaram a ter default.
Auditoria feita ao resto da função contra o schema real: `$oppHideout` não lê nenhum
campo inexistente e os 6 do `$oppChar` são os já cobertos acima.

[Lição] `SELECT *` + acesso direto por chave assume que o schema tem tudo o que o
payload precisa. Campos DERIVADOS (totais, online_status) e campos de features
não implementadas (sewed_*) nunca lá estão. Usar `?? default` em projeções de
dados de TERCEIROS — um campo em falta não deve derrubar a resposta inteira.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (buildOpponentProjection ~L1155 e ~L1251)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000081 (ox-alpha, 2026-08-26) — Tutorial do esconderijo repetia e o scroll entre pisos ficava preso na 2ª linha

[Sintoma]
A mensagem que conclui o tutorial do esconderijo aparecia sempre. Não era possível
descer de piso: o ecrã ficava preso na 2ª linha mesmo com espaços escavados na 3ª.

[Causa]
`Character::setTutorialFlag()` valida a chave contra uma whitelist e **descarta em
SILÊNCIO** (sem erro, sem log) o que não estiver lá:
    if (!array_key_exists($key, self::TUTORIAL_DONE_FLAGS)
        && !array_key_exists($key, self::HIDEOUT_TUTORIAL_FLAGS)) {
        return;
    }
`hideout_tutorial_completed` NÃO estava em `HIDEOUT_TUTORIAL_FLAGS`. O
`finishHideoutTutorial` chamava o setter, devolvia sucesso (`err=''` no HAR) e não
gravava nada. Confirmado no HAR: a flag não aparece em `character.tutorial_flags`
depois da ação. O cliente lê exatamente essa chave:
    get_hasHideoutTutorialCompleted=function(){return this.hasTutorialFlag("hideout_tutorial_completed")}
que é o gate do scroll entre pisos, dos botões de abortar e do resto das
funcionalidades do painel.

[Correcao]
Acrescentadas a `HIDEOUT_TUTORIAL_FLAGS`:
    hideout_tutorial_finished, hideout_tutorial_completed,
    hideout_tutorial_show_attacker, hideout_build_attacker_units,
    hideout_first_attack
(todas escritas pelo servidor ou lidas pelo cliente build 257).

[Lição] Whitelist que falha em silêncio esconde o bug: o handler devolvia 200 e
a flag nunca era gravada. Vale a pena logar chaves rejeitadas.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (const HIDEOUT_TUTORIAL_FLAGS ~L127)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000082 (ox-alpha, 2026-08-26) — Recolher bot de combate não fazia nada (unidade produzida desaparecia)

[Sintoma]
Produzir um bot de combate, instaskip, clicar em recolher: a sala voltava a IDLE
mas `current_attacker_units` ficava igual. O bot desaparecia.

[Causa]
`Hideout::applyCaps()` recalcula `max_resource_glue/stone` e
`max_attacker/defender_units` a partir das salas COLOCADAS, mas só corria **em
memória** dentro do `statePayload` — o resultado nunca era persistido. A linha
`hideout` na BD ficava com limites velhos:
    BD CRU    -> current_atk=1 max_atk=1
    applyCaps -> current_atk=1 max_atk=2      (sala já no nível 2)
O handler do collect lê a linha CRUA (`Hideout::requireRow`) e faz:
    min((int)$h['max_attacker_units'], (int)$h['current_attacker_units'] + $count)
    = min(1, 1 + 1) = 1
A unidade era produzida, a sala consumida — e o ganho engolido pelo cap velho.
Mesma armadilha aplicável a cola/pedra e a unidades de defesa.

[Correcao]
1. `collectHideoutRoomActivityResult`: `Hideout::applyCaps($h, $allRooms)` na row
   ANTES de creditar seja o que for.
2. Raiz — `Hideout::statePayload()`: passa a gravar (`saveHideout`) quando algum
   cap muda, para que nenhuma outra ação volte a ler limites desatualizados.

Verificado ponta a ponta (produzir -> instaskip -> recolher): bots 1/2 -> 2/2.

[Lição] Valor derivado calculado só para a resposta é uma bomba-relógio: a ação
seguinte lê a BD, não a resposta. Ou se persiste, ou se recalcula em toda a leitura.

[Ficheiros]
- server-laravel/actions/hideoutExtra.php (collectHideoutRoomActivityResult ~L26)
- server-laravel/app/HeroZero/Hideout.php (statePayload ~L1387)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000083 (ox-alpha, 2026-08-26) — Esconderijo não usava relogio interno do servidor para as datas

[Sintoma]
Ao mandar melhorar o broker (centro de especulação), a data de conclusão aparecia
como **01/09/2026** quando devia ser **07/10/2026**. Genericamente: qualquer data
absoluta no cliente vinha com `data_real_do_PC + duração` em vez de
`data_do_jogo + duração`. Também afetava boosters ("expira a…") e fim de missões.

[Causa]
NÃO era o hideout nem o servidor — era a função de formatação de datas de TODO o
jogo, no cliente minificado:

    wb.timestampToLocalString = function(a,b,c){
      null==c&&(c=!1);
      c && (a -= K.get_timestampCorrection());      // <-- aqui
      return Oc.fromTimestamp(a).format(b, n.info.get_defaultLocale())
    };

O 3.º parâmetro significa "converte este timestamp do relógio do SERVIDOR para o
relógio do PC". No jogo oficial `_timestampCorrection` vale 0-2 segundos (só
compensa latência de rede), logo é inofensivo. Neste servidor o relógio de jogo
(`GameTime::now()` = epoch + uptime) está ~36 dias à frente do tempo real, e
`K.correctTime()` guarda essa diferença INTEIRA:
    _timestampCorrection = server_time - relógio_do_PC = 3 101 244 s ≈ 35,9 dias
A subtração destruía a data:
    ts_activity_end 07/10/2026 15:57  -  35,9 dias  =  01/09/2026 18:29   (o que aparecia)

Auditoria feita ANTES de chegar aqui (tudo limpo, o servidor não tinha culpa):
  - `time()` cru em `actions/` e `app/HeroZero/`: ZERO ocorrências;
  - todos os `date()` do hideout recebem `GameTime::now()`;
  - `ts_activity_end` gravado = `GameTime::now() + duração` (HAR: server_time
    1790870227 -> ts_end 1791388627, exatamente 518400s = 6 dias);
  - `new Date` cru no cliente: 12 usos, nenhum no hideout (internos de `Oc`,
    parsing de strings, zip, filtros de blur);
  - `server_timestamp_offset` (3600): a álgebra confere — os getters de `Oc` são
    LOCAIS (`getHours()`, não `getUTCHours()`), portanto o `utcLocalTimestampOffset`
    cancela-se sozinho e S tem de valer o offset do fuso NA DATA DE JOGO, que é o
    que `Live::gameTimestampOffset()` já devolve.

[Correcao]
Removida a subtração — as datas passam a ser SEMPRE as do relógio de jogo:

    wb.timestampToLocalString=function(a,
    b,c){null==c&&(c=!1);return Oc.fromTimestamp(a).format(b,n.info.get_defaultLocale())};

Os restantes usos de `get_timestampCorrection` (`Oc.isToday` / `isYesterday` /
`isTomorrow`) foram auditados e ficam INTACTOS: são invocados via
`K.get_localDateTime()` / `K.get_serverDateTime()`, cujo `_localTimestampOffset`
já contém a correção, portanto `offset - correction` dá 0 nos dois casos e
cancela-se. Verificado numericamente.

Validado com `node --check`. Backup: `HeroZero.min.js.bak-pre-gameclock`.

[Lição] Um servidor com relógio próprio muito adiantado transforma um ajuste de
latência (0-2s no oficial) num erro de 36 DIAS. Qualquer sítio do cliente que
trate `_timestampCorrection` como "diferença desprezável" é suspeito quando o
relógio de jogo diverge do real.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js (wb.timestampToLocalString)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000084 (sonnet-4-6, 2026-08-27) — Centro de especulação (broker) não aplicava desconto em ouro em nenhuma ação

[Sintoma]
O broker estava colocado no esconderijo mas o custo em ouro de todas as ações
(construir sala, fazer upgrade, desbloquear slot) mantinha-se igual ao preço base,
sem qualquer desconto.

[Causa]
`Hideout::applyRoomPassives()` e `removeRoomPassives()` não tinham `case` para
`broker`, `gem_production` nem `robot_storage`. Quando qualquer destas salas era
colocada no chão (STATUS_BUILDING concluído, STATUS_PLACING concluído), o campo
`current_broker_level` na tabela `hideout` ficava sempre a 0.
`Hideout::reducedGold()` lê `$h['current_broker_level']` para calcular o desconto:
com o campo a 0, a função devolvia sempre o preço original.
Adicionalmente, `unlockHideoutRoomSlot` em `hideoutExtra.php` nunca chamava
`reducedGold()`, pelo que o desbloqueio de slots não era descontado mesmo que
`current_broker_level` fosse > 0.

[Correcao]
1. `applyRoomPassives` e `removeRoomPassives`: adicionados `case 'broker'`,
   `case 'gem_production'` e `case 'robot_storage'` que atualizam
   `current_broker_level`, `current_gem_production_level` e
   `current_robot_storage_level` respetivamente.
2. `unlockHideoutRoomSlot` (`hideoutExtra.php`): o custo em ouro de
   `slotExpansionCost()` passa agora por `Hideout::reducedGold($h, ...)` antes de
   ser verificado e debitado.
3. Correcao manual da BD: as contas `laliene` (broker lvl 4) e `skel` (broker lvl 5)
   tinham `current_broker_level = 0` — atualizado diretamente via UPDATE.

[Lição] Salas de tipo boost/upgrade que guardam o seu nível numa coluna
dedicada da tabela `hideout` (e não nas `hideout_rooms`) têm de ser
explicitamente listadas em `applyRoomPassives`/`removeRoomPassives`.
Qualquer nova sala com uma coluna `current_X_level` requer o seu `case`.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (applyRoomPassives ~L558, removeRoomPassives ~L574)
- server-laravel/actions/hideoutExtra.php (unlockHideoutRoomSlot ~L313)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000085 (sonnet-4-6, 2026-08-27) — Gerador aplicava bónus a todas as salas de produção em vez de só às adjacentes

[Sintoma]
Colocar um gerador em qualquer sítio do esconderijo aumentava a capacidade e
velocidade de produção de TODAS as salas de produção, independentemente de estarem
ao lado do gerador ou não.

[Causa]
`Hideout::refreshGeneratorLevels()` percorria todas as salas de produção e aplicava
o nível mais alto de qualquer gerador colocado a todas elas:
    $room['current_generator_level'] = $highest;  // todas as salas, sem verificar posição
O gerador devia boostar apenas as salas **imediatamente à esquerda ou à direita**
(mesma linha, coluna adjacente), e apenas salas dos tipos:
`main_building`, `xp_production`, `gem_production`, `glue_production`, `stone_production`.
`worker_home`, `attacker_production`, `defender_production` e `barracks` NÃO são
boosted (confirmado por staff oficial Playata).
Se uma sala tiver geradores dos dois lados, conta apenas o mais alto (não acumula).

[Correcao]
`refreshGeneratorLevels` reescrito:
1. Constrói mapa `(linha, coluna) → nível do gerador` para geradores colocados.
2. Para cada sala boostable, determina a posição via `roomSlotAndLevel` e
   calcula os slots adjacentes (coluna imediatamente à esquerda e à direita,
   respeitando o tamanho da sala em células).
3. Aplica o nível mais alto de gerador adjacente; 0 se não houver nenhum.
4. Salas não boostable ficam com `current_generator_level = 0`.
Adicionada constante `GENERATOR_BOOSTABLE_ROOMS` com os tipos elegíveis.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (refreshGeneratorLevels ~L661, const GENERATOR_BOOSTABLE_ROOMS)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000086 (sonnet-4-6, 2026-08-27) — Central do esconderijo mostrava 3 moedas máximo (fórmula dinâmica não implementada)

[Sintoma]
A central do esconderijo (`main_building`) mostrava uma capacidade máxima de 3 moedas
independentemente do nível do personagem, do nível da sala ou de qualquer bónus activo.

[Causa]
`Hideout::effectiveMaxResourceAmount` tratava a `main_building` como qualquer outra
sala de produção automática: usava directamente `$room['max_resource_amount']` gravado
na BD. Este campo é populado aquando da construção com
`(int)round($levelCfg['resource_production_max'])` — para a `main_building` nível 1
esse valor é `round(1.0) = 1`, e para o nível 20 é `round(2.9) = 3`.
A capacidade de moedas da central NÃO é um valor fixo da master data — é calculada
dinamicamente em função do nível do personagem:
    `14.3 × nível_personagem^1.39 × rpm × bónus_guilda × bónus_energético`
onde `rpm` = `resource_production_max` do nível da sala (fator de escala), confirmado
no servidor flash 129 (2018): `HideoutUtils::getMainBuildingGoldMax`.
O energético de trabalho (`booster_work1/2/3` → 10%/25%/50%) também não estava a
ser aplicado: o campo `active_work_booster_id` não era lido em nenhum sítio do
cálculo da central.

[Correcao]
1. `Hideout::mainBuildingGoldMax(int $characterId, float $rpm)` — novo método que
   consulta `character` (nível + guild_id + active_work_booster_id) e `guild`
   (stat_quest_game_currency_reward_boost) e aplica a fórmula completa.
2. `effectiveMaxResourceAmount`: caso `main_building` redireccionado para
   `mainBuildingGoldMax`; gerador adjacente aplicado por cima.
3. `statePayload`: antes de serializar, actualiza `max_resource_amount` da
   `main_building` na BD e no array, para que o cliente receba sempre o valor correcto.
4. `buildMd`: adicionado `'boosters'` ao `$keepExact` para que a master data dos
   boosters fique disponível no slice do Hideout.

[Exemplos após correcção]
- skel (nível 302, central nível 20, guilda +100%, sem energético): 232.260 moedas
- skel (nível 302, central nível 20, guilda +100%, energético 50%): 348.390 moedas
- laliene (nível 58, central nível 20, guilda +4%, sem energético): ~11.719 moedas

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (mainBuildingGoldMax ~L525, effectiveMaxResourceAmount ~L568, statePayload ~L1489, buildMd ~L110)

**Estado:** ✅ Corrigido

----------------------------------------------------------------------------
## BUG-000087 (sonnet-4-6, 2026-09-01) — Energético de missão aplicado ao iniciar em vez de ao gerar

[Sintoma]
O painel de missões mostrava um custo de energia (ex.: 4) mas ao iniciar a missão
só era debitado um valor inferior (ex.: 1). O jogador via um custo no painel mas
pagava outro ao clicar.

[Causa]
O energético pessoal do jogador (`booster_quest1/2/3` → 10/25/50%) era aplicado
em `Character::startQuest()` no momento de iniciar, e não na geração da missão.
`insertQuestPool` usava `questRewardMultipliers()` que só devolvia stats de XP e
moedas da guilda — nunca o booster de velocidade do jogador. O `energy_cost`
gravado na BD era o valor bruto. Ao iniciar, `startQuest` calculava o custo
boosted, debitava esse valor e sobreescrevia `energy_cost` na BD — ficando diferente
do valor exibido antes de iniciar.

[Correcao]
1. `Character::insertQuestPool()` — lê o `active_quest_booster_id` do personagem e
   calcula `$energyFactor = 1 - boost/100`. Aplica o factor à `energy` e `duration`
   antes do INSERT, para que o valor gravado na BD já seja o custo real.
2. `Character::startQuest()` — removida toda a lógica de booster e o UPDATE a
   `energy_cost`. Debita directamente `$q['energy_cost']` sem transformações.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (insertQuestPool ~L451, startQuest ~L2950)

----------------------------------------------------------------------------
## BUG-000088 (sonnet-4-6, 2026-09-01) — Reset diário de energias de missão não disparava

[Sintoma]
Ao passar a meia-noite do relógio interno do servidor, `quest_energy` não era reposto
ao máximo, `quest_energy_refill_amount_today` não era zerado e energias sobrantes
não eram guardadas no depósito (`current_energy_storage`).

[Causa]
O reset não estava implementado. Adicionalmente, a primeira tentativa usava
`ts_last_action` como referência de "último dia activo", mas o `GameController`
actualiza `ts_last_action = GameTime::now()` **antes** do dispatch de qualquer
action — incluindo `autoLoginUser`. Quando `syncDailyReset` corria, o timestamp
já era do dia actual e a condição `lastAction >= todayMidnight` era sempre verdadeira,
impedindo o reset de disparar.

[Correcao]
1. `Character::syncDailyReset()` — novo método chamado em `autoLoginUser` após o
   `League::syncDailyCount`. Usa `ts_last_quest_energy_refill` (gravado no fim do
   próprio reset) como referência de "último reset feito", evitando o conflito com
   `ts_last_action`. Ao disparar:
   - Energia sobrante → `current_energy_storage` (cap 100, conta trofeus via
     `GuildArtifacts::effectiveMax`)
   - `quest_energy` reposto ao máximo efectivo
   - `quest_energy_refill_amount_today` → 0
   - `slotmachine_spin_count` → 0
   - `ts_last_quest_energy_refill` → `GameTime::now()` (marca o reset)
2. `actions/autoLoginUser.php` — chamada a `$char->syncDailyReset()` após
   `League::syncDailyCount`.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (syncDailyReset ~L1027)
- server-laravel/actions/autoLoginUser.php

----------------------------------------------------------------------------
## BUG-000089 (sonnet-4-6, 2026-09-01) — buyQuestEnergy com moedas falhava (coluna não existe em `user`)

[Sintoma]
Comprar energia com moedas (`use_premium: false`) devolvia `response_errorIO`.

[Causa]
O UPDATE de débito de moedas estava a correr contra a tabela `user`:
`UPDATE user SET game_currency = ...` mas `game_currency` reside na tabela
`character`. O PDO lançava `SQLSTATE[42S22]: Unknown column 'game_currency'`.

[Correcao]
Corrigida a query para `UPDATE character SET game_currency = game_currency - ?
WHERE id = ? AND game_currency >= ?` usando `$cid` (character id) em vez de
`$userId`.

[Ficheiros]
- server-laravel/actions/energyTraining.php (buyQuestEnergy ~L44)

----------------------------------------------------------------------------
## BUG-000090 (sonnet-4-6, 2026-09-01) — Clamp de moedas usava energia boosted → premios sempre no máximo

[Sintoma]
Após aplicar o fix do BUG-000087 (energético aplicado na geração), todas as novas missões
de skel (lvl≈303, gstat=50, booster_quest3=50%) tinham moedas sempre fixas no máximo
da variância (1.15×) em vez de valor aleatório entre ±15%.

[Causa]
Em `insertQuestPool`, o fluxo era:
  1. `rollQuest(origE=6)` → coins = perEnergy * 6 * rand(0.85,1.15) ≈ 71849
  2. Booster: `$best['energy'] = 3` (50% de 6)
  3. Clamp com energia BOOSTED (3): cMax = perEnergy * 3 * 1.15 = 41313
  4. coins 71849 > cMax 41313 → sempre clamped para 41313

A variância aleatória tornava-se irrelevante: independentemente do rand(), o
resultado seria sempre cMax. Coins ficavam 100% fixas no topo do intervalo.

[Causa raiz]
O clamp devia usar a energia ORIGINAL (antes do booster), pois a documentação
define que "os energéticos não alteram as recompensas — apenas reduzem o tempo
e a energia cobrada".

[Correção]
Guardar `$origEnergy = $best['energy']` antes de aplicar o booster e usar
`$origEnergy` nos cálculos de `expectedXpRange` e `expectedCoinsRange`.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (insertQuestPool ~L509)

----------------------------------------------------------------------------
## BUG-000091 (sonnet-4-6, 2026-10-12) — Torneio semanal iniciava no login do primeiro jogador em vez de num limite semanal fixo

[Sintoma]
O primeiro jogador a fazer login após o servidor arrancar (ou após o torneio
anterior terminar) criava um novo torneio com `ts_start = GameTime::now()`,
ou seja, a uma hora arbitrária. O torneio não coincidia com nenhum limite de
semana fixo.

[Causa]
`Tournament::active()` chama `create()` se não existir torneio ativo.
`create()` usava `GameTime::now()` como `ts_start`.
`active()` é chamado em cada login via `ensureSnapshot()` → primeiro login
cria o torneio.

[Correção]
- Adicionado `Tournament::weekStart(int $now)`: calcula a segunda-feira UTC
  mais recente às 00:00 (alinha com a liga).
- `create()` passa a usar `weekStart()` em vez de `now()`.
- Quando um torneio expira e é criado o seguinte, `active()` passa o `ts_end`
  do anterior como `ts_start` do novo (`createFrom($prevEnd)`), evitando drift
  acumulado.

[Ficheiros]
- server-laravel/app/HeroZero/Tournament.php (active, create, createWithStart, weekStart, createFrom)

----------------------------------------------------------------------------
## BUG-000092 (opus-4-6, 2026-09-02) — Torneios semanais davam recompensas erradas (tudo donuts)

[Sintoma]
Ao resgatar recompensas de torneio, o jogador recebia apenas donuts
independentemente do tipo de torneio. O torneio de Stats dava donuts em
vez de pontos de habilidade. O torneio de PvP dava donuts em vez de XP.
O torneio de XP dava donuts mas faltavam os pontos de habilidade extra.

[Causa]
1. `Tournament::end()` guardava sempre `donuts` no JSON de recompensas
   para os 3 tipos, usando uma tabela única (`REWARDS`) que só tinha
   montantes de donuts.
2. `claimTournamentRewards.php` só somava o campo `donuts` de todos os
   tipos e creditava `premium_currency`. Nunca dava `stat_points_available`
   nem `xp`.

[Correção]
- `Tournament.php`: tabela `REWARDS` substituída por tabelas separadas:
  - `REWARDS_XP_DONUTS` — donuts do torneio XP
  - `REWARDS_XP_STAT_POINTS` — pontos de habilidade extra do torneio XP
  - `REWARDS_STATS` — pontos de habilidade do torneio de Stats
  - `REWARDS_PVP_XP_PCT` — percentagem do XP do nível actual (PvP)
- `Tournament::end()` grava campos correctos por tipo no JSON:
  - XP (key 1): `donuts` + `stat_points`
  - Stats (key 2): `stat_points`
  - PvP (key 4): `xp_percentage`
- `claimTournamentRewards.php` distribui cada campo ao sítio certo:
  - `donuts` → `user.premium_currency`
  - `stat_points` → `character.stat_points_available`
  - `xp_percentage` → calcula `(xpForLevel(nivel+1) - xpForLevel(nivel)) * pct / 100` → `character.xp`
- `donutsForRank()` substituído por `amountForRank()` genérico.

[Ficheiros]
- server-laravel/app/HeroZero/Tournament.php (REWARDS_*, end, amountForRank)
- server-laravel/actions/claimTournamentRewards.php

----------------------------------------------------------------------------
## BUG-000093 (opus-4-6, 2026-09-02) — Torneios só encerravam quando alguém fazia login

[Sintoma]
Após avançar o relógio do servidor 7+ dias, o torneio continuava activo
(status=1) até um jogador fazer login. Sem login, nenhum torneio era
encerrado nem criado.

[Causa]
`Tournament::active()` só era chamado em `autoLoginUser.php` (via
`ensureSnapshot`). Sem login, o ciclo de vida do torneio ficava parado.

[Correção]
Adicionada chamada a `Tournament::active()` no endpoint `/servertime`
(POST), de modo que ao avançar o relógio os torneios expirados são
encerrados e novos são criados automaticamente.

[Ficheiros]
- server-laravel/routes/web.php (endpoint /servertime)

----------------------------------------------------------------------------
## BUG-000094 (opus-4-6, 2026-09-02) — Oponentes da liga podiam ser qualquer jogador do servidor

[Sintoma]
No painel da liga, os 3 oponentes oferecidos podiam incluir jogadores que
nem sequer estavam inscritos na liga (`league_group_id = 0`) ou que estavam
em divisões completamente diferentes. Isto acontecia porque o fallback
global não filtrava por divisão nem por inscrição na liga.

[Causa]
`Live::leagueOpponents()` tinha 3 passos de sorteio:
1. Mesma `league_group_id` + nível ±25%
2. Mesma `league_group_id`, qualquer nível
3. **Fallback global**: qualquer personagem do servidor, ordenado por
   proximidade de nível — sem filtro de liga nem divisão.
Com poucos jogadores no mesmo grupo, o passo 3 disparava e mostrava
jogadores fora da liga.

[Correção]
Reescrita completa de `Live::leagueOpponents()`:
- Cada oponente é sorteado individualmente com 90% de chance de vir da
  mesma divisão e 10% de chance de vir de uma divisão abaixo.
- Se a divisão preferida não tiver jogadores disponíveis, tenta a outra.
- Na divisão 1 (Bronze I) só sorteia da mesma (não há abaixo).
- Nunca apanha jogadores não inscritos na liga (`league_group_id = 0`).
- Removido o fallback global que ignorava divisões.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (leagueOpponents)

----------------------------------------------------------------------------
## BUG-000095 (sonnet-4-6, 2026-09-02) — Jogadores semeados na liga ficavam invisíveis no ranking e sem oponentes

[Sintoma]
Após entrar na liga, o jogador não aparecia no ranking da sua divisão e não
recebia oponentes. O painel de liga abria vazio. Jogadores já existentes com
`league_group_id` correcto também não apareciam no ranking ao clicar na
divisão a partir do perfil de outro jogador.

[Causa]
Três locais do servidor usavam `divisão × GROUP_FACTOR` (ex: `1 × 100000 = 100000`)
como identificador de grupo ao semear e ao sortear oponentes. O cliente, porém,
pede o ranking sempre com `league_group_id = divisão × 100000 + 1` (ex: `100001`).
O valor `div × 100000` funciona como "grupo zero" que o cliente nunca requisita,
tornando invisíveis todos os jogadores aí colocados:
1. `enterLeagueDivision.php` — semeava com `GROUP_FACTOR * 1` (= 100000).
2. `League::adjustDivision()` — ao promover/despromover usava `GROUP_FACTOR * $newDiv`.
3. `Live::leagueOpponents()` — calculava `$groupId = $div * GROUP_FACTOR` para sortear.

[Correcção]
Adicionado `+ 1` aos três locais:
- `enterLeagueDivision.php`: `GROUP_FACTOR * 1 + 1`
- `League.php` `adjustDivision()`: `GROUP_FACTOR * $newDiv + 1`
- `Live.php` `leagueOpponents()`: `$div * GROUP_FACTOR + 1`
Jogadores já existentes no grupo errado (`MOD(league_group_id, 100000) = 0`)
migrados via `UPDATE … SET league_group_id = league_group_id + 1`.

[Ficheiros]
- server-laravel/actions/enterLeagueDivision.php
- server-laravel/app/HeroZero/League.php (adjustDivision)
- server-laravel/app/HeroZero/Live.php (leagueOpponents)

----------------------------------------------------------------------------
## BUG-000096 (sonnet-4-6, 2026-09-02) — Ranking da liga com ordem e numeração incorrectas

[Sintoma]
O painel de ranking da liga (`retrieveLeagueGroupLeaderboard`) mostrava jogadores
fora de ordem (não necessariamente do mais para o menos pontos) e com números
de rank inconsistentes.

[Causa]
A query usava variáveis de utilizador MySQL (`@rank := @rank+1`) para numerar
linhas dentro de uma subquery com `ORDER BY`. No MySQL 8.x, a avaliação de
variáveis de utilizador em subqueries deixou de garantir que o `ORDER BY` é
respeitado antes da atribuição — a numeração podia ser atribuída antes de a
ordenação ser aplicada, resultando em ranks arbitrários. O mesmo padrão
existia nas queries de ranking de guildas.

[Correcção]
Substituídas todas as ocorrências de `@rank := @rank+1` por
`ROW_NUMBER() OVER (ORDER BY …)` (window function do SQL standard, disponível
desde MySQL 8.0). A query exterior passou a ter `ORDER BY h.rank ASC` explícito
para garantir que a janela de 50 resultados é devolvida pela ordem correcta.

[Ficheiros]
- server-laravel/actions/leaderboard.php (retrieveLeagueGroupLeaderboard,
  retrieveGuildLeaderboard — todas as ocorrências de @rank)

----------------------------------------------------------------------------
## BUG-000098 (sonnet-4-6, 2026-09-04) — startHideoutFight devolve errStartHideoutFightRewardsChanged ao atacar

[Sintoma]
Ao tentar atacar um oponente no esconderijo, o servidor devolvia sempre
`errStartHideoutFightRewardsChanged`, impedindo o combate de iniciar.

[Causa]
A validação de tolerância (80%) em `hideoutExtra.php` calculava `$totalActual`
usando apenas os valores BASE de glue e stone (sem o bónus de +60%), mas
`$totalExpected` (enviado pelo cliente) inclui base + bónus, pois o cliente
soma ambos antes de enviar via `expacted_glue_reward` / `expacted_stone_reward`.

Exemplo concreto do HAR:
  - Preview servidor → base glue 53 747 + bónus 32 248 = 85 995 (glue total)
  - Preview servidor → base stone 88 936 + bónus 53 362 = 142 298 (stone total)
  - Cliente envia: expacted_glue_reward=85 995, expacted_stone_reward=142 298
  - totalExpected = 228 293; threshold 80% = 182 634
  - totalActual (só base) = 53 747 + 88 936 = 142 683 < 182 634 → erro

[Correcção]
Em `hideoutExtra.php`, a variável `$totalActual` passou a incluir também o
bónus, decodificando `$lootPreview['bonus_rewards']` (JSON devolvido por
`Hideout::opponentLoot`) e somando `hideout_glue` e `hideout_stone` do bónus.

[Ficheiros]
- server-laravel/actions/hideoutExtra.php (bloco startHideoutFight ~L511)

----------------------------------------------------------------------------
## BUG-000097 (sonnet-4-6, 2026-09-02) — Ranking geral de jogadores limitado a 30 entradas

[Sintoma]
O ranking geral de jogadores (`retrieveLeaderboard`) devolvia sempre no máximo
30 jogadores, mesmo havendo mais contas registadas. O campo `max_characters`
também ficava limitado a 30 em vez do total real.

[Causa]
`Live::leaderboardCharacters()` tinha `int $limit = 30` como valor por omissão,
hardcoded. Este valor era passado directamente ao `LIMIT` da query SQL, não
sendo calculado a partir do número real de jogadores na base de dados.

[Correcção]
O parâmetro `$limit` passou a ter valor por omissão `0`. Quando `0`, a função
faz um `SELECT COUNT(*)` prévio para obter o total real de jogadores e usa
esse valor como `LIMIT`, garantindo que todos os jogadores aparecem no ranking
independentemente do tamanho da base de dados.

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (leaderboardCharacters ~L745)

----------------------------------------------------------------------------
## BUG-000099 (sonnet-4-6, 2026-09-04) — Trocar oponente no esconderijo devolve sempre o mesmo

[Sintoma]
Ao clicar no botão de trocar oponente no esconderijo (`refreshHideoutOpponent`),
o servidor devolvia sempre o mesmo oponente, independentemente de quantas vezes
o botão fosse premido.

[Causa]
`Hideout::selectOpponent` escolhe o candidato com menor delta de pontos/nível de
forma determinística, sem excluir o oponente atualmente atribuído. Com poucos
jogadores no servidor (situação típica em testes), o mesmo candidato ganhava
sempre o menor delta e era retornado em todas as chamadas.

[Correcção]
`selectOpponent` recebeu o parâmetro opcional `int $excludeCharacterId = 0`.
Quando fornecido (via `refreshHideoutOpponent`), o candidato com esse character_id
é ignorado durante a selecção normal. Apenas se não existir nenhuma alternativa
válida é que o servidor cai de volta para `$anyBestId` (o melhor candidato sem
restrição), garantindo que nunca devolve zero quando há pelo menos um oponente.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (selectOpponent ~L1159)
- server-laravel/actions/hideoutExtra.php (refreshHideoutOpponent ~L429)

----------------------------------------------------------------------------
## BUG-000100 (sonnet-4-6, 2026-09-04) — Histórico de batalhas do esconderijo devolve response_errorIO

[Sintoma]
Ao abrir o histórico de ataques recebidos no esconderijo (`getHideoutBattleHistoryFights`),
o servidor devolvia `response_errorIO` e a lista não aparecia.

[Causa]
A query SQL selecionava `c.online_status`, coluna que não existe na tabela
`character` (o estado online é calculado a partir de `ts_last_action`).
A coluna inexistente causava uma excepção PDO que o handler convertia em
`response_errorIO`. Adicionalmente, a query não selecionava `c.id AS character_id`,
que `shapeHistoryBattle` tentava aceder em `$b['character_id']`.

[Correcção]
As queries de `getHideoutBattleHistoryFights` e `getHideoutBattleHistoryFight`
passaram a selecionar `c.id AS character_id` e `c.ts_last_action` em vez de
`c.online_status`. Em `shapeHistoryBattle`, `character_online_status` passou a
ser calculado como `(now - ts_last_action) < 300 ? 1 : 2`.

[Ficheiros]
- server-laravel/actions/hideoutExtra.php (getHideoutBattleHistoryFights ~L694, getHideoutBattleHistoryFight ~L721)
- server-laravel/app/HeroZero/Hideout.php (shapeHistoryBattle ~L1477)

----------------------------------------------------------------------------
## BUG-000101 (sonnet-4-6, 2026-09-04) — Replay de batalha do esconderijo crasha com "unknown field defender_count"

[Sintoma]
Ao clicar para ver o replay de uma batalha no histórico do esconderijo
(`getHideoutBattleHistoryFight`), o cliente lançava uma excepção não apanhada:
`Error in DataObject! Tried to retrieve unknown field in DataObject data! key=defender_count`.

[Causa]
`shapeHistoryBattle` omitia vários campos que o cliente lê para iniciar a
visualização da batalha: `defender_count`, `attacker_count`, `attacker_bonus_rewards`,
`attacker_character_name`, `defender_character_name`. Além disso,
`getHideoutBattleHistoryFight` não incluía `attacker_profiles` nem `defender_profiles`
na resposta, campos necessários para mapear as unidades durante o replay.

[Correcção]
- `shapeHistoryBattle` passou a incluir todos os campos em falta.
- `getHideoutBattleHistoryFight` passou a acrescentar `attacker_profiles` e
  `defender_profiles` ao shape (já acrescentava `rounds`).

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (shapeHistoryBattle ~L1477)
- server-laravel/actions/hideoutExtra.php (getHideoutBattleHistoryFight ~L732)

---

## BUG-000102 (sonnet-4-6, 2026-09-04) — Leaderboard do esconderijo crasha com "unknown field id / character_id"

[Sintoma]
Ao navegar para o ranking do esconderijo (`retrieveHideoutLeaderboard`), o cliente
lançava excepções: `key=id` e, após correcção parcial, `key=character_id`. A lista
não era apresentada.

[Causa]
O shape de cada entrada do leaderboard usava nomes de campos errados
(`hideout_id` em vez de `id`, `character_name` em vez de `name`, `character_gender`
em vez de `gender`, etc.) e omitia `character_id` (necessário para `get_isMe`) e os
campos de emblema de guilda (`emblem_background_shape`, `emblem_background_color`,
`emblem_background_border_color`, `emblem_icon_shape`, `emblem_icon_color`,
`emblem_icon_size`). A query também não fazia JOIN à tabela `guild`.

[Correcção]
Reescrita completa do shape de `retrieveHideoutLeaderboard`:
- Nomes de campo corrigidos para os que o cliente espera.
- Adicionado `character_id` como alias de `id`.
- Query passa a fazer LEFT JOIN a `guild` para incluir todos os campos de emblema.

[Ficheiros]
- server-laravel/actions/hideoutExtra.php (retrieveHideoutLeaderboard ~L1022)

---

## BUG-000103 (sonnet-4-6, 2026-09-04) — Ordenação do leaderboard por expansão ignorava o parâmetro points_sort=false

[Sintoma]
Ao trocar a ordenação do leaderboard do esconderijo para "expansão" (enviando
`points_sort=false`), a lista continuava ordenada por pontos de honra em vez de
por nível.

[Causa]
O código fazia `$pointsSort = (bool)($params['points_sort'] ?? true)`. Em PHP,
`(bool)'false'` avalia para `true` porque qualquer string não vazia é truthy.
Logo `points_sort=false` era sempre tratado como `true`.

[Correcção]
Substituída a conversão para:
```php
$pointsSort = ($params['points_sort'] ?? 'true') !== 'false';
```

[Ficheiros]
- server-laravel/actions/hideoutExtra.php (retrieveHideoutLeaderboard ~L1025)

---

## BUG-000104 (sonnet-4-6, 2026-09-04) — Visitar esconderijo a partir do leaderboard devolve errRequestInvalidParameter

[Sintoma]
Ao clicar em "Mostrar Esconderijo" no leaderboard do esconderijo, a acção
`getHideout` devolvia `errRequestInvalidParameter` e o esconderijo do outro
jogador não era aberto.

[Causa]
O cliente envia `hideout_id` com o valor do campo `id`/`character_id` do
leaderboard (que é o `character.id`), mas o código fazia lookup directo por
`hideout.id` — IDs de tabelas distintas que raramente coincidem.

[Correcção]
`getHideout` passou a resolver `hideout_id` como `character_id` via
`Hideout::rowFor($requestedId)`, que pesquisa por `character_id`, retornando
a linha correcta da tabela `hideout`.

[Ficheiros]
- server-laravel/actions/getHideout.php

---

## BUG-000105 (sonnet-4-6, 2026-09-06) — Gangue escalava pelos participantes inscritos e vitória não dava XP

[Sintoma]
Dois problemas distintos descobertos na mesma sessão:
1. A dificuldade da gangue NPC era calculada com a média dos stats dos jogadores
   que se **inscreveram** no ataque, não de toda a guilda. Bastava atacar sozinho
   para enfrentar uma gangue muito mais fraca do que o esperado.
2. Ao vencer a gangue, cada participante recebia apenas moedas — o XP nunca era
   atribuído.

[Causa]
1. `GuildBattles::simulateDungeonBattle()` carregava os perfis a partir de
   `$characterIds` (lista de quem clicou "juntar-me"), em vez de todos os membros
   da guilda.
2. A tabela `guild_battle_rewards` não tinha coluna `xp`; o código de resolução
   não calculava nem gravava XP; o claim (`claimGuildDungeonBattleReward`) só
   aplicava `game_currency`.

[Correcção]
1. Substituída a query de perfis por `SELECT id FROM character WHERE guild_id = ?`,
   que inclui todos os membros independentemente de quem se inscreveu.
2. Adicionada coluna `xp INT NOT NULL DEFAULT 0` à tabela `guild_battle_rewards`.
   Fórmula de XP por vitória: `floor(xpStep × xpPct)`, onde xpStep = XP do nível
   actual para o seguinte e `xpPct` = 15% (fácil) / 25% (médio) / 50% (difícil)
   — o mesmo padrão do torneio PvP semanal. O claim passa a aplicar moedas + XP
   num único UPDATE.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php (simulateDungeonBattle ~L132, ~L304)
- server-laravel/actions/guildDungeon.php (claimGuildDungeonBattleReward ~L381)
- server-laravel/docker/initdb/01_schema.sql (tabela guild_battle_rewards)
- docs/Guilda/guild_atacar_gang_npcs.md (secções 2.1 e 7 actualizadas)

Migração necessária em BDs existentes:
  ALTER TABLE `guild_battle_rewards` ADD COLUMN `xp` INT NOT NULL DEFAULT 0 AFTER `game_currency`;

---

## BUG-000105b (sonnet-4-6, 2026-09-06) — XP do dungeon de guilda usava XP cumulativo em vez do passo do nível

[Sintoma]
Ao reclamar a recompensa de uma batalha de gangue ganha, o personagem recebia
um XP absurdamente elevado (ex.: nível 305 com dificuldade difícil → +17 970 841 XP,
subindo 28 níveis de uma vez).

[Causa]
A fórmula usava `Character::xpForLevel(lvl)`, que devolve o XP **cumulativo
total** para atingir o nível `lvl` (≈35.9M no nível 305), não o XP necessário
para o **passo actual** (305→306 = 577 372). Multiplicar esse valor por 0.50
gerava uma recompensa de quase 18M XP por batalha.

[Correcção]
Substituída a fórmula por:
```php
$xpStep = max(0, Character::xpForLevel($lvl + 1) - Character::xpForLevel($lvl));
$xp     = (int)floor($xpStep * $xpPct);
```
Valores correctos para nível 305: fácil 86 605 / médio 144 343 / difícil 288 686.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php (simulateDungeonBattle ~L304)
- docs/Guilda/guild_atacar_gang_npcs.md (secção 2.1 actualizada)

---

## BUG-000106 (sonnet-4-6, 2026-09-06) — XP não aparecia no diálogo de recompensa da gangue

[Sintoma]
Ao vencer uma gangue, o diálogo "Vitória sobre a gangue!" mostrava apenas as
moedas ganhas — o XP não aparecia, apesar de ter sido aplicado correctamente
à conta.

[Causa]
`Live::attachGuildBattleState()` preenchia o array `guild_dungeon_battle_rewards`
(enviado no `accountState`) com `'xp' => 0` hardcoded. O cliente lê este array
para construir o `DOGuildDungeonBattleReward` e só adiciona XP ao diálogo de
recompensa se `get_xp() > 0` (via `GuildDungeonBattleReward.get_genericRewards()`
linha ~2132 do min.js). Como chegava sempre `0`, o XP nunca era mostrado.

[Correcção]
```php
'xp' => (int)($myReward['xp'] ?? 0),
```

[Ficheiros]
- server-laravel/app/HeroZero/Live.php (attachGuildBattleState ~L1084)
- docs/Guilda/guild_atacar_gang_npcs.md (secção 2.1 actualizada)

---

## BUG-000107 (sonnet-4-6, 2026-09-06) — Laboratório cibernético dava bónus mesmo quando guardado (fora da grelha)

[Sintoma]
Remover o laboratório cibernético (`kybernetic_improvement`) da grelha do hideout
não retirava o bónus de stats (+N%) nem o bónus de budget de itens (+N flat).
O personagem mantinha os stats inflacionados mesmo com a sala guardada.

[Causa]
`Hideout::kyberneticBonuses()` verificava apenas `status = 1` (STATUS_IDLE).
O problema é que `STATUS_IDLE = 1` é o estado de uma sala **colocada e inactiva**
MAS TAMBÉM de uma sala **já guardada** — quando o storing activity termina,
`completeRoomActivity` repõe o status para STATUS_IDLE (1). Assim, uma sala
guardada continuava a devolver o bónus como se estivesse na grelha.

[Correcção]
Removida a condição `status = 1`. Em vez disso, após encontrar a sala pelo
identifier, verifica-se com `Hideout::isRoomPlaced($h, $roomId)` se o ID da sala
aparece em algum dos slots `room_slot_X_Y` da tabela `hideout`. Só nesse caso é
que o bónus é aplicado.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (kyberneticBonuses)
- docs/hideout/implementar_hideout.md (Fase 7 actualizada)

---

## BUG-000108 (sonnet-4-6, 2026-09-06) — Clube de luta privado (personal_arena) não aumentava Coragem/Adrenalina máximas

[Sintoma]
A sala `personal_arena` estava colocada na grelha mas os caps de Coragem
(max_duel_stamina) e Adrenalina (max_league_stamina) não aumentavam — a barra
de energia ficava sempre no valor base independentemente do nível da sala.

[Causa]
A sala não estava implementada. Nenhum código no servidor lia os
`passiv_bonus_amount_1/2` da `personal_arena` nem os somava aos caps.

[Causa secundária]
Mesmo após implementar o bónus, os valores enviados ao cliente continuavam
errados. O `characterData()` serializa `max_duel_stamina` e `max_league_stamina`
directamente da coluna DB (via template overlay) e depois aplica apenas os
troféus de guilda (`GuildArtifacts::effectiveMax`). O bónus do `personal_arena`
não era somado nessa serialização, pelo que a barra exibida no cliente ficava
sempre no valor base mesmo que a regeneração interna já usasse o cap correcto.

[Correcção]
Adicionado `Hideout::personalArenaBonuses(characterId)` — lê a sala da DB,
verifica se está na grelha via `isRoomPlaced`, e devolve
`{duel_stamina: +N, league_stamina: +N}` flat lidos das constantes.
O bónus é somado em três locais:
- `Character::syncDuelStamina()` → cap de regeneração da Coragem
- `League::syncStamina()` → cap de regeneração da Adrenalina
- `Character::characterData()` → valores `max_duel_stamina`/`max_league_stamina`
  enviados ao cliente (a seguir ao loop de `GuildArtifacts::effectiveMax`)

Escala: +5 Coragem e +2 Adrenalina por nível (nível 25 = +125/+50).

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (personalArenaBonuses)
- server-laravel/app/HeroZero/Character.php (syncDuelStamina, characterData)
- server-laravel/app/HeroZero/League.php (syncStamina)
- docs/hideout/implementar_hideout.md (Fase 7 actualizada)

----------------------------------------------------------------------------
## BUG-000109 (sonnet-4-6, 2026-09-08) — Flag premiumShop invertida: loja 1 gerava itens desportivos e loja 2 gerava catálogo geral

[Sintoma]
A loja 1 (donuts, shop_index=1) mostrava apenas itens desportivos/mundiais.
A loja 2 (desportiva, shop_index=2) mostrava itens do catálogo geral (~3700 items).
O comportamento esperado era o oposto.

[Causa]
Em `Character::refreshShopItems()`, a chamada a `generateShopItem()` passava
`$shopIndex === 2` como flag `$premiumShop`:

```php
$newId = $this->insertItemFromTemplate($this->generateShopItem($shopIndex === 2));
```

Dentro de `generateShopItem(bool $premiumShop)`, quando `$premiumShop = true`
usa o catálogo geral (`shopIdentifierCatalog()`), e quando `false` usa o
catálogo desportivo (`shopSportsCatalog()`). A condição estava invertida — a
loja 2 recebia `true` e ficava com o catálogo errado.

[Correcção]
Inverter a condição para `$shopIndex === 1`:

```php
$newId = $this->insertItemFromTemplate($this->generateShopItem($shopIndex === 1));
```

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (refreshShopItems)

----------------------------------------------------------------------------
## BUG-000110 (sonnet-4-6, 2026-09-08) — Loja 1 gerava apenas itens comuns; distribuição de qualidade incorrecta

[Sintoma]
A loja de donuts (shop_index=1) mostrava quase exclusivamente itens comuns
(quality=1). Itens raros e épicos raramente ou nunca apareciam.

[Causa]
A distribuição de qualidade em `generateShopItem()` usava sempre a mesma
tabela independentemente da loja:

```php
$quality = $roll <= 70 ? 1 : ($roll <= 95 ? 2 : 3);
// → 70% comum, 25% raro, 5% épico (mesmo na loja de donuts)
```

Não havia ramo separado para `$premiumShop = true`.

[Correcção]
Adicionado ramo condicional — a loja de donuts usa distribuição premium:

```php
if ($premiumShop) {
    // 40% comum, 30% raro, 30% épico
    $quality = $roll <= 40 ? 1 : ($roll <= 70 ? 2 : 3);
} else {
    // 70% comum, 25% raro, 5% épico (loja desportiva)
    $quality = $roll <= 70 ? 1 : ($roll <= 95 ? 2 : 3);
}
```

[Ficheiro]
- server-laravel/app/HeroZero/Character.php (generateShopItem)


## BUG-000111 (sonnet-4-6, 2026-09-08) — Sidekick libertado continuava a aparecer no painel "Meus" sem refresh

[Descrição]
Ao clicar em "Largar" num sidekick, o pet não desaparecia imediatamente do
painel "Meus Acompanhantes". Só após fazer refresh manual (getSidekicks) é que
o pet deixava de aparecer. O utilizador tentava soltar o mesmo pet múltiplas
vezes porque o UI não actualizava.

[Causa]
O cliente HTML5 usa `refreshSidekick(data)` para fazer merge por `id` na lista
interna `character._sidekicks` — mas esse método nunca apaga entries, apenas
actualiza ou acrescenta. O painel "Meus" filtra por `status==1`, por isso a
única forma de excluir um pet do painel é actualizar o seu `status` para 0.

A resposta do `releaseSidekick` não incluía o sidekick libertado com
`status=0`, portanto o cliente mantinha a entrada antiga e o pet continuava
visível.

Adicionalmente, o `all_sidekicks` (catálogo com `now_owned`) só era enviado na
resposta de `getSidekicks`, não na de `releaseSidekick`, o que impedia que o
separador "Todos" actualizasse o estado owned/not-owned.

[Correcção]
- `releaseSidekick` passa a incluir `extra['sidekick']` com o DO do pet
  libertado com `status=0`. O `updateData` do cliente chama `refreshSidekick`
  que actualiza o entry existente; o filtro `status==1` exclui-o do painel.
- `all_sidekicks` adicionado à lista de acções que o incluem na resposta
  (`releaseSidekick`, `mergeSidekick`), para que o separador "Todos" actualize
  `now_owned` imediatamente.

[Ficheiros]
- server-laravel/actions/sidekicks.php (releaseSidekick case + lista needsCatalog)

----------------------------------------------------------------------------
## BUG-000112 (sonnet-4-6, 2026-09-10) — Sistema de treinos: correcção geral de múltiplos problemas

[Sintoma]
O sistema de treinos apresentava vários problemas em simultâneo: quests de
combate e habilidade mostravam "LOC" no nome do oponente/treinador; só
apareciam missões com dificuldade fácil; o botão de +15 energia (buyTrainingEnergy)
não tinha efeito visível; moedas e XP das quests não escalavam com o nível;
stat_points por estrela eram sempre 1/2/3 independentemente do custo do treino;
a barra de progresso usava percentagem fixa (+34) em vez de valor absoluto variável.

[Causa]
Múltiplos problemas acumulados (ver lista completa em docs/treinos/implementacao.md):
- `fight_difficulty` gerado como 0/1/2 em vez de 1/2/3 → cliente sem chave de locale → LOC
- `energy_cost` fixo por tipo em vez de dinâmico (3–9)
- `training_progress` fixo (+34) em vez de variável (45–180)
- Recompensas de moedas/XP das quests hardcoded (5×slot / 10×slot)
- `buyTrainingEnergy` actualizava `training_count` (sessões diárias) em vez de `training_energy` (+15)
- `used_resources` em percentagem (0–100) em vez de valor absoluto (0–needed_energy)
- `stat_points_star_N` fixo 1/2/3 em vez de escalado com custo (cost×0.25 / cost×0.50)

[Correcção]
Ver documentação completa em `docs/treinos/implementacao.md` (secção "Por fazer")
e fórmulas detalhadas em `docs/treinos/formulas.md`.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (trainingQuestsData, claimTrainingQuestRewards,
  trainingsData, claimTrainingStar, buyTrainingEnergy, + novos métodos trainingStatPoints,
  trainingQuestCoins, trainingQuestXp)
- server-laravel/actions/buyTrainingEnergy.php

----------------------------------------------------------------------------
## BUG-000113 (sonnet-4-6, 2026-09-10) — Painel de missões: "Skin not found: gods_1" ao navegar para a cidade

[Sintoma]
Ao abrir o painel de missões o cliente lançava a excepção
`uncaught exception: Skin not found: gods_1` e o background da cidade não renderizava.
O erro era reportado via `gameReportError` com `Last Action: getDailyBonusRewardData`.

[Causa]
O cliente lê o campo `season` da resposta do servidor e chama
`K.set_currentSeason(valor)` para definir o skin Spine do background das cidades
(`location1_animated.json`, etc.). Esses spines têm skins de estação do ano:
`summer`, `winter`, `spring`, `autumn`, `rain` — mas não têm skins de season pass.
Em `autoLoginUser.php` o campo `season` estava a ser preenchido com
`$seasonProgress['identifier']` (= `"gods_1"`), que é o identifier da season pass,
não a estação do ano. O cliente tentava aplicar o skin `"gods_1"` ao spine e falhava.

[Correcao]
`autoLoginUser.php` linha ~163: substituído `$seasonProgress['identifier']` por cálculo
da estação do ano com base no mês actual do servidor:
  Mar–Mai → "spring" | Jun–Ago → "summer" | Set–Nov → "autumn" | Dez–Fev → "winter"

[Ficheiros]
- server-laravel/actions/autoLoginUser.php (~L163)

## BUG-000114 (sonnet-4-6, 2026-09-10) — generateNewQuests ignorava parâmetro all_stages: apenas regenerava o stage actual

[Descrição]
O cliente envia `all_stages=false` (2 donuts, renovar cidade actual) ou `all_stages=true` (9 donuts, renovar todas as cidades). O servidor ignorava o parâmetro e regenerava sempre apenas o stage actual, independentemente do custo.

[Causa]
`generateNewQuests.php` não lia o parâmetro `all_stages` e não deduzia qualquer premium. `Character::regenerateQuests()` só apagava/regenerava o stage actual.

[Correcção]
- `generateNewQuests.php`: lê `all_stages`, deduz 2 ou 9 donuts conforme o caso.
- `Character::regenerateQuests(bool $allStages)`: quando `$allStages=true`, apaga quests de todos os stages desbloqueados e regenera 3 por cada um; quando `false`, comportamento anterior (só o stage actual).

[Ficheiros]
- server-laravel/actions/generateNewQuests.php
- server-laravel/app/HeroZero/Character.php (regenerateQuests)

## BUG-000115 (sonnet-4-6, 2026-09-10) — Doação de donuts à guilda sempre bloqueada

[Descrição]
Não era possível doar donuts à guilda. O cliente mostrava "Só os Donuts comprados podem ser dados de presente à Equipe" e bloqueava o botão.

[Causa]
Três problemas em cadeia:
1. `trusted: false` no template do utilizador — o cliente bloqueia a doação em contas não verificadas.
2. `getCharacterMaxSpendableAmount` devolvia `accountState` completo mas sem o campo `max_spendable_amount` que o cliente lê para saber o limite doável. Com o campo ausente (`undefined`), o cliente tratava o limite como 0.
3. `blocked_premium_currency` foi erroneamente posto a `premium_currency` (todos bloqueados) numa tentativa de correcção falhada — revertido para 0 (sem donuts bloqueados num servidor privado).

[Correcção]
- `Character::overlayUser()`: `trusted` forçado a `true`, `blocked_premium_currency` forçado a `0`.
- `charMisc.php` (`getCharacterMaxSpendableAmount`): devolve `max_spendable_amount = $char->premiumCurrency()` (donuts actuais do herói).

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (overlayUser)
- server-laravel/actions/charMisc.php

----------------------------------------------------------------------------
## BUG-000116 (gpt-6.1-sol, 2026-10-03) — Gemas e abridores criados com tipos incorrectos

[Sintoma]
Os objetos produzidos para modificar equipamento não correspondiam aos tipos
esperados pelo cliente. Uma gema podia ser tratada como equipamento, e os
consumíveis para abrir espaços não activavam o diálogo correcto.

[Causa]
- `Character::createImprovementItem()` gravava o tipo do destino (1–7) em
  `items.type`, mas o cliente exige tipo 12 para a gema. O destino é definido
  pelo identificador no catálogo `item_improvements`.
- A recolha da oficina chamava `createSpecialItem($identifier, 0)` para os
  abridores, em vez dos tipos 13 (equipamento) e 14 (sidekick).
- A criação só conhecia destinos 1–7 e raridades 1–3. Pedir um sidekick ou
  qualidade 4 caía nos valores de fallback máscara/comum.

[Correcção]
- Gemas criadas com tipo 12; o catálogo determina a compatibilidade.
- Destino 9 (`sidekick`) e raridade 4 (`legendary`) acrescentados à criação.
  Destinos/raridades inválidos são recusados em vez de usar fallback.
- `createSpecialItem()` determina os tipos 13/14 pelo identificador e cria
  uma carga para os abridores. A recolha também passa o tipo correcto.
- Scripts de reparação corrigem os tipos dos consumíveis antigos na BD.

[Validação]
Catálogo oficial consultado: 32 definições, oito destinos × quatro raridades.
Sintaxe PHP validada. Reparações de dados dependem da execução na BD do utilizador.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (createImprovementItem, createSpecialItem)
- server-laravel/actions/hideoutExtra.php (recolha de gem_production)
- scripts/testing/item_improvements.sql
- scripts/testing/migrate_sidekick_improvements.php

----------------------------------------------------------------------------
## BUG-000117 (gpt-6.1-sol, 2026-10-03) — Usar uma modificação consumia o objeto sem o aplicar

[Sintoma]
O caminho genérico de `useInventoryItem` podia consumir uma gema ou abridor sem
abrir espaço nem associar a modificação ao equipamento. A aplicação a sidekicks
também não tinha um ramo próprio.

[Causa]
O handler só tratava especificamente `special_reskill`; os restantes objetos
passavam pelo decremento de cargas/apagamento. Não processava `target_item_id`,
`target_sidekick_id` nem a confirmação `replace_existing` para modificações.

[Correcção]
- Tipos 12/13/14 encaminhados para `ItemImprovement::useItem()`.
- Abertura grava 1 no destino; aplicação grava o ID negativo da gema;
  substituição exige confirmação e elimina a gema anterior.
- Valida propriedade, presença na mochila, nível, compatibilidade, espaço
  e destino. Recusa pedidos que indiquem os dois destinos em simultâneo.
- Transação com `FOR UPDATE` e savepoint quando já existe transação: uma
  falha não consome o objeto nem altera parcialmente a associação.
- Para pets foi acrescentado `sidekicks.item_improvement_id`, com migração
  idempotente; a aplicação exige um sidekick possuído com status=1.

[Validação]
Sintaxe validada. Criados testes de integração com rollback para equipamentos
e sidekicks; a sua execução contra MySQL ficou pendente por falta de ligação
à BD nesta sessão. O utilizador confirmou a criação/visualização dos objetos
pelos scripts, o que não equivale a validar todos os casos de aplicação.

[Ficheiros]
- server-laravel/app/HeroZero/ItemImprovement.php (useItem, migrateSidekicks)
- server-laravel/actions/inventoryOps.php (useInventoryItem)
- server-laravel/docker/initdb/01_schema.sql (sidekicks.item_improvement_id)
- scripts/testing/test_item_improvements.php
- scripts/testing/test_sidekick_improvements.php

----------------------------------------------------------------------------
## BUG-000118 (gpt-6.1-sol, 2026-10-03) — Estado da conta não representava gemas associadas nem os seus atributos

[Sintoma]
O backend não tinha um caminho completo para devolver uma gema aplicada e
integrar os seus atributos no equipamento ou no acompanhante. Os dados do
sidekick expunham sempre `item_improvement_id=0`.

[Causa]
- `Character::equipBonus()` somava apenas os atributos do equipamento.
- `itemsData()` não distinguia os registos de gemas aplicadas dos itens soltos.
- Faltava a coleção persistente `item_improvements` nas respostas da conta e
  na consulta de outros heróis.
- `Sidekick::doFromRow()` forçava a associação a zero; os atributos próprios
  e o contributo do acompanhante activo não incluíam modificações.

[Correcção]
- `equipBonus()` soma os quatro atributos da gema associada ao equipamento.
- Gemas aplicadas são excluídas de `items` e enviadas em `item_improvements`.
- `Live` e `getCharacter` incluem as coleções e associações necessárias.
- Correção adicional no caminho próprio de `autoLoginUser`: o boot também
  envia `ItemImprovement::data($char->id())`. Sem isso, relogar com gemas
  aplicadas provocava `get_itemImprovement() is null` ao desenhar equipamento.
- `Sidekick::withImprovement()` acrescenta a gema às bases numa cópia do
  registo e aplica a escala de nível/raridade do sidekick. As bases originais
  na BD não são alteradas, evitando acumulação em substituições/subidas de nível.
- `activeRow()` fornece os atributos modificados ao herói; a consulta de
  outro herói evita processar duas vezes os atributos do acompanhante.
- A movimentação de inventário recusa usar uma gema aplicada como item solto.

[Validação]
Catálogo, associação no protocolo e cálculo dos atributos verificados com PDO
simulado. Testes de integração preparados para aplicação/substituição,
desequipar e exclusão da coleção de itens; execução contra MySQL pendente.
O `problema.har` confirmou quatro equipamentos com IDs negativos de gemas,
mas sem `item_improvements` na resposta de login. As quatro gemas existiam
na resposta seguinte; o reset da season não as tinha apagado.
`test_login_improvement_contract.php problema.har` passou na verificação
do contrato de boot e das referências do HAR. Login real após esta correção
ficou por confirmar pelo utilizador.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php (equipBonus, itemsData)
- server-laravel/app/HeroZero/ItemImprovement.php (data)
- server-laravel/app/HeroZero/Sidekick.php (withImprovement, doFromRow, activeRow)
- server-laravel/app/HeroZero/Live.php
- server-laravel/actions/getCharacter.php
- server-laravel/actions/inventoryOps.php (moveInventoryItem)

----------------------------------------------------------------------------
## BUG-000119 (gpt-6.1-sol, 2026-10-03) — Recolha da oficina criava o objeto sem o colocar na mochila

[Sintoma]
O registo da gema/abridor era criado em `items`, mas não havia associação a uma
posição da mochila. O objeto produzido não ficava acessível como item recolhido.

[Causa]
O ramo de recolha de `gem_production` chamava os métodos de criação sem guardar
o ID devolvido num `inventory.bag_itemN_id`.

[Correcção]
- Procura a primeira posição livre e coloca nela o ID do objeto criado.
- Mochila cheia devolve `errInventoryNoEmptySlot` e preserva a produção pronta.
- Recolha protegida por transação e bloqueios do personagem, inventário e sala,
  relendo o estado da sala antes de criar o resultado.

[Validação]
Sintaxe e inspeção do caminho de recolha validadas. A produção/recolha completa
contra a BD real não foi testada nesta sessão.

[Ficheiro]
- server-laravel/actions/hideoutExtra.php (collectHideoutRoomActivityResult, gem_production)

----------------------------------------------------------------------------
## BUG-000120 (gpt-6.1-sol, 2026-10-03) — Vendas e fusões não tratavam as gemas associadas

[Sintoma]
Ao acrescentar as associações de modificações, os caminhos existentes de venda
e fusão não eliminavam o registo da gema nem incluíam o seu valor no preço.
A fusão também não separava as bases originais das bases expostas com melhoria.

[Causa]
Os handlers apagavam directamente o equipamento/sidekick e calculavam o preço
sem consultar a associação. O reinício do nível na fusão usava as bases do DO,
que agora incluem a melhoria, em vez das bases originais guardadas na BD.

[Correcção]
- Venda individual, em lote e do banco elimina a gema associada ao equipamento.
- Preço de equipamento inclui o aumento por raridade da gema: +25%/+50%/
  +100%/+150% para comum/rara/épica/lendária, conforme o cliente.
- Libertação e consumo de sidekick numa fusão eliminam a sua gema.
- `Sidekick::sellPrice()` acrescenta o valor da modificação separado do valor
  dos atributos originais do pet, seguindo o cálculo do cliente.
- Reinício do nível na fusão usa as bases originais da BD; a gema do destino
  mantém-se associada e é acrescentada na leitura dos atributos.

[Validação]
Fórmulas confrontadas com o JS e sintaxe validada. Testes de integração de venda,
substituição e limpeza preparados; execução contra MySQL pendente.

[Ficheiros]
- server-laravel/app/HeroZero/ItemImprovement.php (deleteAttached, sellPrice)
- server-laravel/app/HeroZero/Sidekick.php (deleteImprovement, sellPrice)
- server-laravel/actions/inventoryOps.php (vendas individuais e banco)
- server-laravel/actions/sellInventoryItems.php
- server-laravel/actions/sidekicks.php (releaseSidekick, mergeSidekick)

----------------------------------------------------------------------------
## BUG-000121 (gpt-6.1-sol, 2026-10-03) — Oficina vazia e sem possibilidade de iniciar produção

[Sintoma]
Em `oficina_1.png`, a seleção do tipo de modificação não mostrava ícone nem
descrição. O HAR `debug_oficina.har` mostrava sucessivos sorteios da oficina,
sem avançar para a escolha de raridade/início da produção.

[Causa]
O servidor guardava/enviava `additional_value_2.slot`, mas o cliente procura
`getAdditionalValue("improvement_slot")`. Mesmo depois de um sorteio válido,
o cliente via o tipo como ausente e voltava a pedir outro sorteio gratuito.
O início/recolha também liam a chave antiga.

[Correcção]
- Novos sorteios guardam `improvement_slot`.
- `Hideout::improvementData()` converte dados antigos ao enviar respostas e
  ao iniciar/recolher produção, preservando o tipo e os timestamps.
- Início valida destinos 1–7/13/14 e usa `item_improvement_duration`, a chave
  oficial da duração (12 horas nas constantes consultadas).
- O início da produção guarda também os dados normalizados da sala.

[Validação]
`test_workshop_contract.php` reprocessou as seis respostas da oficina no HAR;
todas passaram a expor um tipo reconhecido pelo cliente. Sintaxe validada.
Sem necessidade de executar SQL para converter as salas antigas.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (improvementData, shapeRoom)
- server-laravel/actions/hideoutExtra.php (sorteio e recolha)
- server-laravel/actions/startHideoutRoomProduction.php
- scripts/testing/test_workshop_contract.php

----------------------------------------------------------------------------
## BUG-000122 (gpt-6.1-sol, 2026-10-03) — Opções de raridade sem identificador e recolha podia criar gema vazia

[Sintoma]
O diálogo de raridades da oficina não recebia dados suficientes para construir
os objetos de pré-visualização. Se faltasse a opção escolhida na recolha, era
possível criar uma gema com todos os atributos a zero em vez de rejeitar o estado.

[Causa]
- As opções continham `quality` e atributos, mas não `identifier` nem `id`.
  O cliente constrói um objeto de item e consulta o identificador no catálogo.
- Chaves numéricas 0/1/2 podiam ser serializadas como array JSON, em vez de
  objeto com as três propriedades esperadas.
- A recolha usava uma opção vazia como fallback e convertia atributos ausentes
  em zero. No `debug1.har` foi observada a gema `11610367`,
  `improvement_belt_common`, com os quatro atributos a zero; o HAR isolado não
  permite confirmar qual dos caminhos históricos originou esse registo.

[Correcção]
- A normalização acrescenta `id=0` e o identificador correcto por destino e
  raridade, preservando os atributos sorteados.
- `improvement_rarity` é serializado como objeto JSON com chaves 0–2.
- A recolha exige uma opção válida e copia os seus atributos; elimina-se o
  fallback que criava uma melhoria vazia quando a opção não existia.
- Gemas antigas já persistidas não são recalculadas automaticamente; podem
  ser corrigidas pelo script de atualização de atributos.

[Validação]
Teste de contrato cobre os sete destinos, três raridades, identificadores do
catálogo, preservação de atributos e normalização idempotente. Sintaxe validada.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (improvementData)
- server-laravel/actions/hideoutExtra.php (raridades e recolha)
- scripts/testing/test_workshop_contract.php
- scripts/testing/update_item_improvement.php

----------------------------------------------------------------------------
## BUG-000123 (gpt-6.1-sol, 2026-10-03) — Oficina interpretava free=false como verdadeiro e não devolvia erro de sorteio gratuito

[Sintoma]
Um pedido pago de sorteio podia ser interpretado como gratuito. Um pedido
gratuito repetido podia descontar donuts em vez de devolver o erro esperado
pelo cliente.

[Causa]
O cast `(bool)` sobre a string `"false"` em PHP devolve true. Os handlers de
slot/raridade usavam esse cast e, se o sorteio gratuito já tivesse sido usado,
passavam directamente para o débito sem distinguir um pedido pago de um
pedido gratuito inválido.

[Correcção]
- `free` interpretado com `FILTER_VALIDATE_BOOLEAN` nos dois sorteios da oficina.
- Pedido gratuito repetido devolve `errRefreshHideoutItemImprovementSlotNotFree`
  ou `errRefreshHideoutItemImprovementRarityNotFree`.
- Só pedidos pagos passam pelo débito do custo de renovação.
- Sorteios recusados enquanto a sala não está disponível, preservando o
  tipo/atributos associados a uma produção manual em curso.

[Validação]
Parâmetros textuais confrontados com `debug_oficina.har` e ramos revistos.
Sintaxe validada; débitos e bloqueios não testados contra MySQL nesta sessão.
A alteração foi limitada aos sorteios da oficina, não a todos os casts do projeto.

[Ficheiro]
- server-laravel/actions/hideoutExtra.php (refreshHideoutItemImprovementSlot,
  refreshHideoutItemImprovementRarity)

----------------------------------------------------------------------------
## BUG-000124 (gpt-6.1-sol, 2026-10-03) — Evoluir a oficina criava um resgate fictício de modificação

[Sintoma]
Depois de evoluir a oficina, aparecia uma modificação para resgatar sem ter sido
iniciada produção. Em `oficina_evol.har`, a sala 33 subia de nível 8 para 9,
ficava com status=6 e `ts_activity_end=0`, e a tentativa de recolha devolvia
`errStartHideoutRoomProductionInvalidProductionCount`.

[Causa]
`Hideout::completeRoomActivity()` usava `isProductionRoom()` para decidir o
estado após construir/evoluir/colocar. Essa classificação inclui produção
automática e manual. A oficina manual passava a PRODUCING sem temporizador,
e o cliente interpretava-a como produção terminada. O problema abrangia
também as outras salas de produção manual.

[Correcção]
- Só salas de produção automática retomam STATUS_PRODUCING nesses três caminhos.
- Salas manuais ficam em STATUS_IDLE, sem resultado/quantidade para resgatar.
- `normalizeManualRoomState()` identifica o estado antigo impossível:
  sala manual em PRODUCING com temporizador zero.
- `roomsFor()` e `roomById()` recuperam e persistem esse estado, preservando
  o nível e os dados da oficina. O UPDATE é condicionado ao estado inválido.
- Produções reais com temporizador positivo, incluindo as já terminadas,
  e salas de produção automática não são alteradas pela recuperação.

[Validação]
`test_workshop_upgrade.php` executa conclusão de construção/evolução/colocação
com PDO simulado para o contador de objetivos. Confirma nível, estado disponível
e ausência de resgate. Cobre recuperação de todas as salas manuais e preservação
das produções reais/automáticas. Os três estados inválidos capturados no HAR
passaram a disponíveis sem perder o nível. Sintaxe validada.
Persistência da recuperação contra a BD real ficou por confirmar no jogo.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php (completeRoomActivity,
  normalizeManualRoomState, repairManualRoomState, roomsFor, roomById)
- scripts/testing/test_workshop_upgrade.php

[Documentação desta série]
- docs/modificações/implementacao.md
- docs/shop_items/modificacoes.md
- docs/sidekicks/modificacoes.md

Nota: o cinto não aparecer ao usar uma gema quando não tem espaço é o
comportamento esperado do cliente, não um bug adicional. É necessário usar
primeiro o consumível tipo 13. Os scripts auxiliares e a criação de suporte
novo não são, por si só, evidência de um erro observado no jogo.

----------------------------------------------------------------------------
## BUG-000125 (gpt-6.1-sol, 2026-10-03) — Modificações do passe marcadas como resgatadas sem gema utilizável na mochila

[Sintoma]
Em `coletar.har`, seis recompensas de modificações (2, 5, 15, 29, 38 e 45)
ficavam resgatadas sem entregar uma gema nova na mochila.

[Causa]
`Season::giveImprovementItem()` inseria registos em `pattern_items`, com
atributos a zero, em vez de criar gemas reais em `items` e ligar o inventário.
O resgate também não protegia a entrega e a marcação com uma única transação.
Este caminho era específico da season, diferente da recolha da oficina
documentada nos BUG-000119 e BUG-000122.

[Correcção]
- Usa o catálogo de modificações e `Character::createImprovementItem()`:
  tipo 12, identificador/raridade corretos e atributos não nulos.
- Coloca a gema na primeira posição livre da mochila; mochila cheia provoca
  erro e mantém a recompensa por resgatar.
- `claimReward()` bloqueia personagem, inventário e progresso; só marca a
  recompensa depois da entrega. Usa transação/savepoint e rollback em falhas.
- A geração dos atributos usa balanceamento local provisório, documentado
  em `docs/seasons/atual.md`; não é apresentada como fórmula oficial.
- Registos históricos em `pattern_items` não são convertidos automaticamente.

[Validação]
Sintaxe validada. Os 12 casos offline de atributos (níveis 1/50/335,
quatro raridades) passaram. `test_season_rewards.php` inclui testes de entrega,
mochila cheia, repetição e ausência de gemas órfãs; integração MySQL pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Season.php (claimReward, giveImprovementItem, improvementStats)
- scripts/testing/test_season_rewards.php
- docs/seasons/atual.md

----------------------------------------------------------------------------
## BUG-000126 (gpt-6.1-sol, 2026-10-03) — Pontos gratuitos da season entregavam donuts em vez de pontos de atributos

[Sintoma]
Ao resgatar a recompensa de 10 pontos, o contador gratuito junto aos atributos
continuava a zero. `debug_pontos_stats_season.har` mostrou o resgate 14;
`pontos_stats_season.png` identificou o contador esperado pelo utilizador.

[Causa]
O tipo de recompensa 3 estava agrupado com o tipo 2 (donuts).
A primeira revisão só cobria o tipo 11 e não corrigia o tipo 3 efetivamente
resgatado. O funcionamento dos torneios semanais confirmou o campo correto:
`character.stat_points_available`.

[Correcção]
- Tipo 3 encaminhado para crédito de pontos gratuitos.
  O tipo 11 tinha sido também identificado como pontos incorretamente;
  corresponde a supercola e foi corrigido em BUG-000129.
- Tipo 2 continua a entregar donuts; tipo 3 deixa de alterar premium_currency.
- Crédito e marcação do resgate ficam na mesma transação, com verificação da escrita.
- `Character::improveStat()` já consome esses pontos antes de cobrar moedas:
  não foi necessário alterar esse comportamento nem aumentar atributos no resgate.

[Validação]
O utilizador confirmou que passou a funcionar ("agora foi").
`test_season_stat_points.php` verifica atualmente o dispatch real do tipo 3
com PDO simulado, permitindo apenas SQL de crédito de pontos. A versão anterior
também testava o tipo 11, mas esse teste confirmava um mapeamento errado
(supercola tratada como pontos), entretanto removido em BUG-000129.
Teste MySQL preparado para duplicados e consumo gratuito sem cobrar moedas.
Resgates antigos não são compensados automaticamente.

[Ficheiros]
- server-laravel/app/HeroZero/Season.php (deliverReward)
- scripts/testing/test_season_stat_points.php
- scripts/testing/test_season_rewards.php

----------------------------------------------------------------------------
## BUG-000127 (gpt-6.1-sol, 2026-10-03) — Primeira abertura do passe mostrava cadeados falsos nas recompensas

[Sintoma]
Na primeira abertura apareciam cadeados nas recompensas já atingidas;
fechar e voltar a abrir fazia-os desaparecer. Evidência: `season.har`
e `season_lock.png`.

[Causa]
O cliente desenha o painel antes da resposta de `openSeasonPanel`.
O login enviava o progresso, mas não pré-carregava as recompensas reais.
A primeira abertura não tinha esses estados em memória; a segunda já tinha.
As duas respostas de abertura no HAR continham estados iguais e corretos,
sem bloqueios por pontos insuficientes nas recompensas atingidas.

[Correcção]
`autoLoginUser` passa a enviar `Season::rewardsForCharacter($char->id())`
em `season_rewards`, juntamente com o progresso real. Não altera os pontos,
os resgates, a compra do passe nem a política de acesso às recompensas premium.

[Validação]
Sintaxe validada. `test_season_boot_contract.php season.har` passou:
verifica o pré-carregamento no código de login e os estados iguais das duas
respostas capturadas. Confirmação visual após relogar ainda pendente.

[Ficheiros]
- server-laravel/actions/autoLoginUser.php
- scripts/testing/test_season_boot_contract.php
- docs/seasons/atual.md

----------------------------------------------------------------------------
## BUG-000128 (gpt-6.1-sol, 2026-10-03) — Chamadas heroicas sem implementação, prémios variados e pontos da temporada

[Sintoma]
O painel de chamadas de emergência mostrava cinco cartões bloqueados com
"a partir da próxima segunda-feira", sem objetivos utilizáveis. Evidência:
`missoes.har` e `missoes.png`.
Depois da primeira implementação, as chamadas davam apenas XP e o resgate
não avançava a progressão do passe de temporada.

[Causa]
- O login não enviava `herobook_objectives` nem as constantes necessárias.
  O JavaScript mostrava o aviso de segunda-feira como fallback para objetivo
  ausente, não como indicação de um agendamento real.
- `collectHerobookReward`, `instantFinishHerobookObjective` e
  `renewHerobookObjective` apontavam para `stateEcho.php`, sem lógica persistente.
- A primeira versão usava XP fixado por fórmula local e não sorteava prémios
  pelas tabelas `herobook_daily_rewards`/`herobook_weekly_rewards`.
- Faltava servir `season_locations` e creditar os pontos no resgate.
- Energia, unidades de treino e recursos eram creditados diretamente, sem
  cupões para guardar os prémios. O treino aumentava `training_energy` em vez
  de representar unidades em `training_count`; os créditos com `LEAST` podiam
  perder quantidades quando a barra ou o depósito já estavam cheios.

[Correcção]
- Implementado `Herobook` com geração, persistência na tabela já existente,
  renovação diária/semanal, progresso e estados do contrato do cliente.
- Ligadas sete atividades suportadas: lavagem, renovação da loja, compra de
  atributos pagos, duelos, missões de tempo, missões e energia de missão.
  Tipos ainda sem integração não são gerados como objetivos impossíveis.
- Criado handler próprio para resgate, conclusão por donuts e troca, com
  validações de propriedade/estado/saldo, locks, transação e savepoint.
- Sorteio de nove categorias de prémio pelos pesos oficiais; quantidades
  fixas e bónus de equipamento lidos do catálogo. Fórmulas escaladas continuam
  locais e estão documentadas, não apresentadas como oficiais.
- Equipamento é enviado como preview antes de abrir o painel e colocado na
  mochila no resgate. Mochila cheia reverte a entrega; previews não entregues
  são limpos ao trocar, descartar ou expirar, preservando itens no inventário/banco.
- Pontos de temporada creditados no resgate, na mesma transação do prémio e
  status: `gods_1` dá 1 nas diárias e 5 nas semanais, conforme locais 14/13.
  Repetição não duplica e descarte não entrega pontos.
- Chamadas antigas mantêm os prémios já mostrados; resgates antigos não são
  compensados automaticamente.
- Energia, unidades de treino, supercola e zenit passam a gerar cupões pessoais
  persistentes nas mensagens, na mesma transação da chamada e dos pontos.
  Login e respostas da conta enviam coleção e notificações reais.
- O resgate posterior do cupão aumenta o saldo correto: unidades em
  `training_count`, não na barra de energia do treino. Lotes são atómicos;
  depósito cheio mantém os cupões por usar, sem truncar prémios nem duplicar.

[Validação]
O utilizador confirmou funcionamento após corrigir prémios e progressão da season
("Ok agora ta"). Isto não prova todos os nove prémios ou todos os casos-limite.
`test_herobook.php --offline` passou: 53 definições, pools viáveis, prazos,
todos os intervalos do sorteio e pontos 1/5. Sintaxe validada.
Teste MySQL com rollback preparado para geração sem duplicados, progresso,
resgate, pontos sem duplicação, troca, conclusão paga, saldo insuficiente e
renovação de vagas expiradas; execução automatizada pendente por falta de ligação.
Restantes atividades, bónus diário por três chamadas e fórmulas/limites oficiais
continuam pendentes; não são considerados corrigidos por esta entrada.
O fluxo dos cupões tem testes offline e resgate com PDO simulado, incluindo
duplicados, propriedade, depósito cheio e rollback. A confirmação posterior
"Pra ja ta boum" refere-se ao comportamento observado pelo utilizador, não
substitui os testes MySQL nem confirma individualmente todos os tipos de prémio.

[Ficheiros]
- server-laravel/app/HeroZero/Herobook.php
- server-laravel/actions/herobook.php
- server-laravel/app/HeroZero/Router.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/app/HeroZero/Character.php
- server-laravel/actions/autoLoginUser.php
- server-laravel/actions/claimDuelRewards.php
- scripts/testing/test_herobook.php
- docs/missoes/geral.md
- docs/missoes/chamadas_emergencia.md
- server-laravel/app/HeroZero/UserVouchers.php
- server-laravel/actions/vouchers.php
- server-laravel/actions/socialState.php
- scripts/testing/test_user_vouchers.php
- scripts/testing/test_user_vouchers_mock.php
- docs/cupoes/mensagens.md

----------------------------------------------------------------------------

## BUG-000129 (2026-10-03, gpt-6.1-sol) — Recompensas da temporada com tipos trocados, energia sem cupão

[Sintoma]
`resgate.har` mostra resgates 8, 17, 32, 39, 52 e 59 marcados como recebidos,
sem acrescentar cupões de energia: faltam 340 energias. Seis prémios de zenit
foram apresentados/guardados como energia. Resgates de treino 25 e 47 criaram
corretamente cupões de 5/10 unidades. A captura não contém resgate dos cupões
das mensagens: saldos do herói inalterados nessa fase são esperados.

[Causa]
O servidor identificava tipo 8 como XP, 4 como energia da barra de treino,
11 como pontos gratuitos e 12 como energia/depósito. Uma revisão inferiu
incorretamente energia = factor × 5. O mapeamento real está em
`et.refreshWithoutReward()` no JS: 4 XP, 6 unidades, 8 energia, 11 cola, 12 zenit.
O prémio de 60 energias aos 8 pontos é o tipo 8 da faixa premium; não o tipo 12.

[Correcção]
- Dispatch e JSON de apresentação alinhados com o JS; energia/unidades usam
  quantidade direta e criam cupões na transação de resgate.
- XP no tipo 4; pontos gratuitos apenas no tipo 3. Recursos 11/12 ficam em
  cupões de cola/zenit, com quantidade local provisória = factor. Fórmulas
  oficiais escaladas de XP/recursos continuam desconhecidas.
- Script explícito recupera energia em falta demonstrada pelo HAR e corrige
  apenas cupões de zenit identificados ainda não usados, com transação,
  validação de dono/progresso e marca idempotente por progresso/prémio.
- Não remove XP/atributos históricos nem modifica cupões já usados.

[Validação]
Comparação automática dos cinco tipos com o método real do JS passou.
Análise do HAR confirmou seis omissões/340 energias e seis cupões errados.
Dispatch real 6/8/11/12 com PDO simulado, testes offline dos cupões/pontos
e sintaxe PHP passaram. O utilizador confirmou depois "Pra ja ta boum".
Isso confirma o fluxo observado, não todos os casos-limite nem a execução
do script de recuperação. Integração MySQL automatizada continua por validar;
não foram alterados dados persistentes pelo agente nesta sessão.

[Ficheiros]
- server-laravel/app/HeroZero/Season.php
- scripts/testing/season_coupon_har.php
- scripts/testing/test_season_coupon_mapping.php
- scripts/testing/repair_season_coupons_from_har.php
- scripts/testing/test_user_vouchers.php
- scripts/testing/test_user_vouchers_mock.php
- scripts/testing/test_season_stat_points.php
- scripts/testing/test_season_rewards.php
- docs/cupoes/mensagens.md
- docs/seasons/atual.md
- docs/seasons/implementar.md

----------------------------------------------------------------------------
## BUG-000130 (2026-10-04, gpt-6.1-sol) — Etapas das histórias sem progresso nem prémios reais

[Sintoma]
As tarefas “faz X” no painel de missões não avançavam nem entregavam o XP e
equipamento apresentado. Em `missoes.har` (servidor local), as etapas mantinham
`points_collected=0`, `rewards=""` e apenas avançavam por conclusão premium.

[Causa]
`storyDungeon.php` era um fluxo provisório: concluir alterava diretamente para
status 4, não tinha contadores e cobrava sempre 1 donut. O resgate marcava a etapa
concluída e dava 100 moedas fixas, sem XP/equipamento; ainda desbloqueava uma zona
por etapa. O cliente distingue 3 (prémio pendente) de 4 (prémio processado).

[Correcção]
Serviço StoryDungeon com catálogo oficial, validação de sequência/nível, atividades
ligadas, objetos nas missões e progresso persistente. Prémios de XP/equipamento
com previews, mochila cheia/descarte e resgate único. Custos proporcionais ao
progresso como no cliente; chefe com Fight, cooldown e batalhas reais. Locks,
transações/savepoints e rollback conjunto com as atividades. Estado e referências
incluídos no login, accountState e respostas parciais. Estados antigos ainda por
resgatar são normalizados sem compensação automática de prémios já consumidos.

[Validação]
`php -l` nos PHP alterados; catálogo/fórmulas offline e testes com PDO simulado
passaram, incluindo consumos/resgates repetidos, mochila cheia, rollback, fontes
reais de missão/energia, objetos e combate. Integração MySQL não validada: PDO 2002.
Teste MySQL com rollback preparado; confirmação visual pendente. A etapa 7/4,
dependente de missão especial ainda sem implementação, continua sem produtor de
progresso normal. Repetições não fazem parte desta implementação. Ver
`docs/missoes/historias.md` para distinguir dados oficiais de geração local.

[Ficheiros]
- server-laravel/app/HeroZero/StoryDungeon.php
- server-laravel/app/HeroZero/Character.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/app/HeroZero/Router.php
- server-laravel/actions/storyDungeon.php
- server-laravel/actions/claimDuelRewards.php
- scripts/testing/test_story_dungeon.php
- scripts/testing/test_story_dungeon_mock.php
- docs/missoes/historias.md
- docs/missoes/geral.md

----------------------------------------------------------------------------
## BUG-000131 (2026-10-04, gpt-6.1-sol) — Comprar para a mochila equipava o item automaticamente

[Sintoma]
No `bug_brecho.har`, `buyShopItem` com `target_slot=9` equipa uma arma e com
`target_slot=11` equipa um fato, deslocando o equipamento anterior para a mochila.
O utilizador tinha arrastado as compras para a mochila.

[Causa]
`Character::buyShopItem()` interpretava apenas destinos de equipamento. Destinos
9–26 caíam num fallback pelo tipo do item, equipando-o.

[Correcção]
Respeitar 1–8 como equipamento e 9–26 como mochila, removendo o fallback.
Validar compatibilidade/nível ao equipar; conservar itens deslocados noutra vaga
ou rejeitar sem cobrança quando cheia. Compra e débito usam locks/transação,
com savepoint quando já existe transação. Consumíveis respeitam destino/moeda;
o ramo de moedas dos sidekicks usa `character.game_currency`.

[Validação]
Sintaxe PHP e `test_shop_purchase.php` com PDO simulado passaram: destinos
9/11/26, equipamento 6, destino ocupado, mochila cheia, incompatibilidade, nível,
saldo insuficiente, débito falhado, moeda, repetição e rollback exterior.
Integração MySQL e confirmação visual ainda pendentes; sem alterações de dados reais.

[Ficheiros]
- `server-laravel/app/HeroZero/Character.php`
- `server-laravel/actions/buyShopItem.php`
- `scripts/testing/test_shop_purchase.php`
- `docs/shop_items/lojas.md`

----------------------------------------------------------------------------
## BUG-000132 (2026-10-04, gpt-6.1-sol) — Itens desapareciam visualmente ao guardar no cofre

[Sintoma]
Itens saíam da mochila sem aparecer no cofre. O HAR lock_inventario.har mostrava
sucesso nos depósitos, sem bank_inventory nas respostas.

[Causa]
Live::accountState omitia o cofre e autoLoginUser esvaziava-o. O handler ignorava
bank_index/target_slot_index e tratava max_bank_index como capacidade, enquanto
o cliente usa páginas de 18 posições e o índice da última página desbloqueada.
A tabela só tinha 90 posições. Os dois itens investigados continuavam na BD,
em bank_item2_id e bank_item3_id; não tinham sido apagados.

[Correcção]
Estado persistido incluído nos dois caminhos. Operações centralizadas com
validação de propriedade/destino, transações/savepoints e locks. Destinos e
vendas por página respeitam o cliente. Bloqueios persistidos e expansões com
custos das constantes. Migração idempotente até 450 posições, com backup dos
valores antigos e conversão da capacidade antiga para páginas completas.

[Validação]
Integração MySQL com rollback: depósito, retirada, troca, venda bloqueada,
destino inválido, expansão/custo/saldo insuficiente, resposta normal e
handler autoLoginUser. Migração repetida sem reconversão. PHP lint nos ficheiros
alterados. Confirmação visual no cliente pendente.

[Ficheiros]
- server-laravel/app/HeroZero/BankInventory.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/actions/autoLoginUser.php
- server-laravel/actions/inventoryOps.php
- server-laravel/docker/initdb/01_schema.sql
- scripts/testing/migrate_bank_inventory.php
- scripts/testing/test_bank_inventory.php
- docs/inventario/implementacao.md
- docs/shop_items/cofre.md (referência para a documentação do inventário)

----------------------------------------------------------------------------
## BUG-000133 (2026-10-04, gpt-6.1-sol) — Mísseis da temporada eram entregues como armas normais

[Sintoma]
Objetos missiles_* tinham tipo 6, zero cargas e atributos de arma; não eram
munição utilizável na posição de arremesso nem no cinto.

[Causa]
Season::giveSeasonItem não mapeava missiles e usava o fallback de arma. O cinto
também tinha handlers incompletos que ignoravam equip e não validavam origem,
tipo, nível ou posições bloqueadas. O combate não registava m nem consumia cargas.

[Correcção]
Prémios missiles_* usam Missiles::give, tipo 8 e pacotes com 100 cargas (regra
local explícita). Operações de munição e combate ligadas com transações e locks;
débito/recarga após persistir a batalha. Script repair_missiles.php converte os
objetos antigos com backup, preservando IDs e devolvendo gemas à mochila quando
necessário. Os dois objetos encontrados nesta BD foram convertidos.

[Validação]
PHP lint e testes offline/MySQL com rollback. test_season_rewards.php confirma
recompensa 9 tipo 8 e rejeição do segundo resgate. test_missiles.php confirma dano,
cinto, compras, consumo/recarga e retomas sem novo consumo. Reparação repetida
identifica zero objetos elegíveis e não repõe cargas. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Missiles.php
- server-laravel/app/HeroZero/Fight.php
- server-laravel/app/HeroZero/Character.php
- server-laravel/app/HeroZero/Season.php
- server-laravel/app/HeroZero/StoryDungeon.php
- server-laravel/actions/inventoryOps.php
- server-laravel/actions/startDuel.php
- server-laravel/actions/leagueMissed.php
- scripts/testing/repair_missiles.php
- scripts/testing/test_missiles.php
- scripts/testing/test_season_rewards.php
- docs/inventario/misseis.md

----------------------------------------------------------------------------
## BUG-000139 (2026-10-04, gpt-6.1-sol) — Ícone incorreto no resultado do torneio PvP

[Sintoma]
Em `imagem.png`, a linha «Torneio PvP» do diálogo de resultados apresentava
um pequeno punho branco em vez do ícone de honra.

[Causa]
O construtor `UiTournamentRewardInfo` substituía o ícone por uma imagem com
textura `fist`, pertencente aos emblemas de guilda, com dimensões inadequadas.

[Correcção]
Removida a substituição. O elemento visual passa a usar `SymbolIconHonorGeneric`
(`icon_honor`, 30×32), preservando o nome do campo, posição, valores e tipos.
Atualizada a versão do bundle no HTML para renovar a cache do cliente.

[Validação]
`node --check` passou. Teste temporário offline executou os construtores reais
do bundle com componentes gráficos simulados: textura de honra, dimensões,
visibilidade dos três tipos e valor apresentado preservados.
`test_daily_bonus_client.js` também passou. Confirmação visual no browser pendente.
Não foram alterados dados da BD.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- docs/torneios_semanais/recompensas.md

----------------------------------------------------------------------------
## BUG-000134 (2026-10-04, gpt-6.1-sol) — Calendário do cliente mudava à 01:00 ao simular outra estação

[Sintoma]
Em hum.png/hum.har, Novembro simulado com o computador em Outubro mostrava a
janela de Dias de Bónus uma hora depois da meia-noite da barra do servidor.
A janela continha sempre as duas datas antigas da captura, com «Hoje» vazio.

[Causa]
DateTime.get_utcLocalTimestampOffset() usava a data real do browser enquanto
TimeUtil.timestampCorrection transportava o relógio para a data simulada.
Ao atravessar a mudança da hora, o offset local ficava desfasado uma hora.
bonus_info vinha das duas datas fixas de Julho no template de autoLoginUser.

[Correcção]
Offset local calculado na data corrigida; calendário de eventos reais substitui
a fixture no login e no estado normal. Calendário vazio não abre diálogo.
Numeração dos eventos arredonda diferença de dias para suportar dias de 23/25h.

[Validação]
node scripts/testing/test_daily_bonus_client.js executa funções reais do bundle
com Outubro real e Novembro/Julho/Março simulados. Meia-noite e número do dia
correctos, sem diálogo vazio. PHP/MySQL: calendário e login real com rollback.
Confirmação visual do utilizador pendente.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- server-laravel/app/HeroZero/BonusEvents.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/actions/autoLoginUser.php
- scripts/testing/test_daily_bonus_client.js

----------------------------------------------------------------------------
## BUG-000135 (2026-10-04, gpt-6.1-sol) — Login falhava ao abrir Dias de Bónus com calendário vazio

[Sintoma]
Depois de avançar o relógio, `autoLoginUser` provocava a excepção
`a[0] is undefined` em `DialogBonusInfoNotification` (linha 4367 do bundle).
`ui.har` contém duas respostas com `bonus_info.bonus_datas = []` e prémio de
login normal do dia 2.

[Causa]
A protecção acrescentada para o calendário vazio verificava `.length`, mas
`DOBonusInfo.get_bonusDatas()` devolve um `DataObjectArray` (`od`), cuja lista
interna é `.a`. O wrapper não tem `.length`: a condição não impedia abrir o diálogo.
O teste inicial usava um array simples, não a conversão/tipo reais do cliente.

[Correcção]
`BonusInfo.get_needsDisplay()` verifica `.a.length`. O teste passou a executar
`DOBonusInfo.get_bonusDatas`, `DataObjectArray`, o iterador e `BonusInfo.update`
extraídos do bundle. A página principal carrega o JS com versão nova na URL.

[Validação]
`node --check server-laravel/public/js/HeroZero.min.js` e
`node scripts/testing/test_daily_bonus_client.js ui.har` passaram: ausência de
dados/lista vazia, evento activo com XP duplo, evento visto e evento terminado
com limpeza do multiplicador. Ambos os calendários vazios do HAR não abrem diálogo.
Regressão de calendário/DST também passou. Confirmação visual pendente.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- scripts/testing/test_daily_bonus_client.js
- docs/logins/login_diario_e_bonus.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000136 (2026-10-04, gpt-6.1-sol) — Gangue ferida mostrava interrogações depois de marcar ataque

[Sintoma]
Em `1.png`, o preview mostra 1/1 membro, nível 261 e 206.952/304.680 HP.
Depois de agendar, `2.png` mostra membros, nível e HP como `?`.

[Causa]
`gangues.har` mostra perfis corretos na seleção e na resposta de agendamento,
mas vazios na atualização seguinte (`getGuildLog`). O INSERT da batalha
pendente guardava `[]`, apesar de a resposta inicial usar os perfis da gangue.

[Correcção]
O agendamento guarda os perfis exatos da gangue na batalha pendente.
Os previews de ataques antigos pendentes sem perfis recuperam os dados de
`guild_dungeon` apenas para a mesma guilda e identificador de gangue.
Login/estado normal, seleção e adesão usam essa recuperação. Batalhas terminadas
não usam o fallback; gangues frescas continuam desconhecidas.

[Validação]
`php -l` passou nos três ficheiros PHP alterados. Teste temporário offline,
com Db/Character/Live simulados e o handler real, verificou INSERT e resposta
com perfis feridos e vazios, preservação do preview, recuperação de ataques
antigos e exclusão de resultados terminados. Integração MySQL e confirmação
visual no browser não executadas nesta correção.

[Ficheiros]
- server-laravel/actions/guildDungeon.php
- server-laravel/app/HeroZero/GuildBattles.php
- server-laravel/app/HeroZero/Live.php
- docs/Guilda/guild_atacar_gang_npcs.md

----------------------------------------------------------------------------
## BUG-000137 (2026-10-04, gpt-6.1-sol) — Martelo reiniciava o custo de comprar atributos

[Sintoma]
Em `martelo_bug.har`, os quatro contadores de pontos comprados eram 450 antes
da redistribuição. Depois de usar o martelo, passaram a zero e cada compra
seguinte de um ponto custou apenas 5 moedas.

[Causa]
`useInventoryItem` (special_reskill) e `reskillCharacterStats` colocavam os
quatro `stat_bought_*` a zero. Esses campos determinam o custo por atributo
em `Character::improveStat`, independentemente dos valores `stat_base_*`.

[Correcção]
As duas ações passam a atualizar apenas a distribuição dos atributos base
e os pontos livres, preservando o histórico de compras de cada atributo.
A correção não recupera contadores já perdidos em redistribuições anteriores.

[Validação]
`php -l` passou nos dois handlers. Teste temporário offline executou os handlers
reais com Db/Character/Live simulados: históricos diferentes nos quatro atributos
preservados, redistribuição com força a 10, aplicação dos pontos livres,
consumo do martelo e alternativa de 25 donuts. O cálculo de custo existente
continua a usar os mesmos contadores preservados. Integração MySQL e confirmação
visual no browser não executadas nesta correção.

[Ficheiros]
- server-laravel/actions/inventoryOps.php
- server-laravel/actions/charMisc.php
- docs/shop_items/lojas.md

----------------------------------------------------------------------------
## BUG-000138 (2026-10-04, gpt-6.1-sol) — Mísseis gastos em combates PvE e pelo defensor PvP

[Sintoma]
Missões de combate, treinos e missões especiais reduziam as cargas dos mísseis.
Duelos e liga também debitavam munição ao jogador que recebia o ataque.

[Causa]
Os handlers persistiam o uso calculado pelo motor nos combates PvE e para
ambos os lados dos combates PvP, sem distinguir quem iniciou o ataque.

[Correcção]
Débito restrito ao atacante que inicia duelo ou liga. Removido o débito em
missões, treinos e dungeon de história, e para o defensor de duelo/liga.
Batalhas de guilda/gangues já não debitavam munição pessoal. Simulação de dano
e animações preservadas. Cargas perdidas anteriormente não são repostas.

[Validação]
PHP lint nos quatro ficheiros de jogo e no teste. `test_missiles.php`:
testes offline e integração MySQL com rollback verificam consumo do atacante,
munição intacta do defensor em duelo/liga e cargas intactas em missão de
combate, treino e missão especial. Retomas não repetem consumo. Revisão de
chamadas confirmou débito de combate apenas nos dois handlers PvP.
Confirmação visual no cliente pendente.

[Ficheiros]
- server-laravel/actions/startDuel.php
- server-laravel/actions/leagueMissed.php
- server-laravel/app/HeroZero/Character.php
- server-laravel/app/HeroZero/StoryDungeon.php
- scripts/testing/test_missiles.php
- docs/inventario/misseis.md

----------------------------------------------------------------------------
## BUG-000140 (2026-10-04, gpt-6.1-sol) — Prémios semanais creditados mas ausentes do diálogo

[Sintoma]
A janela mostrava classificações dos três torneios, mas os prémios monetários,
atributos e XP não apareciam na lista; eram creditados ao carregar em OK.

[Causa]
O servidor enviava apenas `donuts`, `stat_points` e `xp_percentage`. Os getters
reais do cliente leem `reward.type/amount/type_2/amount_2`. O diálogo semanal
não incluía XP na construção da lista. A resposta de resgate usava ainda um
objeto Character carregado antes do pagamento, devolvendo saldos desatualizados.

[Correcção]
Adaptação no get, incluindo recompensas pendentes antigas. XP absoluto fixado
na primeira leitura e partilhado com o resgate. Lista visual inclui XP.
Resgate atómico com locks/savepoints e estado recarregado após pagamento.
Cache do bundle atualizada. Mantida a janela conjunta dos três torneios.

[Validação]
PHP lint e sintaxe Node passaram. Integração MySQL com rollback em
`test_tournament_rewards.php`: contratos, XP estável, pagamentos exatos,
marcação e resposta atualizadas, rejeição de resgate repetido.
Teste temporário offline executou getters e construtor reais do diálogo com
componentes simulados, mostrando XP, donuts e soma dos atributos.
Teste de regressão do calendário diário passou. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Tournament.php
- server-laravel/actions/getTournamentRewards.php
- server-laravel/actions/claimTournamentRewards.php
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- scripts/testing/test_tournament_rewards.php
- docs/torneios_semanais/recompensas.md
- docs/torneios_semanais/geral.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000141 (2026-10-04, gpt-6.1-sol) — Cadeias semanais sobrepostas geravam prémios repetidos

[Sintoma]
`torneios.har` mostra resgates dos registos 1179 e 1264, de torneios distintos
mas sobrepostos na mesma semana. A BD tinha duas cadeias ativas, às 00:00 e
02:32:36. `imagem.png` mostrava o horário antigo na aba XP.

[Causa]
O ciclo partilhado dos três tipos não serializava encerramento/criação.
Um pedido podia criar outro ciclo enquanto o anterior tinha status=2.
Não existiam índices únicos de período ou locks globais. `week` usava uptime.

[Correcção]
Calendários independentes solicitados: XP domingo, PvP terça, habilidades
quarta, todos às 21:00 do relógio/fuso do jogo. Lifecycle atómico com lock
único e índices únicos; fim gera prémios de apenas um tipo. Datas por tipo no
ranking e bundle, incluindo cache ao alternar abas. Migração com backup cancela
cadeias antigas ativas sem novos prémios, preserva uma base de progresso e os
prémios anteriormente emitidos. Não retira saldos nem apaga histórico.

[Validação]
Migração MySQL executada duas vezes (idempotência), com backups.
Testes de calendário offline/DST e integração MySQL com rollback: três ciclos,
lock bloqueia segundo PDO, limites independentes, um prémio por tipo, resgates,
login real, estado normal/partial, repetição e saltos temporais.
Node testa setters/getters reais e cache por aba. Regressão do resgate, login diário/bónus e mísseis
validada; PHP lint e sintaxe JS passaram. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Tournament.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/app/HeroZero/Replay.php
- server-laravel/actions/autoLoginUser.php
- server-laravel/tools/tournament.php
- server-laravel/docker/initdb/01_schema.sql
- server-laravel/docker/initdb/06_tournament_calendar.sql
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- scripts/testing/migrate_tournament_calendar.php
- scripts/testing/test_tournament_calendar.php
- scripts/testing/test_tournament_calendar_client.js
- docs/torneios_semanais/geral.md
- docs/torneios_semanais/recompensas.md
- docs/scripts.md
- docs/RESPONSE_SCHEMA.md

----------------------------------------------------------------------------
## BUG-000142 (2026-10-04, gpt-6.1-sol) — Pontos do passe incompletos nos torneios XP/habilidades

[Sintoma]
O utilizador reportou ausência de pontos do passe no ranking após separar
calendários. O resgate dos torneios também não entregava esses pontos.

[Causa]
As linhas dos dois layouts decidiam criar o componente no construtor usando
a data partilhada de XP, antes de conhecer o tipo/prazo da linha. Essa condição
não foi adaptada na separação dos calendários. O handler de claim nunca chamou
`Season::givePoints`; esta lacuna de crédito já existia antes da separação.

[Correcção]
Componente criado com temporada ativa; elegibilidade revista no refresh usando
a data de XP ou habilidades, conforme a linha. Pontos oficiais de posições
lidos de `season_locations` (gods_1: top3 30, top10 20, top100 10 por tipo).
Preview explícito `season_reward`, ligado à janela de temporada, crédito na
transação do resgate e progresso/recompensas atualizados na resposta.
Mantida a condição de temporada ativa que termina após o torneio.
Cache do bundle atualizada. Não foram repostos pontos de resgates anteriores.

[Validação]
PHP lint e sintaxe JS passaram. Integração MySQL com rollback: posições dos
dois torneios, preview 30+30, crédito exato e progresso atualizado, duplicados,
PvP/inatividade/fim de temporada sem prémio. Calendários separados continuam
a passar. Node executa os construtores/refresh reais dos dois layouts e o
componente real de pontos, com datas diferentes por tipo.
Confirmação visual do utilizador pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Tournament.php
- server-laravel/actions/getTournamentRewards.php
- server-laravel/actions/claimTournamentRewards.php
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- scripts/testing/test_tournament_rewards.php
- scripts/testing/test_tournament_calendar.php
- scripts/testing/test_tournament_calendar_client.js
- docs/torneios_semanais/recompensas.md
- docs/seasons/atual.md
- docs/seasons/implementar.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000143 (2026-10-04, gpt-6.1-sol) — Renovação do passe e temporadas desfasadas entre contas

[Sintoma]
Inicialmente, avançar o relógio para além do fim prolongava o passe sem renovar
pontos e resgates. Após corrigir a renovação, os HARs `sonnet46.har`,
`laliene.har` e `skel.har` mostraram outra manifestação: no mesmo instante do
relógio interno, sonnet46 estava em `fairytale_1` e as outras em `gods_3`,
com datas de fim diferentes entre si.

[Causa]
A implementação antiga alterava apenas as datas para agora/agora+30 dias e
usava `<` em vez de `<=`. A primeira correção renovava cada personagem desde
o seu fim anterior; continuavam a existir origens individuais e datas
historicamente prolongadas pelo momento do login. Um relógio comum não basta
quando cada conta calcula o ciclo a partir de uma origem diferente.
O estado normal também não enviava a coleção de recompensas atualizada.

[Correcção]
`Season::currentCycle()` calcula o tema e os limites comuns usando
`GameTime::now()` e uma origem fixa em `season_calendar.ts_anchor` (linha 1).
A migração idempotente usa o menor `ts_created` positivo dos progressos;
sem progressos, usa o instante atual do relógio interno. A origem fica guardada.
O schema inicial inclui a tabela; não se executa DDL em transações de jogo.

No próximo pedido que consulta o passe, cada conta sincroniza com esse ciclo,
com locks e transação/savepoint. Na adaptação das janelas antigas, o mesmo tema
ainda ativo conserva pontos, premium e resgates; tema diferente ou janela
expirada/futura reinicia o progresso. Contas novas entram no ciclo atual.
Mantém IDs do progresso, `ts_created`, objetos e saldos já recebidos.

Cada 30 × 86400 segundos avança um dos 80 catálogos das constantes, na rotação
local iniciada em `gods_1`. Avançar o relógio 30 dias avança um tema para todas
as contas; o tempo restante é aproximadamente o mesmo. Saltos maiores não dão
prémios retroativos. IDs de recompensa distintos por tema rejeitam pedidos do
tema anterior; o cliente limpa o cache quando muda o identificador.
Login e estado normal incluem progresso e recompensas atualizados.

[Validação]
Migração aplicada nesta instalação. PHP lint e integração MySQL com rollback:
expiração exata, limpeza, idempotência, saltos, 80 catálogos/IDs exclusivos,
rotação completa, login e estado normal. Três personagens reproduzindo as
janelas dos HARs convergem para tema e datas iguais; inverter a ordem dos
logins não altera o resultado. Criação tardia entra na mesma janela.
Regressões de recompensas do passe e calendário/resgates de torneios passaram.
O teste simula o relógio apenas em memória; confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Season.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/actions/unlockSeasonReward.php
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- server-laravel/docker/initdb/06_season.sql
- scripts/testing/migrate_season_calendar.php
- scripts/testing/test_season_cycles.php
- scripts/testing/test_season_rewards.php
- docs/seasons/atual.md
- docs/seasons/implementar.md
- docs/scripts.md
- docs/RESPONSE_SCHEMA.md

----------------------------------------------------------------------------
## BUG-000144 (2026-10-04, gpt-6.1-sol) — Chamadas perdiam pontos do passe nos temas beetle/bear

[Sintoma]
Depois de avançar o relógio e rodar a temporada, os pontos desapareceram das
cinco chamadas heroicas. `1.png` mostra a ausência do ícone; o HAR
`regressao_chamadas.har` confirma passe `beetle` ativo e três chamadas diárias/
duas semanais. Resgatar nestes temas também não creditava pontos.

[Causa]
As constantes originais `season_locations[14/13]` têm zero em `beetle` e
`bear`, ao contrário dos 1/5 dos outros 78 temas. A introdução da rotação
expôs essa diferença sem preservar os pontos das chamadas em todos os temas.
O cliente esconde o ícone quando `ea.fromSeasonPoints` não produz recompensa;
o backend consultava os mesmos zeros. Não era um desfasamento do relógio.

[Correcção]
Regra local explícita de 1 ponto por diária e 5 por semanal em todos os temas.
`Herobook::constants()` sobrepõe apenas as localizações 14/13 das temporadas
conhecidas. `Live::withLocalConstants` envia a mesma fatia usada no resgate,
alinhando apresentação e crédito. Versão do cache incrementada para v3.
Assets oficiais e outras localizações inalterados; sem DDL ou compensação
retroativa. Recarregar o jogo para receber as novas constantes.

[Validação]
PHP lint. Testes offline: contrato de `initGame`/resgate nos 80 temas.
MySQL com rollback: rotação para `beetle`/`bear`, diárias +1, semanais +5,
resgate duplicado recusado, descarte sem pontos; testes normais de chamadas.
Node executa funções reais `eq.refresh`, `ea.fromSeasonPoints` e getters do
bundle com gráficos simulados: cinco cartões mostram quantidade/ícone em todos
os temas; passe inativo esconde o ícone. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Herobook.php
- scripts/testing/test_herobook.php
- scripts/testing/test_herobook_season_client.js
- docs/missoes/chamadas_emergencia.md
- docs/missoes/geral.md
- docs/seasons/atual.md
- docs/seasons/implementar.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000145 (2026-10-04) — Casino com prémios base incompletos e cupão de energético fixo

[Sintoma]
O casino não oferecia energéticos de missões, habilidades e trabalho de 50%
por 3 e 14 dias, nem energia de 10/60 e treino de 4/40. A implementação
oferecia só energético de missões 25% e quantidades provisórias escaladas.

[Causa]
`Casino::numericReward` usava fórmulas locais em vez das variantes base
confirmadas pelo utilizador. O catálogo mostrava só uma variante por categoria
e o resgate criava sempre `quest_booster` de 25%, independentemente do energético.

[Correcção]
Catálogo partilhado `baseRewardVariants`: seis energéticos de 50%
(`booster_quest3/stats3/work3` × durações 3/14), energia 10/60 e treino 4/40.
Sorteio e prémios possíveis usam as mesmas variantes, sem multiplicação pela
qualidade ou por `great_rewards`. Resgate resolve tipo/percentagem pelo catálogo
de energéticos e cria o cupão correspondente, com a duração guardada no giro.
Giros antigos já pagos preservam os prémios guardados. A disponibilidade das
categorias por tema não foi alterada; não é uma confirmação das probabilidades
oficiais, que continuam desconhecidas.
Seleção atual da variante pela qualidade descrita em BUG-000146: o catálogo
mantém todas as variantes, mas três símbolos iguais garantem o valor máximo.

[Validação]
PHP lint. Offline verifica as dez variantes e valores em todos os temas/qualidades.
MySQL com rollback: catálogo de 34 temas × três máquinas, todas as variantes
nas categorias disponíveis, dez cupões com tipos/quantidades/durações corretos,
resgate real de 10+60 energias, 4+40 unidades e energéticos de 50% nos três
tipos por 3+14 dias. Testes preservam relógio e eventos reais.

[Ficheiros]
- server-laravel/app/HeroZero/Casino.php
- scripts/testing/test_casino.php
- docs/casino/README.md
- docs/Features.md
- docs/RESPONSE_SCHEMA.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000146 (2026-10-04) — Três símbolos iguais podiam entregar o prémio base inferior

[Sintoma]
Três símbolos iguais de energético, treino ou energia podiam entregar 3 dias,
4 unidades ou 10 energias, em vez dos prémios máximos de 14 dias/40/60.

[Causa]
A qualidade determinava os símbolos da slot, mas numericReward sorteava a
variante base independentemente dessa qualidade, permitindo um prémio inferior
mesmo quando o resultado visual era qualidade 3.

[Correcção]
Qualidade 3 garante energético de 50% por 14 dias, treino 40 ou energia 60.
Qualidades 1/2 dão energético por 3 dias, treino 4 ou energia 10. O tipo do
energético continua a variar entre missões/habilidades/trabalho. Catálogo mantém
ambas as variantes; great_rewards não multiplica estes valores. Cada prémio
num multigiro usa a própria qualidade; os símbolos representam o melhor do lote.
Resultados pendentes anteriormente pagos preservam os valores guardados.

[Validação]
PHP lint; test_casino.php offline e MySQL com rollback: valores inferiores e
máximos em todos os temas/qualidades, criação real de prémios de qualidade 3,
correspondência dos símbolos aos prémios nos giros reais e resgate de cupões.
Regressão Node do calendário, máquinas, símbolos e lotes ilimitados passou.
Relógio, eventos reais e saldos persistentes preservados.

[Ficheiros]
- server-laravel/app/HeroZero/Casino.php
- scripts/testing/test_casino.php
- docs/casino/README.md
- docs/Features.md
- docs/RESPONSE_SCHEMA.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000147 (2026-10-04, gpt6.1sol) — Resgate de cupões perde energia e unidades acima do máximo diário

[Sintoma]
Em `cupoes.har`, 117/120 energias mais um cupão de 50 deixam apenas 120;
22 unidades de treino mais um cupão de 4 continuam em 22. Os cupões são consumidos.

[Causa]
`UserVouchers::redeem()` soma os prémios corretamente, mas `syncDailyReset()`
e `syncTrainingForLevel()` aplicavam `min(saldo, máximo × factor)` em todas as
sincronizações, incluindo a geração da resposta ao resgate.

[Correcção]
Removidos os dois cortes incondicionais. O excedente mantém-se nas consultas
do mesmo dia; reposição diária e acréscimos de eventos mantêm as regras existentes.
Na primeira sincronização após a meia-noite do calendário do jogo, a energia
restante é acrescentada ao armazenamento até ao limite total de 100; o que
ultrapassa esse limite perde-se e a energia diária é reposta. As unidades de
treino restantes não transitam: o contador é reposto ao máximo diário aplicável.
Cupões ainda não resgatados permanecem guardados. A correção preserva o prémio
no dia do resgate, sem alterar estas regras de mudança de dia.
Não há compensação automática dos cupões anteriormente desperdiçados.

[Validação]
PHP lint e testes de cupões com PDO simulado e MySQL. Integração reproduz o
handler real do resgate e confirma 167 energias e 26 unidades na resposta e no
estado seguinte. Testes MySQL de bónus diários, eventos, estado normal e login
também passaram. Todas as alterações de teste revertidas; confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- scripts/testing/test_user_vouchers.php
- docs/cupoes/mensagens.md

----------------------------------------------------------------------------
## BUG-000148 (2026-10-04, gpt6.1sol) — Barra de energia gasta só atualizava na sincronização seguinte

[Sintoma]
Os patamares diários funcionavam, mas a barra no jogo não acompanhava
imediatamente a energia debitada ao iniciar uma missão. O progresso aparecia
após uma nova consulta de estado, mudança de painel ou recarregamento.

[Causa]
Os handlers de missões construíam respostas parciais diretamente, sem passar
por Live::accountState/overlayAccount. startQuest debitava e registava a energia,
mas não devolvia daily_bonus_lookup.quest_energy_spent nem os prémios atualizados.
O JS atualiza a barra a partir desse contador; a resposta deixava o modelo antigo.

[Correcção]
startQuest, instantFinishQuest, checkForQuestComplete e claimQuestRewards
anexam DailyEnergy::attach à resposta, incluindo contador, estados dos patamares,
configuração e previews. O cliente recebe o progresso no próprio pedido de missão.
Após BUG-000149, a contagem avança apenas no resgate de missões concluídas;
as respostas de início/conclusão continuam a enviar o estado, sem o incrementar.

[Validação]
PHP lint nos quatro handlers e teste de integração. MySQL com rollback confirma
na validação inicial, startQuest devolvia 90+10=100 sem sync. Após BUG-000149,
o teste confirma 90 no início/cancelamento e 100 na resposta de claimQuestRewards,
com o prémio disponível; retry não duplica energia. As respostas de conclusão/resgate incluem
patamares vivos. Teste Node executa as funções reais do bundle: merge do contador,
barra, legenda e máscara acompanham cada resposta sem novo pedido HTTP.
Confirmação visual no browser pendente; dados de teste revertidos.

[Ficheiros]
- server-laravel/actions/startQuest.php
- server-laravel/actions/instantFinishQuest.php
- server-laravel/actions/checkForQuestComplete.php
- server-laravel/actions/claimQuestRewards.php
- scripts/testing/test_daily_energy.php
- scripts/testing/test_daily_energy_client.js
- docs/missoes/patamares_energia.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000149 (2026-10-04, gpt6.1sol) — Missões canceladas avançavam os patamares diários de energia

[Sintoma]
Iniciar e cancelar uma missão mantinha a sua energia contabilizada nos patamares
100/200/300. Era possível atingir e resgatar o prémio antes de concluir a missão.

[Causa]
DailyEnergy::record era chamado no débito de startQuest, quando a missão ainda
podia ser cancelada. A regra anterior preservava essa contagem no cancelamento.

[Correcção]
Contagem transferida para claimQuestRewardsLocked, após validar a missão concluída,
na mesma transação das recompensas. Início e cancelamento não avançam os patamares;
o contador e o prémio disponível são enviados na resposta ao resgate da missão.
Não se devolve energia ao saldo ao cancelar. Missões que atravessam a meia-noite
contam no dia do resgate. Contagens/prémios anteriores não são recalculados.
Esta regra substitui a contagem ao iniciar descrita inicialmente em BUG-000148.

[Validação]
PHP lint e integração MySQL com rollback: 90 energias permanecem 90 ao iniciar,
repetir início e cancelar uma missão de custo 10; o patamar ainda fica bloqueado.
Cancelamento repetido e missão sem energia não alteram o contador. Só o resgate
da missão concluída passa a 100 e envia o prémio disponível na mesma resposta;
retry não duplica a contagem. Catálogo completo, cupões, temporada, mudança de dia,
login/estado e teste Node da barra também passam. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- scripts/testing/test_daily_energy.php
- docs/missoes/patamares_energia.md
- docs/Features.md

----------------------------------------------------------------------------
## BUG-000150 (2026-10-04, gpt6.1sol) — Pontos livres dos patamares não apareciam no diálogo

[Sintoma]
Ao resgatar o patamar de dez combates de liga do skel, o diálogo mostrava apenas
o ponto de temporada, apesar de os dez pontos livres serem creditados ao herói.
O mesmo contrato afetava os patamares de energia e duelos com esse prémio.

[Causa]
DailyMilestones enviava `stat_points` no JSON de recompensas. O cliente
`Reward.get_statPoints()` lê exclusivamente `statPoints`; o parser de prémios
ignorava o campo e o diálogo acrescentava apenas a temporada.

[Correcção]
Sorteios novos usam `statPoints`. A leitura normaliza linhas antigas com
`stat_points`, incluindo previews e resgates. O crédito lê o campo normalizado
na mesma transação, mantendo proteção contra duplicados. Não se alteram
sorteios/saldos antigos nem se concede compensação: os pontos já foram entregues.

[Validação]
PHP lint e MySQL com rollback no herói skel: catálogos de energia/duelos/liga,
previews antigos normalizados, todos os prémios, crédito único e retries passaram.
Teste Node executa parser, getter e diálogo reais do bundle: 5/10/20 pontos livres
aparecem junto do ponto de temporada nos tipos 2/3/5. Componentes gráficos
simulados; confirmação visual no browser pendente.

[Ficheiros]
- server-laravel/app/HeroZero/DailyMilestones.php
- scripts/testing/test_daily_energy.php
- scripts/testing/test_daily_combats.php
- scripts/testing/test_daily_milestone_rewards_client.js
- docs/missoes/patamares_energia.md
- docs/missoes/patamares_combates.md
- docs/RESPONSE_SCHEMA.md
- docs/scripts.md

----------------------------------------------------------------------------
## BUG-000151 (2026-10-04) — Habilidades do casino desapareciam do diálogo dos multigiros

[Sintoma]
Em `casino.har`, lotes de dez giros pareciam mostrar apenas oito/nove prémios.
Os prémios de habilidades não apareciam, incluindo quando a máquina mostrava
esse símbolo. Todos os 16 lotes tinham dez recompensas no payload; os resgates
bem-sucedidos creditavam os pontos. Exemplo: o lote com símbolos 5/5/5 elevou
os pontos disponíveis de 210 para 234, mas não mostrava os prémios de habilidades.

[Causa]
Casino emitia `stat_points`, enquanto `Reward.get_statPoints()` do cliente lê
`statPoints`. `DialogCasinoReward` agrega `get_genericRewards()` de cada giro,
que descartava visualmente os prémios com o campo incorreto.

[Correcção]
Novos prémios de habilidades usam `statPoints`. Respostas de `spinCasinoMachine`
e `getCasinoReward` normalizam o campo legado sem modificar o resultado pago
na BD nem voltar a creditar pontos. Resgate e histórico aceitam ambos os nomes.
Não se alteraram probabilidades, animações ou durações: a captura não continha
três símbolos de energético (3/3/3); os triplos 4/4/4 eram acompanhantes.

[Validação]
PHP lint; casino offline e integração MySQL no skel com rollback passaram.
Teste Node combina normalização PHP, getter/parser e agregação reais do diálogo:
lote com sete energias, duas habilidades e um energético mostra oito entradas
com o campo antigo e dez depois da correção; habilidades 2/20 e energético de
14 dias são preservados e apresentados. Gráficos simulados; confirmação visual
no browser pendente. Não foi necessária compensação dos saldos.

[Ficheiros]
- server-laravel/app/HeroZero/Casino.php
- server-laravel/actions/casino.php
- scripts/testing/test_casino.php
- scripts/testing/test_casino_rewards_client.js
- docs/casino/README.md

----------------------------------------------------------------------------
## BUG-000152 (2026-10-04) — Identificadores de pets truncados mostravam caminhos de tradução

[Sintoma]
`pets.png` mostrava `sidekick/sidekick_season_.../name` no menu dos pets.
`pets.har` tinha 14 identificadores inválidos nos 75 pets possuídos, todos
cortados a 32 caracteres, embora o catálogo global tivesse os nomes completos.

[Causa]
`sidekicks.identifier` usava `varchar(32)`. A inserção cortava identificadores
longos; o cliente, com nome personalizado vazio, procurava uma tradução
inexistente para o prefixo truncado. O schema inicial tinha o mesmo limite
em `sewed_identifier`, afetando também identificadores de aparência longos.

[Correcção]
Schema inicial e migração idempotente usam 128 caracteres nos dois campos.
A migração cria o campo de aparência quando faltar, guarda backup antes das
alterações e repara apenas identificadores comprovados. Onze casos tinham
prefixo oficial único; os três armoured_beasts foram recuperados como variante
2 pelo histórico do casino. As reparações foram aplicadas à BD existente.
Não se mudaram nomes personalizados, habilidades, atributos ou saldos.

[Validação]
PHP lint, diagnóstico, aplicação e repetição idempotente passaram. Comparação
com o backup confirmou exatamente 14 identificadores alterados e preservação
dos restantes campos originais. Todos os nomes reparados têm tradução no
pt_BR servido. Integração MySQL com rollback cria um pet e aparência com
identificadores longos e confirma ambos em Live::accountState. Confirmação
visual no browser pendente.

[Ficheiros]
- server-laravel/docker/initdb/01_schema.sql
- scripts/testing/migrate_sidekick_identifiers.php
- scripts/testing/test_sidekick_identifiers.php
- docs/sidekicks/sidekicks_implementados.md

----------------------------------------------------------------------------
## BUG-000153 (2026-10-04) — Pets recebidos podiam nascer sem as habilidades especiais

[Sintoma]
Na conta laliene, 66 dos 67 pets possuídos com habilidades fixas nas constantes
não tinham a combinação esperada. Pets obtidos pelo casino/temporada nasciam
com habilidades genéricas, apesar de o catálogo indicar habilidades especiais.
A divergência também existia antes da reparação dos nomes, que preservou skills.

[Causa]
`Sidekick::create()` usava apenas `randomSkills()` quando o chamador não
fornecia habilidades. Casino e temporadas chamavam este caminho; a existência
de `skillsForIdentifier()` noutro fluxo não protegia estas origens de prémios.

[Correcção]
Criação central aplica as habilidades fixas do identificador aos respetivos
estágios, preservando a geração normal dos restantes. Abrange todas as origens
que usam `Sidekick::create()`, inclusive chamadas com habilidades explícitas.
Não modifica pets existentes nem habilidades transferidas por fusão. Reskill
não foi alterado neste pedido, dedicado à geração/entrega de novos pets.

[Validação]
PHP lint e teste MySQL com rollback: criação dos 188 identificadores com
habilidades fixas, chamada explícita, pet genérico e estado com identificadores
longos passaram. Teste de integração do casino com rollback verifica o resgate
real do pet e as suas habilidades fixas, além da regressão geral do casino.
Confirmação visual no browser pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Sidekick.php
- scripts/testing/test_sidekick_identifiers.php
- scripts/testing/test_casino.php
- docs/sidekicks/sidekicks_implementados.md
- docs/casino/README.md

----------------------------------------------------------------------------
## BUG-000154 (2026-10-04) — Energético do esconderijo ativo sem efeito na produção

[Sintoma]
`hideout.har` mostrava `booster_hideout2` ativado por cupão durante três dias,
mas os valores de produção de cola/pedra não recebiam o bónus. O utilizador
reportou também uma cor avermelhada, cuja causa visual independente não foi
confirmada na análise da captura.

[Causa]
`Hideout::autoRoomProducedAmount()` ignorava o energético. Os getters de taxa
e quantidade do cliente também não tinham esse fator. `buyHideoutBooster`
continuava como eco, sem ativação persistente. Não existia timestamp de início
para distinguir produção anterior à ativação. A recolha subtraía a quantidade
já guardada antes de limpar a sala, incompatível com guardar produção na troca.

[Correcção]
Cálculo aplica +25/+50% só a cola/pedra, dentro da janela real de início/fim,
sem aumentar capacidade nem dar bónus retroativo. Cupões e compra partilham
ativação que guarda a produção anterior. Compra valida e debita atomicamente.
Recolha individual/coletiva entrega toda a quantidade e usa lock/transação.
Estado envia extensão local production_booster nos metadados da sala; getters
do bundle calculam taxa, quantidade, tempo restante e atualização na expiração.
Schema inicial e migração idempotente incluem timestamp de início. A migração
foi aplicada à instalação existente; não houve compensação retroativa.

[Validação]
PHP lint, sintaxe do bundle e integração MySQL com rollback no skel passaram:
+25/+50%, expiração, ausência de retroatividade, ativação por cupão, estado,
recolha individual/coletiva, retry, compra com saldo e rejeição sem saldo.
Node executa getters reais com gráficos simulados; regressão MySQL do casino
passou. Confirmação visual no browser, incluindo a cor reportada, pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Hideout.php
- server-laravel/app/HeroZero/UserVouchers.php
- server-laravel/actions/boosterExtra.php
- server-laravel/actions/hideoutExtra.php
- server-laravel/public/js/HeroZero.min.js
- server-laravel/docker/initdb/01_schema.sql
- scripts/testing/migrate_user_vouchers.php
- scripts/testing/test_hideout_booster.php
- scripts/testing/test_hideout_booster_client.js
- docs/hideout/energeticos.md
- docs/hideout/extract/salas_especiais.md

----------------------------------------------------------------------------
## BUG-000155 (2026-10-04) — Menu de construção do esconderijo falhava com hideout_id ausente

[Sintoma]
Ao abrir a construção num slot do esconderijo, o cliente lançava uma exceção
DataObject por campo `hideout_id` ausente. A stack passava por tooltip,
`HideoutRoom.get_hash()` e `get_hideoutProductionWindow()`.

[Causa]
A extensão do energético introduzida no BUG-000154 consultava
`get_isOwnHideoutRoom()` em qualquer objeto de sala. As pré-visualizações do
catálogo de construção são objetos parciais e não têm `hideout_id`; o getter
estrito do cliente lançava exceção antes de poder ignorar o energético.

[Correcção]
`get_hideoutProductionWindow()` verifica a presença de `hideout_id`,
`identifier` e `additional_value_2` antes de consultar propriedade ou metadados.
Previews sem esses campos usam os cálculos e hash originais; salas reais
continuam a aplicar o energético. Não houve alteração de recursos ou salas.

[Validação]
Teste Node reproduziu a exceção antes da correção e passou depois. Executa
getters e hash original do bundle com previews parciais de gerador, cola e
pedra: hash, taxa, quantidade e tempo não consultam campos ausentes.
Regressão dos bónus +25/+50%, expiração e hash passou; sintaxe do bundle válida.
Gráficos simulados; confirmação visual do menu no browser pendente.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- scripts/testing/test_hideout_booster_client.js
- docs/hideout/energeticos.md

----------------------------------------------------------------------------
## BUG-000156 (2026-10-05) — Atributos ocultos no combate contra vilões

[Sintoma]
Na captura `3.png`, os atributos do herói não aparecem no combate contra o vilão.

[Causa]
O ramo worldboss de `PanelBattle.show` ocultava explicitamente os painéis
`statsA` e `statsB`, apesar de os preencher com os perfis do combate.

[Correcção]
Ativada a visibilidade do painel do herói (`statsA`) no ramo worldboss.
O painel do vilão (`statsB`) fica oculto, conforme o pedido corrigido pelo utilizador.

[Validação]
Sintaxe JavaScript validada com Node. Verificada alteração única de
visibilidade e preenchimento existente por `refreshFromBattleProfile`.
Confirmação visual no navegador pendente.

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- docs/vilao - worldboss/worldboss.md

----------------------------------------------------------------------------
## BUG-000157 (2026-10-05) — Golpe final substituído pelo penúltimo atacante

[Sintoma]
O evento 22 mostrava sonnet46 no golpe vencedor, apesar de o ataque 41 de
skel ter retirado os 1 656 525 HP restantes.

[Causa]
Quando o líder também matava o vilão, o backend substituía o autor em
`winning_attacker_name` por `2_<outro atacante>`. Essa transferência é
prevista pelos textos oficiais, mas contraria a regra local pedida pelo utilizador.

[Correcção]
Guardar sempre quem leva a vida a zero e atribuir-lhe o prémio do golpe final.
Removida transferência para o penúltimo atacante. Corrigido apenas o nome
do evento 22 para skel; prémios históricos preservados.

[Validação]
PHP lint e integração MySQL com rollback passaram, incluindo dois heróis,
líder que dá golpe final e ausência de pet para o penúltimo atacante.

[Ficheiros]
- server-laravel/app/HeroZero/Worldboss.php
- scripts/testing/test_worldboss.php
- docs/vilao - worldboss/worldboss.md

----------------------------------------------------------------------------
## BUG-000158 (2026-10-05) — Reinício da visualização do mesmo combate de vilão

[Sintoma]
`b.webm` mostra efeitos sobrepostos e progressão mais rápida do que a missão
em `a.webm`. Chamadas repetidas de show reiniciavam o mesmo combate.

[Causa]
O percurso worldboss pode voltar a chamar `PanelBattle.show` com o painel
aberto. Show reiniciava os rounds sem cancelar as timelines anteriores.
O reinício foi reproduzido no teste; após aplicar a proteção, o utilizador
confirmou que a animação ficou correta.

[Correcção]
Proteção por ataque/batalha para impedir reinício em chamadas repetidas.
Batalhas seguintes do triplo e novos ataques continuam a iniciar; reset
limpa a proteção. Sem batalha pendente, não reinicia a anterior.

[Validação]
Sintaxe do bundle e teste Node com métodos extraídos passaram. Reproduz
o reinício anterior e verifica chamadas repetidas, triplo, novo ataque,
fim, reset e percurso de missões/duelos, com gráficos simulados. O utilizador
confirmou a melhoria visual no navegador ("Ok agora tá").

[Ficheiros]
- server-laravel/public/js/HeroZero.min.js
- scripts/testing/test_worldboss_animation_client.js
- docs/vilao - worldboss/worldboss.md

----------------------------------------------------------------------------
## BUG-000159 (2026-10-05) — Fontes incorretas no catálogo de pets

[Sintoma]
Em `hum.har`, `getSidekicks.all_sidekicks` anuncia Kylie (`sidekick_peacock`)
com `sources = ["shop_general"]`, embora as constantes a associem à conquista
de colecionar 140 pets diferentes. Outros pets de conquistas eram apresentados
como brechó ou indisponíveis; faltavam fontes de sorteios, tesouros e torres.

[Causa]
O catálogo misturava associações manuais com derivação incompleta, limitada a
passes premium e alguns campos de eventos. Não lia conquistas e as restantes
famílias de recompensas. O cliente traduz cada identificador de `sources`;
fontes incorretas no backend eram mostradas diretamente na interface.

[Correcção]
`Sidekick::sourcesFromConstants` deriva conquistas e limiares, passes premium
e gratuitos, eventos, sorteios, tesouros, objetos escondidos, torres e covis
lendários, incluindo JSON embutido em `reward_data`. Referências explícitas
substituem as fontes manuais; estas ficam como fallback quando não há origem
no asset. Removidas as entradas manuais incorretas dos oito pets de conquistas.
Acrescentados textos pt_BR para as novas fontes, com o limiar 140 para Kylie.
Atualizada a chave de cache para invalidar o catálogo antigo.

[Validação]
Sintaxe PHP validada. Teste offline verifica conquistas, fontes de todas as
famílias e tradução de cada chave. O handler real `getSidekicks` foi exercitado
em MySQL com rollback e envia as fontes esperadas para os 212 pets. Regressões
de habilidades/identificadores e de vilão passaram com rollback. Não foi feita
confirmação visual no navegador nesta alteração.

[Ficheiros]
- server-laravel/app/HeroZero/Sidekick.php
- assets/i18n/pt_BR/text.data
- server-laravel/public/assets/i18n/pt_BR/text.data
- scripts/testing/test_sidekick_sources.php
- scripts/testing/sync_sidekick_source_texts.py
- scripts/testing/document_sidekicks.py
- docs/sidekicks/sidekicks_info.md
- docs/sidekicks/sidekicks_implementados.md

----------------------------------------------------------------------------
## BUG-000160 (2026-10-05) — Equipamento de vilão sem seleção pela família de recompensas

[Sintoma]
Os eventos repetiam o mesmo objeto de recompensa, sem usar as peças associadas
aos vilões nas constantes nem distinguir corretamente os conjuntos das categorias.

[Causa]
A seleção local anterior usava identificadores fixos de prémio, em vez de
construir os pools de equipamento pelas fontes de `item_pattern`.

[Correcção]
`Worldboss::equipmentPool` resolve as associações `worldboss_event_*` e as
versões de conjunto por `nonSetToSet`. Normais usam Ironman na faixa superior
e Anfíbio na inferior; vilões temáticos usam as associações disponíveis.
Sem fonte direta, as alternativas locais são explicitadas na documentação.
Sorteia-se uma peça por faixa/evento e guarda-se a escolha na BD; recarregar
não sorteia novamente. A mesma peça pode repetir por acaso entre eventos e
é partilhada pelos participantes da mesma faixa dentro do evento.

[Validação]
Testes offline verificam os 39 templates, famílias e peças válidas. Integração
MySQL com rollback verifica preview, persistência, resgate e temporada sem pet.
Não se usa uma amostra aleatória para afirmar que nunca há repetição.

[Ficheiros]
- server-laravel/app/HeroZero/Worldboss.php
- scripts/testing/test_worldboss.php
- docs/vilao - worldboss/worldboss.md

----------------------------------------------------------------------------
## BUG-000161 (2026-10-05) — Prémios de vilão com fórmula de atributos diferente da loja

[Sintoma]
O equipamento de vilão tinha um orçamento inferior ao da fórmula documentada
da loja, e os pets recebiam bases fixas 60/100/40/23 independentemente do nível
do herói. O preview de pet não mostrava as bases efetivamente entregues.

[Causa]
O equipamento era gerado por `createBlacksmithItem`, com multiplicador épico
1.5 e três atributos. A entrega de pets usava bases fixas e o preview tinha
os quatro atributos a zero. Não havia reutilização da fórmula da loja.

[Correcção]
Extraído `Character::shopItemStats` e reutilizado na loja e nos prémios de
vilão: nível requerido entre nível do herói menos 4 e o seu nível, mínimo 1;
orçamento linear/quadrático, multiplicador épico 2.3, bónus `item_flat` do
esconderijo e distribuição épica de três/quatro atributos.
`randomSidekickBases` partilha o scaling da compra de pet por nível/qualidade
e a variação de ±15%. As bases são guardadas no preview e copiadas no resgate,
sem novo sorteio. Previews antigos a zero usam o scaling atual ao resgatar;
objetos já preparados com atributos não nulos são preservados.

[Validação]
Sintaxe PHP validada. Testes offline verificam orçamento/distribuição em
vários níveis e raridades. MySQL com rollback verifica equipamento real,
limites das bases do pet e igualdade entre preview e bases entregues.
Regressão da compra na loja passou com PDO simulado; isso não constitui
integração MySQL da compra nem confirmação visual do preview no navegador.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- server-laravel/app/HeroZero/Worldboss.php
- scripts/testing/test_worldboss.php
- docs/shop_items/geracao_itens.md
- docs/sidekicks/scaling.md
- docs/vilao - worldboss/worldboss.md

----------------------------------------------------------------------------
## BUG-000162 (2026-10-05) — Ofertas de pet com atributos repetidos e diferentes da compra

[Sintoma]
Pets do brechó da mesma qualidade e nível apresentavam os mesmos atributos
no preview. A compra sorteava novas bases, que podiam diferir do anunciado.

[Causa]
`sidekickEstimatedStats` usava os midpoints fixos do scaling ao gerar o item.
`buyShopItemLocked` ignorava esses atributos e chamava `randomSidekickBases`
novamente, com o nível do herói no momento da compra.

[Correcção]
`randomSidekickItemStats` sorteia ±15% por atributo ao gerar a oferta e guarda
as bases nos campos `items.stat_*`. A compra copia os valores persistidos
para `stat_base_*`, sem novo sorteio. Subir de nível entretanto não altera a
oferta. Valores não nulos de ofertas antigas são respeitados; quatro valores
a zero mantêm uma via de compatibilidade com sorteio na compra. As proporções
do scaling e regras de habilidades não foram alteradas.

[Validação]
Sintaxe PHP validada. Compras MySQL com rollback verificam qualidade comum e
épica, limites dos atributos, igualdade entre preview/bases/atributos ao
nível 1, subida de nível, repetição rejeitada e oferta antiga a zero. Os
refreshes e pool normal passaram com rollback; compra geral passou com PDO
simulado e prémios de vilão passaram com rollback. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- scripts/testing/test_shop_pet_stats.php
- docs/shop_items/lojas.md
- docs/sidekicks/scaling.md
- docs/sidekicks/sidekicks_implementados.md
- scripts/testing/document_sidekicks.py
- docs/sidekicks/sidekicks_info.md

----------------------------------------------------------------------------
## BUG-000163 (2026-10-05) — Renovação global de missões limitada à zona selecionada

[Sintoma]
Renovar todas as missões numa zona inferior à última desbloqueada removia
os pools das zonas superiores e gerava missões apenas até à zona selecionada.
Afetava missões normais e oportunidades de objetos/objetivos de evento.
No bug.har, com 14 zonas desbloqueadas, a seleção na zona 2 devolvia 6
missões, na zona 8 devolvia 24 e na zona 14 devolvia 42.

[Causa]
Character::regenerateQuests(true) apagava os pools anteriores, preservando
a missão ativa, mas usava current_quest_stage como limite do ciclo de geração
em vez de max_quest_stage.

[Correcção]
A renovação global usa max_quest_stage para regenerar três missões por zona
desbloqueada, independentemente da zona selecionada. A missão ativa mantém-se
e ocupa uma das vagas do seu pool. A renovação local continua a usar
current_quest_stage. Não altera as probabilidades de objetos de evento.

[Validação]
PHP lint passou nos dois ficheiros PHP alterados. Testes offline e com PDO
simulado passaram; o simulador foi atualizado para a consulta de participação
em eventos introduzida pelo motor de eventos. Integração MySQL com rollback
passou com 14 zonas desbloqueadas e seleção nas zonas 3, 8 e 14: 42 missões,
composição de dois tempos e um combate por zona, preservação integral da
missão ativa e renovação local sem alterações nas outras zonas.
Confirmação visual no cliente pendente. Os testes não deixaram preparação
persistente no herói.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- scripts/testing/test_quest_generation.php
- docs/missoes/geral.md
- docs/eventos/implementacao.md

----------------------------------------------------------------------------
## BUG-000164 (2026-10-05) — Mísseis de eventos gerados com atributos de equipamento

[Sintoma]
Mísseis ganhos na Pirâmide da Sorte apresentavam resistência, força, crítico e esquiva. O mesmo caminho de geração era usado pela Caça às Toupeiras.

[Causa]
O auxiliar de prémios chamava o gerador de atributos da loja também para o tipo 8. O teste verificava incorretamente o orçamento épico dos equipamentos nas munições.

[Correcção]
O tipo 8 usa agora Missiles::template, com os quatro atributos e stat_weapon_damage a zero. O dano é calculado por Missiles::damage a partir de item_level e das habilidades compatíveis do pet. Preserva raridade épica, borda dourada, ausência de espaço e 100 cargas iniciais. Script de reparação com consulta, backup e transação para itens já gerados, identificados pelos IDs dos prémios dos dois motores.

[Validação]
PHP lint passou. Testes de regressão MySQL passaram com rollback nos 55 catálogos da Pirâmide e nos 47 das Toupeiras, verificando atributos nulos, nível e cargas das munições. Um míssil existente no herói skel foi reparado com backup, preservando nível e cargas; a consulta posterior encontrou zero afetados. Confirmação visual após recarregar o cliente pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- scripts/testing/event_reward_assertions.php
- scripts/testing/repair_event_missiles.php
- docs/eventos/event_draws_implementacao.md
- docs/eventos/event_treasures_implementacao.md

----------------------------------------------------------------------------
## BUG-000165 (2026-10-05) — Recolha da oficina provoca response_errorIO

[Sintoma]
`hideout.har` mostra `collectHideoutRoomActivityResult` a devolver
`response_errorIO` ao recolher um abridor de espaço produzido na oficina.
O cliente apresenta erro e a produção continua por recolher.

[Causa]
A recolha individual passou a usar `Missiles::transaction`, mas o ramo
`gem_production` continuava a chamar `PDO::beginTransaction` na mesma ligação.
A segunda transação era rejeitada antes da entrega do item.

[Correcção]
Removida a gestão duplicada da transação na oficina. A transação exterior
mantém os locks e garante rollback conjunto da criação do item, entrega na
mochila e conclusão da produção. O auxiliar usa savepoint quando invocado
numa transação já existente.

[Validação]
PHP lint passou no handler e no novo script. Integração MySQL com rollback no
skel passou: abridores 13/14, gema de cinto com atributos preservados, item na
resposta, oficina disponível após recolha, rejeição de duplicados e mochila
cheia sem perda da produção nem criação de itens. Regressão dos energéticos
e recolha individual/coletiva de recursos também passou com rollback.
Preparação de testes integralmente revertida; confirmação visual pendente.

[Ficheiros]
- server-laravel/actions/hideoutExtra.php
- scripts/testing/test_hideout_improvement_collection.php
- docs/modificações/implementacao.md


----------------------------------------------------------------------------
## BUG-000166 (2026-10-07) — Geração da oficina ignora bónus de equipa e excede orçamentos pequenos

[Sintoma]
Com bónus bruto de equipa positivo, a geração usa um máximo inferior ao calculado
pelo cliente. Com máximo 1, a distribuição anterior podia atribuir 3 pontos à
opção épica, acima do seu orçamento.

[Causa]
`refreshHideoutItemImprovementRarity` fixava o bónus de equipa em zero, separado
do valor enviado por `getHideoutItemImprovementGuildBonus`. A distribuição
forçava pelo menos um ponto por atributo e recriava um resto positivo quando o
orçamento já estava esgotado.

[Correcção]
Fonte local partilhada para o valor bruto e contagem de membros; conversão para
percentagem e máximo com as fórmulas/arredondamentos de `Cf.get_guildBonus` e
`Cf.updateBonusInfo`. Distribuição limitada ao orçamento disponível. Mantidas
as proporções locais de raridade, sem as apresentar como sorteio oficial.
Instalações sem o campo opcional `guild.gem_production_level` mantêm zero;
a origem oficial desse agregado não foi implementada nem inferida.

[Validação]
`php -l` passou nos três ficheiros PHP alterados/criados.
`test_improvement_scaling.php`: referências numéricas e conservação de orçamento
offline; fonte de equipa e aplicação de gema ao pet com PDO simulado passaram.
`test_workshop_contract.php` e `test_workshop_upgrade.php` passaram offline.
Integração MySQL e confirmação visual não executadas. Nenhuma gema persistida
foi alterada por esta correção.

[Ficheiros]
- server-laravel/app/HeroZero/ItemImprovement.php
- server-laravel/actions/hideoutExtra.php
- scripts/testing/test_improvement_scaling.php
- docs/modificações/implementacao.md


## BUG-000167 (2026-10-07) — Caixas solo HeroCon rejeitadas ao abrir

[Sintoma]
`useInventoryItem` devolvia `errInventoryInvalidItem` ao abrir caixas solo HeroCon.
Após a abertura ser implementada, o diálogo podia mostrar sempre três
equipamentos antigos em vez das uma/duas peças entregues (captura `a.png`).

[Causa]
A entrega estava implementada, mas faltava a abertura; um bloqueio explícito
impedia que o fallback consumisse a caixa sem entregar conteúdo.
O diálogo lê até três entradas da coleção `items`; a resposta incluía todo o
inventário e os sistemas de eventos podiam reconstruir a coleção após o handler.

[Correcção]
Abertura atómica com uma/duas peças do conjunto, prioridade às peças em falta,
efeito animado conforme constantes e capacidade validada antes de consumir.
O Router limita `items` aos prémios depois de todas as atualizações de estado;
o diálogo recebe exatamente uma/duas peças, sem itens antigos adicionais.

[Validação]
PHP lint e `test_herocon.php --mysql` passaram com rollback: entrega por handler,
resposta final pelo Router com exatamente dois/um itens e sem marcador interno;
retry rejeitado, mochila cheia preserva caixa, pequena reutiliza o próprio slot,
histórico de peças permanece mesmo fora da mochila. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/HeroConBox.php
- server-laravel/actions/inventoryOps.php
- server-laravel/app/HeroZero/Router.php
- scripts/testing/test_herocon.php
- docs/implementar/herocon.md

## BUG-000168 (2026-10-07) — NPCs das missões de combate não correspondem ao nome

[Sintoma]
O Vendedor de Balões Evasivo aparecia como hippies e a Policial Fajuta como
ladrão; reproduzido em `captura.har`, `1.png` e `2.png` do servidor local.

[Causa]
`Character::insertQuestPool()` sorteava o NPC do catálogo global sem relação
com a missão. O cliente traduz o nome por `quest.identifier`, mas escolhe
imagem/voz por `fight_npc_identifier`, expondo a divergência.

[Correcção]
Mapa estável de 195 missões normais e 34 temáticas, inferido de nomes, briefings
e imagens locais. Geração e tematização usam esse mapa. Ofertas existentes
são reparadas condicionalmente no estado/início; missões em curso/concluídas,
atributos, custos e prémios são preservados. Casos genéricos permanecem escolhas
visuais locais, documentadas em `docs/missoes/npcs.md`, sem confirmação oficial.

[Validação]
PHP lint; teste offline dos 229 identificadores, nomes e assets; MySQL com
rollback para os dois casos reportados, reparação idempotente de oferta,
preservação dos outros campos e de estados 2/4, geração real. Regressões de
geração offline/PDO simulado, HeroCon MySQL e os 35 eventos/resgates MySQL
passaram com rollback. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/QuestNpcs.php
- server-laravel/app/HeroZero/Character.php
- server-laravel/app/HeroZero/EventQuests.php
- server-laravel/data/reference/quest_npcs.json
- scripts/testing/test_quest_npcs.php
- docs/missoes/npcs.md
- docs/missoes/geral.md


## BUG-000169 (2026-10-07) — Compra confirma mas não entrega o pacote escolhido

[Sintoma]
Em `compras.har`, o conjunto completo da Guerreira do Futuro com acompanhante
recebe uma confirmação de pagamento, mas as cinco peças e o pet não são entregues.

[Causa]
`initPayment.php` apenas somava o `premium_amount` enviado pelo cliente.
`initConsumableOfferPayment` envia `offer_identifier`, sem essa quantidade;
o handler ignorava o conteúdo do catálogo. O estado de pagamento era sempre
confirmado, sem verificar recibo nem propriedade.

[Correcção]
Compras locais gratuitas com resolução do catálogo, entrega completa e recibo
atómicos, validação de nível/capacidade/limites. Pets entram na coleção; peças
entram na mochila. Donuts normais são resolvidos também por preço/método.
Nas novas compras, equipamento é épico com borda e espaço livre; pets recebem
espaço vazio (`item_improvement_id=1`). Atributos de ambos reutilizam os
geradores do brechó. Consulta confirma apenas recibos da conta. Bridge local confirma sem iframe
e acrescenta chave para retries idempotentes; clientes antigos sem chave
continuam a considerar cada pedido inicial uma nova compra.

[Validação]
PHP lint, offline e MySQL com rollback: pacote exato do HAR, 72 ofertas,
coleções no Router/login, saldos sem débito, retries, propriedade do recibo,
mochila cheia sem entrega parcial, limites, fichas, passe, pás e nível.
Validado também orçamento/distribuição épica, bases de pets com ±15% e
espaços livres na resposta e no login, para as 72 ofertas.
Node valida bridge, respostas binárias/JSON e preservação das outras ações.
Migração idempotente aplicada e repetida. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/LocalPayments.php
- server-laravel/app/HeroZero/EventTreasures.php
- server-laravel/app/HeroZero/Router.php
- server-laravel/actions/initPayment.php
- server-laravel/actions/getLastPaymentStatus.php
- server-laravel/actions/paymentOffers.php
- server-laravel/public/index.html
- server-laravel/public/js/local-payments.js
- server-laravel/docker/initdb/16_local_payments.sql
- scripts/testing/migrate_local_payments.php
- scripts/testing/test_local_payments.php
- scripts/testing/test_local_payments_bridge.js
- docs/compras/overview.md


## BUG-000170 (2026-10-07) — Prémios premium da temporada acessíveis sem comprar o passe

[Sintoma]
`hum.har` mostra `premium_unlocked=false` com recompensas premium disponíveis
(status 1), além de prémios premium já resgatados. A faixa paga estava gratuita
sem exigir a compra local do passe.

[Causa]
`Season::rewardsForCharacter()` calculava acesso apenas por pontos/desbloqueios;
`claimReward()` tinha removido a restrição do passe. `unlockReward()` também
permitia desbloquear diretamente um prémio premium sem compra.

[Correcção]
Prémios premium não resgatados têm status 0 antes da compra. Resgatar, descartar
ou desbloquear um prémio premium exige `premium_unlocked=true`; pontos e
registos individuais antigos não fazem bypass. A compra local do passe ativa
o acesso e envia os novos estados. Prémios já resgatados mantêm o histórico.

[Validação]
PHP lint; MySQL com rollback: faixa gratuita disponível, premium bloqueado,
rejeição de resgate/descarte/desbloqueio e bypass antigo; compra do passe,
resposta atualizada, resgate após compra, idempotência e login. Regressão de
prémios da temporada passou com rollback; contrato offline de boot passou.
Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Season.php
- scripts/testing/test_local_payments.php
- scripts/testing/test_season_boot_contract.php
- docs/seasons/Overview.md
- docs/compras/overview.md

## BUG-000171 (2026-10-07) — Esconderijo recebe o segundo robô sem compra

[Sintoma]
`hum.har` mostra dois robôs permanentes. Novos esconderijos recebiam ambos
no desbloqueio inicial, sem comprar o segundo.

[Causa]
`unlockHideout.php` forçava `max_worker_count=2` e `idle_worker_count=2`, embora
a base seja um robô. O catálogo não tinha `hideout_worker`, produto procurado
pelo diálogo do cliente. O login também não incluía o estado real do esconderijo.

[Correcção]
Desbloqueio inicial atómico/idempotente com um robô. Produto `hideout_worker`
com preço visual local 34,90 R$ e sem cobrança dá o segundo permanente, acrescentando um robô livre e
preservando a ocupação anterior. Compra e recibo são atómicos; compra repetida
não dá um terceiro. Login inclui o esconderijo real. A interpretação histórica
de dois iniciais/três após compra foi corrigida nos documentos do cliente.
Esconderijos antigos com dois robôs não são reduzidos automaticamente.

[Validação]
PHP lint; MySQL com rollback para esconderijo novo e retry, catálogo/compra,
primeiro robô ocupado, segundo livre, ausência de débito, recibo/retry,
rejeição de nova compra e login. Catálogo da loja verificado antes/depois da
compra, preço visual e botão/imagem `hideout_worker.webp` servida localmente.
Confirmação visual pendente.

[Ficheiros]
- server-laravel/actions/unlockHideout.php
- server-laravel/actions/autoLoginUser.php
- server-laravel/app/HeroZero/LocalPayments.php
- scripts/testing/test_local_payments.php
- server-laravel/public/assets/special_offers/hideout_worker.webp
- docs/hideout/implementar_hideout.md
- docs/hideout/extract/salas_especiais.md
- docs/hideout/extract/modelo_dados.md
- docs/compras/overview.md

## BUG-000172 (2026-10-07) — Contas novas continuam com energia de treino 100/100

[Sintoma]
`2.png` e `treinos.har` mostram 100/100 numa conta nova e 92/100 após gastar 8.
Recorrência do BUG-TR-026, documentado em `docs/treinos/bugs_corrigidos.md`.

[Causa]
O INSERT de `Character::createNew()` ainda gravava 100/100. Corrigir os defaults
em GameData/schema não corrigia esse caminho usado por `createCharacter`.

[Correcção]
Criação com base 30/30. Script de reparação com simulação, filtro por herói,
backup obrigatório e transação com locks. Preserva o défice após regeneração
pelo relógio do jogo e não modifica missões, unidades ou treino ativo.
Aplicado às três contas afetadas existentes.

[Validação]
Lint e testes offline passaram; MySQL com rollback confirmou criação, login,
estado normal, início de treino/exercício e reparação dos campos de energia.
Após aplicação real, nova simulação encontrou zero candidatos.
Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/Character.php
- scripts/testing/repair_training_energy.php
- scripts/testing/test_training_energy.php
- docs/treinos/bugs_corrigidos.md
- docs/treinos/implementacao.md

## BUG-000173 (2026-10-07) — Outras ofertas confirmadas como compra do segundo robô

[Sintoma]
`que.png` e `que.har`: compras especiais mostram a confirmação do robô do
esconderijo. A loja também anuncia fichas com casino fechado e duas caixas
históricas da temporada da Festa da Piscina.

[Causa]
`getLastPaymentStatus` devolvia sempre `hideout` e `season_progress`; o cliente
`Sd.handlePurchaseComplete` infere o produto pela presença desses campos e dá
prioridade ao esconderijo. O catálogo retirava datas antigas sem filtrar fichas
pelo evento ativo nem excluir as duas caixas históricas de piscina.

[Correcção]
Confirmação baseada no recibo pertencente à conta: contexto de esconderijo só
para robô e de temporada só para passe; texto local com o nome do produto para
as outras compras. Alteração limitada no cliente para mostrar esse texto,
preservando atualização do estado e sincronização. Robô mantém notificação
nativa. Versão do bundle no HTML atualizada para invalidar cache.
Fichas anunciadas/compráveis apenas durante casino ativo pelo GameTime.
Duas caixas `pool_party_season_set_sale_*` removidas do catálogo disponível.

[Validação]
Lint PHP e Node syntax passaram. MySQL com rollback: regressão de compras,
mensagens de equipamento/donuts/passe/fichas e contexto nativo do robô;
casino fechado/futuro/ativo/terminado, visibilidade e rejeição de compras;
caixas de piscina ocultas e rejeitadas. Teste JavaScript executa a função real
do bundle: texto específico, estado atualizado, ausência de falsa notificação
de robô e falha de compra. Bridge anterior passou. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/LocalPayments.php
- server-laravel/actions/getLastPaymentStatus.php
- server-laravel/public/js/HeroZero.min.js
- server-laravel/public/index.html
- scripts/testing/test_local_payments.php
- scripts/testing/test_payment_confirmation_client.js
- docs/compras/overview.md
- docs/casino/README.md


## BUG-000174 (2026-10-09) — Campo ausente no refresh parcial da Academia Heroica

[Sintoma]
No login, um refresh parcial podia falhar em `refreshTowerEventData` com
`DataObject ... unknown field ... occurrence_id`.

[Causa]
A comparação entre ocorrências lia `occurrence_id` sem confirmar que as duas
respostas continham esse campo opcional.

[Correcção]
Só comparar IDs quando o campo existe nos dois objetos. Atualizado o parâmetro
de cache do bundle para o navegador obter o JavaScript corrigido.

[Validação]
Node syntax, teste de contrato JavaScript da Academia Heroica e testes offline e
MySQL com rollback passaram.

[Ficheiros]
- `server-laravel/public/js/HeroZero.min.js`
- `server-laravel/public/index.html`
- `scripts/testing/test_tower_events_client.js`


## BUG-000175 (2026-10-09) — Primeira sala da Academia Heroica impossível de construir

[Sintoma]
Após entrar na academia, o tutorial aponta para a construção, mas o botão de
compra da primeira sala não aparece e o herói não consegue avançar.

[Causa]
A região inicial tinha `ts_unlocked` preenchido e `ts_unlock_end=-1`, mas
`ts_unlock_started=0`. `TowerRegionBase.get_state()` do cliente considera início
zero como estado 1 (fechado) antes de avaliar os restantes campos. A UI esconde
o botão e a ação recusa a região. O backend considerava-a aberta pelo outro campo.

[Correcção]
Enviar início não nulo também para a primeira região. `TowerEconomy::accrue()`
normaliza participações antigas antes do retorno sem tempo decorrido; os caminhos
de estado/ações persistem essa normalização na transação habitual. Não é preciso
reset, novo evento ou migração de schema. Atualizada a versão de cache para v2.

[Validação]
PHP lint; teste offline preservando integralmente o progresso após normalização;
MySQL com rollback nas seis variantes, incluindo reparação persistida via
`attach()` e construção inicial; métodos reais do JS para estado 4 da região,
visibilidade do botão, custo 8, saldo suficiente, clique e comando de desbloqueio.
Contrato JavaScript e testes completos da academia passaram. Em 2026-10-09,
o utilizador confirmou no navegador que a construção da primeira sala já
funciona após a correção.

[Ficheiros]
- `server-laravel/app/HeroZero/TowerEconomy.php`
- `server-laravel/public/index.html`
- `scripts/testing/test_tower_events.php`
- `scripts/testing/test_tower_events_client.js`
- `docs/tower_events_implementacao.md`


## BUG-000176 (2026-10-09) — Contratar funcionários da academia provoca `h.hasData is not a function`

[Sintoma]
Ao contratar um funcionário, incluindo o cofre (`towerCommand`, `command=hire`,
`kind=vault`), o cliente falha em `syncVectorFromArray` e interrompe a atualização.

[Causa]
O listener de `managers` em `TowerManagerPoolBase` passava diretamente o array
recebido ao sincronizador. Os snapshots HTTP da ponte contêm objetos JSON simples;
o sincronizador exige `DataObject` e chama `hasData`. O teste anterior simulava
um pool que convertia esses objetos, não executando o listener original.

[Correcção]
O listener converte a coleção atual por
`getTypedObject("managers").get_asArray()` antes de sincronizar. Esta conversão
suporta snapshots JSON e as coleções de DataObjects das operações locais.
Mantém identidade de gestores existentes, criação/remoção e ligação aos alvos.
A contratação já gravada antes da exceção é carregada ao recarregar; não exige
repetir compra, reset ou novo evento. Versão de cache do cliente atualizada para v3.

[Validação]
Node syntax e contrato JavaScript passaram. A regressão executa o construtor,
listener, sincronizador de gestores e sincronizador de vetores reais do bundle:
primeira/segunda contratação, atualização de objeto existente, remoção e coleção
vazia nos três pools. A ponte de contratação usa estes pools reais para salas,
elevador e cofre, verificando comando/callback e funcionário atribuído ao alvo.
Confirmação visual do utilizador pendente.

[Ficheiros]
- `server-laravel/public/js/HeroZero.min.js`
- `server-laravel/public/index.html`
- `scripts/testing/test_tower_events_client.js`
- `docs/tower_events_implementacao.md`


## BUG-000177 (2026-10-09) — Sala e elevador da academia mostram acumulados sempre em zero

[Sintoma]
O saldo global aumenta, mas os indicadores de moedas na sala e no elevador
permanecem em zero. A produção/transporte não corresponde ao circuito esperado
pelo cliente; esses indicadores não são a taxa de rendimento automático.

[Causa]
O backend creditava diretamente no banco o rendimento limitado pelo menor fluxo,
sem atualizar os `bank_amount` intermédios. A ponte reaplicava zeros nas respostas.
Os ciclos visuais do cofre também podiam acrescentar ao banco moedas já creditadas
pelo cálculo do servidor, criando uma segunda contagem temporária no cliente.

[Correcção]
Separar capacidades em `flows()` e integrar produção/transporte/entrega em
`advanceProduction()`, em passos de até um segundo. Persistir as filas de salas
/elevador e creditar apenas entrega no banco. Preservar ganho offline separado.
Na ponte JS, impedir crédito local de origem `vaultWorker`; aplicar saldo do
servidor. Não alterar outras origens. Cache atualizada para v4, sem reset ou DDL.

[Validação]
PHP lint, Node syntax, testes offline e MySQL nas seis variantes com rollback,
incluindo conservação de moedas, filas de produção/transporte, ausência de
produção repetida no mesmo timestamp e persistência/serialização via `attach()`.
Contrato JS verifica que entrega visual não duplica saldo e que outras entradas
continuam a funcionar. Confirmação visual do utilizador pendente.

[Ficheiros]
- `server-laravel/app/HeroZero/TowerEconomy.php`
- `server-laravel/public/js/HeroZero.min.js`
- `server-laravel/public/index.html`
- `scripts/testing/test_tower_events.php`
- `scripts/testing/test_tower_events_client.js`
- `docs/tower_events_implementacao.md`


## BUG-000186 (2026-10-22) — Coluna «Time» vazia no ranking de heróis

[Sintoma]
Na aba «Heróis» das listas de ranking, a coluna «Time» estava vazia mesmo para
personagens com equipa. A captura `1.png` mostrava vários casos.

[Causa]
`Live::leaderboardCharacters()` preenchia sempre `guild_name` com `''`. Na BD,
por exemplo, DavidRamos pertencia à BotGuild_138, mas a API devolvia nome vazio.
As respostas `retrieveLeaderboard` de `1.har` foram truncadas antes da lista de
heróis; a comparação do campo foi confirmada pela API local. Após acrescentar o
nome, o cliente passou a construir o emblema da equipa e encontrou ausentes os
seis campos `emblem_*`, causando `gameReportError` em
`fromLeaderboardCharacter`.

[Correcção]
A consulta do ranking inclui agora o nome e os seis campos do emblema da equipa
ativa por `LEFT JOIN`. Heróis sem equipa conservam nome vazio e campos do
emblema inteiros com valor zero. A ordenação usa colunas qualificadas para
manter os mesmos critérios após o join.

[Validação]
`php -l` sem erros. Pela API real, `retrieveLeaderboard` com os parâmetros do
cliente e a ordenação por nível devolveu 261 heróis sem erro, 231 com nome de
equipa e os seis campos `emblem_*` inteiros presentes em todas as entradas.
DavidRamos e JoanaSantos surgiram com BotGuild_138 e laliene com GPT ASTRA.
Confirmação visual após recarregar o cliente ainda pendente.

[Ficheiros]
- `server-laravel/app/HeroZero/Live.php`


## BUG-000188 (2026-10-22) — Ordenar equipas por expansão devolvia `response_errorIO`

[Sintoma]
Na aba «Times» do ranking, escolher expansão (`retrieveGuildLeaderboard`,
`sort_type=2`) provocava `response_errorIO`.

[Causa]
A consulta usava o alias `tp` no `ORDER BY` da função `ROW_NUMBER()`, na mesma
SELECT que definia esse alias. O MySQL devolvia `Unknown column 'tp' in 'window
order by'`.

[Correcção]
Os cálculos de posição e a listagem ordenam pela expressão real da expansão,
mantendo `tp` como campo da resposta e o divisor 180 já documentado.

[Validação]
`php -l` sem erros. Pela API real, o pedido com `sort_type=2`,
`guild_name=GPT ASTRA` e `same_locale=false` devolveu 16 equipas sem erro,
`centered_rank=4` e valores de `tp` em ordem decrescente. Também passaram
`sort_type=2` sem pesquisa e `sort_type=1` com pesquisa.

[Ficheiros]
- `server-laravel/actions/leaderboard.php`


## BUG-000196 (2026-10-22) — Brechó só mostrava itens renovados após outra ação

[Sintoma]
Ao clicar para renovar a loja, a operação concluía, mas as imagens dos novos
itens só apareciam depois de equipar/trocar um item ou de outra sincronização.

[Causa]
`refreshShopItems` persistia os novos itens, mas chamava `inventoryData([])`.
Esse método só devolve campos presentes no template recebido; com `[]`, a
resposta tinha `inventory: []` e não comunicava os novos IDs dos slots.

[Correcção]
O handler usa as formas de `autoLoginUser` para `user`, `character`, `inventory`
e `items`, preenchidas com dados atuais da BD. A resposta de renovação inclui
os nove IDs e os itens correspondentes sem esperar por outra ação.

[Validação]
Lint PHP passou. Teste MySQL com rollback confirmou que a resposta de renovação
contém nove slots não vazios, cada um com item correspondente, e saldo de donuts.
Confirmação visual no cliente ainda pendente.

[Ficheiros]
- server-laravel/actions/refreshShopItems.php
- scripts/testing/test_shop_refresh_response.php
- docs/shop_items/lojas.md

## BUG-000198 (2026-10-24) — Combate de equipa sem arremessos crashava em get_displayOptions

[Sintoma]
`k.har` e novo relatório com última ação `syncHideout` mostram crash no painel
battle: `Bb.fromId(...) is null` em `Hm.setMissile`, ao ler get_displayOptions.

[Causa]
O perfil de simulação usa missiles como lista de munições. Ao juntar esse perfil
à aparência, personagens sem arremesso conservavam missiles=[] porque a aparência
não incluía essa chave. O cliente interpreta o campo como identificador e apenas
verifica null. Confirmado em oito dos nove perfis da batalha 8 do laliene.
As últimas chamadas getDailyBonusRewardData/syncHideout não identificavam a origem.

[Correcção]
Normalização dos campos visuais missiles/missiles1–4 para null quando não são
strings não vazias. Novas batalhas são gravadas com a aparência correta; login,
sync e histórico normalizam também perfis antigos em leitura. Não altera
simulação, resultados, recompensas ou dados históricos persistidos.

[Validação]
Lint PHP passou nos quatro ficheiros PHP. Testes offline confirmaram ausência,
identificador equipado, preservação de atributos e novos perfis. Teste MySQL
somente de leitura validou nove perfis históricos. Confirmação visual pendente.

[Ficheiros]
- server-laravel/app/HeroZero/GuildBattles.php
- server-laravel/app/HeroZero/Live.php
- server-laravel/actions/guildState.php
- scripts/testing/test_guild_battle_appearance.php
- docs/Guilda/guild_vs_guild_battles.md

## BUG-000199 (2026-10-24) — Relógio contava tempo desligado e recuava com a data do PC

[Sintoma]
Parar containers sem stop.sh podia contar o intervalo desligado. Recuar a data
real do PC fazia o relógio do servidor recuar dias.

[Causa]
GameTime e leitores Python somavam time() - started_at ao uptime acumulado.
Só stop.sh fechava a sessão antiga. A barra do browser também interpolava o
server_time com Date.now(), ficando sensível a ajustes da hora local.

[Correcção]
Processo monotónico supervisionado dentro do container do jogo grava checkpoints
atómicos de uptime com hrtime(true), a cada 250 ms. GameTime, bots e setup leem
o mesmo valor persistido sem extrapolar tempo offline. O entrypoint termina
jogo e relógio em conjunto; stop.sh deixa de fazer aritmética com datas.
A barra usa performance.now(), limita interpolação e suspende-a após erro de sync.
Na primeira migração preserva uptime_accumulated e ignora a sessão started_at
infiável; não tenta corrigir retroativamente eventos/recompensas já processados.

[Validação]
Testes PHP offline: 24h ligadas, reinício com grande intervalo monotónico, frações,
data civil isolada e proteção contra recuo. Suite Python: 149 testes passaram,
um ignorado, incluindo data real adiantada/recuada um dia sem alterar o resultado.
Lint PHP e sintaxe Bash passaram. Docker real: uptime avançou ligado, checkpoint
ficou idêntico durante paragem direta sem stop.sh e retomou ao reiniciar. API PHP
e leitor Python confirmados sincronizados. Não foi alterada a data real do host.

[Ficheiros]
- server-laravel/app/HeroZero/ActiveGameClock.php
- server-laravel/app/HeroZero/GameTime.php
- scripts/setup/game_clock.php
- scripts/testing/test_active_game_clock.php
- containers/php-entrypoint.sh
- start.sh
- stop.sh
- bots/bot_protocol.py
- bots/setup_async.py
- bots/tests/test_dynamic.py
- server-laravel/public/index.html
- docs/DOCKER.md
- docs/Features.md
- bots/overview.md
