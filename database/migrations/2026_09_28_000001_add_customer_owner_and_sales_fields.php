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
            if (!Schema::hasColumn('service_customers', 'responsible_it_id')) {
                $table->unsignedBigInteger('responsible_it_id')->nullable()->after('phone');
                $table->index('responsible_it_id');
                $table->foreign('responsible_it_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('service_customers', 'sales_name')) {
                $table->string('sales_name', 150)->nullable()->after('responsible_it_id');
            }

            if (!Schema::hasColumn('service_customers', 'sales_email')) {
                $table->string('sales_email', 190)->nullable()->after('sales_name');
            }

            if (!Schema::hasColumn('service_customers', 'sales_active')) {
                $table->boolean('sales_active')->default(false)->after('sales_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_customers', function (Blueprint $table): void {
            if (Schema::hasColumn('service_customers', 'responsible_it_id')) {
                $table->dropForeign(['responsible_it_id']);
                $table->dropIndex(['responsible_it_id']);
                $table->dropColumn('responsible_it_id');
            }

            if (Schema::hasColumn('service_customers', 'sales_active')) {
                $table->dropColumn('sales_active');
            }

            if (Schema::hasColumn('service_customers', 'sales_email')) {
                $table->dropColumn('sales_email');
            }

            if (Schema::hasColumn('service_customers', 'sales_name')) {
                $table->dropColumn('sales_name');
            }
        });
    }
};
