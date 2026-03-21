<?php

namespace App\Http\Controllers;

use App\Models\BankAccounts;
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
use Illuminate\View\View;
use InvalidArgumentException;

class LoanRecoveryPaymentsController extends Controller
{
    public function __construct(
        private readonly LoanRecoveryProcessor $recoveryProcessor
    ) {}

    /**
     * Show the form for recording a recovery payment.
     */
    public function create(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        if (Str::lower((string) $loan->status) !== 'written_off') {
            abort(403, 'Recovery payments are only allowed for written-off loans.');
        }

        $bankAccounts = BankAccounts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number']);

        $today = now()->toDateString();

        return view('loans.recovery.create', compact('loan', 'bankAccounts', 'today'));
    }

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
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'reversed', 'failed'])],
        ]);

        if (Str::lower((string) $loan->status) !== 'written_off') {
            return back()->with('error', 'Recovery payments are only allowed for written-off loans.');
        }

        // Validate bank account if payment method requires it
        $paymentMethod = $validated['payment_method'] ?? 'cash';
        $bankAccountId = !empty($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null;
        $requiresBank = !in_array($paymentMethod, ['cash', 'customer_credit', 'savings'], true);
        
        if ($requiresBank && !$bankAccountId) {
            return back()->withInput()->with('error', 'Bank account is required for this payment method.');
        }

        // Validate bank account belongs to same subshop
        if ($bankAccountId) {
            $bankAccount = BankAccounts::query()->find($bankAccountId);
            if ($bankAccount && (int) $bankAccount->subshop_id !== (int) $loan->subshop_id) {
                return back()->withInput()->with('error', 'Selected bank account does not belong to this branch.');
            }
        }

        return DB::transaction(function () use ($loan, $validated, $bankAccountId) {
            $paymentDate = Carbon::parse((string) $validated['payment_date'])->startOfDay();
            $paymentMethod = $validated['payment_method'] ?? 'cash';

            $payment = LoanPayments::create([
                'loan_id' => $loan->id,
                'customer_id' => (int) $loan->customer_id,
                'user_id' => Auth::id(),
                'amount' => (float) $validated['amount'],
                'payment_date' => $paymentDate->toDateString(),
                'payment_method' => $paymentMethod,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'confirmed',
            ]);

            $recovery = $this->recoveryProcessor->processRecovery(
                $loan, 
                $payment,
                $bankAccountId,
                $paymentMethod,
                $validated['reference_number'] ?? null
            );

            return back()->with('success', 'Recovery payment recorded successfully.')->with('recovery_id', $recovery->id);
        });
    }
}
