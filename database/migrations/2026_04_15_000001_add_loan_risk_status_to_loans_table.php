<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Add risk status column for faster filtering and indexing
            $table->enum('risk_status', ['current', 'par30', 'par60', 'par90', 'default'])
                ->default('current')
                ->after('status')
                ->comment('Risk classification based on DPD');

            // Add max_days_overdue for quick access
            $table->unsignedInteger('max_days_overdue')
                ->default(0)
                ->after('risk_status')
                ->comment('Maximum days past due across all installments');

            // Add index for faster risk queries
            $table->index(['risk_status', 'is_active', 'status'], 'idx_loans_risk_active');
            $table->index(['max_days_overdue', 'is_active'], 'idx_loans_dpd_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('idx_loans_risk_active');
            $table->dropIndex('idx_loans_dpd_active');
            $table->dropColumn(['risk_status', 'max_days_overdue']);
        });
    }
};
