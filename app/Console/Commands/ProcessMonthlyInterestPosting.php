<?php

namespace App\Console\Commands;

use App\Services\Loans\Interest\MonthlyInterestPostingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMonthlyInterestPosting extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loans:post-monthly-interest 
                            {--date= : Post interest as of a specific date (Y-m-d)}
                            {--loan-id= : Process only a specific loan ID}';

    /**
     * The console command description.
     */
    protected $description = 'Post monthly interest from accruals to income and update installments';

    public function __construct(
        protected MonthlyInterestPostingService $monthlyPostingService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $date = (string) $this->option('date');
            $loanId = (string) $this->option('loan-id');
            $asOf = $date !== '' ? Carbon::parse($date)->startOfDay() : Carbon::today()->startOfDay();

            $this->info("Processing monthly interest posting as of {$asOf->toDateString()}...");

            if ($loanId !== '') {
                // Process single loan
                $loan = \App\Models\Loans::query()->findOrFail((int) $loanId);
                $posting = $this->monthlyPostingService->postInterestForLoan($loan, $asOf);

                if ($posting) {
                    $this->info("Successfully posted interest: {$posting->interest_amount}");
                    return Command::SUCCESS;
                } else {
                    $this->info("No unposted interest found for loan {$loan->loan_code}");
                    return Command::SUCCESS;
                }
            }

            // Process all loans
            $results = $this->monthlyPostingService->processMonthlyPosting($asOf);

            $this->info("Monthly interest posting completed:");
            $this->info("  - Loans processed: {$results['processed']}");
            $this->info("  - Loans skipped (no accruals): {$results['skipped']}");
            $this->info("  - Errors: {$results['errors']}");
            $this->info("  - Total interest posted: {$results['total_interest']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Failed to process monthly interest posting', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->error('Failed to process monthly interest posting. Check logs for details.');
            return Command::FAILURE;
        }
    }
}
