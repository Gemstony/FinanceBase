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
        Schema::create('loan_product_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            // ================================
            // RELATION
            // ================================
            $table->foreignId('loan_product_id')
                ->constrained()
                ->cascadeOnDelete();

            // ================================
            // ELIGIBILITY RULES
            // ================================
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();

            $table->unsignedInteger('min_membership_days')->nullable(); 
            // e.g. customer must be member for at least X days

            $table->boolean('requires_active_savings')->default(false);
            $table->decimal('min_savings_balance', 15, 2)->nullable();

            // ================================
            // LOAN AMOUNT & EXPOSURE RULES
            // ================================
            $table->decimal('min_loan_amount', 15, 2)->nullable();
            $table->decimal('max_loan_amount', 15, 2)->nullable();

            $table->decimal('loan_to_savings_ratio', 5, 2)->nullable(); 
            // e.g. loan <= savings * ratio

            $table->unsignedTinyInteger('max_active_loans')->default(1);

            // ================================
            // TENURE & REPAYMENT CONTROL
            // ================================
            $table->unsignedSmallInteger('min_installments')->nullable();
            $table->unsignedSmallInteger('max_installments')->nullable();

            $table->unsignedSmallInteger('grace_period_days')->default(0);

            // ================================
            // SECURITY DEPOSIT (COLLATERAL)
            // ================================
            $table->boolean('requires_security_deposit')->default(false);


            // ================================
            // COLLATERAL POLICY
            // ================================
            $table->boolean('requires_collateral')->default(false);
            $table->decimal('min_collateral_coverage_ratio', 5, 2)->nullable();
            // e.g. 120% means collateral must cover 120% of loan amount


            // ================================
            // INTEREST & PRICING CONTROL
            // ================================
            $table->decimal('min_interest_rate', 5, 2)->nullable();
            $table->decimal('max_interest_rate', 5, 2)->nullable();

            $table->boolean('allow_interest_override')->default(false);

            // ================================
            // PENALTY & DEFAULT CONTROL
            // ================================
            $table->unsignedSmallInteger('penalty_start_day')->default(1);
            $table->boolean('auto_apply_penalty')->default(true);

            // ================================
            // TOP-UP & RESTRUCTURING
            // ================================
            $table->boolean('allow_top_up')->default(false);
            $table->decimal('min_repayment_ratio_for_topup', 5, 2)->nullable();
            // e.g. 50% of principal must be repaid before top-up

            $table->boolean('allow_restructure')->default(false);

            // ================================
            // APPROVAL & CONTROL
            // ================================
            $table->boolean('requires_guarantor')->default(false);
            $table->boolean('manual_override_allowed')->default(false);

            // ================================
            // STATUS & AUDIT
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            //avoids rules duplication
            $table->unique(['subshop_id', 'loan_product_id']);

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_rules');
    }
};
