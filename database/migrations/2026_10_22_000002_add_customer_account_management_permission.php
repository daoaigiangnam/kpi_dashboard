<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['module' => 'Customer Portal', 'name' => 'Manage customer asset accounts', 'code' => 'customer_accounts.manage', 'description' => 'Create and manage customer portal accounts and their asset/Audit permissions.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                $permission + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $superAdmin = DB::table('user_groups')->where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $permissionId = DB::table('permissions')->where('code', 'customer_accounts.manage')->value('id');
            DB::table('group_permissions')->updateOrInsert(
                ['user_group_id' => $superAdmin->id, 'permission_id' => $permissionId],
                []
            );
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'customer_accounts.manage')->first();
        if ($permission) {
            DB::table('group_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
