param(
    [Parameter(Mandatory = $true)][string]$RepositoryRoot,
    [Parameter(Mandatory = $true)][string]$Destination,
    [Parameter(Mandatory = $true)][string]$Version
)

$ErrorActionPreference = 'Stop'

if (Test-Path $Destination) {
    Remove-Item $Destination -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $Destination | Out-Null

$directories = @(
    'app',
    'bootstrap',
    'config',
    'database',
    'lang',
    'public',
    'resources\views',
    'routes',
    'storage',
    'vendor'
)

foreach ($relative in $directories) {
    $source = Join-Path $RepositoryRoot $relative
    if (-not (Test-Path $source)) {
        throw "Required application path is missing: $relative"
    }

    $target = Join-Path $Destination $relative
    New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
    Copy-Item $source $target -Recurse -Force
}

foreach ($file in @('artisan', 'composer.json', 'composer.lock')) {
    Copy-Item (Join-Path $RepositoryRoot $file) (Join-Path $Destination $file) -Force
}

$cleanupPaths = @(
    'storage\logs',
    'storage\app\backups',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views'
)

foreach ($relative in $cleanupPaths) {
    $path = Join-Path $Destination $relative
    if (Test-Path $path) {
        Remove-Item $path -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $path | Out-Null
}

Get-ChildItem $Destination -Recurse -Force -Include '.git*', '*.map' -ErrorAction SilentlyContinue |
    Remove-Item -Force -Recurse -ErrorAction SilentlyContinue

$installScript = Join-Path $RepositoryRoot 'offline\scripts\Install-Offline.ps1'
$installContent = (Get-Content $installScript -Raw).Replace('__APP_VERSION__', $Version)
Set-Content -Path $installScript -Value $installContent -Encoding UTF8

Write-Output "Prepared offline application package at $Destination"
