<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_collaterals', function (Blueprint $table) {
            $table->string('collateral_image', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('customer_collaterals', function (Blueprint $table) {
            $table->dropColumn('collateral_image');
        });
    }
};
