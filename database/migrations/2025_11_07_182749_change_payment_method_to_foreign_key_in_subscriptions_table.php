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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the enum column
            $table->dropColumn('payment_method');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Add foreign key column
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the foreign key constraint and column
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Restore the enum column
            $table->enum('payment_method', ['card', 'bank_transfer', 'cash', 'paypal', 'stripe', 'mpesa', 'airtel_money', 'other'])->default('card');
        });
    }
};
