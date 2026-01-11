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
Schema::create('loan_fees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');

    $table->string('name');
    $table->string('code')->unique();

    $table->enum('fee_type', ['FIXED', 'PERCENTAGE']);

    $table->decimal('amount', 12, 2)->nullable();
    $table->decimal('percentage', 5, 2)->nullable();

    // Application
    $table->enum('apply_on', ['DISBURSEMENT', 'REPAYMENT', 'TOP_UP']);

    // Accounting
    $table->foreignId('income_account_id')->constrained('charts_of_accounts');

    $table->boolean('is_active')->default(true);

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_fees');
    }
};
