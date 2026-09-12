<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_alert_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->foreignId('alert_policy_id')->nullable()->constrained('service_alert_policies')->nullOnDelete();
            $t->unsignedTinyInteger('alert_stage');
            $t->decimal('remaining_percent', 8, 2)->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('status', 20)->default('open');
            $t->timestamp('triggered_at');
            $t->timestamp('acknowledged_at')->nullable();
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['service_id', 'status']);
            $t->index(['alert_stage', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_alert_events');
    }
};
