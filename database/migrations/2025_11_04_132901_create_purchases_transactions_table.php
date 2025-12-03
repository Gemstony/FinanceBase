<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('purchases_transactions')) {
            Schema::create('purchases_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('purchase_order_id');
                $table->string('transaction_type')->default('payment'); // payment, refund, etc.
                $table->string('payment_method')->nullable(); // Bank name or method
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->date('transaction_date');
                $table->string('reference_number')->nullable(); // ties to order_no
                $table->text('notes')->nullable();
                $table->timestamps();

                // Use a short custom index name to avoid MySQL's 64-char identifier limit
                $table->index(['supplier_id', 'purchase_order_id', 'transaction_type'], 'pt_supp_po_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases_transactions');
    }
};