Beklenen dataset yapisi:

- `ai-worker/datasets/football/images/train`
- `ai-worker/datasets/football/images/val`
- `ai-worker/datasets/football/images/test`
- `ai-worker/datasets/football/labels/train`
- `ai-worker/datasets/football/labels/val`
- `ai-worker/datasets/football/labels/test`

Hazirlama:

- raw videolari bir klasore koy
- sonra:

```bat
PREPARE_FOOTBALL_DATASET.bat --source-dir C:\path\to\raw-football-videos
```

Bu komut:

- videolardan frame cikarir
- `train / val / test` split yapar
- bos YOLO label dosyalari olusturur
- `manifest.csv` yazar

Uretilen manifest:

- `ai-worker/datasets/football/manifest.csv`

Etiketleme araci:

- CVAT
- Label Studio
- Roboflow Annotate
- herhangi bir YOLO bbox editor

Etiketleme standardi:

- [AI_LABELING_GUIDE_FOOTBALL.md](../../docs/AI_LABELING_GUIDE_FOOTBALL.md)

YOLO etiket sirasi:

- `0 player`
- `1 ball`
- `2 goalkeeper`
- `3 referee`

Ilk gecis icin minimum:

- `player`
- `ball`

Dosya:

- dataset config: `ai-worker/datasets/football_detection.yaml`
- dataset config: `ai-worker/datasets/basketball_detection.yaml`
- dataset config: `ai-worker/datasets/volleyball_detection.yaml`
- dataset prep script: `ai-worker/scripts/prepare_football_dataset.py`
- train script: `ai-worker/scripts/train_football_model.py`
- train script: `ai-worker/scripts/train_basketball_model.py`
- train script: `ai-worker/scripts/train_volleyball_model.py`
- smoke test script: `ai-worker/scripts/smoke_test_model.py`

Opsiyonel event validation:

- `ai-worker/datasets/<sport>/event_validation.json`

Beklenen format:

```json
{
  "cases": [
    {
      "case_id": "football-pass-001",
      "video_path": "E:/datasets/football/events/pass_001.mp4",
      "sport": "football",
      "analysis_type": "scout_mvp",
      "tolerance_seconds": 2,
      "expected_events": [
        { "event_type": "pass", "start_second": 14, "end_second": 16 },
        { "event_type": "shot", "start_second": 41, "end_second": 43 }
      ]
    }
  ]
}
```

Bu dosya varsa `validate_sport_model.py` ek olarak event precision / recall / f1 ve event-type bazli metrikler uretir.
