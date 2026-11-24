# Skills de sidekick — estado de implementação

Registo do que já está escrito em código, o que está inerte e porquê.
A master data tem **289 skills**; a esmagadora maioria é exclusiva de pets
específicos (campo `fixed_sidekick`) e nunca foi tocada.

Nomes em pt-BR conforme `assets/i18n/pt_BR/text.data`
(chave `sidekick_skill/<identifier>/name`).

## Como uma skill entra em jogo

Há **duas vias completamente separadas**, e uma skill só faz alguma coisa se
cair numa delas:

| Via | Ficheiro | Chave de despacho | Aplica-se a |
|---|---|---|---|
| Combate | `app/HeroZero/Fight.php` | `identifier` (string, hardcoded) | Efeitos durante a luta |
| Atributos | `app/HeroZero/Sidekick.php::passiveStatBonus` | `sidekick_battle_effect_id` → grupos 105-109 | Bónus permanente às 4 stats |

O `Fight.php` compara `identifier` literal (`self::roll($x, 'stage1_1')`), por
isso **qualquer skill fora da lista abaixo é ignorada em silêncio** — nem efeito,
nem ícone no cliente (o cliente só desenha o que vier em `ae`/`de` nos rounds).

Desbloqueio por nível do pet: `level >= 10 × stage` (10 / 20 / 30).

---

## Via 1 — Combate (`Fight.php`)

### Implementadas ✅

Reescrita completa em 2026-09-07: substituído o despacho por `identifier` literal
por um sistema de comportamentos (`SKILL_BEHAVIOR` — tabela `identifier → tipo`) com
loop independente por grupo. Isto cobre automaticamente todas as skills do mesmo
tipo sem código extra. Abaixo estão os **comportamentos** implementados com
exemplos representativos; as variantes adicionais têm o mesmo mecanismo.

| Comportamento | Identificadores exemplo | Efeito |
|---|---|---|
| `initiative` | stage1_3 | Desloca a probabilidade de ataque primeiro |
| `dodge_debuff` | stage2_6, stage2_8, stage2_11–13, stage2_15, stage2_27, stage2_33 | **Passiva** — baixa a esquiva do adversário |
| `crit_debuff` | stage2_7, stage2_10 | **Passiva** — baixa o crítico do adversário |
| `stun_attack` | stage1_1, stage1_8/9/14/21/23/24/28/29/33/34/41/42/47/49/53/56/59/63/65/70/78/79/89, stage3_46/49/52 | Atordoa o defensor ao acertar |
| `stun_hit` | stage1_43–45/50/58/71/81/90/98, stage3_24/25/29/31/32/33/47/50/51 | Atordoa o atacante ao ser atingido (reativo) |
| `stun_crit` | stage3_45, stage3_16, stage3_53 | Atordoa o defensor ao fazer crítico |
| `stun_if_dodge` | stage3_21 | Atordoa o defensor quando ele esquiva do meu golpe |
| `dot` | stage1_5/6/15/16/19/25/26/27/35/38/39/57/69/72/74/77/83/85/87/99/103/106 | Veneno (DoT) ao acertar; v3<1 = % do dano; v2 = rondas |
| `dot_dodge` | stage1_36/51/64/95/105/111 | DoT ao adversário quando eu esquivo; tick = damage_normal/v2 |
| `crit_extra` | stage3_7/9/28/48, stage1_12/18/20/46/54/61/62/66/67/68/73/75/76/80/82/84/86/88/94/96/100/101/104/108/110 | Dano extra no crítico (+v2); cada skill proca independente |
| `reduce_crit_dmg` | stage1_48, stage1_91 | Reduz dano de crítico recebido em v2 (ch=1) |
| `flat_dmg` | stage1_37/55/93/102/107/109/112 | Bonus flat (+v2) em qualquer golpe |
| `dmg_reduce` | stage3_8, stage1_2 | Reduz dano recebido em v2; cada skill proca independente |
| `counter_crit_dmg` | stage3_4 | Dano de recuo após levar crítico |
| `counter_any_dmg` | stage3_6 | Dano de recuo em qualquer golpe |
| `counter_crit` | stage1_4/11/17/22/30/31/32/40/52/60/92/97, stage3_26 | Contra-ataque (cadeia) após crítico recebido |
| `double_strike` | stage3_2/10/12/17/18/19/27/30/34/35/36/38/39/40/42/43/44 | Golpe duplo (cadeia) |
| `second_dodge` | stage3_3, stage3_13, stage3_20 | 2.ª hipótese de esquiva |
| `survive` | stage3_1 | Sobrevive ao golpe fatal com 1 HP (sem sorteio, ch=1) |
| `survive_plus` | stage3_11, stage3_41 | Sobrevive ao golpe fatal + bonus v3 ao próximo ataque |
| `double_dodge` | stage3_22 | Golpe duplo após esquivar (cadeia) |

