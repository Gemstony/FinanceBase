<?php

namespace App\Services\Loans\Interest;

use App\Models\ChartsOfAccount;
use App\Models\InterestAccrualAccount;
use App\Models\LoanInterestAccruals;
use App\Models\LoanInstallments;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Accounting\JournalEntryBuilder;
use App\Services\Accounting\JournalPostingEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class InterestAccrualEngine
{
    public function __construct(
        protected LoanOutstandingCalculator $loanOutstandingCalculator,
        protected DailyInterestCalculator $dailyInterestCalculator,
        protected ?JournalPostingEngine $journalPostingEngine = null,
    ) {
    }

    /**
     * Cache for validated subshop configurations to avoid redundant validation.
     *
     * @var array<int, array{receivable_account_id: int, income_account_id: int}>
     */
    protected array $validatedSubshops = [];

    /**
     * Track subshops without configuration to avoid duplicate warnings.
     *
     * @var array<int, bool>
     */
    protected array $unconfiguredSubshops = [];

    /**
     * Process daily interest accrual for the active loan portfolio.
     *
     * Business rules:
     * - Accrue interest daily on outstanding principal.
     * - Only accrue for loans in an "active" lifecycle state (disbursed/partially_paid).
     * - Do NOT accrue for written-off/paid-off loans.
     * - Do NOT accrue for loans with max overdue days > 90 (non-performing).
     * - Prevent duplicate accrual (one row per loan per day).
     * - Only process loans from subshops with valid interest accrual configuration.
     *
     * @return array{
     *     total_loans: int,
     *     processed: int,
     *     skipped: int,
     *     failed: int,
     *     configured_subshops: array<int>,
     *     unconfigured_subshops: array<int>,
     *     errors: array<string>
     * }
     */
    public function processDailyAccrual(?Carbon $asOfDate = null): array
    {
        $today = ($asOfDate ?? Carbon::today())->startOfDay();

        // Reset caches for this run
        $this->validatedSubshops = [];
        $this->unconfiguredSubshops = [];

        // Treat these as "active" loans for interest accrual in this system.
        $activeStatuses = ['active', 'disbursed', 'partially_paid'];

        // Pre-flight: identify all subshops with active loans
        $subshopIdsWithLoans = Loans::query()
            ->where('is_active', true)
            ->whereIn('status', $activeStatuses)
            ->distinct()
            ->pluck('subshop_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->all();

        // Validate configurations and build lists
        $configuredSubshops = [];
        foreach ($subshopIdsWithLoans as $subshopId) {
            try {
                $this->getInterestAccrualAccounts($subshopId);
                $configuredSubshops[] = $subshopId;
            } catch (InvalidArgumentException $e) {
                // Configuration missing or invalid - track once
                $this->unconfiguredSubshops[$subshopId] = true;
                Log::warning('Subshop skipped - interest accrual accounts not configured', [
                    'subshop_id' => $subshopId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $unconfiguredSubshops = array_keys($this->unconfiguredSubshops);

        if (empty($configuredSubshops)) {
            Log::error('No subshops have interest accrual accounts configured. Aborting.');
            return [
                'total_loans' => 0,
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'configured_subshops' => [],
                'unconfigured_subshops' => $unconfiguredSubshops,
                'errors' => ['No subshops have interest accrual accounts configured.'],
            ];
        }

        $stats = [
            'total_loans' => 0,
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'configured_subshops' => $configuredSubshops,
            'unconfigured_subshops' => $unconfiguredSubshops,
            'errors' => [],
        ];

        // Build base query with subshop filtering
        $query = Loans::query()
            ->where('is_active', true)
            ->whereIn('status', $activeStatuses)
            ->whereIn('subshop_id', $configuredSubshops)
            ->select([
                'id',
                'loan_code',
                'subshop_id',
                'principal_amount',
                'interest_rate',
            ])
            ->orderBy('id');

        $query->chunkById(200, function ($loans) use ($today, &$stats) {
            foreach ($loans as $loan) {
                $stats['total_loans']++;
                try {
                    $result = $this->accrueForLoan($loan, $today);
                    if ($result === 'processed') {
                        $stats['processed']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $errorMsg = "Loan {$loan->loan_code} (ID: {$loan->id}): {$e->getMessage()}";
                    $stats['errors'][] = $errorMsg;
                    Log::error('Interest accrual failed for loan', [
                        'loan_id' => $loan->id,
                        'date' => $today->toDateString(),
                        'message' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }
        });

        // Summary logging
        Log::info('Interest accrual batch completed', [
            'date' => $today->toDateString(),
            'total_loans' => $stats['total_loans'],
            'processed' => $stats['processed'],
            'skipped' => $stats['skipped'],
            'failed' => $stats['failed'],
            'configured_subshops' => $stats['configured_subshops'],
            'unconfigured_subshops' => $stats['unconfigured_subshops'],
        ]);

        return $stats;
    }

    /**
     * Accrue interest for a single loan.
     *
     * @return string 'processed'|'skipped'
     * @throws \Throwable
     */
    protected function accrueForLoan(Loans $loan, Carbon $today): string
    {
        // Prevent duplicate accrual record per loan per day.
        $exists = LoanInterestAccruals::query()
            ->where('loan_id', $loan->id)
            ->whereDate('accrual_date', $today->toDateString())
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        // Do not accrue if loan is non-performing: max overdue days > 90.
        $maxOverdueDays = $this->calculateMaxOverdueDays($loan, $today);
        if ($maxOverdueDays > 90) {
            return 'skipped';
        }

        $principalBalance = $this->loanOutstandingCalculator->calculateOutstandingPrincipal($loan);
        if ($principalBalance <= 0) {
            return 'skipped';
        }

        $annualRate = (float) ($loan->interest_rate ?? 0);
        if ($annualRate <= 0) {
            return 'skipped';
        }

        $dailyInterest = $this->dailyInterestCalculator->calculateDailyInterest($principalBalance, $annualRate);
        if ($dailyInterest <= 0) {
            return 'skipped';
        }

        DB::transaction(function () use ($loan, $today, $principalBalance, $annualRate, $dailyInterest) {
            // Double-check inside the transaction to avoid race conditions when running in parallel.
            $existsTx = LoanInterestAccruals::query()
                ->where('loan_id', $loan->id)
                ->whereDate('accrual_date', $today->toDateString())
                ->where('is_active', true)
                ->exists();

            if ($existsTx) {
                return;
            }

            $accrual = LoanInterestAccruals::create([
                'loan_id' => $loan->id,
                'installment_id' => null,
                'accrual_date' => $today->toDateString(),
                'principal_balance' => round($principalBalance, 2),
                'interest_rate' => round($annualRate, 4),
                'daily_interest' => round($dailyInterest, 6),
                'is_posted' => false,
                'posting_id' => null,
                'is_active' => true,
            ]);

            // Post to General Ledger if JournalPostingEngine is available
            $this->postAccrualToGeneralLedger($loan, $accrual);
        });

        return 'processed';
    }

    /**
     * Calculate maximum days overdue among overdue installments.
     *
     * This is used as a simple NPL (non-performing loan) guard:
     * if max overdue days exceeds 90, we stop accruing interest.
     */
    protected function calculateMaxOverdueDays(Loans $loan, Carbon $today): int
    {
        $max = (int) LoanInstallments::query()
            ->where('loan_id', $loan->id)
            ->where('is_active', true)
            ->where('status', 'overdue')
            ->get(['due_date'])
            ->map(function ($i) use ($today) {
                $due = $i->due_date instanceof Carbon ? $i->due_date->copy()->startOfDay() : Carbon::parse($i->due_date)->startOfDay();
                return max(0, (int) $due->diffInDays($today, false));
            })
            ->max();

        return max(0, $max);
    }

    /**
     * Get interest accrual accounts for a subshop with full validation.
     *
     * @return array{receivable_account_id: int, income_account_id: int}
     * @throws InvalidArgumentException
     */
    public function getInterestAccrualAccounts(int $subshopId): array
    {
        Log::debug('Getting interest accrual accounts', ['subshop_id' => $subshopId]);

        // Return cached validation result if available
        if (isset($this->validatedSubshops[$subshopId])) {
            return $this->validatedSubshops[$subshopId];
        }

        $config = InterestAccrualAccount::forSubshop($subshopId);

        if (!$config) {
            Log::error('Interest accrual accounts not configured', ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException(
                "Interest accrual accounts are not configured for subshop {$subshopId}. " .
                'Please configure them before running interest accrual.'
            );
        }

        $receivableAccountId = (int) $config->interest_receivable_account_id;
        $incomeAccountId = (int) $config->interest_income_account_id;

        // Validate receivable account (should be Class 1 - Asset)
        $receivableAccount = ChartsOfAccount::query()->whereKey($receivableAccountId)->first();
        if (!$receivableAccount) {
            Log::error('Interest receivable account not found', ['account_id' => $receivableAccountId]);
            throw new InvalidArgumentException('Configured interest receivable account not found.');
        }

        $receivableClass = (int) ($receivableAccount->accountClass?->code ?? 0);
        if ($receivableClass !== 1) {
            Log::error('Interest receivable account not asset class', [
                'account_id' => $receivableAccountId,
                'account_class' => $receivableClass,
            ]);
            throw new InvalidArgumentException(
                'Interest receivable account must be an Asset account (Account Class 1).'
            );
        }

        if (!$receivableAccount->is_active) {
            Log::error('Interest receivable account not active', ['account_id' => $receivableAccountId]);
            throw new InvalidArgumentException('Interest receivable account is not active.');
        }

        // Validate account belongs to the same shop (shop-level scope)
        $currentSubshop = SubShop::findOrFail($subshopId);
        if ((int) $receivableAccount->shop_id !== (int) $currentSubshop->shop_id) {
            Log::error('Interest receivable account wrong shop', [
                'account_id' => $receivableAccountId,
                'account_shop_id' => $receivableAccount->shop_id,
                'expected_shop_id' => $currentSubshop->shop_id,
            ]);
            throw new InvalidArgumentException('Interest receivable account does not belong to this shop.');
        }

        // Validate income account (should be Class 4 - income)
        $incomeAccount = ChartsOfAccount::query()->whereKey($incomeAccountId)->first();
        if (!$incomeAccount) {
            Log::error('Interest income account not found', ['account_id' => $incomeAccountId]);
            throw new InvalidArgumentException('Configured interest income account not found.');
        }

        $incomeClass = (int) ($incomeAccount->accountClass?->code ?? 0);
        if ($incomeClass !== 4) {
            Log::error('Interest income account not revenue class', [
                'account_id' => $incomeAccountId,
                'account_class' => $incomeClass,
            ]);
            throw new InvalidArgumentException(
                'Interest income account must be a Revenue account (Account Class 4).'
            );
        }

        if (!$incomeAccount->is_active) {
            Log::error('Interest income account not active', ['account_id' => $incomeAccountId]);
            throw new InvalidArgumentException('Interest income account is not active.');
        }

        // Validate account belongs to the same shop (shop-level scope)
        if ((int) $incomeAccount->shop_id !== (int) $currentSubshop->shop_id) {
            Log::error('Interest income account wrong shop', [
                'account_id' => $incomeAccountId,
                'account_shop_id' => $incomeAccount->shop_id,
                'expected_shop_id' => $currentSubshop->shop_id,
            ]);
            throw new InvalidArgumentException('Interest income account does not belong to this shop.');
        }

        Log::debug('Interest accrual accounts validated', [
            'subshop_id' => $subshopId,
            'receivable_account_id' => $receivableAccountId,
            'income_account_id' => $incomeAccountId,
        ]);

        // Cache the validated accounts for this run
        $this->validatedSubshops[$subshopId] = [
            'receivable_account_id' => $receivableAccountId,
            'income_account_id' => $incomeAccountId,
        ];

        return $this->validatedSubshops[$subshopId];
    }

    /**
     * Post daily interest accrual to General Ledger using JournalPostingEngine.
     *
     * Journal Entry:
     * - Debit: Accrued Interest Receivable (from configuration)
     * - Credit: Interest Income (from configuration)
     *
     * This ensures interest accruals are properly reflected in financial statements.
     *
     * @throws InvalidArgumentException When configuration is missing or invalid
     */
    protected function postAccrualToGeneralLedger(Loans $loan, LoanInterestAccruals $accrual): void
    {
        // Skip if JournalPostingEngine is not available (for backwards compatibility)
        if (!$this->journalPostingEngine) {
            return;
        }

        // Get subshop_id from the loan (needed for configuration lookup)
        $subshopId = (int) ($loan->subshop_id ?? 0);
        if ($subshopId <= 0) {
            Log::warning('Interest accrual GL posting skipped - subshop_id not found', [
                'loan_id' => $loan->id,
            ]);
            return;
        }

        try {
            // Get and validate configured accounts
            $accounts = $this->getInterestAccrualAccounts($subshopId);
            $accruedInterestAccountId = $accounts['receivable_account_id'];
            $interestIncomeAccountId = $accounts['income_account_id'];

            $amount = round((float) $accrual->daily_interest, 2);

            // Build journal entry lines using JournalEntryBuilder
            $lines = app(JournalEntryBuilder::class)
                ->reset()
                ->addDebit($accruedInterestAccountId, $amount, "Interest accrual - {$loan->loan_code}")
                ->addCredit($interestIncomeAccountId, $amount, "Interest income accrual - {$loan->loan_code}")
                ->getLines();

            // Use JournalPostingEngine for consistent posting with proper validation
            $this->journalPostingEngine->postJournalEntry(
                $lines,
                'interest_accrual',
                (int) $accrual->id,
                "Daily interest accrual - Loan {$loan->loan_code}",
                $accrual->accrual_date?->toDateString() ?? now()->toDateString(),
                $subshopId
            );

            Log::info('Interest accrual posted to General Ledger', [
                'loan_id' => $loan->id,
                'accrual_id' => $accrual->id,
                'amount' => $amount,
                'accrued_interest_account_id' => $accruedInterestAccountId,
                'interest_income_account_id' => $interestIncomeAccountId,
                'subshop_id' => $subshopId,
            ]);
        } catch (InvalidArgumentException $e) {
            // Re-throw configuration errors so they can be caught and logged at higher level
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to post interest accrual to General Ledger', [
                'loan_id' => $loan->id,
                'accrual_id' => $accrual->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
