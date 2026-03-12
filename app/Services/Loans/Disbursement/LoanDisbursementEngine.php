<?php

declare(strict_types=1);

namespace App\Services\Loans\Disbursement;

use App\Models\LoanDisbursements;
use App\Models\Loans;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanDisbursementEngine
{
    public function __construct(
        private readonly LoanDisbursementValidator $validator,
        private readonly LoanActivationService $activationService,
    ) {
    }

    /**
     * Execute a loan disbursement.
     *
     * Financial meaning:
     * - Creates an immutable audit record of the disbursement event.
     * - Transitions the loan into its post-disbursement lifecycle so the repayment engines can operate.
     *
     * Data integrity:
     * - Performed in a DB transaction.
     * - Must not regenerate or modify installment history.
     */
    public function disburseLoan(
        Loans $loan,
        float $amount,
        int $disbursementMethodId,
        int $bankAccountId,
        ?string $transactionReference,
        int $processedBy,
        ?string $notes
    ): LoanDisbursements {
        $this->validator->validate($loan, $amount);

        return DB::transaction(function () use (
            $loan,
            $amount,
            $disbursementMethodId,
            $bankAccountId,
            $transactionReference,
            $processedBy,
            $notes
        ) {
            // Step 3: Insert a disbursement record.
            // This is the financial audit trail of funds released to the borrower.
            $disbursement = LoanDisbursements::create([
                'loan_id' => $loan->id,
                'disbursement_date' => Carbon::now()->toDateString(),
                'amount' => $amount,
                'disbursement_method_id' => $disbursementMethodId,
                'bank_account_id' => $bankAccountId,
                'transaction_reference' => $transactionReference,
                'processed_by' => $processedBy,
                'notes' => $notes,
            ]);

            // Step 4: Activate the loan.
            // This begins the loan lifecycle: repayment eligibility and accrual engines can run.
            $this->activationService->activateLoan($loan, $amount);

            return $disbursement;
        });
    }
}
