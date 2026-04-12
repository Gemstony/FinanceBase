<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subshop_id')
                ->constrained('sub_shops')
                ->cascadeOnDelete();

            $table->string('payment_method', 50);

            $table->foreignId('chart_of_account_id')
                ->constrained('charts_of_accounts')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['subshop_id', 'payment_method'], 'pma_subshop_method_unique');
            $table->index(['subshop_id', 'chart_of_account_id'], 'pma_subshop_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_accounts');
    }
};
