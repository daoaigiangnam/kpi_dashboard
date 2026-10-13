<?php

use App\Models\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['Mail Backup', 'View Mail Backup Manager', 'mail_backup.view'],
            ['Mail Backup', 'Manage Mail Backup batches and retry', 'mail_backup.manage'],
            ['Mail Backup', 'Manage dynamic Email Admin settings', 'mail_backup.settings'],
        ];

        foreach ($definitions as [$module, $name, $code]) {
            $permission = Permission::updateOrCreate(
                ['code' => $code],
                ['module' => $module, 'name' => $name]
            );

            foreach (['Super Admin', 'KPI Admin'] as $groupName) {
                $group = UserGroup::where('name', $groupName)->first();
                if ($group) {
                    $group->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }
    }

    public function down(): void
    {
        $codes = ['mail_backup.view', 'mail_backup.manage', 'mail_backup.settings'];
        $ids = Permission::whereIn('code', $codes)->pluck('id')->all();
        if ($ids) {
            \Illuminate\Support\Facades\DB::table('group_permissions')->whereIn('permission_id', $ids)->delete();
        }
        Permission::whereIn('code', $codes)->delete();
    }
};
