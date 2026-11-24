# Schema de resposta do request.php — campos de nivel raiz (classe `ta` no HeroZero.min.js)

Extraido via regex em `ta.prototype.get_*` de `server/public/js/HeroZero.min.js`
(build html5_257/252). Lista COMPLETA de todo campo que o client sabe interpretar na
raiz de `data` de QUALQUER resposta do `request.php` (o wrapper `ta` e reusado por toda
`JsonActionRemoteRequest`, entao isso vale pra TODAS as actions, nao so o boot).

- **tipo**: `Int`/`String`/`Boolean`/`StringVector`/`TypedObject` = primitivo/map cru;
  `DO` = wrapper de objeto unico (classe indicada); `ArrayOfDO` = colecao (`od`) de DOs.
- **validado**: `x` = ja apareceu numa resposta real capturada (`tools/capture/*.json`
  ou no HAR de boot); vazio = o client sabe ler mas nunca vimos o server oficial mandar —
  risco de feature so coberta por `stateEcho.php` (passthrough generico) no nosso Router,
  nunca validada contra dado real oficial.

## Hardening contra o crash null -> `.iterator()`

Os 96 campos "nunca vistos" caem no `stateEcho.php` (passthrough) e por isso podem
sair com shape incorreta. O risco concreto e o padrao documentado em
`memory/guild-response-key-null-iterator-pattern`: o cliente le o campo por nome
exato e, se vier **presente com `null`** em vez de colecao/mapa vazio, quebra em
`.iterator()`.

`app/HeroZero/ResponseSchema.php` (gerado desta tabela por
`tools/gen_response_schema.php`) roda em `Response::ok()` — choke point de TODA
action — e coage qualquer campo presente-mas-`null` ao container vazio correto:
`[]` para as 59 colecoes (ArrayOfDO/StringVector/Array) e `{}` para os 24 mapas
(TypedObject). Nao injeta campos ausentes (presenca = feature ativa) nem altera
valores ja preenchidos. Assim, mesmo os campos ainda nao validados contra captura
real ficam **crash-safe**: o pior caso vira "feature vazia", nao um travamento.

Validar de fato (coluna `validado`) ainda exige capturar sessoes oficiais que
toquem cada feature; o hardening apenas garante que a ausencia de validacao nao
derruba o cliente.

