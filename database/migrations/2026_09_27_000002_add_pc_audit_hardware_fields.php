<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->json('mainboard')->nullable()->after('asset_tag');
            $table->json('bios')->nullable()->after('mainboard');
            $table->json('operating_system')->nullable()->after('bios');
            $table->json('windows_update')->nullable()->after('operating_system');
            $table->string('last_boot')->nullable()->after('windows_update');
            $table->decimal('uptime', 10, 2)->nullable()->after('last_boot');
            $table->json('tpm')->nullable()->after('uptime');
            $table->json('secure_boot')->nullable()->after('tpm');
        });
    }

    public function down(): void
    {
        Schema::table('pc_audits', function (Blueprint $table) {
            $table->dropColumn([
                'mainboard',
                'bios',
                'operating_system',
                'windows_update',
                'last_boot',
                'uptime',
                'tpm',
                'secure_boot',
            ]);
        });
    }
};
