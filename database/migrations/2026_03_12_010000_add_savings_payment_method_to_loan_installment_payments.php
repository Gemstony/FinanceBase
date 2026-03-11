<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_installment_payments', function (Blueprint $table) {
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'mobile_money',
                'customer_credit',
                'savings',
                'other',
            ])->default('cash')->change();
        });
    }

    public function down(): void
    {
        Schema::table('loan_installment_payments', function (Blueprint $table) {
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'mobile_money',
                'customer_credit',
                'other',
            ])->default('cash')->change();
        });
    }
};
