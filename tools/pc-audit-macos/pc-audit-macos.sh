#!/bin/bash
# MSTAR PC Audit macOS - system LaunchDaemon installer
set -Eeuo pipefail
LABEL="vn.review360.pc-audit"
DOMAIN="system/$LABEL"
ROOT="/Library/Application Support/MSTAR/PCAudit"
BIN="$ROOT/agent.py"
CONFIG="$ROOT/config.json"
PLIST="/Library/LaunchDaemons/$LABEL.plist"
LOGDIR="$ROOT/logs"
API_ROOT="https://kpi.review360.id.vn/api"
log(){ printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
die(){ log "ERROR: $*"; exit 1; }
need_root(){ [ "$(id -u)" -eq 0 ] || die "Chạy bằng sudo: sudo $0 --install"; }
safe_dirs(){ install -d -o root -g wheel -m 700 "$ROOT" "$LOGDIR"; }

write_agent(){
cat > "$BIN" <<'PYAGENT'
#!/usr/bin/python3
import datetime,json,os,platform,subprocess,sys,time,urllib.error,urllib.parse,urllib.request,glob,plistlib
ROOT="/Library/Application Support/MSTAR/PCAudit"; CFG=ROOT+"/config.json"; LOGDIR=ROOT+"/logs"; LOG=LOGDIR+"/agent.log"
def log(s):
 os.makedirs(LOGDIR,mode=0o700,exist_ok=True)
 try:
  if os.path.exists(LOG) and os.path.getsize(LOG)>2097152:
   for i in range(4,0,-1):
    a=LOG+"."+str(i); b=LOG+"."+str(i+1)
    if os.path.exists(a):
     if i==4: os.remove(a)
     else: os.replace(a,b)
   os.replace(LOG,LOG+".1")
  with open(LOG,"a",encoding="utf-8") as f: os.chmod(LOG,0o600); f.write("[%s] %s\n"%(datetime.datetime.now().astimezone().isoformat(timespec="seconds"),str(s)[:2000]))
 except Exception: pass
def cmd(a,t=30):
 try:return subprocess.run(a,stdout=subprocess.PIPE,stderr=subprocess.DEVNULL,text=True,timeout=t).stdout.strip()
 except Exception:return ""
def config():
 if os.path.islink(CFG): raise RuntimeError("Refusing symlink config")
 st=os.stat(CFG)
 if st.st_uid!=0 or st.st_mode&0o077: raise RuntimeError("Config must be root-owned mode 0600")
 with open(CFG,encoding="utf-8") as f:p=json.load(f)
 if not isinstance(p,dict) or not all(isinstance(p.get(k),str) and p[k].strip() for k in ("code","employee_name","api_root","computer_id")): raise RuntimeError("Invalid config schema")
 if not p["api_root"].startswith("https://"): raise RuntimeError("API must use HTTPS")
 return p
def api(url,method="GET",data=None):
 req=urllib.request.Request(url,data=json.dumps(data).encode() if data is not None else None,method=method,headers={"Accept":"application/json","Content-Type":"application/json","User-Agent":"MSTAR-PC-Audit-macOS/2.0"})
 try:
  with urllib.request.urlopen(req,timeout=45) as r:
   raw=r.read().decode("utf-8","replace")
   try:return json.loads(raw)
   except Exception:raise RuntimeError("Invalid JSON response")
 except urllib.error.HTTPError as e:
  raw=e.read(2000).decode("utf-8","replace"); log("HTTP %s %s: %s"%(e.code,urllib.parse.urlparse(url).path,raw))
  if e.code==422: raise RuntimeError("HTTP 422 validation error; inspect agent.log")
  if e.code in (401,403): raise RuntimeError("HTTP %s authorization error"%e.code)
  if e.code==429: raise RuntimeError("HTTP 429 rate limited")
  if e.code>=500: raise RuntimeError("HTTP %s server error"%e.code)
  raise RuntimeError("HTTP %s"%e.code)
 except urllib.error.URLError as e: raise RuntimeError("Network/DNS/TLS error: "+str(e.reason))
def collect(p):
 sp={}
 try:sp=json.loads(cmd(["/usr/sbin/system_profiler","SPHardwareDataType","SPSoftwareDataType","-json"],90) or "{}")
 except Exception:pass
 hw=(sp.get("SPHardwareDataType") or [{}])[0]; sw=(sp.get("SPSoftwareDataType") or [{}])[0]
 try:mem=round(int(cmd(["/usr/sbin/sysctl","-n","hw.memsize"]))/1073741824,2)
 except Exception:mem=None
 disks=[]
 for line in cmd(["/bin/df","-k","/"]).splitlines()[1:]:
  a=line.split()
  if len(a)>=6:
   try:disks.append({"drive":"/","total_gb":round(int(a[1])/1048576,2),"used_gb":round(int(a[2])/1048576,2)})
   except Exception:pass
 fv=cmd(["/usr/bin/fdesetup","status"]); fw=cmd(["/usr/libexec/ApplicationFirewall/socketfilterfw","--getglobalstate"])
 # Battery: use system_profiler power data; tolerate desktops/no battery.
 battery=[]
 try:
  power=json.loads(cmd(["/usr/sbin/system_profiler","SPPowerDataType","-json"],60) or "{}")
  for item in power.get("SPPowerDataType",[]):
   if isinstance(item,dict):
    name=item.get("device_name") or item.get("name") or item.get("sppower_battery_model")
    status=item.get("battery_health") or item.get("condition") or item.get("state")
    charge=item.get("charge") or item.get("state_of_charge") or item.get("sppower_battery_charge_level")
    if name or status or charge is not None:
     try: charge=int(str(charge).replace("%","")) if charge is not None else None
     except Exception: charge=None
     battery.append({"name":name or "Built-in battery","status":status,"charge_percent":charge})
 except Exception: pass
 # Network inventory without transmitting MAC/IP; network presence is collected only as adapter type/name.
 network=[]
 for iface in cmd(["/sbin/ifconfig","-l"]).split():
  detail=cmd(["/sbin/ifconfig",iface])
  if "status: active" in detail:
   network.append({"type":"Wi-Fi" if iface.startswith("en") else "Network","name":iface,"description":"Active network interface","ipv4":None,"gateway":None,"mac":None,"dns":None,"dhcp":None,"connection_status":"Connected","link_speed":None})
 apps=[]
 if p.get("collect_app_inventory") is True:
  seen=set()
  for pat in ("/Applications/*.app/Contents/Info.plist","/Applications/*/*.app/Contents/Info.plist"):
   for path in glob.glob(pat):
    try:
     with open(path,"rb") as f:x=plistlib.load(f)
     n=x.get("CFBundleDisplayName") or x.get("CFBundleName")
     if n and n not in seen: seen.add(n); apps.append({"name":n,"version":x.get("CFBundleShortVersionString")})
    except Exception:pass
 osver=sw.get("os_version") or sw.get("system_version") or platform.mac_ver()[0]
 return {"computer":{"computer_name":p["computer_id"],"manufacturer":"Apple","model":hw.get("machine_model") or hw.get("model_identifier") or "Mac","serial_number":None,"username":None,"domain":None},
 "mainboard":{"manufacturer":"Apple","product":hw.get("machine_model") or hw.get("model_identifier"),"serial_number":None},"bios":{"version":hw.get("boot_rom_version") or hw.get("system_firmware_version")},
 "cpu":[{"name":hw.get("chip") or hw.get("cpu_type") or platform.processor()}],"memory":[{"capacity_gb":mem,"capacity":str(mem)+" GB" if mem is not None else None}],"storage":disks,"monitors":[],"gpu":[],"battery":battery,
 "windows":{"caption":"macOS "+str(osver),"version":platform.mac_ver()[0],"build":sw.get("kernel_version"),"architecture":platform.machine()},"windows_update":None,"network":network,
 "security":{"filevault":{"enabled":True if "FileVault is On" in fv else (False if "FileVault is Off" in fv else None),"raw_status":fv},"firewall":[{"profile":"macOS Application Firewall","enabled":True if "enabled" in fw.lower() else (False if "disabled" in fw.lower() else None)}] if fw else [],"secure_boot":None,"antivirus":[],"bitlocker":[],"tpm":None},
 "licenses":[],"software":apps,"other":{"platform":"macOS","macos_version":platform.mac_ver()[0],"architecture":platform.machine(),"collected_at":datetime.datetime.now(datetime.timezone.utc).isoformat()}}
def once():
 p=config(); c=api(p["api_root"]+"/pc-audit/config")
 if c.get("enabled") is not True: raise RuntimeError(c.get("disabled_message") or "PC Audit disabled")
 d=collect(p); payload={"code":p["code"],"employee_name":p["employee_name"],"department":p.get("department"),"employee_email":p.get("employee_email"),"data":d}
 r=api(p["api_root"]+"/pc-audit/submit","POST",payload)
 if r.get("ok") is not True: raise RuntimeError(r.get("message") or "Submit failed")
 log("Audit submitted score=%s status=%s"%(r.get("audit_score"),r.get("audit_status")))
def loop():
 while True:
  try:
   p=config(); c=api(p["api_root"]+"/pc-audit/agent-config?code="+urllib.parse.quote(p["code"])); s=c.get("schedule") or {}; state=ROOT+"/last-success"
   try:
    with open(state) as f:last=float(f.read())
   except Exception:last=0
   interval=max(15,min(10080,int(s.get("interval_minutes") or 360)))*60
   if c.get("enabled") and s.get("enabled",True) and ((last==0 and s.get("run_on_start",True)) or time.time()-last>=interval):
    once()
    with open(state,"w") as f:f.write(str(time.time()))
    os.chmod(state,0o600)
  except Exception as e:log("ERROR: "+str(e))
  time.sleep(300)
if __name__=="__main__":
 try:
  if "--run-once" in sys.argv:once()
  elif "--service" in sys.argv:loop()
  else:raise RuntimeError("Use --service or --run-once")
 except Exception as e:log("FATAL: "+str(e)); print(str(e),file=sys.stderr); sys.exit(1)
PYAGENT
chmod 700 "$BIN"; chown root:wheel "$BIN"
}
write_config(){
 local code name dept email consent computer_id
 read -r -p "Audit Code: " code; [ -n "$code" ] || die "Audit Code trống"
 read -r -p "Họ tên người sử dụng: " name; [ -n "$name" ] || die "Họ tên trống"
 read -r -p "Phòng ban (Enter bỏ qua): " dept
 read -r -p "Email (Enter bỏ qua): " email
 read -r -p "Đồng ý thu thập danh sách ứng dụng? (y/N): " consent
 [[ "$consent" =~ ^[Yy]$ ]] && consent_json=true || consent_json=false
 computer_id="macOS-$(/usr/bin/uuidgen | tr '[:upper:]' '[:lower:]')"
 /usr/bin/python3 - "$CONFIG" "$code" "$name" "$dept" "$email" "$API_ROOT" "$computer_id" "$consent_json" <<'PYCFG'
import json,os,sys,tempfile
path,code,name,dept,email,api,cid,consent=sys.argv[1:]
obj={"code":code.strip().upper(),"employee_name":name.strip(),"department":dept.strip() or None,"employee_email":email.strip() or None,"api_root":api.rstrip("/"),"computer_id":cid,"collect_app_inventory":consent=="true","schema_version":2}
fd,tmp=tempfile.mkstemp(dir=os.path.dirname(path),prefix=".config-")
try:
 with os.fdopen(fd,"w",encoding="utf-8") as f: json.dump(obj,f,ensure_ascii=False); f.flush(); os.fsync(f.fileno())
 os.chmod(tmp,0o600); os.replace(tmp,path); os.chown(path,0,0); os.chmod(path,0o600)
finally:
 try:os.unlink(tmp)
 except FileNotFoundError:pass
PYCFG
}
write_plist(){
cat > "$PLIST" <<PLISTXML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
<key>Label</key><string>$LABEL</string>
<key>ProgramArguments</key><array><string>/usr/bin/python3</string><string>$BIN</string><string>--service</string></array>
<key>RunAtLoad</key><true/><key>KeepAlive</key><true/><key>ProcessType</key><string>Background</string>
<key>WorkingDirectory</key><string>$ROOT</string>
<key>StandardOutPath</key><string>$LOGDIR/launchd-out.log</string>
<key>StandardErrorPath</key><string>$LOGDIR/launchd-error.log</string>
</dict></plist>
PLISTXML
chown root:wheel "$PLIST"; chmod 644 "$PLIST"; plutil -lint "$PLIST" >/dev/null || die "Plist không hợp lệ"
}
install_service(){
 need_root; safe_dirs
 command -v /usr/bin/python3 >/dev/null 2>&1 || die "Thiếu Python hệ thống; bản này chưa phải executable tự chứa"
 write_agent
 if [ ! -f "$CONFIG" ]; then write_config; else chown root:wheel "$CONFIG"; chmod 600 "$CONFIG"; fi
 write_plist
 launchctl bootout "$DOMAIN" >/dev/null 2>&1 || true
 launchctl bootstrap system "$PLIST" || die "launchctl bootstrap thất bại"
 launchctl kickstart -k "$DOMAIN" || die "Không khởi động được Service"
 log "Đã cài LaunchDaemon $LABEL"; launchctl print "$DOMAIN" | head -n 25
}
status_service(){ launchctl print "$DOMAIN" || { echo "Service chưa cài; chạy sudo $0 --install"; exit 1; } }
uninstall_service(){ need_root; launchctl bootout "$DOMAIN" >/dev/null 2>&1 || true; rm -f "$PLIST"; echo "Đã gỡ Service; giữ cấu hình/log tại $ROOT"; }
MODE="$1"
case "$MODE" in
 --install) install_service ;;
 --update) need_root; [ -f "$CONFIG" ] || die "Chưa cài"; install_service ;;
 --status) status_service ;;
 --run-once) [ -x "$BIN" ] || die "Chưa cài"; /usr/bin/python3 "$BIN" --run-once ;;
 --reconfigure) need_root; safe_dirs; write_config; chown root:wheel "$CONFIG"; chmod 600 "$CONFIG"; launchctl kickstart -k "$DOMAIN" >/dev/null 2>&1 || true; log "Đã cập nhật cấu hình" ;;
 --uninstall) uninstall_service ;;
 *) echo "Usage: sudo $0 {--install|--update|--uninstall|--status|--run-once|--reconfigure}"; exit 2 ;;
esac
