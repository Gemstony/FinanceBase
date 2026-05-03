<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_penalty_applications', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0.00)->after('amount');
            $table->decimal('forgiven_amount', 15, 2)->default(0.00)->after('paid_amount');
            $table->foreignId('forgiven_by')->nullable()->constrained('users')->nullOnDelete()->after('forgiven_amount');
            $table->timestamp('forgiven_at')->nullable()->after('forgiven_by');
            $table->text('forgiveness_reason')->nullable()->after('forgiven_at');
        });
    }

    public function down(): void
    {
        Schema::table('loan_penalty_applications', function (Blueprint $table) {
            $table->dropForeign(['forgiven_by']);
            $table->dropColumn([
                'paid_amount',
                'forgiven_amount',
                'forgiven_by',
                'forgiven_at',
                'forgiveness_reason',
            ]);
        });
    }
};
