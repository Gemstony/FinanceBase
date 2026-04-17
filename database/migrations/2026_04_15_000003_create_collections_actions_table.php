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
        Schema::create('collections_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id')->comment('Related loan');
            $table->unsignedBigInteger('customer_id')->comment('Related customer');
            $table->unsignedBigInteger('subshop_id')->nullable();

            // Action details
            $table->enum('action_type', [
                'phone_call',
                'sms_reminder',
                'email_notice',
                'field_visit',
                'legal_notice',
                'promise_to_pay',
                'payment_received',
                'escalation',
                'write_off_recommendation',
                'other'
            ])->comment('Type of collection action');

            // Scheduling
            $table->dateTime('scheduled_at')->nullable()->comment('When action is scheduled');
            $table->dateTime('completed_at')->nullable()->comment('When action was completed');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'overdue'])
                ->default('pending');

            // Outcome
            $table->text('notes')->nullable()->comment('Action notes/outcome');
            $table->enum('outcome', [
                'successful_payment',
                'promise_made',
                'no_contact',
                'refused_payment',
                'dispute_raised',
                'wrong_contact',
                'other'
            ])->nullable();
            $table->decimal('amount_promised', 15, 2)->nullable();
            $table->date('promise_date')->nullable();
            $table->decimal('amount_collected', 15, 2)->nullable();

            // Assignment
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('Staff assigned to action');
            $table->unsignedBigInteger('created_by')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['loan_id', 'status'], 'idx_collections_loan_status');
            $table->index(['customer_id', 'status'], 'idx_collections_customer_status');
            $table->index(['subshop_id', 'status'], 'idx_collections_subshop_status');
            $table->index(['assigned_to', 'status'], 'idx_collections_assigned_status');
            $table->index(['scheduled_at', 'status'], 'idx_collections_scheduled_status');
            $table->index('action_type', 'idx_collections_action_type');
            $table->index('created_at', 'idx_collections_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections_actions');
    }
};
