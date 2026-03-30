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
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Add aggregator fields for ClickPesa
            $table->decimal('fee_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('net_amount', 12, 2)->nullable()->after('fee_amount');
            $table->string('aggregator')->nullable()->after('provider');
            $table->string('channel_provider')->nullable()->after('aggregator');
            
            // Add indexes for new fields
            $table->index('aggregator');
            $table->index('channel_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['aggregator']);
            $table->dropIndex(['channel_provider']);
            
            // Drop columns
            $table->dropColumn(['fee_amount', 'net_amount', 'aggregator', 'channel_provider']);
        });
    }
};
