<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0.00)->after('account_number');
            $table->string('currency_code', 3)->default('TZS')->after('opening_balance');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('description');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['opening_balance', 'currency_code']);
        });
    }
};
