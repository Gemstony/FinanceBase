<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_security_deposits', function (Blueprint $table) {
            $table->foreignId('payment_bank_account_id')
                ->nullable()
                ->after('loan_id')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_security_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_bank_account_id');
        });
    }
};
