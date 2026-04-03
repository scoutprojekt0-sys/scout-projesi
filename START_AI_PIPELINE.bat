@echo off
setlocal

cd /d "%~dp0ai-worker"

if not exist ".venv\\Scripts\\python.exe" (
  echo [AI PIPELINE] Python sanal ortam bulunamadi: ai-worker\\.venv
  echo [AI PIPELINE] Once README veya docs\\AI_WORKER_SETUP.md adimlarini tamamlayin.
  exit /b 1
)

if not exist ".env" if exist ".env.example" (
  copy /Y ".env.example" ".env" >nul
)

set "AI_WORKER_PORT=8010"
set "AI_WORKER_MODE=pipeline"
set "AI_WORKER_DETECTOR=auto"

if exist "models\\football_player_ball.pt" (
  set "AI_WORKER_YOLO_MODEL_PATH=models/football_player_ball.pt"
  set "AI_WORKER_FOOTBALL_MODEL_PATH=models/football_player_ball.pt"
  if exist "models\\basketball_player_ball.pt" set "AI_WORKER_BASKETBALL_MODEL_PATH=models/basketball_player_ball.pt"
  if exist "models\\volleyball_player_ball.pt" set "AI_WORKER_VOLLEYBALL_MODEL_PATH=models/volleyball_player_ball.pt"
) else if exist "models\\player_ball.pt" (
  set "AI_WORKER_YOLO_MODEL_PATH=models/player_ball.pt"
) else (
  echo [AI PIPELINE] YOLO model dosyasi bulunamadi. Heuristic detector ile devam ediliyor.
)

echo [AI PIPELINE] Pipeline worker baslatiliyor on port %AI_WORKER_PORT%
echo [AI PIPELINE] Mode=%AI_WORKER_MODE% Detector=%AI_WORKER_DETECTOR%
".venv\\Scripts\\python.exe" -m uvicorn app.main:app --host 127.0.0.1 --port %AI_WORKER_PORT%
