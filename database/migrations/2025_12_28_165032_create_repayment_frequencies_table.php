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
        Schema::create('repayment_frequencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            // Identification
            $table->string('name'); 
            // e.g Daily, Weekly, Bi-Weekly, Monthly, Quarterly

            $table->string('code')->unique(); 
            // e.g DLY, WKY, BWK, MTH, QTR

            // Interval definition
            $table->unsignedInteger('interval_days'); 
            // Daily=1, Weekly=7, Bi-Weekly=14, Monthly=30 (logical)

            // Advanced config
            $table->boolean('is_month_based')->default(false);
            // true = Monthly/Quarterly (calendar based)
            // false = Daily/Weekly (fixed days)

            $table->unsignedTinyInteger('max_installments')->nullable();
            $table->unsignedTinyInteger('min_installments')->nullable();

            // Business control
            $table->boolean('is_active')->default(true);


            // Audit
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayment_frequencies');
    }
};
