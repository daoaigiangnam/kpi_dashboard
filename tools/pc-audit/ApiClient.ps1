function Invoke-PcAuditValidateCode {
    param([Parameter(Mandatory)][string]$Code)

    $body = @{ code = $Code } | ConvertTo-Json -Depth 5
    try {
        return Invoke-RestMethod -Uri $script:PcAuditValidateEndpoint -Method Post -ContentType 'application/json' -Body $body -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Không thể xác thực Audit Code: $($_.Exception.Message)"
    }
}

function Invoke-PcAuditSubmit {
    param([Parameter(Mandatory)][hashtable]$Payload)

    $json = $Payload | ConvertTo-Json -Depth 30 -Compress
    try {
        return Invoke-RestMethod -Uri $script:PcAuditSubmitEndpoint -Method Post -ContentType 'application/json' -Body $json -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Không thể gửi dữ liệu Audit lên máy chủ: $($_.Exception.Message)"
    }
}
