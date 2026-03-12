# NextScout Release Notes (v1)

Date: 2026-02-24

## Live URLs
- Landing: https://nextscout-landing.vercel.app
- API: https://scout-api-91m6.onrender.com/api

## Delivered
- Premium single-page landing with high-contrast hero and single CTA.
- Clear role entry chips: Oyuncu, Scout, Menajer, Antrenor, Takim.
- Social proof block + live opportunities block.
- Live news integration via `GET /api/news/live`.
- Landing auto API strategy:
  - query `api_base`
  - `localStorage.nextscout_api_base`
  - `window.NEXTSCOUT_API_BASE`
  - default live API base (`https://scout-api-91m6.onrender.com/api`)
- Topbar API health badge:
  - `API Connected`
  - `API Degraded`

## Backend Notes
- Public endpoint added: `GET /api/news/live`.
- Dockerized deployment flow prepared for cloud platforms.
- CORS configured for landing + local dev origins.

## Verification
- `/up` health endpoint returns success.
- `/api/news/live` returns valid JSON.
- Landing displays live news and relative time from production API.
