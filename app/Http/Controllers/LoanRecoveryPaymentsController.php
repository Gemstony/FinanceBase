<?php

namespace App\Http\Controllers;

use App\Models\LoanPayments;
use App\Models\Loans;
use App\Services\Loans\WriteOff\LoanRecoveryProcessor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LoanRecoveryPaymentsController extends Controller
{
    public function __construct(
        private readonly LoanRecoveryProcessor $recoveryProcessor
    ) {}

    /**
     * Record a recovery payment for a written-off loan.
     *
     * Important:
     * - This flow intentionally does NOT use PaymentProcessor.
     * - Normal payments remain blocked for written_off loans.
     * - This controller creates a loan_payments row for audit, then records recovery allocation
     *   into loan_writeoff_recoveries.
     */
    public function store(Request $request, Loans $loan): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'reversed', 'failed'])],
        ]);

        if (Str::lower((string) $loan->status) !== 'written_off') {
            return back()->with('error', 'Recovery payments are only allowed for written-off loans.');
        }

        return DB::transaction(function () use ($loan, $validated) {
            $paymentDate = Carbon::parse((string) $validated['payment_date'])->startOfDay();

            $payment = LoanPayments::create([
                'loan_id' => $loan->id,
                'customer_id' => (int) $loan->customer_id,
                'user_id' => Auth::id(),
                'amount' => (float) $validated['amount'],
                'payment_date' => $paymentDate->toDateString(),
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'confirmed',
            ]);

            $recovery = $this->recoveryProcessor->processRecovery($loan, $payment);

            return back()->with('success', 'Recovery payment recorded successfully.')->with('recovery_id', $recovery->id);
        });
    }
}
