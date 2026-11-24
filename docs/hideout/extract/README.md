# Extract — análise do cliente (`HeroZero.min.js`)

> Análise do `server-laravel/public/js/HeroZero.min.js` (build HTML5 257) e das
> respostas reais capturadas do servidor oficial (`server-laravel/data/*.json`),
> focada no **hideout/esconderijo**.
>
> ⚠️ Esta análise é a fonte de verdade para implementar o hideout — o
> `implementar_hideout.md` na pasta acima foi escrito ANTES desta análise ao JS
> e está desatualizado; ignorá-lo.

## Ficheiros

| Ficheiro | Conteúdo |
|---|---|
| [acoes_protocolo.md](acoes_protocolo.md) | **As 39 ações HTTP** com params verbatim + tratamento de erros; mensagens socket (`syncHideoutAttack`, `syncGuildSynergy`); padrões comuns (assinatura md5, updateData, convenções de nomes). Começar por aqui. |
| [modelo_dados.md](modelo_dados.md) | Classes DO*/C* e os campos que cada objeto tem de ter: `hideout` (grid `room_slot_L_S`, workers, níveis), `hideout_room` (status/produção), battle/history/opponent; fórmulas derivadas no cliente (produção, maxResource, durações, instant-finish). |
| [constantes.md](constantes.md) | As 6 chaves master data (`hideout_rooms`, `hideout_units`, `hideout_expansion`, `hideout_room_upgrade`, `hideout_generator_upgrades`, `hideout_room_blacksmith_items`) + ~50 constantes escalares, com campos e regras de uso. |
| [combate.md](combate.md) | Fluxo de ataque/oponente/cooldown, tutorial fight, histórico, leaderboard, rewards; batalha 100% server-side (cliente só reproduz `rounds`). |
| [salas_especiais.md](salas_especiais.md) | Exchange, gym, blacksmith, item improvement (slots+raridade), salas de guilda/sinergia, workers premium, boosters, unlock, slots, bónus passivos. |
| [respostas_capturadas.md](respostas_capturadas.md) | Respostas REAIS do servidor oficial (HAR em `server-laravel/data/*.json`) — prova do formato dos envelopes, deltas parciais, `hideout_battle`, profiles e rounds. |

## Regras locais implementadas

Os patamares diários de 5/10/20 ataques usam balanceamento local e estão
documentados em [patamares_combates.md](../patamares_combates.md).
Esta extração descreve o cliente oficial; o documento dos patamares descreve
a contagem, prémios, migração e testes atuais do servidor.

## Ordem de leitura sugerida (para implementar)

1. `respostas_capturadas.md` — o formato exato que temos de reproduzir (prova real).
2. `acoes_protocolo.md` — o contrato completo pedido↔resposta↔erros.
3. `modelo_dados.md` — o estado que o servidor mantém e envia (e o que o
   cliente recalcula sozinho: enviar bases, não valores finais!).
4. `constantes.md` — a master data a servir no boot.
5. `salas_especiais.md` + `combate.md` — lógica de negócio por feature.

## Factos críticos (resumo de 30 segundos)

- Envelope `{data:{...}, error:""}` com **deltas parciais**; chaves hideout
  aplicadas em qualquer resposta: `hideout`, `hideout_room(s)`,
  `hideout_opponent`, `hideout_battle` (só se `id==active_battle_id`),
  `missed_hideout_attacks`, `guild_gem_production_level`.
- Assinatura `md5(action+"GN1al351"+user_id)`; params automáticos
  `user_id/user_session_id/client_version/build_number/rct/keep_active`.
- Nomes de params **inconsistentes de propósito**: `hideoutRoomId` vs
  `hideout_room_id`; typos a respeitar: `expacted_glue_reward`,
  `errstartHideoutBattleInvalidAttackerCount`, `wm.OPPOENT_REFRESH`,
  erros `*NotJetPossible`.
- Grid 12×5 codificado nas chaves `room_slot_<L>_<S>`:
  ausente=bloqueado, -1=livre bloqueado... ver tabela em `modelo_dados.md`
  (≤-2 = desbloqueio em curso com `-ts_fim` embutido).
- Batalha resolvida toda no servidor; cliente reproduz `rounds`
  (`{a,d,r,v}`; r∈1..6).
- Rewards creditadas apenas em `collectHideoutFightRewards` (no fecho do diálogo).
- `productionCount` muda de significado por sala (quantidade / item id /
  índice de raridade) — tabela crítica em `salas_especiais.md`.

## Metodologia

O ficheiro minificado (15,7 MB) foi analisado por: inventário de string literals
→ clustering por offsets → extração de spans → mapa de nomes minificados via
`__name__`/`__className__` → extração dirigida por tópico (5 agentes em
paralelo) → **verificação amostral de citações contra o ficheiro original**
(todas as verificações feitas bateram certo, incluindo os typos do jogo).

Spans extraídos reutilizáveis em `/tmp/hz_hideout/` (208 trechos + index.json).
