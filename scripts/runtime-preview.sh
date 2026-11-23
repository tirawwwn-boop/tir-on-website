#!/usr/bin/env bash
# Managed, local-only preview of the real WordPress installation.
# Docker Compose is the supported fresh-install workflow (see README).
set -euo pipefail
cd "$(dirname "$0")/.."
port="${PORT:-4173}"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --port) port="$2"; shift 2 ;;
    --port=*) port="${1#*=}"; shift ;;
    *) shift ;;
  esac
done
if [[ ! $port =~ ^[0-9]{1,5}$ ]] || (( port == 0 || port > 65535 )); then
  echo "Port local invalid." >&2
  exit 1
fi
if [[ ! -f .runtime/wordpress/wp-config.php ]]; then
  echo "Folosește instalarea Docker din README pentru o copie nouă a magazinului." >&2
  exit 1
fi
export TIRON_PREVIEW_PORT="$port"
exec bash scripts/runtime-php.sh -S "127.0.0.1:$port" -t "$PWD/.runtime/wordpress" "$PWD/scripts/runtime-router.php"
