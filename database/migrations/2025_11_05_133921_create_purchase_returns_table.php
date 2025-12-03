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
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subshop_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->integer('quantity_returned');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id']);
            $table->index(['purchase_order_item_id']);
            $table->index(['item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
