<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('employee_code', 50)->nullable()->unique()->after('id');
            $t->string('phone', 30)->nullable()->after('email');
            $t->date('date_of_birth')->nullable()->after('phone');
            $t->string('gender', 20)->nullable()->after('date_of_birth');
            $t->date('join_date')->nullable()->after('gender');
            $t->string('department', 150)->nullable()->after('join_date');
            $t->string('location', 150)->nullable()->after('department');
            $t->string('notes', 500)->nullable()->after('location');
            $t->index(['department', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['department', 'is_active']);
            $t->dropUnique(['employee_code']);
            $t->dropColumn(['employee_code','phone','date_of_birth','gender','join_date','department','location','notes']);
        });
    }
};
