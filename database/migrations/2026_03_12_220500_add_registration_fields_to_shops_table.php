<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->string('registration_number')->nullable()->unique()->after('short_name');
            $table->string('license_number')->nullable()->after('registration_number');
            $table->string('tin')->nullable()->after('license_number');
            $table->string('website')->nullable()->after('tin');
            $table->string('country')->nullable()->after('website');
            $table->string('region')->nullable()->after('country');
            $table->string('district')->nullable()->after('region');
            $table->string('street')->nullable()->after('district');
            $table->string('currency')->nullable()->after('street');
            $table->string('logo')->nullable()->after('currency');
            $table->string('email')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique('shops_registration_number_unique');
            $table->dropColumn([
                'short_name',
                'registration_number',
                'license_number',
                'tin',
                'website',
                'country',
                'region',
                'district',
                'street',
                'currency',
                'logo',
                'email',
            ]);
        });
    }
};
