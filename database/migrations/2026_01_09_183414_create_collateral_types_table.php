<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collateral_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            // ================================
            // BASIC IDENTIFICATION
            // ================================
            $table->string('name'); 
            // e.g. House, Land, Car, Motorcycle, Equipment

            $table->string('code')->unique(); 
            // e.g. HOUSE, LAND, CAR

            $table->text('description')->nullable();

            // ================================
            // VALUATION & RISK RULES
            // ================================
            $table->boolean('requires_valuation')->default(true);
            // true = must be valued before loan approval

            $table->decimal('default_ltv_ratio', 5, 2)->nullable();
            // Loan-To-Value % (e.g. 70.00 = loan <= 70% of value)

            $table->boolean('depreciates')->default(false);
            // true for vehicles, equipment; false for land

            $table->unsignedSmallInteger('revaluation_interval_days')->nullable();
            // e.g. 365 days for vehicles

            // ================================
            // LEGAL & OPERATIONAL CONTROL
            // ================================
            $table->boolean('requires_ownership_proof')->default(true);
            // title deed, logbook, invoice, etc.

            $table->boolean('requires_insurance')->default(false);
            // some collaterals require insurance cover

            $table->boolean('allow_multiple_per_loan')->default(true);
            // can multiple assets of this type be pledged for one loan?

            // ================================
            // STATUS & GOVERNANCE
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collateral_types');
    }
};
