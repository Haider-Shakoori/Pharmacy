$ErrorActionPreference = "Stop"

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $root

if (Test-Path "windows\runner\Runner.rc") {
    Write-Host "Windows host already exists."
    exit 0
}

$temp = Join-Path ([System.IO.Path]::GetTempPath()) ("businessos-pharmacy-" + [guid]::NewGuid())
try {
    flutter create --platforms=windows --org af.businessos --project-name businessos_pharmacy --no-pub $temp
    if (Test-Path "windows") {
        Remove-Item "windows" -Recurse -Force
    }
    Copy-Item (Join-Path $temp "windows") "windows" -Recurse
    Copy-Item (Join-Path $temp ".metadata") ".metadata" -Force
    Write-Host "Generated Windows host for BusinessOS Pharmacy."
}
finally {
    if (Test-Path $temp) {
        Remove-Item $temp -Recurse -Force
    }
}
