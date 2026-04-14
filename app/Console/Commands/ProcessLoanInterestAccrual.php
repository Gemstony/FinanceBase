<?php

namespace App\Console\Commands;

use App\Models\SubShop;
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
        $this->info('================================================');
        $this->info('    DAILY INTEREST ACCRUAL PROCESSING');
        $this->info('================================================');
        $this->newLine();

        try {
            $date = (string) $this->option('date');
            $asOf = $date !== '' ? Carbon::parse($date)->startOfDay() : null;
            $displayDate = $asOf?->toDateString() ?? Carbon::today()->toDateString();

            $this->info("Accrual Date: {$displayDate}");
            $this->newLine();

            // Run the accrual process and get statistics
            $stats = $this->interestAccrualEngine->processDailyAccrual($asOf);

            // Display Summary
            $this->displaySummary($stats, $displayDate);

            // Determine exit code based on results
            if ($stats['failed'] > 0 || !empty($stats['errors'])) {
                return Command::FAILURE;
            }

            if ($stats['total_loans'] === 0 && empty($stats['configured_subshops'])) {
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('Failed to process daily interest accrual', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->newLine();
            $this->error('CRITICAL ERROR: ' . $e->getMessage());
            $this->error('Check logs for full details.');

            return Command::FAILURE;
        }
    }

    /**
     * Display the execution summary.
     */
    protected function displaySummary(array $stats, string $displayDate): void
    {
        $this->newLine();
        $this->info('================================================');
        $this->info('           EXECUTION SUMMARY');
        $this->info('================================================');
        $this->newLine();

        // Subshop Configuration Status
        $this->warn('SUBSHOP CONFIGURATION STATUS');
        $this->info('------------------------------');

        if (!empty($stats['configured_subshops'])) {
            $this->info('Configured Subshops (' . count($stats['configured_subshops']) . '):');
            $configuredSubshops = SubShop::with('shop')
                ->whereIn('id', $stats['configured_subshops'])
                ->get()
                ->keyBy('id');
            foreach ($stats['configured_subshops'] as $subshopId) {
                $subshop = $configuredSubshops->get($subshopId);
                $shopName = $subshop?->shop?->name ?? 'Unknown Shop';
                $subshopName = $subshop?->name ?? "Subshop #{$subshopId}";
                $fullName = "{$shopName} - {$subshopName}";
                $this->info("  [OK] {$fullName}");
            }
        }

        if (!empty($stats['unconfigured_subshops'])) {
            $this->newLine();
            $this->warn('Unconfigured Subshops (' . count($stats['unconfigured_subshops']) . '):');
            $unconfiguredSubshops = SubShop::with('shop')
                ->whereIn('id', $stats['unconfigured_subshops'])
                ->get()
                ->keyBy('id');
            foreach ($stats['unconfigured_subshops'] as $subshopId) {
                $subshop = $unconfiguredSubshops->get($subshopId);
                $shopName = $subshop?->shop?->name ?? 'Unknown Shop';
                $subshopName = $subshop?->name ?? "Subshop #{$subshopId}";
                $fullName = "{$shopName} - {$subshopName}";
                $this->warn("  [SKIP] {$fullName} - Interest accrual accounts not configured");
            }
        }

        $this->newLine();
        $this->info('------------------------------');
        $this->info('LOAN PROCESSING STATISTICS');
        $this->info('------------------------------');

        // Statistics table
        $headers = ['Metric', 'Count'];
        $rows = [
            ['Total Loans Evaluated', $stats['total_loans']],
            ['Successfully Processed', $stats['processed']],
            ['Skipped (Duplicate/NPL/etc.)', $stats['skipped']],
            ['Failed', $stats['failed']],
        ];
        $this->table($headers, $rows);

        // Success rate
        if ($stats['total_loans'] > 0) {
            $successRate = round(($stats['processed'] / $stats['total_loans']) * 100, 2);
            $this->info("Success Rate: {$successRate}%");
        }

        // Display errors if any
        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->error('ERRORS (' . count($stats['errors']) . '):');
            $this->error('------------------------------');
            foreach (array_slice($stats['errors'], 0, 10) as $index => $error) {
                $this->error(($index + 1) . '. ' . $error);
            }
            if (count($stats['errors']) > 10) {
                $remaining = count($stats['errors']) - 10;
                $this->error("... and {$remaining} more errors (see logs)");
            }
        }

        $this->newLine();
        $this->info('================================================');

        // Final status message
        if ($stats['failed'] === 0 && empty($stats['errors'])) {
            if ($stats['processed'] > 0) {
                $this->info('Status: SUCCESS - All loans processed successfully.');
            } elseif ($stats['total_loans'] === 0) {
                $this->warn('Status: NO LOANS - No loans to process for configured subshops.');
            } else {
                $this->warn('Status: SKIPPED - All loans were skipped (already accrued or NPL).');
            }
        } else {
            $this->error('Status: PARTIAL FAILURE - Some loans failed to process.');
        }

        $this->info('================================================');
    }
}
