Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path $here 'Config.ps1')
. (Join-Path $here 'ApiClient.ps1')
. (Join-Path $here 'Collector.ps1')

Write-Host ''
Write-Host '=========================================' -ForegroundColor Cyan
Write-Host '        MSTAR PC AUDIT TOOL' -ForegroundColor Cyan
Write-Host '=========================================' -ForegroundColor Cyan
Write-Host 'Dang lay cau hinh he thong...' -ForegroundColor Yellow
Initialize-PcAuditConfig

$serverVersion = [string]$script:PcAuditServerConfig.tool_version
if (-not [string]::IsNullOrWhiteSpace($serverVersion)) { Write-Host "Tool phien ban may chu: $serverVersion" -ForegroundColor DarkGray }

$code = Read-Host 'Audit Code'
if ([string]::IsNullOrWhiteSpace($code)) { throw 'Audit Code khong duoc de trong.' }
$code = $code.Trim().ToUpperInvariant()

Write-Host 'Dang xac thuc Audit Code...' -ForegroundColor Yellow
$validation = Invoke-PcAuditValidateCode -Code $code
if ($validation.ok -ne $true) { throw ('Audit Code khong hop le: ' + [string]$validation.message) }

$customerName = [string]$validation.data.customer.name
$branchName = [string]$validation.data.branch.name
Write-Host "Khach hang : $customerName" -ForegroundColor Green
Write-Host "Chi nhanh  : $branchName" -ForegroundColor Green

$defaultEmployee = [string]$env:USERNAME
$employeeInput = Read-Host "Ho ten nguoi su dung (Enter = $defaultEmployee)"
$employeeName = if ([string]::IsNullOrWhiteSpace($employeeInput)) { $defaultEmployee } else { $employeeInput.Trim() }
if ([string]::IsNullOrWhiteSpace($employeeName)) { throw 'Khong xac dinh duoc nguoi su dung may.' }

$departmentInput = Read-Host 'Phong ban (Enter = bo qua)'
$department = if ([string]::IsNullOrWhiteSpace($departmentInput)) { $null } else { $departmentInput.Trim() }

Write-Host ''
$answer = Read-Host 'Tiep tuc Audit may nay? (Y/N)'
if ($answer -notmatch '^(Y|y)$') { exit 0 }

Write-Host ''
Write-Host '=========================================' -ForegroundColor Cyan
Write-Host '      DANG THU THAP THONG TIN PC' -ForegroundColor Cyan
Write-Host '=========================================' -ForegroundColor Cyan
Write-Host '[01] Computer / Domain / Serial / Asset Tag' -ForegroundColor Yellow
Write-Host '[02] Mainboard / BIOS' -ForegroundColor Yellow
Write-Host '[03] CPU / RAM' -ForegroundColor Yellow
Write-Host '[04] Storage / Monitor / GPU / Battery' -ForegroundColor Yellow
Write-Host '[05] Windows / Build / Uptime' -ForegroundColor Yellow
Write-Host '[06] Windows Update / HotFix' -ForegroundColor Yellow
Write-Host '[07] Network / LAN / WIFI / Modem / DNS / Gateway' -ForegroundColor Yellow
Write-Host '[08] Security / Antivirus / BitLocker / Firewall / TPM / Secure Boot' -ForegroundColor Yellow
Write-Host '[09] License Windows / Office' -ForegroundColor Yellow
Write-Host '[10] Software da cai dat' -ForegroundColor Yellow
Write-Host ''
Write-Host 'Dang quet may, vui long khong tat cua so nay...' -ForegroundColor Cyan

$collector = Get-PcAuditCollector
Write-Host 'THU THAP THONG TIN: HOAN TAT' -ForegroundColor Green

$payload = @{
    code = $code
    employee_name = $employeeName
    department = $department
    data = $collector
}

Write-Host 'Dang gui du lieu ve he thong...' -ForegroundColor Yellow
$response = Invoke-PcAuditSubmit -Payload $payload
if ($response.ok -ne $true) { throw ('Server tu choi du lieu: ' + [string]$response.message) }

try {
    $computerName = [string]$collector.computer.computer_name
    if ([string]::IsNullOrWhiteSpace($computerName)) { $computerName = $env:COMPUTERNAME }
    $subject = "PC Audit - $computerName - $customerName - $branchName"
    $mailBody = ($payload | ConvertTo-Json -Depth 30)
    Write-Host 'Dang gui Email bao cao...' -ForegroundColor Yellow
    $mailResponse = Invoke-PcAuditSendMail -Code $code -Subject $subject -Body $mailBody
    if ($mailResponse.ok -eq $true) { Write-Host "Email da gui: $([string]$mailResponse.to)" -ForegroundColor Green }
} catch { Write-Warning "Audit da luu thanh cong nhung Email chua gui duoc: $($_.Exception.Message)" }

Write-Host ''
Write-Host '=========================================' -ForegroundColor Green
Write-Host ' AUDIT HOAN TAT - DU LIEU DA DUOC LUU.' -ForegroundColor Green
Write-Host " Customer : $customerName" -ForegroundColor Green
Write-Host " Chi nhanh: $branchName" -ForegroundColor Green
Write-Host " Nguoi dung: $employeeName" -ForegroundColor Green
Write-Host " Phong ban : $(if($department){$department}else{'Khong khai bao'})" -ForegroundColor Green
Write-Host " Diem Audit: $([string]$response.audit_score)" -ForegroundColor Green
Write-Host " Trang thai: $([string]$response.audit_status)" -ForegroundColor Green
Write-Host '=========================================' -ForegroundColor Green
Read-Host 'Nhan Enter de ket thuc'
