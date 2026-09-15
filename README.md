# Red Fox — Arena Skills Pack (PHP 8.4)

This package contains the complete 17-skill set for auditing, repairing, testing and releasing the Red Fox application with Arena Agent Mode.

## Structure
All skills are under `.arena/`.

## Important
Arena should be explicitly instructed to read every `.md` file under `.arena` and treat them as mandatory instructions. Do not rely on automatic discovery.

## Recommended flow
1. Connect GitHub repository and branch in Arena Agent Mode.
2. Make `.arena` available in the repository or upload the files.
3. Run AUDIT ONLY first.
4. Review the report.
5. Then run FULL REPAIR.
6. Review Diff and Checks.
7. Create/review the PR and deploy.
