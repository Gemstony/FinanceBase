<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_deposit_forfeiture_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subshop_id');
            $table->unsignedBigInteger('chart_of_account_id');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('subshop_id');
            $table->foreign('subshop_id')->references('id')->on('sub_shops')->onDelete('cascade');
            $table->foreign('chart_of_account_id')->references('id')->on('charts_of_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_deposit_forfeiture_accounts');
    }
};
