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
        Schema::table('loan_writeoff_recoveries', function (Blueprint $table) {
            // Add foreign key for bank account
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();

            // Add payment method column
            $table->string('payment_method', 50)->nullable()->after('bank_account_id');

            // Add transaction reference
            $table->string('transaction_reference', 255)->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_writeoff_recoveries', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['bank_account_id', 'payment_method', 'transaction_reference']);
        });
    }
};
