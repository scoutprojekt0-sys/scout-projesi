# AI Worker

Bu servis, Laravel backend'in gonderdigi video analysis islerini alir ve sonucu callback ile geri yollar.

## Ne yapiyor

- `POST /jobs/video-analysis` ile is kabul eder
- background task baslatir
- mock analyzer veya gercek analyzer adapter'i ile payload uretir
- sonucu Laravel callback endpoint'ine yollar
- futbol, basketbol ve voleybol icin sport-aware pipeline calistirir

## Calistirma

```bash
cd ai-worker
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8010
```

## Ortam degiskenleri

- `AI_WORKER_PORT=8010`
- `AI_WORKER_MODE=mock`
- `AI_WORKER_MODE=pipeline`
- `AI_WORKER_DETECTOR=auto`
- `AI_WORKER_CALLBACK_TIMEOUT_SECONDS=20`
- `AI_WORKER_LOG_LEVEL=info`

## Laravel ayarlari

`.env` icine:

```env
AI_ANALYSIS_MODE=external
AI_ANALYSIS_WORKER_BASE_URL=http://127.0.0.1:8010
AI_ANALYSIS_CALLBACK_SECRET=change-me
AI_ANALYSIS_WORKER_TIMEOUT_SECONDS=20
```

## Sonraki adim

`app/analyzers/mock.py` yerine gercek CV modeli kullanan analyzer baglanir.

Hazir pipeline:

- `app/analyzers/pipeline.py`
- frame extraction
- detector adapter
- tracking
- event detection
- metric aggregation
- sport profilleri: `football`, `basketball`, `volleyball`

Gercek modele gecmek icin:

1. `AI_WORKER_MODE=pipeline`
2. `AI_WORKER_DETECTOR=auto`
3. `models/` altina uygun `.pt` dosyasini koy
4. spor bazli model kullanacaksan:
   - `AI_WORKER_FOOTBALL_MODEL_PATH=models/football_player_ball.pt`
   - `AI_WORKER_BASKETBALL_MODEL_PATH=models/basketball_player_ball.pt`
   - `AI_WORKER_VOLLEYBALL_MODEL_PATH=models/volleyball_player_ball.pt`

Davranis:

- model varsa `auto` mod YOLO'ya gecer
- model yoksa otomatik heuristic detector ile devam eder

Model standardi:

- [docs/AI_MODEL_SPEC.md](../docs/AI_MODEL_SPEC.md)
