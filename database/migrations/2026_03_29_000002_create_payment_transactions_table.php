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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('subshop_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->string('reference')->unique();
            $table->enum('provider', ['mpesa', 'airtel', 'tigo']);
            $table->enum('channel', ['stk', 'c2b', 'b2c']);
            $table->decimal('amount', 15, 2);
            $table->string('phone', 20);
            $table->enum('status', ['initiated', 'pending', 'success', 'failed', 'reversed'])->default('initiated');
            $table->string('external_id')->nullable();
            $table->text('provider_response')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('shop_id');
            $table->index('subshop_id');
            $table->index('customer_id');
            $table->index('loan_id');
            $table->index('reference');
            $table->index('status');
            $table->index('provider');
            $table->index('external_id');
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'provider']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
