# Scout API Week 3-4 Execution Plan

## Week 3: Core Product UX

### 1) Core flow screens: opportunities, application, profile
- Build/complete screens for:
  - opportunities list/detail
  - application flow (apply, outgoing/incoming state visibility)
  - profile view/edit (role-aware fields)
- Enforce same UI language from `DESIGN_SYSTEM_V1`.
- Keep API response mapping explicit in UI state adapters.

Definition of Done
- Main user journeys can be completed end-to-end from UI.
- Profile and application states are visible and actionable.
- No raw/unmapped backend error is shown directly to users.

### 2) Filter, sort, pagination UX improvements
- Add filter model for opportunities/profiles:
  - role/position/city/status based filters
  - deterministic sort options
- Pagination improvements:
  - clear page size and total state
  - preserve query params on navigation
  - empty filter result feedback
- Add URL-query sync for sharable filter state.

Definition of Done
- Filters, sorting, and pagination are predictable and stable.
- Back/forward browser navigation preserves list state.
- Empty results and invalid query states are handled gracefully.

### 3) Mobile behavior and responsive details
- Validate all main screens on mobile breakpoints.
- Improve touch targets and sticky actions where needed.
- Standardize responsive table/list fallback (cards on small screens).

Definition of Done
- Core screens are fully usable on mobile without layout breaks.
- Actions remain reachable and readable on small viewport widths.

## Week 4: Messaging + Media

### 1) Finish messaging and media UX + API parity
- Messaging UX:
  - inbox/sent/compose flows
  - status transitions and permission-aware actions
- Media UX:
  - upload/list/delete lifecycle
  - preview/error fallback behavior
- Validate endpoint parity:
  - request/response contracts match UI expectations
  - permission errors map to clear UX messages

Definition of Done
- Messaging and media flows are complete and consistent.
- API-driven state changes reflect immediately in UI.

### 2) Standardize empty/error/loading states
- Introduce reusable UI primitives for:
  - loading skeleton/spinner
  - empty state with next action
  - inline and page-level error states
- Replace ad-hoc states across auth/core/messaging/media screens.

Definition of Done
- All major screens use the same state primitives.
- No silent failure path remains in primary user flows.

### 3) Expand E2E test coverage
- Extend Postman/Newman collection for:
  - opportunities/applications/profile journey
  - messaging/media happy + negative paths
  - auth/session regression checks from Week 2
- Add CI-required E2E stage for critical route set.

Definition of Done
- Critical product journeys are covered by automated E2E.
- CI blocks merge on critical E2E failures.

## Suggested Execution Order

Week 3
1. Screen inventory + journey map (opportunities/applications/profile)
2. API-to-UI mapping and missing contract notes
3. Filter/sort/pagination implementation
4. Mobile responsive hardening
5. Regression sweep on core flows

Week 4
1. Messaging UX completion
2. Media lifecycle completion
3. Unified empty/error/loading states
4. E2E expansion (happy + negative)
5. CI gate updates and stabilization

## Tracking Metrics

- Core journey completion rate (opportunity -> apply -> follow-up)
- List interaction success (filter/sort/pagination without reset errors)
- Mobile error rate and layout break incidents
- Media upload success ratio
- Messaging delivery/visibility consistency
- E2E first-pass success rate in CI
