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

        // Existing Super Admin keeps full access to the newly introduced permissions.
        $superAdminId = DB::table('user_groups')->where('name', 'Super Admin')->value('id');
        if ($superAdminId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('group_permissions')->updateOrInsert([
                    'group_id' => $superAdminId,
                    'permission_id' => $permissionId,
                ], []);
            }
        }

        // KPI Admin also receives the complete PC Audit module permissions.
        $kpiAdminId = DB::table('user_groups')->where('name', 'KPI Admin')->value('id');
        if ($kpiAdminId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('group_permissions')->updateOrInsert([
                    'group_id' => $kpiAdminId,
                    'permission_id' => $permissionId,
                ], []);
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