| campo | tipo | classe DO | validado |
|---|---|---|---|
| `abo_bonus_claimed` | Boolean |  | [x] |
| `abo_bonus_days_left` | Int |  | [x] |
| `ad_settings` | TypedObject |  | [ ] |
| `advertisment_info` | TypedObject |  | [x] |
| `all_loaded` | Boolean |  | [ ] |
| `all_sidekicks` | ArrayOfDO | lf | [ ] |
| `alternative` | String |  | [ ] |
| `available` | Boolean |  | [ ] |
| `battle` | DO | lw | [x] |
| `battles` | ArrayOfDO | lw | [ ] |
| `bonus_info` | DO | jga | [ ] |
| `casino_identifier` | String | `casino_ID_TEMA`; calendário/pendente em [casino](casino/README.md) | [x] |
| `casino_reward_quality` | Int | Resultado pendente: 1–3 | [x] |
| `casino_reward_type` | Int | Símbolo do melhor prémio: 1–12 | [x] |
| `casino_slot1` | Int | Símbolo: 1–12 | [x] |
| `casino_slot2` | Int | Símbolo: 1–12 | [x] |
| `casino_slot3` | Int | Símbolo: 1–12 | [x] |
| `character` | DO | $a | [x] |
| `character_guild_inventory` | DO | Tp | [x] |
| `chests` | ArrayOfDO | y0 | [x] |
| `collected_goals` | TypedObject |  | [x] |
| `collected_item_pattern` | TypedObject |  | [x] |
| `collected_optical_changes` | TypedObject |  | [x] |
| `completed_story_dungeon_steps` | StringVector |  | [x] |
| `constants` | TypedObject |  | [x] |
| `constants_override` | ArrayOfDO | V$ | [x] |
| `current_goal_values` | TypedObject |  | [x] |
| `current_guild_competition_identifier` | String |  | [ ] |
| `current_item_pattern_values` | TypedObject |  | [x] |
| `daily_bonus_reward` | DO | nw | [ ] |
| `daily_bonus_reward_data` | TypedObject |  | [x] |
| `daily_bonus_rewards` | ArrayOfDO | nw | [x] tipos 2/3/4/5: patamares vivos; tipo 1 capturado |
| `daily_login_bonus_rewards` | DO | W$ | [ ] |
| `decrease_counter` | Boolean |  | [ ] |
| `draw_event` | DO | QA | [ ] |
| `dungeon_hardmode_emblems` | Int |  | [x] |
| `dungeon_quest` | DO | Ls | [ ] |
| `dungeon_quests` | ArrayOfDO | Ls | [ ] |
| `dungeons` | ArrayOfDO | Tu | [ ] |
| `event_hub_entries` | ArrayOfDO | JC | [ ] |
| `expired_boosters` | StringVector |  | [ ] |
| `expired_guild_boosters` | StringVector |  | [x] |
| `extendedConfig` | TypedObject |  | [x] |
| `finished_guild_battle_defense` | DO | In | [ ] |
| `finished_guild_dungeon_battle` | DO | xo | [ ] |
| `friend_data` | ArrayOfDO | Sj | [x] |
| `from_character_name` | String |  | [ ] |
| `global_tournament_end_timestamp` | Int | PvP: próxima terça às 21:00 do relógio/fuso do jogo | [x] |
| `goal_item_ids` | TypedObject |  | [ ] |
| `guild` | DO | Ac | [x] |
| `guild_battle_entries` | ArrayOfDO | wo | [ ] |
| `guild_battle_guilds` | ArrayOfDO | Ac | [ ] |
| `guild_chat_message` | TypedObject |  | [ ] |
| `guild_competition_data` | DO | qw | [x] |
| `guild_competition_tournament_end_timestamp` | Int |  | [ ] |
| `guild_dungeon_battle` | DO | xo | [ ] |
| `guild_dungeon_battle_rewards` | ArrayOfDO | RA | [ ] |
| `guild_gem_production_level` | Int |  | [x] |
| `guild_history_battle` | DO | In | [x] |
| `guild_history_battles` | ArrayOfDO | np | [x] |
| `guild_leader_vote` | DO | Uu | [ ] |
| `guild_log` | TypedObject |  | [x] |
| `guild_members` | ArrayOfDO | Nd | [x] |
| `guildbook_objectives` | ArrayOfDO | ok | [x] |
| `guilds_with_rewards` | Int |  | [ ] |
| `has_initialized_unbinding` | Boolean |  | [x] |
| `hidden_object_event_quest` | DO | Mq | [ ] |
| `hideout` | DO | dh | [x] |
| `hideout_battle` | DO | pp | [x] |
| `hideout_history_battles` | ArrayOfDO | qp | [x] |
| `hideout_opponent` | DO | c6 | [x] |
| `hideout_room` | DO | hl | [x] |
| `hideout_rooms` | ArrayOfDO | hl | [x] |
| `inactive_sidekick` | DO | lf | [ ] |
| `ingame_notifications` | TypedObject |  | [ ] |
| `inventory` | DO | wf | [x] |
| `item` | DO | ch | [ ] |
| `item_improvements` | ArrayOfDO | xx | [ ] |
| `items` | ArrayOfDO | ch | [x] |
| `last_payment_confirmed` | Boolean |  | [ ] |
| `last_sync` | String |  | [x] |
| `leaderboard_characters` | ArrayOfDO | $f | [x] |
| `leaderboard_guild_competitions` | ArrayOfDO | Ms | [ ] |
| `leaderboard_hideouts` | ArrayOfDO | $f | [x] |
| `leaderboard_solo_guild_competitions` | ArrayOfDO | yo | [ ] |
| `league_division_change` | String |  | [ ] |
| `league_locked` | Boolean |  | [x] |
| `league_opponents` | ArrayOfDO | x0 | [ ] |
| `league_season_end_timestamp` | Int |  | [x] |
| `legendary_dungeon` | DO | rw | [ ] |
| `legendary_dungeon_run` | DO | tm | [ ] |
| `local_notification_settings` | TypedObject |  | [x] |
| `login_count` | Int |  | [x] |
| `max_characters` | Int |  | [x] |
| `max_guild_competitions` | Int |  | [ ] |
| `max_guilds` | Int |  | [x] |
| `max_hideouts` | Int |  | [x] |
| `max_solo_guild_competitions` | Int |  | [ ] |
| `max_spendable_amount` | Int |  | [ ] |
| `messages` | StringVector |  | [ ] |
| `messages_ignored_character_info` | ArrayOfDO | SA | [ ] |
| `missed_duel` | DO | Yy | [ ] |
| `missed_duel_data` | ArrayOfDO | sw | [ ] |
| `missed_duel_opponents` | ArrayOfDO | Gl | [ ] |
| `missed_duels` | Int |  | [x] |
| `missed_hideout_attacks` | Int |  | [x] |
| `missed_league_fight` | DO | Zy | [ ] |
| `missed_league_fight_data` | ArrayOfDO | kga | [ ] |
| `missed_league_fight_opponents` | ArrayOfDO | Gl | [ ] |
| `missed_league_fights` | Int |  | [x] |
| `new_guild_log_entries` | Int |  | [x] |
| `new_version` | Boolean |  | [x] |
| `news` | TypedObject |  | [x] |
| `next_guild_competition_data` | DO | qw | [ ] |
| `one_time_tutorials` | ArrayOfDO | Hr | [x] |
| `opponent_inventory` | DO | wf | [x] |
| `opponent_inventory_items` | ArrayOfDO | ch | [x] |
| `opponents` | ArrayOfDO | Zm | [x] |
| `outfit` | DO | tw | [ ] |
| `owned_items` | ArrayOfDO | d6 | [x] |
| `payment_id` | Int |  | [ ] |
| `pending_guild_battle_defense` | DO | In | [ ] |
| `pending_guild_dungeon_battle` | DO | xo | [ ] |
| `private_conversation` | DO | Gt | [ ] |
| `private_conversation_messages` | ArrayOfDO | Ns | [ ] |
| `private_conversations` | ArrayOfDO | Gt | [ ] |
| `quest` | DO | zo | [x] |
| `quests` | ArrayOfDO | zo | [x] |
| `reactivation_code` | String |  | [ ] |
| `redirect_url` | String |  | [ ] |
| `requested_character_collected_goals` | TypedObject |  | [x] |
| `requested_character_current_goal_values` | TypedObject |  | [x] |
| `requested_hideout` | DO | dh | [x] |
| `requested_hideout_optical_changes` | TypedObject |  | [x] |
| `requested_hideout_rooms` | ArrayOfDO | hl | [x] |
| `reskill_enabled` | Boolean |  | [x] |
| `resource_request` | DO | zx | [ ] |
| `resource_request_friends` | ArrayOfDO | Sj | [x] |
| `reward_box` | DO | uw | [ ] |
| `reward_boxes` | ArrayOfDO | uw | [ ] |
| `rewards` | StringVector |  | [ ] |
| `saved_seconds` | Int |  | [x] |
| `season` | String |  | [x] |
| `season_progress` | DO | Wu | [x] |
| `season_rewards` | ArrayOfDO | Xu | [x] |
| `sections` | ArrayOfDO | w0 | [ ] |
| `server_system_message_teaser_info` | String |  | [ ] |
| `server_system_messages` | ArrayOfDO | lga | [ ] |
| `show_advertisment` | Boolean |  | [x] |
| `show_preroll_advertisment` | Boolean |  | [x] |
| `sidekick` | DO | lf | [ ] |
| `sidekick_item_skills` | ArrayOfDO | IT | [ ] |
| `sidekicks` | ArrayOfDO | lf | [x] |
| `speedserver_expired` | Boolean |  | [ ] |
| `speedserver_rewards` | ArrayOfDO | f6 | [ ] |
| `story_dungeon_lookup` | DO | bF | [x] |
| `story_dungeon_step` | DO | Os | [x] |
| `story_dungeon_steps` | ArrayOfDO | Os | [x] |
| `stream_id` | Int |  | [ ] |
| `streams_info` | TypedObject |  | [x] |
| `surveys` | ArrayOfDO | KC | [ ] |
| `sync_states` | TypedObject |  | [x] |
| `text` | TypedObject |  | [x] |
| `titles` | ArrayOfDO | h6 | [x] |
| `tournament_end_timestamp` | Int | XP: próximo domingo às 21:00 do relógio/fuso do jogo | [x] |
| `tournament_end_timestamps` | Object | Extensão local: `"1"` XP, `"2"` habilidades (quarta às 21:00); timestamps Unix por aba | [x] |
| `tournament_guild_competition_reward` | DO | Qna | [ ] |
| `tournament_league_reward` | DO | JT | [ ] |
| `tournament_rewards` | DO | MH; local: `id`, `week`, `rewards` (JSON string) | [ ] |
| `tower_event_data` | TypedObject |  | [x] |
| `training` | DO | rp | [x] |
| `training_quest` | DO | Nq | [x] |
| `trainings` | ArrayOfDO | rp | [ ] |
| `treasure_event` | DO | Lq | [x] |
| `url_speedserver_leaderboard` | String |  | [ ] |
| `user` | DO | mga | [x] |
| `user_voucher` | DO | TA | [ ] |
| `user_vouchers` | ArrayOfDO | TA | [ ] |
| `video_advertisment_id` | Int |  | [x] |
| `voucher_rewards` | TypedObject |  | [ ] |
| `worldboss_attack` | DO | Ht | [ ] |
| `worldboss_event_character_data` | Array |  | [x] |
| `worldboss_events` | ArrayOfDO | Up | [ ] |
| `worldboss_previous_attackers` | StringVector |  | [ ] |
| `worldboss_reward` | DO | Zu | [ ] |
| `worldboss_rewards` | ArrayOfDO | Zu | [ ] |
## Login diário e Dias de Bónus (2026-10-04)

