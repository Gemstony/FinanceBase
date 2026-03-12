<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_disbursements', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_disbursements', 'bank_account_id')) {
                $table->foreignId('bank_account_id')
                    ->nullable()
                    ->after('disbursement_method_id')
                    ->constrained('bank_accounts')
                    ->nullOnDelete();
            } else {
                $table->unsignedBigInteger('bank_account_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_disbursements', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};