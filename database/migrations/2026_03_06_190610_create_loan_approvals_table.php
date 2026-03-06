<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_approvals', function (Blueprint $table) {
            $table->id();

            // =========================
            // RELATIONSHIPS
            // =========================
            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            $table->foreignId('loan_id')
                  ->constrained('loans')
                  ->cascadeOnDelete();

            $table->foreignId('loan_product_approval_level_id')
                  ->constrained('loan_product_approval_levels')
                  ->cascadeOnDelete();

            // approver user
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // =========================
            // APPROVAL DETAILS
            // =========================
            $table->integer('level_order');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'skipped'
            ])->default('pending');

            $table->timestamp('approved_at')->nullable();

            $table->text('comments')->nullable();

            // =========================
            // SYSTEM CONTROL
            // =========================
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // =========================
            // INDEXES
            // =========================
            $table->index(['loan_id']);
            $table->index(['loan_product_approval_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_approvals');
    }
};