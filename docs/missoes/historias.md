# Histórias das zonas — Story Dungeon

As cinco etapas de cada história aparecem no painel de missões: cumprir uma meta,
receber XP/equipamento e avançar para a etapa seguinte. Este sistema é distinto das
metas genéricas (`goals`) e das chamadas heroicas (`herobook`).

## Fontes e implementação

- `missoes.har`: captura do servidor **local**, com ações de concluir, resgatar e
  iniciar a etapa seguinte. Mostra o problema anterior (pontos sempre a zero,
  prémios vazios); não prova as regras de recompensa do backend oficial.
- `HeroZero.min.js`: `StoryDungeonStep`, `DialogStoryDungeonOutcome`,
  `UiStoryDungeonBriefingContent` e `Reward` confirmam os campos, estados,
  fórmula do preço e fluxo do cliente.
- `constants_json.data` (zlib): `story_dungeons` contém 14 histórias de cinco
  etapas; `story_dungeon_items` define objetos, referências e probabilidades.
- Textos `story_dungeon/<identifier>/<step>/progress` confirmam os tipos.
- Implementação: `app/HeroZero/StoryDungeon.php`, produtores de atividades em
  `Character.php`, `actions/storyDungeon.php` e `actions/claimDuelRewards.php`.

## Progresso

| Tipo | Atividade ligada |
|---|---|
| 1 | Resgatar uma missão de tempo |
| 4 | Energia efetivamente debitada ao iniciar uma missão |
| 5 | Resgatar um duelo disputado, com qualquer resultado |
| 6 | Resgatar um duelo vencido pelo atacante |
| 10 | Resgatar uma missão com o objeto da etapa ativa; perder a luta remove o objeto |
| 11 | Lavagem de item aceite |
| 12 | Renovação manual do brechó normal (`shop_index=1`), gratuita ou paga |
| 14 | Unidades de treino efetivamente consumidas ao iniciar um treino |
| 15 | Concluir a missão especial indicada em `reference`; ver limite abaixo |
| 16 | Resgatar uma missão de luta ganha |

O progresso começa ao iniciar a etapa, não conta atividades anteriores e fica
limitado à meta. Missões e duelos contam no resgate; energia e unidades contam no
consumo. Este momento de contagem é a implementação local, não uma confirmação do
momento exato usado pelo servidor oficial.

Etapas com objetos usam `story_dungeon_item` nas recompensas da missão. O sorteio
ocorre ao gerar cada missão compatível, usando a chance do catálogo e a referência
`*stageN_`/`*stageN`. Ao iniciar a etapa também são atualizadas as missões ainda
não iniciadas já oferecidas. Só o objeto correspondente à etapa ativa conta;
este objeto de história não ocupa um slot de equipamento na mochila.

## Estados e sequência

- `2`: em curso.
- `3`: concluída, pronta a resgatar (é este estado que o cliente usa para mostrar
  a conclusão e os prémios).
- `4`: prémio processado; a resposta mantém este objeto para o cliente disparar
  `startNewStoryDungeonStep` da etapa seguinte.

Validam-se catálogo, nível mínimo, etapa anterior e história anterior
(`after_story_dungeon`, concluída no passo 5). Não se pode substituir uma etapa
em curso, saltar etapas nem resgatar duas vezes. As zonas continuam a abrir pelo
nível (`stages.min_level`), sem o antigo desbloqueio artificial em cada resgate.

O estado continua em `character.story_dungeon_state`; não há alteração de schema.
Estados antigos prontos a resgatar com `status=4` e ausentes de `completed` são
normalizados para `3`, com preview de prémio. Não se apagam etapas já concluídas
nem se compensam automaticamente resgates antigos de 100 moedas.

## Prémios e conclusão instantânea

Cada etapa usa `reward_xp`, `reward_item_identifier` e
`reward_item_level_offset` do catálogo. O item é criado como preview, enviado em
`items` antes de o cliente resolver o ID e entregue à mochila apenas no resgate.
Mochila cheia devolve `errInventoryNoEmptySlot` e conserva XP/prémio por resgatar.
`discard_item=true` descarta **só o equipamento**; o XP é entregue na mesma.

A geração dos atributos usa o gerador local de equipamento existente, com nível
do item acrescido do offset e nível requerido igual ao do herói na criação.
Os mísseis de prémio têm 100 cargas e usam o fator de dano 3,9 do catálogo.
A fórmula completa de atributos, o nível requerido e as 100 cargas são decisões
locais; não foram validados contra uma captura de prémios oficiais.

Preço do cliente:

```
progress = floor(points_collected / unlock_points_needed * 100)
cost = ceil((100 - progress) / 100 * skip_price)
```

No passo 5 o progresso para este cálculo é zero. O saldo é debitado uma só vez,
na mesma transação que conclui a etapa. Exemplo: história 1, etapa 2 custa 9
quando está a zero; etapa 1 a 4/5 custa 1.

## Chefe (etapa 5)

O ataque normal usa `Fight` com os atributos do NPC definidos no catálogo e os
atributos atuais do herói. Guarda uma linha real em `battle` e envia `battle_ids`,
`battle`/`battles` e `character.active_story_dungeon_attack_id`, também no login.
Vitória deixa o prémio por resgatar; derrota permite fechar o diálogo sem prémio.

O cooldown usa `GameTime`, `story_dungeon_attack_cooldown=3600` e o salto pedido
por `finish_cooldown` custa `story_dungeon_attack_premium_currency=2`.
A conclusão instantânea premium garante a vitória e devolve uma batalha válida.
Não se permite iniciar um novo ataque enquanto a batalha anterior aguarda o
fecho/resgate, nem atacar com missão, duelo ou ataque de chefe mundial ativos.

## Transações e respostas

A preparação, progresso e resgate usam locks do herói e utilizador. Os produtores
ligados partilham a transação com os contadores: uma falha no contador reverte o
consumo/início/resgate. Transações existentes são preservadas com savepoints.
Não se executa DDL dentro destes fluxos.

`Live::attachStoryDungeonState` cobre login e estado normal. O Router acrescenta
o estado às respostas parciais do próprio herói, incluindo os itens e batalhas
referenciados antes da apresentação pelo cliente.

## Validação e limites

```bash
php scripts/testing/test_story_dungeon.php --offline
php scripts/testing/test_story_dungeon_mock.php
php scripts/testing/test_story_dungeon.php --hero="Nome do herói"
```

O último exige MySQL e herói nível 9+, prepara estado e recursos **só dentro de
uma transação com rollback**. Não é um reset persistente.

Validação nesta sessão: sintaxe PHP, catálogo/fórmulas offline e PDO simulado
passaram. O mock cobre progresso, fontes reais de missão/energia, chances de
objetos, sequência, saldo, preview, mochila cheia, descarte, duplicados,
rollback/savepoints e combate/cooldown do chefe. MySQL não estava acessível
(PDO 2002); integração real e confirmação visual no cliente ficaram pendentes.

**Limites existentes:** repetição das histórias (`repeat`) não implementada.
A história 7, etapa 4 (tipo 15, `reference=dungeon4`) depende de missões especiais:
as ações destas ainda apontam para `stateEcho.php`, sem conclusão real. O serviço
aceita `StoryDungeon::record(..., SPECIAL_DUNGEON, ..., 'dungeon4')` para uma futura
conclusão validada, mas não inventa esse evento. O progresso normal desta etapa
continua pendente dessa funcionalidade; a conclusão premium funciona.
