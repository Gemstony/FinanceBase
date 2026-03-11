<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_security_deposits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subshop_id')->constrained('sub_shops')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            $table->enum('status', ['held', 'applied', 'refunded', 'forfeited'])->default('held');

            $table->timestamp('held_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->foreignId('applied_to_loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['customer_id']);
            $table->index(['loan_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_security_deposits');
    }
};
