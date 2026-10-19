#!/bin/bash
# MSTAR PC Audit Agent for macOS (Intel and Apple Silicon)
# First run installs a LaunchAgent for the current user; use --install-daemon for managed/system-wide deployment.
set -u
APP_NAME="MSTAR PCAudit"
BASE_DIR="${HOME}/Library/Application Support/MSTAR/PCAudit"
CONFIG_FILE="${BASE_DIR}/config.json"
LOG_DIR="${BASE_DIR}/logs"
AGENT_FILE="${BASE_DIR}/agent.py"
PLIST_LABEL="vn.review360.pc-audit"
PLIST_FILE="${HOME}/Library/LaunchAgents/${PLIST_LABEL}.plist"
API_ROOT="https://kpi.review360.id.vn/api"
mkdir -p "$BASE_DIR" "$LOG_DIR" "${HOME}/Library/LaunchAgents"
chmod 700 "$BASE_DIR"

log(){ printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "${LOG_DIR}/agent.log"; }
fail(){ log "ERROR: $*"; echo; echo "Cài đặt chưa hoàn tất. Xem log: ${LOG_DIR}/agent.log"; exit 1; }
command -v /usr/bin/python3 >/dev/null 2>&1 || fail "Không tìm thấy Python 3 hệ thống. Cần triển khai runtime đi kèm trong bản đóng gói."
command -v /usr/bin/curl >/dev/null 2>&1 || fail "Không tìm thấy curl hệ thống."
if [ ! -f "$CONFIG_FILE" ]; then
  echo "=============================================="
  echo " MSTAR PC AUDIT - macOS"
  echo " Máy: $(scutil --get ComputerName 2>/dev/null || hostname)"
  echo " Kiến trúc: $(uname -m)"
  echo "=============================================="
  read -r -p "Nhập Audit Code: " AUDIT_CODE
  [ -n "$AUDIT_CODE" ] || fail "Audit Code không được để trống."
  read -r -p "Họ tên người sử dụng: " EMPLOYEE_NAME
  [ -n "$EMPLOYEE_NAME" ] || EMPLOYEE_NAME="$(id -F 2>/dev/null || id -un)"
  read -r -p "Phòng ban (Enter để bỏ qua): " DEPARTMENT
  read -r -p "Email (Enter để bỏ qua): " EMPLOYEE_EMAIL
  /usr/bin/python3 - "$CONFIG_FILE" "$AUDIT_CODE" "$EMPLOYEE_NAME" "$DEPARTMENT" "$EMPLOYEE_EMAIL" "$API_ROOT" <<'PYCFG'
import json,sys,os
p,code,name,dept,email,api=sys.argv[1:]
with open(p,'w',encoding='utf-8') as f:
 json.dump({'code':code.strip().upper(),'employee_name':name.strip(),'department':dept.strip() or None,'employee_email':email.strip() or None,'api_root':api},f)
os.chmod(p,0o600)
PYCFG
fi

cat > "$AGENT_FILE" <<'PYAGENT'
#!/usr/bin/python3
import json, os, platform, subprocess, urllib.request, urllib.error, datetime, socket, plistlib, glob, time, sys
BASE=os.path.expanduser('~/Library/Application Support/MSTAR/PCAudit')
CFG=os.path.join(BASE,'config.json')
LOG=os.path.join(BASE,'logs','agent.log')
def log(msg):
 os.makedirs(os.path.dirname(LOG),exist_ok=True)
 with open(LOG,'a',encoding='utf-8') as f:f.write('[%s] %s\n'%(datetime.datetime.now().isoformat(timespec='seconds'),msg))
def cmd(args,timeout=30):
 try:return subprocess.run(args,stdout=subprocess.PIPE,stderr=subprocess.DEVNULL,text=True,timeout=timeout).stdout.strip()
 except Exception:return ''
def api(url,method='GET',data=None):
 raw=json.dumps(data).encode() if data is not None else None
 req=urllib.request.Request(url,data=raw,method=method,headers={'Accept':'application/json','Content-Type':'application/json'})
 with urllib.request.urlopen(req,timeout=60) as r:return json.loads(r.read().decode())
