# Batalhas Guilda-vs-Guilda (atacar outro time)

> Documentação completa da implementação no servidor Laravel, baseada no
> servidor antigo (`herodocker/herozero/server/class/GuildsBattle.php`).
> Atualizado em 2026-08-23. Bugs relacionados: BUG-000057, 000058, 000061, 000063.

---

## 1. Fluxo completo

```
1. getGuildBattleList            → lista de guildas atacáveis
2. checkAttackGuild              → validações antes de agendar
3. attackGuild                   → agenda a batalha (status=1) + pendentes nas 2 guildas
4. joinGuildBattle               → membros registam-se para ATAQUE ou DEFESA
5. ts_attack chega               → resolução LAZY no próximo pedido de estado
                                   (Live::attachGuildBattleState →
                                    GuildBattles::resolveDueGuildBattles)
6. finished_guild_battle_attack / _defense + guild_battle_rewards na resposta
   → cliente abre o diálogo de resultado (Cj) e o botão "Ver" abre a animação
7. claimGuildBattleReward        → paga moedas ao membro, apaga linha,
                                   limpa o ponteiro no personagem
```

## 2. Estados (`guild_battle.status`)

| Status | Significado |
|---|---|
| 1 | Agendada / pendente (igual ao servidor antigo; **não** é 2 — esse é o convenção do dungeon) |
| 2 | NÃO USAR (legado da 1ª implementação; migrado) |
| 3 | Resolvida |
| 4 | Cancelada (`abortGuildBattle`) |

## 3. Ações do servidor (`actions/guildBattle.php`)

### attackGuild
- **Params:** `guild_id` (defensor), `time` (1–5)
- **Validações** (do `checkAttackGuild` antigo):
  - personagem tem guilda; alvo existe e ≠ própria guilda (`errCreateAlreadyFought`)
  - alvo já está a ser atacado → `errCreateAlreadyAttacked`
  - alvo já ataca outra guilda → `errCreateAlreadyAttacking`
  - a nossa guilda já tem ataque pendente → `errCreateAlreadyAttacking`
- **Agendamento:** slots `[1=>12, 2=>16, 3=>18, 4=>20, 5=>22]` na hora do
  relógio de jogo (**Europe/Lisbon**, o mesmo fuso que a topbar mostra).
  Janela de preparação de 10h: se `hora_atual >= h − 10`, marca para amanhã.
- **Efeito:** INSERT em `guild_battle` (status=1, `attacker_character_ids=[iniciador]`)
  + `guild.pending_guild_battle_attack_id = id` (atacante)
  + `guild.pending_guild_battle_defense_id = id` (defensor)
- **Resposta:** `Live::accountState()` (inclui `pending_guild_battle_attack`
  e `guild_battle_guilds` via attachGuildBattleState)

### joinGuildBattle
- **Param OFICIAL:** `attack` = `'true'|'false'` (string bool!)
  - true → acrescenta o personagem a `attacker_character_ids`
  - false → acrescenta a `defender_character_ids`
- Resolve a batalha pelo pendente DA PRÓPRIA guilda (attack_id ou defense_id)
- Erros: `errInvalidStateToPendingBattle`, `errJoinGuildBattleInvalidGuildBattle`,
  `errJoinGuildBattleAlreadyFought` (já tem reward row desta batalha),
  `errAddCharacterIdAlreadyJoined` (já está no outro lado)
- **Resposta:** accountState

### claimGuildBattleReward
- **Params:** `guild_battle_id`, `discard_item`
- Procura reward row por `(guild_battle_id, character_id)` — sem filtro de type
- Paga `game_currency`, DELETE da row, e limpa o ponteiro:
  - type 1 → `finished_guild_battle_attack_id = 0`
  - type 2 → `finished_guild_battle_defense_id = 0`
- **Resposta:** accountState

## 4. Resolução automática (`GuildBattles.php`)

### resolveDueGuildBattles()
Chamado por `Live::attachGuildBattleState` em TODA resposta de estado completo.
Seleciona `status=1 AND ts_attack <= now` e chama `simulateGuildBattle`.

