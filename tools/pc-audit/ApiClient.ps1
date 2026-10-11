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
        $responseBody = $null

        # Windows PowerShell may not populate ErrorDetails.Message for HTTP 422.
        # Read the response stream directly so Laravel validation errors are visible.
        try {
            if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
                $responseBody = [string]$_.ErrorDetails.Message
            }
        } catch {}

        if ([string]::IsNullOrWhiteSpace($responseBody)) {
            try {
                $httpResponse = $_.Exception.Response
                if ($null -ne $httpResponse) {
                    $stream = $httpResponse.GetResponseStream()
                    if ($null -ne $stream) {
                        $reader = New-Object System.IO.StreamReader($stream)
                        try { $responseBody = $reader.ReadToEnd() } finally { $reader.Dispose() }
                    }
                }
            } catch {}
        }

        if (-not [string]::IsNullOrWhiteSpace($responseBody)) {
            try {
                $parsed = $responseBody | ConvertFrom-Json -ErrorAction Stop
                if ($parsed.errors) {
                    $lines = @()
                    foreach ($key in $parsed.errors.PSObject.Properties.Name) {
                        $messages = @($parsed.errors.$key | ForEach-Object { [string]$_ }) -join '; '
                        $lines += ($key + ': ' + $messages)
                    }
                    if ($lines.Count -gt 0) { $detail = $lines -join ' | ' }
                    elseif ($parsed.message) { $detail = [string]$parsed.message }
                    else { $detail = $responseBody }
                } elseif ($parsed.message) {
                    $detail = [string]$parsed.message
                } else {
                    $detail = $responseBody
                }
            } catch {
                $detail = $responseBody
            }
        }

        throw "Khong the gui du lieu Audit len may chu: $detail"
}
