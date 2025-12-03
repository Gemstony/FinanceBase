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
if (!Schema::hasColumn('write_offs', 'created_by')) {
    Schema::table('write_offs', function (Blueprint $table) {
        $table->foreignId('created_by')
              ->constrained('users')
              ->onDelete('cascade');
    });
}

}

public function down(): void
{
    Schema::table('write_offs', function (Blueprint $table) {
        $table->dropForeign(['created_by']);
        $table->dropColumn('created_by');
    });
}

};
