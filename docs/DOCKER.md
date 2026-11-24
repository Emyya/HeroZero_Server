# Stack local em Docker

A stack atual usa o `docker-compose.yml` da raiz. O Compose antigo em
`server-laravel/docker/` inclui este ficheiro para evitar duas configurações
incompatíveis. Antes, só o MySQL estava em Docker e `start.sh` lançava PHP e Node
no host. Agora:

| Serviço | Execução | Acesso no host |
|---|---|---|
| Jogo | Apache + PHP 8.4 | http://127.0.0.1:8080/beta.html |
| Admin | Apache + PHP 8.4 | http://127.0.0.1:8001 |
| Socket | Node 22 | porta 8090 |
| MySQL | MySQL 8.4 | porta 3308 |
| Bots | Python local, fora do Docker | GAME_URL aponta para 8080 |

A porta 8000 está ocupada pelo LibreNMS nesta máquina; não é usada pelo jogo.
Pode alterar-se a porta com `HZ_GAME_PORT=... bash start.sh`, ajustando também
`GAME_URL` dos bots. As portas desta stack são publicadas apenas em localhost.

## Arranque e paragem

```bash
bash start.sh
docker compose ps
docker compose logs -f game admin socket
bash stop.sh
```

Só é necessário Docker/Compose no host para executar o servidor. Dependências
Composer são instaladas dentro dos containers em volumes próprios. O código e
os assets são montados a partir do projeto. O utilizador PHP corresponde ao UID/GID
do utilizador que executa `start.sh`. Caches compilados ficam em volumes Docker.
Não se usa `php artisan serve` nem Node nativo para estes serviços.

O MySQL reutiliza `docker/mysql-data`; não há limpeza, recriação do diretório ou
restauro automático. Os scripts de initdb só se aplicam a uma pasta MySQL vazia.
`stop.sh` usa `docker compose stop` e preserva todos os volumes. O relógio partilha
`.run` com o host e o admin. O container do jogo acumula uptime monotónico em
`.run/game_clock.json`, com checkpoints atómicos a cada 250 ms; `stop.sh` não faz
cálculos com datas. Paragens e reinícios diretos pelo Docker também congelam o
relógio. Alterar a data/hora do PC não altera esse uptime. A data real só é usada
para definir o epoch inicial; avanços/recuos voluntários continuam pelo offset
do jogo. O admin e os bots leem o mesmo checkpoint, sem somar tempo desligado.
Se o processo morrer abruptamente, pode perder-se apenas o intervalo desde o
último checkpoint. A migração preserva o uptime acumulado antigo e ignora
`started_at`, pois a sessão antiga não pode ser reconstruída com segurança após
mudanças da data do host. O ficheiro `uptime_accumulated` continua atualizado
para compatibilidade; não é somado ao valor de `game_clock.json`.
O arranque executa os preparadores idempotentes de adversário de tutorial e
casino de abertura dentro do container do jogo.

PHP liga ao MySQL em `db:3306`; o admin chama a API do jogo em `http://game`;
o jogo envia pushes para `http://socket:8090/push`. O browser usa os endereços
publicados no host. Tokens e passwords podem ser substituídos pelas variáveis
`HZ_SOCKET_TOKEN` e `HZ_MYSQL_PASSWORD`; a password deve corresponder à BD já
existente (mudar a variável não muda a password gravada no MySQL).

## Bots locais

### Reiniciar o servidor do zero

Com os bots parados, executar `bash reset_server.sh` na raiz. É uma operação
destrutiva, sem backup: limpa os dados de todas as tabelas de jogo, incluindo
contas, personagens, equipas, pagamentos e calendários. Preserva o schema e o
histórico técnico de migrações. Não apaga volumes nem a pasta MySQL.

Também remove o epoch, uptime e offset do relógio, checkpoints de registo,
estado individual, logs, planos de população, perfis e allowances dos bots.
Planos manuais antigos gerados na raiz e em `bots/`, com as respetivas listas e
manifestos, também são removidos para o launcher não voltar a adotá-los.
O script deixa os serviços parados. O relógio começa na data em que for executado
`bash start.sh` novamente; esse arranque prepara o adversário de tutorial e o
casino inicial de três dias. O reset não é executado automaticamente.

```bash
bots/.venv/bin/python bots/setup_async.py --mode 2 --bots 300 --out contas300_docker.txt
bots/.venv/bin/python bots/orchestrator_async.py --accounts contas300_docker.txt --bots 300
```

O padrão de `GAME_URL` é `http://127.0.0.1:8080/request.php` e o MySQL local usa
3308. O preflight e a consulta inicial de constantes enviam assinatura `auth`.
Nenhum bot é lançado automaticamente pelo Compose ou pelo start.sh.

## Validação desta migração

