# Week 7 Accessibility Checklist

Date: 2026-02-25
Scope: demo and operations UI pages in `resources/views`

## Keyboard and Focus

- [x] Skip links added for major pages.
- [x] Focus-visible style added for links, buttons, inputs, selects and textareas.
- [x] Interactive controls reachable without pointer.

## Semantics and Labels

- [x] Main landmarks have page-level ids and clear headings.
- [x] Status regions use `role="status"` and `aria-live="polite"`.
- [x] Dynamic list containers expose update intent with live regions.

## Async and State Feedback

- [x] Dynamic containers now toggle `aria-busy` during loading.
- [x] Empty, error and loading states stay visible in-page.
- [x] Confirm dialogs remain for destructive actions (delete/revoke).

## Responsive and Readability

- [x] Mobile table behavior remains readable with data labels.
- [x] Action buttons keep minimum touch target on small screens.
- [x] Spacing and helper text reviewed for first-time onboarding.

## Follow-up (Week 8+)

- Add automated axe/Lighthouse checks in CI.
- Add screen-reader regression pass for release candidate.
