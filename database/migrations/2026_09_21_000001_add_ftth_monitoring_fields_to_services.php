<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->string('monitor_check_method', 20)->nullable()->after('alert_policy_id');
            $t->unsignedSmallInteger('monitor_port')->nullable()->after('monitor_check_method');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->dropColumn(['monitor_check_method', 'monitor_port']);
        });
    }
};
