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
        Schema::create('sales_orders_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('item_id');
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->integer('quantity');
            $table->enum('vat_type', ['none','inclusive','exclusive'])->default('none');
            $table->decimal('vat_rate', 8, 2)->default(0); // percent
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('base_amount', 15, 2)->default(0); // price*qty before vat
            $table->decimal('line_total', 15, 2)->default(0); // base + vat
            $table->timestamps();

            $table->index(['sales_order_id','item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders_items');
    }
};
