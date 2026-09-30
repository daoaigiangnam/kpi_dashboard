<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_customers')) {
            return;
        }

        if (!Schema::hasColumn('service_customers', 'responsible_it_id')) {
            Schema::table('service_customers', function (Blueprint $table): void {
                $table->unsignedBigInteger('responsible_it_id')->nullable()->after('phone');
            });
        }

        if (!Schema::hasColumn('service_customers', 'sales_name')) {
            Schema::table('service_customers', function (Blueprint $table): void {
                $table->string('sales_name', 150)->nullable()->after('responsible_it_id');
            });
        }

        if (!Schema::hasColumn('service_customers', 'sales_email')) {
            Schema::table('service_customers', function (Blueprint $table): void {
                $table->string('sales_email', 190)->nullable()->after('sales_name');
            });
        }

        if (!Schema::hasColumn('service_customers', 'sales_active')) {
            Schema::table('service_customers', function (Blueprint $table): void {
                $table->boolean('sales_active')->default(false)->after('sales_email');
            });
        }

        $hasOwnerIndex = false;
        foreach (Schema::getIndexes('service_customers') as $index) {
            if (($index['name'] ?? '') === 'service_customers_responsible_it_id_index') {
                $hasOwnerIndex = true;
                break;
            }
        }

        if (!$hasOwnerIndex) {
            Schema::table('service_customers', function (Blueprint $table): void {
                $table->index('responsible_it_id', 'service_customers_responsible_it_id_index');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty. These fields are part of the current
        // Service Customer ownership model and should not be removed during rollback.
    }
};
