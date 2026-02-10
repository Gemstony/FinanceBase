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
            $table->unsignedBigInteger('principal_account_id');
            // FK: principal ledger (asset) where outstanding loan principal is tracked
            $table->foreign('principal_account_id', 'lp_acc_principal_fk')
                ->references('id')->on('charts_of_accounts');

                
            // ================================
            // CUSTOMER SAVINGS / COLLATERAL CONTROL
            // ================================

            // Control account holding customer savings used as collateral
            $table->unsignedBigInteger('customer_savings_control_account_id')->nullable();
            // FK: control account for customer savings used as collateral (optional)
            $table->foreign('customer_savings_control_account_id', 'lp_acc_cust_sav_fk')
                ->references('id')->on('charts_of_accounts');

            // Control account holding fixed security deposits (if any)
            $table->unsignedBigInteger('security_deposit_control_account_id')->nullable();
            // FK: control account for fixed security deposits held (optional)
            $table->foreign('security_deposit_control_account_id', 'lp_acc_sec_dep_fk')
                ->references('id')->on('charts_of_accounts');


            // ================================
            // INTEREST ACCOUNTS
            // ================================
            // Accrued but not yet paid interest (Asset)
            $table->unsignedBigInteger('interest_receivable_account_id');
            // FK: asset account for accrued (unpaid) interest
            $table->foreign('interest_receivable_account_id', 'lp_acc_int_recv_fk')
                ->references('id')->on('charts_of_accounts');

            // Earned interest income (Income)
            $table->unsignedBigInteger('interest_income_account_id');
            // FK: income account where earned interest is recognized
            $table->foreign('interest_income_account_id', 'lp_acc_int_inc_fk')
                ->references('id')->on('charts_of_accounts');

            // ================================
            // PENALTY ACCOUNTS
            // ================================
            // Accrued penalties (Asset)
            $table->unsignedBigInteger('penalty_receivable_account_id');
            // FK: asset account for accrued (unpaid) penalties
            $table->foreign('penalty_receivable_account_id', 'lp_acc_pen_recv_fk')
                ->references('id')->on('charts_of_accounts');

            // Earned penalty income (Income)
            $table->unsignedBigInteger('penalty_income_account_id');
            // FK: income account where penalty income is recognized
            $table->foreign('penalty_income_account_id', 'lp_acc_pen_inc_fk')
                ->references('id')->on('charts_of_accounts');

            // ================================
            // FEE ACCOUNTS (OPTIONAL)
            // ================================
            // Some systems prefer fees per fee-definition,
            // but this allows product-level default fee income
            $table->unsignedBigInteger('fee_income_account_id')->nullable();
            // FK: default income account for fees at product level (optional)
            $table->foreign('fee_income_account_id', 'lp_acc_fee_inc_fk')
                ->references('id')->on('charts_of_accounts');

            // ================================
            // CUSTIMER SAVINGS CONTROL (OPTIONAL)
            // ================================

            $table->unsignedBigInteger('customer_savings_account_id')->nullable();
            // FK: default income account for fees at product level (optional)
            $table->foreign('customer_savings_account_id', 'lp_acc_cus_sav_fk')
                ->references('id')->on('charts_of_accounts');

            // ================================
            // CUSTIMER SECURITY DEPOSIT CONTROL (OPTIONAL)
            // ================================

            $table->unsignedBigInteger('customer_security_deposit_account_id')->nullable();
            // FK: default income account for fees at product level (optional)
            $table->foreign('customer_security_deposit_account_id', 'lp_acc_cus_sec_dep_fk')
                ->references('id')->on('charts_of_accounts');

            // ================================
            // WRITE-OFF & LOSS
            // ================================
            // Expense account when loan is written off
            $table->unsignedBigInteger('write_off_expense_account_id');
            // FK: expense account used when loans are written off
            $table->foreign('write_off_expense_account_id', 'lp_acc_wroff_exp_fk')
                ->references('id')->on('charts_of_accounts');

            

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
