<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_backup_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 50)->unique();
            $table->string('name', 190);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('mail_scope', 30)->default('all');
            $table->string('status', 30)->default('PENDING')->index();
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('pending_jobs')->default(0);
            $table->unsignedInteger('running_jobs')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->unsignedInteger('failed_jobs')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('mail_backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('mail_backup_batches')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('display_name', 190)->nullable();
            $table->string('status', 30)->default('PENDING')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedBigInteger('processed_messages')->default(0);
            $table->unsignedBigInteger('expected_messages')->nullable();
            $table->string('pst_path', 1024)->nullable();
            $table->unsignedBigInteger('pst_size')->nullable();
            $table->text('last_error')->nullable();
            $table->string('worker_id', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'email']);
            $table->index(['batch_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('mail_backup_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('mail_backup_jobs')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no')->default(0);
            $table->string('level', 20)->default('INFO');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['job_id', 'created_at']);
        });

        Schema::create('mail_backup_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('result', 20)->default('SUCCESS');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::create('mail_backup_setting_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('label', 160);
            $table->string('group', 80)->default('Email Admin');
            $table->string('type', 30)->default('text');
            $table->longText('value')->nullable();
            $table->text('description')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['group', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_backup_setting_definitions');
        Schema::dropIfExists('mail_backup_audit_logs');
        Schema::dropIfExists('mail_backup_job_logs');
        Schema::dropIfExists('mail_backup_jobs');
        Schema::dropIfExists('mail_backup_batches');
    }
};
