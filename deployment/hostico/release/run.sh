#!/bin/bash
set -eu
# cPanel invokes this from the private repository root. No token, FTP or network call.
for cli in /usr/local/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /usr/bin/php; do
    if [ -x "$cli" ] && "$cli" -r 'exit(PHP_SAPI === "cli" && PHP_VERSION_ID >= 80300 && class_exists("ZipArchive") && function_exists("proc_open") ? 0 : 1);'; then
        exec "$cli" deployment/hostico/release/hostico.php
    fi
done
echo 'Hostico trebuie să confirme PHP CLI 8.3+ cu ZipArchive și proc_open pentru Git Deployment.' >&2
exit 1
