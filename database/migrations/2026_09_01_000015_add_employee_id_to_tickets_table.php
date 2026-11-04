<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('employee_id')
                ->nullable()
                ->after('external_ticket_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->index(['employee_id', 'created_on']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['employee_id', 'created_on']);
            $table->dropColumn('employee_id');
        });
    }
};
