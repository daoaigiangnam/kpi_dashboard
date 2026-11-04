<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->boolean('secure_boot')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->json('secure_boot')->nullable()->change();
        });
    }
};
