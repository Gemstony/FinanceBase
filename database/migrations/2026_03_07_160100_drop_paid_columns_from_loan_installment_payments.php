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
        Schema::table('loan_installment_payments', function (Blueprint $table) {
            // Drop duplicated allocation columns
            $table->dropColumn(['principal_paid', 'interest_paid', 'fees_paid', 'penalty_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_installment_payments', function (Blueprint $table) {
            // Re-add the columns if we ever need to roll back
            $table->decimal('principal_paid', 15, 2)->default(0)->after('total_paid');
            $table->decimal('interest_paid', 15, 2)->default(0)->after('principal_paid');
            $table->decimal('fees_paid', 15, 2)->default(0)->after('interest_paid');
            $table->decimal('penalty_paid', 15, 2)->default(0)->after('fees_paid');
        });
    }
};
