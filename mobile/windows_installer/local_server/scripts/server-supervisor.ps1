$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$AppRoot = Join-Path $Root 'app'
$Runtime = Join-Path $Root 'runtime'
$PhpRoot = Join-Path $Runtime 'php'
$Php = Join-Path $PhpRoot 'php.exe'
$PhpCgi = Join-Path $PhpRoot 'php-cgi.exe'
$Caddy = Join-Path $Runtime 'caddy.exe'
$TemplateIni = Join-Path $Root 'config\php.ini'

$DataRoot = Join-Path $env:ProgramData 'BusinessOS\Pharmacy'
$DatabaseRoot = Join-Path $DataRoot 'database'
$StorageRoot = Join-Path $DataRoot 'storage'
$LicenseRoot = Join-Path $DataRoot 'license'
$LogRoot = Join-Path $DataRoot 'logs'
$RuntimeRoot = Join-Path $DataRoot 'runtime'
$AppKeyFile = Join-Path $DataRoot 'app.key'
$CentralDatabase = Join-Path $DatabaseRoot 'central.sqlite'
$ClockFile = Join-Path $LicenseRoot 'clock.dat'
$PhpIni = Join-Path $RuntimeRoot 'php.ini'
$CaddyFile = Join-Path $RuntimeRoot 'Caddyfile'

foreach ($path in @(
    $DataRoot,
    $DatabaseRoot,
    $StorageRoot,
    (Join-Path $StorageRoot 'app'),
    (Join-Path $StorageRoot 'framework'),
    (Join-Path $StorageRoot 'framework\cache'),
    (Join-Path $StorageRoot 'framework\sessions'),
    (Join-Path $StorageRoot 'framework\views'),
    $LicenseRoot,
    $LogRoot,
    $RuntimeRoot
)) {
    New-Item -ItemType Directory -Path $path -Force | Out-Null
}

if (-not (Test-Path $AppKeyFile)) {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    }
    finally {
        $rng.Dispose()
    }
    $key = 'base64:' + [Convert]::ToBase64String($bytes)
    Set-Content -Path $AppKeyFile -Value $key -Encoding ASCII -NoNewline
}

if (-not (Test-Path $CentralDatabase)) {
    New-Item -ItemType File -Path $CentralDatabase -Force | Out-Null
}

$extensionDir = (Join-Path $PhpRoot 'ext').Replace('\', '/')
(Get-Content $TemplateIni -Raw).Replace('__EXTENSION_DIR__', $extensionDir) |
    Set-Content -Path $PhpIni -Encoding UTF8

$env:APP_NAME = 'BusinessOS Pharmacy'
$env:APP_ENV = 'production'
$env:APP_KEY = (Get-Content $AppKeyFile -Raw).Trim()
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8787'
$env:APP_TIMEZONE = 'Asia/Kabul'
$env:APP_LOCALE = 'en'
$env:APP_FALLBACK_LOCALE = 'en'
$env:LOG_CHANNEL = 'daily'
$env:LOG_LEVEL = 'info'
$env:CENTRAL_DB_CONNECTION = 'sqlite'
$env:CENTRAL_DB_DATABASE = $CentralDatabase
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $CentralDatabase
$env:SESSION_DRIVER = 'database'
$env:CACHE_STORE = 'database'
$env:QUEUE_CONNECTION = 'sync'
$env:FILESYSTEM_DISK = 'local'
$env:PHARMACY_LOCAL_NODE = 'true'
$env:PHARMACY_LOCAL_BASE_URL = 'http://127.0.0.1:8787'
$env:PHARMACY_LOCAL_PORT = '8787'
$env:PHARMACY_LOCAL_CLOCK_STATE_FILE = $ClockFile
$env:PHARMACY_STORAGE_PATH = $StorageRoot
$env:PHARMACY_DATABASE_PATH = $DatabaseRoot
$env:PHARMACY_DEPLOYMENT_HOST = 'pharmacy.businessos.af'
$env:PHARMACY_PLATFORM_DOMAIN = 'pharmacy.businessos.af'
$env:TENANCY_DB_PROVISIONER = 'local'
$env:SECURITY_HSTS_ENABLED = 'false'

& $Php -c $PhpIni (Join-Path $AppRoot 'artisan') migrate --force --no-interaction
if ($LASTEXITCODE -ne 0) {
    throw 'Local Pharmacy database migration failed.'
}

$publicRoot = (Join-Path $AppRoot 'public').Replace('\', '/')
$caddyLog = (Join-Path $LogRoot 'caddy-access.log').Replace('\', '/')
@"
{
    admin off
    auto_https off
}

:8787 {
    root * "$publicRoot"
    encode gzip
    php_fastcgi 127.0.0.1:9077
    file_server
    log {
        output file "$caddyLog"
    }
}
"@ | Set-Content -Path $CaddyFile -Encoding UTF8

$phpProcess = $null
$caddyProcess = $null

try {
    $phpProcess = Start-Process -FilePath $PhpCgi `
        -ArgumentList @('-c', $PhpIni, '-b', '127.0.0.1:9077') `
        -WorkingDirectory $PhpRoot `
        -WindowStyle Hidden `
        -PassThru

    Start-Sleep -Milliseconds 750

    $caddyProcess = Start-Process -FilePath $Caddy `
        -ArgumentList @('run', '--config', $CaddyFile, '--adapter', 'caddyfile') `
        -WorkingDirectory $Root `
        -WindowStyle Hidden `
        -PassThru

    while ($true) {
        Start-Sleep -Seconds 2

        if ($phpProcess.HasExited) {
            throw "PHP FastCGI exited with code $($phpProcess.ExitCode)."
        }

        if ($caddyProcess.HasExited) {
            throw "Caddy exited with code $($caddyProcess.ExitCode)."
        }
    }
}
finally {
    foreach ($process in @($caddyProcess, $phpProcess)) {
        if ($null -ne $process -and -not $process.HasExited) {
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        }
    }
}