# Phase 11 verification

- Ticket attachments are MIME-validated, size-limited, hashed and stored outside direct web access.
- Attachment download rechecks reseller and customer scope.
- Settlement balance reservation/refund/payment is transactional and ledgered.
- IBAN format and mod-97 checksum are validated and full IBAN is encrypted.
- Reseller categories and product mappings are isolated by owner and wired into vpnbot queries.
- Write API requires HMAC, timestamp, nonce, idempotency and optional IP allowlist.
- Service/message/product/settlement writes reuse existing scoped service layers.
- Runtime DDL was removed from operational files and replaced with schema assertions.
- Migration 010 contains final feature and legacy runtime schema.
- 313 project PHP files parsed with zero parser errors.
- Phase 1 through Phase 11 assertions passed.

Final live certification still requires running Migration 010 and transaction/panel/gateway tests on staging.
