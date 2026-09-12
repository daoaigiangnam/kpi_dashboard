<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'name']);
        });

        Schema::create('service_type_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
            $t->unsignedSmallInteger('months');
            $t->timestamps();
            $t->unique(['service_type_id', 'months']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_terms');
        Schema::dropIfExists('service_types');
    }
};
