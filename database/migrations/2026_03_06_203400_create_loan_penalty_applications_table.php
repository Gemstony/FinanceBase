<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_penalty_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')
                  ->constrained('loans')
                  ->cascadeOnDelete();

            $table->foreignId('loan_product_penalty_id')
                  ->constrained('loan_product_penalties')
                  ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->enum('charge_event', [
                'overdue_installment',
                'late_payment',
                'manual',
            ])->comment('The event that triggered this penalty');

            $table->date('applied_on');
            $table->boolean('is_paid')->default(false);
            $table->timestamps();

            $table->unique(['loan_id', 'loan_product_penalty_id', 'applied_on'], 'unique_penalty_application');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_penalty_applications');
    }
};