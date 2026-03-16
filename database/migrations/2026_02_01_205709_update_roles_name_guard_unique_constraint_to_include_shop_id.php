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
        Schema::table('roles', function (Blueprint $table) {
            // Drop the existing unique constraint
            $table->dropUnique('roles_name_guard_name_unique');
            
            // Add new unique constraint that includes shop_id
            $table->unique(['name', 'guard_name', 'shop_id'], 'roles_name_guard_shop_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Drop the new constraint
            $table->dropUnique('roles_name_guard_shop_unique');
            
            // Restore the original constraint
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};