### simulateGuildBattle(battleId)
1. **Rosters:** apenas os REGISTADOS em cada lado (`*_character_ids`),
   carregados com `battleProfile('a')` **+ `battleAppearance()`** (name,
   gender, aparencia, equipamento — sem isto o cliente crasha com
   "unknown field: name"), ordenados pela **FORMAÇÃO de cada guilda**
   (`guild.guild_battle_tactics_attack_order` para os atacantes,
   `_defense_order` para os defensores — constants `guild_battle_tactics`):
   | Valor | Nome | Ordem |
   |---|---|---|
   | 1 | Na sequência | nível menor → maior |
   | 3 | Fortaleza | nível maior → menor |
   | 5 | Confusão | aleatória (`shuffle`) |

   Empates de nível resolvem-se pelos pontos de habilidade
   (`stat_base+stat_bought+stat_trained` dos 4 stats), na direção da
   formação. As posições servidas ao cliente (`position` em
   `guildSideProfiles`) seguem esta ordem automaticamente
2. **Lutas sequenciais** (`Fight`, guildFight=true): cada confronto começa
   com ambos os heróis à vida cheia; quem perde dá lugar ao seguinte do seu lado.
   No cliente, `GuildBattle.endHitPoints` reinicia o cálculo ao mudar de adversário,
   incluindo no histórico e na reprodução parcial. O dano de confrontos anteriores
   não é descontado no confronto atual; o resultado visual nunca fica negativo.
3. **Vencedor:** a guilda cujo roster esvaziar primeiro perde
   (roster vazio = derrota imediata / walkover)

   > **Walkover (nenhum defensor registado):** `rounds=[]` fica gravado na BD.
   > Em vez de enviar o diálogo de batalha ao cliente (que crashava ao tentar
   > iterar `rounds[0]`), `Live::attachGuildBattleState` deteta `rounds=[]` e
   > processa o prémio silenciosamente: credita as moedas, apaga a reward e
   > limpa `finished_guild_battle_attack_id`. O cliente não recebe
   > `finished_guild_battle_attack` nem abre diálogo — sem crash (BUG-000063).
4. **Honra das guildas** (fórmulas antigas ×10):
   ```
   f = (hW > hL) ? 1−(hL/hW)³ : (hL==0 ? 1 : −(1−(hW/hL)³))
   win  = round((100 − f×100) × 10)          // ~1000 com honras iguais
   lose = −round(min(0.75 × win, 0.05 × hL))
   ```
5. **Moedas por membro VENCEDOR:** `floor(rand(0.70–0.80) × (nível+10)²)`
   arredondado às dezenas; perdedores recebem 0
   - rows em `guild_battle_rewards`: type **1** = atacante, type **2** = defensor
6. **Ponteiros:** grava `finished_guild_battle_attack_id` nos atacantes e
   `finished_guild_battle_defense_id` nos defensores (gatilho do diálogo)
7. Grava rounds + perfis dos dois lados + `guild_winner_id` +
   `attacker_rewards`/`defender_rewards` (`{"honor": N}`); status=3;
   limpa os pendentes das duas guildas

## 5. O que o cliente espera (contrato wire)

### DOGuildBattle (chaves obrigatórias — getString/getInt lançam exceção se faltarem)

**Pendente (status=1)** — shape limitada igual a `getDataForAttacker/Defender`:
```json
{"id":1,"status":1,"ts_attack":0,"battle_time":3,
 "guild_attacker_id":1,"guild_defender_id":2,
 "character_ids":"[25295]"}
```

**Terminada (status=3)** — acrescenta:
```json
{"guild_winner_id":2,
 "attacker_character_profiles":"{...}",
 "defender_character_profiles":"{...}",
 "rounds":"[{\"a\":\"25295\",\"d\":\"25296\",\"r\":2,\"v\":2299}]",
 "attacker_rewards":"{\"honor\":1000}",
 "defender_rewards":"{\"honor\":-50}"}
```

- `character_ids`, `*_profiles`, `rounds`, `*_rewards` são **STRINGS JSON**
  (o cliente faz fromJsonString/JSON.parse)

