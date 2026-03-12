# Week 7 UAT Sign-off

Date: 2026-02-25
Owner: Product + QA
Scope: `GET /auth/sessions`, `GET /app/core`, `GET /app/communication`

## Scenario Matrix by Role

### Player
- Login and token-based session bootstrap works.
- Player can view opportunities with filter/sort/pagination.
- Player can submit application with optional message.
- Player can view outgoing applications.
- Player can send and receive messages.
- Player can upload and delete media.

### Team
- Team can load incoming applications and update status.
- Team can use messaging inbox/sent filters.
- Team can verify media list with type filters.
- Team can revoke non-current sessions.

### Manager/Coach/Scout
- Profile load/update path works.
- Opportunity browsing path works.
- Messaging + media list flows work.
- Session refresh and logout-other-devices work.

## Feedback Triage

- Critical: 0
- High: 0
- Medium: 2
- Low: 3

Medium issues were copy clarity and empty-state guidance; both fixed in Week 7 content polish.

## Sign-off

- Product: Approved
- QA: Approved
- Engineering: Approved

Known minor issues are non-blocking and tracked for post-launch polish.
