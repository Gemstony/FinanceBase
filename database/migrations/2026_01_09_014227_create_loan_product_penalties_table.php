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
        Schema::create('loan_product_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            // ================================
            // RELATIONSHIPS
            // ================================
            $table->foreignId('loan_product_id')
                ->constrained('loan_products')
                ->cascadeOnDelete();

            $table->foreignId('loan_penalty_id')
                ->constrained('loan_penalties')
                ->cascadeOnDelete();

            // ================================
            // BUSINESS CONFIGURATION
            // ================================

            // Override grace period if needed (null = use loan_penalties.grace_days)
            $table->unsignedSmallInteger('grace_days_override')->nullable();

            // Penalty start rule
            // false = manual / triggered by scheduler
            // true  = auto apply after grace days
            $table->boolean('auto_apply')->default(true);

            // Apply penalty repeatedly (e.g daily, per installment)
            $table->boolean('is_recurring')->default(false);

            // Maximum times penalty can be applied (null = unlimited)
            $table->unsignedSmallInteger('max_applications')->nullable();

            // ================================
            // CONTROL FLAGS
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ================================
            // CONSTRAINTS
            // ================================
            // Prevent duplicate penalty assignment to same product
            $table->unique(
                ['loan_product_id', 'loan_penalty_id'],
                'lp_penalty_unique'
            );
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_penalties');
    }
};
