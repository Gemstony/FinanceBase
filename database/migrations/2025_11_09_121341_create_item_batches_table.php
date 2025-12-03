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
        Schema::create('item_batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
        $table->string('batch_number')->unique();
        $table->integer('quantity'); // Remaining quantity in this batch
        $table->decimal('cost_price', 10, 2); // Purchase cost per unit
        $table->decimal('selling_price', 10, 2); // Selling price per unit
        $table->date('expire_date')->nullable();
        $table->date('manufacture_date')->nullable();
        $table->timestamps(); // Purchase date via created_at
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_batches');
    }
};
