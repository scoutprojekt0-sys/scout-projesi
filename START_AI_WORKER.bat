@echo off
setlocal

cd /d "%~dp0ai-worker"

if not exist ".venv\\Scripts\\python.exe" (
  echo [AI WORKER] Python sanal ortam bulunamadi: ai-worker\\.venv
  echo [AI WORKER] Once README veya docs\\AI_WORKER_SETUP.md adimlarini tamamlayin.
  exit /b 1
)

if not exist ".env" if exist ".env.example" (
  copy /Y ".env.example" ".env" >nul
)

set "AI_WORKER_PORT=8010"
set "AI_WORKER_MODE=mock"
set "AI_WORKER_DETECTOR=heuristic"

echo [AI WORKER] Mock worker baslatiliyor on port %AI_WORKER_PORT%
".venv\\Scripts\\python.exe" -m uvicorn app.main:app --host 127.0.0.1 --port %AI_WORKER_PORT%
