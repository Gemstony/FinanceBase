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
        Schema::create('loan_restructure_installments', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONSHIPS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('restructure_id')
                ->constrained('loan_restructures')
                ->cascadeOnDelete();

            $table->foreignId('installment_id')
                ->constrained('loan_installments')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | OLD INSTALLMENT SNAPSHOT
            |--------------------------------------------------------------------------
            */

            $table->integer('installment_number');

            $table->date('old_due_date');

            $table->decimal('old_principal_due', 15, 2)->default(0);
            $table->decimal('old_interest_due', 15, 2)->default(0);
            $table->decimal('old_fees_due', 15, 2)->default(0);
            $table->decimal('old_penalty_due', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | PAYMENT STATUS SNAPSHOT
            |--------------------------------------------------------------------------
            */

            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('fees_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | SYSTEM FIELDS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['restructure_id']);
            $table->index(['installment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_restructure_installments');
    }
};