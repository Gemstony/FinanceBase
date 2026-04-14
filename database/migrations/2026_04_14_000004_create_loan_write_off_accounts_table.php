<?php

declare(strict_types=1);

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
        Schema::create('loan_write_off_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->unique()->constrained('sub_shops')->onDelete('cascade');
            $table->foreignId('write_off_expense_account_id')->constrained('charts_of_accounts')->onDelete('restrict');
            $table->foreignId('recovery_income_account_id')->constrained('charts_of_accounts')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_write_off_accounts');
    }
};
