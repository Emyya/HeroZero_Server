# Vilões / world boss

Implementado em `Worldboss.php`, com os sete handlers do cliente HTML5 e os 39
`worldboss_event_templates` do ficheiro comprimido de constantes. Inclui os vilões
por zona, eventos especiais e Primal/Utopia/Dystopia. Não usa anúncios. As imagens
principais e thumbnails disponíveis na raiz foram copiadas para os assets públicos
quando faltavam, preservando os ficheiros existentes.

## Índice

- [Arquitetura e fontes de verdade](#arquitetura-e-fontes-de-verdade)
- [Catálogo de templates disponíveis](#catálogo-de-templates-disponíveis)
- [Iniciar um evento](#iniciar-um-evento)
- [Ciclo completo do evento e do herói](#ciclo-completo-do-evento-e-do-herói)
- [Pedidos e resposta do protocolo](#pedidos-e-resposta-do-protocolo)
- [Regras confirmadas no cliente e nos textos](#regras-confirmadas-no-cliente-e-nos-textos)
- [Balanceamento local, sem fórmula oficial confirmada](#balanceamento-local-sem-fórmula-oficial-confirmada)
- [Exemplo de atributos e resolução do triplo](#exemplo-de-atributos-e-resolução-do-triplo)
- [Recompensas em vigor (2026-10-05)](#recompensas-em-vigor-2026-10-05)
- [Preparação e entrega do prémio, passo a passo](#preparação-e-entrega-do-prémio-passo-a-passo)
- [Golpe final e ataques simultâneos](#golpe-final-e-ataques-simultâneos)
- [Persistência e concorrência](#persistência-e-concorrência)
- [Modelo de dados e invariantes](#modelo-de-dados-e-invariantes)
- [Diagnóstico e limites conhecidos](#diagnóstico-e-limites-conhecidos)
- [Validação](#validação)
- [Atributos no combate (2026-10-05)](#atributos-no-combate-2026-10-05)
- [Animação de combate (2026-10-05)](#animação-de-combate-2026-10-05)

## Arquitetura e fontes de verdade

O sistema separa o **evento global**, a **tentativa de ataque de um herói**, as
**batalhas individuais** e o **prémio desse herói**. Uma linha de ataque triplo
contém até três batalhas; não cria três eventos nem três prémios.

| Componente | Responsabilidade |
|---|---|
| `server-laravel/app/HeroZero/Worldboss.php` | Catálogo, associação, ataques, vida global, ranking, preparação e entrega de prémios |
| `server-laravel/actions/worldboss.php` | Recebe os parâmetros, identifica o herói da conta e junta o resultado ao estado normal |
| `server-laravel/app/HeroZero/Router.php` | Encaminha as sete ações e acrescenta estado worldboss às respostas parciais aplicáveis |
| `server-laravel/actions/autoLoginUser.php` | Recupera o estado dos vilões no login |
| `server-laravel/app/HeroZero/Live.php` | Acrescenta os vilões a `accountState` |
| `server-laravel/app/HeroZero/StoryDungeon.php` | Fornece a transação com locks de personagem/utilizador e suporte de savepoints |
| `server-laravel/app/HeroZero/Character.php` | Perfil do atacante, aparência, geração de equipamento e atualização de nível |
| `server-laravel/app/HeroZero/Fight.php` | Simulação de rounds, probabilidades, dano e efeitos |
| `server-laravel/app/HeroZero/Missiles.php` | Snapshot e consumo real de munição por batalha |
| `server-laravel/app/HeroZero/Sidekick.php` | Catálogo elegível de pets e criação do pet entregue |
| `server-laravel/app/HeroZero/UserVouchers.php` | Criação e apresentação dos cupões de energia/treino |
| `server-laravel/public/js/HeroZero.min.js` | Interface, sequência visual dos combates e leitura dos campos do protocolo |
| `server-laravel/docker/initdb/01_schema.sql` | Schema para instalações novas |
| `scripts/testing/migrate_worldboss.php` | Atualização idempotente de instalações existentes |

As constantes estão comprimidas com zlib. `Worldboss::constants()` lê a cópia
pública em `server-laravel/public/assets/data/constants_json.data`, através de
`AssetCache::slice('worldboss_v2', ...)`, e retém as chaves `worldboss_*`,
`item_templates`, `item_pattern`, `npcs`, `quest_duration_short` e
`quest_level_full_duration`. A raiz `assets/data/constants_json.data` é também
uma fonte de referência; uma alteração apenas nessa cópia não demonstra que o
backend passou a usá-la.

| Origem | O que sustenta |
|---|---|
| Constantes e JavaScript do cliente | Identificadores, NPCs, fontes de objetos, custos premium, limiar do triplo, tipos/quantidades dos escalões e consumo do protocolo |
| Textos oficiais do cliente | Descrição do ranking e prémios; incluem uma regra de transferência do golpe final que o servidor local não aplica |
| Regras pedidas para este servidor | Fatores fixos 95–120%, autor real do golpe final, famílias de equipamento, exceção fora do top 10 e probabilidade de pet de 50% |
| Balanceamento local do código | Vida/duração do evento configuráveis, moedas/XP por nível, cálculo do escalão por posição/participantes e alternativas temáticas sem fonte worldboss direta |

A presença de um conjunto ou pet nas constantes não prova, por si só, a sua
probabilidade oficial de entrega. As regras abaixo descrevem a implementação
local em vigor em 2026-10-05.

## Catálogo de templates disponíveis

Inventário dos 39 templates carregados pelo backend e número de candidatos
dos pools atuais. A coluna de pets indica o tamanho do pool após filtragem,
não a quantidade de pets entregue: um herói recebe no máximo um pet. Estes
valores devem ser revistos se os assets ou o catálogo de `Sidekick` mudarem.

| Template | Categoria | NPC | Peças superior/inferior | Pets elegíveis |
|---|---|---|---:|---:|
| `default_event_stage1` | Normal | `npc_joker` | 5/5 | 110 |
| `default_event_stage2` | Normal | `npc_c3_d2` | 5/5 | 110 |
| `default_event_stage3` | Normal | `npc_sergant` | 5/5 | 110 |
| `default_event_stage4` | Normal | `npc_revenger` | 5/5 | 110 |
| `default_event_stage5` | Normal | `npc_mad_scientist` | 5/5 | 110 |
| `default_event_stage6` | Normal | `npc_dark_father` | 5/5 | 110 |
| `default_event_stage7` | Normal | `npc_prof_evil` | 5/5 | 110 |
| `default_event_stage8` | Normal | `npc_mafioso_godfather` | 5/5 | 110 |
| `default_event_stage9` | Normal | `npc_mario` | 5/5 | 110 |
| `default_event_stage10` | Normal | `npc_artist` | 5/5 | 110 |
| `default_event_stage11` | Normal | `npc_dirty_slob` | 5/5 | 110 |
| `olympia_event_stage2` | Especial | `npc_olympia_worldboss` | 5/5 | 3 |
| `halloween_event_stage2` | Especial | `npc_zombie` | 10/10 | 2 |
| `xmas_event_stage2` | Especial | `npc_grinch` | 35/35 | 5 |
| `easter_event_stage2` | Especial | `npc_easterthief` | 12/12 | 4 |
| `telekom_event_stage2` | Especial | `npc_telekom` | 5/5 | 0 |
| `winterolympics_event_stage2` | Especial | `npc_olympia_worldboss` | 5/5 | 0 |
| `fonic_event_stage1` | Especial | `npc_fonic` | 5/5 | 0 |
| `ghostbusters_event_stage2` | Especial | `npc_marshmellow_man` | 5/5 | 0 |
| `worldcup_event_stage2` | Especial | `npc_worldcup_killjoy` | 7/7 | 4 |
| `default_event_stage12` | Normal | `npc_poacher` | 5/5 | 110 |
| `hercules_event_stage1` | Especial | `npc_hercules_hydra` | 5/5 | 0 |
| `tmnt_event_stage1` | Especial | `npc_tmnt_shredder` | 5/5 | 0 |
| `disneyinfinity_event_stage1` | Especial | `npc_disneyinfinity_maleficent` | 5/5 | 0 |
| `rtl2promo_event_stage1` | Especial | `npc_rtl2promo_lora_ley` | 5/5 | 0 |
| `default_event_stage13` | Normal | `npc_gontrilla_roboter` | 5/5 | 110 |
| `bttf_event_stage1` | Especial | `npc_bttf_buff` | 5/5 | 0 |
| `default_event_stage14` | Normal | `npc_egypt_mummy_robot` | 5/5 | 110 |
| `sci_fi_week_stage2` | Especial | `npc_reverse_nerd` | 5/5 | 2 |
| `environmental_week_stage2` | Especial | `npc_air_polluter` | 5/5 | 2 |
| `video_games_week_stage2` | Especial | `npc_pacman` | 5/5 | 3 |
| `carnival_stage2` | Especial | `npc_carnival_boss` | 5/5 | 1 |
| `primal_season_worldboss` | Passe | `npc_primal_season_worldboss` | 5/5 | 0 |
| `utopia_season_worldboss` | Passe | `npc_utopia_season_worldboss` | 5/5 | 0 |
| `default_event_stage1_2` | Normal | `npc_burglar_duo` | 5/5 | 110 |
| `default_event_stage2_2` | Normal | `npc_rude_rocker` | 5/5 | 110 |
| `default_event_stage3_2` | Normal | `npc_pompous_salesman` | 5/5 | 110 |
| `default_event_stage4_2` | Normal | `npc_real_estate_shark` | 5/5 | 110 |
| `dystopia_season_worldboss` | Passe | `npc_dystopia_season_worldboss` | 5/5 | 0 |

As quantidades resultam de `equipmentPool()` e `petPool()`. Um pool vazio de
pets significa que essa categoria não entrega pet, mesmo com um sorteio <=50.
O nome do NPC é também usado no caminho da imagem principal
`server-laravel/public/assets/worldboss_npcs/<npc_identifier>.webp`.

## Iniciar um evento

Há três entradas específicas, todas com `--list`, `--help` e `--dry-run`:

- `start_worldboss_normal.php`: 18 templates normais das zonas (inclui as quatro variantes `_2`).
- `start_worldboss_season.php`: 3 templates dos arcos Primal/Utopia/Dystopia.
- `start_worldboss_event.php`: 18 variantes especiais, incluindo Natal, Halloween,
  Páscoa, Olimpíadas e promoções. Natal/Halloween/Páscoa são eventos festivos;
  a sua presença neste catálogo não implica ligação ao passe de temporada.

```bash
php scripts/testing/start_worldboss_normal.php --list
php scripts/testing/start_worldboss_normal.php default_event_stage1 --hp=100000 --minutes=60
php scripts/testing/start_worldboss_season.php --list
php scripts/testing/start_worldboss_season.php primal --stage=1 --hp=500000 --minutes=120
php scripts/testing/start_worldboss_event.php --list
php scripts/testing/start_worldboss_event.php natal --hp=500000 --minutes=120
```

Cada entrada rejeita templates de outras categorias. Os nomes `primal`, `utopia`,
`dystopia`, `natal`, `halloween` e `pascoa` são atalhos locais dos identificadores
oficiais. Os identificadores completos continuam disponíveis. Natal, Halloween e
Páscoa têm zona padrão 2, derivada dos seus templates; pode usar `--stage=N`.
Nenhum destes scripts ativa o passe ou a campanha festiva completa, apenas o vilão.

O script genérico continua disponível. Na raiz do projeto:

```bash
php scripts/testing/start_worldboss.php --list
php scripts/testing/start_worldboss.php default_event_stage1 --hp=100000 --minutes=60
php scripts/testing/start_worldboss.php primal_season_worldboss --stage=1 --hp=500000 --minutes=120
php scripts/testing/start_worldboss.php utopia_season_worldboss --stage=2 --hp=500000 --minutes=120
php scripts/testing/start_worldboss.php dystopia_season_worldboss --stage=3 --hp=500000 --minutes=120
```

O script cria um evento global persistente; não começa ataques pelos jogadores.
Recarregar o jogo para o receber imediatamente. As leituras de estado seguintes
também o anunciam. A zona precisa de estar desbloqueada pelo herói.
`--min-level=N` e `--max-level=N` restringem participação. `--dry-run` mostra a
configuração sem escrever na BD. `--help` explica todas as opções.
Nos identificadores com `stageN`, a zona padrão é N; nos restantes é 1.
A zona passada no comando pode ser diferente da zona sugerida pelo identificador;
o backend valida o intervalo 1–14 e usa o `stage` guardado no evento.
Recusa outro vilão ainda ativo na mesma zona; não substitui eventos nem apaga dados.
Vida: 1–2 000 000 000; duração: 1–10 080 minutos, medida por `GameTime`.
Não há agendamento automático nem ligação à rotação do passe: estes scripts permitem
lançar manualmente também os vilões das temporadas fora do seu tema corrente.

Instalações existentes precisam de executar, uma vez:

```bash
php scripts/testing/migrate_worldboss.php
```

Migração idempotente: adiciona iterações, lista de batalhas e acumulados de moedas/XP
nos ataques; três percentagens fixas por evento para força, cérebro e intuição;
estado de resgate e índice único evento/herói nos prémios. Não executa
DDL dentro de transações de jogo. Não remove eventuais duplicados antigos.
O schema inicial contém os mesmos campos. Migração executada nesta instalação.

## Ciclo completo do evento e do herói

1. O operador cria o evento: template, zona, intervalo de níveis, vida total e
   prazo. São guardados os três fatores de atributos e uma peça para cada faixa
   de equipamento. A criação não inicia ataques nem cobra jogadores.
2. Login/estado normal apresentam os eventos ativos compatíveis com o herói.
   O evento já associado também é recuperado, mesmo depois de terminar.
3. `assignWorldbossEvent` associa o herói; `startWorldbossAttack` também faz essa
   associação, valida as atividades e cria a tentativa normal ou tripla.
4. A tentativa aguarda `ts_complete`, ou o jogador pede conclusão instantânea.
   **Iniciar a tentativa não retira vida ao vilão.** O dano só é calculado quando
   uma ação de conclusão resolve efetivamente as batalhas.
5. Cada batalha lê a vida global sob lock, calcula os perfis, simula rounds,
   guarda o resultado, consome munição e confirma o dano no evento.
6. A conclusão devolve as batalhas para visualização. `finishWorldbossAttack`
   fecha a tentativa e liberta `active_worldboss_attack_id`.
7. O herói pode iniciar outra tentativa enquanto o evento estiver ativo.
8. O evento termina por vida zero ou por prazo. A existência de estado 4 não
   distingue vitória de fuga: a preparação do prémio verifica se a vida é zero.
9. Depois de fechar/libertar a tentativa pendente, a leitura de estado prepara
   o prémio individual, incluindo os previews dos objetos.
10. `claimWorldbossEventRewards` entrega o prémio numa transação, marca o resgate
    e limpa a associação ao evento. Só então o herói fica livre para mudar de
    evento quando já tinha participado no anterior.

A expiração é verificada em `event()` nas leituras/operações do sistema; não
existe neste módulo um processo periódico que percorra todos os eventos para
os terminar exatamente ao segundo. O prazo usa `GameTime::now()`.

### Estados persistidos e estados apresentados

| Entidade/campo | Valor | Significado na implementação |
|---|---:|---|
| `worldboss_event.status` na BD | 1 | Evento ativo |
| `worldboss_event.status` na BD | 4 | Evento terminado por morte ou prazo |
| `worldboss_events[].status` na resposta | 2 | Evento terminado, mas este herói ainda tem tentativa aberta; valor derivado, não gravado como estado global |
| `worldboss_attack.status` | 1 | Tentativa iniciada; pode já conter algumas batalhas de um triplo parcialmente confirmado |
| `worldboss_attack.status` | 3 | Todas as batalhas possíveis resolvidas, à espera de fecho |
| `worldboss_attack.status` | 4 | Tentativa fechada |
| `worldboss_attack.status` | 5 | Tentativa cancelada/libertada sem resolução |
| `worldboss_reward.claimed` | 0 | Prémio preparado, por resgatar |
| `worldboss_reward.claimed` | 1 | Prémio entregue; nova entrega rejeitada |

Os valores iniciais 0 existentes no schema não constituem um fluxo adicional
implementado. Uma tentativa fechada ou cancelada deixa de ser selecionada pelo
ID ativo do herói. O resultado de `finishWorldbossAttack` pode incluir o snapshot
3 calculado antes do UPDATE para 4; a BD e o ID ativo já refletem o fecho.

### Elegibilidade e atividades incompatíveis

O nível deve estar entre `min_level` e `max_level`, inclusive, e a zona do evento
deve ser <= `character.max_quest_stage`. O evento tem de estar ativo para associar
ou iniciar. Iniciar é recusado quando existir:

- outra tentativa de vilão ativa;
- duelo, combate de liga ou missão de dungeon ativos;
- trabalho com estado 1;
- missão normal ativa sem um reforço de multitarefa ainda válido.

Estes são os bloqueios verificados por `Worldboss::action`; não devem ser
interpretados como uma lista de todas as atividades do jogo. A associação
isolada não executa todos os bloqueios de início de combate.

Se o herói já estiver associado ao mesmo evento, a associação é mantida. Para
mudar, não pode ter tentativa ativa. Se o evento anterior tiver ataques realizados
ou já estiver terminado, exige prémio resgatado. Sem ataques e com o anterior
ativo, o código permite mudar. Uma associação vazia a um evento terminado pode
preparar um prémio de zero moedas/XP para permitir encerrar essa ligação.

## Pedidos e resposta do protocolo

Os sete pedidos passam por `POST /request.php`, com o envelope e autenticação
comuns descritos em [PROTOCOL.md](../PROTOCOL.md) e
[RESPONSE_SCHEMA.md](../RESPONSE_SCHEMA.md). A tabela apresenta os parâmetros
específicos; não reproduz assinaturas, tokens ou credenciais.

| Ação | Parâmetros específicos | Efeito |
|---|---|---|
| `assignWorldbossEvent` | `worldboss_event_id` | Associa o herói sem criar tentativa |
| `startWorldbossAttack` | `worldboss_event_id`, `iterations` (1 ou 3; omitido = 1) | Valida, cobra o início do triplo e cria tentativa com prazo |
| `abortWorldbossAttack` | Nenhum; usa o ID ativo | Cancela apenas estado 1 sem batalhas confirmadas |
| `instantFinishWorldbossAttack` | Nenhum; usa o ID ativo | Cobra se ainda falta tempo e resolve as batalhas possíveis |
| `checkForWorldbossAttackComplete` | Nenhum; usa o ID ativo | Resolve se o prazo chegou; antes disso devolve erro |
| `finishWorldbossAttack` | `worldboss_event_id` | Resolve se necessário e já permitido, fecha tentativa e liberta atividade |
| `claimWorldbossEventRewards` | `worldboss_event_id`, `discard_item` (opcional) | Entrega prémio; descarte refere-se apenas ao equipamento |

O handler obtém o herói através da conta. Não aceita um `character_id` arbitrário
para escolher quem ataca. `discard_item` é convertido em booleano pelo handler.
O ID de evento no fecho tem de corresponder ao da tentativa.

### Coleções e campos enviados

| Campo em `data` | Conteúdo e utilização |
|---|---|
| `worldboss_events` | Eventos compatíveis ativos e o associado; inclui campos da BD e totais personalizados de ranking/moedas/XP |
| `worldboss_attack` | Tentativa ativa ou snapshot da tentativa resolvida, com `battle_ids`, `battle_id`, dano e prémios acumulados |
| `battles` | Linhas de batalha referenciadas pela tentativa, para reproduzir os rounds |
| `worldboss_previous_attackers` | Até 10 nomes de tentativas anteriores por ID, com estado 3/4; não é o ranking nem uma lista garantida de heróis distintos |
| `worldboss_rewards` | Prémio preparado e ainda não resgatado do evento associado |
| `worldboss_event_character_data` | Coleção inicializada vazia pela implementação atual |
| `items` | Inclui previews referenciados por `item_id`/`sidekick_item_id`, além dos objetos normais já presentes |
| `character.worldboss_event_id` | Evento associado ao herói |
| `character.active_worldboss_attack_id` | Tentativa que ainda ocupa a atividade; zero quando libertada |
| `character.worldboss_event_attack_count` | Número de batalhas efetivamente realizadas nesse evento |

Cada evento acrescenta `ranking` (posição do herói, zero sem participação),
`coin_reward_total`, `xp_reward_total`, `coin_reward_next_attack` e
`xp_reward_next_attack`. Estes dois últimos são as quantidades por uma batalha,
não o prémio total de um triplo. A linha global pode existir para quem ainda não
está associado; o ID da personagem determina qual é o evento escolhido.

`battle_ids`, `profile_a_stats`, `profile_b_stats`, `rounds` e `rewards` são campos
armazenados/transportados como texto JSON nos respetivos registos. Não devem ser
convertidos arbitrariamente em arrays ao alterar o protocolo. `cast()` converte
strings numéricas em números, preservando os campos de nome/identificador
explicitamente tratados no código.

`Worldboss::attach()` é chamado no login, em `Live::accountState` e pelo Router
quando uma resposta parcial contém personagem mas ainda não contém os eventos.
O preenchimento de `items` acontece depois de preparar os previews: um ID de
recompensa sem o respetivo objeto não é suficiente para o cliente o desenhar.
Uma leitura de estado pode, portanto, escrever na BD: terminar um evento expirado,
inicializar fatores antigos ou preparar o prémio e os seus objetos.

### Erros e condições

| Erro | Condição principal |
|---|---|
| `errRequestInvalidParameter` | Ação inválida ou número de iterações diferente de 1/3 |
| `errStartWorldbossAttackInvalidWorldbossEvent` | Evento inexistente/inativo ou nível/zona incompatíveis |
| `errAssignWorldbossEventInvalidId` | Mudança de evento impedida pelo progresso/resgate anterior |
| `errStartWorldbossAttackActiveWorldbossAttackFound` | Tentativa de vilão ativa |
| `errStartWorldbossAttackActiveDuelFound` | Duelo ativo |
| `errStartWorldbossAttackActiveLeagueFightFound` | Combate de liga ativo |
| `errStartWorldbossAttackActiveDungeonQuestFound` | Missão de dungeon ativa |
| `errStartWorldbossAttackActiveWorkFound` | Trabalho ativo |
| `errStartWorldbossAttackActiveQuestFound` | Missão ativa sem multitarefa válida |
| `errCreateWorldbossEventHitpointsToLow_<vida>` | Triplo iniciado abaixo do limiar; sufixo contém HP atual |
| `errRemovePremiumCurrencyNotEnough` | Saldo insuficiente para débito premium |
| `errFinishNotYetCompleted` | Prazo da tentativa ainda não chegou e não houve conclusão instantânea |
| `errAbortWorldbossAttackNoActiveWorldbossAttack` | Cancelamento sem tentativa ativa válida |
| `errInstantFinishWorldbossAttackNoActiveWorldbossAttack` | Conclusão instantânea sem tentativa ativa válida |
| `errCheckForWorldbossAttackCompleteNoActiveWorldbossAttack` | Verificação sem tentativa ativa válida |
| `errFinishInvalidWorldbossAttackStatus` | Fecho/estado inválido, evento de fecho incorreto ou cancelamento após batalha confirmada |
| `errClaimWorldbossEventRewardsInvalidStatus` | Evento não terminado/associado, tentativa ainda aberta, prémio resgatado ou preview inválido |
| `errInventoryNoEmptySlot` | Equipamento a receber sem espaço na mochila |

A tabela cobre os erros principais emitidos por este módulo. Dependências de
inventário, pets, cupões e personagem podem também emitir erros próprios.

## Regras confirmadas no cliente e nos textos

- `startWorldbossAttack`: ataque normal ou 3 iterações por 1 donut.
- Só o triplo exige vida atual >= `ceil(vida total × 0.1)`; abaixo disso o normal
  continua disponível. A rejeição atualiza a vida no cliente pelo erro
  `errCreateWorldbossEventHitpointsToLow_<vida>`.
- Conclusão instantânea: 2 donuts por iteração (6 no triplo), além do custo de 1.
- Duração base: `quest_duration_short = 300` segundos. Antes do nível 10 multiplica
  por nível/10; desconta boosters de missão pessoais e da guilda; arredonda ao
  minuto mais próximo, mínimo de 60 segundos. Localmente o triplo dura 3 vezes
  esse tempo; esta multiplicação não está comprovada por captura oficial.
- Ranking por número de ataques. Textos pt_BR confirmam objeto para top 10,
  prémio específico para líder e golpe final e objeto aleatório para os restantes.
  O texto oficial prevê transferência para o penúltimo atacante quando o líder
  dá o golpe final; por decisão local, essa transferência não é aplicada.
  `winning_attacker_name` guarda sempre quem levou a vida a zero.
- Tipos de `worldboss_event_rewards`: 1 energia, 2 unidades de treino, confirmado
  em `UiWorldbossReward.setValue`. Quantidades oficiais: 30/8, 25/7, 20/6, 15/4, 10/2.
- Estados enviados: evento 1 em curso, 2 pendente por herói com ataque ainda aberto,
  4 pronto para recolher. Ataque 1 iniciado, 3 concluído; os estados internos 4
  (fechado) e 5 (cancelado) não são anunciados como ataques ativos.

## Balanceamento local, sem fórmula oficial confirmada

- Vida e duração global do vilão escolhidas no script.
- Usa o motor `Fight`, com atributos do herói, pets e mísseis. Cada iteração
  começa com a última vida global registada, lida sob lock no início do combate.
  Condição (`stamina`) = `ceil(vida global / 10)`; HP usa o valor global exato.
  Ao gerar o vilão, sorteiam-se três fatores independentes inteiros entre 95% e
  120%, guardados no evento. Força, cérebro e intuição do NPC são os atributos
  correspondentes do herói multiplicados por esses fatores, arredondados e com
  mínimo 1. O fator de força aplica-se à força do herói, sem somar a arma.
  Eventos ativos anteriores à migração recebem os fatores uma única vez, sob
  lock, na primeira leitura. As chances de crítico/esquiva são calculadas pelo
  motor a partir dos ratings. Estas regras são balanceamento local pedido pelo
  utilizador, não uma fórmula oficial confirmada. Dano global é a diferença de HP final do motor; inclui
  veneno, recuo e efeitos defensivos. Não há dano garantido por ataque.
- Por ataque efetivamente realizado: moedas `floor(max(10, nível × 20) / 2)` e XP
  `floor(max(5, nível × 10) / 2)`, acumulados e entregues apenas no resgate do evento.
  Redução local de 50% aplicada em 2026-10-07 também ao preview do próximo ataque.
  Para níveis válidos (≥1), equivale a moedas `nível × 10` e XP `nível × 5`.
  Acumulados anteriores e prémios já preparados não são recalculados.
- Escalões de energia/treino usam posição / número de participantes e o primeiro
  `max_pool_percentage` que cubra essa fração. Não confundir top 10 jogadores
  com top 10%: o objeto usa a posição absoluta <= 10, os escalões usam a fração.
  A fórmula dessa fração é inferência local, não verificada num HAR oficial.
- Cupões de energia/treino usam `UserVouchers`. Os textos oficiais referem dois
  dias de validade; o sistema local de cupões mantém a validade já existente,
  sem acrescentar expiração específica de world boss.
- Empates de número de ataques: menor ID de tentativa que contribua com batalhas realizadas, independentemente da ordem de conclusão.

## Exemplo de atributos e resolução do triplo

Suponha-se que um evento guardou os fatores de força 105%, cérebro 95% e intuição
120%. Para um herói com força 1 000, cérebro 2 000 e intuição 3 000:

| Atributo do vilão | Cálculo | Resultado |
|---|---|---:|
| Força | `max(1, round(1000 × 105 / 100))` | 1 050 |
| Cérebro (`criticalrating`) | `max(1, round(2000 × 95 / 100))` | 1 900 |
| Intuição (`dodgerating`) | `max(1, round(3000 × 120 / 100))` | 3 600 |
| HP, quando a vida global é 123 457 | Valor global exato | 123 457 |
| Condição (`stamina`) | `ceil(123457 / 10)` | 12 346 |

Outro herói aplica os mesmos fatores aos seus próprios atributos. Os valores
absolutos do NPC podem mudar entre heróis; os fatores não mudam entre ataques.
O perfil do herói vem de `Character::battleProfile('a')`, com equipamento,
atributos treinados, bónus aplicáveis e pet ativo. É obtido durante a resolução,
não congelado no momento de criação da tentativa.

O NPC tem nível igual ao herói, `weapondamage = 0`, `damage_normal = força` e
`damage_bonus = 0`. O perfil criado por `npcProfile()` não lhe atribui um pet
ativo nem habilidades próprias. O herói usa o pet e as munições do seu perfil.
`Fight::criticalChance` e `Fight::dodgeChance` calculam as chances dos dois lados
com base nos ratings comparados; não se sorteiam percentagens de chance de
crítico/esquiva entre 95% e 120%.

```text
HP inicial global                         1 000 000
Herói A, primeiro combate do triplo         -80 000 -> 920 000 (commit)
Herói B, combate intercalado               -120 000 -> 800 000 (commit)
Herói A, segundo combate                   começa com 800 000
Herói A, terceiro combate                  relê o HP confirmado antes de começar
```

Se B retirar os 920 000 restantes, B fica registado como autor do golpe final.
A mantém o primeiro combate, dano, moedas/XP e participação no ranking; não
realiza o segundo ou terceiro. Não recupera automaticamente o donut de início
nem o custo de conclusão que já tenha sido confirmado.

A separação é entre **batalhas completas**, não entre rounds. Durante os rounds
de uma batalha o evento está bloqueado e outro pedido aguarda. Os três combates
são resolvidos no mesmo pedido HTTP, com commits entre eles; não se espera pelo
fim de cada animação no navegador para resolver o seguinte. A intercalação é
possível, mas não é garantida: depende dos pedidos concorrentes e da aquisição
dos locks. Não existe uma pausa artificial para forçar outro jogador a atacar.

O dano global é `min(HP_inicial, max(0, HP_inicial - HP_final_do_motor))`.
O resultado visual `winner` da batalha e o autor do golpe final são conceitos
distintos: só a transição da vida global para zero encerra o evento por morte.
Batalhas sem dano também contam como realizadas e acumulam as moedas/XP locais.

## Recompensas em vigor (2026-10-05)

Estas regras foram definidas para este servidor pelo utilizador. Os catálogos
identificam as peças e as suas origens; não definem a probabilidade de 50% nem
toda a distribuição por ranking.

| Categoria | Top 1 | Posições 2–10 | Pet para beneficiários elegíveis |
|---|---|---|---|
| Normais (`default_event_stage*`) | Uma peça Ironman | Uma peça Anfíbio | 50%; exclui pets de temporadas e lendários |
| Festivos, como Natal/Páscoa/Halloween | Uma peça temática | Uma peça temática | 50%; apenas pets do tema elegível |
| Primal/Utopia/Dystopia associados ao passe | Uma peça do respetivo conjunto | Uma peça do respetivo conjunto | Nenhum, incluindo o autor do golpe final |

Top 10 e autor do golpe final recebem também energia e treinos em cupões.
O autor do golpe final é elegível mesmo fora do top 10 e recebe a peça da
faixa superior. Se estiver nas posições 2–10, recebe essa peça em vez da peça
da faixa inferior; não recebe dois equipamentos. A hipótese de pet continua
a depender da categoria: 50% nos normais/festivos, zero nos associados ao passe.

Os restantes participantes recebem apenas as moedas e XP acumuladas dos seus
ataques. Se o vilão fugir, todos recebem apenas esses acumulados. Não há épico
aleatório de participação ou de consolação.

### Equipamento e associações nas constantes

- Normais: `item_pattern` associa cinco peças `ironman1` e cinco `frogman1`
  à fonte `worldboss_event_general`. O backend usa `nonSetToSet` para selecionar
  as versões de conjunto: `mask`, `suit`, `cape`, `belt` e `boots`.
- Primal/Utopia/Dystopia: fontes `worldboss_event_primal_season_worldboss`,
  `worldboss_event_utopia_season_worldboss` e
  `worldboss_event_dystopia_season_worldboss`, com cinco peças cada.
- Carnaval e ficção científica: associações diretas à fonte do worldboss.
- Natal/Páscoa/Halloween: as constantes não têm uma lista direta de equipamento
  do worldboss. O backend usa as peças temáticas das fontes `shop_xmas`,
  `shop_easter` e `shop_halloween` como alternativa local, convertidas para
  versões de conjunto quando existir essa associação.
- Outros especiais sem lista temática usam as famílias gerais como alternativa
  local. Não se apresenta esta escolha como uma tabela oficial de recompensas.

É sorteada uma peça por faixa na criação do evento, guardada em
`reward_top_rank_item_identifier` e `reward_top_pool_item_identifier` e anunciada
no cliente. A escolha varia entre eventos, mas pode repetir por acaso; não é
sorteada uma peça diferente por participante dentro da mesma faixa do evento.

### Sorteio de pets e energia/treinos

A hipótese de pet é 50% por herói elegível: resultados 1–50 de um sorteio 1–100.
O catálogo normal exclui identificadores e fontes de temporada, qualidade >=4
e pets de covis lendários. Os festivos usam as fontes temáticas de
`Sidekick::official`, com as mesmas exclusões. Um tema sem pets elegíveis não
atribui pet. As fontes deste catálogo são locais ou derivadas; as constantes
não fornecem uma percentagem oficial de obtenção de pet.

Os escalões de energia/treinos mantêm o cálculo local posição / participantes:
30/8 até 10%, 25/7 até 30%, 20/6 até 50%, 15/4 até 70% e 10/2 até 100%.
A elegibilidade é verificada primeiro: estar fora do top 10 só permite receber
estes cupões se o herói tiver dado o golpe final. A exceção usa o escalão da sua
posição real, sem o promover artificialmente ao primeiro lugar.

### Prémios preparados e eventos anteriores

O resultado do sorteio de pet e os objetos gerados ficam guardados no prémio
único do evento/herói. Recarregar, voltar a ler o estado ou repetir o resgate
não volta a sortear. Prémios já preparados antes desta alteração são preservados.
Eventos anteriores ainda sem prémio preparado validam o equipamento anunciado
contra as famílias novas e escolhem uma peça elegível se o identificador antigo
não pertencer à família correta.

## Preparação e entrega do prémio, passo a passo

O ranking soma o número de batalhas por herói, por ordem decrescente. Em empate,
vence o menor ID de tentativa contribuinte; não há desempate por dano total ou
pelo horário em que o browser terminou a animação. Uma tentativa criada antes e
resolvida depois pode, portanto, ter precedência por ID num empate.

`ensureReward()` começa por procurar o prémio existente sob lock. Se existir,
devolve-o sem recalcular posição, equipamento ou pet. Caso contrário:

1. Calcula posição, número de participantes e moedas/XP acumuladas.
2. Considera vitória global apenas se `npc_hitpoints_current == 0`.
3. Aplica a elegibilidade: vitória, posição válida e top 10 ou golpe final.
4. Escolhe a faixa superior para top 1 ou golpe final; os outros elegíveis usam
   a faixa inferior. Só existe um equipamento por herói.
5. Valida a peça anunciada contra a família atual. Se um evento antigo anunciar
   uma peça incompatível, escolhe uma da família válida para esse prémio.
6. Sorteia o pet, se a categoria tiver pool elegível e o resultado 1–100 for <=50.
7. Cria os previews e grava o prémio com `claimed = 0`.

A geração usa `Character::createWorldbossRewardItem`, preservando o identificador
escolhido do conjunto. O equipamento tem qualidade 3 e usa o mesmo cálculo de
atributos da loja, através de `Character::shopItemStats`. Os atributos não vêm
de uma lista de valores fixos por template de worldboss.
O sorteio decide a peça; a probabilidade de pet é uma decisão separada. Não há
proteção contra peças repetidas em eventos consecutivos.

### Fórmulas partilhadas com a loja (2026-10-05)

Referências: [geração de itens](../shop_items/geracao_itens.md) e
[scaling de pets](../sidekicks/scaling.md). A implementação partilha os métodos
com a loja para evitar duas versões das mesmas fórmulas. O método de ferreiro
continua a existir para as suas outras utilizações; o worldboss deixou de o usar.

Para equipamento:

```text
L = max(1, nível do herói - random_int(0, 4))
qualidade = 3 (épico)
budget = ceil((L × 1.6 + 2 + L² × 0.004) × 2.3) + bónus item_flat do esconderijo
```

`required_level` e `item_level` recebem L. O orçamento é distribuído segundo
a regra épica da loja: 40% de probabilidade de 3 atributos, 60% de 4, com a ordem
dos atributos embaralhada. São condição, força, cérebro e intuição; o dano de
arma não entra nessa soma. Uma arma de tipo 6 usa `ceil(L × 1.035) + 2` de dano;
os conjuntos atuais de vilão são peças de tipos 1–5, sem dano de arma.

Exemplo sem bónus do esconderijo: L = 300 dá `ceil(842 × 2.3) = 1937` pontos.
O equipamento tem `buy_price = 0` por ser prémio; não há custo de compra ou
ocupação de slot da loja. O valor de venda segue a referência local em moedas,
com metade do preço calculado da loja e mínimo 4. A fórmula de atributos não
altera a família do conjunto nem sorteia uma raridade comum/rara.

Para pet:

```text
H = nível do herói no momento da preparação do preview
M = 1.2 para qualidade comum/default, 2.5 para épico, 4.2 para lendário
soma de referência = H × M
midpoints = round(soma × ratio)
ratios = condição 0.28, força 0.43, cérebro 0.18, intuição 0.11
cada base = random_int(max(1, round(midpoint × 0.85)),
                       max(limite inferior, round(midpoint × 1.15)))
```

A fórmula suporta qualidade lendária como a loja, mas o pool de recompensas de
vilão continua a excluí-la. Um pet épico para herói de nível 300 tem midpoints
210/323/135/83; cada base varia independentemente em ±15%, com arredondamento.
O preview representa o pet ao nível 1, tem dano de arma zero e não ocupa a
mochila. O crescimento posterior segue `Sidekick::statAtLevel`, sem alteração.

O nível usado é o da **preparação do prémio**, não o nível na criação do evento
nem o nível após creditar a XP do resgate. Depois de preparado, subir de nível,
recarregar ou repetir a leitura não muda os atributos sorteados. O pet normal,
o pet temático e o equipamento sazonal usam estas fórmulas nas categorias onde
já são elegíveis; a alteração não modifica as probabilidades/regras de entrega.

### Exemplos de elegibilidade e escalões

| Situação num evento derrotado | Equipamento | Energia/treino | Pet |
|---|---|---|---|
| Normal, posição 1 | Ironman | Escalão da posição | 50% do pool normal |
| Normal, posição 7 | Anfíbio | Escalão da posição | 50% do pool normal |
| Normal, posição 7 e golpe final | Ironman, uma peça | Escalão da posição 7 | 50% do pool normal |
| Normal, posição 18 e golpe final | Ironman | Escalão da posição 18 | 50% do pool normal |
| Normal, posição 18 sem golpe final | Nenhum | Nenhum | Nenhum |
| Natal, posição 18 e golpe final | Peça temática | Escalão da posição 18 | 50% do pool de Natal |
| Primal, posição 18 e golpe final | Peça Primal | Escalão da posição 18 | Nenhum |
| Evento expirado com vida restante | Nenhum | Nenhum | Nenhum |

Todos estes participantes recebem as suas moedas/XP acumuladas. Com 100
participantes, posição 5 usa 5% e recebe 30/8; posição 18 usa 18% e só recebe
25/7 se tiver dado o golpe final. Com 10 participantes, posição 1 usa 10% e
recebe 30/8, enquanto posição 10 usa 100% e recebe 10/2. Com um único
participante, posição 1/1 usa 100% e recebe 10/2. Estes exemplos refletem a fórmula
local efetiva; ser líder não força o escalão máximo.

### Categorias especiais sem associação completa

`eventTheme()` reconhece `xmas`, `easter`, `halloween`, `carnival`, `sci_fi_week`,
`video_games_week`, `olympia`, `worldcup` e `environmental_week` através dos
respetivos identificadores de evento. O equipamento prefere a associação
`worldboss_event_<identifier>`; só depois procura `shop_<tema>` e, se não houver
peças, usa as famílias normais.

Um especial sem tema reconhecido pode receber equipamento geral, mas **não
recebe automaticamente o pool normal de pets**. Esse pool é exclusivo dos
identificadores `default_event_stage*`. Sem tema/pool temático elegível, não há
pet. O normal pode incluir pets originários de outros eventos se passarem as
exclusões de temporada/lendário; não está restrito a pets com fonte worldboss.

### Resgate atómico e mochila

`claimWorldbossEventRewards` exige evento terminado, associação correta, nenhuma
tentativa ativa e prémio não resgatado. A entrega faz, na mesma transação:

1. Valida a propriedade/existência do preview de equipamento.
2. Se `discard_item = true`, apaga esse equipamento; caso contrário, coloca-o no
   primeiro slot livre entre `bag_item1_id` e `bag_item18_id`.
3. Converte o preview de pet em `sidekicks`, preservando identificador/qualidade,
   e apaga o objeto temporário de tipo 9.
4. Credita moedas/XP, cria os cupões de energia/treino e sincroniza o nível.
5. Marca `claimed = 1` e limpa o evento/contagem da personagem.

Se existir equipamento sem espaço e não houver descarte, a operação falha com
`errInventoryNoEmptySlot`: nenhum dos restantes prémios é entregue parcialmente.
Um prémio sem equipamento não precisa de um slot de mochila para estas moedas,
XP, cupões ou conversão do pet. O descarte não cancela esses outros prémios.

O pet usa a qualidade do template e as mesmas bases aleatórias da compra na
loja, escaladas pelo nível do herói. Os quatro atributos são sorteados na
preparação do prémio e guardados no preview. O resgate converte esses valores
em `stat_base_*` sem repetir o sorteio; não usa as antigas bases fixas 60/100/40/23.
Previews antigos com todos os atributos a zero recebem bases pela fórmula atual
no resgate. Equipamentos e previews já preparados com atributos não nulos não
são recalculados por esta alteração.

Os cupões são pessoais, com uma utilização e recompensa `quest_energy` ou
`training_sessions`. Receber o prémio cria o cupão; não adiciona diretamente
energia/treino ao saldo da personagem. O worldboss não acrescenta um prazo de
dois dias aos cupões, apesar de essa validade aparecer em textos oficiais.

## Golpe final e ataques simultâneos

O golpe final pertence ao herói cujo combate processado leva a vida global a
zero. Podem existir vários ataques em curso, mas as conclusões são resolvidas
uma de cada vez sob lock do evento. No triplo, cada combate confirma o dano e
liberta os locks antes do seguinte, permitindo que outros heróis ataquem pelo
meio. Cada iteração volta a ler a vida global atual. A ordem é a de processamento da conclusão,
não a de início do ataque nem a da animação no navegador.

`winning_attacker_name` guarda o nome desse herói, mesmo quando também lidera
o ranking. Não é enviado o prefixo `2_` nem transferido o prémio para o
penúltimo atacante. Esta é uma decisão local: o texto oficial do cliente prevê
essa transferência quando o líder dá também o golpe final.

Após a morte, ataques ainda pendentes são libertados sem novo combate ou dano.
Num triplo parcialmente resolvido, preservam-se as batalhas, dano e prémios já
confirmados; as iterações restantes não são realizadas.
O evento 22 foi corrigido para `winning_attacker_name = skel`, com base no
ataque 41 que retirou os 1 656 525 HP restantes. Os prémios históricos foram
preservados; não existe recálculo automático dos restantes eventos históricos.

## Persistência e concorrência

Operações bloqueiam personagem, utilizador e evento, usando transação ou savepoint.
A vida é partilhada; o lock do evento serializa os golpes e o encerramento. Ranking
lê ataques com leitura corrente, sem bloquear outras personagens. Não entrega
prémios globais a todos dentro do golpe final: prepara o prémio de cada herói na
leitura após fechar o ataque, protegido por lock e índice único.

Cancelamento liberta a atividade sem devolver o donut de início do triplo. Se o
vilão acabar antes de o herói resolver o ataque, esse ataque é libertado sem novo
combate ou cobrança de conclusão imediata. Iterações posteriores à morte do NPC
não são realizadas nem contam para ranking; não há reembolso do custo inicial.
Retries da conclusão não repetem dano, consumo de munição ou donuts. O progresso
parcial fica em `battle_ids`, dano e prémios acumulados, ainda com estado 1;
o ranking conta apenas as iterações realizadas. Depois da primeira iteração,
o ataque já não pode ser abortado. Uma falha posterior pode ser retomada sem
repetir as iterações confirmadas nem a cobrança inicial de conclusão.

Os handlers resolvem os combates no servidor antes de devolver a resposta;
a animação reproduz as batalhas guardadas e não define a ordem dos golpes.
Em testes com transação exterior, os savepoints preservam o rollback e os locks
só são libertados no fim dessa transação; esse modo não valida concorrência real.

Não permite mudar de evento depois de realizar ataques até resgatar o anterior.
Mochila cheia impede a entrega completa, incluindo moedas/XP/pet/cupões; libertar
um espaço e repetir, ou usar o descarte do equipamento. `discard_item` refere-se
ao objeto de equipamento; o pet e os restantes prémios continuam a ser entregues.
O pet é pré-visualizado como `items.type=9`, convertido em `sidekicks` no resgate.
Login, estado normal e respostas parciais incluem eventos, ataque, batalhas e os
objetos referenciados pelos prémios. O cliente pode ter cache de eventos antigos;
`worldboss_event_id` determina qual está associado ao herói.

## Modelo de dados e invariantes

### `worldboss_event`: uma linha global por ocorrência

| Campos | Significado |
|---|---|
| `id`, `identifier`, `npc_identifier` | Ocorrência, template e imagem/aparência do NPC |
| `stage`, `min_level`, `max_level` | Zona e intervalo de participação |
| `status`, `ts_end` | Estado persistido e prazo global |
| `npc_hitpoints_total`, `npc_hitpoints_current` | Vida de criação e vida global restante; armazenadas em BIGINT |
| `npc_strength_percent`, `npc_critical_percent`, `npc_dodge_percent` | Fatores fixos do evento, SMALLINT nullable para compatibilidade com eventos antigos |
| `attack_count` | Total de batalhas efetivamente realizadas por todos os heróis |
| `top_attacker_character_id`, `top_attacker_name`, `top_attacker_count` | Snapshot do líder, atualizado após cada batalha |
| `winning_attacker_name` | Nome do herói que levou a vida a zero; sem prefixo `2_` |
| `reward_top_rank_item_identifier`, `reward_top_pool_item_identifier` | Peças sorteadas por faixa na criação do evento |

O autor do golpe final é guardado como **nome**, não como um novo campo de ID.
`ensureReward()` compara esse nome com `character.name`. Não existe neste módulo
uma migração automática dessa referência quando o herói muda de nome.

### `worldboss_attack`: uma tentativa normal ou tripla

| Campos | Significado |
|---|---|
| `id`, `worldboss_event_id`, `character_id` | Tentativa e propriedade |
| `status` | Estado descrito no ciclo de vida |
| `ts_complete` | Prazo inicial; passa ao momento de resolução quando há progresso confirmado |
| `duration`, `duration_raw` | Ambos recebem a duração calculada × iterações; o segundo não guarda um tempo sem boosters separado |
| `iterations` | Número pedido durante o progresso; número efetivamente realizado quando termina antecipadamente |
| `battle_ids` | Lista JSON ordenada das batalhas realizadas; também é o marcador de progresso parcial |
| `battle_id` | Primeiro ID da lista, mantido para compatibilidade |
| `total_damage` | Soma do dano confirmado desta tentativa |
| `coin_reward`, `xp_reward` | Acumulados desta tentativa, ainda por entregar no prémio do evento |

No estado 1, as iterações efetivas são `JSON_LENGTH(battle_ids)`. Nos estados
3/4, usa-se `iterations`. As tentativas canceladas em estado 5 não contam.
O ranking agrega moedas/XP apenas de tentativas com batalhas realizadas.

### `worldboss_reward`: um prémio por evento e herói

| Campos | Significado |
|---|---|
| `worldboss_event_id`, `character_id` | Par protegido por `uq_worldboss_reward_owner` |
| `game_currency`, `xp` | Soma dos acumulados do participante |
| `item_id` | Preview do equipamento; zero quando não há |
| `sidekick_item_id` | Preview do pet em `items`; zero quando não há |
| `quest_energy`, `training_sessions` | Quantidades de cupões a criar no resgate |
| `rewards` | JSON dos extras de energia/treino; não substitui os campos de equipamento/pet |
| `claimed` | Proteção contra entrega duplicada |

`battle` guarda os dois perfis, vencedor e rounds com as aparências. `items`
guarda os previews e depois o equipamento entregue; `inventory` recebe o ID do
equipamento; `sidekicks` recebe o pet criado; `vouchers` recebe os cupões.

As três referências na personagem são `worldboss_event_id`,
`active_worldboss_attack_id` e `worldboss_event_attack_count`. Não representam
uma cópia independente da vida do vilão.

### Transações, locks e recuperação

A ordem base é personagem → utilizador → evento. A personagem serializa as
operações do mesmo herói; o utilizador protege o saldo; o evento serializa a
vida partilhada. O ranking faz leitura corrente dos ataques com `FOR UPDATE`
e consulta os nomes sem bloquear outras personagens.

Uma batalha confirma em conjunto a linha `battle`, o progresso do ataque,
o consumo de munição, a vida/ranking do evento e a contagem da personagem.
Se falhar antes do commit, esses efeitos são revertidos. No triplo, um commit
anterior permanece válido se uma iteração posterior falhar; o pedido seguinte
continua a partir de `battle_ids`. O débito de conclusão instantânea é confirmado
com a primeira batalha e `ts_complete` deixa de estar no futuro, evitando nova
cobrança ao retomar. O débito premium usa UPDATE condicionado a saldo suficiente.

Quando outro pedido do mesmo herói fecha a tentativa entre commits, a resolução
em curso conserva o ID que já estava a tratar (`resolvingAid`); não passa a
resolver uma eventual nova tentativa desse herói.

Uma transação exterior é preservada por savepoints. Estes não libertam os locks
como um commit independente: quem envolver a resolução inteira numa transação
exterior também impede a intercalação durante esse período. Os handlers normais
não envolvem os três combates numa transação exterior.

## Diagnóstico e limites conhecidos

| Sintoma | Verificação recomendada |
|---|---|
| Vilão não aparece | Template/NPC e assets; zona desbloqueada; limites de nível; estado/prazo; distinção entre evento global e associação do herói |
| Não permite triplo | HP atual comparado com `ceil(HP total × 0.1)`; iterações enviadas; saldo e atividades incompatíveis |
| Vida parece antiga durante animação | Ver `profile_b_stats.hitpoints` da batalha; a animação reproduz um snapshot já confirmado, não a vida em tempo real durante os rounds |
| Atributos do vilão parecem iguais | Comparar fatores guardados e atributos do herói; repetir contra o mesmo herói usa fatores iguais por desenho |
| Outra pessoa aparece como golpe final | Ver sequência de dano confirmado e `winning_attacker_name`; ordem de início/visualização não decide o autor |
| Mesma peça em vários prémios | Dentro da mesma faixa/evento é esperado; entre eventos pode repetir por acaso; verificar associação temática e identificadores persistidos |
| Pet não aparece | Categoria, elegibilidade, pool filtrado, sorteio persistido e presença do preview em `items`; temporadas do passe nunca dão pet |
| Energia/treino não subiram | Procurar cupões pessoais; o resgate do prémio não equivale ao uso do cupão |
| Prémio não é mostrado | Evento terminado, tentativa ainda ativa, associação, `claimed` e inclusão dos objetos referenciados na resposta |
| Resgate bloqueado com mochila cheia | Libertar um slot ou descartar equipamento; manter o prémio persistido para repetir sem novo sorteio |

Não existe neste módulo agendamento automático de vilões, ligação automática ao
passe, distribuição oficial de HP por zona, reembolso de iterações interrompidas
ou recálculo global de prémios antigos. Os nomes de eventos históricos não são
corrigidos automaticamente. Os scripts recusam um evento ativo na mesma zona,
mas essa proteção é da entrada operacional: `Worldboss::create()` isoladamente
não faz essa consulta e o schema não impõe uma unicidade de evento ativo por zona.

No script de criação, a linha `Prémios:` chama `defaultRewards()` para mostrar um
sorteio ilustrativo; `create()` faz o sorteio persistido novamente. A peça dessa
linha ou de `--dry-run` não é uma garantia da peça efetivamente guardada. Os
campos do evento na BD/resposta são a referência após a criação.

O bloqueio do evento pode fazer outro pedido aguardar durante uma batalha.
A separação por iteração permite intercalação, mas não foi validada nesta sessão
com dois pedidos HTTP simultâneos nem com teste de carga. Os testes MySQL com
rollback validam progresso e leitura após intervenção determinística, mantendo
uma transação exterior; não provam libertação real de locks entre pedidos.

## Validação

```bash
php scripts/testing/test_worldboss.php --offline
php scripts/testing/test_worldboss.php --hero="skel"
```

Offline: catálogos/NPCs, custos/duração, limiar de vida, escalões locais,
cinco peças por família, exclusões de pets, limite 50/51 e elegibilidade do
golpe final fora do top 10. A regra de probabilidade é verificada diretamente;
não se tenta provar 50% com uma amostra aleatória de eventos.

MySQL com rollback: estados, custos, triplo, combate, retries, cancelamento,
expiração, ranking entre dois heróis, vida partilhada, autor do golpe final,
equipamento de temporada sem pet, previews, mochila cheia, resgate único,
cupões, login e estado normal. A entrega de pet usa uma fixture determinística
para testar a conversão do preview em sidekick sem depender do sorteio.
Não deixa eventos de teste nem consumos persistidos.

Após a alteração das recompensas, os testes offline e MySQL com rollback
`--hero="skel"` passaram, assim como `php -l` nos PHP alterados. A confirmação
visual no navegador continua pendente. Estes testes não são uma prova de carga
com pedidos concorrentes reais nem de comportamento do cliente no navegador.

A documentação anterior registava uma regressão das histórias com
`test_story_dungeon.php --hero="skel"` aprovada; não foi repetida nesta alteração
de recompensas.

## Atributos no combate (2026-10-05)

No ramo worldboss de `PanelBattle.show`, apenas os atributos do herói
(`statsA`) são mostrados. Os atributos do vilão (`statsB`) ficam ocultos,
conforme a correção do pedido pelo utilizador. O preenchimento dos perfis e
os cálculos do combate mantêm-se. Sintaxe validada; confirmação visual pendente.


## Animação de combate (2026-10-05)

As capturas `a.webm` (missão) e `b.webm` (worldboss) mostram efeitos
sobrepostos e progressão mais rápida no segundo caso. O cliente usa as mesmas
funções e durações de animação nos dois tipos de combate.

O percurso de worldboss pode chamar `PanelBattle.show()` quando o painel já
está aberto. Cada chamada reiniciava a visualização sem cancelar as timelines
anteriores. Foi adicionada uma proteção por par ataque/batalha: repetir `show`
para esse par preserva os rounds em curso. Uma batalha seguinte do triplo ou
um novo ataque inicia normalmente; `reset` limpa a proteção. Sem batalha
pendente, não volta a iniciar a batalha anterior. O painel base continua a
receber `show`; missões e duelos mantêm o percurso anterior.

Validação: `node --check server-laravel/public/js/HeroZero.min.js` e
`node scripts/testing/test_worldboss_animation_client.js` passaram. O teste
executa os métodos extraídos do bundle com gráficos simulados, reproduz duas
renderizações antes da proteção e verifica chamadas repetidas, triplo, novo
ataque, ausência de batalha pendente, reset e percurso de missões/duelos.
O utilizador confirmou que a animação ficou correta após esta alteração;
não foram alteradas as durações dos golpes.

Verificação dos atributos e triplo: `test_worldboss.php` testa os fatores offline
e, em MySQL com rollback, intervém na vida entre iterações e simula a morte por
outro herói. Não é um teste com dois pedidos HTTP simultâneos.

Validação das fórmulas de prémio: `test_worldboss.php` verifica orçamento e
número de atributos para várias raridades/níveis, incluindo bónus de itens;
em MySQL com rollback verifica o equipamento real, os limites das bases de pet
e a igualdade entre preview e bases entregues. `test_shop_purchase.php` passou
com PDO simulado após a extração dos métodos partilhados.
