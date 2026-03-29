<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('name');
            $table->string('event'); // loan.disbursed, payment.received, otp.generated, etc.
            $table->text('message_template');
            $table->json('variables'); // Store variable names as JSON array
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'event']);
            $table->index(['shop_id', 'is_active']);
            
            // Foreign key
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};