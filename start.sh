#!/usr/bin/env bash
# Stack Docker; os bots continuam no Python local.
set -euo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"
export HZ_UID="$(id -u)" HZ_GID="$(id -g)"
mkdir -p .run
if [ ! -f .run/epoch ]; then date +%s > .run/epoch; fi
if [ ! -f .run/uptime_accumulated ]; then echo 0 > .run/uptime_accumulated; fi
docker compose up -d --build --wait --wait-timeout 300
docker compose exec -T -u hzapp game php /workspace/scripts/setup/ensure_tutorial_player.php
docker compose exec -T -u hzapp game php /workspace/scripts/setup/ensure_opening_casino.php
echo "Jogo: http://127.0.0.1:${HZ_GAME_PORT:-8080}/beta.html"
echo 'Admin: http://127.0.0.1:8001 — Socket: 8090 — MySQL: 3308'
echo 'Logs: docker compose logs -f — Parar: bash stop.sh'
