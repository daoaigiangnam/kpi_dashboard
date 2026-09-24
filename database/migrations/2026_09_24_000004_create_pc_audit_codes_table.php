<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pc_audit_codes')) {
            return;
        }

        Schema::create('pc_audit_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('customer_branches')->cascadeOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audit_codes');
    }
};
