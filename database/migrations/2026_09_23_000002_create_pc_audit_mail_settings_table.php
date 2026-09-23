<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pc_audit_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('smtp_host', 255);
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 20)->nullable();
            $table->string('smtp_username', 255)->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('from_email', 255);
            $table->string('from_name', 255)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audit_mail_settings');
    }
};
