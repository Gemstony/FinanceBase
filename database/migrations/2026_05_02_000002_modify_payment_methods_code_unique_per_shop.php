<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Drop the existing unique index on code (global unique)
            $table->dropUnique(['code']);
            
            // Add composite unique index on shop_id + code (unique per shop)
            $table->unique(['shop_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Drop the composite unique index
            $table->dropUnique(['shop_id', 'code']);
            
            // Restore the global unique index on code
            $table->unique(['code']);
        });
    }
};
