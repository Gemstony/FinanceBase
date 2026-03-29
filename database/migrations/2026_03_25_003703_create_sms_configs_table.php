<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('provider'); // beem, twilio, etc.
            $table->string('api_url');
            $table->string('api_key'); // will be encrypted
            $table->string('secret_key'); // will be encrypted
            $table->string('sender_id');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('rate_limit_per_minute')->default(60);
            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'is_active']);
            $table->index(['shop_id', 'is_default']);
            
            // Foreign key
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_configs');
    }
};