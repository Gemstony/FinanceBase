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
        Schema::table('payment_methods', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_methods', 'is_repayment_method')) {
                $table->boolean('is_repayment_method')->default(true)->after('status');
            }
            if (!Schema::hasColumn('payment_methods', 'is_deposit_method')) {
                $table->boolean('is_deposit_method')->default(true)->after('is_repayment_method');
            }
            if (!Schema::hasColumn('payment_methods', 'is_refund_method')) {
                $table->boolean('is_refund_method')->default(true)->after('is_deposit_method');
            }
            if (!Schema::hasColumn('payment_methods', 'is_withdrawal_method')) {
                $table->boolean('is_withdrawal_method')->default(true)->after('is_refund_method');
            }
            if (!Schema::hasColumn('payment_methods', 'requires_bank_account')) {
                $table->boolean('requires_bank_account')->default(false)->after('is_withdrawal_method');
            }
            if (!Schema::hasColumn('payment_methods', 'requires_phone')) {
                $table->boolean('requires_phone')->default(false)->after('requires_bank_account');
            }
            if (!Schema::hasColumn('payment_methods', 'account_type')) {
                $table->string('account_type', 20)->default('asset')->after('requires_phone');
            }
            if (!Schema::hasColumn('payment_methods', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('account_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'is_repayment_method',
                'is_deposit_method',
                'is_refund_method',
                'is_withdrawal_method',
                'requires_bank_account',
                'requires_phone',
                'account_type',
                'sort_order',
            ]);
        });
    }
};
