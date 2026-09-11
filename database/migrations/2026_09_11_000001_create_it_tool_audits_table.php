<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('it_tool_audits')) {
            return;
        }

        Schema::create('it_tool_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 253)->index();
            $table->ipAddress('wan_ip')->nullable()->index();
            $table->string('status', 32)->default('completed')->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_tool_audits');
    }
};
