# Estatísticas da Team — como funciona

Como o diálogo de estatísticas da team (`DialogGuildStatistics`) é preenchido:
funções responsáveis no cliente e no servidor, fluxo de dados e fórmulas.

----------------------------------------------------------------------------

## Fluxo geral (resumo)

```
[Team page] --botão estatísticas--> DialogGuildStatistics (l6)
                                        |  lê TUDO do objeto Guild (Va)
                                        |  já carregado em memória
                                        v
        NÃO HÁ request dedicado: os dados chegam pelo accountState
                                        |
   Live::accountState() ──> $data['guild']         = Live::shapeGuild()
                          └─> $data['guild_members'] = Live::guildMembers()
```

O diálogo **não faz nenhum `sendActionRequest`**: usa o objeto `Guild` que o
cliente constrói a partir da resposta do `accountState` (chaves `guild` e
`guild_members`). Qualquer alteração nas funções do servidor é visível no
próximo boot/poll, sem tocar no diálogo.

----------------------------------------------------------------------------

## Lado do servidor (HeroZero_Server)

### `Live::accountState()` — server-laravel/app/HeroZero/Live.php (~linha 367)

Se o herói tem guilda:

```php
$data['guild']         = self::shapeGuild($guild);
$data['guild_members'] = self::guildMembers($gid);
```

É daqui que saem os dois blocos que alimentam o diálogo.

### `Live::guildMembers($guildId)` — média das habs

`SELECT` dos membros na tabela `character` e, por membro, monta a shape com:

- `stat_total_stamina / stat_total_strength / stat_total_critical_rating /
  stat_total_dodge_rating` — **totais REAIS**, vindos de
  `Character::load($id)->battleProfile('a')`
  (= base + treinado + equipamento + bónus de booster/guilda, exatamente o
  número que o perfil do personagem mostra). BUG-000061: antes era só
  `base + treinado`, e as médias do diálogo ficavam muito abaixo do real.
- `level`, `name`, donativos, estado online, etc.

### `Live::shapeGuild($guild)` — contadores de batalha + expansão

Passa todas as colunas da tabela `guild` (g.*) e calcula AO VIVO os contadores
que o diálogo lê (as colunas da tabela nunca são incrementadas):

| campo no JSON | origem |
|---|---|
| `battles_attacked` | `COUNT(guild_battle WHERE status=3 AND guild_attacker_id=?)` |
| `battles_defended` | idem com `guild_defender_id` |
| `battles_fought` | attacked + defended |
| `battles_won` | `... AND guild_winner_id=?` |
| `battles_lost` | fought − won |
| `dungeon_battles_fought` | `COUNT(guild_dungeon_battle WHERE status=3 AND guild_id=?)` |
| `dungeon_battles_won` | idem E rewards pagos em moedas — mesmo critério do histórico; **com `JSON_VALID(rewards)`** porque há linhas com `rewards=''` e o `JSON_EXTRACT` crasha sem isso |

A percentagem de expansão NÃO é calculada no servidor para este diálogo — o
cliente faz contas com as colunas cruas dos upgrades
(`stat_guild_capacity`, `stat_character_base_stats_boost`,
`stat_quest_xp_reward_boost`, `stat_quest_game_currency_reward_boost`).

----------------------------------------------------------------------------

## Lado do cliente (public/js/HeroZero.min.js)

Nomes minificados atuais (podem mudar entre builds, a estrutura não):

| classe | papel |
|---|---|
| `l6` | `DialogGuildStatistics` — o diálogo |
| `Va` | `Guild` — objeto da guilda do jogador |
| `ag` / `Nd` | `GuildMember` / base DOCharacter de um membro |

### Onde cada linha do diálogo vai buscar o valor

| linha do diálogo | getter usado | fonte |
|---|---|---|
| Fundada em | `get_foundationDateString()` | `ts_creation` |
| Membros | `Q.getGuildMemberCountString(get_memberCount())` | `_members.length` |
| Nível médio | `get_averageLevel()` | calculado no cliente¹ |
| Força/Resistência/Crítico/Esquiva médios | `get_averageStrength/Stamina/CriticalRating/DodgeRating()` | calculado no cliente¹ |
| Expansão (%) | `get_totalImprovementPercentageString()` | calculado no cliente² |
| Combates lutados | `Q.getGuildBattleCountString(get_battlesFought())` | `battles_fought` |
| Ataque / Defesa | `get_battlesAttacked()` / `get_battlesDefended()` | colunas calculadas |
| Ganhos / Perdidos (+ % sobre lutados) | `get_battlesWon()` / `get_battlesLost()` | colunas calculadas |
| Gangues derrotadas / vezes que lutámos | `get_dungeonBattlesWon()` / `get_dungeonBattlesFought()` | colunas calculadas |
| Mísseis disparados / auto-joins usados | `get_missilesFired()` / `get_autoJoinsUsed()` | colunas da tabela |
| Artefactos (atuais/máx/ganhos/perdidos) | `get_artifactsOwnedCurrent/Max/Won/Lost()` | colunas da tabela |

¹ Médias — somatório sobre `this._members`, dividido por `get_memberCount()`:

```js
// exemplo (as outras três são iguais, trocando o campo):
Va.prototype.get_averageStrength = function() {
    var total = 0;
    for (membro of this._members) total += membro.get_statTotalStrength();
    return Math.round(total / this.get_memberCount());
};
```

O `get_statTotalStrength()` do membro lê `getInt("stat_total_strength")` —
ou seja, **o valor que o servidor manda em `guild_members[]` é o que conta**.

² Expansão — fórmula oficial do cliente:

```js
Va.prototype.totalImprovementPercentage = function(a, b) {
    if (b == null || b <= 0) b = x.get_guild_percentage_total_base();   // 175
    return (stat_guild_capacity + stat_character_base_stats_boost
          + stat_quest_xp_reward_boost + stat_quest_game_currency_reward_boost
          + a) / b * 100;
};
Va.prototype.get_totalImprovementPercentageString = function() {
    // O DIÁLOGO USA ESTE CAMINHO → divisor guild_percentage_total_real = 180
    return "" + Math.round(this.totalImprovementPercentage(0, x.get_guild_percentage_total_real()));
};
```

Constantes em `server-laravel/data/reference/balancing_constants.json`:

- `guild_percentage_total_base` = 175 (usado noutros sítios do cliente, ex.
  URLs das camadas de fundo da arena)
- `guild_percentage_total_real` = 180 (**o divisor correto para % exibida**)

----------------------------------------------------------------------------

## Ecrãs que mostram expansão — têm de usar TODOS o divisor 180

| ecrã | função servidor | campo | escala enviada |
|---|---|---|---|
| Estatísticas da team | (nenhuma — calculado no cliente) | — | round(soma/180×100) |
| Ranking de teams | `actions/leaderboard.php` (`retrieveGuildLeaderboard`) | `tp` | fração soma/180 (cliente faz ×100) |
| Procurar team | `actions/guildState.php` (`getGuildList`) | `total_percentage` | fração soma/180 (`RH.get_fullTotalPercentage` = Math.round(100×tp)) |

Regra de ouro: sempre que aparecer um valor de expansão errado num destes
ecrãs, confirmar (1) a métrica = soma dos 4 upgrades, (2) o divisor = 180,
(3) a escala = fração 0–1 quando o cliente multiplica por 100.

Histórico: BUG-000060 ("procurar team" mostrava ocupação ×100 = 600%/900%) e
BUG-000061 (ranking dividia por 175; ver também docs/bugs_encontrados.md).
