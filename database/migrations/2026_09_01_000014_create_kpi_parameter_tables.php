<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_weights', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->unsignedTinyInteger('weight');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kpi_sla_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description', 100);
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedTinyInteger('weight');
            $table->unsignedInteger('workload_point');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('kpi_weights')->insert([
            ['code'=>'productivity','name'=>'PRODUCTIVITY','weight'=>25,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'sla_compliance','name'=>'SLA COMPLIANCE','weight'=>35,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'quality_reopen','name'=>'QUALITY (REOPEN)','weight'=>15,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'process_compliance','name'=>'PROCESS COMPLIANCE','weight'=>15,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'ticket_responsiveness','name'=>'TICKET RESPONSIVENESS','weight'=>10,'sort_order'=>5,'created_at'=>now(),'updated_at'=>now()],
        ]);

        DB::table('kpi_sla_priorities')->insert([
            ['code'=>'P1','description'=>'Critical','response_minutes'=>15,'resolution_minutes'=>240,'weight'=>4,'workload_point'=>40,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'P2','description'=>'High','response_minutes'=>30,'resolution_minutes'=>480,'weight'=>3,'workload_point'=>20,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'P3','description'=>'Medium','response_minutes'=>120,'resolution_minutes'=>1440,'weight'=>2,'workload_point'=>10,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'P4','description'=>'Low','response_minutes'=>240,'resolution_minutes'=>2880,'weight'=>1,'workload_point'=>5,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_sla_priorities');
        Schema::dropIfExists('kpi_weights');
    }
};
