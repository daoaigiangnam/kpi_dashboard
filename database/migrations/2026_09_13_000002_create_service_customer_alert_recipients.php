<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_customer_alert_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('service_customers')->cascadeOnDelete();
            $t->unsignedTinyInteger('level');
            $t->string('recipient_name', 150)->nullable();
            $t->string('recipient_email', 190)->nullable();
            $t->string('recipient_phone', 50)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['customer_id', 'level']);
            $t->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_customer_alert_recipients');
    }
};
