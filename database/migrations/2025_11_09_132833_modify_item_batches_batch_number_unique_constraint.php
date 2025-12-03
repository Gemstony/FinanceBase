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
        Schema::table('item_batches', function (Blueprint $table) {
            // Drop the existing unique constraint on batch_number
            $table->dropUnique(['batch_number']);
            
            // Add composite unique constraint on (item_id, batch_number)
            $table->unique(['item_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_batches', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique(['item_id', 'batch_number']);
            
            // Restore the original unique constraint on batch_number
            $table->unique('batch_number');
        });
    }
};
