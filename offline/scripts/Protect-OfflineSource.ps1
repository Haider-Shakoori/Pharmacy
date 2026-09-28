param(
    [Parameter(Mandatory = $true)][string]$EncoderPath,
    [Parameter(Mandatory = $true)][string]$ApplicationRoot
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $EncoderPath)) {
    throw "ionCube Encoder was not found at $EncoderPath"
}

foreach ($relative in @('app', 'routes')) {
    $source = Join-Path $ApplicationRoot $relative
    $protected = "$source.encoded"

    if (Test-Path $protected) {
        Remove-Item $protected -Recurse -Force
    }

    $process = Start-Process -FilePath $EncoderPath -ArgumentList @(
        $source,
        '-o',
        $protected
    ) -Wait -PassThru -NoNewWindow

    if ($process.ExitCode -ne 0) {
        throw "ionCube encoding failed for $relative with exit code $($process.ExitCode)."
    }

    Remove-Item $source -Recurse -Force
    Rename-Item $protected (Split-Path $source -Leaf)
}

Write-Output 'BusinessOS proprietary PHP source was encoded with ionCube.'
