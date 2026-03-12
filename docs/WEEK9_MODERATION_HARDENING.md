# Week 9 Moderation Hardening

Date: 9 Mart 2026

## Scope

Week 9 odagi, moderasyon akisinda operasyonel guvenlik ve yetki sertlestirmesidir.

## Changes

### 1) Role-based moderation authorization

`ModerationController` now denies moderation endpoints for users without reviewer privileges.

Allowed if any of the following is true:
- `editor_role` in: `reviewer`, `senior_reviewer`, `admin`
- legacy staff role in: `manager`, `coach`, `scout`

Protected endpoints:
- `GET /api/moderation`
- `GET /api/moderation/{id}`
- `POST /api/moderation/{id}/approve`
- `POST /api/moderation/{id}/reject`
- `POST /api/moderation/{id}/flag`
- `GET /api/moderation/stats`

### 2) Critical moderation permission gate

Critical queue items (`priority=critical`) now require elevated permission:
- `can_verify_critical = true`
or
- `editor_role` in: `senior_reviewer`, `admin`

### 3) Dual-approval hardening

`ModerationQueue::approve()` logic fixed:
- first reviewer records initial approval and item stays `pending`
- second approval requires a different reviewer
- same reviewer cannot provide second approval
- second approval finalizes item as `approved`

### 4) Dual-approval permission gate

When an item already has first approval and `requires_dual_approval=true`, second approval requires:
- `can_dual_approve = true`
or
- `editor_role` in: `senior_reviewer`, `admin`

## Tests

`tests/Feature/Week9ModerationHardeningTest.php`

Covers:
- non-reviewer denied from moderation list
- dual approval needs two distinct reviewers
- critical items need critical permission
