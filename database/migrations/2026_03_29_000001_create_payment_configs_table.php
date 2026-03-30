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
        Schema::create('payment_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->enum('provider', ['mpesa', 'airtel', 'tigo']);
            $table->string('api_url');
            $table->text('api_key'); // Encrypted
            $table->text('secret_key'); // Encrypted
            $table->string('shortcode')->nullable();
            $table->text('passkey')->nullable(); // Encrypted
            $table->enum('environment', ['sandbox', 'live'])->default('sandbox');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('shop_id');
            $table->index('provider');
            $table->index('is_active');
            $table->index('is_default');

            // Unique constraint: one provider per shop
            $table->unique(['shop_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_configs');
    }
};
