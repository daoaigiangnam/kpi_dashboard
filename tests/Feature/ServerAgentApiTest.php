<?php

namespace Tests\Feature;

use App\Models\AgentCommand;
use App\Models\MonitoredServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServerAgentApiTest extends TestCase
{
    use RefreshDatabase;

    private function createServer(string $status = 'active'): array
    {
        $plainToken = Str::random(64);
        $server = MonitoredServer::create([
            'agent_id' => (string) Str::uuid(),
            'name' => 'CI Test Server',
            'server_code' => 'CI-' . strtoupper(Str::random(8)),
            'token_hash' => hash('sha256', $plainToken),
            'status' => $status,
            'audit_enabled' => false,
        ]);

        return [$server, $plainToken];
    }

    public function test_agent_api_rejects_missing_and_invalid_credentials(): void
    {
        [$server, $token] = $this->createServer();

        $this->getJson('/api/agent/config')
            ->assertUnauthorized();

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/agent/config')
            ->assertUnauthorized();
    }

    public function test_authenticated_agent_can_read_config_and_updates_heartbeat_timestamp(): void
    {
        [$server, $token] = $this->createServer();

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/agent/config')
            ->assertOk()
            ->assertJsonPath('config_version', 1)
            ->assertJsonPath('modules.command_audit.enabled', false)
            ->assertJsonPath('modules.resource_monitor.enabled', false);

        $this->assertNotNull($server->fresh()->last_seen_at);
    }

    public function test_disabled_server_cannot_authenticate_even_with_correct_token(): void
    {
        [$server, $token] = $this->createServer('disabled');

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/agent/config')
            ->assertUnauthorized();
    }

    public function test_agent_poll_expires_old_commands_and_delivers_only_current_server_commands(): void
    {
        [$server, $token] = $this->createServer();
        [$otherServer] = $this->createServer();

        $expired = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => ['session_id' => (string) Str::uuid()],
            'status' => 'queued',
            'expires_at' => now()->subMinute(),
        ]);

        $queued = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_STOP',
            'payload' => ['session_id' => (string) Str::uuid()],
            'status' => 'queued',
            'expires_at' => now()->addMinutes(5),
        ]);

        $otherServer->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => [],
            'status' => 'queued',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/agent/commands')
            ->assertOk()
            ->assertJsonCount(1, 'commands')
            ->assertJsonPath('commands.0.command_id', $queued->command_id);

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('delivered', $queued->fresh()->status);
    }
    public function test_unacknowledged_delivered_command_is_redelivered_after_lease_with_same_id(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => ['session_id' => (string) Str::uuid()],
            'status' => 'delivered',
            'delivered_at' => now()->subMinutes(2),
            'lease_expires_at' => now()->subMinute(),
            'delivery_attempts' => 1,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/agent/commands')
            ->assertOk()
            ->assertJsonCount(1, 'commands')
            ->assertJsonPath('commands.0.command_id', $command->command_id)
            ->assertJsonPath('commands.0.delivery_attempt', 2);

        $fresh = $command->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertSame(2, $fresh->delivery_attempts);
        $this->assertTrue($fresh->lease_expires_at->isFuture());
    }

    public function test_delivery_retry_limit_marks_unacknowledged_command_failed(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => ['session_id' => (string) Str::uuid()],
            'status' => 'delivered',
            'delivered_at' => now()->subMinutes(2),
            'lease_expires_at' => now()->subMinute(),
            'delivery_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/agent/commands')
            ->assertOk()
            ->assertJsonCount(0, 'commands');

        $fresh = $command->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNull($fresh->lease_expires_at);
        $this->assertSame(5, $fresh->delivery_attempts);
        $this->assertNotNull($fresh->completed_at);
        $this->assertStringContainsString('retry limit', $fresh->error);
    }

    public function test_legacy_delivered_command_without_lease_is_recovered(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => ['session_id' => (string) Str::uuid()],
            'status' => 'delivered',
            'delivered_at' => now()->subMinutes(5),
            'lease_expires_at' => null,
            'delivery_attempts' => 1,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/agent/commands')
            ->assertOk()
            ->assertJsonCount(1, 'commands')
            ->assertJsonPath('commands.0.command_id', $command->command_id)
            ->assertJsonPath('commands.0.delivery_attempt', 2);

        $fresh = $command->fresh();
        $this->assertSame(2, $fresh->delivery_attempts);
        $this->assertTrue($fresh->lease_expires_at->isFuture());
    }

    public function test_executing_command_can_be_completed_after_start_deadline(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => [],
            'status' => 'executing',
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'completed',
            'result' => ['exit_code' => 0],
        ])->assertOk();

        $this->assertSame('completed', $command->fresh()->status);
    }

    public function test_authenticated_heartbeat_updates_agent_metadata(): void
    {
        [$server, $token] = $this->createServer();

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/heartbeat', [
            'agent_version' => '1.0.0',
            'hostname' => 'ci-ubuntu-01',
            'os_info' => ['name' => 'Ubuntu', 'version' => '24.04'],
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('server_code', $server->server_code);

        $this->assertSame('1.0.0', $server->fresh()->agent_version);
        $this->assertSame('ci-ubuntu-01', $server->fresh()->hostname);
        $this->assertSame('Ubuntu', $server->fresh()->os_info['name']);
    }

    public function test_agent_acknowledgement_enforces_command_state_transitions(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => [],
            'status' => 'delivered',
            'expires_at' => now()->addMinutes(5),
        ]);

        $headers = [
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ];

        $this->withHeaders($headers)->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'executing',
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'completed',
            'result' => ['exit_code' => 0],
        ])->assertOk();

        $this->assertSame('completed', $command->fresh()->status);
        $this->assertSame(0, $command->fresh()->result['exit_code']);
    }

    public function test_replayed_terminal_ack_does_not_overwrite_original_result(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'CUSTOM',
            'payload' => [],
            'status' => 'completed',
            'result' => ['exit_code' => 0, 'output' => 'original'],
            'error' => null,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'completed',
            'result' => ['exit_code' => 99, 'output' => 'replayed'],
        ])->assertOk();

        $storedResult = $command->fresh()->result;

        // MySQL JSON columns may return object keys in a different order.
        // Assert each value independently; key order is not part of the contract.
        $this->assertSame(0, $storedResult['exit_code']);
        $this->assertSame('original', $storedResult['output']);
    }

    public function test_expired_command_ack_is_rejected_and_persisted_as_expired(): void
    {
        [$server, $token] = $this->createServer();
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => [],
            'status' => 'delivered',
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'completed',
        ])->assertUnprocessable();

        $this->assertSame('expired', $command->fresh()->status);
    }

    public function test_audit_event_ingestion_deduplicates_events_and_counts_on_central(): void
    {
        [$server, $token] = $this->createServer();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'running',
            'event_count' => 0,
        ]);

        $headers = [
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ];
        $event = [
            'event_key' => 'audit-serial-1001',
            'event_time' => now()->toIso8601String(),
            'username' => 'ubuntu',
            'uid' => 1000,
            'pid' => 1234,
            'executable' => '/usr/bin/id',
            'arguments' => ['id', '-u'],
        ];

        $this->withHeaders($headers)->postJson('/api/agent/audit/events', [
            'session_id' => $sessionId,
            'events' => [$event, $event],
        ])->assertOk()->assertJsonPath('accepted', 1);

        $this->withHeaders($headers)->postJson('/api/agent/audit/events', [
            'session_id' => $sessionId,
            'events' => [$event],
        ])->assertOk()->assertJsonPath('accepted', 0);

        $this->assertDatabaseCount('command_audit_events', 1);
        $this->assertSame(1, $session->fresh()->event_count);
    }

    public function test_audit_event_batch_rejects_payload_larger_than_one_megabyte(): void
    {
        [$server, $token] = $this->createServer();
        $sessionId = (string) Str::uuid();
        $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'running',
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/audit/events', [
            'session_id' => $sessionId,
            'events' => [[
                'event_key' => 'oversized-event',
                'event_time' => now()->toIso8601String(),
                'raw_event' => ['payload' => str_repeat('x', 1_048_577)],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('events');

        $this->assertDatabaseCount('command_audit_events', 0);
    }

    public function test_completed_report_uses_central_event_count_not_agent_supplied_count(): void
    {
        [$server, $token] = $this->createServer();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'stopping',
            'event_count' => 0,
        ]);
        $server->forceFill(['audit_enabled' => true])->save();
        $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_STOP',
            'payload' => ['session_id' => $sessionId],
            'status' => 'completed',
            'expires_at' => now()->addMinutes(5),
        ]);

        foreach (['event-a', 'event-b'] as $key) {
            \Illuminate\Support\Facades\DB::table('command_audit_events')->insert([
                'server_id' => $server->id,
                'session_id' => $sessionId,
                'event_key' => $key,
                'event_time' => now(),
                'created_at' => now(),
            ]);
        }

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/audit/report', [
            'session_id' => $sessionId,
            'status' => 'completed',
            'event_count' => 999999,
        ])->assertOk();

        $this->assertSame(2, $session->fresh()->event_count);
        $this->assertFalse($server->fresh()->audit_enabled);
    }

    public function test_successful_audit_start_ack_marks_session_running_and_enabled(): void
    {
        [$server, $token] = $this->createServer();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'queued',
        ]);
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => ['session_id' => $sessionId],
            'status' => 'delivered',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'completed',
        ])->assertOk();

        $this->assertSame('running', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->started_at);
        $this->assertTrue($server->fresh()->audit_enabled);
    }

    public function test_failed_audit_stop_ack_keeps_collection_marked_enabled(): void
    {
        [$server, $token] = $this->createServer();
        $server->forceFill(['audit_enabled' => true])->save();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'stopping',
        ]);
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_STOP',
            'payload' => ['session_id' => $sessionId],
            'status' => 'delivered',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'failed',
            'error' => 'auditd still running',
        ])->assertOk();

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertNull($session->fresh()->stopped_at);
        $this->assertTrue($server->fresh()->audit_enabled);
    }

    public function test_agent_can_flush_final_audit_events_while_session_is_stopping(): void
    {
        [$server, $token] = $this->createServer();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'stopping',
            'event_count' => 0,
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/audit/events', [
            'session_id' => $sessionId,
            'events' => [[
                'event_key' => 'final-spool-event',
                'event_time' => now()->toIso8601String(),
                'executable' => '/usr/bin/id',
            ]],
        ])->assertOk()->assertJsonPath('accepted', 1);

        $this->assertSame(1, $session->fresh()->event_count);
    }

    public function test_completed_report_is_rejected_without_successful_stop_ack(): void
    {
        [$server, $token] = $this->createServer();
        $server->forceFill(['audit_enabled' => true])->save();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'stopping',
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/audit/report', [
            'session_id' => $sessionId,
            'status' => 'completed',
        ])->assertUnprocessable();

        $this->assertSame('stopping', $session->fresh()->status);
        $this->assertNull($session->fresh()->stopped_at);
        $this->assertTrue($server->fresh()->audit_enabled);
    }

    public function test_failed_audit_report_does_not_fabricate_stop_time_or_clear_enabled_flag(): void
    {
        [$server, $token] = $this->createServer();
        $server->forceFill(['audit_enabled' => true])->save();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'stopping',
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/audit/report', [
            'session_id' => $sessionId,
            'status' => 'failed',
            'event_count' => 0,
        ])->assertOk();

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertNull($session->fresh()->stopped_at);
        $this->assertTrue($server->fresh()->audit_enabled);
    }

    public function test_failed_audit_start_ack_does_not_fabricate_started_or_stopped_times(): void
    {
        [$server, $token] = $this->createServer();
        $sessionId = (string) Str::uuid();
        $session = $server->auditSessions()->create([
            'session_id' => $sessionId,
            'status' => 'queued',
        ]);
        $command = $server->commands()->create([
            'command_id' => (string) Str::uuid(),
            'type' => 'AUDIT_START',
            'payload' => ['session_id' => $sessionId],
            'status' => 'delivered',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/agent/ack', [
            'command_id' => $command->command_id,
            'status' => 'failed',
            'error' => 'audit rule installation failed',
        ])->assertOk();

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertNull($session->fresh()->started_at);
        $this->assertNull($session->fresh()->stopped_at);
        $this->assertFalse($server->fresh()->audit_enabled);
    }

}
