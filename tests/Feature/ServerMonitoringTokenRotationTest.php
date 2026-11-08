<?php

namespace Tests\\Feature;

use App\\Http\\Controllers\\Admin\\ServerMonitoringController;
use App\\Models\\MonitoredServer;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class ServerMonitoringTokenRotationTest extends TestCase
{
    use RefreshDatabase;

    private function createServer(string $status = 'active'): array
    {
        $token = Str::random(64);
        $server = MonitoredServer::create([
            'agent_id' => (string) Str::uuid(),
            'name' => 'Token Rotation Test',
            'server_code' => 'ROT-' . strtoupper(Str::random(8)),
            'token_hash' => hash('sha256', $token),
            'status' => $status,
            'audit_enabled' => false,
        ]);

        return [$server, $token];
    }

    public function test_rotation_invalidates_old_token_and_issues_new_token_once(): void
    {
        [$server, $oldToken] = $this->createServer();
        $request = Request::create('/admin/server-monitoring/' . $server->id . '/rotate-token', 'POST');
        $request->setUserResolver(fn () => User::factory()->create());

        $response = app(ServerMonitoringController::class)->rotateToken($request, $server);
        $this->assertSame(302, $response->getStatusCode());

        $newToken = $response->getSession()->get('issued_agent_token');
        $this->assertIsString($newToken);
        $this->assertSame(64, strlen($newToken));
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $server->fresh()->token_hash);

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $oldToken,
        ])->getJson('/api/agent/config')->assertUnauthorized();

        $this->withHeaders([
            'X-Agent-ID' => $server->agent_id,
            'Authorization' => 'Bearer ' . $newToken,
        ])->getJson('/api/agent/config')->assertOk();
    }

    public function test_rotation_does_not_reenable_a_disabled_server(): void
    {
        [$server] = $this->createServer('disabled');
        $request = Request::create('/admin/server-monitoring/' . $server->id . '/rotate-token', 'POST');
        $request->setUserResolver(fn () => User::factory()->create());

        app(ServerMonitoringController::class)->rotateToken($request, $server);

        $this->assertSame('disabled', $server->fresh()->status);
    }
}