Login/estado normal: `daily_login_bonus_day` (int) e
`daily_login_bonus_rewards: {id:int,rewards:string JSON}` apenas enquanto há
prémio por resgatar. Itens de preview incluídos em `items` antes do diálogo.
`bonus_info: {bonus_datas:[{event_id:null,date:int,type:int,value:int,dateFormatted:string}]}`
contém calendário de eventos activos, ou array vazio. Prémios de missão e máximos
continuam base: o cliente aplica os multiplicadores. Regras em `logins/login_diario_e_bonus.md`.

## Contrato local de resgate dos torneios

`getTournamentRewards` devolve `tournament_rewards.rewards` como string JSON.
As keys numéricas são `1` XP, `2` habilidades e `4` PvP. Cada uma contém
`rank`, `value` e `reward: {type, amount, type_2, amount_2}`, além dos campos
locais de pagamento. Novos ciclos separados têm apenas a key do tipo terminado.

Dentro desta string, `season_reward` é uma extensão local com `amount`,
`identifier`, `progress_id` e `ts_start`, que fixa o prémio de pontos do passe.
Não é uma recompensa singular `season_reward` na raiz de `data`.
O bundle lê esse objeto para apresentar o mesmo valor que será creditado.

Após o claim, a resposta inclui saldos atualizados, `season_progress`,
`season_rewards` e `character.active_season_progress_id` alinhado ao progresso.
Detalhes de elegibilidade e numerações em
[recompensas dos torneios](torneios_semanais/recompensas.md).


