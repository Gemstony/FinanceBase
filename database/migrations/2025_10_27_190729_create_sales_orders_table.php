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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subshop_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('order_no')->unique();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_cash', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['subshop_id','customer_id','created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
