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
AI_WORKER_DETECTOR=auto
AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt
AI_WORKER_FOOTBALL_MODEL_PATH=models/football_player_ball.pt
AI_WORKER_BASKETBALL_MODEL_PATH=models/basketball_player_ball.pt
AI_WORKER_VOLLEYBALL_MODEL_PATH=models/volleyball_player_ball.pt
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

Windows hizli baslatma:

```bat
START_AI_WORKER.bat
START_AI_PIPELINE.bat
```

`START_AI_WORKER.bat`:

- `mock` modda calisir
- local gelistirme icin guvenli fallback verir

`START_AI_PIPELINE.bat`:

- `pipeline` modda calisir
- model varsa otomatik `yolo` detector kullanir
- model yoksa `heuristic` detector ile acilir

Worker `.env` destegi:

- `ai-worker/.env.example` dosyasini `ai-worker/.env` olarak kopyalayabilirsin
- worker artik `ai-worker/.env` dosyasini otomatik okur

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

## 5. Gercek model checklist

1. `ai-worker/models/football_player_ball.pt` dosyasini ekle
2. `pip install -r ai-worker/requirements.txt` ile `ultralytics` dahil bagimliliklari yukle
3. `ai-worker/.env` icinde:

```env
AI_WORKER_MODE=pipeline
AI_WORKER_DETECTOR=auto
AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt
AI_WORKER_FOOTBALL_MODEL_PATH=models/football_player_ball.pt
```

4. Laravel `.env` icinde:

```env
AI_ANALYSIS_MODE=external
AI_ANALYSIS_WORKER_BASE_URL=http://127.0.0.1:8010
AI_ANALYSIS_CALLBACK_SECRET=change-me
```

5. `START_AI_PIPELINE.bat` ile worker'i baslat

Ham video toplama standardi:

- [AI_VIDEO_COLLECTION_GUIDE.md](AI_VIDEO_COLLECTION_GUIDE.md)

## 6. Futbol model egitimi

Dataset yaml:

- `ai-worker/datasets/football_detection.yaml`

Etiketleme guide:

- [AI_LABELING_GUIDE_FOOTBALL.md](AI_LABELING_GUIDE_FOOTBALL.md)

Train:

```bat
TRAIN_FOOTBALL_MODEL.bat --data ai-worker/datasets/football_detection.yaml --device 0
```

Smoke test:

```bat
SMOKE_TEST_FOOTBALL_MODEL.bat --model ai-worker/models/football_player_ball.pt --image C:\path\to\test.jpg
```

## 7. Basketbol model egitimi

Dataset yaml:

- `ai-worker/datasets/basketball_detection.yaml`

Etiketleme guide:

- [AI_LABELING_GUIDE_BASKETBALL.md](AI_LABELING_GUIDE_BASKETBALL.md)

Dataset hazirlama:

```bat
PREPARE_BASKETBALL_DATASET.bat --source-dir C:\path\to\raw-basketball-videos --output-dir C:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean\ai-worker\datasets\basketball
```

Train:

```bat
TRAIN_BASKETBALL_MODEL.bat --data ai-worker/datasets/basketball_detection.yaml --device 0
```

## 8. Voleybol model egitimi

Dataset yaml:

- `ai-worker/datasets/volleyball_detection.yaml`

Etiketleme guide:

- [AI_LABELING_GUIDE_VOLLEYBALL.md](AI_LABELING_GUIDE_VOLLEYBALL.md)

Dataset hazirlama:

```bat
PREPARE_VOLLEYBALL_DATASET.bat --source-dir C:\path\to\raw-volleyball-videos --output-dir C:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean\ai-worker\datasets\volleyball
```

Train:

```bat
TRAIN_VOLLEYBALL_MODEL.bat --data ai-worker/datasets/volleyball_detection.yaml --device 0
```
