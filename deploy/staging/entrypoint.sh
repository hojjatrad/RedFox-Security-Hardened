#!/usr/bin/env bash
set -euo pipefail
until mysqladmin ping -h"${REDFOX_DB_HOST:-mysql}" -u"${REDFOX_DB_USER:-redfox}" -p"${REDFOX_DB_PASSWORD:-redfox_test}" --silent; do sleep 2; done
cd /var/www/html
mkdir -p logs storage/cache updates
php table.php >/tmp/redfox-table.log 2>&1 || { cat /tmp/redfox-table.log; exit 1; }
php bin/migrate.php
chown -R www-data:www-data logs storage updates
exec "$@"
