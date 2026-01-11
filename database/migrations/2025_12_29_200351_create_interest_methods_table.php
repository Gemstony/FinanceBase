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
        Schema::create('interest_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');
            $table->string('name'); 
            // Flat, Reducing Balance, Compound

            $table->string('code')->unique(); 
            // FLAT, RED, COMP

            // Business behavior flags
            $table->boolean('supports_installment_based')->default(false);
            $table->boolean('supports_daily_accrual')->default(false);

            // Controls
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interest_methods');
    }
};
