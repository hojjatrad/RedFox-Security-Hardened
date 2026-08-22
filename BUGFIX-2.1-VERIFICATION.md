# Verification 2.1

- Full-project unsigned ZIPs are recognized only in explicit compatibility mode.
- Signed packages retain Ed25519 and per-file hash verification.
- Upload, server folder and GitHub Release sources are supported.
- In-place updates are diff-based and preserve runtime/customer data.
- Mandatory encrypted DB backup and per-file backup run before replacement.
- Atomic temp-file replacement and file rollback are implemented.
- GitHub update checking stores metadata and drives the admin notification badge.
- Passargard web creation is normalized to marzban version 1 and requires reference-user sync.
- Legacy channels gain an auto-increment ID through Migration 011.
- 320 project PHP files parsed with zero parser errors.
