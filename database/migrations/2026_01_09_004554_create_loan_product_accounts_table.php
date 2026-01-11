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
        Schema::create('loan_product_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            // ================================
            // RELATION
            // ================================
            $table->foreignId('loan_product_id')
                ->constrained()
                ->cascadeOnDelete();

            // ================================
            // PRINCIPAL ACCOUNTS
            // ================================
            // Loan principal outstanding (Asset)
            $table->foreignId('principal_account_id')
                ->constrained('charts_of_accounts');

                
            // ================================
            // CUSTOMER SAVINGS / COLLATERAL CONTROL
            // ================================

            // Control account holding customer savings used as collateral
            $table->foreignId('customer_savings_control_account_id')
                ->nullable()
                ->constrained('charts_of_accounts');

            // Control account holding fixed security deposits (if any)
            $table->foreignId('security_deposit_control_account_id')
                ->nullable()
                ->constrained('charts_of_accounts');


            // ================================
            // INTEREST ACCOUNTS
            // ================================
            // Accrued but not yet paid interest (Asset)
            $table->foreignId('interest_receivable_account_id')
                ->constrained('charts_of_accounts');

            // Earned interest income (Income)
            $table->foreignId('interest_income_account_id')
                ->constrained('charts_of_accounts');

            // ================================
            // PENALTY ACCOUNTS
            // ================================
            // Accrued penalties (Asset)
            $table->foreignId('penalty_receivable_account_id')
                ->constrained('charts_of_accounts');

            // Earned penalty income (Income)
            $table->foreignId('penalty_income_account_id')
                ->constrained('charts_of_accounts');

            // ================================
            // FEE ACCOUNTS (OPTIONAL)
            // ================================
            // Some systems prefer fees per fee-definition,
            // but this allows product-level default fee income
            $table->foreignId('fee_income_account_id')
                ->nullable()
                ->constrained('charts_of_accounts');

            // ================================
            // WRITE-OFF & LOSS
            // ================================
            // Expense account when loan is written off
            $table->foreignId('write_off_expense_account_id')
                ->constrained('charts_of_accounts');

            // ================================
            // STATUS & AUDIT
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ================================
            // CONSTRAINTS
            // ================================
            $table->unique('loan_product_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_accounts');
    }
};
