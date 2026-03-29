<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('sms_configs', function (Blueprint $table) {
            $table->text('api_key')->change();
            $table->text('secret_key')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_configs', function (Blueprint $table) {
            $table->string('api_key', 255)->change();
            $table->string('secret_key', 255)->change();
        });
    }
};
