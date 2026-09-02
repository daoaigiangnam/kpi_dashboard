<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The stored workload contribution is used for the Total row.
        // Only completed tickets contribute to completed workload.
        DB::table('tickets')
            ->whereNull('finished_on')
            ->update(['workload_point' => 0]);
    }

    public function down(): void
    {
        // Do not reconstruct historical workload values here because the
        // authoritative values come from the configured Priority parameters.
    }
};
