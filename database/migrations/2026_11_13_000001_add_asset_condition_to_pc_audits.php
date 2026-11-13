<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pc_audits', function (Blueprint $table): void {
            $table->string('asset_condition', 30)
                ->default('Đang sử dụng')
                ->after('asset_tag')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('pc_audits', function (Blueprint $table): void {
            $table->dropIndex(['asset_condition']);
            $table->dropColumn('asset_condition');
        });
    }
};
