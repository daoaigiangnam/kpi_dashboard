<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->decimal('cost_amount', 15, 2)->nullable()->after('value');
            $t->string('cost_currency', 3)->default('VND')->after('cost_amount');
            $t->string('cost_billing_cycle', 20)->default('monthly')->after('cost_currency');
            $t->index(['cost_currency', 'cost_billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['cost_currency', 'cost_billing_cycle']);
            $t->dropColumn(['cost_amount', 'cost_currency', 'cost_billing_cycle']);
        });
    }
};
