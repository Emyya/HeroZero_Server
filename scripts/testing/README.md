# Scripts de preparação, manutenção e testes

O catálogo completo está em [docs/scripts.md](../../docs/scripts.md): finalidade,
argumentos, pré-requisitos e efeitos de todos os ficheiros desta pasta, incluindo
os scripts em `trofeus/`.

Os scripts têm modos diferentes: alguns guardam alterações, outros migram/reparam
dados e outros são testes offline, com PDO simulado ou MySQL com rollback.
Consultar a entrada correspondente antes de executar.

Comandos a partir da raiz do repositório:

```bash
php scripts/testing/give_donuts.php "Nome do herói" 1000
php scripts/testing/test_quest_generation.php
php scripts/testing/test_daily_bonus.php --db "Nome do herói"
node scripts/testing/test_daily_bonus_client.js
```

O primeiro comando credita donuts permanentemente; os restantes são testes com
os modos descritos no catálogo. Ler a saída completa: o código de saída zero não
basta para confirmar sucesso em todos os scripts antigos.

Troféus: [descrição dos cenários](trofeus/README.md).
Login diário/eventos: [regras da funcionalidade](../../docs/logins/login_diario_e_bonus.md).

Eventos de coleção: [catálogo, regras e comandos de preparação/teste](../../docs/eventos/implementacao.md).

## Caça às Toupeiras

Documentação: [event_treasures_implementacao.md](../../docs/eventos/event_treasures_implementacao.md).

| Script em `scripts/testing/` | Argumentos | Efeito |
| --- | --- | --- |
| `migrate_event_treasures.php` | Nenhum | Schema idempotente, sem eventos ativados |
| `start_event_treasure.php` | `--list`, `--calendar`, `Template --days=N [--start="AAAA-MM-DD HH:MM:SS"] [--hero="Nome"]`, `--cancel=ID` | Agendamento/cancelamento global persistente; consultas não alteram dados |
| `prepare_event_treasure.php` | `"Nome" [--show|--accept|--shovels|--unlock]` | Consulta, aceitação ou preparação persistente de pás/espera com backup, sem prémios |
| `test_event_treasures.php` | `[--mysql] [--hero="Nome"]` | Offline por defeito; integração MySQL com rollback |
| `test_event_treasures_client.js` | Nenhum; Node.js | Métodos reais do bundle, sem navegador |

## Pirâmide da Sorte

Regras e efeitos em [event_draws_implementacao.md](../../docs/eventos/event_draws_implementacao.md).

| Script em `scripts/testing/` | Argumentos | Efeito |
| --- | --- | --- |
| `migrate_event_draws.php` | Nenhum | Schema idempotente, sem eventos ativados |
| `start_event_draw.php` | `--list`, `--calendar`, `Template --days=N [--start="AAAA-MM-DD HH:MM:SS"] [--hero="Nome"]`, `--cancel=ID` | Agendamento/cancelamento global persistente; consultas não alteram dados |
| `prepare_event_draw.php` | `"Nome" [--show|--accept]` | Consulta ou aceitação persistente, sem sorteios/prémios/saldo |
| `test_event_draws.php` | `[--mysql] [--hero="Nome"]` | Offline por defeito; integração MySQL com rollback |
| `test_event_draws_client.js` | Nenhum; Node.js | Métodos reais do bundle, custos/pesos em 880 estados, sem navegador |
