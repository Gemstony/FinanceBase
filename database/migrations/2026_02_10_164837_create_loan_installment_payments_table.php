<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installment_payments', function (Blueprint $table) {
            $table->id();

            // =========================
            // RELATIONS
            // =========================
            $table->foreignId('installment_id')
                  ->constrained('loan_installments')
                  ->cascadeOnDelete();

            $table->foreignId('loan_id')
                  ->constrained('loans')
                  ->cascadeOnDelete();

            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            $table->foreignId('customer_id')
                  ->constrained('customers')
                  ->cascadeOnDelete();

            // =========================
            // PAYMENT DETAILS
            // =========================
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('fees_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);

            $table->decimal('total_paid', 15, 2); // sum of all above
            $table->enum('payment_method', ['cash', 'bank_transfer', 'mobile_money', 'other'])->default('cash');

            $table->date('payment_date');
            $table->string('reference_number')->nullable(); // e.g., transaction ID

            $table->boolean('is_successful')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // =========================
            // INDEXES
            // =========================
            $table->index(['installment_id', 'loan_id', 'customer_id'], 'loan_installment_payment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installment_payments');
    }
};
