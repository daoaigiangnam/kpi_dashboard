<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $types = [
            ['code' => 'DOMAIN', 'name' => 'Domain', 'description' => 'Domain registration and renewal.'],
            ['code' => 'SSL', 'name' => 'SSL Certificate', 'description' => 'SSL/TLS certificate.'],
            ['code' => 'VPS', 'name' => 'VPS', 'description' => 'Virtual private server service.'],
            ['code' => 'HOSTING', 'name' => 'Hosting', 'description' => 'Web or application hosting service.'],
            ['code' => 'LICENSE', 'name' => 'License', 'description' => 'Software or platform license.'],
            ['code' => 'INTERNET', 'name' => 'Internet', 'description' => 'Internet or connectivity service.'],
            ['code' => 'BACKUP', 'name' => 'Backup', 'description' => 'Backup service or subscription.'],
            ['code' => 'EMAIL', 'name' => 'Email Service', 'description' => 'Email hosting or subscription.'],
            ['code' => 'WEBSITE', 'name' => 'Website', 'description' => 'Website or web service.'],
            ['code' => 'MAINTENANCE', 'name' => 'Maintenance Contract', 'description' => 'Support or maintenance contract.'],
            ['code' => 'OTHER', 'name' => 'Other', 'description' => 'Other monitored IT service.'],
        ];

        foreach ($types as $type) {
            $id = DB::table('service_types')->where('code', $type['code'])->value('id');
            if (!$id) {
                $id = DB::table('service_types')->insertGetId($type + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ([1,3,6,9,12,24] as $months) {
                DB::table('service_type_terms')->updateOrInsert(
                    ['service_type_id' => $id, 'months' => $months],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep reference data on rollback; remove only by the normal catalog UI.
    }
};
