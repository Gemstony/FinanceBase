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
        Schema::table('payment_methods', function (Blueprint $table) {
            // Add shop_id column
            if (!Schema::hasColumn('payment_methods', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->cascadeOnDelete();
            }
            
            // Add status column if it doesn't exist
            if (!Schema::hasColumn('payment_methods', 'status')) {
                $table->boolean('status')->default(true)->after('sort_order');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['shop_id', 'status']);
        });
    }
};
