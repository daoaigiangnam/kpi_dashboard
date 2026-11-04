<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // monitor_target, monitor_interval_seconds and monitor_timeout_seconds
        // are already created by 2026_09_21_000002_add_network_monitor_config_to_services.php.
        // This migration only adds the runtime monitoring state/metrics.
        Schema::table('services', function (Blueprint $t) {
            $t->string('monitor_status', 20)->nullable()->after('monitor_timeout_seconds');
            $t->decimal('monitor_last_latency_ms', 10, 1)->nullable()->after('monitor_status');
            $t->decimal('monitor_packet_loss_percent', 5, 2)->nullable()->after('monitor_last_latency_ms');
            $t->unsignedInteger('monitor_failure_count')->default(0)->after('monitor_packet_loss_percent');
            $t->timestamp('monitor_last_checked_at')->nullable()->after('monitor_failure_count');
            $t->timestamp('monitor_down_since')->nullable()->after('monitor_last_checked_at');
            $t->index(['monitor_check_method', 'monitor_status']);
            $t->index(['monitor_last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['monitor_check_method', 'monitor_status']);
            $t->dropIndex(['monitor_last_checked_at']);
            $t->dropColumn([
                'monitor_status', 'monitor_last_latency_ms', 'monitor_packet_loss_percent',
                'monitor_failure_count', 'monitor_last_checked_at', 'monitor_down_since',
            ]);
        });
    }
};
