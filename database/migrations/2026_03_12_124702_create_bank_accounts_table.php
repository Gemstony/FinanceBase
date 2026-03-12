<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subshop_id')
                ->constrained('sub_shops')
                ->cascadeOnDelete();

            $table->string('account_name');
            // Example: CRDB Loan Account, Branch Cash Box, M-Pesa Float

            $table->string('account_type');
            // bank, cash, mobile_money

            $table->string('bank_name')->nullable();
            // CRDB, NMB, NBC etc

            $table->string('account_number')->nullable();

            $table->foreignId('chart_of_account_id')
                ->constrained('charts_of_accounts');
            // Links to GL account used in accounting

            $table->boolean('is_active')->default(true);

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};