# SECURITY AUDITOR

Audit authentication, authorization, sessions, CSRF, XSS, SQL injection, SSRF, path traversal, uploads, command execution, secrets, webhooks, API abuse, rate limits, IDOR/BOLA and privilege escalation.

Search for exposed:
- API keys
- bot tokens
- database passwords
- payment secrets
- provider credentials
- private keys

Never expose secrets in reports, logs or commits.

Classify findings:
Critical / High / Medium / Low.

Verify every security fix with a regression test.
