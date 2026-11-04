<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => 'pc_audit.package_macos'],
            [
                'module' => 'PC Audit',
                'name' => 'Create macOS Audit Package',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $adminGroup = DB::table('user_groups')->where('name', 'KPI Admin')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'pc_audit.package_macos')->value('id');
        if ($adminGroup && $permissionId) {
            DB::table('group_permissions')->updateOrInsert([
                'user_group_id' => $adminGroup,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'pc_audit.package_macos')->value('id');
        if ($permissionId) {
            DB::table('group_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
