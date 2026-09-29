$ErrorActionPreference = 'SilentlyContinue'

$Root = Split-Path -Parent $PSScriptRoot
$Nssm = Join-Path $Root 'runtime\nssm.exe'
$ServiceName = 'BusinessOSPharmacyLocalServer'
$FirewallName = 'BusinessOS Pharmacy Local Server'

if (Get-Service -Name $ServiceName -ErrorAction SilentlyContinue) {
    Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
    & $Nssm remove $ServiceName confirm | Out-Null
}

Get-NetFirewallRule -DisplayName $FirewallName -ErrorAction SilentlyContinue |
    Remove-NetFirewallRule -ErrorAction SilentlyContinue

# Pharmacy data under ProgramData is intentionally preserved on uninstall.