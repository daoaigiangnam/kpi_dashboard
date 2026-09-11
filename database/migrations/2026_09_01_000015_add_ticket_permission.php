<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'kpi.tickets'],
            [
                'module' => 'KPI',
                'name' => 'Ticket Data',
                'description' => 'Import and view Ticket data used by KPI calculations.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('code', 'kpi.tickets')->value('id');
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
        $permissionId = DB::table('permissions')->where('code', 'kpi.tickets')->value('id');

        if ($permissionId) {
            DB::table('group_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
