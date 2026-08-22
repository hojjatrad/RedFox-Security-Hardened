#!/usr/bin/env bash
set -Eeuo pipefail
PACKAGE="${1:-}"; BASE="${2:-/var/www/redfox}"; PHP_BIN="${PHP_BIN:-/usr/bin/php}"
[[ -f "$PACKAGE" ]] || { echo "Usage: $0 package.zip [/var/www/redfox]" >&2; exit 2; }
mkdir -p "$BASE/releases" "$BASE/shared/storage" "$BASE/shared/logs"
exec 9>"$BASE/deploy.lock"; flock -n 9 || { echo 'Another deployment is running' >&2; exit 3; }
TS="$(date +%Y%m%d%H%M%S)"; TMP="$BASE/releases/.tmp-$TS"; REL="$BASE/releases/$TS"
trap 'rm -rf "$TMP"' ERR
mkdir -p "$TMP"; unzip -q "$PACKAGE" -d "$TMP"
SRC="$(find "$TMP" -mindepth 1 -maxdepth 1 -type d | head -1)"; [[ -n "$SRC" ]] || { echo 'Invalid package' >&2; exit 4; }
mv "$SRC" "$REL"; rm -rf "$TMP"
CONFIG_SOURCE="${CONFIG_SOURCE:-$BASE/current/config.php}"
[[ -f "$CONFIG_SOURCE" ]] || { echo "Missing config: set CONFIG_SOURCE" >&2; exit 5; }
cp "$CONFIG_SOURCE" "$REL/config.php"; chmod 640 "$REL/config.php"
rm -rf "$REL/storage" "$REL/logs"; ln -s "$BASE/shared/storage" "$REL/storage"; ln -s "$BASE/shared/logs" "$REL/logs"
"$PHP_BIN" "$REL/bin/migrate.php"
"$PHP_BIN" "$REL/bin/preflight.php"
ln -sfn "$REL" "$BASE/.current-new"; mv -Tf "$BASE/.current-new" "$BASE/current"
find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | awk 'NR>5{print $2}' | xargs -r rm -rf
echo "DEPLOYED $REL"
