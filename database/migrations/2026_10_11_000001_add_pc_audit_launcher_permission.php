<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => 'pc_audit.launcher'],
            ['module' => 'PC Audit', 'name' => 'Create Audit Package', 'updated_at' => $now, 'created_at' => $now]
        );
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'pc_audit.launcher')->first();
        if ($permission) {
            DB::table('group_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