def collect():
 sp={}
 try:sp=json.loads(cmd(['/usr/sbin/system_profiler','SPHardwareDataType','SPSoftwareDataType','SPMemoryDataType','SPStorageDataType','SPDisplaysDataType','SPPowerDataType','SPNetworkDataType','SPApplicationsDataType','-json'],120) or '{}')
 except Exception:pass
 hw=(sp.get('SPHardwareDataType') or [{}])[0]; sw=(sp.get('SPSoftwareDataType') or [{}])[0]
 serial=hw.get('serial_number') or hw.get('serial_number_system')
 hostname=cmd(['/usr/sbin/scutil','--get','ComputerName']) or socket.gethostname()
 uname=cmd(['/usr/bin/id','-un']) or os.getenv('USER','')
 memory=cmd(['/usr/sbin/sysctl','-n','hw.memsize'])
 try:memgb=round(int(memory)/1073741824,2)
 except Exception:memgb=None
 disk=cmd(['/bin/df','-k','/'])
 disks=[]
 for line in disk.splitlines()[1:]:
  p=line.split()
  if len(p)>=6:
   try:disks.append({'volume':p[-1],'total_gb':round(int(p[1])/1048576,2),'used_gb':round(int(p[2])/1048576,2),'free_gb':round(int(p[3])/1048576,2)})
   except Exception:pass
 net=[]
 try:
  for iface in cmd(['/sbin/ifconfig','-l']).split():
   detail=cmd(['/sbin/ifconfig',iface])
   mac=''; ip=[]
   for line in detail.splitlines():
    line=line.strip()
    if line.startswith('ether '):mac=line.split()[1]
    if line.startswith('inet '):ip.append(line.split()[1])
   if mac or ip:net.append({'name':iface,'mac':mac or None,'ipv4':ip})
 except Exception:pass
 apps=[]
 for pattern in ['/Applications/*.app/Contents/Info.plist','/System/Applications/*.app/Contents/Info.plist','/Applications/*/*.app/Contents/Info.plist']:
  for p in glob.glob(pattern):
   try:
    with open(p,'rb') as f:pl=plistlib.load(f)
    apps.append({'name':pl.get('CFBundleDisplayName') or pl.get('CFBundleName') or os.path.basename(os.path.dirname(os.path.dirname(p))),'version':pl.get('CFBundleShortVersionString'),'publisher':pl.get('TeamIdentifier')})
   except Exception:pass
 filevault=cmd(['/usr/bin/fdesetup','status'])
 firewall=cmd(['/usr/libexec/ApplicationFirewall/socketfilterfw','--getglobalstate'])
 return {
  'computer':{'computer_name':hostname,'username':uname,'domain':None,'manufacturer':'Apple','model':hw.get('machine_model') or hw.get('model_identifier'),'serial_number':serial,'asset_tag':None},
  'mainboard':{'manufacturer':'Apple','product':hw.get('machine_model') or hw.get('model_identifier'),'serial_number':serial},
  'bios':{'version':hw.get('boot_rom_version') or hw.get('system_firmware_version'),'date':None},
  'cpu':[{'name':hw.get('chip') or hw.get('cpu_type') or platform.processor(),'cores':hw.get('number_processors'),'threads':None,'max_clock':None}],
  'memory':[{'capacity_gb':memgb,'capacity':str(memgb)+' GB' if memgb is not None else None,'speed':None,'slot':None,'manufacturer':None,'part_number':None,'serial_number':None}],
  'storage':disks,'monitors':sp.get('SPDisplaysDataType',[]),'gpu':sp.get('SPDisplaysDataType',[]),'battery':sp.get('SPPowerDataType',[]),
  'windows':{'caption':sw.get('os_version') or sw.get('system_version'),'version':platform.mac_ver()[0],'build':sw.get('kernel_version'),'architecture':platform.machine(),'last_boot':None,'uptime_hours':None},
  'windows_update':None,'network':net,
  'security':{'filevault':filevault,'firewall':firewall,'secure_boot':None,'antivirus':[],'bitlocker':[],'tpm':None},
  'licenses':[],'software':apps,'other':{'platform':'macOS','macos_version':platform.mac_ver()[0],'architecture':platform.machine(),'collected_at':datetime.datetime.now(datetime.timezone.utc).isoformat()}
 }
def run_once():
 with open(CFG,encoding='utf-8') as f:profile=json.load(f)
 conf=api(profile['api_root']+'/pc-audit/config')
 if conf.get('enabled') is not True:raise RuntimeError(conf.get('disabled_message') or 'PC Audit is disabled on server')
 data=collect()
 payload={'code':profile['code'],'employee_name':profile['employee_name'],'department':profile.get('department'),'employee_email':profile.get('employee_email'),'data':data}
 result=api(profile['api_root']+'/pc-audit/submit','POST',payload)
 if result.get('ok') is not True:raise RuntimeError(result.get('message') or 'Audit submit failed')
 log('Audit submitted; computer=%s score=%s status=%s'%(data['computer']['computer_name'],result.get('audit_score'),result.get('audit_status')))
def main():
 try:
  if '--once' in sys.argv:run_once();return
  while True:
   try:
    with open(CFG,encoding='utf-8') as f:p=json.load(f)
    c=api(p['api_root']+'/pc-audit/agent-config?code='+p['code'])
    if c.get('enabled') and c.get('schedule',{}).get('enabled',True):
     state=os.path.join(BASE,'last-success')
     try:last=float(open(state).read().strip())
     except Exception:last=0
     sch=c.get('schedule',{});interval=max(15,int(sch.get('interval_minutes') or 360))*60
     if (last==0 and sch.get('run_on_start',True)) or time.time()-last>=interval:
      run_once()
      open(state,'w').write(str(time.time()))
   except Exception as e:log('ERROR: '+str(e))
   time.sleep(300)
 except Exception as e:log('FATAL: '+str(e));raise
if __name__=='__main__':main()
PYAGENT
chmod 700 "$AGENT_FILE"

# Register a per-user LaunchAgent (no admin prompt required).
cat > "$PLIST_FILE" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>Label</key><string>$PLIST_LABEL</string>
<key>ProgramArguments</key><array><string>/usr/bin/python3</string><string>$AGENT_FILE</string></array>
<key>RunAtLoad</key><true/>
<key>KeepAlive</key><true/>
<key>StandardOutPath</key><string>$LOG_DIR/launchd-out.log</string>
<key>StandardErrorPath</key><string>$LOG_DIR/launchd-error.log</string>
</dict></plist>
PLIST
chmod 644 "$PLIST_FILE"
/bin/launchctl unload "$PLIST_FILE" >/dev/null 2>&1 || true
/bin/launchctl load "$PLIST_FILE" || fail "Không đăng ký được LaunchAgent."
log "Installed/loaded LaunchAgent $PLIST_LABEL for $(uname -m)"
echo
echo "Đã cài MSTAR PC Audit Agent cho macOS."
echo "Log: $LOG_DIR/agent.log"
echo "Agent sẽ tự lấy Schedule từ Backend."
