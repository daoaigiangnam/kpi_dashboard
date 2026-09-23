Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$here = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $here 'Config.ps1')
. (Join-Path $here 'ApiClient.ps1')
. (Join-Path $here 'Collector.ps1')

Write-Host ''
Write-Host '========================================='
Write-Host '        MSTAR PC AUDIT TOOL' -ForegroundColor Cyan
Write-Host '========================================='

$code = Read-Host 'Audit Code'
$department = Read-Host 'Phòng ban'
$employeeName = Read-Host 'Họ và tên'

if ([string]::IsNullOrWhiteSpace($code) -or [string]::IsNullOrWhiteSpace($department) -or [string]::IsNullOrWhiteSpace($employeeName)) {
    throw 'Code, Phòng ban và Họ tên không được để trống.'
}

Write-Host 'Đang xác thực Audit Code...' -ForegroundColor Yellow
$validation = Invoke-PcAuditValidateCode -Code $code

if ($validation.ok -ne $true) {
    throw ('Audit Code không hợp lệ: ' + [string]$validation.message)
}

$customerName = $validation.data.customer.name
$branchName = $validation.data.branch.name
Write-Host "Khách hàng : $customerName" -ForegroundColor Green
Write-Host "Chi nhánh  : $branchName" -ForegroundColor Green
Write-Host ''

$answer = Read-Host 'Tiếp tục Audit máy này? (Y/N)'
if ($answer -notmatch '^(Y|y)$') { exit 0 }

Write-Host 'Đang thu thập thông tin máy...' -ForegroundColor Yellow
$collector = Get-PcAuditCollector

$payload = @{
    code = $code
    department = $department
    employee_name = $employeeName
    data = $collector
}

Write-Host 'Đang gửi dữ liệu về hệ thống...' -ForegroundColor Yellow
$response = Invoke-PcAuditSubmit -Payload $payload

if ($response.ok -ne $true) {
    throw ('Server từ chối dữ liệu: ' + [string]$response.message)
}

Write-Host ''
Write-Host 'AUDIT HOÀN TẤT - DỮ LIỆU ĐÃ ĐƯỢC GỬI VỀ HỆ THỐNG.' -ForegroundColor Green
Read-Host 'Nhấn Enter để kết thúc'
