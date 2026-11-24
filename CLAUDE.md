# HeroZero_Server — instruções para agentes

## Projeto e comunicação

Servidor privado do **Hero Zero**, compatível com o cliente HTML5 oficial
(versão 257). Reimplementa o protocolo HTTP `POST /request.php`, envelope
`{data, error}`, assinatura `md5(action + salt + user_id)` e socket de tempo real.

- Comunicar em português europeu, de forma direta e prática.
- Ler os documentos relevantes em `docs/` antes de alterar uma funcionalidade;
  não é necessário carregar toda a documentação em cada tarefa.
- Para implementar, fazer alterações e testes proporcionais ao risco.
  Para analisar ou diagnosticar, não alterar dados/código sem pedido de correção.
- Preservar alterações existentes e não mexer em funcionalidades fora do pedido.
- Não delegar a subagentes sem pedido explícito do utilizador.

## Estrutura

| Caminho | Conteúdo |
|---|---|
| `server-laravel/actions/` | Handlers das ações do cliente |
| `server-laravel/app/HeroZero/` | Núcleo do jogo: Router, Live, Character e sistemas |
| `server-laravel/data/` | Fixtures de protocolo e dados de referência |
| `server-laravel/public/` | Cliente HTML5 e assets servidos |
| `assets/` | Assets e constantes disponíveis na raiz |
| `server-laravel/docker/initdb/` | Schema e seeds do MySQL |
| `docker-compose.yml` | MySQL; dados persistentes em `docker/mysql-data/` |
| `socket-server/` | Socket de tempo real |
| `admin-laravel/` | Painel de administração |
| `scripts/testing/` | Scripts de preparação, migração e testes |
| `docs/` | Documentação técnica e registo de bugs |

O compose expõe MySQL na porta 3308. Não presumir que Docker/BD estão
acessíveis na sessão; verificar quando necessário e reportar limitações reais.
Nunca apagar volumes, recriar a BD ou alterar permissões do Docker para contornar acesso.

## Fontes para compreender o jogo

- JavaScript: `server-laravel/public/js/HeroZero.min.js`.
- Constantes: `assets/data/constants_json.data`, também disponíveis no caminho
  equivalente de `server-laravel/public/assets/data/` quando presente.
  São JSON comprimido com zlib, não texto JSON diretamente pesquisável.
- HARs/imagens fornecidos pelo utilizador: analisar pedidos, respostas e
  comportamento visual. No HAR, o texto da resposta pode estar em base64.
- Não expor tokens, cookies, `auth`, IDs de sessão ou outros dados sensíveis
  presentes nas capturas/configuração.
- Preservar nomes de campos, tipos JSON, estados e códigos de erro esperados
  pelo cliente. Não inventar significados de enums ou fatores sem evidência.
- Distinguir dados oficiais, inferências e balanceamento local. Se a fórmula
  não existir no cliente/constantes, não apresentar uma aproximação como oficial.
- Preferir correções no backend. Editar o bundle minificado só quando o problema
  for realmente do cliente e estiver no âmbito do pedido.
- Verificar login (`autoLoginUser`), estado normal (`Live::accountState`) e
  respostas parciais: nem todos passam pelo mesmo caminho. Incluir as coleções
  referenciadas por ID antes de o cliente desenhar os objetos.

## BD, recompensas e scripts

- Validar propriedade, estado, nível, saldo, capacidade e compatibilidade.
- Entrega de prémios, consumo de recursos e marcação de resgate devem ser
  atómicos, com locks/transações e proteção contra resgates duplicados.
- Quando já existir transação, usar savepoint em vez de iniciar outra.
- Não executar DDL dentro de transações de jogo: MySQL pode fazer commit implícito.
- Alterações de schema precisam de migração idempotente para instalações
  existentes e atualização do schema inicial quando aplicável. Alterar só o
  initdb não atualiza uma BD já criada.
- Scripts de teste devem aceitar nome do herói quando possível, validar alvos,
  explicar efeitos e evitar obrigar o utilizador a procurar IDs ou escrever SQL.
- Operações persistentes de preparação devem ser explícitas. Testes de integração
  devem preferir rollback; resets relevantes devem guardar backup quando possível.
- Usar o relógio do jogo (`GameTime`) e o calendário/fuso apropriado do sistema,
  não misturar silenciosamente datas reais com datas simuladas.

## Verificação e documentação

- Executar `php -l` nos ficheiros PHP alterados e os testes relevantes disponíveis.
- Separar teste offline, teste com PDO simulado, integração MySQL e confirmação
  manual do utilizador. Sucesso num deles não prova sucesso nos restantes.
- Ler a saída dos testes: bootstrap/handlers de exceções podem produzir erro
  mesmo quando o processo devolve código zero.
- Se não houver acesso à BD, preparar testes/scripts e indicar o que ficou
  pendente; não declarar que a integração foi validada.
- Atualizar os documentos da funcionalidade quando mudar regras ou protocolo.
- Em `docs/bugs_encontrados.md`, pesquisar antes de adicionar. Atualizar uma
  entrada existente quando for o mesmo bug; para um novo, usar ID único e manter
  a estrutura `[Sintoma]`, `[Causa]`, `[Correcção]`, `[Validação]`, `[Ficheiros]`.
- Funcionalidades novas e scripts auxiliares não são automaticamente bugs.
  Não registar problemas apenas suspeitos como corrigidos.
- Não atribuir trabalho a um modelo cuja identidade não esteja confirmada.

## Documentação de entrada

- Arquitetura: [SERVER_DESIGN.md](docs/SERVER_DESIGN.md).
- Protocolo: [PROTOCOL.md](docs/PROTOCOL.md), [RESPONSE_SCHEMA.md](docs/RESPONSE_SCHEMA.md).
- Mapeamento: [Mapping.md](docs/Mapping.md); estado: [Features.md](docs/Features.md).
- Bugs: [bugs_encontrados.md](docs/bugs_encontrados.md).
- Produção: [PRODUCTION_AUDIT.md](docs/PRODUCTION_AUDIT.md).
- Missões: [geral.md](docs/missoes/geral.md), [chamadas_emergencia.md](docs/missoes/chamadas_emergencia.md).
- Temporadas: [atual.md](docs/seasons/atual.md), [implementar.md](docs/seasons/implementar.md).
- Modificações de equipamento/pets: [implementacao.md](docs/modificações/implementacao.md).
- Itens/brechó: `docs/shop_items/`, sobretudo `geracao_itens.md` e `lojas.md`.
- Pets: `docs/sidekicks/`; treinos: `docs/treinos/`.
- Liga: [league.md](docs/league/league.md).
- Guilda: [README.md](docs/Guilda/README.md).
- Troféus: [implementacao.md](docs/trofeus/implementacao.md).
- Esconderijo: `docs/hideout/`.

Planos históricos podem estar desatualizados. Comparar com o código atual e
com os documentos de estado antes de os usar como lista de trabalho pendente.
