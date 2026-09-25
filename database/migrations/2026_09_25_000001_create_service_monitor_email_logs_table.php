<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_monitor_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_monitor_event_id')->constrained('service_monitor_events')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('recipient_type', 50);
            $table->string('recipient_email');
            $table->string('email_type', 30);
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('failed');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['service_monitor_event_id', 'level', 'recipient_email', 'email_type'], 'service_monitor_email_unique');
            $table->index(['service_monitor_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_monitor_email_logs');
    }
};
