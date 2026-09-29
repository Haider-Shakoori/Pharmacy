$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$Nssm = Join-Path $Root 'runtime\nssm.exe'
$Supervisor = Join-Path $PSScriptRoot 'server-supervisor.ps1'
$PowerShell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$ServiceName = 'BusinessOSPharmacyLocalServer'
$FirewallName = 'BusinessOS Pharmacy Local Server'
$DataRoot = Join-Path $env:ProgramData 'BusinessOS\Pharmacy'
$LogRoot = Join-Path $DataRoot 'logs'

New-Item -ItemType Directory -Path $LogRoot -Force | Out-Null

if (Get-Service -Name $ServiceName -ErrorAction SilentlyContinue) {
    Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
    & $Nssm remove $ServiceName confirm | Out-Null
}

& $Nssm install $ServiceName $PowerShell | Out-Null
& $Nssm set $ServiceName AppParameters "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$Supervisor`"" | Out-Null
& $Nssm set $ServiceName AppDirectory $Root | Out-Null
& $Nssm set $ServiceName DisplayName 'BusinessOS Pharmacy Local Server' | Out-Null
& $Nssm set $ServiceName Description 'Local Laravel/API runtime for the BusinessOS Pharmacy offline edition.' | Out-Null
& $Nssm set $ServiceName Start SERVICE_AUTO_START | Out-Null
& $Nssm set $ServiceName AppExit Default Restart | Out-Null
& $Nssm set $ServiceName AppRestartDelay 3000 | Out-Null
& $Nssm set $ServiceName AppStdout (Join-Path $LogRoot 'service-out.log') | Out-Null
& $Nssm set $ServiceName AppStderr (Join-Path $LogRoot 'service-error.log') | Out-Null

Get-NetFirewallRule -DisplayName $FirewallName -ErrorAction SilentlyContinue |
    Remove-NetFirewallRule -ErrorAction SilentlyContinue
New-NetFirewallRule `
    -DisplayName $FirewallName `
    -Direction Inbound `
    -Action Allow `
    -Protocol TCP `
    -LocalPort 8787 `
    -Profile Private,Domain | Out-Null

Start-Service -Name $ServiceName

$ready = $false
for ($attempt = 0; $attempt -lt 45; $attempt++) {
    Start-Sleep -Seconds 1
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8787/up' -TimeoutSec 2
        if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
            $ready = $true
            break
        }
    }
    catch {
    }
}
if (-not $ready) {
    throw "BusinessOS Pharmacy local server did not start. Check $LogRoot."
}