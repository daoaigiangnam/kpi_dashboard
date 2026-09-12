<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $types = [
            ['DOMAIN', 'Domain', 'Domain name registration / renewal'],
            ['SSL', 'SSL Certificate', 'SSL/TLS certificate'],
            ['VPS', 'VPS', 'Virtual private server'],
            ['HOSTING', 'Hosting', 'Web hosting / managed hosting'],
            ['LICENSE', 'License', 'Software or subscription license'],
            ['INTERNET', 'Internet', 'Internet / leased-line service'],
            ['BACKUP', 'Backup', 'Backup service / subscription'],
            ['EMAIL', 'Email Service', 'Business email service'],
            ['WEBSITE', 'Website', 'Website service / maintenance'],
            ['CONTRACT', 'Maintenance Contract', 'IT maintenance / support contract'],
            ['OTHER', 'Other', 'Other IT service requiring expiry alert'],
        ];

        foreach ($types as [$code, $name, $description]) {
            $id = DB::table('service_types')->where('code', $code)->value('id');
            if (!$id) {
                $id = DB::table('service_types')->insertGetId([
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $terms = match ($code) {
                'DOMAIN' => [12, 24],
                'SSL' => [3, 6, 12, 24],
                'VPS', 'HOSTING', 'INTERNET', 'BACKUP', 'EMAIL', 'WEBSITE', 'CONTRACT' => [1, 3, 6, 12, 24],
                'LICENSE' => [1, 3, 6, 12, 24],
                default => [1, 3, 6, 9, 12, 24],
            };
            foreach ($terms as $months) {
                DB::table('service_type_terms')->updateOrInsert(
                    ['service_type_id' => $id, 'months' => $months],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('service_types')->whereIn('code', ['DOMAIN','SSL','VPS','HOSTING','LICENSE','INTERNET','BACKUP','EMAIL','WEBSITE','CONTRACT','OTHER'])->delete();
    }
};
