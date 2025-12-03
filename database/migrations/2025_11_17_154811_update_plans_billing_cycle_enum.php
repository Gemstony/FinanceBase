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
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('billing_cycle', ['monthly', '2_months', '3_months', '6_months', 'yearly', 'one_time'])->default('monthly')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time'])->default('monthly')->change();
        });
    }
};
