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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            $table->string('voucher_number')->unique();

            // receipt or payment
            $table->enum('voucher_type', ['receipt', 'payment']);

            // manual or automatic
            $table->enum('source_type', ['manual', 'system'])->default('system');

            // reference to other modules
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // total voucher amount
            $table->decimal('total_amount', 15, 2);

            // payment channel
            $table->string('payment_method')->nullable();

            // bank or cash account used
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();

            $table->text('description')->nullable();

            // status control
            $table->enum('status', ['draft', 'posted', 'cancelled'])
                ->default('posted');

            // organization structure
            $table->foreignId('subshop_id')
                ->nullable()
                ->constrained('sub_shops')
                ->nullOnDelete();

            // audit fields
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->timestamp('voucher_date');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
