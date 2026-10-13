<?php

use App\\Models\\Permission;
use App\\Models\\UserGroup;
use Illuminate\\Database\\Migrations\\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defs = [
            ['Mail Backup', 'View Mail Backup Manager', 'mail_backup.view'],
            ['Mail Backup', 'Manage Mail Backup batches and retry', 'mail_backup.manage'],
            ['Mail Backup', 'Manage dynamic Email Admin settings', 'mail_backup.settings'],
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
        $codes = ['mail_backup.view', 'mail_backup.manage', 'mail_backup.settings'];
        $ids = Permission::whereIn('code', $codes)->pluck('id')->all();
        if ($ids) {
            \Illuminate\Support\Facades\DB::table('group_permissions')->whereIn('permission_id', $ids)->delete();
        }
        Permission::whereIn('code', $codes)->delete();
    }
};
