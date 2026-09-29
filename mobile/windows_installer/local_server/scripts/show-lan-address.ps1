$addresses = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object {
        $_.IPAddress -notlike '127.*' -and
        $_.IPAddress -notlike '169.254.*' -and
        $_.PrefixOrigin -ne 'WellKnown'
    } |
    Select-Object -ExpandProperty IPAddress -Unique

Write-Host ''
Write-Host 'BusinessOS Pharmacy local server:' -ForegroundColor Cyan
Write-Host '  This PC: http://127.0.0.1:8787'
foreach ($address in $addresses) {
    Write-Host ('  LAN:     http://' + $address + ':8787')
}
Write-Host ''
Write-Host 'Android devices must be on the same private Wi-Fi/LAN.' -ForegroundColor Yellow
Read-Host 'Press Enter to close'