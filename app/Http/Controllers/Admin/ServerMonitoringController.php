<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AgentCommand;
use App\Models\CommandAuditSession;
use App\Models\MonitoredServer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ServerMonitoringController extends Controller {
 public function index(){ $servers=MonitoredServer::withCount(['auditSessions','auditEvents'])->latest()->paginate(25);$stats=['total'=>MonitoredServer::count(),'online'=>MonitoredServer::where('last_seen_at','>=',now()->subMinutes(2))->count(),'audit_running'=>CommandAuditSession::where('status','running')->count()];return view('admin.server-monitoring.index',compact('servers','stats')); }
 public function create(){return view('admin.server-monitoring.create');}
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:150','hostname'=>'nullable|string|max:190','server_code'=>'required|string|max:80|alpha_dash|unique:monitored_servers,server_code']);$agentId=(string)Str::uuid();$token=Str::random(64);$s=MonitoredServer::create($d+['agent_id'=>$agentId,'token_hash'=>hash('sha256',$token),'status'=>'active','audit_enabled'=>false]);return redirect()->route('admin.server_monitoring.show',$s)->with('issued_agent_token',$token);}
 public function show(MonitoredServer $server){$server->load(['auditSessions'=>fn($q)=>$q->latest()->limit(20)]);$events=$server->auditEvents()->latest('event_time')->paginate(50);return view('admin.server-monitoring.show',compact('server','events'));}
 public function startAudit(Request $r,MonitoredServer $server){abort_unless($server->status==='active',422);abort_if($server->auditSessions()->whereIn('status',['running','queued'])->exists(),409);$sid=(string)Str::uuid();CommandAuditSession::create(['server_id'=>$server->id,'session_id'=>$sid,'status'=>'queued','started_by'=>$r->user()->id]);$this->queue($server,'AUDIT_START',['session_id'=>$sid],$r->user()->id);$server->update(['audit_enabled'=>true]);return back()->with('success','Audit start command queued. Session: '.$sid);}
 public function stopAudit(Request $r,MonitoredServer $server){$session=$server->auditSessions()->whereIn('status',['running','queued'])->latest()->firstOrFail();$this->queue($server,'AUDIT_STOP',['session_id'=>$session->session_id],$r->user()->id);return back()->with('success','Audit stop command queued.');}
 public function disable(Request $r,MonitoredServer $server){$server->update(['status'=>'disabled','audit_enabled'=>false]);return back()->with('success','Server disabled.');}
 private function queue(MonitoredServer $s,string $type,array $payload,int $uid):void{AgentCommand::create(['server_id'=>$s->id,'command_id'=>(string)Str::uuid(),'type'=>$type,'payload'=>$payload,'status'=>'queued','requested_by'=>$uid,'requested_at'=>now(),'expires_at'=>now()->addMinutes(15)]);}
}