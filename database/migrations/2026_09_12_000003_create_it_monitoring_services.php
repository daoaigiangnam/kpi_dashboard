<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('contact_name', 150)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('phone', 50)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'name']);
        });

        Schema::create('service_providers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('website', 255)->nullable();
            $t->string('support_contact', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'name']);
        });

        Schema::create('service_alert_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_type_id')->constrained('service_types')->restrictOnDelete();
            $t->string('name', 150);
            $t->decimal('alert_1_percent', 5, 2);
            $t->decimal('alert_2_percent', 5, 2);
            $t->decimal('alert_3_percent', 5, 2);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['service_type_id', 'name']);
            $t->index(['service_type_id', 'is_active']);
        });

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

        Schema::create('service_alert_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->foreignId('alert_policy_id')->nullable()->constrained('service_alert_policies')->nullOnDelete();
            $t->unsignedTinyInteger('alert_stage');
            $t->decimal('remaining_percent', 6, 2);
            $t->date('expiry_date');
            $t->string('status', 30)->default('open');
            $t->timestamp('triggered_at');
            $t->timestamp('acknowledged_at')->nullable();
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['status', 'triggered_at']);
            $t->index(['service_id', 'alert_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_alert_events');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_alert_policies');
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('service_customers');
    }
};
