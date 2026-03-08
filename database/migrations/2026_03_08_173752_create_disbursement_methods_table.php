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
        Schema::create('disbursement_methods', function (Blueprint $table) {

            $table->id();

            $table->foreignId('subshop_id')
                  ->constrained('sub_shops')
                  ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | BASIC INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->string('name');
            // Example: Cash, Bank Transfer, Mobile Money

            $table->string('code', 50)->unique();
            // Example: cash, bank_transfer, mobile_money

            $table->text('description')->nullable();
            // Optional explanation of the method

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION REQUIREMENTS
            |--------------------------------------------------------------------------
            */

            $table->boolean('requires_reference')->default(false);
            // True for bank transfer or mobile money

            $table->boolean('requires_account_details')->default(false);
            // True if bank account or wallet details must be recorded

            /*
            |--------------------------------------------------------------------------
            | SYSTEM MANAGEMENT
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);
            // Allows enabling or disabling a disbursement channel

            $table->boolean('is_system_method')->default(false);
            // Protect default methods from deletion

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursement_methods');
    }
};
