<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['module' => 'IT Monitoring', 'name' => 'Manage Service Customers', 'code' => 'service_customers.view', 'description' => 'View and manage IT service customers.'],
            ['module' => 'IT Monitoring', 'name' => 'Manage Service Providers', 'code' => 'service_providers.view', 'description' => 'View and manage IT service providers.'],
            ['module' => 'IT Monitoring', 'name' => 'Manage Services', 'code' => 'services.view', 'description' => 'View and manage monitored IT services.'],
            ['module' => 'IT Monitoring', 'name' => 'Manage Alert Policies', 'code' => 'service_alert_policies.view', 'description' => 'View and manage service alert policies.'],
            ['module' => 'IT Monitoring', 'name' => 'View Alert Events', 'code' => 'service_alert_events.view', 'description' => 'View service alert events.'],
            ['module' => 'IT Monitoring', 'name' => 'Run Service Monitoring', 'code' => 'service_monitoring.run', 'description' => 'Run IT service expiry monitoring.'],
        ];

        $groupIds = DB::table('user_groups')->whereIn('name', ['Super Admin', 'KPI Admin'])->pluck('id');
        foreach ($permissions as $item) {
            $existing = DB::table('permissions')->where('code', $item['code'])->first();
            $permissionId = $existing?->id;
            if (!$permissionId) {
                $permissionId = DB::table('permissions')->insertGetId($item + ['created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($groupIds as $groupId) {
                DB::table('group_permissions')->updateOrInsert(
                    ['user_group_id' => $groupId, 'permission_id' => $permissionId],
                    []
                );
            }
        }
    }

    public function down(): void
    {
        $codes = ['service_customers.view','service_providers.view','services.view','service_alert_policies.view','service_alert_events.view','service_monitoring.run'];
        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('group_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