### Outras chaves da resposta

| Chave | Conteúdo | Quem consome |
|---|---|---|
| `pending_guild_battle_attack/_defense` | shape acima | painel da guilda (agendado/defesa) |
| `finished_guild_battle_attack/_defense` | shape terminada | diálogo de resultado (Cj) |
| `guild_battle_rewards[]` | `{id, guild_battle_id, character_id, game_currency, item_id, status, type}` | prémio individual no diálogo |
| `guild_battle_guilds[]` | shapes de guilda (adversárias) | resolver `get_attacker()/get_defender()/get_name()` — **sem isto o jogo crasha no login** |

### Gatilho do diálogo de resultado (xf.checkForFinishedGuildBattles)

```
se character.finished_guild_battle_attack_id != 0
   E finishedAttack==null OU finishedAttack.id != esse valor
→ syncGame() (força re-sincronização)
senão, estando no painel da guilda e não mostrado ainda:
→ showDialog(DialogGuildBattleResult) para o id mais antigo
```

⚠️ A recompensa devolvida tem de ser da MESMA batalha apontada no
personagem — ids diferentes = loop de sync infinito sem dialogo (BUG-000061).

### Perfis dos lutadores (`*_character_profiles`)

Mapa `"charId" → {...}`; cada entrada precisa de:
`profile` (= charId como string), `name`, `gender`, `level`, `position`,
`stamina`, `total_stamina`, `strength`, `criticalrating`, `dodgerating`,
`weapondamage`, `hitpoints`, `damage_bonus` (tooltip faz getInt e lança se
faltar), aparencia `appearance_*`, equipamento por identifier, `sidekick`.
Sem `name` → crash "unknown field: name" ao ver a luta.

Os campos visuais `missiles` e `missiles1`–`missiles4` usam identificadores de
item ou `null`. Não enviar a lista interna de munições do simulador nestes
campos: `[]` passa pela guarda de nulidade do cliente e provoca um lookup de
item inexistente em `Hm.setMissile` (`get_displayOptions`). A gravação de novos
perfis normaliza a ausência; login, sincronização e histórico normalizam também
os snapshots antigos em leitura, sem alterar resultados ou recompensas.

Validação: `docker compose exec -T game php /workspace/scripts/testing/test_guild_battle_appearance.php --mysql`.

## 6. Fuso horário do agendamento (importante)

Os slots são gravados na hora **Europe/Lisbon** — o mesmo fuso que a topbar e
os detalhes mostram ao jogador. Já tentámos usar UTC replicando
`Uj.getGuildBattleAttackTimestamp` mas o sinal do offset do cliente estava
trocado na análise: escolher 12 aparecia como 13:00. Regra prática: o que o
jogador vê no relógio do jogo é o que fica marcado.

## 7. Colunas envolvidas

```sql
guild:      pending_guild_battle_attack_id, pending_guild_battle_defense_id,
            honor, battles_attacked/won/defended/lost (ainda não incrementadas)
character:  finished_guild_battle_attack_id, finished_guild_battle_defense_id
guild_battle: status, battle_time, ts_attack, guild_attacker_id,
              guild_defender_id, attacker_character_ids, defender_character_ids,
              guild_winner_id, attacker_character_profiles,
              defender_character_profiles, rounds, attacker_rewards,
              defender_rewards, initiator_character_id
guild_battle_rewards: guild_battle_id, character_id, game_currency, item_id,
              status, type (1=atacante, 2=defensor, 3=dungeon)
```

## 8. Pendências conhecidas

- Táticas de batalha: a **FORMAÇÃO** (order 1/3/5) já é respeitada na
  simulação; faltam os FOCOS ESTRATÉGICOS (`guild_battle_tactics_*_tactic`,
  type 2: tactic2 = acerto +5%/dano −7.5%, tactic3 = dano +5%/acerto −7.5%)
  e mísseis/medikits — o combate ignora esses modificadores
- Contadores de guilda (battles_won/lost etc.) não incrementados
- Log de guilda dos eventos de batalha não escrito
