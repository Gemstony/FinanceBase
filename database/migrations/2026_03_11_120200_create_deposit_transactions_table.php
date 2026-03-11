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
        Schema::create('deposit_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deposit_account_id')->constrained('deposit_accounts')->onDelete('cascade');

            $table->enum('transaction_type', [
                'deposit',
                'withdrawal',
                'interest',
                'loan_payment',
                'transfer',
                'fee',
                'adjustment',
            ]);

            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['deposit_account_id', 'created_at']);
            $table->index(['transaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_transactions');
    }
};
