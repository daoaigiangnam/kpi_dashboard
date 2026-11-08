<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentCommand;
use App\Models\CommandAuditSession;
use App\Models\MonitoredServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServerMonitoringController extends Controller
{
    public function index()
    {
        $servers = MonitoredServer::withCount(['auditSessions', 'auditEvents'])
            ->latest()
            ->paginate(25);

        $stats = [
            'total' => MonitoredServer::count(),
            'online' => MonitoredServer::where('status', 'active')
                ->where('last_seen_at', '>=', now()->subMinutes(2))->count(),
            'audit_running' => CommandAuditSession::whereIn('status', ['running', 'stopping'])->count(),
        ];

        return view('admin.server-monitoring.index', compact('servers', 'stats'));
    }

    public function create()
    {
        return view('admin.server-monitoring.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'hostname' => ['nullable', 'string', 'max:190'],
            'server_code' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:monitored_servers,server_code'],
        ]);

        $token = Str::random(64);
        $server = MonitoredServer::create($data + [
            'agent_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'audit_enabled' => false,
        ]);

        return redirect()->route('admin.server_monitoring.show', $server)
            ->with('issued_agent_token', $token);
    }

    public function show(MonitoredServer $server)
    {
        $server->load(['auditSessions' => fn ($query) => $query->latest()->limit(20)]);
        $events = $server->auditEvents()->latest('event_time')->paginate(50);

        return view('admin.server-monitoring.show', compact('server', 'events'));
    }

    public function startAudit(Request $request, MonitoredServer $server)
    {
        DB::transaction(function () use ($request, $server) {
            // Serialize START/STOP operations per server before checking active sessions.
            $lockedServer = MonitoredServer::whereKey($server->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedServer->status === 'active', 422, 'Server is disabled.');

            $hasActiveSession = $lockedServer->auditSessions()
                ->whereIn('status', ['queued', 'running', 'stopping'])
                ->exists();

            abort_if(
                $hasActiveSession || $lockedServer->audit_enabled,
                409,
                'An audit session is active or its stop state is unconfirmed.'
            );

            $sessionId = (string) Str::uuid();
            CommandAuditSession::create([
                'server_id' => $lockedServer->id,
                'session_id' => $sessionId,
                'status' => 'queued',
                'started_by' => $request->user()->id,
            ]);

            $this->queue($lockedServer, 'AUDIT_START', ['session_id' => $sessionId], $request->user()->id);
        });

        return back()->with('success', 'Đã đưa yêu cầu START vào hàng đợi. Chờ Agent xác nhận mới chuyển sang Running.');
    }

    public function stopAudit(Request $request, MonitoredServer $server)
    {
        DB::transaction(function () use ($request, $server) {
            $lockedServer = MonitoredServer::whereKey($server->id)->lockForUpdate()->firstOrFail();

            $isAlreadyStopping = $lockedServer->auditSessions()
                ->where('status', 'stopping')
                ->exists();
            abort_if($isAlreadyStopping, 409, 'Audit session is already stopping.');

            $session = $lockedServer->auditSessions()
                ->whereIn('status', ['queued', 'running'])
                ->latest()
                ->lockForUpdate()
                ->first();

            // A failed STOP leaves audit_enabled=true; allow an operator to retry STOP.
            if (! $session && $lockedServer->audit_enabled) {
                $session = $lockedServer->auditSessions()
                    ->where('status', 'failed')
                    ->latest()
                    ->lockForUpdate()
                    ->first();
            }

            abort_if(! $session, 404, 'No active or recoverable audit session was found.');

            $session->forceFill(['status' => 'stopping'])->save();
            $this->queue($lockedServer, 'AUDIT_STOP', ['session_id' => $session->session_id], $request->user()->id);
        });

        return back()->with('success', 'Đã đưa yêu cầu STOP vào hàng đợi. Chờ Agent xác nhận kết thúc.');
    }

    public function disable(Request $request, MonitoredServer $server)
    {
        DB::transaction(function () use ($server) {
            $locked = MonitoredServer::whereKey($server->id)->lockForUpdate()->firstOrFail();
            // Disabling Central access does not stop a local auditd process.
            // Keep active/stopping sessions intact and never fabricate stopped_at.
            $mayStillBeCollecting = $locked->audit_enabled
                || $locked->auditSessions()->whereIn('status', ['running', 'stopping'])->exists()
                || $locked->commands()
                    ->where('type', 'AUDIT_START')
                    ->whereIn('status', ['delivered', 'executing', 'completed'])
                    ->exists();

            $locked->forceFill([
                'status' => 'disabled',
                'audit_enabled' => $mayStillBeCollecting,
            ])->save();

            $locked->commands()->whereIn('status', ['queued', 'delivered', 'executing'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            // A queued session with no confirmed START is no longer actionable.
            // Mark it failed, but leave stopped_at null because no STOP was confirmed.
            $locked->auditSessions()->where('status', 'queued')
                ->update(['status' => 'failed', 'updated_at' => now()]);
        });

        return back()->with('success', 'Server đã được disable tại Central; Agent sẽ không xác thực được các request tiếp theo.');
    }

    private function queue(MonitoredServer $server, string $type, array $payload, int $userId): AgentCommand
    {
        return $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload,
            'status' => 'queued',
            'requested_by' => $userId,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
