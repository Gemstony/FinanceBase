<?php

namespace App\Console\Commands;

use App\Models\LoanInstallments;
use App\Services\Loans\Installments\InstallmentStatusEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateLoanInstallmentStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     * php artisan loans:update-installment-statuses
     */
    protected $signature = 'loans:update-installment-statuses {--date= : Evaluate statuses as of a specific date (Y-m-d)}';

    /**
     * The console command description.
     */
    protected $description = 'Update loan installment statuses (pending/partial/paid/overdue) based on balances and due dates.';

    public function __construct(
        private readonly InstallmentStatusEngine $statusEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOpt = (string) $this->option('date');
        $asOf = $dateOpt !== '' ? Carbon::parse($dateOpt)->startOfDay() : Carbon::today()->startOfDay();

        Carbon::setTestNow($asOf);

        $updated = 0;
        $seen = 0;

        LoanInstallments::query()
            ->where('is_active', true)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('outstanding_amount', '>', 0)
            ->select(['id', 'loan_id', 'due_date', 'status', 'outstanding_amount', 'principal_due', 'principal_paid', 'interest_due', 'interest_paid', 'fees_due', 'fees_paid', 'penalty_due', 'penalty_paid'])
            ->orderBy('id')
            ->chunkById(500, function ($installments) use (&$updated, &$seen, $asOf) {
                foreach ($installments as $installment) {
                    $seen++;

                    $due = $installment->due_date instanceof Carbon
                        ? $installment->due_date->copy()->startOfDay()
                        : Carbon::parse($installment->due_date)->startOfDay();

                    // Only bother evaluating overdue/pending transitions when due_date has passed.
                    // Partial/paid are handled on payment posting; we avoid scanning future-due rows.
                    if ($due->gt($asOf)) {
                        continue;
                    }

                    $newStatus = $this->statusEngine->determineStatus($installment);

                    if ($installment->status !== $newStatus) {
                        $installment->status = $newStatus;
                        $installment->save();
                        $updated++;
                    }
                }
            });

        Carbon::setTestNow();

        $this->info("Installment statuses checked: {$seen}");
        $this->info("Installment statuses updated: {$updated}");

        return Command::SUCCESS;
    }
}
