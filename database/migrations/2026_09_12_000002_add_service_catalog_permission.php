<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permission = DB::table('permissions')->where('code', 'service_types.view')->first();
        if (!$permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module' => 'IT Monitoring',
                'name' => 'Manage Service Catalog',
                'code' => 'service_types.view',
                'description' => 'View and manage service type catalog and allowed service terms.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;
        }

        $groups = DB::table('user_groups')->whereIn('name', ['Super Admin', 'KPI Admin'])->pluck('id');
        foreach ($groups as $groupId) {
            DB::table('group_permissions')->updateOrInsert(
                ['user_group_id' => $groupId, 'permission_id' => $permissionId],
                []
            );
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'service_types.view')->first();
        if ($permission) {
            DB::table('group_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
