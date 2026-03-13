<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_account_id')
                ->constrained('bank_accounts')
                ->cascadeOnDelete();

            $table->date('statement_date');

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);

            $table->string('reference_number')->nullable();
            $table->string('file_path')->nullable(); // if importing CSV/PDF

            $table->enum('status', [
                'draft',
                'in_progress',
                'reconciled'
            ])->default('draft');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};