param(
    [string]$ProjectRoot = "C:\Users\Hp\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring",
    [string]$OutputDir = "",
    [switch]$WithEnv
)

$envFile = Join-Path $ProjectRoot ".env"
if (-not (Test-Path $envFile)) { throw ".env not found: $envFile" }

$map = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#") -or -not $line.Contains("=")) { return }
    $parts = $line.Split("=", 2)
    $key = $parts[0].Trim()
    $value = $parts[1].Trim().Trim('"').Trim("'")
    $map[$key] = $value
}

$dbHost = $map["DB_HOST"]
$dbPort = $map["DB_PORT"]
$dbName = $map["DB_NAME"]
$dbUser = $map["DB_USER"]
$dbPass = if ($map.ContainsKey("DB_PASSWORD")) { $map["DB_PASSWORD"] } else { "" }

if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $ProjectRoot "backups"
}
if (-not (Test-Path $OutputDir)) { New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null }

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$sqlFile = Join-Path $OutputDir ("db-{0}.sql" -f $timestamp)

$mysqldump = "C:\xampp\mysql\bin\mysqldump.exe"
if (-not (Test-Path $mysqldump)) {
    $mysqldump = "mysqldump"
}

$argList = @("-h", $dbHost, "-P", $dbPort, "-u", $dbUser)
if (-not [string]::IsNullOrEmpty($dbPass)) {
    $argList += "-p$dbPass"
}
$argList += @("--single-transaction", "--routines", "--triggers", $dbName)

& $mysqldump @argList | Out-File -FilePath $sqlFile -Encoding utf8

if (-not (Test-Path $sqlFile)) { throw "Backup failed: $sqlFile not created" }

if ($WithEnv) {
    Copy-Item -Path $envFile -Destination (Join-Path $OutputDir ("env-{0}.bak" -f $timestamp)) -Force
}

# Keep only the latest 7 SQL backups.
$sqlBackups = Get-ChildItem -Path $OutputDir -Filter "db-*.sql" -File | Sort-Object LastWriteTime -Descending
if ($sqlBackups.Count -gt 7) {
    $sqlBackups | Select-Object -Skip 7 | ForEach-Object { Remove-Item -Path $_.FullName -Force }
}

Write-Output ("Backup created: {0}" -f $sqlFile)
