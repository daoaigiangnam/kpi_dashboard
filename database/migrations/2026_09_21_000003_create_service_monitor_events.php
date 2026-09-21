<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_monitor_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->string('event_type', 20); // down / recovered
            $t->string('check_method', 20);
            $t->string('target', 255);
            $t->unsignedSmallInteger('port')->nullable();
            $t->string('status', 20); // open / resolved
            $t->decimal('latency_ms', 10, 1)->nullable();
            $t->decimal('packet_loss_percent', 5, 2)->nullable();
            $t->timestamp('started_at');
            $t->timestamp('resolved_at')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->index(['service_id', 'status']);
            $t->index(['event_type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_monitor_events');
    }
};
