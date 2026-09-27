function Invoke-PcAuditValidateCode {
    param([Parameter(Mandatory)][string]$Code)

    $body = @{ code = $Code } | ConvertTo-Json -Depth 5
    try {
        return Invoke-RestMethod -Uri $script:PcAuditValidateEndpoint -Method Post -ContentType 'application/json' -Body $body -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Khong the xac thuc Audit Code: $($_.Exception.Message)"
    }
}

function Invoke-PcAuditGetMailConfig {
    param([Parameter(Mandatory)][string]$Code)

    $body = @{ code = $Code } | ConvertTo-Json -Depth 5
    try {
        return Invoke-RestMethod -Uri $script:PcAuditMailConfigEndpoint -Method Post -ContentType 'application/json' -Body $body -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Khong the lay cau hinh Email tu may chu: $($_.Exception.Message)"
    }
}

function Invoke-PcAuditSendMail {
    param(
        [Parameter(Mandatory)][string]$Code,
        [Parameter(Mandatory)][string]$Subject,
        [Parameter(Mandatory)][string]$Body
    )

    $json = @{ code = $Code; subject = $Subject; body = $Body } | ConvertTo-Json -Depth 5 -Compress
    try {
        return Invoke-RestMethod -Uri $script:PcAuditSendMailEndpoint -Method Post -ContentType 'application/json' -Body $json -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Khong the gui Email Audit: $($_.Exception.Message)"
    }
}

function Invoke-PcAuditSubmit {
    param([Parameter(Mandatory)][hashtable]$Payload)

    $json = $Payload | ConvertTo-Json -Depth 30 -Compress
    try {
        return Invoke-RestMethod -Uri $script:PcAuditSubmitEndpoint -Method Post -ContentType 'application/json' -Body $json -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Khong the gui du lieu Audit len may chu: $($_.Exception.Message)"
    }
}
