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
        Schema::create('loan_product_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            // ================================
            // RELATIONSHIPS
            // ================================
            $table->foreignId('loan_product_id')
                ->constrained('loan_products')
                ->cascadeOnDelete();

            $table->foreignId('loan_fee_id')
                ->constrained('loan_fees')
                ->cascadeOnDelete();

            // ================================
            // APPLICATION RULES
            // ================================

            // When the fee is charged
            // e.g disbursement, approval, first_installment, every_installment
            $table->enum('charge_event', [
                'approval',
                'disbursement',
                'first_installment',
                'every_installment',
                'manual'
            ])->default('disbursement');

            // How the fee is paid
            // upfront = deducted from principal
            // added_to_loan = capitalized
            // separate_payment = paid independently
            $table->enum('payment_method', [
                'upfront',
                'added_to_loan',
                'separate_payment'
            ])->default('upfront');

            // Auto charge this fee?
            $table->boolean('auto_apply')->default(true);

            // ================================
            // LIMIT & CONTROL
            // ================================

            // Maximum times this fee can be charged (null = unlimited)
            $table->unsignedSmallInteger('max_applications')->nullable();

            // Can this fee be waived?
            $table->boolean('is_waivable')->default(false);

            // Is fee mandatory for this product?
            $table->boolean('is_mandatory')->default(true);

            // ================================
            // STATUS
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ================================
            // CONSTRAINTS
            // ================================
            // Prevent duplicate fee assignment
            $table->unique(
                ['loan_product_id', 'loan_fee_id'],
                'lp_fee_unique'
            );
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_fees');
    }
};
