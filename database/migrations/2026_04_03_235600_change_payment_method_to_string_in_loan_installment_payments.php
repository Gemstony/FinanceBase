<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_installment_payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('loan_installment_payments', function (Blueprint $table) {
            $table->string('payment_method', 50)->default('cash')->after('total_paid');
        });
    }

    public function down(): void
    {
        Schema::table('loan_installment_payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('loan_installment_payments', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bank_transfer', 'mobile_money', 'other'])->default('cash')->after('total_paid');
        });
    }
};
