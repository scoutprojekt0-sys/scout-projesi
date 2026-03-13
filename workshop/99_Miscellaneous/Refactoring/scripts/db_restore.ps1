param(
    [Parameter(Mandatory=$true)]
    [string]$SqlFile,
    [string]$ProjectRoot = "C:\Users\Hp\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring"
)
if (-not (Test-Path $SqlFile)) { throw "SQL file not found: $SqlFile" }

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
$mysql = "C:\xampp\mysql\bin\mysql.exe"
if (-not (Test-Path $mysql)) { $mysql = "mysql" }

$argList = @("-h", $dbHost, "-P", $dbPort, "-u", $dbUser)
if (-not [string]::IsNullOrEmpty($dbPass)) {
    $argList += "-p$dbPass"
}
$argList += $dbName

Get-Content -Raw $SqlFile | & $mysql @argList

Write-Output ("Restore completed: {0}" -f $SqlFile)