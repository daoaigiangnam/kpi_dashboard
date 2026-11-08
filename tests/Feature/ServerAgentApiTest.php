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
            ->assertJsonPath('server_code', null)
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
}
