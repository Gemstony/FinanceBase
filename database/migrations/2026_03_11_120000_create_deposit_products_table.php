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
        Schema::create('deposit_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            $table->string('name');
            $table->enum('type', ['savings', 'fixed', 'compulsory'])->default('savings');

            $table->decimal('interest_rate', 10, 2)->default(0);
            $table->decimal('minimum_balance', 15, 2)->default(0);
            $table->decimal('withdrawal_fee', 15, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['subshop_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_products');
    }
};
