<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_fee_applications', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->nullable()->after('is_paid');
            $table->date('payment_date')->nullable()->after('paid_amount');
            $table->string('payment_method', 50)->nullable()->after('payment_date');
            $table->string('payment_reference', 100)->nullable()->after('payment_method');
            $table->foreignId('paid_by')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('loan_fee_applications', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['paid_amount', 'payment_date', 'payment_method', 'payment_reference', 'paid_by', 'paid_at']);
        });
    }
};
