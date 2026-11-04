<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->string('audit_status', 20)->default('REVIEW')->after('collected_at')->index();
            $table->unsignedTinyInteger('audit_score')->nullable()->after('audit_status');
            $table->json('audit_results')->nullable()->after('audit_score');
            $table->string('audit_engine_version', 30)->nullable()->after('audit_results');
        });
    }

    public function down(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->dropColumn(['audit_status','audit_score','audit_results','audit_engine_version']);
        });
    }
};
