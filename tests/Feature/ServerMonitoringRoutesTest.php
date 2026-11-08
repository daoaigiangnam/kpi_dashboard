<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServerMonitoringRoutesTest extends TestCase
{
    public function test_agent_routes_are_registered_with_agent_authentication(): void
    {
        $routes = app('router')->getRoutes();

        $config = $routes->getByName('generated::server-agent-config');
        $this->assertNotNull($config);

        $agentRoutes = collect($routes)->filter(
            fn ($route) => str_starts_with($route->uri(), 'api/agent/')
        );

        $this->assertNotEmpty($agentRoutes);

        foreach ($agentRoutes as $route) {
            $this->assertContains('agent.token', $route->gatherMiddleware());
        }
    }

    public function test_admin_audit_controls_require_the_audit_permission(): void
    {
        $routes = app('router')->getRoutes();

        $start = collect($routes)->first(
            fn ($route) => $route->getName() === 'admin.server_monitoring.audit.start'
        );

        $stop = collect($routes)->first(
            fn ($route) => $route->getName() === 'admin.server_monitoring.audit.stop'
        );

        $this->assertNotNull($start);
        $this->assertNotNull($stop);
        $this->assertContains('permission:server_monitoring.audit', $start->gatherMiddleware());
        $this->assertContains('permission:server_monitoring.audit', $stop->gatherMiddleware());
    }

    public function test_agent_token_middleware_alias_is_registered(): void
    {
        $aliases = app('router')->getMiddleware();

        $this->assertArrayHasKey('agent.token', $aliases);
        $this->assertSame(
            \App\Http\Middleware\AuthenticateAgent::class,
            $aliases['agent.token']
        );
    }
}
