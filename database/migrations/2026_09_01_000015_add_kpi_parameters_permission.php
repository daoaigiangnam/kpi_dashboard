<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'kpi.parameters'],
            [
                'module' => 'KPI',
                'name' => 'KPI Parameters',
                'description' => 'Manage KPI weights and SLA priority parameters.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('code', 'kpi.parameters')->value('id');
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
        $permissionId = DB::table('permissions')->where('code', 'kpi.parameters')->value('id');
        if ($permissionId) {
            DB::table('group_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
