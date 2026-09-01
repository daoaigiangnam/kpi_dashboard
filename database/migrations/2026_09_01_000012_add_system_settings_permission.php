<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'module' => 'Admin',
            'name' => 'System Settings',
            'code' => 'system.settings',
            'description' => 'Manage system email, password recovery and future KPI configuration.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groups = DB::table('user_groups')->whereIn('name', ['Super Admin', 'KPI Admin'])->pluck('id');
        foreach ($groups as $groupId) {
            DB::table('group_permissions')->insertOrIgnore([
                'user_group_id' => $groupId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'system.settings')->value('id');
        if ($permissionId) {
            DB::table('group_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
