<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandAuditSession;
use App\Models\MonitoredServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServerAgentController extends Controller
{
    private function server(Request $request): MonitoredServer
    {
        /** @var MonitoredServer $server */
        $server = $request->attributes->get('monitored_server');
        return $server;
    }

    public function heartbeat(Request $request)
    {
        $data = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:40'],
            'hostname' => ['nullable', 'string', 'max:190'],
            'os_info' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $server = $this->server($request);
        $server->forceFill([
            'last_seen_at' => now(),
            'agent_version' => $data['agent_version'] ?? $server->agent_version,
            'hostname' => $data['hostname'] ?? $server->hostname,
            'os_info' => $data['os_info'] ?? $server->os_info,
            'metadata' => $data['metadata'] ?? $server->metadata,
        ])->save();

        return response()->json([
            'ok' => true,
            'server_code' => $server->server_code,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function config(Request $request)
    {
        $server = $this->server($request);

        return response()->json([
            'config_version' => 1,
            'modules' => [
                'command_audit' => ['enabled' => (bool) $server->audit_enabled],
                'resource_monitor' => ['enabled' => false],
            ],
            'poll_interval_seconds' => 10,
        ]);
    }

    public function commands(Request $request)
    {
        $server = $this->server($request);

        $commands = DB::transaction(function () use ($server) {
            $now = now();

            $server->commands()
                ->whereIn('status', ['queued', 'delivered'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->update(['status' => 'expired', 'lease_expires_at' => null, 'updated_at' => $now]);

            // Stop retrying commands that exhausted the delivery budget.
            // This avoids an infinite poll/re-delivery loop for an offline or broken Agent.
            $server->commands()
                ->where('status', 'delivered')
                ->where('delivery_attempts', '>=', 5)
                ->where(function ($query) use ($now) {
                    $query->whereNull('lease_expires_at')
                        ->orWhere('lease_expires_at', '<=', $now);
                })
                ->update([
                    'status' => 'failed',
                    'lease_expires_at' => null,
                    'error' => 'Delivery retry limit exceeded without acknowledgement.',
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);

            // Re-deliver an unacknowledged command after its 60-second delivery
            // lease expires. Keep the same command_id so the Agent can deduplicate.
            $rows = $server->commands()
                ->where(function ($query) use ($now) {
                    $query->where('status', 'queued')
                        ->orWhere(function ($retry) use ($now) {
                            $retry->where('status', 'delivered')
                                ->where(function ($lease) use ($now) {
                                    // Legacy deliveries created before leases have NULL here;
                                    // make them eligible for one safe re-delivery.
                                    $lease->whereNull('lease_expires_at')
                                        ->orWhere('lease_expires_at', '<=', $now);
                                });
                        });
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                })
                ->orderBy('id')
                ->limit(10)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $command) {
                $command->forceFill([
                    'status' => 'delivered',
                    'delivered_at' => $now,
                    'lease_expires_at' => $now->copy()->addMinute(),
                    'delivery_attempts' => (int) $command->delivery_attempts + 1,
                ])->save();
            }

            return $rows;
        });

        return response()->json([
            'commands' => $commands->map(fn ($command) => [
                'command_id' => $command->command_id,
                'type' => $command->type,
                'payload' => $command->payload,
                'expires_at' => $command->expires_at?->toIso8601String(),
                'delivery_attempt' => (int) $command->delivery_attempts,
            ])->values(),
        ]);
    }

    public function acknowledge(Request $request)
    {
        $data = $request->validate([
            'command_id' => ['required', 'uuid'],
            'status' => ['required', 'in:executing,completed,failed'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        $acknowledged = DB::transaction(function () use ($data, $request) {
            $server = $this->server($request);
            $command = $server->commands()
                ->where('command_id', $data['command_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $current = $command->status;

            // Expiry prevents starting an unacknowledged command; once the Agent
            // has ACKed "executing", allow its terminal ACK even past expires_at.
            if ($command->expires_at && $command->expires_at->isPast()
                && in_array($current, ['queued', 'delivered'], true)) {
                if ($current !== 'expired') {
                    $command->forceFill(['status' => 'expired', 'lease_expires_at' => null])->save();
                }

                return false;
            }
            $next = $data['status'];

            // Replayed ACKs are idempotent: never let a retry rewrite data
            // recorded by the first ACK for the current execution state.
            if ($current === $next && in_array($current, ['executing', 'completed', 'failed'], true)) {
                return true;
            }

            $allowed = [
                'delivered' => ['executing', 'completed', 'failed'],
                'executing' => ['completed', 'failed'],
                'completed' => ['completed'],
                'failed' => ['failed'],
            ];

            if (! in_array($next, $allowed[$current] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Invalid command state transition: {$current} -> {$next}.",
                ]);
            }

            $command->forceFill([
                'status' => $next,
                'result' => $data['result'] ?? $command->result,
                'error' => $data['error'] ?? $command->error,
                'completed_at' => in_array($next, ['completed', 'failed'], true)
                    ? ($command->completed_at ?? now())
                    : null,
                // ACK ends the delivery lease; executing commands are no longer redelivered.
                'lease_expires_at' => null,
            ])->save();

            if (in_array($next, ['completed', 'failed'], true)) {
                $sessionId = $command->payload['session_id'] ?? null;
                if ($sessionId && in_array($command->type, ['AUDIT_START', 'AUDIT_STOP'], true)) {
                    $session = $server->auditSessions()
                        ->where('session_id', $sessionId)
                        ->lockForUpdate()
                        ->first();

                    if ($session) {
                        if ($command->type === 'AUDIT_START' && $session->status === 'queued') {
                            $session->forceFill([
                                'status' => $next === 'completed' ? 'running' : 'failed',
                                'started_at' => $next === 'completed' ? now() : null,
                                // A failed START is not evidence of a STOP.
                                'stopped_at' => null,
                            ])->save();

                            if ($next === 'completed') {
                                $server->forceFill(['audit_enabled' => true])->save();
                            }
                        } elseif ($command->type === 'AUDIT_STOP' && $session->status === 'stopping') {
                            $session->forceFill([
                                'status' => $next === 'completed' ? 'completed' : 'failed',
                                // Only a successful STOP ACK confirms the stop time.
                                'stopped_at' => $next === 'completed' ? now() : null,
                            ])->save();

                            // A failed STOP does not prove that audit collection stopped.
                            // Keep the server marked enabled until a successful STOP is confirmed.
                            if ($next === 'completed') {
                                $server->forceFill(['audit_enabled' => false])->save();
                            }
                        }
                    }
                }
            }

            return true;
        });

        if (! $acknowledged) {
            throw ValidationException::withMessages([
                'command_id' => 'Command has expired and must not be executed.',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function auditEvents(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'events' => ['required', 'array', 'max:500'],
            'events.*.event_key' => ['required', 'string', 'max:190'],
            'events.*.event_time' => ['required', 'date'],
            'events.*.username' => ['nullable', 'string', 'max:190'],
            'events.*.uid' => ['nullable', 'integer', 'min:0'],
            'events.*.auid' => ['nullable', 'integer', 'min:0'],
            'events.*.pid' => ['nullable', 'integer', 'min:0'],
            'events.*.ppid' => ['nullable', 'integer', 'min:0'],
            'events.*.executable' => ['nullable', 'string', 'max:1024'],
            'events.*.arguments' => ['nullable', 'array', 'max:256'],
            'events.*.arguments.*' => ['string', 'max:2048'],
            'events.*.audit_serial' => ['nullable', 'string', 'max:100'],
            'events.*.source_ip' => ['nullable', 'ip'],
            'events.*.raw_event' => ['nullable', 'array'],
        ]);

        // Bound the serialized event batch, including nested raw audit records.
        // Count limits alone do not prevent a single oversized JSON payload.
        $eventPayload = json_encode($data['events'], JSON_THROW_ON_ERROR);
        if (strlen($eventPayload) > 1_048_576) {
            throw ValidationException::withMessages([
                'events' => 'Audit event batch exceeds the 1 MiB limit.',
            ]);
        }

        $server = $this->server($request);
        $accepted = DB::transaction(function () use ($data, $server) {
            $session = $server->auditSessions()
                ->where('session_id', $data['session_id'])
                // The Agent must flush its durable spool after STOP is requested
                // and before it ACKs STOP; accept that final batch while stopping.
                ->whereIn('status', ['running', 'stopping'])
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();
            $rows = [];
            foreach ($data['events'] as $event) {
                $rows[] = [
                    'server_id' => $server->id,
                    'session_id' => $session->session_id,
                    'event_key' => $event['event_key'],
                    'event_time' => $event['event_time'],
                    'username' => $event['username'] ?? null,
                    'uid' => $event['uid'] ?? null,
                    'auid' => $event['auid'] ?? null,
                    'pid' => $event['pid'] ?? null,
                    'ppid' => $event['ppid'] ?? null,
                    'executable' => $event['executable'] ?? null,
                    'arguments' => isset($event['arguments']) ? json_encode($event['arguments']) : null,
                    'audit_serial' => $event['audit_serial'] ?? null,
                    'source_ip' => $event['source_ip'] ?? null,
                    'raw_event' => isset($event['raw_event']) ? json_encode($event['raw_event']) : null,
                    'created_at' => $now,
                ];
            }

            $inserted = 0;
            foreach (array_chunk($rows, 100) as $chunk) {
                $inserted += DB::table('command_audit_events')->insertOrIgnore($chunk);
            }

            if ($inserted > 0) {
                $session->increment('event_count', $inserted);
            }

            return $inserted;
        });

        return response()->json(['ok' => true, 'accepted' => $accepted]);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'status' => ['required', 'in:completed,failed'],
            // Event counts are maintained by Central from accepted, deduplicated events.
            'event_count' => ['sometimes', 'integer', 'min:0'],
            'report_sha256' => ['nullable', 'regex:/^[a-fA-F0-9]{64}$/'],
            'report_name' => ['nullable', 'string', 'max:190'],
        ]);

        $server = $this->server($request);

        DB::transaction(function () use ($data, $server) {
            $session = $server->auditSessions()
                ->where('session_id', $data['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($session->status, ['stopping', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'session_id' => 'Session is not in a reportable state.',
                ]);
            }

            if ($session->status === 'completed' && $data['status'] !== 'completed') {
                throw ValidationException::withMessages([
                    'status' => 'A completed audit session cannot be changed to failed.',
                ]);
            }

            // A report is not proof that auditd stopped. Require a successful
            // AUDIT_STOP command ACK before accepting a completed report.
            if ($data['status'] === 'completed') {
                $stopConfirmed = $server->commands()
                    ->where('type', 'AUDIT_STOP')
                    ->where('status', 'completed')
                    ->where('payload->session_id', $session->session_id)
                    ->exists();

                if (! $stopConfirmed) {
                    throw ValidationException::withMessages([
                        'status' => 'A completed report requires a successful AUDIT_STOP acknowledgement.',
                    ]);
                }
            }

            // Do not trust an Agent-supplied count; Central's deduplicated event
            // ingestion is the source of truth for the session count.
            $centralEventCount = DB::table('command_audit_events')
                ->where('server_id', $server->id)
                ->where('session_id', $session->session_id)
                ->count();

            $session->forceFill([
                'status' => $data['status'],
                // A failed report does not prove that local audit collection stopped.
                'stopped_at' => $data['status'] === 'completed'
                    ? ($session->stopped_at ?? now())
                    : null,
                'event_count' => $centralEventCount,
                'metadata' => array_merge($session->metadata ?? [], [
                    'report_sha256' => $data['report_sha256'] ?? null,
                    'report_name' => $data['report_name'] ?? null,
                ]),
            ])->save();

            // A failed report leaves collection state uncertain; only a successful
            // completion report is allowed to clear the enabled flag.
            if ($data['status'] === 'completed') {
                $server->forceFill(['audit_enabled' => false])->save();
            }
        });

        return response()->json(['ok' => true]);
    }
}
