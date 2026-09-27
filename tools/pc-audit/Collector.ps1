param([switch]$Progress)

function Write-PcAuditStage {
    param([string]$Title)
    if ($Progress) { Write-Host ("`n[PC AUDIT] {0}" -f $Title) -ForegroundColor Yellow }
}
function Write-PcAuditResult {
    param([string]$Label, [object]$Value)
    if (-not $Progress) { return }
    if ($null -eq $Value) { $text = '(khong co du lieu)' }
    elseif ($Value -is [System.Array]) { $text = if($Value.Count -eq 0){'(khong co du lieu)'}else{$Value -join ', '} }
    else { $text = [string]$Value }
    Write-Host ("    {0}: {1}" -f $Label, $text) -ForegroundColor Gray
}

function Get-PcAuditCollector {
    $result = [ordered]@{}

    Write-PcAuditStage '01/10 - Computer / Domain / Serial / Asset Tag'
    $cs = Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue
    $bios = Get-CimInstance Win32_BIOS -ErrorAction SilentlyContinue
    $base = Get-CimInstance Win32_BaseBoard -ErrorAction SilentlyContinue
    $cpu = Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue
    $os = Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue
    $enclosure = Get-CimInstance Win32_SystemEnclosure -ErrorAction SilentlyContinue
    $result.computer = [ordered]@{ username=[Environment]::UserName; domain=$cs.Domain; computer_name=$env:COMPUTERNAME; manufacturer=$cs.Manufacturer; model=$cs.Model; serial_number=$bios.SerialNumber; asset_tag=$enclosure.SMBIOSAssetTag }
    Write-PcAuditResult 'Computer Name' $result.computer.computer_name
    Write-PcAuditResult 'User Windows' $result.computer.username
    Write-PcAuditResult 'Domain' $result.computer.domain
    Write-PcAuditResult 'Manufacturer / Model' ("{0} / {1}" -f $result.computer.manufacturer,$result.computer.model)
    Write-PcAuditResult 'Serial' $result.computer.serial_number
    Write-PcAuditResult 'Asset Tag' $result.computer.asset_tag

    Write-PcAuditStage '02/10 - Mainboard / BIOS'
    $result.mainboard = [ordered]@{ manufacturer=$base.Manufacturer; product=$base.Product; serial_number=$base.SerialNumber }
    $result.bios = [ordered]@{ version=$bios.SMBIOSBIOSVersion; date=$bios.ReleaseDate }
    Write-PcAuditResult 'Mainboard' ("{0} / {1}" -f $result.mainboard.manufacturer,$result.mainboard.product)
    Write-PcAuditResult 'Mainboard Serial' $result.mainboard.serial_number
    Write-PcAuditResult 'BIOS' $result.bios.version
    Write-PcAuditResult 'BIOS Date' $result.bios.date

    Write-PcAuditStage '03/10 - CPU / RAM'
    $result.cpu = @($cpu | ForEach-Object { [ordered]@{ name=$_.Name; cores=$_.NumberOfCores; threads=$_.NumberOfLogicalProcessors; max_clock=$_.MaxClockSpeed } })
    $result.memory = @(Get-CimInstance Win32_PhysicalMemory -ErrorAction SilentlyContinue | ForEach-Object { [ordered]@{ capacity_gb=[math]::Round($_.Capacity/1GB,2); speed=$_.Speed; slot=$_.DeviceLocator; manufacturer=$_.Manufacturer; part_number=$_.PartNumber; serial_number=$_.SerialNumber } })
    Write-PcAuditResult 'CPU' (($result.cpu | ForEach-Object { $_.name }) -join ' | ')
    Write-PcAuditResult 'CPU Cores / Threads' (($result.cpu | ForEach-Object { "{0}/{1}" -f $_.cores,$_.threads }) -join ', ')
    Write-PcAuditResult 'RAM' (($result.memory | ForEach-Object { "{0} GB @ {1} MHz" -f $_.capacity_gb,$_.speed }) -join ', ')

    Write-PcAuditStage '04/10 - Storage / Monitor / GPU / Battery'
    $result.storage = @(Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" -ErrorAction SilentlyContinue | ForEach-Object { [ordered]@{ drive=$_.DeviceID; used_gb=[math]::Round(($_.Size-$_.FreeSpace)/1GB,2); total_gb=[math]::Round($_.Size/1GB,2) } })
    $result.monitors = @(Get-CimInstance -Namespace root\wmi -ClassName WmiMonitorID -ErrorAction SilentlyContinue | ForEach-Object { $decode={param($a) if($a){-join($a|Where-Object{$_ -ne 0}|ForEach-Object{[char]$_})}}; [ordered]@{ manufacturer=&$decode $_.ManufacturerName; model=&$decode $_.UserFriendlyName; serial_number=&$decode $_.SerialNumberID } })
    $result.gpu = @(Get-CimInstance Win32_VideoController -ErrorAction SilentlyContinue | ForEach-Object { [ordered]@{ name=$_.Name; vram_gb=if($_.AdapterRAM){[math]::Round($_.AdapterRAM/1GB,2)}else{$null}; driver_version=$_.DriverVersion } })
    $result.battery = @(Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue | ForEach-Object { [ordered]@{ name=$_.Name; status=$_.Status; charge_percent=$_.EstimatedChargeRemaining } })
    Write-PcAuditResult 'Disk' (($result.storage | ForEach-Object { "{0}: {1}/{2} GB" -f $_.drive,$_.used_gb,$_.total_gb }) -join ', ')
    Write-PcAuditResult 'Monitor' (($result.monitors | ForEach-Object { "{0} {1}" -f $_.manufacturer,$_.model }) -join ' | ')
    Write-PcAuditResult 'GPU' (($result.gpu | ForEach-Object { $_.name }) -join ' | ')
    Write-PcAuditResult 'Battery' (($result.battery | ForEach-Object { "{0}%" -f $_.charge_percent }) -join ', ')

    Write-PcAuditStage '05/10 - Windows / Build / Uptime'
    $result.windows = [ordered]@{ caption=$os.Caption; version=$os.Version; build=$os.BuildNumber; architecture=$os.OSArchitecture; last_boot=$os.LastBootUpTime; uptime_hours=if($os.LastBootUpTime){[math]::Round(((Get-Date)-$os.LastBootUpTime).TotalHours,2)}else{$null} }
    Write-PcAuditResult 'Windows' $result.windows.caption
    Write-PcAuditResult 'Version / Build' ("{0} / {1}" -f $result.windows.version,$result.windows.build)
    Write-PcAuditResult 'Architecture' $result.windows.architecture
    Write-PcAuditResult 'Last Boot' $result.windows.last_boot
    Write-PcAuditResult 'Uptime' ("{0} hours" -f $result.windows.uptime_hours)

    Write-PcAuditStage '06/10 - Windows Update / HotFix'
    try {
        $updates=@(Get-CimInstance -Namespace root/cimv2 -ClassName Win32_QuickFixEngineering -ErrorAction Stop | Where-Object { $_.InstalledOn } | Sort-Object InstalledOn -Descending); $latest=$updates|Select-Object -First 1
        $result.windows_update=[ordered]@{ latest_kb=if($latest){[string]$latest.HotFixID}else{$null}; latest_installed_on=if($latest){[string]$latest.InstalledOn}else{$null}; count=$updates.Count }
    } catch { $result.windows_update=[ordered]@{ latest_kb=$null; latest_installed_on=$null; count=$null } }
    Write-PcAuditResult 'Latest KB' $result.windows_update.latest_kb
    Write-PcAuditResult 'Installed' $result.windows_update.latest_installed_on
    Write-PcAuditResult 'HotFix Count' $result.windows_update.count

    Write-PcAuditStage '07/10 - Network / LAN / WIFI / Modem / DNS / Gateway'
    $result.network=@(Get-CimInstance Win32_NetworkAdapterConfiguration -Filter "IPEnabled=True" -ErrorAction SilentlyContinue | ForEach-Object {
        $adapter=Get-CimInstance Win32_NetworkAdapter -Filter "Index=$($_.Index)" -ErrorAction SilentlyContinue
        $type=if($adapter.Name -match 'Wi-?Fi|Wireless|802.11'){'WIFI'}elseif($adapter.Name -match 'WWAN|Cellular|LTE|5G|4G|Mobile Broadband|Modem|Fibocom|Quectel|Sierra Wireless|Telit'){'MODEM'}else{'LAN'}
        [ordered]@{ type=$type; name=$adapter.NetConnectionID; description=$adapter.Name; ipv4=@($_.IPAddress|Where-Object{$_ -match '^\d{1,3}(\.\d{1,3}){3}$'}); gateway=@($_.DefaultIPGateway); mac=$adapter.MACAddress; dns=@($_.DNSServerSearchOrder); dhcp=$_.DHCPEnabled; connection_status=$adapter.NetConnectionStatus; link_speed=$adapter.Speed }
    })
    foreach($n in $result.network){ Write-PcAuditResult ("Network {0}" -f $n.type) ("{0} | IP: {1} | GW: {2}" -f $n.name,($n.ipv4 -join ','),($n.gateway -join ',')) }

    Write-PcAuditStage '08/10 - Security / Antivirus / BitLocker / Firewall / TPM / Secure Boot'
    $result.security=[ordered]@{}
    try{$result.security.antivirus=@(Get-CimInstance -Namespace root/SecurityCenter2 -ClassName AntiVirusProduct -ErrorAction Stop|ForEach-Object{[ordered]@{display_name=$_.displayName;status=$_.productState;executable_path=$_.pathToSignedProductExe}})}catch{$result.security.antivirus=@()}
    $result.security.bitlocker=@(Get-BitLockerVolume -ErrorAction SilentlyContinue|ForEach-Object{[ordered]@{mount_point=$_.MountPoint;protection_status=[string]$_.ProtectionStatus;volume_status=[string]$_.VolumeStatus;encryption_percent=$_.EncryptionPercentage}})
    $result.security.firewall=@(Get-NetFirewallProfile -ErrorAction SilentlyContinue|ForEach-Object{[ordered]@{profile=$_.Name;enabled=$_.Enabled}})
    try{$tpm=Get-Tpm -ErrorAction Stop;$result.security.tpm=[ordered]@{present=$tpm.TpmPresent;ready=$tpm.TpmReady;manufacturer_version=$tpm.ManufacturerVersion}}catch{$result.security.tpm=[ordered]@{present=$null;ready=$null;manufacturer_version=$null}}
    try{$result.security.secure_boot=[bool](Confirm-SecureBootUEFI -ErrorAction Stop)}catch{$result.security.secure_boot=$null}
    Write-PcAuditResult 'Antivirus' (($result.security.antivirus | ForEach-Object { $_.display_name }) -join ', ')
    Write-PcAuditResult 'BitLocker' (($result.security.bitlocker | ForEach-Object { "{0}: {1}%" -f $_.mount_point,$_.encryption_percent }) -join ', ')
    Write-PcAuditResult 'Firewall' (($result.security.firewall | ForEach-Object { "{0}={1}" -f $_.profile,$_.enabled }) -join ', ')
    Write-PcAuditResult 'TPM' ("Present={0}, Ready={1}" -f $result.security.tpm.present,$result.security.tpm.ready)
    Write-PcAuditResult 'Secure Boot' $result.security.secure_boot

    Write-PcAuditStage '09/10 - License Windows / Office'
    $result.licenses=[ordered]@{windows=@();office=@()}
    try{$result.licenses.windows=@(Get-CimInstance SoftwareLicensingProduct -ErrorAction SilentlyContinue|Where-Object{$_.PartialProductKey -and $_.Name -match 'Windows'}|ForEach-Object{[ordered]@{product_name=$_.Name;status=$_.LicenseStatus;partial_product_key=$_.PartialProductKey}})}catch{}
    try{$result.licenses.office=@(Get-CimInstance SoftwareLicensingProduct -ErrorAction SilentlyContinue|Where-Object{$_.PartialProductKey -and $_.Name -match 'Office|Microsoft 365'}|ForEach-Object{[ordered]@{product_name=$_.Name;status=$_.LicenseStatus;partial_product_key=$_.PartialProductKey}})}catch{}
    Write-PcAuditResult 'Windows License' (($result.licenses.windows | ForEach-Object { "{0} (Status {1})" -f $_.product_name,$_.status }) -join ' | ')
    Write-PcAuditResult 'Office License' (($result.licenses.office | ForEach-Object { "{0} (Status {1})" -f $_.product_name,$_.status }) -join ' | ')

    Write-PcAuditStage '10/10 - Software da cai dat'
    $paths=@('HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*','HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*','HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*')
    $software=@()
    foreach($path in $paths){
        $software += @(Get-ItemProperty $path -ErrorAction SilentlyContinue | Where-Object {
            $_.PSObject.Properties['DisplayName'] -and -not [string]::IsNullOrWhiteSpace([string]$_.DisplayName)
        } | ForEach-Object {
            [ordered]@{
                name=[string]$_.DisplayName
                version=if($_.PSObject.Properties['DisplayVersion']){[string]$_.DisplayVersion}else{$null}
                publisher=if($_.PSObject.Properties['Publisher']){[string]$_.Publisher}else{$null}
                install_date=if($_.PSObject.Properties['InstallDate']){[string]$_.InstallDate}else{$null}
                estimated_size=if($_.PSObject.Properties['EstimatedSize']){$_.EstimatedSize}else{$null}
            }
        })
    }
    $result.software=@($software | Sort-Object { $_['name'] }, { $_['version'] } -Unique)
    Write-PcAuditResult 'Software Count' $result.software.Count
    Write-PcAuditResult 'Software Sample' (($result.software | Select-Object -First 8 | ForEach-Object { if($_.version){"$($_.name) $($_.version)"}else{$_.name} }) -join ' | ')

    if($Progress){ Write-Host "`n[PC AUDIT] 10/10 - THU THAP HOAN TAT" -ForegroundColor Green }
    $result
}
