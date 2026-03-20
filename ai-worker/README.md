# AI Worker

Bu servis, Laravel backend'in gonderdigi video analysis islerini alir ve sonucu callback ile geri yollar.

## Ne yapiyor

- `POST /jobs/video-analysis` ile is kabul eder
- background task baslatir
- mock analyzer veya gercek analyzer adapter'i ile payload uretir
- sonucu Laravel callback endpoint'ine yollar

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
- `AI_WORKER_DETECTOR=heuristic`
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

Gercek modele gecmek icin:

1. `AI_WORKER_MODE=pipeline`
2. `AI_WORKER_DETECTOR=yolo`
3. `AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt`

Model standardi:

- [docs/AI_MODEL_SPEC.md](../docs/AI_MODEL_SPEC.md)
