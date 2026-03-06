<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();

            // =========================
            // RELATIONSHIPS
            // =========================
            $table->foreignId('loan_id')
                  ->constrained('loans')
                  ->cascadeOnDelete();

            $table->foreignId('guarantor_id')
                  ->constrained('customers') // guarantor must be a registered customer
                  ->cascadeOnDelete();

            $table->boolean('is_joint_liability')->default(false);
            // used heavily in group loans

            // =========================
            // SYSTEM
            // =========================
            $table->timestamps();
            $table->softDeletes();

            // =========================
            // INDEXES & CONSTRAINTS
            // =========================
            $table->unique(['loan_id', 'guarantor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
