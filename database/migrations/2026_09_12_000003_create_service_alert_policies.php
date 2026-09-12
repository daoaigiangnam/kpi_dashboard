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
            $t->unique(['service_type_id', 'name']);
            $t->index(['service_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_alert_policies');
    }
};
