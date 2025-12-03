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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->datetime('start_date');
            $table->datetime('end_date')->nullable();
            $table->enum('status', ['trial', 'active', 'expired', 'cancelled', 'suspended'])->default('trial');
            $table->boolean('auto_renew')->default(true);
            $table->enum('payment_method', ['card', 'bank_transfer', 'cash', 'paypal', 'stripe', 'mpesa', 'airtel_money', 'other'])->default('card');
            $table->datetime('last_payment_date')->nullable();
            $table->datetime('next_payment_date')->nullable();
            $table->datetime('cancellation_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
