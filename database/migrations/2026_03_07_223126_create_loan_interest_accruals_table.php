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
        Schema::create('loan_interest_accruals', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONSHIPS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            // Optional because some banks accrue interest at loan level
            $table->foreignId('installment_id')
                ->nullable()
                ->constrained('loan_installments')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ACCRUAL DATA
            |--------------------------------------------------------------------------
            */

            // Date the interest was accrued
            $table->date('accrual_date');

            // Principal balance used for interest calculation
            $table->decimal('principal_balance', 15, 2);

            // Annual interest rate used (snapshot for audit)
            $table->decimal('interest_rate', 8, 4);

            // Interest accrued for this specific day
            $table->decimal('daily_interest', 15, 6);

            /*
            |--------------------------------------------------------------------------
            | ACCOUNTING FLAGS
            |--------------------------------------------------------------------------
            */

            // Whether the accrual has already been posted to the ledger
            $table->boolean('is_posted')->default(false);

            // Posting reference if interest has been posted
            $table->foreignId('posting_id')
                ->nullable()
                ->constrained('loan_interest_postings')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | SYSTEM FLAGS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['loan_id', 'accrual_date']);
            $table->unique(['loan_id', 'accrual_date'], 'unique_loan_daily_interest_accrual');
            $table->index(['installment_id']);
            $table->index(['is_posted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_interest_accruals');
    }
};