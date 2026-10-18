'use strict';
/* MSTAR PC Audit Tool shared by Windows 7/10/11; built-in modules only. */
var os=require('os'),fs=require('fs'),path=require('path'),https=require('https'),cp=require('child_process'),readline=require('readline');
var EMBEDDED_AUDIT_CODE='__MSTAR_AUDIT_CODE__';
var CONFIG_ENDPOINT='https://kpi.review360.id.vn/api/pc-audit/config';
function ask(rl,q){return new Promise(function(resolve){rl.question(q,function(v){resolve(String(v||'').trim());});});}
function txt(v){return v===undefined||v===null?'':String(v).trim();}
function stage(title){console.log('\n[THU THAP] '+title);}
function detail(label,value){var v=value;if(v===undefined||v===null||v===''||(Array.isArray(v)&&!v.length))v='(khong co du lieu)';else if(Array.isArray(v))v=v.join(' | ');console.log('  - '+label+': '+String(v));}
function listValues(rows,fn){return (rows||[]).map(fn).filter(function(v){return !!v;});}
function num(v){var n=Number(txt(v).replace(/,/g,''));return txt(v)===''||!isFinite(n)?null:n;}
function rnd(v,d){return v===null||v===undefined||!isFinite(Number(v))?null:Number(Number(v).toFixed(d));}
function gb(v){var n=num(v);return n===null?null:rnd(n/1073741824,2);}
function parseArray(v){if(Array.isArray(v))return v.map(function(x){return txt(x);}).filter(Boolean);var s=txt(v);if(!s)return[];if(s.charAt(0)==='{'&&s.charAt(s.length-1)==='}')s=s.slice(1,-1);return s.split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/).map(function(x){return x.trim().replace(/^"|"$/g,'');}).filter(Boolean);}
function parseList(s){var rows=[],row={};String(s||'').replace(/^\uFEFF/,'').split(/\r?\n/).forEach(function(line){line=line.trim();if(!line){if(Object.keys(row).length)rows.push(row);row={};return;}var i=line.indexOf('=');if(i>0)row[line.slice(0,i).trim()]=line.slice(i+1).trim();});if(Object.keys(row).length)rows.push(row);return rows;}
function exec(file,args,timeout){return new Promise(function(resolve){cp.execFile(file,args,{windowsHide:true,timeout:timeout||25000,maxBuffer:8*1024*1024,encoding:'utf8'},function(err,stdout,stderr){resolve({ok:!err,out:String(stdout||''),err:String(stderr||''),error:err?err.message:null});});});}
async function wmi(cls,props,opt){
 opt=opt||{};
 var isWin7=process.platform==='win32'&&String(os.release()).indexOf('6.1.')===0;
 function runWmic(){
   var args=[];if(opt.ns)args.push('/namespace:'+opt.ns);args.push('path',cls);if(opt.where)args.push('where',opt.where);args.push('get',props.join(','),'/format:list');
   return exec('wmic',args,30000).then(function(r){return r.ok?parseList(r.out):[];});
 }
 function runPowerShell(){
   var q="try { $ErrorActionPreference='Stop'; $p=@("+props.map(function(p){return "'"+p.replace(/'/g,"''")+"'";}).join(",")+"); ";
   q+="if (Get-Command Get-CimInstance -ErrorAction SilentlyContinue) { $x=Get-CimInstance -ClassName '"+cls.replace(/'/g,"''")+"'";
   if(opt.ns)q+=" -Namespace '"+opt.ns.split('\\').join('\\\\').split('/').join('\\\\').replace(/^\\\\+/,'').replace(/'/g,"''")+"'";
   if(opt.where)q+=" -Filter '"+opt.where.replace(/'/g,"''")+"'";
   q+=" -Property $p } else { $x=Get-WmiObject -Class '"+cls.replace(/'/g,"''")+"'";
   if(opt.ns)q+=" -Namespace '"+opt.ns.split('\\').join('\\\\').split('/').join('\\\\').replace(/^\\\\+/,'').replace(/'/g,"''")+"'";
   if(opt.where)q+=" -Filter '"+opt.where.replace(/'/g,"''")+"'";
   q+=" -Property $p }; @($x) | ConvertTo-Json -Depth 5 -Compress } catch { exit 2 }";
   return exec('powershell.exe',['-NoProfile','-ExecutionPolicy','Bypass','-Command',q],30000).then(function(r){
     if(!r.ok||!txt(r.out))return[];
     try{var data=JSON.parse(r.out);return Array.isArray(data)?data:(data&&typeof data==='object'?[data]:[]);}catch(e){return[];}
   });
 }
 function field(row,name){if(!row)return undefined;if(Object.prototype.hasOwnProperty.call(row,name))return row[name];var keys=Object.keys(row);for(var i=0;i<keys.length;i++)if(keys[i].toLowerCase()===name.toLowerCase())return row[keys[i]];return undefined;}
 function hasUsefulValue(v){return v!==undefined&&v!==null&&String(v).trim()!=='';}
 function mergeRows(primary,fallback){
   if(!primary.length)return fallback;
   if(!fallback.length)return primary;
   return primary.map(function(row,i){
     var extra=fallback[i]||{},merged={},k;
     Object.keys(row).forEach(function(key){merged[key]=row[key];});
     props.forEach(function(prop){
       var current=field(row,prop),other=field(extra,prop);
       if(!hasUsefulValue(current)&&hasUsefulValue(other))merged[prop]=other;
     });
     return merged;
   });
 }
 // Windows 7: query WMIC first because it is native there; use the existing PowerShell/WMI
 // path only when WMIC fails or at least one requested property is missing.
 if(isWin7){
   var legacy=await runWmic();
   var complete=legacy.length>0&&legacy.every(function(row){return props.every(function(prop){return hasUsefulValue(field(row,prop));});});
   if(complete)return legacy;
   var fallback=await runPowerShell();
   return mergeRows(legacy,fallback);
 }
 // Windows 10/11: preserve the existing PowerShell-first behavior, with WMIC fallback.
 var modern=await runPowerShell();
 if(modern.length)return modern;
 return await runWmic();
}
function val(r,k){return r?txt(r[k]):'';}
function dateDmtf(v){if(v&&typeof v==='object'){if(v.value!==undefined)return dateDmtf(v.value);if(v.DateTime!==undefined)return dateDmtf(v.DateTime);}var s=txt(v);if(!s)return null;var dotnet=s.match(/^\/Date\((-?\d+)(?:[+-]\d+)?\)\/$/);if(dotnet){var d=new Date(Number(dotnet[1]));return isNaN(d.getTime())?null:d.toISOString().slice(0,19);}var m=s.match(/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/);if(m)return m[1]+'-'+m[2]+'-'+m[3]+'T'+m[4]+':'+m[5]+':'+m[6];var iso=s.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/);return iso?iso[1]:s;}
function readRegRows(out,source){var result=[],pathNow='',values={};function flush(){if(values.DisplayName)result.push({name:values.DisplayName,version:values.DisplayVersion||null,publisher:values.Publisher||null,install_date:values.InstallDate||null,estimated_size:num(values.EstimatedSize),source:source});values={};}String(out||'').split(/\r?\n/).forEach(function(line){var km=line.match(/^(HKEY_[^\r\n]+)$/i);if(km){if(pathNow)flush();pathNow=km[1];return;}var m=line.match(/^\s+([^\s]+)\s+REG_\w+\s*(.*)$/i);if(m)values[m[1]]=m[2].trim();});if(pathNow)flush();return result;}
async function software(){var keys=[['HKLM\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall','Registry HKLM'],['HKLM\\SOFTWARE\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall','Registry HKLM WOW6432Node'],['HKCU\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall','Registry HKCU']],all=[],seen={};for(var i=0;i<keys.length;i++){var r=await exec('reg',['query',keys[i][0],'/s'],35000);all=all.concat(readRegRows(r.out,keys[i][1]));}return all.filter(function(x){var k=(x.name+'|'+(x.version||'')+'|'+(x.publisher||'')).toLowerCase();if(seen[k])return false;seen[k]=true;return true;}).sort(function(a,b){return a.name.localeCompare(b.name);});}
function licenseText(v){return ({0:'Unlicensed',1:'Licensed / Activated',2:'OOB Grace',3:'OOT Grace',4:'Non-Genuine Grace',5:'Notification',6:'Extended Grace'})[num(v)]||('Unknown ('+txt(v)+')');}
async function collect(){
 stage('01/10 - May tinh / nguoi dung / domain / serial / asset tag');
 var out={},a,cs=(await wmi('Win32_ComputerSystem',['Name','Domain','Manufacturer','Model','UserName']))[0]||{},bios=(await wmi('Win32_BIOS',['SerialNumber','SMBIOSBIOSVersion','ReleaseDate']))[0]||{},bb=(await wmi('Win32_BaseBoard',['Manufacturer','Product','SerialNumber']))[0]||{},enc=(await wmi('Win32_SystemEnclosure',['SMBIOSAssetTag']))[0]||{},osr=(await wmi('Win32_OperatingSystem',['Caption','Version','BuildNumber','OSArchitecture','InstallDate','RegisteredUser','Organization','SerialNumber','ProductType','OperatingSystemSKU','OSLanguage','CSDVersion','WindowsDirectory','SystemDirectory','SystemDrive','Manufacturer','Status','TotalVisibleMemorySize','LastBootUpTime']))[0]||{};
 out.computer={username:val(cs,'UserName').split('\\').pop()||os.userInfo().username,domain:val(cs,'Domain')||null,computer_name:val(cs,'Name')||os.hostname(),manufacturer:val(cs,'Manufacturer')||null,model:val(cs,'Model')||null,serial_number:val(bios,'SerialNumber')||null,asset_tag:val(enc,'SMBIOSAssetTag')||null};
 detail('Computer Name',out.computer.computer_name);detail('User Windows',out.computer.username);detail('Domain',out.computer.domain);detail('Manufacturer / Model',out.computer.manufacturer+' / '+out.computer.model);detail('Serial BIOS',out.computer.serial_number);detail('Asset Tag',out.computer.asset_tag);
 stage('02/10 - Mainboard / BIOS');
 out.mainboard={manufacturer:val(bb,'Manufacturer')||null,product:val(bb,'Product')||null,serial_number:val(bb,'SerialNumber')||null};out.bios={version:val(bios,'SMBIOSBIOSVersion')||null,date:dateDmtf(bios.ReleaseDate)};
 detail('Mainboard',out.mainboard.manufacturer+' / '+out.mainboard.product);detail('Mainboard Serial',out.mainboard.serial_number);detail('BIOS Version',out.bios.version);detail('BIOS Date',out.bios.date);
 stage('03/10 - CPU / RAM');
 out.cpu=(await wmi('Win32_Processor',['Name','NumberOfCores','NumberOfLogicalProcessors','MaxClockSpeed'])).map(function(r){return{name:val(r,'Name')||null,cores:num(r.NumberOfCores),threads:num(r.NumberOfLogicalProcessors),max_clock:num(r.MaxClockSpeed)};});
 var ramRows=await wmi('Win32_PhysicalMemory',['Capacity','Speed','ConfiguredClockSpeed','DeviceLocator','BankLabel','Manufacturer','PartNumber','SerialNumber','FormFactor','MemoryType','DataWidth','TotalWidth']);
 // Windows 7 WMIC can return sparse fields on some BIOS/WMI providers. Retry with the exact minimal query used for diagnosis.
 if(!ramRows.some(function(r){return num(r.Capacity)>0;})){
   var ramFallback=await exec('wmic',['path','Win32_PhysicalMemory','get','Capacity,Speed,DeviceLocator,Manufacturer,PartNumber,SerialNumber','/format:list'],30000);
   ramRows=parseList(ramFallback.out);
 }
 // Keep canonical DB fields and raw WMI fields side-by-side so no useful module data is lost.
 out.memory=ramRows.map(function(r){
   var bytes=num(r.Capacity)||num(r.capacity)||num(r.CapacityBytes),g=gb(bytes);
   var speed=num(r.ConfiguredClockSpeed)||num(r.Speed)||num(r.speed);
   var slot=val(r,'DeviceLocator')||val(r,'device_locator')||val(r,'BankLabel')||val(r,'bank_label');
   return{
     capacity:g===null?null:g+' GB',
     capacity_gb:g,
     capacity_bytes:bytes,
     speed:speed,
     configured_clock_speed:num(r.ConfiguredClockSpeed)||speed,
     slot:slot||null,
     bank_label:val(r,'BankLabel')||val(r,'bank_label')||null,
     manufacturer:val(r,'Manufacturer')||val(r,'manufacturer')||null,
     part_number:val(r,'PartNumber')||val(r,'part_number')||null,
     serial_number:val(r,'SerialNumber')||val(r,'serial_number')||null,
     form_factor:num(r.FormFactor),
     memory_type:num(r.MemoryType),
     data_width:num(r.DataWidth),
     total_width:num(r.TotalWidth),
     wmi_raw:r
   };
 }).filter(function(r){return r.capacity_bytes>0||r.slot||r.manufacturer||r.part_number||r.serial_number;});
 detail('CPU',listValues(out.cpu,function(x){return x.name;}).join(' | '));detail('CPU Cores / Threads',listValues(out.cpu,function(x){return x.cores+'/'+x.threads;}).join(', '));detail('RAM Total GB',rnd(out.memory.reduce(function(sum,r){return sum+(Number(r.capacity_gb)||0);},0),2));detail('RAM Modules',listValues(out.memory,function(x){return (x.capacity_gb||'?')+' GB @ '+(x.speed||'?')+' MHz | Slot '+(x.slot||'?')+' | '+(x.manufacturer||'')+' '+(x.bank_label||'')+' | Part '+(x.part_number||'?')+' | Serial '+(x.serial_number||'?');}).join(' ; '));
 out.memory_summary={
   module_count:out.memory.length,
   total_capacity_gb:out.memory.reduce(function(sum,r){return sum+(Number(r.capacity_gb)||0);},0),
   query:'Win32_PhysicalMemory',
   warning:out.memory.length?'': 'WMI/WMIC khong tra ve module RAM; can kiem tra dich vu WMI/BIOS tren may.'
 };
 stage('04/10 - O dia / phan vung / monitor / GPU / pin');
 var disks=await wmi('Win32_LogicalDisk',['DeviceID','VolumeName','FreeSpace','Size','FileSystem','DriveType']);out.storage=disks.filter(function(r){return num(r.DriveType)===3&&num(r.Size)>0;}).map(function(r){var sz=num(r.Size),fr=num(r.FreeSpace);return{drive:val(r,'DeviceID')||null,volume_name:val(r,'VolumeName')||null,free_gb:gb(fr),used_gb:sz===null||fr===null?null:gb(sz-fr),total_gb:gb(sz),filesystem:val(r,'FileSystem')||null};});
 out.physical_disks=(await wmi('Win32_DiskDrive',['Model','Size','InterfaceType','SerialNumber','MediaType'])).map(function(r){return{model:val(r,'Model')||null,size_gb:gb(r.Size),interface_type:val(r,'InterfaceType')||null,serial_number:val(r,'SerialNumber')||null,media_type:val(r,'MediaType')||null};});
 function decodeWmi(v){return parseArray(v).map(Number).filter(function(n){return n>0;}).map(function(n){return String.fromCharCode(n);}).join('')||null;}
 out.monitors=(await wmi('WmiMonitorID',['ManufacturerName','UserFriendlyName','SerialNumberID'],{ns:'\\\\root\\wmi'})).map(function(r){return{manufacturer:decodeWmi(r.ManufacturerName),model:decodeWmi(r.UserFriendlyName),serial_number:decodeWmi(r.SerialNumberID)};}).filter(function(r){return r.manufacturer||r.model||r.serial_number;});
 out.gpu=(await wmi('Win32_VideoController',['Name','AdapterRAM','DriverVersion'])).map(function(r){var v=gb(r.AdapterRAM);return{name:val(r,'Name')||null,vram:v===null?null:v+' GB',vram_gb:v,driver_version:val(r,'DriverVersion')||null};});
 out.battery=(await wmi('Win32_Battery',['Name','Status','EstimatedChargeRemaining'])).map(function(r){return{name:val(r,'Name')||null,status:val(r,'Status')||null,charge_percent:num(r.EstimatedChargeRemaining)};});
 detail('Logical disks',listValues(out.storage,function(x){return x.drive+' '+x.used_gb+'/'+x.total_gb+' GB, free '+x.free_gb+' GB, '+x.filesystem;}).join(' | '));
 detail('Physical disks',listValues(out.physical_disks,function(x){return x.model+' '+x.size_gb+' GB '+x.interface_type+' Serial '+(x.serial_number||'?');}).join(' | '));
 detail('Monitor',listValues(out.monitors,function(x){return x.manufacturer+' '+x.model+' Serial '+(x.serial_number||'?');}).join(' | '));
 detail('GPU',listValues(out.gpu,function(x){return x.name+' VRAM '+x.vram+' Driver '+x.driver_version;}).join(' | '));
 detail('Battery',listValues(out.battery,function(x){return x.name+' '+x.charge_percent+'% '+x.status;}).join(' | '));
 stage('05/10 - Windows / Build / Uptime');
 var total=num(osr.TotalVisibleMemorySize),boot=dateDmtf(osr.LastBootUpTime),bootDate=boot?new Date(boot):null,up=bootDate&&!isNaN(bootDate.getTime())?rnd((Date.now()-bootDate.getTime())/3600000,2):null;
 out.windows={caption:val(osr,'Caption')||null,version:val(osr,'Version')||null,build:val(osr,'BuildNumber')||null,architecture:val(osr,'OSArchitecture')||(os.arch()==='x64'?'64-bit':'32-bit'),install_date:dateDmtf(osr.InstallDate),registered_user:val(osr,'RegisteredUser')||null,organization:val(osr,'Organization')||null,serial_number:val(osr,'SerialNumber')||null,product_type:num(osr.ProductType),sku:num(osr.OperatingSystemSKU),language:num(osr.OSLanguage),csd_version:val(osr,'CSDVersion')||null,windows_directory:val(osr,'WindowsDirectory')||null,system_directory:val(osr,'SystemDirectory')||null,system_drive:val(osr,'SystemDrive')||null,manufacturer:val(osr,'Manufacturer')||null,status:val(osr,'Status')||null,total_visible_memory_gb:total===null?null:rnd(total/1048576,2),last_boot:boot,uptime_hours:up};
 detail('Windows',out.windows.caption);detail('Version / Build',out.windows.version+' / '+out.windows.build);detail('Architecture',out.windows.architecture);detail('Install Date',out.windows.install_date);detail('Registered User',out.windows.registered_user);detail('OS Serial / SKU',out.windows.serial_number+' / '+out.windows.sku);detail('System Drive / Windows Dir',out.windows.system_drive+' / '+out.windows.windows_directory);detail('Last Boot',out.windows.last_boot);detail('Uptime Hours',out.windows.uptime_hours);
 stage('06/10 - Windows Update / HotFix');
 var updates=(await wmi('Win32_QuickFixEngineering',['HotFixID','InstalledOn'])).filter(function(r){return val(r,'HotFixID')&&val(r,'InstalledOn');}).sort(function(a,b){return val(b,'InstalledOn').localeCompare(val(a,'InstalledOn'));});out.windows_update={latest_kb:updates.length?val(updates[0],'HotFixID'):null,latest_installed_on:updates.length?val(updates[0],'InstalledOn'):null,count:updates.length};
 detail('Latest KB',out.windows_update.latest_kb);detail('Latest KB Installed',out.windows_update.latest_installed_on);detail('HotFix Count',out.windows_update.count);
 stage('07/10 - Network / LAN / WIFI / Modem / DNS / Gateway');
 var na=await wmi('Win32_NetworkAdapterConfiguration',['Index','Description','IPAddress','DefaultIPGateway','DNSServerSearchOrder','DHCPEnabled','MACAddress','IPEnabled','DHCPServer'],{where:'IPEnabled=True'}),ad=await wmi('Win32_NetworkAdapter',['Index','Name','NetConnectionID','NetConnectionStatus','Speed','MACAddress']);
 out.network=na.map(function(n){var a=ad.filter(function(x){return String(x.Index)===String(n.Index);})[0]||{},name=val(a,'Name')||val(n,'Description'),ips=parseArray(n.IPAddress).filter(function(ip){return /^\d{1,3}(\.\d{1,3}){3}$/.test(ip);}),gw=parseArray(n.DefaultIPGateway),dns=parseArray(n.DNSServerSearchOrder);return{type:/wi-?fi|wireless|802\.11/i.test(name)?'WIFI':/wwan|cellular|lte|5g|4g|mobile broadband|modem/i.test(name)?'MODEM':'LAN',name:val(a,'NetConnectionID')||name||null,description:name||null,ipv4:ips,gateway:gw,mac:val(a,'MACAddress')||val(n,'MACAddress')||null,dns:dns,dhcp:/^true$/i.test(val(n,'DHCPEnabled')),connection_status:num(a.NetConnectionStatus),link_speed:num(a.Speed),dhcp_server:val(n,'DHCPServer')||null};}).filter(function(n){return n.ipv4.length||n.gateway.length||n.mac;});
 detail('Network adapters',listValues(out.network,function(n){return n.type+' | '+n.name+' | IP '+n.ipv4.join(',')+' | GW '+n.gateway.join(',')+' | DNS '+n.dns.join(',')+' | MAC '+n.mac+' | DHCP '+n.dhcp;}).join('\\n    '));
 stage('08/10 - Security / Antivirus / BitLocker / Firewall / TPM / Secure Boot');
 var av=await wmi('AntiVirusProduct',['displayName','productState','pathToSignedProductExe'],{ns:'\\\\root\\SecurityCenter2'});var avRows=av.map(function(r){return{display_name:val(r,'displayName')||null,status:num(r.productState),executable_path:val(r,'pathToSignedProductExe')||null};});if(!avRows.length){var defenderScript="try { if (Get-Command Get-MpComputerStatus -ErrorAction SilentlyContinue) { $m=Get-MpComputerStatus; @{display_name='Microsoft Defender Antivirus';status=if($m.AntivirusEnabled){'Enabled'}else{'Disabled'};executable_path=$m.AMServiceVersion;real_time_protection=$m.RealTimeProtectionEnabled;signature_version=$m.AntivirusSignatureVersion} | ConvertTo-Json -Compress } } catch { }";var defender=await exec('powershell.exe',['-NoProfile','-ExecutionPolicy','Bypass','-Command',defenderScript],15000);if(defender.out){try{var dr=JSON.parse(defender.out);if(dr&&dr.display_name)avRows.push(dr);}catch(e){}}}out.security={antivirus:avRows,bitlocker:[],firewall:[],tpm:{present:null,ready:null,manufacturer_version:null},secure_boot:null};
 var fw=(await exec('netsh',['advfirewall','show','allprofiles'],15000)).out;var currentProfile=null;fw.split(/\r?\n/).forEach(function(line){var heading=line.match(/^\s*(Domain|Private|Public) Profile Settings:/i);if(heading){currentProfile=heading[1];return;}if(currentProfile){var state=line.match(/^\s*State\s+(ON|OFF)/i);if(state){out.security.firewall.push({profile:currentProfile,enabled:state[1].toUpperCase()==='ON'});currentProfile=null;}}});
 var tpm=await wmi('Win32_Tpm',['IsEnabled_InitialValue','IsActivated_InitialValue','ManufacturerVersion'],{ns:'\\\\root\\CIMV2\\Security\\MicrosoftTpm'});if(tpm.length){out.security.tpm={present:true,ready:/true|1/i.test(val(tpm[0],'IsEnabled_InitialValue'))&&/true|1/i.test(val(tpm[0],'IsActivated_InitialValue')),manufacturer_version:val(tpm[0],'ManufacturerVersion')||null};}else{out.security.tpm={present:false,ready:false,manufacturer_version:null};}
 // Query native Windows security cmdlets when available. Unsupported cmdlets on Windows 7 are ignored.
 var psSecurity='try { $x=@(); if (Get-Command Get-BitLockerVolume -ErrorAction SilentlyContinue) { $x=@(Get-BitLockerVolume | ForEach-Object { @{mount_point=$_.MountPoint;protection_status=[string]$_.ProtectionStatus;volume_status=[string]$_.VolumeStatus;encryption_percent=$_.EncryptionPercentage} }) }; $f=@(); if (Get-Command Get-NetFirewallProfile -ErrorAction SilentlyContinue) { $f=@(Get-NetFirewallProfile | ForEach-Object { @{profile=$_.Name;enabled=[bool]$_.Enabled} }) }; $t=$null; if (Get-Command Get-Tpm -ErrorAction SilentlyContinue) { $z=Get-Tpm; $t=@{present=$z.TpmPresent;ready=$z.TpmReady;manufacturer_version=$z.ManufacturerVersion} }; $sb=$null; if (Get-Command Confirm-SecureBootUEFI -ErrorAction SilentlyContinue) { try {$sb=[bool](Confirm-SecureBootUEFI -ErrorAction Stop)} catch {$sb=$null} }; @{bitlocker=$x;firewall=$f;tpm=$t;secure_boot=$sb} | ConvertTo-Json -Compress -Depth 5 } catch { }';
 var psr=await exec('powershell.exe',['-NoProfile','-ExecutionPolicy','Bypass','-Command',psSecurity],25000);
 if(psr.out){try{
   var psdata=JSON.parse(psr.out);
   function asRows(v){if(v===undefined||v===null)return[];return Array.isArray(v)?v:[v];}
   var bitRows=asRows(psdata.bitlocker),fwRows=asRows(psdata.firewall);
   if(bitRows.length)out.security.bitlocker=bitRows;
   if(fwRows.length)out.security.firewall=fwRows;
   if(psdata.tpm)out.security.tpm=psdata.tpm;
   if(psdata.secure_boot!==undefined&&psdata.secure_boot!==null)out.security.secure_boot=psdata.secure_boot;
 }catch(e){console.log('  - Canh bao: khong doc duoc mot so truong bao mat tu PowerShell: '+e.message);}}
 detail('Antivirus',listValues(out.security.antivirus,function(x){return x.display_name+' (state '+x.status+')';}).join(' | '));
 detail('BitLocker',listValues(out.security.bitlocker,function(x){return x.mount_point+': '+x.protection_status+', '+x.encryption_percent+'%';}).join(' | '));
 detail('Firewall',listValues(out.security.firewall,function(x){return x.profile+'='+x.enabled;}).join(' | '));
 detail('TPM',JSON.stringify(out.security.tpm));detail('Secure Boot',out.security.secure_boot);
 stage('09/10 - Windows / Office License');
 var lic=await wmi('SoftwareLicensingProduct',['Name','LicenseStatus','PartialProductKey','Description','LicenseFamily','ApplicationID','ProductKeyID2','GracePeriodRemaining']);function getLic(regex){return lic.filter(function(r){return regex.test(val(r,'Name'))&&val(r,'PartialProductKey');}).map(function(r){return{product_name:val(r,'Name'),status:num(r.LicenseStatus),status_text:licenseText(r.LicenseStatus),partial_product_key:val(r,'PartialProductKey')||null,description:val(r,'Description')||null,license_family:val(r,'LicenseFamily')||null,application_id:val(r,'ApplicationID')||null,product_key_channel:val(r,'ProductKeyID2')||null,grace_period_remaining:num(r.GracePeriodRemaining)};});}
 out.licenses={windows:getLic(/Windows/i),office:getLic(/Office|Microsoft 365/i)};
 detail('Windows Activation',listValues(out.licenses.windows,function(x){return x.product_name+' | '+x.status_text+' | Key '+x.partial_product_key+' | '+x.description;}).join(' ; '));
 detail('Office Activation',listValues(out.licenses.office,function(x){return x.product_name+' | '+x.status_text+' | Key '+x.partial_product_key+' | '+x.description;}).join(' ; '));
 stage('10/10 - Phan mem da cai dat');
 out.software=await software();
 detail('Software Count',out.software.length);
 if(!out.memory.length)detail('CANH BAO RAM','WMI khong tra ve thanh RAM nao; kiem tra WMI/BIOS.');
 if(!out.storage.length)detail('CANH BAO STORAGE','Khong tim thay phan vung co DriveType=3 va dung luong > 0.');
 if(!out.network.length)detail('CANH BAO NETWORK','Khong tim thay card mang co IPEnabled=True va co IP/Gateway/MAC.');
 if(!out.software.length)detail('CANH BAO SOFTWARE','Registry khong tra ve phan mem da cai dat.');
 out.software.forEach(function(x){detail('Software',x.name+' | '+(x.version||'')+' | '+(x.publisher||'')+' | Install '+(x.install_date||''));});out.audit={collector:'MSTAR PC Audit Tool Node.js',collector_version:'2.1.0-win7',runtime:process.version,architecture:process.arch,collected_at:new Date().toISOString(),software_count:out.software.length,notes:'Thu thap bang WMIC/WMI va Registry. Truong khong duoc Windows/hardware cung cap duoc de null. Secure Boot khong duoc ho tro chuan tren Windows 7.'};return out;
}
function reqJson(url,method,payload){return new Promise(function(resolve,reject){var u;try{u=new URL(url);}catch(e){reject(new Error('URL API khong hop le'));return;}if(u.protocol!=='https:'){reject(new Error('Chi chap nhan HTTPS'));return;}var body=payload===undefined?null:Buffer.from(JSON.stringify(payload),'utf8'),headers={Accept:'application/json'};if(body){headers['Content-Type']='application/json; charset=utf-8';headers['Content-Length']=body.length;}var req=https.request({hostname:u.hostname,port:u.port||443,path:u.pathname+u.search,method:method,headers:headers},function(res){var raw='';res.setEncoding('utf8');res.on('data',function(c){raw+=c;});res.on('end',function(){var data={};try{data=raw?JSON.parse(raw):{};}catch(e){reject(new Error('May chu tra ve JSON khong hop le (HTTP '+res.statusCode+')'));return;}if(res.statusCode<200||res.statusCode>=300){var detail=data.message||('HTTP '+res.statusCode);if(data.errors)detail=Object.keys(data.errors).map(function(k){return k+': '+[].concat(data.errors[k]).join('; ');}).join(' | ');reject(new Error(detail));return;}resolve(data);});});req.setTimeout(120000,function(){req.destroy(new Error('Het thoi gian cho ket noi Audit API'));});req.on('error',reject);if(body)req.write(body);req.end();});}
function saveJson(payload){var dir=path.join(__dirname,'output');if(!fs.existsSync(dir))fs.mkdirSync(dir);var name=String(payload.data.computer.computer_name||os.hostname()).replace(/[^A-Za-z0-9_-]/g,'_'),stamp=new Date().toISOString().replace(/[:.]/g,'-'),file=path.join(dir,'pc-audit-'+name+'-'+stamp+'.json');fs.writeFileSync(file,JSON.stringify(payload,null,2),'utf8');return file;}

var SCHEDULE_TASK_NAME='MSTAR_PC_AUDIT_SCHEDULE';
var SCHEDULE_CONFIG=path.join(__dirname,'pc-audit-schedule.json');
function logScheduled(message){var dir=path.join(__dirname,'output');if(!fs.existsSync(dir))fs.mkdirSync(dir);fs.appendFileSync(path.join(dir,'scheduled.log'),'['+new Date().toISOString()+'] '+message+'\r\n','utf8');}
async function scheduledRun(){
 var profile;
 try{profile=JSON.parse(fs.readFileSync(SCHEDULE_CONFIG,'utf8'));}catch(e){throw new Error('Chua co cau hinh lich. Chay PC_Audit.bat va chon cai dat lich truoc.');}
 var conf=await reqJson(CONFIG_ENDPOINT,'GET');if(conf.enabled!==true)throw new Error(conf.disabled_message||'He thong Audit dang tam ngung.');
 var base=String(conf.api_base_url||'').replace(/\/+$/,'');if(!/^https:\/\//i.test(base))throw new Error('API Base URL khong hop le.');
 var code=String(profile.code||'').toUpperCase();if(!code)throw new Error('Thieu Audit Code trong cau hinh lich.');
 var v=await reqJson(base+'/pc-audit/validate-code','POST',{code:code});if(v.ok!==true)throw new Error(v.message||'Audit Code khong hop le');
 var data=await collect(),payload={code:code,employee_name:profile.employee_name,department:profile.department||null,employee_mobile:profile.employee_mobile||null,employee_email:profile.employee_email||null,data:data};
 var jsonFile=saveJson(payload);logScheduled('Collected '+data.computer.computer_name+'; JSON='+jsonFile);
 var resp=await reqJson(base+'/pc-audit/submit','POST',payload);if(resp.ok!==true)throw new Error(resp.message||'Server tu choi du lieu');
 logScheduled('SUCCESS computer='+data.computer.computer_name+' score='+resp.audit_score+' status='+resp.audit_status);
 console.log('Scheduled PC Audit OK: '+data.computer.computer_name+' / '+resp.audit_status+' / '+resp.audit_score);
}
async function setupSchedule(){
 var rl=readline.createInterface({input:process.stdin,output:process.stdout});
 try{
  console.log('=== CAU HINH PC AUDIT TU DONG ===');
  var code=(EMBEDDED_AUDIT_CODE==='__MSTAR_AUDIT_CODE__'?(await ask(rl,'Audit Code: ')):EMBEDDED_AUDIT_CODE).toUpperCase();
  if(!code)throw new Error('Audit Code khong duoc de trong.');
  var conf=await reqJson(CONFIG_ENDPOINT,'GET');if(conf.enabled!==true)throw new Error(conf.disabled_message||'He thong Audit dang tam ngung.');
  var base=String(conf.api_base_url||'').replace(/\/+$/,'');
  var v=await reqJson(base+'/pc-audit/validate-code','POST',{code:code});if(v.ok!==true)throw new Error(v.message||'Audit Code khong hop le');
  console.log('Khach hang: '+v.data.customer.name+' | Chi nhanh: '+v.data.branch.name);
  var def=os.userInfo().username||process.env.USERNAME||'Unknown';
  var employee=(await ask(rl,'Ho ten nguoi dung (Enter = '+def+'): '))||def;
  var department=(await ask(rl,'Phong ban (Enter = bo qua): '))||null;
  var mobile=(await ask(rl,'Di dong (Enter = bo qua): '))||null;
  var email=(await ask(rl,'Email (Enter = bo qua): '))||null;
  console.log('Tan suat: 1 = Moi ngay, 2 = Moi gio');
  var freq=await ask(rl,'Chon tan suat (1/2): ');
  var args=['/Create','/TN',SCHEDULE_TASK_NAME,'/TR','"'+path.join(__dirname,'PC_Audit_Scheduled.bat')+'"','/RU','SYSTEM','/RL','HIGHEST','/F'];
  if(freq==='2'){
    var hours=Number(await ask(rl,'Chay lap lai moi bao nhieu gio (1-23): '));
    if(!isFinite(hours)||hours<1||hours>23||Math.floor(hours)!==hours)throw new Error('Khoang gio phai tu 1 den 23.');
    args.push('/SC','HOURLY','/MO',String(hours));
  }else{
    var time=await ask(rl,'Gio chay moi ngay HH:MM (vi du 02:00): ');
    if(!/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(time))throw new Error('Gio khong hop le, dung HH:MM.');
    args.push('/SC','DAILY','/ST',time);
  }
  var profile={code:code,employee_name:employee,department:department,employee_mobile:mobile,employee_email:email,created_at:new Date().toISOString(),customer:v.data.customer.name,branch:v.data.branch.name};
  fs.writeFileSync(SCHEDULE_CONFIG,JSON.stringify(profile,null,2),'utf8');
  var nodePath=process.execPath;
  var bat='@echo off\r\ncd /d "'+__dirname+'"\r\nif not exist "output" mkdir "output"\r\n"'+nodePath+'" "'+path.join(__dirname,'PC_Audit.js')+'" --scheduled >> "'+path.join(__dirname,'output','scheduled.log')+'" 2>&1\r\n';
  fs.writeFileSync(path.join(__dirname,'PC_Audit_Scheduled.bat'),bat,'utf8');
  var task=await exec('schtasks.exe',args,30000);
  if(!task.ok){try{fs.unlinkSync(SCHEDULE_CONFIG);}catch(e){}throw new Error('Khong tao duoc Scheduled Task. Hay chay bang Run as Administrator. '+(task.err||task.out||task.error));}
  console.log('Da cai dat lich PC Audit tu dong.');
  console.log('Khach hang: '+profile.customer+' | Chi nhanh: '+profile.branch);
  console.log('Log: '+path.join(__dirname,'output','scheduled.log'));
  console.log('Chay thu ngay bay gio? (Y/N)');
  if(/^y$/i.test(await ask(rl,'> '))){rl.close();await scheduledRun();return;}
 }finally{try{rl.close();}catch(e){}}
}
async function removeSchedule(){
 var r=await exec('schtasks.exe',['/Delete','/TN',SCHEDULE_TASK_NAME,'/F'],20000);
 if(!r.ok)throw new Error('Khong go duoc lich (co the chua cai dat): '+(r.err||r.out||r.error));
 try{fs.unlinkSync(SCHEDULE_CONFIG);}catch(e){}
 console.log('Da go lich PC Audit tu dong.');
}


var SERVICE_NAME='MSTAR_PCAudit';
var SERVICE_CONFIG=SCHEDULE_CONFIG;
function sleep(ms){return new Promise(function(resolve){setTimeout(resolve,ms);});}
async function serviceLoop(){
 logScheduled('SERVICE STARTED; interval=6 hours');
 while(true){
  try{await scheduledRun();}
  catch(e){try{logScheduled('SERVICE ERROR '+(e&&e.message?e.message:String(e)));}catch(ignore){}}
  await sleep(6*60*60*1000);
 }
}
async function setupService(){
 var rl=readline.createInterface({input:process.stdin,output:process.stdout});
 try{
  console.log('=== CAU HINH WINDOWS SERVICE PC AUDIT ===');
  var code=(EMBEDDED_AUDIT_CODE==='__MSTAR_AUDIT_CODE__'?(await ask(rl,'Audit Code: ')):EMBEDDED_AUDIT_CODE).toUpperCase();
  if(!code)throw new Error('Audit Code khong duoc de trong.');
  var conf=await reqJson(CONFIG_ENDPOINT,'GET');if(conf.enabled!==true)throw new Error(conf.disabled_message||'He thong Audit dang tam ngung.');
  var base=String(conf.api_base_url||'').replace(/\/+$/,'');
  var v=await reqJson(base+'/pc-audit/validate-code','POST',{code:code});if(v.ok!==true)throw new Error(v.message||'Audit Code khong hop le');
  console.log('Khach hang: '+v.data.customer.name+' | Chi nhanh: '+v.data.branch.name);
  var def=os.userInfo().username||process.env.USERNAME||'Unknown';
  var employee=(await ask(rl,'Ho ten nguoi dung (Enter = '+def+'): '))||def;
  var department=(await ask(rl,'Phong ban (Enter = bo qua): '))||null;
  var mobile=(await ask(rl,'Di dong (Enter = bo qua): '))||null;
  var email=(await ask(rl,'Email (Enter = bo qua): '))||null;
  var profile={code:code,employee_name:employee,department:department,employee_mobile:mobile,employee_email:email,created_at:new Date().toISOString(),customer:v.data.customer.name,branch:v.data.branch.name};
  fs.writeFileSync(SERVICE_CONFIG,JSON.stringify(profile,null,2),'utf8');
  console.log('Da luu cau hinh Service. Service se thu thap va cap nhat inventory moi 6 gio.');
  console.log('Luu y: can chay Audit lan dau de tao du lieu day du truoc khi cai Service.');
 }finally{try{rl.close();}catch(e){}}
}
async function removeService(){
 var nssm=path.join(__dirname,'nssm.exe');
 if(!fs.existsSync(nssm))throw new Error('Khong tim thay nssm.exe trong thu muc cong cu.');
 var stop=await exec(nssm,['stop',SERVICE_NAME],20000);
 await sleep(1500);
 var rem=await exec(nssm,['remove',SERVICE_NAME,'confirm'],20000);
 if(!rem.ok)throw new Error('Khong go duoc Service: '+(rem.err||rem.out||rem.error));
 try{fs.unlinkSync(SERVICE_CONFIG);}catch(e){}
 console.log('Da go Windows Service '+SERVICE_NAME+'.');
}
async function serviceStatus(){
 var r=await exec('sc.exe',['query',SERVICE_NAME],15000);
 console.log(r.out||r.err||r.error||'Khong co thong tin Service.');
}

async function main(){
 if(process.argv.indexOf('--service-loop')>=0){serviceLoop().catch(function(e){try{logScheduled('FATAL '+(e&&e.message?e.message:String(e)));}catch(ignore){}process.exitCode=1;});return;}
 if(process.argv.indexOf('--setup-service')>=0){await setupService();return;}
 if(process.argv.indexOf('--remove-service')>=0){await removeService();return;}
 if(process.argv.indexOf('--service-status')>=0){await serviceStatus();return;}
 if(process.argv.indexOf('--scheduled')>=0){try{await scheduledRun();}catch(e){try{logScheduled('ERROR '+(e&&e.message?e.message:String(e)));}catch(ignore){}console.error('[SCHEDULED AUDIT ERROR] '+(e&&e.message?e.message:String(e)));process.exitCode=1;}return;}
 if(process.argv.indexOf('--setup-schedule')>=0){await setupSchedule();return;}
 if(process.argv.indexOf('--remove-schedule')>=0){await removeSchedule();return;}
 var rl=readline.createInterface({input:process.stdin,output:process.stdout});try{console.log('==============================================');console.log(' MSTAR PC AUDIT TOOL - WINDOWS 7 / NODE.JS');console.log(' Runtime: '+process.version+' / '+process.arch);console.log('==============================================');console.log('Dang lay cau hinh may chu...');var conf=await reqJson(CONFIG_ENDPOINT,'GET');if(conf.enabled!==true)throw new Error(conf.disabled_message||'He thong Audit dang tam ngung.');var base=String(conf.api_base_url||'').replace(/\/+$/,'');if(!/^https:\/\//i.test(base))throw new Error('API Base URL khong hop le.');var code=EMBEDDED_AUDIT_CODE==='__MSTAR_AUDIT_CODE__'?(await ask(rl,'Audit Code: ')).toUpperCase():EMBEDDED_AUDIT_CODE.toUpperCase();if(!code)throw new Error('Audit Code khong duoc de trong.');console.log('Dang xac thuc Audit Code...');var v=await reqJson(base+'/pc-audit/validate-code','POST',{code:code});if(v.ok!==true)throw new Error(v.message||'Audit Code khong hop le');var customer=v.data.customer.name,branch=v.data.branch.name;console.log('Khach hang: '+customer);console.log('Chi nhanh : '+branch);var def=os.userInfo().username||process.env.USERNAME||'Unknown',employee=(await ask(rl,'Ho ten nguoi su dung (Enter = '+def+'): '))||def;if(!employee)throw new Error('Khong xac dinh duoc nguoi su dung');var department=(await ask(rl,'Phong ban (Enter = bo qua): '))||null,mobile=(await ask(rl,'Di dong (Enter = bo qua): '))||null,email=(await ask(rl,'Email (Enter = bo qua): '))||null;if(!/^y$/i.test(await ask(rl,'Tiep tuc Audit may nay? (Y/N): '))){console.log('Da huy.');return;}console.log('\\n==============================================');console.log(' BAT DAU THU THAP - GIA TRI THUC TE SE HIEN THI');console.log('==============================================');var data=await collect(),payload={code:code,employee_name:employee,department:department,employee_mobile:mobile,employee_email:email,data:data},jsonFile=saveJson(payload);console.log('Da luu JSON: '+jsonFile);console.log('So phan mem thu duoc: '+data.software.length);console.log('Dang gui du lieu len Server...');var resp=await reqJson(base+'/pc-audit/submit','POST',payload);if(resp.ok!==true)throw new Error(resp.message||'Server tu choi du lieu');try{var mail=await reqJson(base+'/pc-audit/send-mail','POST',{code:code,subject:'PC Audit - '+data.computer.computer_name+' - '+customer+' - '+branch,body:JSON.stringify(payload,null,2)});console.log(mail.ok?'Email bao cao da gui.':'Audit da luu, email chua gui.');}catch(e){console.log('Audit da luu, email chua gui: '+e.message);}console.log('\n==============================================');console.log(' AUDIT HOAN TAT - DU LIEU DA DUOC LUU');console.log(' Khach hang: '+customer);console.log(' Chi nhanh : '+branch);console.log(' Nguoi dung: '+employee);console.log(' Diem Audit: '+String(resp.audit_score));console.log(' Trang thai: '+String(resp.audit_status));console.log('==============================================');}finally{rl.close();}}
main().catch(function(e){console.error('\\n[PC AUDIT ERROR] '+(e&&e.message?e.message:String(e)));process.exitCode=1;});
