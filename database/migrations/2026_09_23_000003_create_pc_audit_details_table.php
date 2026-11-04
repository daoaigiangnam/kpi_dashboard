<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pc_audit_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pc_audit_id')->unique()->constrained()->cascadeOnDelete();

            // Keep the complete collector output so no field is lost when the
            // PowerShell collector evolves. Repeating data is also preserved here.
            $table->json('hardware')->nullable();
            $table->json('cpu')->nullable();
            $table->json('memory')->nullable();
            $table->json('storage')->nullable();
            $table->json('monitors')->nullable();
            $table->json('gpu')->nullable();
            $table->json('battery')->nullable();
            $table->json('windows')->nullable();
            $table->json('network')->nullable();
            $table->json('security')->nullable();
            $table->json('licenses')->nullable();
            $table->json('software')->nullable();
            $table->json('other')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_audit_details');
    }
};
