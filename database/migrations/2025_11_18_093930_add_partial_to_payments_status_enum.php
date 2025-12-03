<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'partial' to payments.status enum
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','completed','failed','refunded','cancelled','partial') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert to previous enum without 'partial'
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
