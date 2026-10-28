param(
    [string]$BackupDirectory = (Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'DisBasuraBackups')
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$UploadDirectory = Join-Path $ProjectRoot 'uploads'
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$StageDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('disbasura-backup-' + $Stamp)
$DatabaseDump = Join-Path $StageDirectory 'disbasura.sql'
$Archive = Join-Path $BackupDirectory ("DisBasura-{0}.zip" -f $Stamp)

$DumpCommand = Get-Command mysqldump.exe -ErrorAction SilentlyContinue
if (-not $DumpCommand) {
    $XamppRoot = Split-Path -Parent (Split-Path -Parent $ProjectRoot)
    $Candidate = Join-Path $XamppRoot 'mysql\bin\mysqldump.exe'
    if (Test-Path -LiteralPath $Candidate) { $DumpPath = $Candidate }
    else { throw 'mysqldump.exe was not found. Add MySQL or XAMPP mysql\bin to PATH.' }
} else { $DumpPath = $DumpCommand.Source }

New-Item -ItemType Directory -Path $BackupDirectory -Force | Out-Null
New-Item -ItemType Directory -Path $StageDirectory -Force | Out-Null
try {
    if ($env:DISBASURA_DB_PASSWORD) { $env:MYSQL_PWD = $env:DISBASURA_DB_PASSWORD }
    & $DumpPath --host=localhost --user=root --single-transaction --routines --triggers "--result-file=$DatabaseDump" disbasura
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE." }
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue

    Copy-Item -LiteralPath $UploadDirectory -Destination (Join-Path $StageDirectory 'uploads') -Recurse
    Compress-Archive -Path (Join-Path $StageDirectory 'disbasura.sql'), (Join-Path $StageDirectory 'uploads') -DestinationPath $Archive -CompressionLevel Optimal
    Write-Output "Backup created: $Archive"
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
    if (Test-Path -LiteralPath $StageDirectory) { Remove-Item -LiteralPath $StageDirectory -Recurse -Force }
}
