<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('subshop_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('phone');
            $table->text('message');
            $table->string('type')->nullable();
            $table->string('status')->default('queued');
            $table->string('provider')->default('beem');
            $table->text('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'subshop_id']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
