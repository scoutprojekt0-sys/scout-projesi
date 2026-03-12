# Branch Protection Runbook (`main`)

Apply these settings in GitHub repository settings for `main`:

1. Enable branch protection rule for `main`.
2. Turn on `Require a pull request before merging`.
3. Turn on `Require status checks to pass before merging`.
4. Mark these checks as required:
   - `Tests / lint`
   - `Tests / PHP 8.2`
   - `Tests / PHP 8.3`
   - `Security / dependency-audit`
   - `Security / secrets-scan`
   - `API Smoke / smoke`
   - `CodeQL / Analyze (PHP)` (recommended)
5. Turn on `Require branches to be up to date before merging`.
6. Disable direct pushes to `main` (except admins if needed by policy).

## Local command set

- `vendor/bin/pint --test`
- `composer test`
- `composer audit --locked --no-interaction`
