<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop existing FK on item_id if present to allow nulls
            try { $table->dropForeign('transactions_item_id_foreign'); } catch (\Throwable $e) {}
        });

        // Make item_id nullable via raw SQL (avoids doctrine/dbal requirement)
        DB::statement('ALTER TABLE `transactions` MODIFY `item_id` BIGINT UNSIGNED NULL');

        // Re-create FK (nullable FKs are allowed)
        Schema::table('transactions', function (Blueprint $table) {
            try { $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade'); } catch (\Throwable $e) {}
        });

        // Extend enum to include payment
        DB::statement("ALTER TABLE `transactions` MODIFY `transaction_type` ENUM('purchase','sale','payment') NOT NULL DEFAULT 'purchase'");
    }

    public function down(): void
    {
        // Revert enum (remove payment)
        DB::statement("ALTER TABLE `transactions` MODIFY `transaction_type` ENUM('purchase','sale') NOT NULL DEFAULT 'purchase'");

        // Set item_id NOT NULL again (may fail if nulls exist)
        // Consider cleaning data before down migration in real environments
        DB::statement('ALTER TABLE `transactions` MODIFY `item_id` BIGINT UNSIGNED NOT NULL');

        // FK is already in place
    }
};
