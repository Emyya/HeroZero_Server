# MAPPING — server-laravel (HeroZero)
# Analise das pastas e ficheiros + funcoes relevantes.

## Estrutura
server-laravel/
  request.php / routes/        entrada HTTP -> Router::dispatch
  app/HeroZero/                nucleo (modelos e helpers)
  actions/                     ume ficheiro por action (retorna callable)
  data/                        fixtures/templates de resposta (json)
  public/js/HeroZero.min.js    cliente HTML5 (contrato)
  docker/                      schema/seed do MySQL

## Fluxo de um request
request -> routes/web.php -> Router::dispatch(action, params)
  -> handler em actions/<x>.php (callable) -> devolve array "data"
  -> Response::ok($data) embrulha em { data, error:"" }
  ResponseSchema::normalize() roda no choke point e coage null->{} / [] (crash-safe)

## app/HeroZero/Router.php  (Routing action -> handler)
- const HANDLERS (linha 19): mapa action => ficheiro em actions/
- dispatch(action, params) (linha 415):
    1) handler dinâmico;  2) Replay::data(action);  3) accountState (dev);
    4) errActionNotSupported
- Tutorial / missao / loja / duelo (linhas 40-115):
    startQuest          -> startQuest.php
    instantFinishQuest  -> instantFinishQuest.php
    checkForQuestComplete -> checkForQuestComplete.php
    claimQuestRewards   -> claimQuestRewards.php
    generateNewQuests   -> generateNewQuests.php
    abortQuest          -> abortQuest.php
    setTutorialFlags    -> setTutorialFlags.php      <-- action do bug
    improveCharacterStat -> improveCharacterStat.php
    startDuel           -> startDuel.php
    checkForDuelComplete-> checkForDuelComplete.php
    claimDuelRewards    -> claimDuelRewards.php
    buyShopItem         -> buyShopItem.php
    refreshShopItems    -> refreshShopItems.php
    sellInventoryItems  -> sellInventoryItems.php
    startTraining / buyTrainingEnergy / finishTraining
    getDuelOpponents    -> getDuelOpponents.php
    syncGame            -> syncAccountState.php
- Nota: muitas actions nao cobertas caem em stateEcho.php (passthrough generico).

## app/HeroZero/Character.php  (modelo, estrategia "DB + template")
Metodos (nº linha aprox.):
- const TUTORIAL_DONE_FLAGS (101): lista de flags de tutorial que o server conhece.
    ordem = sequencia do tutorial:
    ts_prb_start(-1), shop2_football_generic, itemImprovementsUpdate, first_visit,
    mission_shown, first_mission_opened, first_mission_started, first_mission,
    stats_spent, shop_shown, first_item, duel_shown, first_duel,
    tutorial_finished, training_new, dungeons
- createNew(134)          cria conta nova (nivel 1, tutorial zerado)
- seedStarterQuests(212)  pool inicial de missoes (stage 1)
- insertQuestPool(240) / scaleQuestRewards(273) / xpRewardFactor(287)
- regenerateQuests(314)
- load(339) / loadByUser(349)
- xpTable(374) / levelForXp(389) / syncLevelFromXp(415)
- syncTutorialForLevel(439)  SO marca todas as flags quando level>=3 (escape hatch)
- setTutorialFlag(463)      NOVO helper: marca 1 flag (usado p/ progresso)
- syncAppearanceDefaults(484) / syncTrainingForLevel(502)
- equipBonus(558)
- overlayCharacter(559)    overlay das colunas do banco no template; le tutorial_flags
- overlayUser(718) / inventoryData / itemsData / questsData / trainingsData
- ensureShopItems(762) / refreshShopItems(807) / generateShopItem / insertItemFromTemplate
- buyShopItem(949)         compra item da loja  -> aqui marcar first_item
- startQuest(1456)         inicia missao
- checkActiveQuestComplete(1300) / instantFinishQuest / claimQuestRewards(1340) -> first_mission
- battleProfile / duelOpponents(1417)
- improveStat(2133)        -> marcar stats_spent
- treino: startTraining, startTrainingQuest, claimTrainingQuestRewards, claimTrainingStar
- hideout / season / treasure / story dungeon / casino / goals / boosters
- Casino: [máquinas, eventos, protocolo e agendamento](casino/README.md).

