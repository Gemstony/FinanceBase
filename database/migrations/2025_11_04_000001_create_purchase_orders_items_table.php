<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('purchase_order_id')
                ->references('id')->on('purchase_orders')
                ->onDelete('cascade');
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders_items');
    }
};
