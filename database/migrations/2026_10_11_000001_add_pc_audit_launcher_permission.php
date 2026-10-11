<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'pc_audit.launcher'],
            ['module' => 'PC Audit', 'name' => 'Create Audit Package']
        );
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'pc_audit.launcher')->first();
        if ($permission) {
            DB::table('permission_user_group')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
