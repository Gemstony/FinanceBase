<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds fields for NPL interest reversal tracking.
     */
    public function up(): void
    {
        Schema::table('loan_interest_accruals', function (Blueprint $table) {
            $table->boolean('is_npl_reversal')->default(false)->after('is_active');
            $table->string('npl_reversal_reason', 500)->nullable()->after('is_npl_reversal');
            $table->index(['loan_id', 'is_npl_reversal', 'accrual_date'], 'idx_npl_reversal_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_interest_accruals', function (Blueprint $table) {
            $table->dropIndex('idx_npl_reversal_lookup');
            $table->dropColumn(['is_npl_reversal', 'npl_reversal_reason']);
        });
    }
};
