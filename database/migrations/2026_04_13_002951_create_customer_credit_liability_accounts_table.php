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
        Schema::create('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('subshop_id')
                ->constrained('sub_shops')
                ->cascadeOnDelete();
            
            $table->foreignId('chart_of_account_id')
                ->constrained('charts_of_accounts')
                ->restrictOnDelete();
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Ensure one liability account per subshop
            $table->unique('subshop_id');
            
            // Indexes for performance
            $table->index(['subshop_id', 'chart_of_account_id'], 'cc_la_subshop_account_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_liability_accounts');
    }
};
