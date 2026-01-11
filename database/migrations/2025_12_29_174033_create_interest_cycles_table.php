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
        Schema::create('interest_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');
            $table->string('name'); 
            // Daily, Monthly, Per Installment

            $table->string('code')->unique(); 
            // DLY, MTH, INST

            $table->unsignedInteger('interval_days')->nullable(); 
            // Daily = 1, Monthly = 30, null if installment based

            $table->boolean('is_installment_based')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interest_cycles');
    }
};
