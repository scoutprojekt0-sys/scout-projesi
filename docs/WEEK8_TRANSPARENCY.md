# Week 8 Transparency Layer

Date: 9 Mart 2026

## Goal

Veri seffafligi katmanini guclendirerek oyuncu kaynak ve guven skorlarini API seviyesinde gorunur hale getirmek.

## Endpoints

### `GET /api/data-quality/source-health`

Returns player source-health KPI summary:
- players_total
- with_source
- missing_source
- low_confidence
- needs_review
- verified
- source_coverage_percent

### `GET /api/data-quality/transparency/players`

Paginated player transparency list with optional filters:
- `verification_status`
- `missing_source=1`
- `max_confidence`
- `position`
- `per_page`

### `GET /api/data-quality/transparency/players/{playerId}`

Player transparency drill-down:
- player source/confidence/verification fields
- latest market-value records (with source and confidence)
- latest transfer records (with source and confidence)

## Notes

- Transparency scope uses `users.role = player`
- Non-player id requests return `404`
- Endpoints are read-only and public under current data-quality prefix
