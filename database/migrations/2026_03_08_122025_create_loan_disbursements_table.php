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
        Schema::create('loan_disbursements', function (Blueprint $table) {

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
            | DISBURSEMENT DETAILS
            |--------------------------------------------------------------------------
            */

            $table->date('disbursement_date');

            $table->decimal('amount', 15, 2);

            /*
            |--------------------------------------------------------------------------
            | DISBURSEMENT METHOD
            |--------------------------------------------------------------------------
            */

            $table->string('disbursement_method'); 
            // examples: cash, bank_transfer, mobile_money

            $table->string('transaction_reference')
                ->nullable();
            // bank reference / mobile money reference

            /*
            |--------------------------------------------------------------------------
            | PROCESSING USER
            |--------------------------------------------------------------------------
            */

            $table->foreignId('processed_by')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL DETAILS
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

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

            $table->index('loan_id');
            $table->index('disbursement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_disbursements');
    }
};