<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('external_ticket_id', 100)->unique();
            $table->string('priority', 20)->index();
            $table->dateTime('created_on')->nullable()->index();
            $table->dateTime('started_on')->nullable();
            $table->dateTime('finished_on')->nullable();
            $table->unsignedInteger('pause_minutes')->default(0);
            $table->unsignedInteger('reopen_count')->default(0);
            $table->string('company_department', 255)->nullable()->index();
            $table->text('resolution_detail')->nullable();
            $table->text('result_screenshot')->nullable();
            $table->decimal('workload_point', 10, 2)->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->unsignedInteger('sla_target_minutes')->nullable();
            $table->string('sla_status', 30)->nullable();
            $table->string('process_status', 30)->nullable();
            $table->string('started_status', 20)->nullable();
            $table->string('source', 50)->default('bitrix_excel');
            $table->string('source_file', 255)->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
