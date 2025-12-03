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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subshop_id');
            $table->string('name');
            $table->string('account_name')->nullable();
            $table->string('account_number');
            $table->string('branch')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('subshop_id')->references('id')->on('sub_shops')->onDelete('cascade');
            $table->unique(['subshop_id', 'account_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
