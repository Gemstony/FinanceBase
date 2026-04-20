<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add shop_id column (only if it doesn't exist)
        if (!Schema::hasColumn('customer_credit_liability_accounts', 'shop_id')) {
            Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
                $table->foreignId('shop_id')
                    ->after('id')
                    ->nullable()
                    ->constrained('shops')
                    ->cascadeOnDelete();
            });
        }

        // Migrate existing data: populate shop_id from subshop_id
        DB::statement('
            UPDATE customer_credit_liability_accounts ccla
            JOIN sub_shops ss ON ccla.subshop_id = ss.id
            SET ccla.shop_id = ss.shop_id
        ');

        // Make shop_id required after migration
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable(false)->change();
        });

        // Drop foreign key first (needed before dropping index) - check if exists via information_schema
        $databaseName = (string) (DB::connection()->getDatabaseName());
        $hasSubshopFk = !empty(DB::select(
            'SELECT 1
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$databaseName, 'customer_credit_liability_accounts', 'subshop_id']
        ));
        if ($hasSubshopFk) {
            Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
                $table->dropForeign(['subshop_id']);
            });
        }

        // Drop old unique constraint and index on subshop_id - use raw SQL with IF EXISTS for safety
        try {
            DB::statement('ALTER TABLE customer_credit_liability_accounts DROP INDEX IF EXISTS customer_credit_liability_accounts_subshop_id_unique');
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
        try {
            DB::statement('ALTER TABLE customer_credit_liability_accounts DROP INDEX IF EXISTS cc_la_subshop_account_idx');
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }

        // Drop subshop_id column (only if still exists)
        if (Schema::hasColumn('customer_credit_liability_accounts', 'subshop_id')) {
            Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
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
            [$databaseName, 'customer_credit_liability_accounts', 'customer_credit_liability_accounts_shop_id_unique']
        ));
        if (!$hasShopUnique) {
            Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
                $table->unique('shop_id');
            });
        }

        $hasShopIndex = !empty(DB::select(
            'SELECT 1
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1',
            [$databaseName, 'customer_credit_liability_accounts', 'cc_la_shop_account_idx']
        ));
        if (!$hasShopIndex) {
            Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
                $table->index(['shop_id', 'chart_of_account_id'], 'cc_la_shop_account_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add subshop_id column back
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->foreignId('subshop_id')
                ->after('id')
                ->nullable()
                ->constrained('sub_shops')
                ->cascadeOnDelete();
        });

        // Migrate data back: populate subshop_id from shop_id (use first subshop of each shop)
        DB::statement('
            UPDATE customer_credit_liability_accounts ccla
            JOIN (
                SELECT shop_id, MIN(id) as first_subshop_id 
                FROM sub_shops 
                GROUP BY shop_id
            ) ss ON ccla.shop_id = ss.shop_id
            SET ccla.subshop_id = ss.first_subshop_id
        ');

        // Make subshop_id required
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->foreignId('subshop_id')->nullable(false)->change();
        });

        // Drop foreign key first (needed before dropping index)
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
        });

        // Drop new unique constraint and index on shop_id
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->dropUnique(['shop_id']);
            $table->dropIndex('cc_la_shop_account_idx');
        });

        // Drop shop_id column
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->dropColumn('shop_id');
        });

        // Restore old unique constraint and index
        Schema::table('customer_credit_liability_accounts', function (Blueprint $table) {
            $table->unique('subshop_id');
            $table->index(['subshop_id', 'chart_of_account_id'], 'cc_la_subshop_account_idx');
        });
    }
};