**Mecanismo central**: `SKILL_BEHAVIOR` (tabela `identifier → tipo`); loops
inline em `hit()` iteram todas as skills de cada combatente. Para stun e
crit_extra procam independente; para DoT, double_strike e counter_crit dispara
o primeiro a passar o sorteio. Passivas de oposição são lidas em `entity()`
antes do combate começar.

### Arremesso pessoal e mísseis de guilda (atualizado em 2026-10-04)

O arremesso pessoal está implementado: `Fight` emite `m=1..5` e usa dano e
cargas dos pacotes equipados/no cinto. `Missiles::damage()` calcula os bónus
passivos do grupo `battle_effect=104`, incluindo `stage2_5` (id 10), conforme
o JavaScript HTML5. A redução com chance do grupo 14, incluindo `stage1_7`
(id 24), atua sobre a parcela de dano de arremesso.

`stage2_36` (id 227, grupo 110) continua fora desta implementação de dano
pessoal. Os melões da guilda e as rondas `r=4/5/6` continuam pendentes.
Ver [mísseis](../inventario/misseis.md) para regras locais, limites e testes.

---

## Via 2 — Bónus de atributos (`Sidekick::passiveStatBonus`)

Não passam pelo `Fight.php`. A fórmula é
`ceil(stat_base_do_heroi × value1)` por skill desbloqueada, e o mapeamento é por
grupo de battle effect (`docs/sidekicks/extract/03_combate.md` §3.1):

| Grupo | Stat |
|---|---|
| 105 | força |
| 106 | vigor (stamina) |
| 107 | cérebro (critical) |
| 108 | intuição (dodge) |
| 109 | as quatro |

### Implementadas ✅

| Identifier | id | eff | Grupo | Nome | Stat | v1 |
|---|---|---|---|---|---|---|
| `stage2_1` | 6 | 111 | 105 | Força dupla | Força | 0.1 |
| `stage2_2` | 7 | 112 | 106 | Parceiro de corrida | Vigor | 0.1 |
| `stage2_3` | 8 | 113 | 107 | Garoto esperto | Cérebro | 0.1 |
| `stage2_4` | 9 | 114 | 108 | Quatro olhos | Intuição | 0.1 |

Funcionam por **dados**, não por código dedicado: qualquer skill cujo
`sidekick_battle_effect_id` caia nos grupos 105-109 é aplicada automaticamente,
mesmo as exclusivas de outros pets. Não é preciso escrever nada para uma nova.

Além destas, `Character::sidekickBonus()` soma também as `stat_*` **próprias**
do pet, tanto na ficha como no perfil de batalha.

### Inerte ❌

| Identifier | id | eff | Grupo | Nome | Porquê |
|---|---|---|---|---|---|
| `stage2_9` | 25 | 105 | 103 | Bem alimentado | "Reduz a perda de troféus" — não é stat de combate; o sistema de troféus não a consulta |

