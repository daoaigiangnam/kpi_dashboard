function Get-PcAuditCollector {
    $result = [ordered]@{}

    $cs = Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue
    $bios = Get-CimInstance Win32_BIOS -ErrorAction SilentlyContinue
    $base = Get-CimInstance Win32_BaseBoard -ErrorAction SilentlyContinue
    $cpu = Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue
    $os = Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue

    $result.computer = [ordered]@{
        username = [Environment]::UserName
        domain = $cs.Domain
        computer_name = $env:COMPUTERNAME
        manufacturer = $cs.Manufacturer
        model = $cs.Model
        serial_number = $bios.SerialNumber
        asset_tag = (Get-CimInstance Win32_SystemEnclosure -ErrorAction SilentlyContinue).SMBIOSAssetTag
    }

    $result.mainboard = [ordered]@{
        manufacturer = $base.Manufacturer
        product = $base.Product
        serial_number = $base.SerialNumber
    }

    $result.bios = [ordered]@{
        version = $bios.SMBIOSBIOSVersion
        date = $bios.ReleaseDate
    }

    $result.cpu = @($cpu | ForEach-Object {
        [ordered]@{ name=$_.Name; cores=$_.NumberOfCores; threads=$_.NumberOfLogicalProcessors; max_clock=$_.MaxClockSpeed }
    })

    $result.memory = @(Get-CimInstance Win32_PhysicalMemory -ErrorAction SilentlyContinue | ForEach-Object {
        [ordered]@{ capacity_gb=[math]::Round($_.Capacity/1GB,2); speed=$_.Speed; slot=$_.DeviceLocator; manufacturer=$_.Manufacturer; part_number=$_.PartNumber; serial_number=$_.SerialNumber }
    })

    $result.storage = @(Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" -ErrorAction SilentlyContinue | ForEach-Object {
        [ordered]@{ drive=$_.DeviceID; used_gb=[math]::Round(($_.Size-$_.FreeSpace)/1GB,2); total_gb=[math]::Round($_.Size/1GB,2) }
    })

    $result.monitors = @(Get-CimInstance -Namespace root\wmi -ClassName WmiMonitorID -ErrorAction SilentlyContinue | ForEach-Object {
        $decode = { param($a) if($a){ -join ($a | Where-Object {$_ -ne 0} | ForEach-Object {[char]$_}) } }
        [ordered]@{ manufacturer=&$decode $_.ManufacturerName; model=&$decode $_.UserFriendlyName; serial_number=&$decode $_.SerialNumberID }
    })

    $result.gpu = @(Get-CimInstance Win32_VideoController -ErrorAction SilentlyContinue | ForEach-Object {
        [ordered]@{ name=$_.Name; vram_gb=if($_.AdapterRAM){[math]::Round($_.AdapterRAM/1GB,2)}else{$null}; driver_version=$_.DriverVersion }
    })

    $result.battery = @(Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue | ForEach-Object {
        [ordered]@{ name=$_.Name; status=$_.Status; charge_percent=$_.EstimatedChargeRemaining }
    })

    $result.windows = [ordered]@{
        caption=$os.Caption; version=$os.Version; build=$os.BuildNumber; architecture=$os.OSArchitecture
        last_boot=$os.LastBootUpTime
        uptime_hours=if($os.LastBootUpTime){[math]::Round(((Get-Date)-$os.LastBootUpTime).TotalHours,2)}else{$null}
    }

    $result.network = @(Get-CimInstance Win32_NetworkAdapterConfiguration -Filter "IPEnabled=True" -ErrorAction SilentlyContinue | ForEach-Object {
        $adapter = Get-CimInstance Win32_NetworkAdapter -Filter "Index=$($_.Index)" -ErrorAction SilentlyContinue
        $type = if($adapter.Name -match 'Wi-?Fi|Wireless|802.11'){ 'WIFI' } elseif($adapter.Name -match 'WWAN|Cellular|LTE|5G|4G|Mobile Broadband|Modem|Fibocom|Quectel|Sierra Wireless|Telit'){ 'MODEM' } else { 'LAN' }
        [ordered]@{
            type=$type; name=$adapter.NetConnectionID; description=$adapter.Name
            ipv4=@($_.IPAddress | Where-Object {$_ -match '^\d{1,3}(\.\d{1,3}){3}$'})
            gateway=@($_.DefaultIPGateway); mac=$adapter.MACAddress
            dns=@($_.DNSServerSearchOrder); dhcp=$_.DHCPEnabled
            connection_status=$adapter.NetConnectionStatus
            link_speed=$adapter.Speed
        }
    })

    $result.security = [ordered]@{}
    try {
        $result.security.antivirus = @(Get-CimInstance -Namespace root/SecurityCenter2 -ClassName AntiVirusProduct -ErrorAction Stop | ForEach-Object {
            [ordered]@{ display_name=$_.displayName; status=$_.productState; executable_path=$_.pathToSignedProductExe }
        })
    } catch {
        $result.security.antivirus = @()
    }

    $result.security.bitlocker = @(Get-BitLockerVolume -ErrorAction SilentlyContinue | ForEach-Object {
        [ordered]@{ mount_point=$_.MountPoint; protection_status=[string]$_.ProtectionStatus; volume_status=[string]$_.VolumeStatus; encryption_percent=$_.EncryptionPercentage }
    })

    $result.security.firewall = @(Get-NetFirewallProfile -ErrorAction SilentlyContinue | ForEach-Object {
        [ordered]@{ profile=$_.Name; enabled=$_.Enabled }
    })

    try {
        $tpm = Get-Tpm -ErrorAction Stop
        $result.security.tpm = [ordered]@{ present=$tpm.TpmPresent; ready=$tpm.TpmReady; manufacturer_version=$tpm.ManufacturerVersion }
    } catch {
        $result.security.tpm = [ordered]@{ present=$null; ready=$null; manufacturer_version=$null }
    }

    try { $result.security.secure_boot = [bool](Confirm-SecureBootUEFI -ErrorAction Stop) } catch { $result.security.secure_boot = $null }

    $result.licenses = [ordered]@{
        windows = @()
        office = @()
    }

    try {
        $result.licenses.windows = @(Get-CimInstance SoftwareLicensingProduct -ErrorAction SilentlyContinue | Where-Object { $_.PartialProductKey -and $_.Name -match 'Windows' } | ForEach-Object {
            [ordered]@{ product_name=$_.Name; status=$_.LicenseStatus; partial_product_key=$_.PartialProductKey }
        })
    } catch {}

    try {
        $result.licenses.office = @(Get-CimInstance SoftwareLicensingProduct -ErrorAction SilentlyContinue | Where-Object { $_.PartialProductKey -and $_.Name -match 'Office|Microsoft 365' } | ForEach-Object {
            [ordered]@{ product_name=$_.Name; status=$_.LicenseStatus; partial_product_key=$_.PartialProductKey }
        })
    } catch {}

    $paths = @(
        'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*'
    )
    $result.software = @(
        foreach($path in $paths){
            Get-ItemProperty $path -ErrorAction SilentlyContinue | Where-Object {$_.DisplayName} | ForEach-Object {
                [ordered]@{ name=$_.DisplayName; version=$_.DisplayVersion; publisher=$_.Publisher; install_date=$_.InstallDate; estimated_size=$_.EstimatedSize }
            }
        }
    ) | Sort-Object { $_.name }, { $_.version } -Unique

    $result
}
