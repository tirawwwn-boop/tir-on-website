#!/usr/bin/env bash
set -euo pipefail
project_dir=$(cd "$(dirname "$0")/.." && pwd)
runtime_dir="$project_dir/.runtime"
if [[ -x "$runtime_dir/php/usr/bin/php8.4" ]]; then
  export LD_LIBRARY_PATH="$runtime_dir/php/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
  exec "$runtime_dir/php/usr/bin/php8.4" -c "$runtime_dir/php.ini" "$@"
fi
exec php "$@"
