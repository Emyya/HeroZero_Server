# Testes dos troféus de guilda

Scripts que validam o sistema de troféus/artefactos (documentação completa em
`docs/trofeus/implementacao.md` — secção 9 tem o inventário exaustivo de
ficheiros/funções para debug). **Todos correm com ROLLBACK**: alteram a BD
dentro de transação e revertem no fim.

Pré-requisitos: MySQL docker ligado; correr da raiz do repo:

```bash
php scripts/testing/trofeus/test_trophies.php
php scripts/testing/trofeus/test_battle_trophies.php
php scripts/testing/trofeus/test_effects.php
```

## `test_trophies.php` — regras base + releaseArtifact

Cenários (usa as guildas 1=sonnet46 e 2=oxAlpha, tudo revertido no fim):

1. **Roubo** — vencedor SEM slot livre → `null`; com slot livre → rouba 1
   aleatório ao perdedor, contadores `artifacts_won/lost` atualizados;
2. **Emissão** — ambas vazias, vitória por ATAQUE → entra troféu novo do pool;
   vencedor cheio → `null`;
3. **releaseArtifact** (action real) — cooldown ativo →
   `errReleaseArtifactActiveCooldown`; membro rank>2 →
   `errReleaseArtifactNoPermission`; sucesso → debita 40 donuts, remove o id,
   contadores certos.

Saída esperada: linhas `evento: {...}` / `GameError: ...` coerentes com os
comentários, e no fim `*** ROLLBACK feito — estado real intacto ***`.

## `test_battle_trophies.php` — batalha guilda-vs-guilda ponta-a-ponta

Monta uma `guild_battle` FICTÍCIA dentro da transação (atacante sonnet46 com
slots livres vs defensor oxAlpha com 1 troféu) e chama
`GuildBattles::simulateGuildBattle()` real. Verifica:

- `attacker_rewards`/`defender_rewards` com `artifact_id` (+`artifact_stolen`
  no lado vencedor quando é roubo — o resultado da luta é aleatório, pode ser
  o defensor a ganhar e a roubar);
- `finished_guild_battle_attack_id` apontado nos personagens;
- estado das duas guildas antes/depois (ids, current/max, won/lost).

## `test_effects.php` — efeitos na gameplay (~29 checks)

Define cenários fixos dentro da transação — guilda 1 = `[4,11,21,41]`
(1 troféu de cada tipo), guilda 2 = `[32]` (1 Energia) — e verifica:

- **A)** `effects()` agregados (`n1..n4`, `reduction`), custo de duelo (18),
  multiplicador pvp (1.25), máximos efetivos (+20/+5/+20/+2);
- **B)** `overlayCharacter` emite os `max_*` e `duel_stamina_cost` já com
  bónus (base lida da coluna real do char);
- **C)** regeneração: stamina 115 (acima da base 100) é MANTIDA; 99 enche até
  ao teto efetivo 120;
- **D)** refill premium `buyDuelStamina` (action real) enche até ao efetivo;
- **E)** penalização de skills na batalha: stats dos perfis gravados = raw ×
  fator por lado (0.56 para a guilda com redução 0.44; 0.90 para a outra);
- **F)** invariante HP dos perfis gravados: `hitpoints` ∈ {0 (derrotado),
  stamina escalada ×10 (sobrevivente)} — nada entre os dois (sem carryover;
  regressão do bug "lutou com 0 hp" da batalha #15).

Saída esperada: `FALHAS: 0`.

## Notas de debug

- Se rebentar com "Class HeroZero\GameError not found": os scripts já fazem
  `require_once Protocol.php` — não remover essa linha.
- O cache de `GuildArtifacts::effects()` dura o request/processo inteiro: os
  scripts fazem os UPDATEs de cenário ANTES da primeira chamada.
- IDs usados: chars 25295/25296 (sonnet46), 25297 (oxAlpha). Se essas contas
  mudarem de guilda, ajustar no topo de cada script.
