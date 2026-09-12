<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_alert_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
            $t->string('name', 150);
            $t->decimal('alert_1_percent', 5, 2);
            $t->decimal('alert_2_percent', 5, 2);
            $t->decimal('alert_3_percent', 5, 2);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['service_type_id', 'is_active']);
        });

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
            $t->string('support_contact', 190)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('service_customers');
        Schema::dropIfExists('service_alert_policies');
    }
};
