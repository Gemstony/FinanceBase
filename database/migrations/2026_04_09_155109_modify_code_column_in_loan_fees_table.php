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
        Schema::table('loan_fees', function (Blueprint $table) {
            $table->dropUnique('loan_fees_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_fees', function (Blueprint $table) {
            $table->unique('code', 'loan_fees_code_unique');
        });
    }
};
