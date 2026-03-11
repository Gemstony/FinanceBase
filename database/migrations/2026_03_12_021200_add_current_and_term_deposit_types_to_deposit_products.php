<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_products', function (Blueprint $table) {
            $table->enum('type', ['savings', 'current', 'term_deposit', 'fixed', 'compulsory'])
                ->default('savings')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_products', function (Blueprint $table) {
            $table->enum('type', ['savings', 'fixed', 'compulsory'])
                ->default('savings')
                ->change();
        });
    }
};
