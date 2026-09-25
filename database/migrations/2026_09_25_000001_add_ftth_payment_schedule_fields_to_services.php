<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_due_day')->nullable()->after('cost_billing_cycle');
            $table->unsignedTinyInteger('payment_alert_percent')->nullable()->default(20)->after('payment_due_day');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['payment_due_day', 'payment_alert_percent']);
        });
    }
};
