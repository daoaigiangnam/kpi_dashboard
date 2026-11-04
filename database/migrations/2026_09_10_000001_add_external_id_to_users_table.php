<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'external_id')) {
                $table->string('external_id', 100)->nullable()->after('employee_code');
                $table->index('external_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'external_id')) {
                $table->dropIndex(['external_id']);
                $table->dropColumn('external_id');
            }
        });
    }
};
