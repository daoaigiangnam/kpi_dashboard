<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_access_all_branches')->default(false)->after('can_download_qr');
        });

        Schema::create('customer_user_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_branch_id')->constrained('customer_branches')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'customer_branch_id'], 'customer_user_branch_unique');
            $table->index('customer_branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_branches');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_access_all_branches');
        });
    }
};
