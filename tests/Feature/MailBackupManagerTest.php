<?php

namespace Tests\Feature;

use App\Models\MailBackupBatch;
use App\Models\MailBackupJob;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MailBackupManagerTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $codes): User
    {
        $group = UserGroup::create(['name' => 'Test Mail Backup ' . bin2hex(random_bytes(3)), 'description' => 'Test']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['module' => 'Mail Backup', 'name' => $code]
            );
            $group->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['user_group_id' => $group->id, 'is_active' => true]);
    }

    public function test_manager_page_requires_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $this->actingAs($user)->get(route('admin.mail_backup.index'))->assertForbidden();
    }

    public function test_csv_import_creates_one_pending_job_per_unique_valid_email(): void
    {
        $user = $this->userWithPermissions(['mail_backup.view', 'mail_backup.manage']);
        $csv = UploadedFile::fake()->createWithContent(
            'accounts.csv',
            "email,display_name\nuser1@example.com,User One\nuser1@example.com,Duplicate\ninvalid,Bad\nuser2@example.com,User Two\n"
        );

        $response = $this->actingAs($user)->post(route('admin.mail_backup.store'), [
            'name' => 'Test Backup',
            'date_from' => '2026-01-01',
            'date_to' => '2026-09-30',
            'mail_scope' => 'all',
            'accounts_csv' => $csv,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('mail_backup_batches', 1);
        $this->assertDatabaseCount('mail_backup_jobs', 2);
        $this->assertDatabaseHas('mail_backup_jobs', ['email' => 'user1@example.com', 'status' => 'PENDING']);
        $this->assertDatabaseHas('mail_backup_jobs', ['email' => 'user2@example.com', 'status' => 'PENDING']);
        $this->assertDatabaseHas('mail_backup_audit_logs', ['action' => 'BATCH_IMPORTED']);
    }

    public function test_retry_failed_does_not_change_completed_jobs(): void
    {
        $user = $this->userWithPermissions(['mail_backup.view', 'mail_backup.manage']);
        $batch = MailBackupBatch::create([
            'batch_code' => 'MB-TEST-001', 'name' => 'Retry Test',
            'date_from' => '2026-01-01', 'date_to' => '2026-09-30',
            'mail_scope' => 'all', 'status' => 'PARTIAL_FAILED', 'created_by' => $user->id,
        ]);
        $failed = $batch->jobs()->create(['email' => 'failed@example.com', 'status' => 'FAILED', 'attempt_count' => 1, 'last_error' => 'Temporary error']);
        $completed = $batch->jobs()->create(['email' => 'done@example.com', 'status' => 'COMPLETED', 'attempt_count' => 1]);

        $this->actingAs($user)->post(route('admin.mail_backup.retry_failed', $batch), ['confirm_retry' => '1'])->assertRedirect();

        $this->assertDatabaseHas('mail_backup_jobs', ['id' => $failed->id, 'status' => 'PENDING', 'attempt_count' => 2, 'last_error' => null]);
        $this->assertDatabaseHas('mail_backup_jobs', ['id' => $completed->id, 'status' => 'COMPLETED', 'attempt_count' => 1]);
    }
}
