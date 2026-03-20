# AI Model Spec

Bu belge `ai-worker` tarafinda kullanilacak detection model dosyasi icin beklenen standardi tanimlar.

## Klasor

Model dosyalari:

- `ai-worker/models/`

Ornek:

- `ai-worker/models/player_ball.pt`

## Env

```env
AI_WORKER_MODE=pipeline
AI_WORKER_DETECTOR=yolo
AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt
```

## Beklenen siniflar

Model en az su siniflardan birini uretmeli:

- `player`
- `ball`

Desteklenen alias'lar:

- `person` -> `player`
- `athlete` -> `player`
- `football` -> `ball`
- `soccer_ball` -> `ball`
- `keeper` -> `goalkeeper`

Bu esleme kodda:

- [model_registry.py](/C:/Users/Hp/Desktop/PhpstormProjects/scout_api_pr_clean/ai-worker/app/model_registry.py)

## Minimum beklenti

- YOLO `.pt` model
- frame bazli `boxes`
- `cls`, `conf`, `xyxy` alanlari

## Sonraki seviye

Sonraki asamada ayri modeller de baglanabilir:

- player detector
- ball detector
- keypoint / pose model
- event classifier

Ama ilk production gecisi icin tek `player_ball.pt` yeterlidir.
