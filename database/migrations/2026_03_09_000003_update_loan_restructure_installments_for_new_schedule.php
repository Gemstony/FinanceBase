<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_restructure_installments')) {
            return;
        }

        Schema::table('loan_restructure_installments', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_restructure_installments', 'loan_id')) {
                $table->foreignId('loan_id')->nullable()->after('id')->constrained('loans')->nullOnDelete();
            }

            // New schedule fields (for storing the restructured schedule)
            if (!Schema::hasColumn('loan_restructure_installments', 'due_date')) {
                $table->date('due_date')->nullable()->after('installment_number');
            }
            if (!Schema::hasColumn('loan_restructure_installments', 'principal_due')) {
                $table->decimal('principal_due', 15, 2)->default(0)->after('due_date');
            }
            if (!Schema::hasColumn('loan_restructure_installments', 'interest_due')) {
                $table->decimal('interest_due', 15, 2)->default(0)->after('principal_due');
            }
            if (!Schema::hasColumn('loan_restructure_installments', 'penalty_due')) {
                $table->decimal('penalty_due', 15, 2)->default(0)->after('interest_due');
            }
            if (!Schema::hasColumn('loan_restructure_installments', 'status')) {
                $table->string('status', 20)->default('pending')->after('penalty_due');
            }
        });

        // Backfill loan_id from parent restructure for existing rows
        DB::statement("UPDATE loan_restructure_installments lri JOIN loan_restructures lr ON lr.id = lri.restructure_id SET lri.loan_id = lr.loan_id WHERE lri.loan_id IS NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('loan_restructure_installments')) {
            return;
        }

        Schema::table('loan_restructure_installments', function (Blueprint $table) {
            foreach (['due_date', 'principal_due', 'interest_due', 'penalty_due', 'status'] as $col) {
                if (Schema::hasColumn('loan_restructure_installments', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('loan_restructure_installments', 'loan_id')) {
                $table->dropConstrainedForeignId('loan_id');
            }
        });
    }
};
