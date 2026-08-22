# Runtime DDL policy

`CREATE TABLE`, `ALTER TABLE` and `DROP TABLE` are forbidden during normal web, bot, API and cron execution.

Allowed locations:

- `migrations/*.sql` and `bin/migrate.php`;
- `table.php` only when invoked by Installer/CLI migration mode;
- backup SQL generation and explicit restore tooling;
- `addFieldToTable` implementation, guarded by `REDFOX_SCHEMA_MIGRATION_MODE` and otherwise reduced to a read-only schema assertion.

`table.php` is denied by Apache/Nginx and returns 404 for web requests without the installer-only constant. After Migration 010, the runtime MySQL account can be reduced to SELECT/INSERT/UPDATE/DELETE. Use `bin/generate-runtime-grants.php` to generate a reviewable GRANT script.
