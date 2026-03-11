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
        Schema::create('deposit_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('deposit_product_id')->constrained('deposit_products')->onDelete('restrict');

            $table->string('account_number')->unique();
            $table->decimal('balance', 15, 2)->default(0);

            $table->enum('status', ['active', 'frozen', 'dormant', 'closed'])->default('active');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['subshop_id', 'customer_id']);
            $table->index(['subshop_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_accounts');
    }
};
