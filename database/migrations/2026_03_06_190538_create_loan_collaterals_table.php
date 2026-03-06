<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_collaterals', function (Blueprint $table) {
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

            $table->foreignId('customer_collateral_id')
                  ->constrained('customer_collaterals')
                  ->cascadeOnDelete();

            // =========================
            // COLLATERAL DETAILS
            // =========================
            $table->decimal('collateral_value', 15, 2);
            $table->decimal('accepted_value', 15, 2)->nullable();
            $table->decimal('coverage_ratio', 8, 2)->nullable();

            // status of collateral
            $table->enum('status', [
                'pending_verification',
                'verified',
                'rejected',
                'released',
                'seized'
            ])->default('pending_verification');

            // =========================
            // TRACKING
            // =========================
            $table->date('verification_date')->nullable();
            $table->date('release_date')->nullable();

            $table->text('notes')->nullable();

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
            $table->index(['customer_collateral_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_collaterals');
    }
};