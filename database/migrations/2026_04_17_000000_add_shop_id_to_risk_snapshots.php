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
        Schema::table('risk_snapshots', function (Blueprint $table) {
            // Add shop_id column for portfolio-wide snapshots
            // This allows shop-specific portfolio snapshots while keeping subshop_id for individual branches
            $table->foreignId('shop_id')->nullable()->after('subshop_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_snapshots', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
