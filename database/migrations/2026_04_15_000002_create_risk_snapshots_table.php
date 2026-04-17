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
        Schema::create('risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->comment('Date of the snapshot');
            $table->unsignedBigInteger('subshop_id')->nullable()->comment('Null for portfolio-wide snapshot');

            // Portfolio metrics
            $table->decimal('portfolio_outstanding', 15, 2)->default(0);
            $table->unsignedInteger('total_active_loans')->default(0);
            $table->unsignedInteger('performing_loans')->default(0);
            $table->unsignedInteger('delinquent_loans')->default(0);

            // PAR metrics
            $table->decimal('par30_rate', 5, 2)->default(0)->comment('PAR30 percentage');
            $table->decimal('par60_rate', 5, 2)->default(0)->comment('PAR60 percentage');
            $table->decimal('par90_rate', 5, 2)->default(0)->comment('PAR90 percentage');
            $table->decimal('par180_rate', 5, 2)->default(0)->comment('PAR180 percentage');

            // PAR amounts
            $table->decimal('par30_amount', 15, 2)->default(0);
            $table->decimal('par60_amount', 15, 2)->default(0);
            $table->decimal('par90_amount', 15, 2)->default(0);
            $table->decimal('par180_amount', 15, 2)->default(0);

            // NPL metrics
            $table->decimal('npl_rate', 5, 2)->default(0)->comment('NPL ratio (PAR90)');
            $table->decimal('npl_amount', 15, 2)->default(0);

            // Risk distribution
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('par30_count')->default(0);
            $table->unsignedInteger('par60_count')->default(0);
            $table->unsignedInteger('par90_count')->default(0);
            $table->unsignedInteger('default_count')->default(0);

            // Metadata
            $table->string('created_by')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['snapshot_date', 'subshop_id'], 'idx_snapshots_date_subshop');
            $table->index('snapshot_date', 'idx_snapshots_date');
            $table->index('subshop_id', 'idx_snapshots_subshop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_snapshots');
    }
};
