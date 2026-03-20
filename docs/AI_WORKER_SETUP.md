# AI Worker Setup

Bu belge, Laravel backend ile dis `ai-worker` servisinin nasil birlikte calisacagini anlatir.

## 1. Laravel

`.env`:

```env
AI_ANALYSIS_MODE=external
AI_ANALYSIS_WORKER_BASE_URL=http://127.0.0.1:8010
AI_ANALYSIS_CALLBACK_SECRET=change-me
AI_ANALYSIS_WORKER_TIMEOUT_SECONDS=20
```

Worker tarafi:

```env
AI_WORKER_MODE=pipeline
AI_WORKER_DETECTOR=heuristic
AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt
AI_WORKER_SAMPLE_EVERY_SECONDS=1
AI_WORKER_MAX_SAMPLE_SECONDS=180
```

Migration:

```bash
php artisan migrate
```

## 2. Worker

```bash
cd ai-worker
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8010
```

## 3. Akis

1. Laravel `POST /api/video-analyses/start`
2. `VideoAnalysisDispatchService` dis worker'a is gonderir
3. Worker `POST /jobs/video-analysis` ile isi kabul eder
4. Worker payload uretir
5. Worker Laravel callback endpoint'ine sonucu yollar
6. Laravel sonucu DB'ye yazar
7. Web polling ile tamamlanan sonucu gosterir

## 4. Gercek modele gecis

`ai-worker/app/analyzers/mock.py` yerine:

- player detection
- ball tracking
- event classification
- clip extraction

boru hattini baglarsin.

Bu repo icinde ilk gercek gecis iskeleti zaten var:

- `ai-worker/app/analyzers/pipeline.py`
- `ai-worker/app/pipeline/extractor.py`
- `ai-worker/app/pipeline/detectors.py`
- `ai-worker/app/pipeline/tracking.py`
- `ai-worker/app/pipeline/events.py`
- `ai-worker/app/pipeline/metrics.py`

Model format detayi:

- [AI_MODEL_SPEC.md](AI_MODEL_SPEC.md)
