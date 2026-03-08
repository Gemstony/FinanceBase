<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_restructures')) {
            return;
        }

        Schema::table('loan_restructures', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_restructures', 'restructure_date')) {
                $table->date('restructure_date')->nullable()->after('loan_id');
            }

            if (!Schema::hasColumn('loan_restructures', 'old_principal_balance')) {
                $table->decimal('old_principal_balance', 15, 2)->default(0)->after('reason');
            }
            if (!Schema::hasColumn('loan_restructures', 'old_interest_balance')) {
                $table->decimal('old_interest_balance', 15, 2)->default(0)->after('old_principal_balance');
            }
            if (!Schema::hasColumn('loan_restructures', 'old_penalty_balance')) {
                $table->decimal('old_penalty_balance', 15, 2)->default(0)->after('old_interest_balance');
            }

            if (!Schema::hasColumn('loan_restructures', 'new_term')) {
                $table->integer('new_term')->nullable()->after('new_interest_rate');
            }
            if (!Schema::hasColumn('loan_restructures', 'grace_period')) {
                $table->integer('grace_period')->default(0)->after('new_term');
            }
            if (!Schema::hasColumn('loan_restructures', 'capitalized_interest')) {
                $table->decimal('capitalized_interest', 15, 2)->default(0)->after('grace_period');
            }

            if (!Schema::hasColumn('loan_restructures', 'status')) {
                $table->string('status', 20)->default('pending')->after('capitalized_interest');
            }

            if (!Schema::hasColumn('loan_restructures', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            }

            if (!Schema::hasColumn('loan_restructures', 'executed_at')) {
                $table->timestamp('executed_at')->nullable()->after('approved_at');
            }
        });

        // Normalize existing records to have a status.
        DB::statement("UPDATE loan_restructures SET status = 'approved' WHERE status IS NULL AND approved_at IS NOT NULL");
        DB::statement("UPDATE loan_restructures SET status = 'pending' WHERE status IS NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('loan_restructures')) {
            return;
        }

        Schema::table('loan_restructures', function (Blueprint $table) {
            foreach (['restructure_date','old_principal_balance','old_interest_balance','old_penalty_balance','new_term','grace_period','capitalized_interest','status','requested_by','executed_at'] as $col) {
                if (Schema::hasColumn('loan_restructures', $col)) {
                    if (in_array($col, ['requested_by'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
