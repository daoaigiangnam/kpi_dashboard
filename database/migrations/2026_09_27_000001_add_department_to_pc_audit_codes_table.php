<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pc_audit_codes') || Schema::hasColumn('pc_audit_codes', 'department')) {
            return;
        }

        Schema::table('pc_audit_codes', function (Blueprint $table) {
            $table->string('department', 200)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pc_audit_codes') && Schema::hasColumn('pc_audit_codes', 'department')) {
            Schema::table('pc_audit_codes', function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }
    }
};
