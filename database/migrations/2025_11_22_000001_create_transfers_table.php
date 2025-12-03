<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('source_subshop_id');
            $table->unsignedBigInteger('destination_subshop_id');
            $table->enum('status', ['draft','approved','dispatched','partially_received','received','cancelled'])->default('draft');
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->unsignedBigInteger('dispatched_by_id')->nullable();
            $table->unsignedBigInteger('received_by_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
