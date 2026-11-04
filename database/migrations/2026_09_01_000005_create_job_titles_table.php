<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('level', 50)->nullable();
            $t->string('description', 255)->nullable();
            $t->decimal('target_workload_point', 10, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['is_active', 'name']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('job_title_id')->nullable()->after('user_group_id')->constrained('job_titles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['job_title_id']);
            $t->dropColumn('job_title_id');
        });

        Schema::dropIfExists('job_titles');
    }
};
