<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class SoftwareManagementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['Software Management','View software requests','software.requests.view'],
            ['Software Management','Create software requests','software.requests.create'],
            ['Software Management','Approve software request','software.approve'],
            ['Software Management','Assess software request','software.assess'],
            ['Software Management','Approve software purchase','software.purchase_approve'],
            ['Software Management','Record installation','software.install'],
            ['Software Management','Close software request','software.close'],
            ['Software Management','Create exception request','software.exception.create'],
            ['Software Management','Approve exception request','software.exception.approve'],
            ['Software Management','View software licenses','software.licenses.view'],
            ['Software Management','Manage software licenses','software.licenses.manage'],
            ['Software Management','View/download software evidence','software.attachments.view'],
            ['Software Management','Upload software evidence','software.attachments.manage'],
        ];
        foreach ($defs as [$module, $name, $code]) {
            Permission::updateOrCreate(['code' => $code], ['module' => $module, 'name' => $name]);
        }
        $codes = array_column($defs, 2);
        $all = Permission::whereIn('code', $codes)->pluck('id');
        $super = UserGroup::where('name', 'Super Admin')->first();
        if ($super) $super->permissions()->syncWithoutDetaching($all);
        $admin = UserGroup::where('name', 'KPI Admin')->first();
        if ($admin) $admin->permissions()->syncWithoutDetaching($all);
    }
}