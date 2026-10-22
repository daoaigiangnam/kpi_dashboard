<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_create_assets')->default(false)->after('can_view_assets');
            $table->boolean('can_edit_assets')->default(false)->after('can_create_assets');
            $table->boolean('can_delete_assets')->default(false)->after('can_edit_assets');
            $table->boolean('can_export_assets')->default(false)->after('can_delete_assets');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'can_create_assets',
                'can_edit_assets',
                'can_delete_assets',
                'can_export_assets',
            ]);
        });
    }
};
