<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            ['DOMAIN', 'Standard', 20, 10, 5],
            ['SSL', 'Standard', 25, 10, 5],
            ['VPS', 'Standard', 20, 10, 5],
            ['HOSTING', 'Standard', 20, 10, 5],
            ['LICENSE', 'Standard', 20, 10, 5],
            ['INTERNET', 'Standard', 20, 10, 5],
            ['BACKUP', 'Standard', 20, 10, 5],
            ['EMAIL', 'Standard', 20, 10, 5],
            ['WEBSITE', 'Standard', 20, 10, 5],
            ['MAINTENANCE', 'Standard', 20, 10, 5],
            ['OTHER', 'Standard', 20, 10, 5],
        ];

        foreach ($defaults as [$code, $name, $a1, $a2, $a3]) {
            $type = DB::table('service_types')->where('code', $code)->first();
            if (!$type) continue;

            DB::table('service_alert_policies')->updateOrInsert(
                ['service_type_id' => $type->id, 'name' => $name],
                [
                    'alert_1_percent' => $a1,
                    'alert_2_percent' => $a2,
                    'alert_3_percent' => $a3,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Keep seeded policies unless explicitly removed by an administrator.
    }
};
