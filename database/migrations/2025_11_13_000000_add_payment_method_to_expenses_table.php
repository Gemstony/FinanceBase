<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'payment_method')) {
                $table->unsignedBigInteger('payment_method')->nullable()->after('description');
                $table->foreign('payment_method')->references('id')->on('banks')->onDelete('set null');
                $table->index('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'payment_method')) {
                $table->dropForeign(['payment_method']);
                $table->dropIndex(['payment_method']);
                $table->dropColumn('payment_method');
            }
        });
    }
};
