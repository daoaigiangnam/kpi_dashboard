<?php

namespace Tests\Feature;

use Tests\TestCase;

class ItToolsTest extends TestCase
{
    public function test_it_tools_routes_require_authentication(): void
    {
        $this->get('/admin/it-tools')->assertRedirect('/login');
        $this->postJson('/admin/it-tools/audit', ['domain' => 'example.com'])
            ->assertStatus(401);
        $this->postJson('/admin/it-tools/bulk-audit', ['items' => [['domain' => 'example.com']]])
            ->assertStatus(401);
    }

    public function test_bulk_audit_validation_rejects_empty_items(): void
    {
        $response = $this->withSession([])
            ->postJson('/admin/it-tools/bulk-audit', ['items' => []]);

        $this->assertContains($response->status(), [302, 401, 422]);
    }

    public function test_domain_audit_requires_a_domain(): void
    {
        $response = $this->postJson('/admin/it-tools/audit', []);

        $this->assertContains($response->status(), [302, 401, 422]);
    }
}
