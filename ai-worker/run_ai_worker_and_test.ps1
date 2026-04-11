# Run AI worker (pipeline) and do quick health + sample jobs test
# Usage: Open PowerShell, go to ai-worker folder and run: .\run_ai_worker_and_test.ps1
# Replace WEBHOOK_URL with your callback (e.g. https://webhook.site/XXXX)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

Write-Host "Working dir: $root"

# Create venv if missing
if (-not (Test-Path .venv)) {
    Write-Host "Creating virtualenv..."
    python -m venv .venv
}

# Activate
Write-Host "Activating venv..."
. .\.venv\Scripts\Activate.ps1

# Install requirements (skip if already installed)
if (-not $env:AI_WORKER_SKIP_INSTALL) {
    Write-Host "Installing requirements (this may take several minutes)..."
    .\.venv\Scripts\python.exe -m pip install --upgrade pip
    .\.venv\Scripts\python.exe -m pip install -r requirements.txt
} else {
    Write-Host "Skipping pip install (AI_WORKER_SKIP_INSTALL is set)"
}

# Set envs
$env:AI_WORKER_MODE = 'pipeline'
$env:AI_WORKER_DETECTOR = 'auto'
# Default model path uses repo ai-worker/yolov8n.pt (change if needed)
$env:AI_WORKER_YOLO_MODEL_PATH = Join-Path $root 'yolov8n.pt'

Write-Host "Starting uvicorn (background)..."
$proc = Start-Process -FilePath .\.venv\Scripts\python.exe -ArgumentList '-m','uvicorn','app.main:app','--host','127.0.0.1','--port','8010' -WindowStyle Hidden -PassThru
Write-Host "uvicorn started PID: $($proc.Id)"

# Wait for health
$healthUrl = 'http://127.0.0.1:8010/health'
$maxWait = 60
$interval = 2
$elapsed = 0

Write-Host "Waiting for /health (timeout ${maxWait}s)..."
while ($elapsed -lt $maxWait) {
    try {
        $resp = Invoke-RestMethod -Uri $healthUrl -UseBasicParsing -TimeoutSec 5
        Write-Host "HEALTH OK:" -ForegroundColor Green
        $resp | ConvertTo-Json -Depth 5
        break
    } catch {
        Start-Sleep -Seconds $interval
        $elapsed += $interval
    }
}

if ($elapsed -ge $maxWait) {
    Write-Host "Timed out waiting for /health. Check logs or run uvicorn in foreground for details." -ForegroundColor Red
    Write-Host "To see uvicorn logs, run: .\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8010" -ForegroundColor Yellow
    exit 1
}

# Send sample jobs for three sports. Replace WEBHOOK_URL with your callback (or use webhook.site)
$webhook = 'https://webhook.site/REPLACE_ME'
$sampleVideo = 'https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_1mb.mp4'

$sports = @('football','basketball','volleyball')
foreach ($sport in $sports) {
    $payload = @{ analysis_id = (Get-Random -Maximum 999999); video_clip_id = (Get-Random -Maximum 999999); sport = $sport; video_url = $sampleVideo; requested_by = 1; callback_url = $webhook; callback_secret = 'test-secret' } | ConvertTo-Json -Depth 5
    Write-Host "Posting test job for $sport..."
    try {
        $r = Invoke-RestMethod -Uri 'http://127.0.0.1:8010/jobs/video-analysis' -Method POST -Body $payload -ContentType 'application/json' -TimeoutSec 30
        Write-Host ("Job response for " + $sport + ":") -ForegroundColor Green
        $r | ConvertTo-Json -Depth 5
    } catch {
        Write-Host ("Failed posting job for " + $sport + ": " + $_) -ForegroundColor Red
    }
}

Write-Host "Done. If you used webhook.site, open it to see callbacks. To stop uvicorn process: Stop-Process -Id $($proc.Id)" -ForegroundColor Cyan
