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
        Schema::create('promises_to_pay', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id')->comment('Related loan');
            $table->unsignedBigInteger('customer_id')->comment('Related customer');
            $table->unsignedBigInteger('subshop_id')->nullable();
            $table->unsignedBigInteger('collections_action_id')->nullable()->comment('Related action');

            // Promise details
            $table->decimal('amount_promised', 15, 2)->comment('Amount customer promised to pay');
            $table->date('promise_date')->comment('Date customer promised to pay by');
            $table->enum('promise_type', ['full_payment', 'partial_payment', 'installment_resumption'])
                ->default('partial_payment');

            // Status tracking
            $table->enum('status', ['pending', 'fulfilled', 'broken', 'cancelled', 'overdue'])
                ->default('pending');
            $table->dateTime('fulfilled_at')->nullable();
            $table->decimal('amount_fulfilled', 15, 2)->nullable();

            // Follow-up
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('follow_up_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);

            // Notes
            $table->text('promise_notes')->nullable();
            $table->text('outcome_notes')->nullable();

            // Tracking
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('collections_action_id')->references('id')->on('collections_actions')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['loan_id', 'status'], 'idx_promises_loan_status');
            $table->index(['customer_id', 'status'], 'idx_promises_customer_status');
            $table->index(['promise_date', 'status'], 'idx_promises_date_status');
            $table->index(['subshop_id', 'status'], 'idx_promises_subshop_status');
            $table->index('collections_action_id', 'idx_promises_action_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promises_to_pay');
    }
};
