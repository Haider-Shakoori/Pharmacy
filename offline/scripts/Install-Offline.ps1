param(
    [Parameter(Mandatory = $true)][string]$AppRoot,
    [Parameter(Mandatory = $true)][string]$DataRoot,
    [string]$ConfigPath = '',
    [string]$PharmacyName = '',
    [string]$OwnerName = '',
    [string]$OwnerEmail = '',
    [string]$OwnerPassword = '',
    [string]$AppVersion = 'dev',
    [int]$HttpPort = 8090
)

$ErrorActionPreference = 'Stop'


if ($ConfigPath -ne '') {
    if (-not (Test-Path $ConfigPath)) {
        throw 'The installer configuration file could not be found.'
    }

    $settings = @{}
    foreach ($line in Get-Content $ConfigPath) {
        $separator = $line.IndexOf('=')
        if ($separator -gt 0) {
            $key = $line.Substring(0, $separator)
            $value = $line.Substring($separator + 1)
            $settings[$key] = $value
        }
    }

    $PharmacyName = [string]$settings['PharmacyName']
    $OwnerName = [string]$settings['OwnerName']
    $OwnerEmail = [string]$settings['OwnerEmail']
    $OwnerPassword = [string]$settings['OwnerPassword']

    Remove-Item $ConfigPath -Force -ErrorAction SilentlyContinue
}

if ([string]::IsNullOrWhiteSpace($PharmacyName) -or
    [string]::IsNullOrWhiteSpace($OwnerName) -or
    [string]::IsNullOrWhiteSpace($OwnerEmail) -or
    [string]::IsNullOrWhiteSpace($OwnerPassword)) {
    throw 'Pharmacy and owner information is incomplete.'
}

if ($OwnerPassword.Length -lt 8) {
    throw 'The initial owner password must contain at least 8 characters.'
}

function New-RandomSecret([int]$Bytes = 32) {
    $buffer = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($buffer)
    return [Convert]::ToBase64String($buffer)
}

function Invoke-Checked([string]$File, [string[]]$Arguments) {
    $process = Start-Process -FilePath $File -ArgumentList $Arguments -Wait -PassThru -NoNewWindow
    if ($process.ExitCode -ne 0) {
        throw "$File failed with exit code $($process.ExitCode)."
    }
}

$Application = Join-Path $AppRoot 'application'
$Runtime = Join-Path $AppRoot 'runtime'
$PhpRoot = Join-Path $Runtime 'php'
$Php = Join-Path $PhpRoot 'php.exe'
$PhpCgi = Join-Path $PhpRoot 'php-cgi.exe'
$Nssm = Join-Path $Runtime 'nssm\nssm.exe'
$Caddy = Join-Path $Runtime 'caddy\caddy.exe'
$MariaRoot = Join-Path $Runtime 'mariadb'
$MariaBin = Join-Path $MariaRoot 'bin'
$MariaDb = Join-Path $MariaBin 'mariadb.exe'
$MariaServer = Join-Path $MariaBin 'mariadbd.exe'
$MariaInstall = Join-Path $MariaBin 'mariadb-install-db.exe'
if (-not (Test-Path $MariaInstall)) {
    $MariaInstall = Join-Path $MariaBin 'mysql_install_db.exe'
}

$DbData = Join-Path $DataRoot 'mariadb\data'
$OfflineData = Join-Path $DataRoot 'license'
$Logs = Join-Path $DataRoot 'logs'
$CaddyConfig = Join-Path $DataRoot 'Caddyfile'
$MyIni = Join-Path $DataRoot 'my.ini'
$PublicKeyFile = Join-Path $AppRoot 'license-public-key.txt'

New-Item -ItemType Directory -Force -Path $DataRoot, $DbData, $OfflineData, $Logs | Out-Null

$DbRootPasswordFile = Join-Path $DataRoot '.db-root.secret'
$DbAppPasswordFile = Join-Path $DataRoot '.db-app.secret'

if (Test-Path $DbRootPasswordFile) {
    $DbRootPassword = (Get-Content $DbRootPasswordFile -Raw).Trim()
} else {
    $DbRootPassword = New-RandomSecret 24
    Set-Content -Path $DbRootPasswordFile -Value $DbRootPassword -NoNewline
}

if (Test-Path $DbAppPasswordFile) {
    $DbAppPassword = (Get-Content $DbAppPasswordFile -Raw).Trim()
} else {
    $DbAppPassword = New-RandomSecret 24
    Set-Content -Path $DbAppPasswordFile -Value $DbAppPassword -NoNewline
}

