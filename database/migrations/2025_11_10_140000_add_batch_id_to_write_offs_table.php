<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('write_offs', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('item_id')->constrained('item_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('write_offs', function (Blueprint $table) {
            if (Schema::hasColumn('write_offs', 'batch_id')) {
                $table->dropConstrainedForeignId('batch_id');
            }
        });
    }
};
