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
        Schema::create('loan_payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_payment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('loan_installment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['loan_installment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payment_allocations');
    }
};
