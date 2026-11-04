<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'name']);
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'name']);
        });

        if (!Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('department_id')->nullable()->after('join_date')->constrained('departments')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('users', 'unit_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('unit_id')->nullable()->after('department_id')->constrained('units')->nullOnDelete();
            });
        }

        $departmentNames = DB::table('users')->whereNotNull('department')->where('department', '<>', '')->distinct()->pluck('department');
        $nextDepartment = 1;
        foreach ($departmentNames as $name) {
            $code = 'DEPT-'.str_pad((string) $nextDepartment, 3, '0', STR_PAD_LEFT);
            while (DB::table('departments')->where('code', $code)->exists()) {
                $nextDepartment++;
                $code = 'DEPT-'.str_pad((string) $nextDepartment, 3, '0', STR_PAD_LEFT);
            }
            $id = DB::table('departments')->insertGetId(['code'=>$code,'name'=>$name,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('users')->where('department', $name)->update(['department_id'=>$id]);
            $nextDepartment++;
        }

        $unitNames = DB::table('users')->whereNotNull('location')->where('location', '<>', '')->distinct()->pluck('location');
        $nextUnit = 1;
        foreach ($unitNames as $name) {
            $code = 'UNIT-'.str_pad((string) $nextUnit, 3, '0', STR_PAD_LEFT);
            while (DB::table('units')->where('code', $code)->exists()) {
                $nextUnit++;
                $code = 'UNIT-'.str_pad((string) $nextUnit, 3, '0', STR_PAD_LEFT);
            }
            $id = DB::table('units')->insertGetId(['code'=>$code,'name'=>$name,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('users')->where('location', $name)->update(['unit_id'=>$id]);
            $nextUnit++;
        }

        foreach (['users', 'user_groups', 'job_titles'] as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'user_groups', 'job_titles'] as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
            }
        }
        if (Schema::hasColumn('users', 'unit_id')) {
            Schema::table('users', function (Blueprint $t) { $t->dropForeign(['unit_id']); $t->dropColumn('unit_id'); });
        }
        if (Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $t) { $t->dropForeign(['department_id']); $t->dropColumn('department_id'); });
        }
        Schema::dropIfExists('units');
        Schema::dropIfExists('departments');
    }
};
