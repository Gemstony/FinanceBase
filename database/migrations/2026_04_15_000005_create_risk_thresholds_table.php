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
        Schema::create('risk_thresholds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subshop_id')->nullable()->comment('Null for global thresholds');

            // PAR thresholds for alerting
            $table->decimal('par30_warning_threshold', 5, 2)->default(5.00)
                ->comment('PAR30 percentage that triggers warning');
            $table->decimal('par30_critical_threshold', 5, 2)->default(10.00)
                ->comment('PAR30 percentage that triggers critical alert');
            $table->decimal('par90_warning_threshold', 5, 2)->default(2.00)
                ->comment('PAR90/NPL percentage that triggers warning');
            $table->decimal('par90_critical_threshold', 5, 2)->default(5.00)
                ->comment('PAR90/NPL percentage that triggers critical alert');

            // Concentration limits
            $table->decimal('max_exposure_per_customer', 15, 2)->nullable()
                ->comment('Maximum loan amount to single customer');
            $table->decimal('max_portfolio_percentage_per_customer', 5, 2)->default(5.00)
                ->comment('Maximum % of portfolio to single customer');
            $table->decimal('max_sector_concentration', 5, 2)->default(25.00)
                ->comment('Maximum % of portfolio in single sector');
            $table->decimal('max_product_concentration', 5, 2)->default(50.00)
                ->comment('Maximum % of portfolio in single product');

            // Provision rates (for calculation)
            $table->decimal('provision_rate_par30', 5, 2)->default(5.00)
                ->comment('Provision rate for PAR30 loans (%)');
            $table->decimal('provision_rate_par60', 5, 2)->default(20.00)
                ->comment('Provision rate for PAR60 loans (%)');
            $table->decimal('provision_rate_par90', 5, 2)->default(50.00)
                ->comment('Provision rate for PAR90 loans (%)');
            $table->decimal('provision_rate_default', 5, 2)->default(100.00)
                ->comment('Provision rate for defaulted loans (%)');

            // Settings
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Unique constraint - one config per subshop
            $table->unique('subshop_id', 'idx_thresholds_subshop_unique');

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Insert default global thresholds
        DB::table('risk_thresholds')->insert([
            'subshop_id' => null,
            'par30_warning_threshold' => 5.00,
            'par30_critical_threshold' => 10.00,
            'par90_warning_threshold' => 2.00,
            'par90_critical_threshold' => 5.00,
            'max_exposure_per_customer' => null,
            'max_portfolio_percentage_per_customer' => 5.00,
            'max_sector_concentration' => 25.00,
            'max_product_concentration' => 50.00,
            'provision_rate_par30' => 5.00,
            'provision_rate_par60' => 20.00,
            'provision_rate_par90' => 50.00,
            'provision_rate_default' => 100.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_thresholds');
    }
};
