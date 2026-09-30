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

        Schema::table('service_customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('service_customers', 'responsible_it_id')) {
                $table->unsignedBigInteger('responsible_it_id')->nullable()->after('phone');
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

        // Add the index separately so this migration remains safe on installations
        // where the ownership column already existed.
        if (Schema::hasColumn('service_customers', 'responsible_it_id')) {
            $indexes = Schema::getIndexes('service_customers');
            $hasIndex = collect($indexes)->contains(function (array $index): bool {
                return ($index['name'] ?? '') === 'service_customers_responsible_it_id_index';
            });

            if (!$hasIndex) {
                Schema::table('service_customers', function (Blueprint $table): void {
                    $table->index('responsible_it_id', 'service_customers_responsible_it_id_index');
                });
            }
        }
    }

    public function down(): void
    {
        // Do not remove these columns automatically because this migration is
        // intentionally defensive for existing production databases.
    }
};
