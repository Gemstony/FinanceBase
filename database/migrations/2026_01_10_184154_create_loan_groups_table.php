<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_groups', function (Blueprint $table) {
            $table->id();

            // ================================
            // MULTI-TENANCY
            // ================================
            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            // ================================
            // GROUP IDENTITY
            // ================================
            $table->string('name');
            // e.g. "Upendo Women Group"

            $table->string('code')->unique();
            // e.g. GRP-001

            $table->text('description')->nullable();

            // ================================
            // FORMATION & CONTROL
            // ================================
            $table->date('formation_date')->nullable();

            $table->boolean('is_active')->default(true);

            // ================================
            // AUDIT
            // ================================
            $table->timestamps();

            // ================================
            // INDEXES
            // ================================
            $table->index(['subshop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_groups');
    }
};
