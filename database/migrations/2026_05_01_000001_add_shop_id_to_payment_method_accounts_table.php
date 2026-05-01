<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_method_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_method_accounts', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->cascadeOnDelete();
            }
        });

        // Backfill shop_id from subshop_id
        DB::table('payment_method_accounts')
            ->whereNull('shop_id')
            ->update([
                'shop_id' => DB::raw('(SELECT s.shop_id FROM sub_shops s WHERE s.id = payment_method_accounts.subshop_id)')
            ]);

        // Remove duplicates per (shop_id, payment_method) - keep the latest row
        $duplicates = DB::table('payment_method_accounts')
            ->select('shop_id', 'payment_method', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('shop_id')
            ->groupBy('shop_id', 'payment_method')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('payment_method_accounts')
                ->where('shop_id', $dup->shop_id)
                ->where('payment_method', $dup->payment_method)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('payment_method_accounts', function (Blueprint $table) {
            // Enforce shop-level uniqueness
            $table->unique(['shop_id', 'payment_method'], 'pma_shop_method_unique');
            $table->index(['shop_id', 'chart_of_account_id'], 'pma_shop_account_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_method_accounts', function (Blueprint $table) {
            $table->dropUnique('pma_shop_method_unique');
            $table->dropIndex('pma_shop_account_idx');

            if (Schema::hasColumn('payment_method_accounts', 'shop_id')) {
                $table->dropConstrainedForeignId('shop_id');
            }
        });
    }
};
