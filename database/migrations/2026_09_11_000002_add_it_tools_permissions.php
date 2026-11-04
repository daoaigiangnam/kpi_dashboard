<?php

use App\Models\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defs = [
            ['IT Tools', 'View IT Tools', 'it_tools.view'],
            ['IT Tools', 'Run Asset Audit', 'it_tools.audit'],
            ['IT Tools', 'Domain Audit', 'it_tools.domain'],
            ['IT Tools', 'DNS Lookup', 'it_tools.dns'],
            ['IT Tools', 'SSL/TLS Checker', 'it_tools.ssl'],
            ['IT Tools', 'Website Checker', 'it_tools.website'],
            ['IT Tools', 'Email Security', 'it_tools.email'],
            ['IT Tools', 'IP/Hosting Lookup', 'it_tools.ip'],
        ];

        foreach ($defs as [$module, $name, $code]) {
            Permission::updateOrCreate(['code' => $code], ['module' => $module, 'name' => $name]);
        }

        $super = UserGroup::where('name', 'Super Admin')->first();
        if ($super) {
            $super->permissions()->syncWithoutDetaching(Permission::whereIn('code', array_column(array_map(fn ($d) => ['code' => $d[2]], $defs), 'code'))->pluck('id')->all());
        }

        $admin = UserGroup::where('name', 'KPI Admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(Permission::whereIn('code', array_column(array_map(fn ($d) => ['code' => $d[2]], $defs), 'code'))->pluck('id')->all());
        }
    }

    public function down(): void
    {
        Permission::whereIn('code', [
            'it_tools.view','it_tools.audit','it_tools.domain','it_tools.dns',
            'it_tools.ssl','it_tools.website','it_tools.email','it_tools.ip',
        ])->delete();
    }
};
