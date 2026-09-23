<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_branches', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->constrained('service_customers')->cascadeOnDelete();
            $t->string('code', 80); $t->string('name', 200); $t->string('address', 500)->nullable();
            $t->string('contact_name', 150)->nullable(); $t->string('phone', 50)->nullable(); $t->string('email', 190)->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
            $t->unique(['customer_id', 'code']); $t->index(['customer_id', 'is_active']);
        });

        Schema::create('pc_audit_codes', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id')->constrained('customer_branches')->cascadeOnDelete();
            $t->string('code', 120)->unique(); $t->string('name', 255)->nullable(); $t->boolean('is_active')->default(true);
            $t->timestamps(); $t->softDeletes(); $t->index(['branch_id', 'is_active']);
        });

        Schema::create('pc_audits', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_code_id')->constrained('pc_audit_codes')->cascadeOnDelete();
            $t->string('department', 200)->nullable(); $t->string('employee_name', 200)->nullable();
            $t->string('employee_username', 190)->nullable(); $t->string('domain', 190)->nullable();
            $t->string('computer_name', 190)->nullable(); $t->string('manufacturer', 190)->nullable();
            $t->string('model', 255)->nullable(); $t->string('serial_number', 190)->nullable(); $t->string('asset_tag', 190)->nullable();
            $t->json('mainboard')->nullable(); $t->json('bios')->nullable(); $t->json('operating_system')->nullable();
            $t->json('windows_update')->nullable(); $t->string('last_boot', 100)->nullable(); $t->string('uptime', 100)->nullable();
            $t->json('tpm')->nullable(); $t->string('secure_boot', 50)->nullable(); $t->timestamp('collected_at')->nullable();
            $t->json('raw_payload')->nullable(); $t->timestamps();
            $t->index(['pc_audit_code_id', 'computer_name']); $t->index('serial_number'); $t->index('employee_name'); $t->index('department');
        });

        Schema::create('pc_audit_memory', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('capacity', 80)->nullable(); $t->string('speed', 80)->nullable(); $t->string('slot', 120)->nullable();
            $t->string('manufacturer', 190)->nullable(); $t->string('part_number', 190)->nullable(); $t->string('serial_number', 190)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_storage', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('drive', 50)->nullable(); $t->decimal('used_gb', 14, 2)->nullable(); $t->decimal('total_gb', 14, 2)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_monitors', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('manufacturer', 190)->nullable(); $t->string('model', 255)->nullable(); $t->string('serial_number', 190)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_gpu', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('name', 255)->nullable(); $t->string('vram', 100)->nullable(); $t->string('driver_version', 150)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_battery', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('name', 255)->nullable(); $t->string('status', 100)->nullable(); $t->string('charge_percent', 30)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_network', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('type', 30)->nullable(); $t->string('name', 255)->nullable(); $t->string('description', 255)->nullable();
            $t->string('ipv4', 190)->nullable(); $t->string('gateway', 190)->nullable(); $t->string('mac', 100)->nullable();
            $t->string('dns', 500)->nullable(); $t->string('dhcp', 190)->nullable(); $t->string('connection_status', 100)->nullable(); $t->string('link_speed', 100)->nullable(); $t->timestamps();
            $t->index(['pc_audit_id', 'type']); $t->index('ipv4'); $t->index('mac');
        });
        Schema::create('pc_audit_antivirus', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('display_name', 255)->nullable(); $t->string('status', 150)->nullable(); $t->string('executable_path', 500)->nullable(); $t->string('signature_version', 190)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_bitlocker', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('mount_point', 30)->nullable(); $t->string('protection_status', 100)->nullable(); $t->string('volume_status', 100)->nullable(); $t->decimal('encryption_percent', 6, 2)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_firewall', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('profile', 50)->nullable(); $t->boolean('enabled')->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_licenses', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('product_type', 30)->nullable(); $t->string('product_name', 255)->nullable(); $t->string('status', 150)->nullable(); $t->string('partial_product_key', 50)->nullable(); $t->timestamps();
        });
        Schema::create('pc_audit_software', function (Blueprint $t) {
            $t->id(); $t->foreignId('pc_audit_id')->constrained('pc_audits')->cascadeOnDelete();
            $t->string('name', 255)->nullable(); $t->string('version', 190)->nullable(); $t->string('publisher', 255)->nullable(); $t->string('install_date', 30)->nullable(); $t->decimal('estimated_size', 18, 2)->nullable(); $t->timestamps();
            $t->index('name'); $t->index('publisher');
        });
    }

    public function down(): void
    {
        foreach (['pc_audit_software','pc_audit_licenses','pc_audit_firewall','pc_audit_bitlocker','pc_audit_antivirus','pc_audit_network','pc_audit_battery','pc_audit_gpu','pc_audit_monitors','pc_audit_storage','pc_audit_memory','pc_audits','pc_audit_codes','customer_branches'] as $table) Schema::dropIfExists($table);
    }
};
