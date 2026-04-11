# AI Video Analysis Local Runbook

## Purpose

This runbook documents the working local setup for the Laravel video analysis flow and the external AI worker callback chain.

## Working Local Env

Laravel `.env`

```env
APP_URL=http://127.0.0.1:8000
AI_ANALYSIS_MODE=external
AI_ANALYSIS_WORKER_BASE_URL=http://127.0.0.1:8010
AI_ANALYSIS_CALLBACK_SECRET=change-me-production
AI_ANALYSIS_WORKER_TIMEOUT_SECONDS=20
```

AI worker `.env`

```env
AI_WORKER_PORT=8010
AI_WORKER_MODE=pipeline
AI_WORKER_DETECTOR=auto
AI_WORKER_SAMPLE_EVERY_SECONDS=2
AI_WORKER_MAX_SAMPLE_SECONDS=10
AI_WORKER_CALLBACK_TIMEOUT_SECONDS=20
AI_WORKER_LOG_LEVEL=info
```

This local profile is optimized for fast end-to-end validation with real football clips.

## Production / Staging Profile

Suggested production-oriented worker values:

```env
AI_WORKER_MODE=pipeline
AI_WORKER_DETECTOR=auto
AI_WORKER_SAMPLE_EVERY_SECONDS=1
AI_WORKER_MAX_SAMPLE_SECONDS=180
AI_WORKER_CALLBACK_TIMEOUT_SECONDS=30
AI_WORKER_LOG_LEVEL=info
```

Suggested Laravel values:

```env
AI_ANALYSIS_MODE=external
AI_ANALYSIS_WORKER_BASE_URL=https://your-worker-host
AI_ANALYSIS_CALLBACK_SECRET=strong-shared-secret
AI_ANALYSIS_WORKER_TIMEOUT_SECONDS=60
```

Use the local profile for quick feedback and debugging. Use the production/staging profile for fuller analysis coverage.

## Start Order

Laravel:

```powershell
cd E:\PhpstormProjects\scout_api_pr_clean
php artisan serve --host=127.0.0.1 --port=8000
```

AI worker:

```powershell
cd E:\PhpstormProjects\scout_api_pr_clean\ai-worker
python -m uvicorn app.main:app --host 127.0.0.1 --port 8010
```

## Health Check

```powershell
Invoke-RestMethod http://127.0.0.1:8010/health
```

Expected:

- `mode: pipeline`
- `detector: auto`

## Required Database State

Run migrations before callback testing:

```powershell
cd E:\PhpstormProjects\scout_api_pr_clean
php artisan migrate
```

This is required because the callback persistence uses the sport-specific metric columns added by:

- `2026_04_10_140000_add_sport_specific_scores_to_player_video_metrics_table`

## Success Criteria

After a real external analysis run:

- `video_analyses.status = completed`
- `video_analyses.worker_status = completed`
- `video_analyses.external_job_id` is populated
- `player_video_metrics` row is created
- `video_analysis_events` rows are created in heuristic mode
- `video_analysis_targets` row is created

Useful DB check:

```powershell
cd E:\PhpstormProjects\scout_api_pr_clean
php artisan tinker --execute="echo json_encode(App\Models\VideoAnalysis::query()->latest('id')->first()?->toArray(), JSON_PRETTY_PRINT);"
```

## Known Notes

- `auto` or `yolo` mode worked end-to-end with real local football footage.
- The sample public MP4 used in early testing produced `0` detections.
- That `track_count: 0` result was not a callback or tracking bug; it was a detector/video mismatch.
- Real model evaluation should be done with actual football, basketball, or volleyball footage.

## Files Changed During Validation

- `ai-worker/app/services.py`
- `ai-worker/app/analyzers/pipeline.py`
- `ai-worker/.env`

## Troubleshooting

If callback fails:

- check worker terminal logs
- check `storage/logs/laravel.log`
- confirm `AI_ANALYSIS_CALLBACK_SECRET` matches the callback header value
- confirm Laravel is reachable at `APP_URL`

If metrics insert fails:

- run `php artisan migrate`
- verify the four sport-specific columns exist on `player_video_metrics`

If worker submit succeeds but analysis is empty:

- verify `AI_WORKER_DETECTOR`
- test with a real sports video
- use `heuristic` for local demo validation
