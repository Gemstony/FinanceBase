<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_installments')) {
            return;
        }

        // Drop any legacy UNIQUE index on (loan_id, installment_number) because it prevents versioned schedules.
        $indexes = DB::select(
            "SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols\n" .
            "FROM information_schema.statistics\n" .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_installments'\n" .
            "GROUP BY INDEX_NAME, NON_UNIQUE"
        );

        $hasVersionedUnique = false;

        foreach ($indexes as $idx) {
            $name = (string) ($idx->INDEX_NAME ?? '');
            $nonUnique = (int) ($idx->NON_UNIQUE ?? 1);
            $cols = (string) ($idx->cols ?? '');

            if ($nonUnique === 0 && $cols === 'loan_id,schedule_version,installment_number') {
                $hasVersionedUnique = true;
                continue;
            }

            if ($nonUnique === 0 && $cols === 'loan_id,installment_number') {
                try {
                    DB::statement("ALTER TABLE loan_installments DROP INDEX `{$name}`");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        // Ensure the correct unique index exists (use the canonical name used by application/migrations).
        if (!$hasVersionedUnique) {
            try {
                DB::statement('ALTER TABLE loan_installments ADD UNIQUE INDEX unique_loan_installment (loan_id, schedule_version, installment_number)');
            } catch (\Throwable $e) {
                // If the name is already taken, fall back to _v2.
                DB::statement('ALTER TABLE loan_installments ADD UNIQUE INDEX unique_loan_installment_v2 (loan_id, schedule_version, installment_number)');
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('loan_installments')) {
            return;
        }

        // Best-effort rollback: drop versioned uniques.
        try {
            DB::statement('ALTER TABLE loan_installments DROP INDEX unique_loan_installment');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::statement('ALTER TABLE loan_installments DROP INDEX unique_loan_installment_v2');
        } catch (\Throwable $e) {
            // ignore
        }

        // Restore legacy unique if needed.
        try {
            DB::statement('ALTER TABLE loan_installments ADD UNIQUE INDEX unique_loan_installment (loan_id, installment_number)');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
