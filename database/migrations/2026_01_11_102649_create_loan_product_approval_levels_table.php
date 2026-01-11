<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_product_approval_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            $table->foreignId('loan_product_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // ================================
            // APPROVAL SEQUENCE
            // ================================
            $table->unsignedTinyInteger('level_order');
            // 1 = first approval, 2 = second, etc.

            // ================================
            // WHO CAN APPROVE
            // ================================
            $table->string('role_code');
            // e.g. LOAN_OFFICER, SUPERVISOR, BRANCH_MANAGER, CREDIT_COMMITTEE

            // ================================
            // LIMITS
            // ================================
            $table->decimal('min_loan_amount', 15, 2)->nullable();
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            // approval applies only within this range

            // ================================
            // CONDITIONS
            // ================================
            $table->boolean('mandatory')->default(true);
            // must this level approve?

            $table->boolean('can_override_rules')->default(false);
            // can this level override product rules?

            $table->boolean('can_reject')->default(true);

            // ================================
            // STATUS
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ================================
            // CONSTRAINTS
            // ================================
            $table->unique(
                ['loan_product_id', 'level_order'],
                'unique_product_approval_level'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_product_approval_levels');
    }
};
