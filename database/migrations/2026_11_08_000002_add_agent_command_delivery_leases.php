<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_commands', function (Blueprint $table) {
            $table->timestamp('lease_expires_at')->nullable()->after('delivered_at')->index();
            $table->unsignedInteger('delivery_attempts')->default(0)->after('lease_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('agent_commands', function (Blueprint $table) {
            $table->dropIndex(['lease_expires_at']);
            $table->dropColumn(['lease_expires_at', 'delivery_attempts']);
        });
    }
};
