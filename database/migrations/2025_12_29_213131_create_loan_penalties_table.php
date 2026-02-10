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
        Schema::create('loan_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            $table->string('name');
            $table->string('code')->unique();

            $table->enum('penalty_type', ['FIXED', 'DAILY_PERCENTAGE']);

            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();

            // Trigger
            $table->unsignedInteger('grace_period_days')->default(0);

            $table->enum('frequency', [
                'once',
                'daily',
                'weekly',
                'monthly',
                'per_installment'
            ])->default('once');

            // Accounting
            $table->foreignId('income_account_id')->constrained('charts_of_accounts');
            $table->foreignId('receivable_account_id')->constrained('charts_of_accounts');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_penalties');
    }
};
