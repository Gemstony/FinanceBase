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
        Schema::table('loan_installments', function (Blueprint $table) {
            // Add component-level paid columns after penalty_due
            $table->decimal('principal_paid', 15, 2)->default(0)->after('penalty_due');
            $table->decimal('interest_paid', 15, 2)->default(0)->after('principal_paid');
            $table->decimal('fees_paid', 15, 2)->default(0)->after('interest_paid');
            $table->decimal('penalty_paid', 15, 2)->default(0)->after('fees_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn(['principal_paid', 'interest_paid', 'fees_paid', 'penalty_paid']);
        });
    }
};