$MyIniContent = @"
[mysqld]
basedir=$($MariaRoot.Replace('\','/'))
datadir=$($DbData.Replace('\','/'))
port=3307
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
skip-name-resolve=1
max_connections=150

[client]
port=3307
host=127.0.0.1
default-character-set=utf8mb4
"@
Set-Content -Path $MyIni -Value $MyIniContent -Encoding ASCII

$DbService = Get-Service -Name 'BusinessOSPharmacyDB' -ErrorAction SilentlyContinue
if (-not $DbService) {
    if (-not (Test-Path (Join-Path $DbData 'mysql'))) {
        if (-not (Test-Path $MariaInstall)) {
            throw 'MariaDB initialization tool was not packaged.'
        }

        Invoke-Checked $MariaInstall @(
            "--datadir=$DbData",
            "--basedir=$MariaRoot",
            "--password=$DbRootPassword",
            '--skip-test-db'
        )
    }

    Invoke-Checked $MariaServer @(
        "--defaults-file=$MyIni",
        '--install=BusinessOSPharmacyDB'
    )
}

Start-Service -Name 'BusinessOSPharmacyDB'
Start-Sleep -Seconds 3

$sql = @"
CREATE DATABASE IF NOT EXISTS businessos_pharmacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'businessos'@'127.0.0.1' IDENTIFIED BY '$DbAppPassword';
ALTER USER 'businessos'@'127.0.0.1' IDENTIFIED BY '$DbAppPassword';
GRANT ALL PRIVILEGES ON *.* TO 'businessos'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
$sqlFile = Join-Path $DataRoot 'bootstrap.sql'
Set-Content -Path $sqlFile -Value $sql -Encoding UTF8
Invoke-Checked $MariaDb @(
    '--host=127.0.0.1',
    '--port=3307',
    '--user=root',
    "--password=$DbRootPassword",
    "--execute=source $($sqlFile.Replace('\','/'))"
)
Remove-Item $sqlFile -Force

if (-not (Test-Path $PublicKeyFile)) {
    throw 'The BusinessOS license verification public key is missing from the installer.'
}

$LicensePublicKey = (Get-Content $PublicKeyFile -Raw).Trim()
if ([string]::IsNullOrWhiteSpace($LicensePublicKey)) {
    throw 'The BusinessOS license verification public key is empty.'
}

$AppKey = 'base64:' + (New-RandomSecret 32)
$EnvPath = Join-Path $Application '.env'
$AppUrl = 'http://{0}:{1}' -f $env:COMPUTERNAME, $HttpPort

$envContent = @"
APP_NAME="BusinessOS Pharmacy"
APP_ENV=production
APP_EDITION=offline
APP_TIMEZONE=Asia/Kabul
APP_KEY=$AppKey
APP_DEBUG=false
APP_URL=$AppUrl
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=businessos_pharmacy
DB_USERNAME=businessos
DB_PASSWORD="$DbAppPassword"

CENTRAL_DB_CONNECTION=mysql
CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_PORT=3307
CENTRAL_DB_DATABASE=businessos_pharmacy
CENTRAL_DB_USERNAME=businessos
CENTRAL_DB_PASSWORD="$DbAppPassword"

SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=central
FILESYSTEM_DISK=local

TENANCY_DB_PROVISIONER=local
PHARMACY_PLATFORM_DOMAIN=platform.businessos.invalid
PHARMACY_DEPLOYMENT_HOST=offline.businessos.invalid
PHARMACY_CURRENCY=AFN

LICENSE_SIGNING_PRIVATE_KEY_B64=
LICENSE_SIGNING_PUBLIC_KEY_B64=$LicensePublicKey

OFFLINE_DATA_ROOT=$($OfflineData.Replace('\','/'))
OFFLINE_ACTIVATION_URL=https://pharmacy.businessos.af/api/v1/offline/license/activate
OFFLINE_ACTIVATION_TIMEOUT_SECONDS=20
OFFLINE_CLOCK_ROLLBACK_TOLERANCE_SECONDS=300
OFFLINE_MAX_INSTALLATIONS_PER_LICENSE=1
OFFLINE_APP_VERSION=$AppVersion
OFFLINE_HTTP_PORT=$HttpPort

BACKUP_ROOT=$((Join-Path $DataRoot 'backups').Replace('\','/'))
BACKUP_RETENTION_COUNT=14
BACKUP_SCHEDULE_ENABLED=true
BACKUP_SCHEDULE_TIME=02:15
BACKUP_PRUNE_TIME=03:15
"@
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

$PhpIni = Join-Path $PhpRoot 'php.ini'
$PhpExt = Join-Path $PhpRoot 'ext'
$phpIniContent = @"
[PHP]
engine=On
short_open_tag=Off
precision=14
output_buffering=4096
expose_php=Off
max_execution_time=120
max_input_time=120
memory_limit=512M
post_max_size=64M
upload_max_filesize=64M
max_file_uploads=20
default_charset="UTF-8"
date.timezone=Asia/Kabul
cgi.force_redirect=0
extension_dir="$PhpExt"

extension=php_curl.dll
extension=php_fileinfo.dll
extension=php_gd.dll
extension=php_intl.dll
extension=php_mbstring.dll
extension=php_mysqli.dll
extension=php_openssl.dll
extension=php_pdo_mysql.dll
extension=php_sodium.dll
extension=php_zip.dll

[Session]
session.use_strict_mode=1
session.use_only_cookies=1
session.cookie_httponly=1
"@
Set-Content -Path $PhpIni -Value $phpIniContent -Encoding ASCII

$IonCubeLoader = Get-ChildItem -Path (Join-Path $Runtime 'ioncube') -Filter 'ioncube_loader_win_8.4.dll' -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
if ($IonCubeLoader) {
    Add-Content -Path $PhpIni -Value ([Environment]::NewLine + 'zend_extension="' + $IonCubeLoader.FullName + '"')
}

Invoke-Checked $Php @('-c', $PhpIni, '-r', 'if (!extension_loaded("pdo_mysql") || !extension_loaded("sodium") || !extension_loaded("mbstring")) { fwrite(STDERR, "Required PHP extensions are missing."); exit(1); }')

Push-Location $Application
try {
    Invoke-Checked $Php @('artisan', 'migrate', '--force')
    Invoke-Checked $Php @(
        'artisan', 'pharmacy:offline:install',
        "--name=$PharmacyName",
        "--owner-name=$OwnerName",
        "--owner-email=$OwnerEmail",
        "--owner-password=$OwnerPassword"
    )
    Invoke-Checked $Php @('artisan', 'optimize')
} finally {
    Pop-Location
}

$CaddyTemplate = Get-Content (Join-Path $AppRoot 'Caddyfile.template') -Raw
$CaddyContent = $CaddyTemplate.Replace('__OFFLINE_HTTP_PORT__', [string]$HttpPort).Replace('__APP_PUBLIC__', (Join-Path $Application 'public').Replace('\','/'))
Set-Content -Path $CaddyConfig -Value $CaddyContent -Encoding UTF8

foreach ($serviceName in @('BusinessOSPharmacyWeb', 'BusinessOSPharmacyPHP', 'BusinessOSPharmacyQueue')) {
    if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) {
        & $Nssm stop $serviceName confirm | Out-Null
        & $Nssm remove $serviceName confirm | Out-Null
    }
}

& $Nssm install BusinessOSPharmacyPHP $PhpCgi '-b 127.0.0.1:9074' | Out-Null
& $Nssm set BusinessOSPharmacyPHP AppDirectory $Application | Out-Null
& $Nssm set BusinessOSPharmacyPHP AppEnvironmentExtra "PHPRC=$PhpRoot" | Out-Null
& $Nssm set BusinessOSPharmacyPHP Start SERVICE_AUTO_START | Out-Null

& $Nssm install BusinessOSPharmacyWeb $Caddy ('run --config "{0}" --adapter caddyfile' -f $CaddyConfig) | Out-Null
& $Nssm set BusinessOSPharmacyWeb AppDirectory $Application | Out-Null
& $Nssm set BusinessOSPharmacyWeb Start SERVICE_AUTO_START | Out-Null

& $Nssm install BusinessOSPharmacyQueue $Php 'artisan queue:work --sleep=2 --tries=3 --timeout=90' | Out-Null
& $Nssm set BusinessOSPharmacyQueue AppDirectory $Application | Out-Null
& $Nssm set BusinessOSPharmacyQueue AppEnvironmentExtra "PHPRC=$PhpRoot" | Out-Null
& $Nssm set BusinessOSPharmacyQueue Start SERVICE_AUTO_START | Out-Null

Start-Service BusinessOSPharmacyPHP
Start-Service BusinessOSPharmacyWeb
Start-Service BusinessOSPharmacyQueue

$SchedulerCommand = '"{0}" "{1}" schedule:run' -f $Php, (Join-Path $Application 'artisan')
schtasks.exe /Create /F /SC MINUTE /MO 1 /TN 'BusinessOS Pharmacy Scheduler' /TR $SchedulerCommand /RU SYSTEM | Out-Null

$NetworkSyncCommand = '"{0}" "{1}" pharmacy:offline:sync-network' -f $Php, (Join-Path $Application 'artisan')
schtasks.exe /Create /F /SC MINUTE /MO 5 /TN 'BusinessOS Pharmacy Network Sync' /TR $NetworkSyncCommand /RU SYSTEM | Out-Null

if (-not (Get-NetFirewallRule -DisplayName 'BusinessOS Pharmacy LAN' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName 'BusinessOS Pharmacy LAN' -Direction Inbound -Action Allow -Protocol TCP -LocalPort $HttpPort -Profile Private | Out-Null
}

Start-Process ('http://{0}:{1}/offline/license' -f $env:COMPUTERNAME, $HttpPort)
