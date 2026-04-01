<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('charts_of_accounts', function (Blueprint $table) {
            // Add shop_id column as nullable first (if it doesn't exist)
            if (!Schema::hasColumn('charts_of_accounts', 'shop_id')) {
                $table->unsignedBigInteger('shop_id')->nullable()->after('subshop_id');
            }
        });

        // Populate shop_id for existing records from sub_shops table
        DB::statement('UPDATE charts_of_accounts coa INNER JOIN sub_shops ss ON coa.subshop_id = ss.id SET coa.shop_id = ss.shop_id WHERE coa.shop_id IS NULL');

        Schema::table('charts_of_accounts', function (Blueprint $table) {
            // Now make shop_id non-nullable and add foreign key constraint
            $table->unsignedBigInteger('shop_id')->nullable(false)->change();
            
            // Add foreign key constraint (if it doesn't exist)
            if (!Schema::hasColumn('charts_of_accounts', 'shop_id')) {
                $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            }
            
            // Drop the global unique constraint on account_code (if it exists)
            try {
                $table->dropUnique('charts_of_accounts_account_code_unique');
            } catch (\Exception $e) {
                // Constraint may not exist, ignore
            }
            
            // Add composite unique constraint for account_code scoped to shop_id (if it doesn't exist)
            try {
                $table->unique(['shop_id', 'account_code'], 'charts_of_accounts_shop_id_account_code_unique');
            } catch (\Exception $e) {
                // Constraint may already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charts_of_accounts', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('charts_of_accounts_shop_id_account_code_unique');
            
            // Restore the global unique constraint on account_code
            $table->unique('account_code', 'charts_of_accounts_account_code_unique');
            
            // Drop the shop_id foreign key and column
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
