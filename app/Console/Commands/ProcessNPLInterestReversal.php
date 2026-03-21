<?php

namespace App\Console\Commands;

use App\Services\Loans\Interest\NPLInterestReversalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessNPLInterestReversal extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loans:reverse-npl-interest 
                            {--date= : Reverse interest as of a specific date (Y-m-d)}';

    /**
     * The console command description.
     */
    protected $description = 'Reverse interest for loans that exceeded 90 days overdue (NPL)';

    public function __construct(
        protected NPLInterestReversalService $nplReversalService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $date = (string) $this->option('date');
            $asOf = $date !== '' ? Carbon::parse($date)->startOfDay() : Carbon::today()->startOfDay();

            $this->info("Processing NPL interest reversals as of {$asOf->toDateString()}...");

            $results = $this->nplReversalService->processNPLReversals($asOf);

            $this->info("NPL interest reversal completed:");
            $this->info("  - Loans processed: {$results['processed']}");
            $this->info("  - Loans became NPL: {$results['loans_became_npl']}");
            $this->info("  - Reversals performed: {$results['reversals']}");
            $this->info("  - Total reversed: {$results['total_reversed']}");
            $this->info("  - Errors: {$results['errors']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Failed to process NPL interest reversal', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->error('Failed to process NPL interest reversal. Check logs for details.');
            return Command::FAILURE;
        }
    }
}
