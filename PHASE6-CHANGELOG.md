# Phase 6 changelog — 1.5.0

- Centralized all DirectPayment calls behind payment_confirm_paid.
- Migrated legacy gateway, cron, admin receipt and in-bot payment paths.
- Fixed premature Telegram Stars fulfillment at pre_checkout_query.
- Disabled unsafe automatic retry of ambiguous paid crypto orders.
- Added integrity checker and optional unique-constraint enforcer.
- Added production Nginx/PHP/Cron examples.
- Added atomic release deploy and code-only rollback scripts.
- Added Migration 006 performance indexes and Phase 6 assertions.
