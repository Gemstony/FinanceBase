<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('source_item_id');
            $table->unsignedBigInteger('destination_item_id')->nullable();
            $table->decimal('planned_qty', 15, 3)->default(0);
            $table->decimal('dispatched_qty', 15, 3)->default(0);
            $table->decimal('received_qty', 15, 3)->default(0);
            $table->string('uom')->nullable();
            $table->string('item_name_snapshot')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};
