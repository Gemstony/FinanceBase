<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credit_balances', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONSHIPS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('subshop_id')
                ->constrained('sub_shops')
                ->cascadeOnDelete();

            // Borrower who owns the credit
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Loan that generated the credit (optional)
            $table->foreignId('loan_id')
                ->nullable()
                ->constrained('loans')
                ->nullOnDelete();

            // Payment that caused the overpayment
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('loan_payments')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | CREDIT AMOUNT
            |--------------------------------------------------------------------------
            */

            $table->decimal('amount', 15, 2);


            /*
            |--------------------------------------------------------------------------
            | CREDIT STATUS
            |--------------------------------------------------------------------------
            */

            // available = unused
            // applied   = used for another loan
            // refunded  = returned to customer
            $table->enum('status', [
                'available',
                'applied',
                'refunded'
            ])->default('available');


            /*
            |--------------------------------------------------------------------------
            | USAGE TRACKING
            |--------------------------------------------------------------------------
            */

            // If the credit is later applied to another loan
            $table->foreignId('applied_to_loan_id')
                ->nullable()
                ->constrained('loans')
                ->nullOnDelete();

            $table->timestamp('applied_at')->nullable();


            /*
            |--------------------------------------------------------------------------
            | REFUND TRACKING
            |--------------------------------------------------------------------------
            */

            $table->timestamp('refunded_at')->nullable();

            $table->foreignId('refunded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | NOTES
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();


            /*
            |--------------------------------------------------------------------------
            | SYSTEM FIELDS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['subshop_id']);
            $table->index(['customer_id']);
            $table->index(['loan_id']);
            $table->index(['status']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_balances');
    }
};