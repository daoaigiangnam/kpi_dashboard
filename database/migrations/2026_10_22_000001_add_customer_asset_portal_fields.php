<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 20)->default('internal')->after('email')->index();
            $table->foreignId('service_customer_id')->nullable()->after('account_type')->constrained('service_customers')->nullOnDelete();
            $table->boolean('can_view_assets')->default(false)->after('service_customer_id');
            $table->boolean('can_view_audit')->default(false)->after('can_view_assets');
            $table->boolean('can_download_qr')->default(false)->after('can_view_audit');
            $table->index(['account_type', 'service_customer_id']);
        });

        Schema::table('pc_audits', function (Blueprint $table): void {
            $table->string('asset_lookup_token', 64)->nullable()->unique()->after('asset_tag');
        });

        // Existing records get random opaque lookup tokens; QR labels must never use sequential IDs.
        \Illuminate\Support\Facades\DB::table('pc_audits')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($audits): void {
                foreach ($audits as $audit) {
                    \Illuminate\Support\Facades\DB::table('pc_audits')
                        ->where('id', $audit->id)
                        ->whereNull('asset_lookup_token')
                        ->update(['asset_lookup_token' => bin2hex(random_bytes(24))]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pc_audits', function (Blueprint $table): void {
            $table->dropUnique(['asset_lookup_token']);
            $table->dropColumn('asset_lookup_token');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_type', 'service_customer_id']);
            $table->dropConstrainedForeignId('service_customer_id');
            $table->dropColumn(['account_type', 'can_view_assets', 'can_view_audit', 'can_download_qr']);
        });
    }
};
