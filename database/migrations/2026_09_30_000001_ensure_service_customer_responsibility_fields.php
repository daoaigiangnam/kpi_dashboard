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

        // Add the FK only when the ownership column was newly introduced and
        // the referenced users table is available. This keeps the migration
        // safe on databases where the fields already exist.
        if (Schema::hasTable('users') && Schema::hasColumn('service_customers', 'responsible_it_id')) {
            $foreignExists = collect(Schema::getForeignKeys('service_customers'))
                ->contains(fn (array $foreign): bool => in_array('responsible_it_id', $foreign['columns'] ?? [], true));

            if (!$foreignExists) {
                Schema::table('service_customers', function (Blueprint $table): void {
                    $table->foreign('responsible_it_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally do not remove these columns. They are part of the
        // current Customer ownership/Sales model and may already have existed
        // before this defensive migration ran.
    }
};
