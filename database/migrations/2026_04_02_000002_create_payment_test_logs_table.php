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
        Schema::create('payment_test_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('config_id');
            $table->string('provider', 50);
            $table->json('test_data')->nullable();
            $table->json('provider_response')->nullable();
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('shop_id');
            $table->index('config_id');
            $table->index('provider');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_test_logs');
    }
};
