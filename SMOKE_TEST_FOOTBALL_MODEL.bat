@echo off
setlocal

cd /d "%~dp0ai-worker"

if not exist ".venv\\Scripts\\python.exe" (
  echo [SMOKE] Python sanal ortam bulunamadi.
  exit /b 1
)

".venv\\Scripts\\python.exe" scripts\\smoke_test_model.py %*
