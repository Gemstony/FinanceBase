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

        DB::statement("ALTER TABLE loan_installments MODIFY status ENUM('pending','paid','partial','overdue','restructured','written_off') DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('loan_installments')) {
            return;
        }

        DB::statement("ALTER TABLE loan_installments MODIFY status ENUM('pending','paid','partial','overdue','restructured') DEFAULT 'pending'");
    }
};
