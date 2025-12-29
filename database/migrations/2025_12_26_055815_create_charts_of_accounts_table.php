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
        Schema::create('charts_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

            $table->string('account_code', 20)->unique();
            $table->string('account_name');
            $table->text('description')->nullable();

            $table->foreignId('account_class_id')->constrained('account_classes');
            $table->foreignId('account_group_id')->constrained('account_groups');

            $table->enum('cash_flow_impact', ['IN','OUT','NONE'])->default('NONE');
            $table->enum('cash_flow_category', ['OPERATING','INVESTING','FINANCING'])->nullable();

            $table->enum('equity_impact', ['INCREASE','DECREASE','NONE'])->default('NONE');
            $table->enum('equity_category', ['CAPITAL','RETAINED_EARNINGS','RESERVES'])->nullable();

            $table->boolean('is_customer_account')->default(false);
            $table->boolean('is_system_account')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charts_of_accounts');
    }
};
