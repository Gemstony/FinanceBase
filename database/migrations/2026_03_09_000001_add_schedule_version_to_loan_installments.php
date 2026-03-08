<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_installments')) {
            return;
        }

        Schema::table('loan_installments', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_installments', 'schedule_version')) {
                $table->unsignedInteger('schedule_version')->default(1)->after('installment_number');
            }
        });

        DB::statement("UPDATE loan_installments SET schedule_version = 1 WHERE schedule_version IS NULL");

        // Extend status to include 'restructured' (raw SQL to avoid doctrine/dbal dependency)
        DB::statement("ALTER TABLE loan_installments MODIFY status ENUM('pending','paid','partial','overdue','restructured') DEFAULT 'pending'");

        // Update unique constraint to include schedule_version so multiple schedules can coexist.
        $indexes = DB::select(
            "SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols\n" .
            "FROM information_schema.statistics\n" .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_installments'\n" .
            "GROUP BY INDEX_NAME, NON_UNIQUE"
        );

        $hasNewUnique = false;
        foreach ($indexes as $idx) {
            $name = (string) ($idx->INDEX_NAME ?? '');
            $nonUnique = (int) ($idx->NON_UNIQUE ?? 1);
            $cols = (string) ($idx->cols ?? '');

            if ($nonUnique === 0 && $cols === 'loan_id,schedule_version,installment_number') {
                $hasNewUnique = true;
            }

            // Drop legacy unique constraint that prevents multiple schedule versions.
            if ($nonUnique === 0 && $cols === 'loan_id,installment_number') {
                try {
                    DB::statement("ALTER TABLE loan_installments DROP INDEX `{$name}`");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        // Also drop by the expected name if it exists but is wrong/leftover.
        try {
            DB::statement('ALTER TABLE loan_installments DROP INDEX unique_loan_installment');
        } catch (\Throwable $e) {
            // ignore
        }

        $nameStillExists = (int) (DB::table('information_schema.statistics')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'loan_installments')
            ->where('INDEX_NAME', 'unique_loan_installment')
            ->count()) > 0;

        if (!$hasNewUnique) {
            if ($nameStillExists) {
                DB::statement('ALTER TABLE loan_installments ADD UNIQUE INDEX unique_loan_installment_v2 (loan_id, schedule_version, installment_number)');
            } else {
                DB::statement('ALTER TABLE loan_installments ADD UNIQUE INDEX unique_loan_installment (loan_id, schedule_version, installment_number)');
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('loan_installments')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE loan_installments DROP INDEX unique_loan_installment');
        } catch (\Throwable $e) {
            // ignore
        }

        DB::statement('ALTER TABLE loan_installments ADD UNIQUE INDEX unique_loan_installment (loan_id, installment_number)');

        // Remove 'restructured' from enum
        DB::statement("ALTER TABLE loan_installments MODIFY status ENUM('pending','paid','partial','overdue') DEFAULT 'pending'");

        Schema::table('loan_installments', function (Blueprint $table) {
            if (Schema::hasColumn('loan_installments', 'schedule_version')) {
                $table->dropColumn('schedule_version');
            }
        });
    }
};
