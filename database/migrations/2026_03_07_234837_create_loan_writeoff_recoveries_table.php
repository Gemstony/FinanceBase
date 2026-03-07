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
        Schema::create('loan_writeoff_recoveries', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATIONSHIPS
            |--------------------------------------------------------------------------
            */

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            $table->foreignId('writeoff_id')
                ->constrained('loan_writeoffs')
                ->cascadeOnDelete();

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('loan_payments')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | RECOVERY DATE
            |--------------------------------------------------------------------------
            */

            $table->date('recovery_date');

            /*
            |--------------------------------------------------------------------------
            | RECOVERED AMOUNTS
            |--------------------------------------------------------------------------
            */

            $table->decimal('recovered_principal', 15, 2)->default(0);
            $table->decimal('recovered_interest', 15, 2)->default(0);
            $table->decimal('recovered_fees', 15, 2)->default(0);
            $table->decimal('recovered_penalties', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | TOTAL RECOVERY
            |--------------------------------------------------------------------------
            */

            $table->decimal('total_recovered', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | NOTES
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

            $table->index(['loan_id']);
            $table->index(['writeoff_id']);
            $table->index(['recovery_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_writeoff_recoveries');
    }
};