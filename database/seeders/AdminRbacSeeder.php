<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminRbacSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['Admin','Access Admin','admin.view'],
            ['Users','View Users','users.view'],
            ['Users','Create Users','users.create'],
            ['Users','Edit Users','users.edit'],
            ['Users','Delete Users','users.delete'],
            ['User Groups','View Groups','groups.view'],
            ['User Groups','Create Groups','groups.create'],
            ['User Groups','Edit Groups','groups.edit'],
            ['User Groups','Delete Groups','groups.delete'],
            ['User Groups','Manage Permissions','groups.permissions'],
            ['Job Titles','View Job Titles','job_titles.view'],
            ['Job Titles','Create Job Titles','job_titles.create'],
            ['Job Titles','Edit Job Titles','job_titles.edit'],
            ['Job Titles','Delete Job Titles','job_titles.delete'],
            ['Job Titles','Import Job Titles','job_titles.import'],
            ['Job Titles','Export Job Titles','job_titles.export'],
            ['KPI','View Dashboard','kpi.dashboard.view'],
            ['KPI','Import Ticket Data','kpi.import'],
            ['KPI','Manage Configuration','kpi.config'],
        ];

        foreach ($defs as [$module, $name, $code]) {
            Permission::updateOrCreate(['code' => $code], ['module' => $module, 'name' => $name]);
        }

        $all = Permission::pluck('id');
        $super = UserGroup::updateOrCreate(['name' => 'Super Admin'], ['description' => 'Full system administration', 'is_system' => true]);
        $admin = UserGroup::updateOrCreate(['name' => 'KPI Admin'], ['description' => 'KPI administration', 'is_system' => true]);
        $viewer = UserGroup::updateOrCreate(['name' => 'KPI Viewer'], ['description' => 'Read-only KPI access', 'is_system' => true]);

        $super->permissions()->sync($all);
        $admin->permissions()->sync(Permission::whereIn('code', [
            'admin.view','users.view','users.create','users.edit',
            'groups.view','groups.create','groups.edit','groups.permissions',
            'job_titles.view','job_titles.create','job_titles.edit',
            'job_titles.import','job_titles.export',
            'kpi.dashboard.view','kpi.import','kpi.config'
        ])->pluck('id'));
        $viewer->permissions()->sync(Permission::where('code', 'kpi.dashboard.view')->pluck('id'));

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'System Administrator', 'password' => 'ChangeMe123!', 'user_group_id' => $super->id, 'is_active' => true]
        );
    }
}
