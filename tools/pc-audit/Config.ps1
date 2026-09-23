# PC Audit Tool configuration
# The bootstrap endpoint is intentionally stable. The actual API base URL is managed by Admin on the Web.
$script:PcAuditConfigEndpoint = 'https://kpi.review360.id.vn/api/pc-audit/config'
$script:PcAuditApiBaseUrl = $null
$script:PcAuditValidateEndpoint = $null
$script:PcAuditSubmitEndpoint = $null
$script:PcAuditMailConfigEndpoint = $null
$script:PcAuditSendMailEndpoint = $null
$script:PcAuditTimeoutSec = 120
$script:PcAuditServerConfig = $null

function Initialize-PcAuditConfig {
    try {
        $config = Invoke-RestMethod -Uri $script:PcAuditConfigEndpoint -Method Get -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Không thể lấy cấu hình PC Audit từ máy chủ: $($_.Exception.Message)"
    }

    if ($config.enabled -ne $true) {
        $message = [string]$config.disabled_message
        if ([string]::IsNullOrWhiteSpace($message)) { $message = 'Hệ thống Audit hiện đang tạm ngưng. Vui lòng thử lại sau.' }
        throw $message
    }

    $baseUrl = ([string]$config.api_base_url).TrimEnd('/')
    if ([string]::IsNullOrWhiteSpace($baseUrl) -or $baseUrl -notmatch '^https://') {
        throw 'Cấu hình API Base URL không hợp lệ. Chỉ cho phép HTTPS.'
    }

    $script:PcAuditApiBaseUrl = $baseUrl
    $script:PcAuditValidateEndpoint = "$baseUrl/pc-audit/validate-code"
    $script:PcAuditSubmitEndpoint = "$baseUrl/pc-audit/submit"
    $script:PcAuditMailConfigEndpoint = "$baseUrl/pc-audit/mail-config"
    $script:PcAuditSendMailEndpoint = "$baseUrl/pc-audit/send-mail"
    $script:PcAuditServerConfig = $config
}
