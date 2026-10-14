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

        $allCodes = array_column($defs, 2);
        $superAdmin = UserGroup::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching(Permission::whereIn('code', $allCodes)->pluck('id'));
        }

        $kpiAdmin = UserGroup::where('name', 'KPI Admin')->first();
        if ($kpiAdmin) {
            $kpiAdmin->permissions()->syncWithoutDetaching(Permission::whereIn('code', [
                'software.requests.view','software.requests.create','software.approve','software.assess',
                'software.purchase_approve','software.install','software.close','software.exception.create',
                'software.exception.approve','software.licenses.view','software.licenses.manage',
                'software.attachments.view','software.attachments.manage'
            ])->pluck('id'));
        }
    }
}