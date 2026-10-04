param([switch]$SkipInstall)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
New-Item -ItemType Directory -Force -Path '.runtime' | Out-Null
$skyPhpPath = (Get-Command php).Source
$skyIni = & php -r 'echo php_ini_loaded_file();'
if (-not (Test-Path -LiteralPath '.runtime/php.ini')) {
    if ($skyIni) { Copy-Item -LiteralPath $skyIni -Destination '.runtime/php.ini' } else { Set-Content -LiteralPath '.runtime/php.ini' -Value '' }
    $skyExtensionDir = Join-Path (Split-Path $skyPhpPath -Parent) 'ext'
    Add-Content -LiteralPath '.runtime/php.ini' -Value ('extension_dir = "' + $skyExtensionDir.Replace('\','/') + '"')
    foreach ($skyExtension in @('mysqli','pdo_mysql','intl','mbstring','sqlite3','zip')) {
        & php -r ("exit(extension_loaded('$skyExtension') ? 0 : 1);")
        if ($LASTEXITCODE -ne 0) { Add-Content -LiteralPath '.runtime/php.ini' -Value ('extension=' + $skyExtension) }
    }
}
$env:PHPRC = (Resolve-Path '.runtime/php.ini').Path
function New-SkySecret {
    $skyBytes = New-Object byte[] 32
    $skyGenerator = [Security.Cryptography.RandomNumberGenerator]::Create()
    $skyGenerator.GetBytes($skyBytes)
    $skyGenerator.Dispose()
    return [BitConverter]::ToString($skyBytes).Replace('-','').ToLowerInvariant()
}
if (-not (Test-Path -LiteralPath '.env')) {
    $skyDbPassword = New-SkySecret
    $skyEnvText = Get-Content -LiteralPath '.env.example' -Raw
    $skyEnvText = $skyEnvText.Replace('replace-with-at-least-32-random-characters',(New-SkySecret)).Replace('replace-with-random-database-password',$skyDbPassword).Replace('replace-with-another-random-password',(New-SkySecret))
    [IO.File]::WriteAllText((Join-Path (Get-Location) '.env'),$skyEnvText)
}
if (-not $SkipInstall) {
    & composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed' }
    & npm.cmd ci
    if ($LASTEXITCODE -ne 0) { throw 'npm install failed' }
}
& npm.cmd run assets
if ($LASTEXITCODE -ne 0) { throw 'Asset copy failed' }
& docker compose pull
if ($LASTEXITCODE -ne 0) { throw 'MariaDB image pull failed' }
& docker compose up -d --wait
if ($LASTEXITCODE -ne 0) { throw 'MariaDB startup failed' }
& php spark migrate
if ($LASTEXITCODE -ne 0) { throw 'Database migration failed' }
& php spark db:seed FlightSeeder
if ($LASTEXITCODE -ne 0) { throw 'Flight seeding failed' }
Write-Output 'Ready. Run: powershell -ExecutionPolicy Bypass -File scripts/start.ps1'
