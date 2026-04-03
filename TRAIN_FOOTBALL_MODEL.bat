@echo off
setlocal

cd /d "%~dp0ai-worker"

if not exist ".venv\\Scripts\\python.exe" (
  echo [TRAIN] Python sanal ortam bulunamadi.
  exit /b 1
)

".venv\\Scripts\\python.exe" scripts\\train_football_model.py %*
