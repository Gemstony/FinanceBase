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
        Schema::create('journal_entries', function (Blueprint $table) {

            $table->id();

            $table->foreignId('subshop_id')
                ->constrained('sub_shops')
                ->cascadeOnDelete();

            $table->string('reference_type');
            // loan_disbursement, loan_payment, interest_posting, penalty_application

            $table->unsignedBigInteger('reference_id');

            $table->date('transaction_date');

            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