## Passe de temporada — calendário global (BUG-000143)

`season_progress` mantém o formato do cliente: ID e progresso são individuais;
`season_id`, `identifier`, `ts_start` e `ts_end` pertencem ao ciclo comum do servidor.
Login e estado normal sincronizam o ciclo pelo relógio interno `GameTime` e
enviam `season_rewards` do catálogo ativo. `character.active_season_progress_id`
referencia o ID real da conta. A origem interna `season_calendar.ts_anchor`
não é um campo novo do protocolo. O campo `season` continua a ser a estação
visual, separado do identificador do passe.

Avançar 30 dias no relógio interno avança um tema para todas as contas, aplicado
no próximo acesso. Regras de renovação, preservação de prémios e migração em
[estado das temporadas](seasons/atual.md).

## Casino — giros ilimitados e previews de prémios

`constants.casino_max_daily_spins=0` é a regra local atual: o cliente considera
os giros ilimitados. O backend usa a mesma regra (`Casino::MAX_DAILY_SPINS`),
sem rejeitar lotes de cinco/dez após 30 ou 99999 giros. O contador
`character.slotmachine_spin_count` continua a ser enviado e reiniciado por
data local do GameTime. Custos e disponibilidade do evento mantêm-se.

Prémios de equipamento são enviados em `items` antes de o diálogo os desenhar:
`ts_availability_start=15` ativa a borda warforged nos tipos 1–8;
`ts_availability_end=1` indica espaço de modificação livre nos tipos 1–7 e
no preview de pet (tipo 9). Ao resgatar um pet, o sidekick criado recebe
`item_improvement_id=1`. Raridade épica mantém-se; a borda não significa
qualidade lendária. Mísseis e gemas não são destinos de modificação.
Regras, exceções e testes em [casino](casino/README.md).

