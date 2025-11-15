param (
    [Parameter(Mandatory=$true)]
    [string]$FileName
)

$root = Get-Location

$found = Get-ChildItem -Path $root -Recurse -File |
    Where-Object { $_.Name -ieq $FileName }

if (-not $found) {
    Write-Host "❌ File tidak ditemukan: $FileName"
    exit
}

foreach ($file in $found) {
    Write-Host "=== FILE: $($file.FullName.Replace($root.Path + '\','')) ==="
    Get-Content $file.FullName
    Write-Host "=== END FILE ==="
    Write-Host ""
}
