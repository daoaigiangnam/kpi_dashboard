<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pc_audit_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('customer_branches')->restrictOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audit_codes');
    }
};
