param([switch]$Background)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (-not (Test-Path '.runtime/php.ini')) { throw 'Run scripts/setup.ps1 first.' }
$env:PHPRC = (Resolve-Path '.runtime/php.ini').Path
if ($Background) {
    if (Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue) { throw 'Port 8000 is already in use.' }
    $skyProcess = Start-Process -FilePath (Get-Command php).Source -ArgumentList @('spark','serve','--host','0.0.0.0','--port','8000') -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -RedirectStandardOutput (Join-Path (Get-Location) 'writable/logs/server-out.log') -RedirectStandardError (Join-Path (Get-Location) 'writable/logs/server-error.log') -PassThru
    Set-Content '.runtime/server.pid' $skyProcess.Id
    Write-Output ('Skybook http://localhost:8000 · process ' + $skyProcess.Id)
} else {
    & php spark serve --host 0.0.0.0 --port 8000
}
