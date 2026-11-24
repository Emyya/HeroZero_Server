#!/usr/bin/env bash
# Reset destrutivo dos dados do jogo; o próximo start.sh inicia um servidor novo.
set -euo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"
if [[ ${1:-} == --help ]]; then
    echo 'Uso: bash reset_server.sh'
    echo 'Apaga contas, jogadores, equipas, calendários, relógio e toda a população/estado/logs dos bots. Sem backup.'
    echo 'O schema fica pronto para um servidor novo. Depois: bash start.sh'
    exit 0
fi
if (( $# )); then echo 'Argumento inválido. Usa --help.' >&2; exit 1; fi
mkdir -p bots/state/populations
exec 9>bots/state/populations/launcher.lock
if ! flock -n 9; then
    echo 'Para os bots com Ctrl+C antes de executar o reset.' >&2
    exit 1
fi
if pgrep -f '(^|/)(python[0-9.]*|pypy[0-9.]*) .*([/]?bot\.py|orchestrator_async\.py|setup_async\.py)( |$)' >/dev/null; then
    echo 'Ainda existe um processo de bots/setup ativo. Para-o antes do reset.' >&2
    exit 1
fi
docker compose stop game admin socket
docker compose up -d --wait --wait-timeout 120 db
docker compose run --rm --no-deps --entrypoint php -u hzapp game /workspace/scripts/setup/reset_database.php --execute
# Nenhum relógio novo é criado aqui: o epoch nasce apenas no próximo start.sh.
rm -f -- server-laravel/storage/game_time_offset
if [[ -d .run ]]; then
    find .run -type f -delete
fi
# Manter apenas o inode do lock enquanto o reset o detém. Não contém dados do jogo.
find bots/state -mindepth 1 ! -path 'bots/state/populations/launcher.lock' -type f -delete
find bots/state -mindepth 1 -type l -delete
if [[ -d bots/logs ]]; then
    find bots/logs -type f -delete
fi
# O launcher também adota planos manuais antigos da raiz: eliminá-los e aos
# respetivos manifestos/listas gerados para não ressuscitar a população anterior.
while IFS= read -r -d '' plan; do
    base=${plan%.plan.txt}
    rm -f -- "$plan" "$base.behavior.json" "$base"
done < <(find . bots -maxdepth 1 -type f -name '*.txt.plan.txt' -print0)
docker compose stop db
echo 'Reset concluído. BD e população apagadas; relógio por iniciar. Sem backup.'
echo 'Novo servidor: bash start.sh'
