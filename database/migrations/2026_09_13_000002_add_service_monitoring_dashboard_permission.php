<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permission = [
            'module' => 'IT Monitoring',
            'name' => 'View Monitoring Dashboard',
            'code' => 'service_monitoring.view',
            'description' => 'View the IT Monitoring dashboard and current service status.',
        ];

        $existing = DB::table('permissions')->where('code', $permission['code'])->first();
        $permissionId = $existing?->id;

        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId($permission + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Team IT already has the other IT Monitoring permissions. Give it
        // the dashboard permission as well so the module is complete.
        $teamItIds = DB::table('user_groups')->where('name', 'Team IT')->pluck('id');
        foreach ($teamItIds as $groupId) {
            DB::table('group_permissions')->updateOrInsert(
                ['user_group_id' => $groupId, 'permission_id' => $permissionId],
                []
            );
        }

        // Keep the built-in administrative groups consistent with the
        // existing IT Monitoring permission set.
        $adminGroupIds = DB::table('user_groups')->whereIn('name', ['Super Admin', 'KPI Admin'])->pluck('id');
        foreach ($adminGroupIds as $groupId) {
            DB::table('group_permissions')->updateOrInsert(
                ['user_group_id' => $groupId, 'permission_id' => $permissionId],
                []
            );
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'service_monitoring.view')->first();
        if (!$permission) {
            return;
        }

        DB::table('group_permissions')->where('permission_id', $permission->id)->delete();
        DB::table('permissions')->where('id', $permission->id)->delete();
    }
};
