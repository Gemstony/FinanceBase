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
        Schema::create('collateral_documents', function (Blueprint $table) {
            $table->id();

            // ================================
            // RELATIONS
            // ================================
            $table->foreignId('customer_collateral_id')
                ->constrained('customer_collaterals')
                ->cascadeOnDelete();

            // ================================
            // DOCUMENT METADATA
            // ================================
            // e.g. title_deed, logbook, photo, valuation_report, insurance
            $table->string('document_type');

            // Relative path in storage (NOT public URL)
            $table->string('file_path');

            // pdf, jpg, png, docx
            $table->string('mime_type', 50);

            // File size in bytes (audit & validation)
            $table->unsignedBigInteger('file_size');

            // Optional original filename
            $table->string('original_filename')->nullable();

            // ================================
            // VERIFICATION & COMPLIANCE
            // ================================
            $table->boolean('is_verified')->default(false);

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            // ================================
            // AUDIT
            // ================================
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ================================
            // INDEXES
            // ================================
            $table->index(['customer_collateral_id', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_documents');
    }
};
