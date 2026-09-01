<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->string('name', 120);
            $table->string('code', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('group_permissions', function (Blueprint $table) {
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_group_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('user_groups');
    }
};
