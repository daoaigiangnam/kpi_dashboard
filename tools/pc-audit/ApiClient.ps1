function Invoke-PcAuditValidateCode {
    param([Parameter(Mandatory)][string]$Code)

    $body = @{ code = $Code } | ConvertTo-Json -Depth 5 -Compress
    $headers = @{ Accept = 'application/json' }
    try {
        return Invoke-RestMethod -Uri $script:PcAuditValidateEndpoint -Method Post -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $body -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Khong the xac thuc Audit Code: $($_.Exception.Message)"
    }
}

function Invoke-PcAuditGetMailConfig {
    param([Parameter(Mandatory)][string]$Code)

    $body = @{ code = $Code } | ConvertTo-Json -Depth 5 -Compress
    $headers = @{ Accept = 'application/json' }
    try {
        return Invoke-RestMethod -Uri $script:PcAuditMailConfigEndpoint -Method Post -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $body -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
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
    $headers = @{ Accept = 'application/json' }
    try {
        return Invoke-RestMethod -Uri $script:PcAuditSendMailEndpoint -Method Post -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $json -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        throw "Khong the gui Email Audit: $($_.Exception.Message)"
    }
}

function Invoke-PcAuditSubmit {
    param([Parameter(Mandatory)][hashtable]$Payload)

    # The API resolves Customer/Branch from Audit Code. Department is not sent by the PC tool.
    $json = $Payload | ConvertTo-Json -Depth 30 -Compress
    $headers = @{ Accept = 'application/json' }
    try {
        return Invoke-RestMethod -Uri $script:PcAuditSubmitEndpoint -Method Post -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $json -TimeoutSec $script:PcAuditTimeoutSec -ErrorAction Stop
    }
    catch {
        $detail = $_.Exception.Message
        try {
            if ($_.ErrorDetails.Message) { $detail = $_.ErrorDetails.Message }
        } catch {}
        throw "Khong the gui du lieu Audit len may chu: $detail"
    }
}
