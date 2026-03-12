# Scout API 2-Week Execution Plan

## Week 1: Foundation

### 1) Project structure, env separation, config cleanup
- Create clear docs structure:
  - `docs/adr`
  - `docs/runbooks`
  - `docs/security`
  - `docs/api`
- Review `.env.example` and define required vars by environment:
  - local
  - CI
  - production
- Consolidate custom settings in a single config file (example: `config/scout.php`).
- Remove dead config keys and any hardcoded secret-like values.

Definition of Done
- `README.md` includes env matrix and minimum required setup.
- App boots in local and CI using documented env values only.
- No secret is committed in code/config.

### 2) Design system v1
- Define token set:
  - color tokens (primary, neutral, success, warning, danger)
  - typography scale (h1-h6, body, caption)
  - spacing scale (4/8 based)
- Write component rules:
  - buttons (variants + disabled/loading)
  - inputs (default/error/help text)
  - feedback (alert/toast/empty state)
- Apply tokens to auth pages for consistency.

Definition of Done
- One source of truth for tokens exists.
- Auth screens follow token/component rules.
- No ad-hoc style values in auth pages.

### 3) Mandatory quality gates
- Ensure local checks are documented:
  - `./vendor/bin/pint --test`
  - `php artisan test`
  - security checks already present in workflow
- Enforce merge gates on `main`:
  - required CI checks
  - no direct push
- Add PR checklist template:
  - tests
  - lint/style
  - migration impact
  - security impact

Definition of Done
- PR merge is blocked when checks fail.
- Branch protection is active on `main`.
- Team runs checks from one documented command list.

## Week 2: Auth + Session Maturity

### 1) Auth flow UX improvements
- Improve login/register/reset feedback:
  - clear validation messaging
  - consistent error states
  - stable success redirects
- Add edge-state handling:
  - invalid token
  - expired reset token
  - throttling feedback

Definition of Done
- Login/register/reset flow is consistent end-to-end.
- API error responses map to predictable frontend messages.

### 2) Session/device management screens
- Build UI backed by existing endpoints:
  - list active sessions/devices
  - revoke selected session
  - revoke all except current
  - refresh current session token
- Add confirmation dialogs for destructive session actions.
- Log session revocation events for auditability.

Definition of Done
- Users manage sessions fully via UI.
- Revocation events are logged and test-verified.

### 3) Expand security test coverage
- Add feature tests for:
  - auth throttling / brute-force behavior
  - token rotation and old-token invalidation
  - cross-user session revocation blocking
  - authorization boundaries on auth/session routes
- Add negative-path tests into required CI checks.

Definition of Done
- Critical auth/session attack paths are covered by automated tests.
- Known-bad scenarios fail reliably in CI.

## Suggested Execution Order

Week 1
1. Env/config inventory + cleanup list
2. Docs structure + env matrix
3. Design token set + component rules
4. Auth UI token alignment
5. CI gate hardening + PR checklist

Week 2
1. Login/register UX polish
2. Reset UX + edge cases
3. Session/device UI + endpoint wiring
4. Audit logging + confirmations
5. Security test expansion + stabilization
