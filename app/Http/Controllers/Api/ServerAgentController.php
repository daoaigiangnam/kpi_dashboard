<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\CommandAuditEvent;
use App\Models\MonitoredServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ServerAgentController extends Controller {
 private function server(Request $r):MonitoredServer{return $r->attributes->get('monitored_server');}
 public function heartbeat(Request $r){
  $d=$r->validate(['agent_version'=>'nullable|string|max:40','hostname'=>'nullable|string|max:190','os_info'=>'nullable|array','metadata'=>'nullable|array']);$s=$this->server($r);
  $s->update(['last_seen_at'=>now(),'agent_version'=>$d['agent_version']??$s->agent_version,'hostname'=>$d['hostname']??$s->hostname,'os_info'=>$d['os_info']??$s->os_info,'metadata'=>$d['metadata']??$s->metadata,'status'=>'active']);
  return response()->json(['ok'=>true,'server_code'=>$s->server_code,'server_time'=>now()->toIso8601String()]);
 }
 public function config(Request $r){$s=$this->server($r);return response()->json(['config_version'=>1,'modules'=>['command_audit'=>['enabled'=>(bool)$s->audit_enabled],'resource_monitor'=>['enabled'=>false]],'poll_interval_seconds'=>10]);}
 public function commands(Request $r){$s=$this->server($r);$rows=$s->commands()->where('status','queued')->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->orderBy('id')->limit(10)->get();foreach($rows as $c)$c->update(['status'=>'delivered','delivered_at'=>now()]);return response()->json(['commands'=>$rows->map(fn($c)=>['command_id'=>$c->command_id,'type'=>$c->type,'payload'=>$c->payload])->values()]);}
 public function acknowledge(Request $r){
  $d=$r->validate(['command_id'=>'required|uuid','status'=>'required|in:executing,completed,failed','result'=>'nullable|array','error'=>'nullable|string|max:2000']);
  $c=$this->server($r)->commands()->where('command_id',$d['command_id'])->firstOrFail();$c->update(['status'=>$d['status'],'result'=>$d['result']??null,'error'=>$d['error']??null,'completed_at'=>in_array($d['status'],['completed','failed'],true)?now():null]);return response()->json(['ok'=>true]);
 }
 public function auditEvents(Request $r){
  $d=$r->validate(['session_id'=>'required|uuid','events'=>'required|array|max:500','events.*.event_key'=>'required|string|max:190','events.*.event_time'=>'required|date','events.*.username'=>'nullable|string|max:190','events.*.uid'=>'nullable|integer','events.*.auid'=>'nullable|integer','events.*.pid'=>'nullable|integer','events.*.ppid'=>'nullable|integer','events.*.executable'=>'nullable|string|max:1024','events.*.arguments'=>'nullable|array','events.*.audit_serial'=>'nullable|string|max:100','events.*.source_ip'=>'nullable|ip','events.*.raw_event'=>'nullable|array']);
  $s=$this->server($r);$session=$s->auditSessions()->where('session_id',$d['session_id'])->where('status','running')->firstOrFail();$n=0;
  DB::transaction(function()use($d,$s,$session,&$n){foreach($d['events'] as $e){$row=['server_id'=>$s->id,'session_id'=>$session->session_id,'event_key'=>$e['event_key'],'event_time'=>$e['event_time'],'username'=>$e['username']??null,'uid'=>$e['uid']??null,'auid'=>$e['auid']??null,'pid'=>$e['pid']??null,'ppid'=>$e['ppid']??null,'executable'=>$e['executable']??null,'arguments'=>$e['arguments']??null,'audit_serial'=>$e['audit_serial']??null,'source_ip'=>$e['source_ip']??null,'raw_event'=>$e['raw_event']??null,'created_at'=>now()];$created=CommandAuditEvent::firstOrCreate(['server_id'=>$s->id,'event_key'=>$e['event_key']],$row);if($created->wasRecentlyCreated)$n++;}$session->increment('event_count',$n);});
  return response()->json(['ok'=>true,'accepted'=>$n]);
 }
 public function report(Request $r){
  $d=$r->validate(['session_id'=>'required|uuid','status'=>'required|in:completed,failed','event_count'=>'required|integer|min:0','report_sha256'=>'nullable|string|size:64','report_name'=>'nullable|string|max:190']);$s=$this->server($r);$session=$s->auditSessions()->where('session_id',$d['session_id'])->firstOrFail();
  $session->update(['status'=>$d['status'],'stopped_at'=>now(),'event_count'=>max($session->event_count,$d['event_count']),'metadata'=>array_merge($session->metadata??[],['report_sha256'=>$d['report_sha256']??null,'report_name'=>$d['report_name']??null])]);return response()->json(['ok'=>true]);
 }
}