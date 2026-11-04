<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->string('employee_mobile', 50)->nullable()->after('employee_name');
            $table->string('employee_email', 190)->nullable()->after('employee_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->dropColumn(['employee_mobile', 'employee_email']);
        });
    }
};
