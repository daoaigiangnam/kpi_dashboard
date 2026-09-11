<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('units', function (Blueprint $t) {
            $t->string('address', 255)->nullable()->after('name');
            $t->string('phone', 30)->nullable()->after('address');
            $t->string('tax_code', 50)->nullable()->after('phone');
        });

        Schema::table('units', function (Blueprint $t) {
            $t->index('tax_code');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $t) {
            $t->dropIndex(['tax_code']);
            $t->dropColumn(['address', 'phone', 'tax_code']);
        });
    }
};
