# Release Candidate Checklist

Date: 2026-02-25
Release Window: Week 8

## Build and Test Gates

- [x] `tests.yml` green on `main`
- [x] `security.yml` green on `main`
- [x] `codeql.yml` green on default branch
- [x] `api-smoke.yml` green with extended Newman coverage
- [x] Week 7 UI polish tests pass (`Week7UiPolishTest`)

## Product and UX Gates

- [x] Role-based UAT sign-off complete
- [x] Core onboarding copy reviewed
- [x] Accessibility checklist completed for launch scope
- [x] Responsive behavior verified on mobile and desktop breakpoints

## Security and Reliability Gates

- [x] Security final checklist completed
- [x] Pentest-style scenario tests added and passing
- [x] Backup/restore runbook available
- [x] Rollback runbook available
- [x] Request metrics and ops logging active

## Deployment Gates

- [x] Pre-prod dry run completed
- [x] Migration compatibility verified
- [x] Environment variables validated
- [x] Rollback commit target identified

## Go/No-Go

- Decision: GO
- Approved by: Engineering, QA, Product
