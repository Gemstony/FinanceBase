<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_collaterals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            // ================================
            // RELATIONSHIP
            // ================================
            $table->foreignId('customer_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('collateral_type_id')
                  ->constrained('collateral_types');

            // ================================
            // COLLATERAL IDENTIFICATION
            // ================================
            $table->string('reference_number')->nullable();
            // Title deed number, logbook number, serial number, etc.

            $table->string('description');
            // e.g. Toyota Hilux 2019, House at Sinza

            $table->text('location')->nullable();
            // Physical location of the asset

            // ================================
            // VALUATION SUMMARY
            // ================================
            $table->decimal('estimated_value', 15, 2);

            $table->date('valuation_date')->nullable();

            $table->string('valued_by')->nullable();
            // Officer name or external valuer company

            // ================================
            // OWNERSHIP & INSURANCE STATUS
            // ================================
            $table->string('ownership_description')->nullable();
            // e.g. Sole owner, Joint ownership, Company owned

            $table->boolean('is_insured')->default(false);

            $table->date('insurance_expiry_date')->nullable();
            // Actual policy document stored in collateral_documents

            // ================================
            // OPERATIONAL STATUS
            // ================================
            $table->enum('status', [
                'available',   // can be pledged
                'pledged',     // linked to active loan
                'released',    // loan completed
                'seized',      // taken due to default
                'disposed'     // sold / written off
            ])->default('available');

            // ================================
            // GOVERNANCE
            // ================================
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // ================================
            // INDEXES
            // ================================
            $table->index(['customer_id', 'status']);
            $table->index(['collateral_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_collaterals');
    }
};
