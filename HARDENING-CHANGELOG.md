# Hardening changelog — 1.0.1-redfox-security

- Added `lib/Security.php`.
- Hardened admin/reseller sessions, CSRF, login rate limits and 2FA attempts.
- Migrated admin authentication to password hashes with transparent legacy upgrade.
- Added expiring Telegram Mini App initData and bearer sessions.
- Separated integration secrets from administrator passwords.
- Enabled TLS certificate verification by default.
- Replaced runtime eval assemblers with release-built compiled modules.
- Added non-destructive SQL migration and static security regression test.
- Normalized installer slug paths and version metadata.
