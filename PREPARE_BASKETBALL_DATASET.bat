@echo off
setlocal

cd /d "%~dp0ai-worker"

if not exist ".venv\\Scripts\\python.exe" (
  echo [DATASET] Python sanal ortam bulunamadi.
  exit /b 1
)

".venv\\Scripts\\python.exe" scripts\\prepare_dataset.py --sport basketball %*
