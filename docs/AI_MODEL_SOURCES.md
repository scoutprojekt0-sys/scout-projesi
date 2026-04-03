# AI Model Sources

Bu belge ilk gercek model gecisi icin kaynak secimini sabitler.

## Secim mantigi

- worker cok sporlu
- ilk asamada `player` ve `ball` detection yeterli
- futbol ve basketbol icin hazir acik model/dataset kullanilabilir
- voleybolda public kaynak daha zayif; gerekirse custom train gerekir

## Futbol

Oncelikli hedef:

- `football_player_ball.pt`

Tercih edilen siniflar:

- `player`
- `ball`
- `referee`
- `goalkeeper`

Not:

- `player` ve `ball` zorunlu
- `referee` ve `goalkeeper` opsiyonel ama faydali

## Basketbol

Oncelikli hedef:

- `basketball_player_ball.pt`

Tercih edilen siniflar:

- `player`
- `ball`

Opsiyonel:

- `hoop`

Etiketleme guide:

- [AI_LABELING_GUIDE_BASKETBALL.md](AI_LABELING_GUIDE_BASKETBALL.md)

## Voleybol

Oncelikli hedef:

- `volleyball_player_ball.pt`

Tercih edilen siniflar:

- `player`
- `ball`

Opsiyonel:

- `net`

Not:

- public tarafta sadece top odakli kaynaklar daha yaygin
- kaliteli sonuc icin oyuncu + top etiketli custom dataset birlestirme gerekebilir

Etiketleme guide:

- [AI_LABELING_GUIDE_VOLLEYBALL.md](AI_LABELING_GUIDE_VOLLEYBALL.md)

## Worker davranisi

- `AI_WORKER_DETECTOR=auto`
- spor bazli model varsa onu kullanir
- yoksa `player_ball.pt` fallback
- hic model yoksa heuristic detector

## Hedef dosya adlari

- `ai-worker/models/football_player_ball.pt`
- `ai-worker/models/basketball_player_ball.pt`
- `ai-worker/models/volleyball_player_ball.pt`
- opsiyonel: `ai-worker/models/player_ball.pt`
