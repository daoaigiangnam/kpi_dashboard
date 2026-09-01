<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class AdminRbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['module'=>'Admin','name'=>'Access Admin','code'=>'admin.access'],
            ['module'=>'Users','name'=>'View Users','code'=>'users.view'],
            ['module'=>'Users','name'=>'Create Users','code'=>'users.create'],
            ['module'=>'Users','name'=>'Update Users','code'=>'users.update'],
            ['module'=>'Users','name'=>'Disable Users','code'=>'users.disable'],
            ['module'=>'User Groups','name'=>'View Groups','code'=>'groups.view'],
            ['module'=>'User Groups','name'=>'Manage Permissions','code'=>'groups.permissions'],
            ['module'=>'KPI','name'=>'View Dashboard','code'=>'kpi.dashboard.view'],
            ['module'=>'KPI','name'=>'Import Ticket Data','code'=>'kpi.import'],
            ['module'=>'KPI','name'=>'Manage Configuration','code'=>'kpi.config'],
        ];

        foreach ($permissions as $item) {
            Permission::updateOrCreate(['code'=>$item['code']], $item);
        }

        $all = Permission::pluck('id');
        $super = UserGroup::updateOrCreate(['name'=>'Super Admin'], ['description'=>'Full administration access','is_system'=>true]);
        $kpi = UserGroup::updateOrCreate(['name'=>'KPI Admin'], ['description'=>'KPI administration access','is_system'=>false]);
        $viewer = UserGroup::updateOrCreate(['name'=>'KPI Viewer'], ['description'=>'Read-only KPI dashboard','is_system'=>false]);

        $super->permissions()->sync($all);
        $kpi->permissions()->sync(Permission::whereIn('code',['admin.access','users.view','kpi.dashboard.view','kpi.import','kpi.config'])->pluck('id'));
        $viewer->permissions()->sync(Permission::where('code','kpi.dashboard.view')->pluck('id'));
    }
}
