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
        Schema::table('payment_configs', function (Blueprint $table) {
            // Modify provider enum to include clickpesa
            $table->enum('provider', ['mpesa', 'airtel', 'tigo', 'clickpesa'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            // Revert provider enum to original values
            $table->enum('provider', ['mpesa', 'airtel', 'tigo'])->change();
        });
    }
};
