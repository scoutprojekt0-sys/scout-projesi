@echo off
setlocal

cd /d "%~dp0ai-worker"

if not exist ".venv\\Scripts\\python.exe" (
  echo [VAL] Python sanal ortam bulunamadi.
  exit /b 1
)

".venv\\Scripts\\python.exe" scripts\\validate_sport_model.py --sport volleyball %*
