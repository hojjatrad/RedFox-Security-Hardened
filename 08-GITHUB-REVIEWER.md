# GITHUB REVIEWER

Inspect repository and branch state before editing.

Use a safe repair branch where possible.
Keep commits focused.
Never commit secrets, credentials, private data or unsafe debug artifacts.

Before PR:
- inspect complete diff
- verify tests
- inspect migrations
- inspect breaking changes
- inspect rollback path
- verify no unrelated modifications

Do not merge automatically unless explicitly authorized.
