<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_group_members', function (Blueprint $table) {
            $table->id();

            // ================================
            // RELATIONSHIPS
            // ================================
            $table->foreignId('loan_group_id')
                  ->constrained('loan_groups')
                  ->cascadeOnDelete();

            $table->foreignId('customer_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // ================================
            // MEMBER ROLE
            // ================================
            $table->enum('role', [
                'member',
                'leader',
                'secretary',
                'treasurer'
            ])->default('member');

            // ================================
            // STATUS CONTROL
            // ================================
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();

            $table->boolean('is_active')->default(true);

            // ================================
            // AUDIT
            // ================================
            $table->timestamps();

            // ================================
            // CONSTRAINTS
            // ================================
            $table->unique(
                ['loan_group_id', 'customer_id'],
                'unique_group_member'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_group_members');
    }
};
