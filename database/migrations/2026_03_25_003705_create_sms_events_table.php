<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('event_name'); // loan.disbursed, payment.received, otp.generated, etc.
            $table->unsignedBigInteger('template_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'event_name']);
            $table->index(['shop_id', 'is_enabled']);
            
            // Foreign keys
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('sms_templates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_events');
    }
};