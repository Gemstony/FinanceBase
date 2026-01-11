<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();

            // ================================
            // MULTI-TENANCY
            // ================================
            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            // ================================
            // BASIC IDENTIFICATION
            // ================================
            $table->string('name');
            // e.g. "Salary Loan", "SME Working Capital"

            $table->string('code')->unique();
            // short unique code (SL01, SME03)

            $table->text('description')->nullable();

            // ================================
            // PRODUCT TYPE & BEHAVIOR
            // ================================
            $table->enum('loan_type', [
                'individual',
                'group',
                'corporate'
            ])->default('individual');

            $table->boolean('is_revolving')->default(false);
            // true = credit line / overdraft behavior

            // ================================
            // INTEREST & REPAYMENT LINKS
            // ================================
            $table->foreignId('interest_method_id')
                  ->constrained('interest_methods');

            $table->foreignId('interest_cycle_id')
                  ->constrained('interest_cycles');

            $table->foreignId('repayment_frequency_id')
                  ->constrained('repayment_frequencies');

            // ================================
            // TENURE DEFAULTS (UI + VALIDATION)
            // ================================
            $table->unsignedSmallInteger('default_installments')->nullable();
            $table->unsignedSmallInteger('max_installments')->nullable();
            $table->unsignedSmallInteger('min_installments')->nullable();

            // ================================
            // AMOUNT DEFAULTS (UI + VALIDATION)
            // ================================
            $table->decimal('default_loan_amount', 15, 2)->nullable();

            // ================================
            // COLLATERAL SUPPORT FLAG
            // ================================
            $table->boolean('supports_collateral')->default(false);
            // actual rules handled in loan_product_rules

            // ================================
            // APPROVAL CONFIG
            // ================================
            $table->boolean('requires_approval')->default(true);

            // ================================
            // PRODUCT STATUS
            // ================================
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);
            // visible in UI for loan officers

            // ================================
            // AUDIT
            // ================================
            $table->timestamps();

            // ================================
            // INDEXES
            // ================================
            $table->index(['subshop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