Prémios base (BUG-000145): `sections[].rewards` enumera seis energéticos
(`genericRewardType=8`, `genericRewardData={booster_id,days}`), energia
10/60 (`genericRewardType=3`) e treino 4/40 (`genericRewardType=4`) quando
o tema inclui essas categorias. Os IDs dos energéticos são `booster_quest3`,
`booster_stats3`, `booster_work3`, cada um com 3 e 14 dias. O resultado do
giro preserva `booster:{booster_id,days}`, `quest_energy` ou `training_sessions`;
o resgate entrega o cupão pessoal correspondente, não um energético fixo.

BUG-000146: `casino_reward_quality=3` gera os três símbolos iguais e garante,
para energético/energia/treino, duração 14 dias/quantidade 60/quantidade 40.
Qualidades 1/2 correspondem a 3 dias/10 energias/4 unidades. Nos multigiros,
os campos visuais descrevem o melhor resultado, mas cada string em `rewards`
mantém o prémio da qualidade do respetivo giro. Não se recalculam resultados
pendentes anteriormente pagos.
Exemplo num giro individual: `casino_slot1=casino_slot2=casino_slot3=8`
devolve `casino_reward_quality=3` e um prémio `{"training_sessions":40}`.
Com o símbolo 7, o prémio é `{"quest_energy":60}`; com o símbolo 3,
`booster.days=14` num dos três energéticos de 50%.

## Patamares diários de energia (tipo 5)

`daily_bonus_lookup.quest_energy_spent` conta o custo das missões concluídas
apenas quando as suas recompensas são recolhidas em `claimQuestRewards`.
A resposta desse resgate inclui o contador atualizado, `daily_bonus_rewards`
e `daily_bonus_reward_data["5"]`, além dos previews referenciados. A barra
atualiza com essa resposta. `startQuest` e cancelamento não avançam o contador.

`daily_bonus_rewards` usa `{id,type:5,value,status,rewards}`, com `rewards`
como string JSON e estados 0 bloqueado, 1 disponível, 2 resgatado/expirado.
Os patamares são 100/200/300; cada resgate de patamar dá um ponto de temporada.
Energia e unidades de treino são entregues em cupões pessoais. O contador
reinicia à meia-noite do jogo; missões que atravessam essa mudança contam
no dia do resgate. Regras completas em
[patamares_energia.md](missoes/patamares_energia.md).

## Patamares diários de combates (tipos 2 e 3)

`daily_bonus_lookup.duel_battle_won` e `league_battle_won` representam,
por regra local, todos os ataques com resultado resgatado (ganhos ou perdidos),
limitados a 50 e 20. Defesas e retries não contam. Os nomes oficiais são
preservados para compatibilidade com o cliente.

`daily_bonus_reward_data["2"]` define 10/20/50 e `["3"]` define 5/10/20,
com os mesmos enums/shape/estados e resgate do tipo 5. Os seis sorteios são
independentes dos de energia. IDs e previews são persistentes; IDs antigos
são enviados com status 2. Login, estado completo/parcial e configuração
servem os quatro sistemas (energia, duelos, liga e esconderijo). `claimDuelRewards` e `claimLeagueFightRewards`
atualizam o contador e os patamares na própria resposta.
Ver [patamares de combates](missoes/patamares_combates.md).

Nos patamares dos tipos 2/3/4/5, pontos livres usam `statPoints` dentro da string
JSON `rewards`. `stat_points_available` é a coluna do herói, não o nome do
campo da recompensa. Sorteios antigos com `stat_points` são normalizados na
resposta e no resgate para preservar a apresentação e a entrega (BUG-000150).

## Patamares de ataques ao esconderijo (tipo 4)

`daily_bonus_lookup.hideout_battle_won` conta ataques a outros esconderijos
resgatados, ganhos ou perdidos, até 20/dia. Tutorial e defesas não contam.
`daily_bonus_reward_data["4"]` define 5/10/20; resgate, enums, `statPoints`,
estados, previews e reset seguem os tipos 2/3/5. `collectHideoutFightRewards`
envia o contador atualizado na mesma resposta. Ver
[patamares do esconderijo](hideout/patamares_combates.md).
