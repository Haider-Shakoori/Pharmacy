param(
    [Parameter(Mandatory = $true)][string]$AppRoot,
    [Parameter(Mandatory = $true)][string]$DataRoot
)

$ErrorActionPreference = 'SilentlyContinue'
$Nssm = Join-Path $AppRoot 'runtime\nssm\nssm.exe'

schtasks.exe /Delete /F /TN 'BusinessOS Pharmacy Scheduler' | Out-Null
schtasks.exe /Delete /F /TN 'BusinessOS Pharmacy Network Sync' | Out-Null

foreach ($serviceName in @('BusinessOSPharmacyWeb', 'BusinessOSPharmacyPHP', 'BusinessOSPharmacyQueue')) {
    if (Test-Path $Nssm) {
        & $Nssm stop $serviceName confirm | Out-Null
        & $Nssm remove $serviceName confirm | Out-Null
    }
}

if (Get-Service -Name 'BusinessOSPharmacyDB' -ErrorAction SilentlyContinue) {
    Stop-Service 'BusinessOSPharmacyDB' -Force
    $MariaServer = Join-Path $AppRoot 'runtime\mariadb\bin\mariadbd.exe'
    if (Test-Path $MariaServer) {
        & $MariaServer --remove=BusinessOSPharmacyDB | Out-Null
    }
}

Get-NetFirewallRule -DisplayName 'BusinessOS Pharmacy LAN' -ErrorAction SilentlyContinue | Remove-NetFirewallRule

Write-Output "BusinessOS Pharmacy services removed. Customer data and backups were intentionally preserved at $DataRoot."
