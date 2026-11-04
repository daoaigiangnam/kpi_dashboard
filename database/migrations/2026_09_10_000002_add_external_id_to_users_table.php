<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'external_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('external_id', 100)->nullable()->after('employee_code');
                $table->index('external_id', 'users_external_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'external_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_external_id_index');
                $table->dropColumn('external_id');
            });
        }
    }
};
