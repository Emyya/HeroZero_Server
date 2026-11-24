# Energéticos do esconderijo

## Efeito e disponibilidade

`booster_hideout1` acrescenta 25% e `booster_hideout2` acrescenta 50% à velocidade
de produção das salas `glue_production` e `stone_production`. Os valores vêm do
catálogo de energéticos existente. Não aumentam a capacidade da sala nem o
limite global de armazenamento. Moedas da central usam o energético de trabalho;
XP, bots e outras salas não recebem este bónus.

O saldo não aumenta instantaneamente ao ativar: os recursos acumulam-se com o
passar do relógio `GameTime`. A produção mantém a granularidade de minutos e
os fatores de gerador já existentes. Para cada período desde a última mudança:

```text
minutos ponderados = minutos decorridos
                   + minutos dentro da janela ativa × percentagem / 100
produção = min(capacidade, quantidade guardada + floor(taxa base × minutos ponderados))
```

## Ativação, renovação e expiração

O resgate de cupões e `buyHideoutBooster` usam a mesma ativação. Antes de mudar
ou renovar o energético, guardam a produção acumulada das salas afetadas e
reiniciam o timestamp de produção. Assim, um energético novo não dá bónus
retroativo e um antigo não perde a produção já gerada. A recolha individual e
coletiva entrega também essa quantidade guardada e limpa a sala, sob transação
e lock do herói. Repetir no mesmo instante não duplica recursos.

A janela usa `ts_active_hideout_boost_started` e
`ts_active_hideout_boost_expires`; fim `-1` significa permanente. O cálculo
aplica o bónus apenas à parte decorrida dentro da janela, mesmo se o jogador
recolher depois da expiração. Renovações somam a duração ao prazo corrente.
A compra valida identificador, duração, esconderijo e saldo; débito e ativação
são atómicos. Preços e durações vêm de `Live::boosterCost()`.

## Protocolo e migração

Cada sala de cola/pedra recebe, dentro do JSON `additional_value_2`, a extensão
local `production_booster: {amount, start, end}`. Os restantes metadados são
preservados. `Live::accountState` e os caminhos que usam `Hideout::statePayload`
expõem a mesma janela. O cliente usa-a para produção calculada, taxa por minuto,
tempo até encher e hash de atualização. A capacidade continua com a fórmula
existente. A apresentação conserva os estilos visuais do cliente; a cor
avermelhada reportada não foi diagnosticada como uma falha independente.

Para instalações existentes:

```bash
php scripts/testing/migrate_user_vouchers.php
```

Migração idempotente, fora de transações de jogo; acrescenta o timestamp de
início quando faltar. Energéticos já ativos passam a contar o bónus a partir
da migração, sem compensação retroativa por períodos em que não era aplicado.
Schema inicial atualizado em `01_schema.sql`.

## Validação

```bash
php scripts/testing/test_hideout_booster.php "skel"
node scripts/testing/test_hideout_booster_client.js
node --check server-laravel/public/js/HeroZero.min.js
```

Integração MySQL com rollback e relógio em memória: +25/+50% em cola/pedra,
capacidade, expiração, produção anterior à ativação, resgate real de cupão,
janela no estado, recolha real individual/coletiva, retry e compra real com
saldo/sem saldo. Node executa getters reais do bundle para taxa, quantidade,
expiração e hash; gráficos simulados. Regressão MySQL do casino passou.
Confirmação visual no browser, incluindo a cor, pendente. Recarregar o cliente
para carregar os novos cálculos. Ver BUG-000154.

## Pré-visualizações de construção (BUG-000155)

Objetos parciais do catálogo podem não ter `hideout_id` ou metadados de sala.
A leitura da janela verifica esses campos antes de consultar a propriedade;
nesses objetos, getters e hash usam o comportamento original sem energético.
O teste Node inclui previews de gerador, cola e pedra e reproduziu a exceção
antes da correção. Salas existentes mantêm o cálculo do bónus.
