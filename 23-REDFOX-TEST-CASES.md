# REDFOX PRIORITY TEST CASES

## Authentication
AUTH-001 valid login
AUTH-002 invalid password
AUTH-003 expired/invalid session
AUTH-004 unauthorized role access

## Representative
REP-001 create agent
REP-002 agent A sees own customers
REP-003 agent A cannot see agent B customers
REP-004 agent A cannot modify agent B pricing
REP-005 direct URL IDOR blocked
REP-006 API ownership bypass blocked

## Wallet
WAL-001 valid credit
WAL-002 valid debit
WAL-003 duplicate callback
WAL-004 concurrent debit
WAL-005 negative balance attempt

## Payment
PAY-001 valid payment
PAY-002 wrong amount
PAY-003 replay callback
PAY-004 invalid transaction
PAY-005 provider timeout
PAY-006 duplicate success callback

## Service
SRV-001 create service
SRV-002 provider failure
SRV-003 expiration
SRV-004 renewal
SRV-005 cancellation
SRV-006 duplicate creation attempt

## Telegram
TG-001 identity
TG-002 unauthorized admin command
TG-003 manipulated callback
TG-004 duplicate update
TG-005 purchase
TG-006 renewal

## API
API-001 unauthenticated request
API-002 wrong role
API-003 wrong owner
API-004 malformed input
API-005 injection payload

## Cron
CRON-001 expiration
CRON-002 reminder
CRON-003 synchronization
CRON-004 duplicate/concurrent execution

## UI
UI-001 desktop
UI-002 mobile
UI-003 RTL
UI-004 validation
UI-005 JavaScript errors
