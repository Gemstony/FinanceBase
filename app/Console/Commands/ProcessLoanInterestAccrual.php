<?php

namespace App\Console\Commands;

use App\Services\Loans\Interest\InterestAccrualEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessLoanInterestAccrual extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loans:accrue-interest {--date= : Accrue interest as of a specific date (Y-m-d)}';

    /**
     * The console command description.
     */
    protected $description = 'Accrue daily interest for active loans and record loan_interest_accruals entries';

    public function __construct(
        protected InterestAccrualEngine $interestAccrualEngine
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $date = (string) $this->option('date');
            $asOf = $date !== '' ? Carbon::parse($date)->startOfDay() : null;

            $this->interestAccrualEngine->processDailyAccrual($asOf);

            $this->info('Daily interest accrual processed successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Failed to process daily interest accrual', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->error('Failed to process daily interest accrual. Check logs for details.');
            return Command::FAILURE;
        }
    }
}
