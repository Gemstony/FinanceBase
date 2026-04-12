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
        if (!Schema::hasColumn('loan_payments', 'payment_account_id')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_account_id')
                    ->after('payment_method')
                    ->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('loan_payments', 'payment_account_id')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->dropColumn('payment_account_id');
            });
        }
    }
};
