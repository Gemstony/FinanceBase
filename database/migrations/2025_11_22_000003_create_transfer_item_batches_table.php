<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_item_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_item_id');
            $table->unsignedBigInteger('source_item_batch_id');
            $table->unsignedBigInteger('destination_item_batch_id')->nullable();
            $table->string('batch_number');
            $table->date('expire_date')->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('selling_price_snapshot', 15, 2)->nullable();
            $table->decimal('planned_qty', 15, 3)->default(0);
            $table->decimal('dispatched_qty', 15, 3)->default(0);
            $table->decimal('received_qty', 15, 3)->default(0);
            $table->decimal('damaged_qty', 15, 3)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_item_batches');
    }
};
