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
        Schema::create('loan_writeoffs', function (Blueprint $table) {

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
            | WRITE-OFF DATE
            |--------------------------------------------------------------------------
            */

            $table->date('writeoff_date');

            /*
            |--------------------------------------------------------------------------
            | FINANCIAL AMOUNTS WRITTEN OFF
            |--------------------------------------------------------------------------
            */

            $table->decimal('principal_written_off', 15, 2)->default(0);
            $table->decimal('interest_written_off', 15, 2)->default(0);
            $table->decimal('fees_written_off', 15, 2)->default(0);
            $table->decimal('penalties_written_off', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | TOTAL AMOUNT WRITTEN OFF
            |--------------------------------------------------------------------------
            */

            $table->decimal('total_written_off', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | WRITE-OFF DETAILS
            |--------------------------------------------------------------------------
            */

            $table->text('reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | APPROVAL WORKFLOW
            |--------------------------------------------------------------------------
            */

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | SYSTEM FIELDS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(['loan_id']);
            $table->index(['writeoff_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_writeoffs');
    }
};