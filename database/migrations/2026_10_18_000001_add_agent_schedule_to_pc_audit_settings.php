<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pc_audit_settings', function (Blueprint $table) {
            $table->boolean('agent_schedule_enabled')->default(true);
            $table->unsignedInteger('agent_interval_minutes')->default(360);
            $table->boolean('agent_run_on_start')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('pc_audit_settings', function (Blueprint $table) {
            $table->dropColumn(['agent_schedule_enabled', 'agent_interval_minutes', 'agent_run_on_start']);
        });
    }
};
