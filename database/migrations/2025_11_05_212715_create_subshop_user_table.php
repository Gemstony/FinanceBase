<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */public function up(): void
    {
        Schema::create('subshop_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subshop_id');
            $table->string('role', 50)->nullable(); // e.g., 'shopkeeper'
            $table->json('permissions')->nullable(); // optional fine-grained overrides
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'subshop_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subshop_id')->references('id')->on('sub_shops')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subshop_user');
    }
};
