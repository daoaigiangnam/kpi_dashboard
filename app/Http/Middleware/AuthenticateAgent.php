<?php
namespace App\Http\Middleware;
use App\Models\MonitoredServer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class AuthenticateAgent {
 public function handle(Request $request,Closure $next):Response {
  $id=(string)$request->header('X-Agent-ID'); $token=(string)$request->bearerToken();
  if($id===''||$token==='') return response()->json(['message'=>'Agent credentials required'],401);
  $server=MonitoredServer::where('agent_id',$id)->where('status','active')->first();
  if(!$server||!hash_equals($server->token_hash,hash('sha256',$token))) return response()->json(['message'=>'Invalid agent credentials'],401);
  $request->attributes->set('monitored_server',$server); $server->forceFill(['last_seen_at'=>now()])->save();
  return $next($request);
 }
}