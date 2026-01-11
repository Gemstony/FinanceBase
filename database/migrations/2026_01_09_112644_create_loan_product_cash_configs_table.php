<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_product_cash_configs', function (Blueprint $table) {
            $table->id();

            // ================================
            // MULTI-TENANCY & RELATION
            // ================================
            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            $table->foreignId('loan_product_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // ================================
            // CASH / COLLATERAL REQUIREMENT
            // ================================
            $table->enum('deposit_requirement', [
                'none',          // No deposit required
                'fixed_amount',  // Fixed cash amount
                'percentage',    // % of loan amount
                'savings_based'  // Must exist in savings
            ])->default('none');

            // Used for fixed_amount OR percentage
            $table->decimal('deposit_value', 15, 2)->nullable();

            // Used when deposit is percentage
            $table->enum('deposit_basis', [
                'loan_amount',
                'principal'
            ])->nullable();

            // ================================
            // SAVINGS / ACCOUNT LINKING
            // ================================
            $table->boolean('use_customer_savings')->default(false);


            // ================================
            // LOCK & WITHDRAWAL RULES
            // ================================
            $table->unsignedInteger('lock_period_days')->nullable();

            $table->boolean('allow_withdrawal_during_loan')->default(false);

            // ================================
            // DEFAULT & REFUND BEHAVIOR
            // ================================
            $table->boolean('is_refundable')->default(true);

            $table->boolean('apply_on_default')->default(true);
            // If true, deposit is used to offset loan balance on default

            // ================================
            // CONTROL & STATUS
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ================================
            // CONSTRAINTS
            // ================================
            $table->unique(
                ['subshop_id', 'loan_product_id'],
                'lp_cash_config_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_product_cash_configs');
    }
};
