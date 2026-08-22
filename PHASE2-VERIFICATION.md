# Phase 2 verification

- PHP source parsed: 268 project PHP files, zero parser errors.
- Bash installer syntax: passed.
- Phase 1 security assertions: passed.
- Phase 2 reseller scope assertions: passed.
- Runtime eval scan: no matches outside vendor.
- Disabled TLS verification scan: no matches outside vendor.
- Portal modules: 19 PHP files including central scope and layout libraries.

## Security invariants implemented

1. Every protected portal page loads `portal/lib/Portal.php`.
2. Invoice ownership is checked by scoped `refral` or reseller bot token (`bottype`).
3. Customer ownership is checked server-side before profile/mutation access.
4. A requested target owner must be in the session's computed `scope_ids`.
5. Super reseller scope contains self plus direct children only.
6. Child mutations require `reseller_parent_id = current_super_id`.
7. Wallet transfers lock balances and write a ledger in the same transaction.
8. Permissions are reloaded from the database on each request.

End-to-end tests still require a staging MySQL database, Telegram bots and actual VPN panels/payment callbacks.
