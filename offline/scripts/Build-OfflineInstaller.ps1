param(
    [Parameter(Mandatory = $true)][string]$RepositoryRoot,
    [Parameter(Mandatory = $true)][string]$Version,
    [Parameter(Mandatory = $true)][string]$LicensePublicKey,
    [ValidateSet('none', 'ioncube')][string]$SourceProtection = 'none',
    [string]$IonCubeEncoderPath = ''
)

$ErrorActionPreference = 'Stop'

$OfflineRoot = Join-Path $RepositoryRoot 'offline'
$Dist = Join-Path $OfflineRoot 'dist'
$ApplicationDist = Join-Path $Dist 'application'
$RuntimeDist = Join-Path $Dist 'runtime'
$ManagerDist = Join-Path $Dist 'manager'
$InstallerDist = Join-Path $Dist 'installer'

if ([string]::IsNullOrWhiteSpace($LicensePublicKey)) {
    throw 'LICENSE_SIGNING_PUBLIC_KEY_B64 is required to build an activatable installer.'
}

if (Test-Path $Dist) {
    Remove-Item $Dist -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $ApplicationDist, $RuntimeDist, $ManagerDist, $InstallerDist | Out-Null

& (Join-Path $OfflineRoot 'scripts\Prepare-OfflinePackage.ps1') -RepositoryRoot $RepositoryRoot -Destination $ApplicationDist -Version $Version

if ($SourceProtection -eq 'ioncube') {
    if ([string]::IsNullOrWhiteSpace($IonCubeEncoderPath)) {
        throw 'IonCubeEncoderPath is required for a protected customer build.'
    }

    & (Join-Path $OfflineRoot 'scripts\Protect-OfflineSource.ps1') -EncoderPath $IonCubeEncoderPath -ApplicationRoot $ApplicationDist
} else {
    Set-Content -Path (Join-Path $Dist 'UNPROTECTED-INTERNAL-BUILD.txt') -Value @'
This installer contains readable BusinessOS PHP application source.
It is for internal installation testing only and must not be distributed to customers.
Use SourceProtection=ioncube for a customer release.
'@
}

dotnet publish (Join-Path $OfflineRoot 'manager\BusinessOS.Pharmacy.Manager.csproj') -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true -o $ManagerDist

$PhpExe = (Get-Command php.exe -ErrorAction Stop).Source
$PhpRoot = Split-Path $PhpExe -Parent
Copy-Item $PhpRoot (Join-Path $RuntimeDist 'php') -Recurse -Force

$caddyDir = Join-Path $RuntimeDist 'caddy'
New-Item -ItemType Directory -Force -Path $caddyDir | Out-Null
Invoke-WebRequest -Uri 'https://caddyserver.com/api/download?os=windows&arch=amd64' -OutFile (Join-Path $caddyDir 'caddy.exe') -UseBasicParsing

if (-not (Get-Command choco.exe -ErrorAction SilentlyContinue)) {
    throw 'Chocolatey is required on the Windows build runner.'
}

choco install nssm mariadb innosetup -y --no-progress

$nssmExe = (Get-Command nssm.exe -ErrorAction Stop).Source
$nssmDir = Join-Path $RuntimeDist 'nssm'
New-Item -ItemType Directory -Force -Path $nssmDir | Out-Null
Copy-Item $nssmExe (Join-Path $nssmDir 'nssm.exe') -Force

$mariaServer = Get-ChildItem 'C:\Program Files\MariaDB*\bin\mariadbd.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1

if (-not $mariaServer) {
    throw 'MariaDB runtime could not be located after Chocolatey installation.'
}

$mariaRoot = Split-Path (Split-Path $mariaServer.FullName -Parent) -Parent
Copy-Item $mariaRoot (Join-Path $RuntimeDist 'mariadb') -Recurse -Force

$ionCubeZip = Join-Path $env:RUNNER_TEMP 'ioncube-loaders.zip'
$ionCubeExtract = Join-Path $env:RUNNER_TEMP 'ioncube-loaders'
Invoke-WebRequest -Uri 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_win_x86-64.zip' -OutFile $ionCubeZip -UseBasicParsing
Expand-Archive $ionCubeZip -DestinationPath $ionCubeExtract -Force
Copy-Item $ionCubeExtract (Join-Path $RuntimeDist 'ioncube') -Recurse -Force

Set-Content -Path (Join-Path $Dist 'license-public-key.txt') -Value $LicensePublicKey -NoNewline

$iss = Join-Path $OfflineRoot 'installer\BusinessOSPharmacy.iss'
$programFilesX86 = [Environment]::GetFolderPath('ProgramFilesX86')
$iscc = Join-Path $programFilesX86 'Inno Setup 6\ISCC.exe'
if (-not (Test-Path $iscc)) {
    throw 'Inno Setup compiler was not found.'
}

& $iscc "/DMyAppVersion=$Version" $iss
if ($LASTEXITCODE -ne 0) {
    throw "Inno Setup failed with exit code $LASTEXITCODE."
}

$installer = Get-ChildItem $InstallerDist -Filter '*.exe' | Select-Object -First 1
if (-not $installer) {
    throw 'The BusinessOS Pharmacy installer was not produced.'
}

$hash = Get-FileHash $installer.FullName -Algorithm SHA256
Set-Content -Path ($installer.FullName + '.sha256') -Value ($hash.Hash.ToLowerInvariant() + '  ' + $installer.Name)

Write-Output "Installer: $($installer.FullName)"
Write-Output "SHA256: $($hash.Hash)"
