#!/usr/bin/env bash
set -Eeuo pipefail
BASE="${1:-/var/www/redfox}"; TARGET="${2:-}"
exec 9>"$BASE/deploy.lock"; flock -n 9 || { echo 'Deployment lock busy' >&2; exit 3; }
if [[ -z "$TARGET" ]]; then
  CURRENT="$(readlink -f "$BASE/current" || true)"
  TARGET="$(find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | awk -v c="$CURRENT" '$2!=c{print $2;exit}')"
fi
[[ -d "$TARGET" && -f "$TARGET/version" ]] || { echo 'Rollback target not found' >&2; exit 4; }
echo 'WARNING: code rollback does not roll back database migrations.' >&2
ln -sfn "$TARGET" "$BASE/.current-new"; mv -Tf "$BASE/.current-new" "$BASE/current"
echo "ROLLED BACK CODE TO $TARGET"
