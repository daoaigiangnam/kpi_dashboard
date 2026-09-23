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

Write-Host 'Đang lấy cấu hình hệ thống...' -ForegroundColor Yellow
Initialize-PcAuditConfig

$serverVersion = [string]$script:PcAuditServerConfig.tool_version
if (-not [string]::IsNullOrWhiteSpace($serverVersion)) {
    Write-Host "Tool phiên bản máy chủ: $serverVersion" -ForegroundColor DarkGray
}

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

Write-Host 'Đang kiểm tra cấu hình Email...' -ForegroundColor Yellow
$mailConfig = Invoke-PcAuditGetMailConfig -Code $code
if ($mailConfig.ok -eq $true) {
    Write-Host "Email nhận : $([string]$mailConfig.data.to_email)" -ForegroundColor DarkGray
}

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

# Send the same collected payload through the server-side SMTP configuration.
# SMTP credentials never leave the server.
try {
    $computerName = [string]$collector.computer_name
    if ([string]::IsNullOrWhiteSpace($computerName)) { $computerName = $env:COMPUTERNAME }
    $subject = "PC Audit - $computerName - $customerName - $branchName"
    $mailBody = ($payload | ConvertTo-Json -Depth 30)

    Write-Host 'Đang gửi Email báo cáo...' -ForegroundColor Yellow
    $mailResponse = Invoke-PcAuditSendMail -Code $code -Subject $subject -Body $mailBody
    if ($mailResponse.ok -eq $true) {
        Write-Host "Email đã gửi: $([string]$mailResponse.to)" -ForegroundColor Green
    }
}
catch {
    Write-Warning "Audit đã lưu thành công nhưng Email chưa gửi được: $($_.Exception.Message)"
}

Write-Host ''
Write-Host 'AUDIT HOÀN TẤT - DỮ LIỆU ĐÃ ĐƯỢC LƯU VÀO HỆ THỐNG.' -ForegroundColor Green
Read-Host 'Nhấn Enter để kết thúc'
