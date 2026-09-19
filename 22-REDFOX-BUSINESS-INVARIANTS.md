# REDFOX BUSINESS INVARIANTS

The agent must explicitly test these invariants.

1. A payment cannot credit twice.
2. A payment cannot create duplicate service.
3. Wallet balance cannot become negative unless the business rule explicitly permits it.
4. Representative A cannot access Representative B data.
5. Client-provided role/owner identifiers cannot bypass server authorization.
6. Expiry dates must remain consistent with paid duration.
7. Renewal must not unintentionally shorten or duplicate service.
8. Provider failure must not be recorded as provider success.
9. Re-running an idempotent webhook/Cron operation must not duplicate effects.
10. Financial changes must be atomic.
11. Telegram callback data must not bypass ownership/authorization.
12. AI output must not grant privileges.
13. Reports must reconcile with authoritative transaction/service records.
