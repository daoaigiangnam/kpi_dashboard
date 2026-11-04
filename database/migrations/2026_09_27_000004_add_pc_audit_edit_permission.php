<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $code = 'pc_audit.edit';

    public function up(): void
    {
        $permission = DB::table('permissions')->where('code', $this->code)->first();

        if (!$permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module' => 'PC Audit',
                'name' => 'Edit Audit Information',
                'code' => $this->code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;
            DB::table('permissions')->where('id', $permissionId)->update([
                'module' => 'PC Audit',
                'name' => 'Edit Audit Information',
                'updated_at' => now(),
            ]);
        }

        $groupIds = DB::table('user_groups')
            ->whereIn('name', ['Super Admin', 'KPI Admin'])
            ->pluck('id');

        foreach ($groupIds as $groupId) {
            DB::table('group_permissions')->updateOrInsert([
                'user_group_id' => $groupId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', $this->code)->value('id');

        if ($permissionId) {
            DB::table('group_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
