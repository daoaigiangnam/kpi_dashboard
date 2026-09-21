<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->string('monitor_target', 255)->nullable()->after('monitor_check_method');
            $t->unsignedSmallInteger('monitor_interval_seconds')->default(60)->after('monitor_port');
            $t->unsignedSmallInteger('monitor_timeout_seconds')->default(5)->after('monitor_interval_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->dropColumn(['monitor_target', 'monitor_interval_seconds', 'monitor_timeout_seconds']);
        });
    }
};
