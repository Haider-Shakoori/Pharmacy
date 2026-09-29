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

Write-Host 'Installing Windows packaging dependencies...'
& choco.exe install nssm mariadb innosetup -y --no-progress
if ($LASTEXITCODE -notin @(0, 1641, 3010)) {
    throw "Chocolatey dependency installation failed with exit code $LASTEXITCODE."
}

Write-Host 'Locating NSSM runtime...'
$nssmCandidates = @(
    'C:\ProgramData\chocolatey\bin\nssm.exe',
    'C:\ProgramData\chocolatey\lib\nssm\tools\nssm.exe',
    'C:\ProgramData\chocolatey\lib\nssm\tools\win64\nssm.exe'
)

$nssmExe = $nssmCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $nssmExe) {
    $nssmExe = Get-ChildItem 'C:\ProgramData\chocolatey\lib\nssm' -Filter 'nssm.exe' -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match 'win64|tools' } |
        Select-Object -ExpandProperty FullName -First 1
}

if (-not $nssmExe) {
    throw 'NSSM runtime could not be located after Chocolatey installation.'
}

$nssmDir = Join-Path $RuntimeDist 'nssm'
New-Item -ItemType Directory -Force -Path $nssmDir | Out-Null
Copy-Item $nssmExe (Join-Path $nssmDir 'nssm.exe') -Force

Write-Host 'Locating MariaDB runtime...'
$mariaServer = Get-ChildItem 'C:\Program Files\MariaDB*\bin\mariadbd.exe' -ErrorAction SilentlyContinue |
    Sort-Object FullName -Descending |
    Select-Object -First 1

if (-not $mariaServer) {
    throw 'MariaDB runtime could not be located after Chocolatey installation.'
}

$mariaRoot = Split-Path (Split-Path $mariaServer.FullName -Parent) -Parent
$mariaTarget = Join-Path $RuntimeDist 'mariadb'
New-Item -ItemType Directory -Force -Path $mariaTarget | Out-Null

Write-Host "Copying MariaDB runtime from $mariaRoot ..."
& robocopy.exe $mariaRoot $mariaTarget /E /XD data /NFL /NDL /NJH /NJS /NP
$robocopyExit = $LASTEXITCODE
if ($robocopyExit -gt 7) {
    throw "MariaDB runtime copy failed with robocopy exit code $robocopyExit."
}

$ionCubeZip = Join-Path $env:RUNNER_TEMP 'ioncube-loaders.zip'
$ionCubeExtract = Join-Path $env:RUNNER_TEMP 'ioncube-loaders'
Invoke-WebRequest -Uri 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_win_x86-64.zip' -OutFile $ionCubeZip -UseBasicParsing
Expand-Archive $ionCubeZip -DestinationPath $ionCubeExtract -Force
Copy-Item $ionCubeExtract (Join-Path $RuntimeDist 'ioncube') -Recurse -Force

Set-Content -Path (Join-Path $Dist 'license-public-key.txt') -Value $LicensePublicKey -NoNewline

$iss = Join-Path $OfflineRoot 'installer\BusinessOSPharmacy.iss'
$programFilesX86 = [Environment]::GetFolderPath('ProgramFilesX86')
$isccCandidates = @(
    (Join-Path $programFilesX86 'Inno Setup 6\ISCC.exe'),
    (Join-Path $env:ProgramFiles 'Inno Setup 6\ISCC.exe')
)
$iscc = $isccCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $iscc) {
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
