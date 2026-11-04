<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permission = [
            'module' => 'PC Audit',
            'name' => 'Delete Audit Results',
            'code' => 'pc_audit.delete',
        ];

        $exists = DB::table('permissions')->where('code', $permission['code'])->exists();

        if (!$exists) {
            $permissionId = DB::table('permissions')->insertGetId([
                ...$permission,
                'description' => 'Permanently delete a PC Audit and all collected hardware, software and audit detail data.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = DB::table('permissions')->where('code', $permission['code'])->value('id');
            DB::table('permissions')->where('id', $permissionId)->update([
                'module' => $permission['module'],
                'name' => $permission['name'],
                'description' => 'Permanently delete a PC Audit and all collected hardware, software and audit detail data.',
                'updated_at' => now(),
            ]);
        }

        // Super Admin keeps full access. KPI Admin follows the existing PC Audit
        // module convention and receives the new permission by default; it can
        // still be removed from any group in Group Management.
        foreach (['Super Admin', 'KPI Admin'] as $groupName) {
            $groupId = DB::table('user_groups')->where('name', $groupName)->value('id');
            if (!$groupId) {
                continue;
            }

            DB::table('group_permissions')->updateOrInsert([
                'user_group_id' => $groupId,
                'permission_id' => $permissionId,
            ], []);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('code', 'pc_audit.delete')
            ->value('id');

        if ($permissionId) {
            DB::table('group_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
