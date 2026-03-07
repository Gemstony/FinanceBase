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
        Schema::create('loan_restructures', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONSHIPS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | RESTRUCTURE INFORMATION
            |--------------------------------------------------------------------------
            */

            // Type of restructuring
            // reschedule, interest_reduction, grace_period, partial_writeoff
            $table->string('restructure_type');

            /*
            |--------------------------------------------------------------------------
            | OLD LOAN TERMS (SNAPSHOT)
            |--------------------------------------------------------------------------
            */

            $table->integer('old_term_months')->nullable();
            $table->decimal('old_interest_rate', 8, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | NEW LOAN TERMS
            |--------------------------------------------------------------------------
            */

            $table->integer('new_term_months')->nullable();
            $table->decimal('new_interest_rate', 8, 4)->nullable();

            // New restructure start date
            $table->date('restructure_effective_date')->nullable();

            /*
            |--------------------------------------------------------------------------
            | FINANCIAL VALUES AT RESTRUCTURE
            |--------------------------------------------------------------------------
            */

            $table->decimal('remaining_principal', 15, 2)->nullable();
            $table->decimal('remaining_interest', 15, 2)->nullable();
            $table->decimal('remaining_fees', 15, 2)->nullable();
            $table->decimal('remaining_penalties', 15, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | APPROVAL WORKFLOW
            |--------------------------------------------------------------------------
            */

            $table->text('reason')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | SYSTEM FLAGS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['loan_id']);
            $table->index(['restructure_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_restructures');
    }
};