Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$here = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $here 'Collector.ps1')

Write-Host 'PC Audit Collector - local test' -ForegroundColor Cyan
Write-Host 'Khong gui du lieu len server.' -ForegroundColor Yellow
Write-Host ''

$data = Get-PcAuditCollector
$out = Join-Path $env:TEMP ('pc-audit-test-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.json')
$data | ConvertTo-Json -Depth 30 | Set-Content -LiteralPath $out -Encoding UTF8

Write-Host "Da thu thap du lieu: $out" -ForegroundColor Green
Write-Host "Computer : $($data.computer.computer_name)"
Write-Host "Serial   : $($data.computer.serial_number)"
Write-Host "RAM      : $(@($data.memory).Count) module(s)"
Write-Host "Storage  : $(@($data.storage).Count) volume(s)"
Write-Host "Network  : $(@($data.network).Count) adapter(s)"
Write-Host "Software : $(@($data.software).Count) item(s)"
