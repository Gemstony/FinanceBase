<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subshop_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name')->nullable();
            $table->string('ip_address');
            $table->unsignedInteger('port')->default(9100);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('subshop_id')->references('id')->on('sub_shops')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['subshop_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_settings');
    }
};
