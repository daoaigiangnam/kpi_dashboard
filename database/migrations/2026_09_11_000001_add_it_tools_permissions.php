<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissions = [
            ['IT Tools','View IT Tools','it_tools.view'],
            ['IT Tools','Run Asset Audit','it_tools.audit'],
            ['IT Tools','Domain Audit','it_tools.domain'],
            ['IT Tools','DNS Lookup','it_tools.dns'],
            ['IT Tools','SSL/TLS Checker','it_tools.ssl'],
            ['IT Tools','Website Checker','it_tools.website'],
            ['IT Tools','Email Security','it_tools.email'],
            ['IT Tools','IP/Hosting Lookup','it_tools.ip'],
        ];

        foreach ($permissions as [$module, $name, $code]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['module' => $module, 'name' => $name]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->whereIn('code', [
            'it_tools.view','it_tools.audit','it_tools.domain','it_tools.dns',
            'it_tools.ssl','it_tools.website','it_tools.email','it_tools.ip',
        ])->delete();
    }
};
