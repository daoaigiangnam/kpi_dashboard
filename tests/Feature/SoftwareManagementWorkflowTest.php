<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftwareManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_software_management_requires_login(): void
    {
        $this->get('/admin/software-management')->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_view_software_requests(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/admin/software-management')->assertForbidden();
    }
}