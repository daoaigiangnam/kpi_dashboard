<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_alert_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_alert_event_id')->constrained('service_alert_events')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('recipient_type', 30);
            $table->string('recipient_email', 255);
            $table->string('email_type', 30);
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('sent');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['service_alert_event_id', 'level', 'recipient_email', 'email_type'], 'service_alert_email_unique');
            $table->index(['service_alert_event_id', 'email_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_alert_email_logs');
    }
};
