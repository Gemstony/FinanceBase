<?php

namespace App\Console\Commands;

use App\Services\Loans\Penalties\PenaltyAccrualEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessLoanPenalties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     * php artisan loans:process-penalties
     */
    protected $signature = 'loans:process-penalties {--date= : Process penalties as of a specific date (Y-m-d)}';

    /**
     * The console command description.
     */
    protected $description = 'Process daily overdue-installment penalty accrual and record penalty applications';

    public function __construct(
        protected PenaltyAccrualEngine $penaltyAccrualEngine
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->penaltyAccrualEngine->processDailyPenalties();
            $this->info('Loan penalties processed successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Failed to process loan penalties', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->error('Failed to process loan penalties. Check logs for details.');
            return Command::FAILURE;
        }
    }
}
