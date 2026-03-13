<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_security_deposits', function (Blueprint $table) {
            $table->string('refund_method')->nullable()->after('refunded_by');

            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('refund_method')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_security_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('refund_method');
        });
    }
};
