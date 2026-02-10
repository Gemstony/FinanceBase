<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            // =========================
            // MULTI-TENANCY
            // =========================
            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            // =========================
            // PRODUCT
            // =========================
            $table->foreignId('loan_product_id')
                  ->constrained('loan_products')
                  ->cascadeOnDelete();

            // =========================
            // BORROWER (INDIVIDUAL OR GROUP)
            // =========================
            $table->enum('borrower_type', ['individual', 'group']);

            $table->foreignId('customer_id')
                  ->nullable()
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('loan_group_id')
                  ->nullable()
                  ->constrained('loan_groups')
                  ->cascadeOnDelete();

            // =========================
            // LOAN CORE DETAILS
            // =========================
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 8, 2)->nullable();

            $table->unsignedSmallInteger('installments')->nullable();
            $table->unsignedSmallInteger('installments_paid')->default(0);

            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('next_installment_amount', 15, 2)->nullable();

            $table->date('disbursement_date')->nullable();
            $table->date('maturity_date')->nullable();

            $table->string('repayment_frequency_code', 50);
            // derived from repayment_frequencies table at creation

            // =========================
            // PRODUCT BEHAVIOR SNAPSHOT
            // =========================
            $table->boolean('supports_collateral')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('allow_top_up')->default(false);

            // =========================
            // COLLATERAL & DEPOSIT SNAPSHOT
            // =========================
            $table->boolean('requires_collateral')->default(false);
            $table->decimal('collateral_value', 15, 2)->nullable();
            $table->decimal('collateral_coverage_ratio', 5, 2)->nullable();

            $table->boolean('requires_security_deposit')->default(false);
            $table->decimal('security_deposit_amount', 15, 2)->nullable();

            // =========================
            // STATUS & LIFECYCLE
            // =========================
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'disbursed',
                'partially_paid',
                'paid_off',
                'defaulted',
                'written_off'
            ])->default('pending');

            $table->boolean('approval_completed')->default(false);

            // =========================
            // APPROVAL AUDIT (SNAPSHOT)
            // =========================
            $table->json('approval_history')->nullable();
            // who approved, role, timestamp

            // =========================
            // ACCOUNT SNAPSHOT (FROM PRODUCT)
            // =========================
            $table->foreignId('principal_account_id')->constrained('charts_of_accounts');
            $table->foreignId('interest_receivable_account_id')->constrained('charts_of_accounts');
            $table->foreignId('interest_income_account_id')->constrained('charts_of_accounts');
            $table->foreignId('penalty_receivable_account_id')->constrained('charts_of_accounts');
            $table->foreignId('penalty_income_account_id')->constrained('charts_of_accounts');
            $table->foreignId('write_off_expense_account_id')->constrained('charts_of_accounts');

            $table->foreignId('fee_income_account_id')->nullable()->constrained('charts_of_accounts');
            $table->foreignId('customer_savings_account_id')->nullable()->constrained('charts_of_accounts');
            $table->foreignId('customer_security_deposit_account_id')->nullable()->constrained('charts_of_accounts');

            // =========================
            // SYSTEM CONTROL
            // =========================
            $table->boolean('is_active')->default(true);

            // =========================
            // AUDIT
            // =========================
            $table->timestamps();
            $table->softDeletes();

            // =========================
            // INDEXES
            // =========================
            $table->index(['borrower_type', 'customer_id']);
            $table->index(['borrower_type', 'loan_group_id']);
            $table->index(['loan_product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
