<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropColumn('reconciled_at');
        });
    }
};
