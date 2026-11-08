<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ServerMonitoringController;
use App\Models\CommandAuditSession;
use App\Models\MonitoredServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServerMonitoringDisableTest extends TestCase
{
    use RefreshDatabase;

    private function disableServer(MonitoredServer $server): void
    {
        $request = Request::create('/admin/server-monitoring/'.$server->id.'/disable', 'POST');
        $request->headers->set('referer', '/admin/server-monitoring');
        $request->setUserResolver(fn () => User::factory()->create());
        $this->app->instance('request', $request);

        app(ServerMonitoringController::class)->disable($request, $server);
    }

    private function createServer(bool $auditEnabled): MonitoredServer
    {
        return MonitoredServer::create([
            'agent_id' => (string) Str::uuid(),
            'name' => 'Disable Test Server',
            'server_code' => 'DIS-' . strtoupper(Str::random(8)),
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => 'active',
            'audit_enabled' => $auditEnabled,
        ]);
    }

    public function test_disabling_server_does_not_claim_running_audit_was_stopped(): void
    {
        $server = $this->createServer(true);
        $session = $server->auditSessions()->create([
            'session_id' => (string) Str::uuid(),
            'status' => 'running',
            'started_at' => now()->subMinute(),
        ]);

        $this->disableServer($server);

        $this->assertSame('disabled', $server->fresh()->status);
        $this->assertTrue($server->fresh()->audit_enabled);
        $this->assertSame('running', $session->fresh()->status);
        $this->assertNull($session->fresh()->stopped_at);
    }

    public function test_disabling_server_cancels_queued_audit_without_fabricating_stop_time(): void
    {
        $server = $this->createServer(false);
        $session = $server->auditSessions()->create([
            'session_id' => (string) Str::uuid(),
            'status' => 'queued',
        ]);

        $this->disableServer($server);

        $this->assertSame('disabled', $server->fresh()->status);
        $this->assertFalse($server->fresh()->audit_enabled);
        $this->assertSame('failed', $session->fresh()->status);
        $this->assertNull($session->fresh()->stopped_at);
    }
}
