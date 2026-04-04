<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('channel', 50)->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->enum('channel', ['stk', 'c2b', 'b2c'])->nullable()->after('provider');
        });
    }
};
