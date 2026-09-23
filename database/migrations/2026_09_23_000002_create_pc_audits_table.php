<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pc_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pc_audit_code_id')->constrained()->restrictOnDelete();
            $table->string('department')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_username')->nullable();
            $table->string('domain')->nullable();
            $table->string('computer_name')->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->string('asset_tag')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['pc_audit_code_id', 'department']);
            $table->index(['pc_audit_code_id', 'employee_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audits');
    }
};
