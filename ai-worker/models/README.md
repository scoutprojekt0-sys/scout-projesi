Beklenen model dosyalari:

- `football_player_ball.pt`
- `basketball_player_ball.pt`
- `volleyball_player_ball.pt`

Opsiyonel ortak fallback:

- `player_ball.pt`

Worker `AI_WORKER_DETECTOR=auto` modunda calisir:

- spor bazli model varsa onu kullanir
- yoksa `player_ball.pt` varsa onu kullanir
- hic model yoksa heuristic detector'a duser
