<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();

            // =========================
            // RELATIONS
            // =========================
            $table->foreignId('loan_id')
                  ->constrained('loans')
                  ->cascadeOnDelete();

            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            // =========================
            // INSTALLMENT DETAILS
            // =========================
            $table->integer('installment_number'); // 1, 2, 3, ...
            $table->decimal('principal_due', 15, 2);
            $table->decimal('interest_due', 15, 2);
            $table->decimal('fees_due', 15, 2)->default(0);
            $table->decimal('penalty_due', 15, 2)->default(0);

            $table->decimal('total_due', 15, 2); // principal + interest + fees + penalties
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->default(0);

            $table->date('due_date');
            $table->date('paid_date')->nullable();

            // =========================
            // STATUS
            // =========================
            $table->enum('status', ['pending', 'paid', 'partial', 'overdue'])->default('pending');

            $table->boolean('is_active')->default(true);

            // =========================
            // ACCOUNTS
            // =========================
            $table->foreignId('principal_account_id')->constrained('charts_of_accounts');
            $table->foreignId('interest_income_account_id')->constrained('charts_of_accounts');
            $table->foreignId('penalty_income_account_id')->constrained('charts_of_accounts');
            $table->foreignId('fee_income_account_id')->nullable()->constrained('charts_of_accounts');

            $table->timestamps();
            $table->softDeletes();

            // =========================
            // UNIQUE CONSTRAINT
            // =========================
            $table->unique(['loan_id', 'installment_number'], 'unique_loan_installment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
