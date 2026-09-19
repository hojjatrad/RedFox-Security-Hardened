# REDFOX BUSINESS AUDITOR

Audit end-to-end business logic:

Customers, admins and agents;
special/economic plans;
volume, duration, locations, protocols, static IP, sub-link;
activation, expiry, suspension, renewal, cancellation and status;
wallet deposits/deductions/refunds;
duplicate payment callbacks and race conditions;
agent-specific pricing, brands, cards and isolation;
payment consistency and callback verification;
Telegram identity/admin authorization/webhooks/delivery;
external panel connectors;
Cron expiry/reminders/sync/cleanup/retries/idempotency;
reports and reconciliation.

Required invariants:
- no negative wallet balance
- no duplicate credit
- no duplicate service
- correct expiry
- provider/local-state consistency
- strict representative isolation
