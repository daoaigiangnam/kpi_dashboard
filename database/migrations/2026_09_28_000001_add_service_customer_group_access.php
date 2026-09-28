<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('service_customer_group')) {
            Schema::create('service_customer_group', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_customer_id')->constrained('service_customers')->cascadeOnDelete();
                $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['service_customer_id', 'user_group_id'], 'scg_customer_group_unique');
            });
        }

        $permissions = [
            ['name' => 'View Customers', 'code' => 'service_customers.view', 'description' => 'View customers assigned to the user group.'],
            ['name' => 'Create Customers', 'code' => 'service_customers.create', 'description' => 'Create service customers.'],
            ['name' => 'Edit Customers', 'code' => 'service_customers.edit', 'description' => 'Edit customers assigned to the user group.'],
            ['name' => 'Delete Customers', 'code' => 'service_customers.delete', 'description' => 'Delete and restore customers assigned to the user group.'],
            ['name' => 'Manage Customer Access', 'code' => 'service_customers.access', 'description' => 'Assign customers to user groups.'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $permissionIds[$permission['code']] = DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'module' => 'IT Monitoring',
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $permissionIds[$permission['code']] = DB::table('permissions')->where('code', $permission['code'])->value('id');
        }

        $allPermissionIds = array_values($permissionIds);
        $superAdmin = DB::table('user_groups')->where('name', 'Super Admin')->value('id');
        if ($superAdmin) {
            foreach ($allPermissionIds as $permissionId) {
                DB::table('group_permissions')->updateOrInsert(
                    ['user_group_id' => $superAdmin, 'permission_id' => $permissionId],
                    []
                );
            }
        }

        // Existing IT operation groups that already manage Services receive the
        // basic customer permissions automatically. Access assignment remains
        // restricted until an administrator grants service_customers.access.
        $serviceViewPermission = DB::table('permissions')->where('code', 'services.view')->value('id');
        if ($serviceViewPermission) {
            $opsGroups = DB::table('group_permissions')
                ->where('permission_id', $serviceViewPermission)
                ->pluck('user_group_id');

            foreach ($opsGroups as $groupId) {
                foreach (['service_customers.view', 'service_customers.create', 'service_customers.edit'] as $code) {
                    DB::table('group_permissions')->updateOrInsert(
                        ['user_group_id' => $groupId, 'permission_id' => $permissionIds[$code]],
                        []
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $codes = [
            'service_customers.view',
            'service_customers.create',
            'service_customers.edit',
            'service_customers.delete',
            'service_customers.access',
        ];

        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('group_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        Schema::dropIfExists('service_customer_group');
    }
};
