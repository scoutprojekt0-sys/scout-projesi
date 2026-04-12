# AI Discovery Smoke Test

## Purpose

This runbook defines the minimum manual smoke test for `http://127.0.0.1:8000/ai-discovery.html` after UI, API, or AI analysis changes.

## Preconditions

- Laravel app is running on `http://127.0.0.1:8000`
- If `AI_ANALYSIS_MODE=external`, the AI worker is running and reachable
- The database contains at least one player with:
  - `player_video_metrics`
  - a related `video_analyses` row
  - a related `video_clips` row

Recommended local profile:

```env
APP_URL=http://127.0.0.1:8000
AI_ANALYSIS_MODE=external
AI_ANALYSIS_WORKER_BASE_URL=http://127.0.0.1:8010
AI_ANALYSIS_CALLBACK_SECRET=change-me-production
AI_ANALYSIS_ALLOW_MOCK_FALLBACK=false
```

## Smoke Flow

1. Open `http://127.0.0.1:8000/ai-discovery.html`.
   Expected:
   - page renders without JS crash
   - status badges fill in
   - discovery cards and ranking chips load

2. Verify runtime status badges.
   Expected:
   - `Discovery Katmani` shows `Aktif`
   - `AI Ranking` shows `Aktif`
   - `Video Analysis` shows `Giris ile Acik`, `Acik`, or `Worker Hazir Degil`
   - `Worker Mode` matches backend mode

3. Use discovery filters.
   Action:
   - search by a known player name
   - optionally narrow by city and position
   - change sort between `Speed`, `Cross`, and `Movement`
   Expected:
   - result cards update
   - each card shows metrics and video status pills

4. Pick a player from discovery results.
   Expected:
   - player panel updates
   - related videos load
   - if exactly one video exists, it is auto-selected
   - preview card updates with title, date, duration, and platform

5. Change the selected player.
   Expected:
   - previous analysis summary is cleared
   - previous events do not remain on screen
   - new player's videos replace old ones

6. Change the selected video for the same player.
   Expected:
   - stale analysis output disappears
   - if cached result exists for that player/video pair, it auto-renders

7. While logged out, click `Giris Yap ve Analiz Et`.
   Expected:
   - no redirect loop
   - no 401 modal crash
   - notice explains that login is required for analysis

8. While logged in, click `Analizi Baslat`.
   Expected:
   - if cached result exists, cached summary and events open immediately
   - if fresh analysis starts, waiting state is shown
   - if worker completes, summary and events populate
   - if worker fails, a visible error notice appears

9. Refresh the page after a completed analysis.
   Expected:
   - discovery remains public
   - selecting the same player/video restores cached analysis locally

## Fast API Cross-Checks

Use these when the UI looks wrong but backend health is unclear.

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/scouting-search/status
Invoke-RestMethod "http://127.0.0.1:8000/api/scouting-search/rankings?limit=5"
Invoke-RestMethod "http://127.0.0.1:8000/api/scouting-search/discovery?per_page=5"
```

If authenticated analysis must be checked, use a valid bearer token for:

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/video-analyses/{id}
Invoke-RestMethod http://127.0.0.1:8000/api/video-analyses/{id}/events
```

## Failure Patterns

- Empty badges:
  check `/api/scouting-search/status`
- Discovery cards fail to load:
  check `/api/scouting-search/discovery`
- Ranking chips fail to load:
  check `/api/scouting-search/rankings`
- `Worker Hazir Degil`:
  verify `APP_URL`, `AI_ANALYSIS_WORKER_BASE_URL`, and `AI_ANALYSIS_CALLBACK_SECRET`
- Analyze button text is wrong or page throws on load:
  recheck `public/assets/js/ai-video-lab.js` for client-side errors around `syncAnalyzeButtonState`

## Exit Criteria

Smoke test is considered passed when:

- public discovery works while logged out
- authenticated analysis can start
- status badges reflect backend mode correctly
- player and video changes do not leak stale analysis state
- completed or cached analysis renders summary and events
