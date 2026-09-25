<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->boolean('ssl_detected')->default(false)->after('monitor_down_since');
            $t->date('ssl_valid_from_date')->nullable()->after('ssl_detected');
            $t->date('ssl_expiry_date')->nullable()->after('ssl_valid_from_date');
            $t->string('ssl_issuer', 255)->nullable()->after('ssl_expiry_date');
            $t->string('ssl_status', 20)->nullable()->after('ssl_issuer');
            $t->timestamp('ssl_last_checked_at')->nullable()->after('ssl_status');
            $t->unsignedTinyInteger('ssl_alert_stage')->default(0)->after('ssl_last_checked_at');
            $t->timestamp('ssl_last_alert_at')->nullable()->after('ssl_alert_stage');
            $t->index(['ssl_detected', 'ssl_expiry_date']);
        });

        Schema::table('service_alert_events', function (Blueprint $t) {
            $t->string('alert_type', 30)->default('service_expiry')->after('service_id');
            $t->index(['service_id', 'alert_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('service_alert_events', function (Blueprint $t) {
            $t->dropIndex(['service_id', 'alert_type', 'status']);
            $t->dropColumn('alert_type');
        });

        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['ssl_detected', 'ssl_expiry_date']);
            $t->dropColumn([
                'ssl_detected', 'ssl_valid_from_date', 'ssl_expiry_date', 'ssl_issuer', 'ssl_status',
                'ssl_last_checked_at', 'ssl_alert_stage', 'ssl_last_alert_at',
            ]);
        });
    }
};
