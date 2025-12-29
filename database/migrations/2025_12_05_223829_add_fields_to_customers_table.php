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
        Schema::table('customers', function (Blueprint $table) {

            $table->string('gender')->nullable()->after('name');
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('altenative_phone')->nullable()->after('phone');
            $table->string('region')->nullable()->after('email');
            $table->string('district')->nullable()->after('region');
            $table->string('ward')->nullable()->after('district');
            $table->string('street')->nullable()->after('ward');
            $table->string('house_no')->nullable()->after('street');
            $table->string('work')->nullable()->after('house_no');
            $table->string('work_address')->nullable()->after('work');
            $table->string('id_type')->nullable()->after('work_address');
            $table->string('id_number')->nullable()->after('id_type');
            $table->string('category')->nullable()->after('id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
              $table->dropColumn(['address', 'contact_person']);
        });
    }
};
