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
        Schema::table('loan_disbursements', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | REMOVE OLD COLUMN
            |--------------------------------------------------------------------------
            */

            $table->dropColumn('disbursement_method');

            /*
            |--------------------------------------------------------------------------
            | ADD NEW FOREIGN KEY
            |--------------------------------------------------------------------------
            */

            $table->foreignId('disbursement_method_id')
                  ->after('loan_id')
                  ->constrained('disbursement_methods')
                  ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_disbursements', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | DROP FOREIGN KEY
            |--------------------------------------------------------------------------
            */

            $table->dropForeign(['disbursement_method_id']);
            $table->dropColumn('disbursement_method_id');

            /*
            |--------------------------------------------------------------------------
            | RESTORE OLD COLUMN
            |--------------------------------------------------------------------------
            */

            $table->string('disbursement_method')->nullable();
        });
    }
};