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
        // Indexes for loan_installments table - critical for PAR calculations
        Schema::table('loan_installments', function (Blueprint $table) {
            // Composite index for overdue installment queries
            $table->index(
                ['is_active', 'status', 'due_date'],
                'idx_installments_active_status_due_date'
            );

            // Index for loan-based queries
            $table->index(
                ['loan_id', 'is_active', 'status', 'due_date'],
                'idx_installments_loan_active_status_due'
            );

            // Index for schedule version queries (restructured loans)
            $table->index(
                ['loan_id', 'is_active', 'schedule_version'],
                'idx_installments_loan_active_version'
            );
        });

        // Indexes for loans table - critical for portfolio queries
        Schema::table('loans', function (Blueprint $table) {
            // Already added in previous migration, but add more for common filters
            $table->index(
                ['is_active', 'status', 'subshop_id', 'is_written_off'],
                'idx_loans_portfolio_filters'
            );

            // Index for customer-based queries
            $table->index(['customer_id', 'status', 'is_active'], 'idx_loans_customer_status_active');

            // Index for product-based analysis
            $table->index(['loan_product_id', 'status', 'is_active'], 'idx_loans_product_status_active');
        });

        // Indexes for loan_payments table
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->index(['status', 'payment_date'], 'idx_payments_status_date');
            $table->index(['loan_id', 'status', 'payment_date'], 'idx_payments_loan_status_date');
        });

        // Indexes for loan_payment_allocations table
        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->index(['loan_payment_id', 'loan_installment_id'], 'idx_allocations_payment_installment');
        });

        // Indexes for risk_snapshots table
        Schema::table('risk_snapshots', function (Blueprint $table) {
            // Already created in migration, but ensure optimal indexes
            $table->index(['snapshot_date', 'subshop_id', 'par90_rate'], 'idx_snapshots_date_subshop_par90');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropIndex('idx_installments_active_status_due_date');
            $table->dropIndex('idx_installments_loan_active_status_due');
            $table->dropIndex('idx_installments_loan_active_version');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('idx_loans_portfolio_filters');
            $table->dropIndex('idx_loans_customer_status_active');
            $table->dropIndex('idx_loans_product_status_active');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_status_date');
            $table->dropIndex('idx_payments_loan_status_date');
        });

        Schema::table('loan_payment_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_allocations_payment_installment');
        });

        Schema::table('risk_snapshots', function (Blueprint $table) {
            $table->dropIndex('idx_snapshots_date_subshop_par90');
        });
    }
};
