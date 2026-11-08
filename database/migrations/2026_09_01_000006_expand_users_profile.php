<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'employee_code' => fn (Blueprint $t) => $t->string('employee_code', 50)->nullable()->after('id'),
            'phone' => fn (Blueprint $t) => $t->string('phone', 30)->nullable()->after('email'),
            'date_of_birth' => fn (Blueprint $t) => $t->date('date_of_birth')->nullable()->after('phone'),
            'gender' => fn (Blueprint $t) => $t->string('gender', 20)->nullable()->after('date_of_birth'),
            'join_date' => fn (Blueprint $t) => $t->date('join_date')->nullable()->after('gender'),
            'department' => fn (Blueprint $t) => $t->string('department', 150)->nullable()->after('join_date'),
            'location' => fn (Blueprint $t) => $t->string('location', 150)->nullable()->after('department'),
            'notes' => fn (Blueprint $t) => $t->string('notes', 500)->nullable()->after('location'),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }

        if (!Schema::hasIndex('users', 'users_employee_code_unique', 'unique')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unique('employee_code');
            });
        }

        if (!Schema::hasIndex('users', 'users_department_is_active_index', 'index')) {
            Schema::table('users', function (Blueprint $t) {
                $t->index(['department', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['department', 'is_active']);
            $t->dropUnique(['employee_code']);
            $t->dropColumn([
                'employee_code',
                'phone',
                'date_of_birth',
                'gender',
                'join_date',
                'department',
                'location',
                'notes',
            ]);
        });
    }
};
