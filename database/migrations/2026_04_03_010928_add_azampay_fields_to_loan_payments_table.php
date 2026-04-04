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
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->string('external_id', 255)->nullable()->after('reference_number');
            $table->string('phone', 20)->nullable()->after('external_id');
            $table->string('provider', 50)->nullable()->after('phone');
            $table->string('transaction_reference', 255)->nullable()->after('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            //
        });
    }
};