> Cuidado com a ambiguidade: o `sidekick_battle_effect_id` **105** da `stage2_9`
> não tem nada a ver com o **grupo** 105 (força). São dois espaços de numeração
> diferentes — o efeito 105 pertence ao grupo 103.

---

## Resumo dos pools genéricos

`Sidekick::SKILL_POOLS` é o que o sorteio aleatório e o reskill podem dar a qualquer pet:

| Stage | Pool (skill ids) | Cobertas | Em falta |
|---|---|---|---|
| 1 | 1, 2, 3, 4, 5, 24 | 5/6 | `stage1_7` (míssil) |
| 2 | 6, 7, 8, 9, 10, 11, 12, 25 | 6/8 | `stage2_5` (míssil), `stage2_9` (troféus) |
| 3 | 15, 16, 17, 18, 20, 21, 26 | 7/7 | — |

**Contagem histórica anterior ao arremesso pessoal:** 18 das 21 do pool genérico.
A integração atual de arremesso é descrita acima por grupos de efeitos HTML5.

## Skills exclusivas de pets (2026-09-07)

Após a reescrita do `Fight.php` com `SKILL_BEHAVIOR`, **todas as ~200 skills
exclusivas** cujo comportamento cabe num dos 21 tipos implementados ficam
automaticamente ativas — sem código extra por skill. Basta que o `identifier`
esteja mapeado na tabela `SKILL_BEHAVIOR`.

Das 289 skills da master data, as que **ainda não têm efeito**:
- **Melões de guilda** — continuam sem simulação; `stage2_36` permanece sem
  efeito sobre arremesso pessoal. `stage1_7` e `stage2_5` já têm suporte acima.
- **Troféu** — `stage2_9` (1 skill): sistema de troféus não consulta skills.
- **Comportamentos sem mapeamento** — `stage3_15` (extra_crit após acerto normal,
  requer estado cross-turn) não foi incluído no `SKILL_BEHAVIOR`; pode ser
  adicionado mais tarde.

Para adicionar uma nova skill: basta acrescentar o seu `identifier` à entrada
adequada em `SKILL_BEHAVIOR` (ou criar um novo tipo com o loop correspondente
em `hit()`). Não é preciso tocar em mais nada.

---

## Mísseis — o que falta

Duas coisas diferentes com o mesmo nome:

**Melões (`guild.missiles`)** — a mecânica real de dano, e **só em batalhas de
equipa**. No servidor flash 129 a guarda é `$this->isGuildFight && ...`; num
duelo normal nunca dispara. Rondas `r=4/5/6`. Produzidos pela sala
`guild_missile_production` do esconderijo (essa parte funciona), mas nunca são
gastos — nada lê `use_missiles_*` nem incrementa `missiles_fired`.

**Armas de arremesso (`inventory.missiles_item_id` + cinto `missiles1..4`)** —
implementadas no backend em 2026-10-04. O HTML5 calcula dano próprio com
`Math.round(item_level × 3.9)` e bónus de pet; a interpretação anterior como
mera animação vinha da referência Flash antiga e não descreve este cliente.
O motor emite `m`, consome cargas e recarrega a partir do cinto. Pacotes novos
recebem 100 cargas como regra local explícita. Integração MySQL com rollback
validada; confirmação visual pendente.

---

## Fontes

- `app/HeroZero/Fight.php` — implementação de combate
- `app/HeroZero/Sidekick.php` — `passiveStatBonus`, `SKILL_POOLS`
- `app/HeroZero/Character.php` — `sidekickBonus()`, `battleProfile()`
- [extract/03_combate.md](extract/03_combate.md) §2 (efeitos), §3.1 (stats), §8 (contrato)
- `assets/i18n/pt_BR/text.data` — nomes e descrições oficiais
- `storage/framework/cache/hz_sidekick_official.cache` — master data (values, cargas, `fixed_sidekick`)
- `herozero(flash 129 2018)/server/class/Fight.php` — referência do oficial antigo
