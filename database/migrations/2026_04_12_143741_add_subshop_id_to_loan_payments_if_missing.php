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
        // Check if subshop_id column exists in loan_payments table
        if (!Schema::hasColumn('loan_payments', 'subshop_id')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('subshop_id')
                    ->after('loan_id')
                    ->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if it exists and was added by this migration
        if (Schema::hasColumn('loan_payments', 'subshop_id')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->dropColumn('subshop_id');
            });
        }
    }
};
