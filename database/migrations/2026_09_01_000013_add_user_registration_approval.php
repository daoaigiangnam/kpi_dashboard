<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_status', 20)->default('approved')->after('is_active')->index();
            $table->timestamp('registration_reviewed_at')->nullable()->after('registration_status');
            $table->foreignId('registration_reviewed_by')->nullable()->after('registration_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('registration_rejection_reason')->nullable()->after('registration_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['registration_reviewed_by']);
            $table->dropIndex(['registration_status']);
            $table->dropColumn(['registration_status','registration_reviewed_at','registration_reviewed_by','registration_rejection_reason']);
        });
    }
};
