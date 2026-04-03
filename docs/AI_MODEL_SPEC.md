# AI Model Spec

Bu belge `ai-worker` tarafinda kullanilacak detection model dosyalari icin beklenen standardi tanimlar.

## Klasor

Model dosyalari:

- `ai-worker/models/`

Spor bazli beklenen adlar:

- `ai-worker/models/football_player_ball.pt`
- `ai-worker/models/basketball_player_ball.pt`
- `ai-worker/models/volleyball_player_ball.pt`

Opsiyonel ortak fallback:

- `ai-worker/models/player_ball.pt`

## Env

```env
AI_WORKER_MODE=pipeline
AI_WORKER_DETECTOR=auto
AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt
AI_WORKER_FOOTBALL_MODEL_PATH=models/football_player_ball.pt
AI_WORKER_BASKETBALL_MODEL_PATH=models/basketball_player_ball.pt
AI_WORKER_VOLLEYBALL_MODEL_PATH=models/volleyball_player_ball.pt
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
- `basketball` -> `ball`
- `volleyball` -> `ball`
- `sports ball` -> `ball`
- `keeper` -> `goalkeeper`
- `rim` -> `hoop`
- `basket` -> `hoop`
- `volleyball_net` -> `net`

Bu esleme kodda:

- [model_registry.py](/C:/Users/Hp/Desktop/PhpstormProjects/scout_api_pr_clean/ai-worker/app/model_registry.py)

## Minimum beklenti

- YOLO `.pt` model
- frame bazli `boxes`
- `cls`, `conf`, `xyxy` alanlari
- spor bazli dosya koyarsan worker otomatik o sporda onu kullanir
- hic model yoksa worker heuristic detector'a duser

## Sonraki seviye

Sonraki asamada ayri modeller de baglanabilir:

- player detector
- ball detector
- keypoint / pose model
- event classifier

Ilk gecis icin pratik sira:

1. futbol icin `football_player_ball.pt`
2. basketbol icin `basketball_player_ball.pt`
3. voleybol icin `volleyball_player_ball.pt`
4. sonra keypoint / pose / court calibration
