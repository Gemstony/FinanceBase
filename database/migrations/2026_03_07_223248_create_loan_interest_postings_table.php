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
        Schema::create('loan_interest_postings', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONSHIPS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            $table->foreignId('installment_id')
                ->nullable()
                ->constrained('loan_installments')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | POSTING INFORMATION
            |--------------------------------------------------------------------------
            */

            // Date interest is officially posted
            $table->date('posting_date');

            // Total interest posted
            $table->decimal('interest_amount', 15, 6);

            // Optional accounting reference
            $table->string('reference_number')->nullable();

            // Description or posting reason
            $table->string('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | SYSTEM FLAGS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_successful')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['loan_id', 'posting_date']);
            $table->index(['installment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_interest_postings');
    }
};