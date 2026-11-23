#!/bin/sh
# Executed in the CLI container after the database and Apache are ready.
set -eu

if ! wp core is-installed; then
    # WP-CLI echoes the fully expanded prompt response. Suppress both streams.
    if ! wp core install --url=http://localhost:8080 --title=TIR-ON \
        --admin_user=atelier --admin_email=atelier@localhost.invalid \
        --skip-email --prompt=admin_password < /run/secrets/admin_password >/dev/null 2>&1; then
        echo 'Inițializarea WordPress a eșuat. Verifică starea bazei de date și a volumelor.' >&2
        exit 1
    fi
fi
wp language core install ro_RO --activate
if ! wp plugin is-installed woocommerce; then
    wp plugin install woocommerce --version=11.3.1
fi
wp plugin activate woocommerce
wp language plugin install woocommerce ro_RO
wp theme activate tir-on
wp eval-file /project/seed.php
wp rewrite flush --hard
