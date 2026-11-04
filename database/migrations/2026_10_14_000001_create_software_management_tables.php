<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('software_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 40)->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('hod_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_it_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department', 150)->nullable();
            $table->string('device_name', 180);
            $table->string('asset_code', 100)->nullable();
            $table->string('software_name', 180);
            $table->string('vendor', 180)->nullable();
            $table->string('version', 100)->nullable();
            $table->text('business_purpose');
            $table->string('request_type', 30);
            $table->string('license_path', 30)->nullable();
            $table->string('permission_type', 30);
            $table->text('data_access')->nullable();
            $table->dateTime('needed_at')->nullable();
            $table->date('usage_until')->nullable();
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->string('budget_source')->nullable();
            $table->string('branch_type', 20);
            $table->string('status', 40)->default('pending_hod');
            $table->string('hod_decision', 30)->default('pending');
            $table->text('hod_comment')->nullable();
            $table->string('it_conclusion', 30)->nullable();
            $table->text('it_assessment')->nullable();
            $table->string('installation_result', 30)->nullable();
            $table->dateTime('installed_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'branch_type']);
            $table->index(['software_name', 'device_name']);
        });

        Schema::create('software_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('software_requests')->cascadeOnDelete();
            $table->string('stage', 40);
            $table->string('decision', 30)->default('pending');
            $table->text('comment')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['stage', 'decision']);
        });

        Schema::create('software_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('software_requests')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('users');
            $table->text('source_url')->nullable();
            $table->string('compatibility_result', 30);
            $table->boolean('admin_rights_required')->default(false);
            $table->text('data_access_scope')->nullable();
            $table->string('security_risk', 30);
            $table->string('license_type', 150)->nullable();
            $table->unsignedInteger('license_quantity')->nullable();
            $table->date('license_until')->nullable();
            $table->text('license_evidence')->nullable();
            $table->string('it_conclusion', 30);
            $table->text('conditions')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('assessed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('software_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('software_requests')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('result', 30);
            $table->string('functionality_check', 20);
            $table->string('security_check', 20);
            $table->string('readonly_check', 20)->default('na');
            $table->text('issues')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->string('user_acceptance', 30)->nullable();
            $table->foreignId('user_acceptance_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('user_accepted_at')->nullable();
            $table->dateTime('it_confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('software_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('software_requests')->cascadeOnDelete();
            $table->string('item_key', 60);
            $table->string('item_label', 255);
            $table->boolean('passed')->default(false);
            $table->text('evidence')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['software_request_id', 'item_key']);
        });

        Schema::create('software_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('software_requests')->cascadeOnDelete();
            $table->text('description');
            $table->text('business_reason');
            $table->text('risk_impact');
            $table->text('scope');
            $table->text('mitigation');
            $table->text('rollback_plan')->nullable();
            $table->date('expires_at');
            $table->foreignId('owner_id')->constrained('users');
            $table->text('it_opinion');
            $table->string('approval_status', 30)->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('closure_result')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['approval_status', 'expires_at']);
        });

        Schema::create('software_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('software_requests')->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('software_name', 180);
            $table->string('vendor', 180)->nullable();
            $table->string('version', 100)->nullable();
            $table->string('license_type', 150);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('assigned_user', 180)->nullable();
            $table->string('device_name', 180)->nullable();
            $table->string('asset_code', 100)->nullable();
            $table->date('installed_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('ticket_code', 40)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('evidence_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['expires_at', 'status']);
            $table->index(['software_name', 'vendor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_licenses');
        Schema::dropIfExists('software_attachments');
        Schema::dropIfExists('software_exceptions');
        Schema::dropIfExists('software_checklists');
        Schema::dropIfExists('software_installations');
        Schema::dropIfExists('software_assessments');
        Schema::dropIfExists('software_approvals');
        Schema::dropIfExists('software_requests');
    }
};