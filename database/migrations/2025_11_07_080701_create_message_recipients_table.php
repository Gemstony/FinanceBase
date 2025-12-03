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
        Schema::create('message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->text('delivery_error')->nullable();
            $table->timestamps();

            // Prevent duplicate recipients for the same message
            $table->unique(['message_id', 'user_id']);

            $table->index(['user_id', 'is_read']);
            $table->index(['message_id', 'delivery_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_recipients');
    }
};
