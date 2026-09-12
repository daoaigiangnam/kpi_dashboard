<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('service_customers')->restrictOnDelete();
            $t->foreignId('service_type_id')->constrained('service_types')->restrictOnDelete();
            $t->foreignId('provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $t->string('service_name', 200);
            $t->string('value', 500)->nullable();
            $t->unsignedSmallInteger('service_term_months')->nullable();
            $t->date('expiry_date')->nullable();
            $t->foreignId('alert_policy_id')->nullable()->constrained('service_alert_policies')->nullOnDelete();
            $t->foreignId('responsible_it_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 30)->default('active');
            $t->boolean('auto_renew')->default(false);
            $t->unsignedTinyInteger('alert_stage')->default(0);
            $t->timestamp('last_alert_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['customer_id', 'status']);
            $t->index(['service_type_id', 'expiry_date']);
            $t->index(['alert_stage', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
