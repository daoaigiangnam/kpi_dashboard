<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pc_audit_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_base_url', 500);
            $table->string('tool_version', 50)->default('1.0.0');
            $table->string('minimum_tool_version', 50)->default('1.0.0');
            $table->boolean('enabled')->default(true);
            $table->string('download_url', 1000)->nullable();
            $table->text('disabled_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audit_settings');
    }
};
