<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add shop_id column (only if it doesn't exist)
        if (!Schema::hasColumn('customer_deposit_liability_accounts', 'shop_id')) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->foreignId('shop_id')
                    ->after('id')
                    ->nullable()
                    ->constrained('shops')
                    ->cascadeOnDelete();
            });
        }

        // Migrate existing data: populate shop_id from subshop_id
        DB::statement('
            UPDATE customer_deposit_liability_accounts cdla
            JOIN sub_shops ss ON cdla.subshop_id = ss.id
            SET cdla.shop_id = ss.shop_id
        ');

        // Make shop_id required after migration
        Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable(false)->change();
        });

        // Drop foreign key first (needed before dropping index) - check if exists via information_schema
        $databaseName = (string) DB::connection()->getDatabaseName();
        $hasSubshopFk = !empty(DB::select(
            'SELECT 1
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$databaseName, 'customer_deposit_liability_accounts', 'subshop_id']
        ));
        if ($hasSubshopFk) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->dropForeign(['subshop_id']);
            });
        }

        // Drop old unique constraint and index on subshop_id - use raw SQL with IF EXISTS for safety
        try {
            DB::statement('ALTER TABLE customer_deposit_liability_accounts DROP INDEX IF EXISTS customer_deposit_liability_accounts_subshop_id_unique');
        } catch (\Exception $e) {
        }

        // Drop subshop_id column (only if still exists)
        if (Schema::hasColumn('customer_deposit_liability_accounts', 'subshop_id')) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->dropColumn('subshop_id');
            });
        }

        // Add new unique constraint and index on shop_id (only if not exists) - check via information_schema
        $hasShopUnique = !empty(DB::select(
            'SELECT 1
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1',
            [$databaseName, 'customer_deposit_liability_accounts', 'customer_deposit_liability_accounts_shop_id_unique']
        ));
        if (!$hasShopUnique) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->unique('shop_id');
            });
        }
    }

    public function down(): void
    {
        // Add subshop_id column back
        Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
            $table->foreignId('subshop_id')
                ->after('id')
                ->nullable()
                ->constrained('sub_shops')
                ->cascadeOnDelete();
        });

        // Migrate data back: populate subshop_id from shop_id (use first subshop of each shop)
        DB::statement('
            UPDATE customer_deposit_liability_accounts cdla
            JOIN (
                SELECT shop_id, MIN(id) as first_subshop_id 
                FROM sub_shops 
                GROUP BY shop_id
            ) ss ON cdla.shop_id = ss.shop_id
            SET cdla.subshop_id = ss.first_subshop_id
        ');

        // Make subshop_id required
        Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
            $table->foreignId('subshop_id')->nullable(false)->change();
        });

        // Drop foreign key first (needed before dropping index) - check if exists
        $databaseName = (string) DB::connection()->getDatabaseName();
        $hasShopFk = !empty(DB::select(
            'SELECT 1
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$databaseName, 'customer_deposit_liability_accounts', 'shop_id']
        ));
        if ($hasShopFk) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->dropForeign(['shop_id']);
            });
        }

        // Drop new unique constraint on shop_id
        try {
            DB::statement('ALTER TABLE customer_deposit_liability_accounts DROP INDEX IF EXISTS customer_deposit_liability_accounts_shop_id_unique');
        } catch (\Exception $e) {
        }

        // Drop shop_id column
        if (Schema::hasColumn('customer_deposit_liability_accounts', 'shop_id')) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->dropColumn('shop_id');
            });
        }

        // Restore old unique constraint
        $hasSubshopUnique = !empty(DB::select(
            'SELECT 1
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1',
            [$databaseName, 'customer_deposit_liability_accounts', 'customer_deposit_liability_accounts_subshop_id_unique']
        ));
        if (!$hasSubshopUnique) {
            Schema::table('customer_deposit_liability_accounts', function (Blueprint $table) {
                $table->unique('subshop_id');
            });
        }
    }
};