## app/HeroZero/Live.php  (monta respostas a partir de templates + banco)
- template(action) (12)      carrega fixture/Replay do <action>
- overlayAccount(data,userId)(133)  põe user/character/inventory/items/quests/trainings
- accountState(userId,data)(298)    resposta COMPLETA da conta (user, character, inventory,
                                    items, quests, trainings, news, guild, optical, ...)
                                    <- setTutorialFlags deveria devolver isto
- attachTrainingState / attachEventQuestState / attachTreasureEventState / attachStoryDungeonState
- shapeGuild / guildMembers / leaderboardCharacters / leagueOpponents / shapeLike / castLike
- BOOSTER_CATALOG(60) / GUILD_BOOSTER_CATALOG(80) / boosterCost(93)

## app/HeroZero/Response.php  (envelope)
- ok(data) -> '{ "data": {...}, "error": "" }'
- error(str) -> '{ "data": {}, "error": "..." }'

## app/HeroZero/ResponseSchema.php  (hardening null->container)
- normalize(data) (116)   coage campo presente-mas-null -> [] (colecao) / {} (mapa)

## app/HeroZero/Protocol.php  (auth)
- signature(action,userId) -> md5(action + "GN1al351" + userId)
- verifyAuth(params) / strictAuth()

## app/HeroZero/Session.php
- guard(action,params) / rotate(userId) / assertMatches(params)

## app/HeroZero/Db.php  (PDO)
- pdo/row/rows/value/column/exec

## app/HeroZero/Replay.php
- has(action) / data(action)   devolve resposta capturada (fixture)

## app/HeroZero/GameData.php
- user() / character()  shapes-base p/ criar conta

## app/HeroZero/GuildChat.php / SocketPush.php
- chat (entry/visibleMessages/version) e push de syncGame via socket

## actions/ — handlers de action relevantes ao tutorial
- setTutorialFlags.php       <-- BUG: devolve Live::overlayAccount(template vazio) =>
                                SEM character; cliente nao recebe tutorial_flags de volta.
- claimQuestRewards.php      responde user/character/quests (template claimQuestRewards)
- startQuest.php             responde user/character/quest + quests
- checkForQuestComplete.php  responde user/character/quest [+ battle]
- instantFinishQuest.php
- buyShopItem.php            responde user/character/inventory/items/current_goal_values
- refreshShopItems.php
- improveCharacterStat.php   responde character/user (template improveCharacterStat)
- startDuel.php / checkForDuelComplete.php / claimDuelRewards.php / getDuelOpponents.php
- stateEcho.php              passthrough generico p/ acoes nao implementadas
- syncAccountState.php       responde accountState (usado por syncGame)

## Tutorial (fluxo no cliente, public/js/HeroZero.min.js)
- setTutorialFlag(flag)      envia setTutorialFlags{flags,flag}; ESPERA a resposta p/
                             atualizar character.tutorial_flags (updateData)
- seta de navegacao:
    hasMissionShown && hasFirstMission
        se stats disponiveis e level==2  -> seta em STATS (stats_spent)
        senao se shopShown && firstItem  -> seta em DUELOS (first_duel)
        senao                             -> seta na LOJA  (first_item)
    senao                                 -> seta na MISSAO (first_mission)
- hasNewTutorialFlag(): "stats_spent" | "first_item" | "first_duel"
- flags que SO o SERVIDOR deriva (o cliente nunca envia): first_mission,
  stats_spent, first_item, first_duel.
