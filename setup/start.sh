#!/bin/sh
# Start a local-only WordPress installation. Re-running preserves the catalog.
set -eu
cd "$(dirname "$0")/.."

command -v docker >/dev/null
command -v openssl >/dev/null
docker compose version >/dev/null
docker info >/dev/null

# PHP and WP-CLI run as www-data and read these source mounts.
chmod -R a+rX theme/tir-on local/mu-plugins setup

# The directory is readable only by the current user. Compose binds individual
# files into containers, where both the database UID and www-data must read them.
umask 077
mkdir -p .local/secrets
chmod 700 .local .local/secrets
for name in db_password db_root_password admin_password; do
    if [ ! -s ".local/secrets/$name" ]; then
        secret_tmp=$(mktemp ".local/secrets/$name.XXXXXX")
        openssl rand -hex 32 > "$secret_tmp"
        chmod 444 "$secret_tmp"
        ln "$secret_tmp" ".local/secrets/$name" 2>/dev/null || test -s ".local/secrets/$name"
        rm -f "$secret_tmp"
    fi
done

docker compose up --detach --wait db wordpress
docker compose exec -T --user 0 wordpress install -d -o 33 -g 33 -m 0700 /var/lib/tiron/photos
docker compose run --rm -T cli sh /project/install.sh

cat <<'EOF'

Magazinul local este pregătit.
  Acasă          http://localhost:8080/
  TIR-ON Luna    http://localhost:8080/produs/luna/
  Administrare   http://localhost:8080/wp-admin/
  Utilizator     atelier
  Parolă         .local/secrets/admin_password (numai pe calculatorul tău)
EOF
