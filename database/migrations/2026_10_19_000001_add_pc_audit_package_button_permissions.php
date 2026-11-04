<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $permissions = [
        ['module' => 'PC Audit', 'name' => 'Create Windows 7 PowerShell Package', 'code' => 'pc_audit.package_windows7'],
        ['module' => 'PC Audit', 'name' => 'Create Windows 10/11 PowerShell Package', 'code' => 'pc_audit.package_windows10'],
        ['module' => 'PC Audit', 'name' => 'Create Windows 7/10/11 Node.js Package', 'code' => 'pc_audit.package_nodejs'],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'module' => $permission['module'],
                    'name' => $permission['name'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // Grant these controls to KPI Admin. Super Admin bypasses permission checks.
        $adminGroup = DB::table('user_groups')->where('name', 'KPI Admin')->value('id');
        if ($adminGroup) {
            $permissionIds = DB::table('permissions')
                ->whereIn('code', array_column($this->permissions, 'code'))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('group_permissions')->updateOrInsert([
                    'user_group_id' => $adminGroup,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_column($this->permissions, 'code'))
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('group_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
