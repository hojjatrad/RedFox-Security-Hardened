#!/usr/bin/env bash
set -Eeuo pipefail
BASE="${BASE_URL:-http://localhost:8080}"
curl -fsS "$BASE/health.php" | grep -q '"ok":true'
curl -fsS -H 'X-Health-Token: staging-health-secret-not-for-production' "$BASE/ready.php" | grep -q '"ok":true'
docker compose exec -T app php bin/preflight.php
docker compose exec -T app php bin/integrity-check.php --json >/tmp/redfox-integrity.json
echo "STAGING SMOKE OK"
