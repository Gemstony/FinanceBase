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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->integer('min_quantity')->default(0);
            $table->integer('max_quantity')->nullable();
            $table->string('unit', 50)->default('piece'); // piece, kg, liter, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