Validado em 2026-10-20: build das imagens, `start.sh` completo, jogo/admin/DB
healthy, cliente `/beta.html` HTTP 200, socket `/health` OK, SELECT 1 no MySQL a
partir de ambos os containers PHP e acesso ao socket pela rede interna.
`initEnvironment` e `initGame` assinados passaram pelo Python local na porta 8080.
Testes de bots: 86 passaram e um foi ignorado por HAR opcional ausente. Nenhuma
população de bots foi lançada. O preparador normal de arranque criou o adversário
de tutorial que faltava; não houve limpeza da BD nem criação de backups.

## Comando único para os bots locais

Com a stack já ligada:

```bash
python3 bot.py 300
# Ou diretamente, graças ao shebang:
./bot.py 300
# Opcional: nomes e equipas num ficheiro:
python3 bot.py 300 nomes.txt
```

O launcher usa automaticamente `bots/.venv/bin/python`, quando disponível.
Guarda o plano e allowances em `bots/state/populations/`, verifica/cria contas e
inicia o orquestrador no mesmo processo. `Ctrl+C` termina a execução.
Repetir o comando reutiliza a população; reduzir o número não elimina bots e
voltar a aumentá-lo reutiliza os nomes anteriores. Crescer além do plano guardado
acrescenta nomes. Na primeira utilização, adota o plano manual mais recente da
raiz que tenha `.behavior.json`, se existir, e informa qual foi usado.
Um ficheiro opcional deve ter pelo menos o número de nomes pedido e aceitar
`nome` ou `nome:equipa`; `none` exclui atribuição inicial. Não é necessário indicar
ficheiros JSON. Orçamentos persistidos são conservados, incluindo planos antigos
que não tenham a nova divisão de 50%. Para uma população inteiramente nova,
continua a divisão metade R$ 0–100, metade R$ 101–10000.

Cada lote de até 10 contas prontas começa a jogar enquanto as restantes são
preparadas. Se uma conta falhar, os bots prontos continuam; repetir o mesmo
comando tenta resolver as pendentes, preservando nomes e allowances. Uma conta
com registo incerto mantém o checkpoint e requer verificação antes de novo
registo, para evitar duplicações. O setup permite até oito pedidos simultâneos,
sem teto de pedidos por segundo; respostas lentas não bloqueiam os restantes.
Um lock impede dois launchers simultâneos. Sem equipa explícita, cerca de 7,5%
dos bots fundam equipas durante o jogo e os restantes procuram vagas.
O endpoint local `/request.php` não aplica limite por IP: todos os bots vindos do
host Docker partilham o mesmo IP. O executor não tem teto de pedidos por segundo
por defeito; mantém até 32 pedidos em curso para não acumular ligações sem limite.
`BOT_HTTP_CONCURRENCY` ajusta essa concorrência, e
`BOT_REQUESTS_PER_SECOND` pode ativar voluntariamente um ritmo máximo. Um HTTP
429 vindo de outro limite suspende os pedidos seguintes pelo `Retry-After`, sem
reenviar automaticamente ações cujo efeito possa ser incerto.
`python3 bot.py 300 --dry-run` prepara só o plano, sem criar contas ou iniciar bots.

Validação do launcher: seis testes offline de persistência, crescimento, ficheiro
opcional, adoção de planos e encadeamento setup/orquestrador. Suite: 92 passaram,
um ignorado por HAR opcional ausente. Sem criação real de população nesta validação.

## Custo de CPU com populações de bots

`AssetCache` conserva catálogos imutáveis em memória por pedido e em APCu entre
pedidos Apache. O armazenamento nativo evita serializar/desserializar arrays em
cada leitura. A imagem instala APCu com 256 MiB partilhados e `apc.serializer=default`; se indisponível ou
sem espaço, mantém o fallback de ficheiros serializados. As entradas são
identificadas pelo caminho e mtime/tamanho do asset, com TTL de uma hora em APCu.
Os templates JSON de `Replay` usam a mesma cache antes de aplicar datas atuais.
Não se guarda estado vivo de saldos, personagens, guildas ou recompensas
nesta cache; os templates capturados continuam a ser apenas a base do formato.
Mudanças na lógica de construção dos catálogos devem incrementar o nome da
entrada (`*_vN`), como anteriormente no fallback em disco.

`Live::accountState` evita o carregamento inicial duplicado do personagem;
continua a carregar depois da sincronização dos torneios e a emitir todas as
coleções do protocolo. Não foram reduzidos limites de pedidos ou frequências
de ações dos bots. Teste offline: `scripts/testing/test_asset_cache.php`;
com `php -d apc.enable_cli=1` verifica também o ramo APCu.

Validação em 2026-10-24: 152 testes Python (1 ignorado), lint PHP e teste da
cache com/sem APCu. No microbenchmark sintético de 50 leituras, o armazenamento
APCu nativo demorou cerca de 10 ms contra 645 ms do ficheiro serializado.
É uma medição isolada da cache, não uma estimativa da redução global de CPU.
