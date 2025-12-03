<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ui_settings', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->cascadeOnDelete();
            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('ui_settings', function (Blueprint $table) {
            $table->dropUnique(['shop_id']);
            $table->dropConstrainedForeignId('shop_id');
        });
    }
};
