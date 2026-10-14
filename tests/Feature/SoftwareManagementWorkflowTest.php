<?php

namespace Tests\Feature;

use App\Models\SoftwareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftwareManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_required_for_software_management(): void
    {
        $response = $this->get('/admin/software-management');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/admin/software-management')->assertForbidden();
    }

    public function test_request_cannot_be_closed_before_approval_and_checklist(): void
    {
        $this->markTestSkipped('Complete UAT with a seeded user group and software permissions in staging before enabling this test.');
    }
}