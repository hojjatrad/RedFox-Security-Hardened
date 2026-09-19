# ARENA RUN INSTRUCTIONS

## AUDIT PHASE

Give Arena:
- GitHub repository
- target branch
- LIVE_URL if available
- test credentials only through a secure mechanism
- test payment/provider credentials where applicable

Start in AUDIT ONLY.

Do not modify source.

Required output:
1. architecture map
2. module map
3. workflow map
4. dependency map
5. defect matrix
6. security findings
7. business findings
8. test coverage gaps
9. repair plan

## REPAIR PHASE

After reviewing the audit, switch to FULL REPAIR.

Use a dedicated repair branch.

For each confirmed defect:
reproduce → fix → regression test → test → re-audit.

## LIVE PHASE

Provide LIVE_URL.

Use test accounts and non-destructive actions.

Compare deployed version with repository.

## RELEASE PHASE

Run final audit.
Review complete diff.
Run tests again.
Do not merge/deploy automatically.

## IMPORTANT

Never paste real production secrets into prompts or repository files.
Use environment/secret storage provided by the execution environment.
