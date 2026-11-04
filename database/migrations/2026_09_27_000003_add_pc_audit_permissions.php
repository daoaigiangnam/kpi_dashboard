<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $permissions = [
        ['module' => 'PC Audit', 'name' => 'View Audit Results', 'code' => 'pc_audit.view'],
        ['module' => 'PC Audit', 'name' => 'Export Audit Results', 'code' => 'pc_audit.export'],
        ['module' => 'PC Audit', 'name' => 'Tool Configuration', 'code' => 'pc_audit.settings'],
        ['module' => 'PC Audit', 'name' => 'Manage Audit Codes', 'code' => 'pc_audit.codes'],
        ['module' => 'PC Audit', 'name' => 'Manage Email Recipients', 'code' => 'pc_audit.recipients'],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            $exists = DB::table('permissions')->where('code', $permission['code'])->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    ...$permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permissions')
                    ->where('code', $permission['code'])
                    ->update([
                        'module' => $permission['module'],
                        'name' => $permission['name'],
                        'updated_at' => now(),
                    ]);
            }
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_column($this->permissions, 'code'))
            ->pluck('id');

        // group_permissions uses user_group_id, not group_id.
        $groupIds = DB::table('user_groups')
            ->whereIn('name', ['Super Admin', 'KPI Admin'])
            ->pluck('id', 'name');

        foreach (['Super Admin', 'KPI Admin'] as $groupName) {
            $groupId = $groupIds->get($groupName);

            if (!$groupId) {
                continue;
            }

            foreach ($permissionIds as $permissionId) {
                DB::table('group_permissions')->updateOrInsert([
                    'user_group_id' => $groupId,
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
