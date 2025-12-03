<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop FKs and columns if they exist
            try { $table->dropForeign('transactions_item_id_foreign'); } catch (\Throwable $e) {}
        });

        if (Schema::hasColumn('transactions', 'item_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('item_id');
            });
        }
        if (Schema::hasColumn('transactions', 'quantity')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }
        if (Schema::hasColumn('transactions', 'unit_price')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('unit_price');
            });
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('transaction_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            // Recreate columns (without FKs to keep down simple)
            if (!Schema::hasColumn('transactions', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable();
            }
            if (!Schema::hasColumn('transactions', 'quantity')) {
                $table->integer('quantity')->default(0);
            }
            if (!Schema::hasColumn('transactions', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->default(0);
            }
        });
    }
};
