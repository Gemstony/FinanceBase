<?php

namespace App\Console\Commands\Risk;

use App\Repositories\Loans\LoanRepository;
use App\Services\Loans\Risk\DpdCalculator;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to update loan risk statuses based on current DPD.
 *
 * This should be run daily to keep risk classifications current.
 */
class UpdateRiskStatusesCommand extends Command
{
    protected $signature = 'risk:update-statuses
                            {--subshop= : Specific subshop ID to process}
                            {--chunk=500 : Number of loans to process per chunk}';

    protected $description = 'Update loan risk statuses and max_days_overdue for all active loans';

    public function __construct(
        private readonly LoanRepository $loanRepository,
        private readonly DpdCalculator $dpdCalculator,
        private readonly PortfolioRiskCalculator $portfolioRisk
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $subshopId = $this->option('subshop');
        $chunkSize = (int) $this->option('chunk');

        $this->info('Starting risk status update...');

        $query = $this->loanRepository->activeLoansQuery();

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
            $this->info("Processing subshop: {$subshopId}");
        }

        $totalUpdated = 0;
        $totalProcessed = 0;

        $query->chunkById($chunkSize, function ($loans) use (&$totalUpdated, &$totalProcessed) {
            $loanIds = $loans->pluck('id')->toArray();
            $totalProcessed += count($loanIds);

            // Bulk calculate DPD for all loans
            $dpdMap = $this->dpdCalculator->bulkCalculateMaxDpd($loanIds);

            foreach ($loans as $loan) {
                $maxDpd = $dpdMap[$loan->id] ?? 0;
                $riskStatus = $this->dpdCalculator->classifyByDpd($maxDpd);

                // Only update if changed
                if ($loan->risk_status !== $riskStatus || $loan->max_days_overdue !== $maxDpd) {
                    $this->loanRepository->updateRiskStatus($loan->id, $riskStatus, $maxDpd);
                    $totalUpdated++;
                }
            }

            $this->info("Processed {$totalProcessed} loans...");
        });

        $this->info("Risk status update complete. Total processed: {$totalProcessed}, Updated: {$totalUpdated}");
        Log::info("Risk status update complete. Total processed: {$totalProcessed}, Updated: {$totalUpdated}");

        return self::SUCCESS;
    }
}
