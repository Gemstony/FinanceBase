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
        // Drop existing column if it was already added with JSON type (has CHECK constraint)
        if (Schema::hasColumn('payment_configs', 'config_json')) {
            Schema::table('payment_configs', function (Blueprint $table) {
                $table->dropColumn('config_json');
            });
        }

        // Add as TEXT to avoid MySQL's automatic json_valid() CHECK constraint
        // since we store encrypted (not raw JSON) data
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->text('config_json')->nullable()->after('passkey');
        });

        // Update provider enum to include clickpesa and azampay
        DB::statement("ALTER TABLE payment_configs MODIFY COLUMN provider ENUM('mpesa', 'airtel', 'tigo', 'clickpesa', 'azampay') NOT NULL");

        // Update payment_transactions provider column to VARCHAR for flexibility
        DB::statement('ALTER TABLE payment_transactions MODIFY COLUMN provider VARCHAR(50) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn('config_json');
        });

        DB::statement("ALTER TABLE payment_configs MODIFY COLUMN provider ENUM('mpesa', 'airtel', 'tigo') NOT NULL");
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN provider ENUM('mpesa', 'airtel', 'tigo') NOT NULL");
    }
};
