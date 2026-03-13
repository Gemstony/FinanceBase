<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_statement_id')
                ->constrained('bank_statements')
                ->cascadeOnDelete();

            $table->date('transaction_date');

            $table->string('reference')->nullable();
            $table->string('description')->nullable();

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            $table->decimal('amount', 15, 2); // net amount (+/-)

            $table->boolean('is_matched')->default(false);

            $table->unsignedBigInteger('matched_journal_entry_id')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('matched_journal_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};