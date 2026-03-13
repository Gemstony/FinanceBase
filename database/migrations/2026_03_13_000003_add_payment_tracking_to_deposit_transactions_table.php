<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_transactions', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('transaction_type');

            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('payment_method');
        });
    }
};
