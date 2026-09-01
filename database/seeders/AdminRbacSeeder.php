<?php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminRbacSeeder
{
    public function run(): void
    {
        $defs = [
            ['Admin','Access Admin','admin.view'], ['Admin','System Settings','system.settings'],
            ['Users','View Users','users.view'], ['Users','Create Users','users.create'], ['Users','Edit Users','users.edit'], ['Users','Delete/Restore Users','users.delete'], ['Users','Import Users','users.import'], ['Users','Export Users','users.export'],
            ['User Groups','View Groups','groups.view'], ['User Groups','Create Groups','groups.create'], ['User Groups','Edit Groups','groups.edit'], ['User Groups','Hide/Restore Groups','groups.delete'], ['User Groups','Manage Permissions','groups.permissions'],
            ['Job Titles','View Job Titles','job_titles.view'], ['Job Titles','Create Job Titles','job_titles.create'], ['Job Titles','Edit Job Titles','job_titles.edit'], ['Job Titles','Hide/Restore Job Titles','job_titles.delete'], ['Job Titles','Import Job Titles','job_titles.import'], ['Job Titles','Export Job Titles','job_titles.export'],
            ['Departments','View Departments','departments.view'], ['Departments','Create Departments','departments.create'], ['Departments','Edit Departments','departments.edit'], ['Departments','Hide/Restore Departments','departments.delete'],
            ['Units','View Units','units.view'], ['Units','Create Units','units.create'], ['Units','Edit Units','units.edit'], ['Units','Delete/Restore Units','units.delete'],
            ['KPI','View Dashboard','kpi.dashboard.view'], ['KPI','Import Ticket Data','kpi.import'], ['KPI','Manage Configuration','kpi.config'],
        ];
        foreach ($defs as [$module,$name,$code]) Permission::updateOrCreate(['code'=>$code],['module'=>$module,'name'=>$name]);
        $all=Permission::pluck('id');
        $super=UserGroup::updateOrCreate(['name'=>'Super Admin'],['description'=>'Full system administration','is_system'=>true]);
        $admin=UserGroup::updateOrCreate(['name'=>'KPI Admin'],['description'=>'KPI administration','is_system'=>true]);
        $viewer=UserGroup::updateOrCreate(['name'=>'KPI Viewer'],['description'=>'Read-only KPI access','is_system'=>true]);
        $super->permissions()->sync($all);
        $adminCodes=['admin.view','system.settings','users.view','users.create','users.edit','users.delete','users.import','users.export','groups.view','groups.create','groups.edit','groups.delete','groups.permissions','job_titles.view','job_titles.create','job_titles.edit','job_titles.delete','job_titles.import','job_titles.export','departments.view','departments.create','departments.edit','departments.delete','units.view','units.create','units.edit','units.delete','kpi.dashboard.view','kpi.import','kpi.config'];
        $admin->permissions()->sync(Permission::whereIn('code',$adminCodes)->pluck('id'));
        $viewer->permissions()->sync(Permission::where('code','kpi.dashboard.view')->pluck('id'));
        User::updateOrCreate(['email'=>'admin@example.com'],['name'=>'System Administrator','password'=>'ChangeMe123!','user_group_id'=>$super->id,'is_active'=>true]);
    }
}
