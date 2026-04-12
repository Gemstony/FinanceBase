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
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('subshop_id')
                ->constrained('sub_shops')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete(); // staff who recorded payment

            $table->decimal('amount', 15, 2);

            $table->date('payment_date');

            $table->string('payment_method')->nullable();
            // cash, bank_transfer, mobile_money, etc

            $table->foreignId('payment_account_id')
                ->nullable()
                ->constrained('charts_of_accounts')
                ->nullOnDelete();

            $table->string('reference_number')->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', [
                'pending',
                'confirmed',
                'reversed',
                'failed'
            ])->default('confirmed');

            $table->timestamps();

            $table->index(['loan_id', 'payment_date']);
            $table->index(['subshop_id', 'payment_date']);
            $table->index(['subshop_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
