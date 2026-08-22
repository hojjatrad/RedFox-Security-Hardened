# Phase 8 verification

- Recovery codes are hashed, one-time and atomically consumed.
- Super-reseller lockout has an auditable offline recovery path.
- Update signatures support trusted key rings and explicit stable/beta channels.
- Unknown key IDs and channel mismatches are rejected.
- Update installation requires an authenticated encrypted database backup.
- Maintenance mode protects web traffic during migration and release switch.
- Backup restore verifies checksum and secretstream authentication before mysql import.
- 299 project PHP files parsed with zero parser errors.
- Phase 1 through Phase 8 static assertions passed.

Live restore drills, key rotation and update crash tests require staging infrastructure.
