# Week 7 Analytics Delivery

Date: 9 Mart 2026

## Endpoints

### `GET /api/analytics/admin-overview`

Auth: `auth:sanctum`

Returns:
- users total and role distribution
- opportunities total/open/closed
- applications total and status distribution
- verified transfer activity for last 30 days

### `GET /api/analytics/team/{teamId}`

Auth: `auth:sanctum`

Returns:
- team opportunity stats (open/closed)
- application funnel (pending/accepted/rejected)
- average applicant rating
- latest applications
- verified transfer volume summary

## Notes

- Team identity is derived from `users.role = team`
- Non-team ids return `404`
- The endpoints are designed to support Week 7 dashboard deep analytics cards
