$ErrorActionPreference = 'Stop'

$projectRoot = 'C:\xampp\htdocs\sims_smpn4'
$backupRoot = Join-Path $env:USERPROFILE 'Documents\SIMS_Backups'
$logPath = Join-Path $backupRoot 'scheduled-backup.log'

New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
$env:SIMS_APP_ENV = 'local'
$env:SIMS_BACKUP_DIR = $backupRoot
$env:SIMS_MYSQLDUMP = 'C:\xampp\mysql\bin\mysqldump.exe'
$env:SIMS_MYSQL = 'C:\xampp\mysql\bin\mysql.exe'
$env:SIMS_DB_HOST = '127.0.0.1'
$env:SIMS_DB_PORT = '3306'
$env:SIMS_DB_NAME = 'sims_db'
$env:SIMS_DB_USER = 'root'
$env:SIMS_DB_PASS = ''

"[$(Get-Date -Format o)] SIMS backup started" | Add-Content -LiteralPath $logPath
Push-Location $projectRoot
try {
    & 'C:\xampp\php\php.exe' 'tools\backup.php' *>> $logPath
    $backupExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}
"[$(Get-Date -Format o)] SIMS backup exit code: $backupExitCode" | Add-Content -LiteralPath $logPath
exit $backupExitCode
