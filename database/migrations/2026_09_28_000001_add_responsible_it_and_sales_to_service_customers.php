<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_customers', function (Blueprint $table): void {
            $table->foreignId('responsible_it_id')
                ->nullable()
                ->after('phone')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('sales_contact_id')
                ->nullable()
                ->after('responsible_it_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('responsible_it_id');
            $table->index('sales_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_customers', function (Blueprint $table): void {
            $table->dropForeign(['responsible_it_id']);
            $table->dropForeign(['sales_contact_id']);
            $table->dropIndex(['responsible_it_id']);
            $table->dropIndex(['sales_contact_id']);
            $table->dropColumn(['responsible_it_id', 'sales_contact_id']);
        });
    }
};
