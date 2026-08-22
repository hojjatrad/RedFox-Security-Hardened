# Phase 4 verification

- 281 project PHP files parsed with zero parser errors.
- Phase 1–4 static assertions passed.
- Messaging audience uses the same server-side customer scope as the portal.
- Queue recipient uniqueness prevents duplicate delivery within a broadcast.
- Claim tokens prevent two workers from processing the same claimed row.
- Broadcast and direct daily caps are enforced server-side.
- API stores token hashes only and returns raw token once.
- API rebuilds reseller/super-reseller scope from the database per request.
- API endpoints are read-only, permission-scoped and rate-limited.

Live Telegram/API concurrency tests require staging tokens and MySQL.
