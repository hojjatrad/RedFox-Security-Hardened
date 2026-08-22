# Phase 3 verification

## Implemented invariants

- Service operations require a server-side scoped invoice.
- Only users with `extend_user` permission can mutate service state.
- Every operation has a globally unique idempotency key.
- Funds are reserved atomically before remote API calls.
- Definite remote failure compensates the reserved balance and writes a refund ledger entry.
- Once the remote panel reports success, database uncertainty never triggers an automatic refund; it enters `needs_reconcile`.
- Automatic retries do not re-call a remote panel for an existing idempotency key.
- Duplicate config usernames across invoices block automated mutation.
- Manual reconciliation is administrator-only and audited.
- A fresh `funds_reserved` operation cannot be manually refunded for 15 minutes.
- Custom reseller products are selected by exact reseller owner ID.
- Product callbacks in vpnbot reject products owned by another reseller.
- Panel credential encryption is authenticated and fail-closed when the key is missing.
- Legacy plaintext credentials remain readable until the explicit migration command is run.
- The migration runner serializes execution with a MySQL advisory lock and rejects checksum drift.

## Static verification

- 273 project PHP files parsed with zero parser errors.
- Phase 1, Phase 2 and Phase 3 static regression assertions passed.
- Bash installer syntax passed.
- No runtime eval remained outside vendor.
- No explicitly disabled TLS verification remained outside vendor.

## Not executable without staging dependencies

- Real panel API mutations for every connector type.
- Payment callback and bank/crypto gateway round trips.
- MySQL migration execution against the user's real historical schema.
- Apache/PHP-FPM environment propagation of `REDFOX_MASTER_KEY`.
- Crash-injection tests between remote success and local finalization.
