<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Shop Status Management
            $table->enum('status', ['active', 'inactive', 'suspended', 'trial'])->default('active')->after('is_active');
            $table->text('suspension_reason')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->timestamp('activated_at')->nullable()->after('suspended_at');

            // Subshop Limits
            $table->integer('max_subshops')->default(2)->after('activated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['status', 'suspension_reason', 'suspended_at', 'activated_at', 'max_subshops']);
        });
    }
};
