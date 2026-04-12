<?php

namespace App\Http\Controllers;

use App\Models\BankAccounts;
use App\Models\LoanInstallments;
use App\Models\LoanPaymentAllocations;
use App\Models\LoanPayments;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Loans\Account\LoanAccountEngine;
use App\Services\Loans\Repayment\PaymentProcessor;
use App\Services\Sms\SmsManager;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class LoanRepaymentController extends Controller
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly LoanAccountEngine $loanAccountEngine,
    ) {}

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $query = Loans::query()
            ->with(['customer', 'loanProduct', 'loanGroup'])
            ->where('subshop_id', $subshopId)
            ->whereIn('status', ['disbursed', 'partially_paid']);

        if ($request->filled('loan_code')) {
            $query->where('loan_code', 'like', '%'.$request->string('loan_code').'%');
        }

        if ($request->filled('borrower')) {
            $borrower = $request->string('borrower');
            $query->where(function ($q) use ($borrower) {
                $q->whereHas('customer', function ($q2) use ($borrower) {
                    $q2->where('name', 'like', '%'.$borrower.'%');
                })->orWhereHas('loanGroup', function ($q2) use ($borrower) {
                    $q2->where('name', 'like', '%'.$borrower.'%');
                });
            });
        }

        $loans = $query->orderByDesc('id')->get();

        return view('loans.repayments.index', compact('loans'));
    }

    public function create(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        $summary = $this->loanAccountEngine->getLoanAccountSummary($loan);

        $payers = collect();
        if (! $loan->customer_id && $loan->loan_group_id) {
            $loan->loadMissing(['loanGroup.members' => function ($q) {
                $q->where('is_active', true)->with('customer');
            }]);

            $payers = $loan->loanGroup?->members
                ?->pluck('customer')
                ?->filter()
                ?->values() ?? collect();
        }

        $latestScheduleVersion = (int) (LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->max('schedule_version') ?: 1);

        $allInstallments = LoanInstallments::query()
            ->where('loan_id', (int) $loan->id)
            ->orderByDesc('schedule_version')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $installmentsByVersion = $allInstallments->groupBy('schedule_version');

        // Keep $installments for repayment processing UI: show the latest ACTIVE schedule.
        $installments = $installmentsByVersion
            ->get($latestScheduleVersion, collect())
            ->where('is_active', true)
            ->values();

        $bankAccounts = BankAccounts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number']);

        return view('loans.repayments.create', compact(
            'loan',
            'summary',
            'installments',
            'installmentsByVersion',
            'latestScheduleVersion',
            'payers',
            'bankAccounts'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        // Validate incoming repayment request
        $validated = $request->validate([
            'loan_code' => ['required', 'string', 'exists:loans,loan_code'],
            'payment_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payer_customer_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'provider' => ['nullable', 'string', 'max:50'],
        ]);

        // Load loan and verify subshop access
        $loan = Loans::query()->where('loan_code', $validated['loan_code'])->firstOrFail();
        $subshopId = (int) session('subshop_id');
        
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403); // Access denied - wrong subshop
        }

        // Handle payer customer (required if loan has no customer)
        $payerCustomerId = null;
        if (! $loan->customer_id) {
            $payerValidated = $request->validate([
                'payer_customer_id' => [
                    'required',
                    'integer',
                    Rule::exists('customers', 'id')->where(fn ($q) => $q->where('subshop_id', $subshopId)),
                ],
            ]);
            $payerCustomerId = (int) $payerValidated['payer_customer_id'];
        }

        $paymentMethod = $validated['payment_method'];

        // Handle Azampay mobile payments separately
        if ($paymentMethod === 'azampay') {
            $azampayValidation = $request->validate([
                'phone_number' => ['required', 'string', 'max:20'],
                'provider' => ['required', 'string', 'max:50'],
            ]);

            try {
                $result = $this->paymentProcessor->processMobilePayment(
                    $loan,
                    $payerCustomerId,
                    (float) $validated['payment_amount'],
                    Carbon::parse((string) $validated['payment_date'])->startOfDay(),
                    $azampayValidation['phone_number'],
                    $azampayValidation['provider'],
                    $validated['notes'] ?? null
                );

                return redirect()
                    ->route('loan.repayments.show', $loan)
                    ->with('info', 'Payment request sent. Please complete the payment on your phone.');
            } catch (\Throwable $e) {
                report($e);
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Failed to initiate mobile payment: '.$e->getMessage());
            }
        }

        // Process standard payment (cash, bank, etc.)
        try {
            Log::info('Processing loan repayment', [
                'loan_code' => $loan->loan_code,
                'payment_method' => $paymentMethod,
                'amount' => $validated['payment_amount'],
            ]);
            
            $payment = $this->paymentProcessor->processPayment(
                $loan,
                $payerCustomerId,
                (float) $validated['payment_amount'],
                (string) $paymentMethod,
                isset($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null,
                $validated['transaction_reference'] ? (string) $validated['transaction_reference'] : null,
                Carbon::parse((string) $validated['payment_date'])->startOfDay(),
                $validated['notes'] ? (string) $validated['notes'] : null,
            );
            
            Log::info('Loan repayment processed successfully', [
                'payment_id' => $payment->id,
                'loan_code' => $loan->loan_code,
                'amount' => $payment->payment_amount,
            ]);
        } catch (InvalidArgumentException $e) {
            // Validation errors (e.g., missing payment method mapping)
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['payment' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // System errors - log and report
            report($e);
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to process payment. Please try again or contact support.');
        }

        // Send SMS notification (non-blocking - failure won't affect payment)
        try {
            $customer = $loan->customer;
            if ($customer && $customer->phone) {
                $shopId = SubShop::where('id', $loan->subshop_id)->value('shop_id');
                app(SmsManager::class)->sendEvent('loan.repayment', [
                    'shop_id' => $shopId,
                    'subshop_id' => $loan->subshop_id,
                    'user_id' => Auth::id(),
                    'phone' => $customer->phone,
                    'data' => [
                        'name' => $customer->name,
                        'amount' => $validated['payment_amount'],
                        'date' => Carbon::parse((string) $validated['payment_date'])->format('Y-m-d'),
                        'loan_code' => $loan->loan_code ?? 'N/A',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            // SMS failure is logged but doesn't affect the payment process
            Log::warning('Failed to send loan repayment SMS', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
        }

        return redirect()
            ->route('loan.repayments.receipt', $payment)
            ->with('success', 'Payment processed successfully.');
    }

    public function show(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        $payments = LoanPayments::query()
            ->with(['user', 'allocations.loanInstallment'])
            ->where('loan_id', (int) $loan->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20);

        $summary = $this->loanAccountEngine->getLoanAccountSummary($loan);

        return view('loans.repayments.history', compact('loan', 'payments', 'summary'));
    }

    public function receipt(LoanPayments $payment): View
    {
        $payment->loadMissing(['loan.customer', 'user', 'allocations.loanInstallment']);

        $subshopId = (int) session('subshop_id');
        if ((int) $payment->loan?->subshop_id !== $subshopId) {
            abort(403);
        }

        $principal = (float) $payment->allocations->sum('principal_amount');
        $interest = (float) $payment->allocations->sum('interest_amount');
        $fee = (float) $payment->allocations->sum('fee_amount');
        $penalty = (float) $payment->allocations->sum('penalty_amount');

        return view('loans.repayments.receipt', compact('payment', 'principal', 'interest', 'fee', 'penalty'));
    }

    public function reverse(LoanPayments $payment): RedirectResponse
    {
        $payment->loadMissing(['loan', 'allocations']);

        $subshopId = (int) session('subshop_id');
        if ((int) $payment->loan?->subshop_id !== $subshopId) {
            abort(403);
        }

        if ((string) $payment->status === 'reversed') {
            return redirect()->back()->with('error', 'This payment is already reversed.');
        }

        DB::transaction(function () use ($payment) {
            $payment = LoanPayments::query()->whereKey((int) $payment->id)->lockForUpdate()->firstOrFail();
            $loan = Loans::query()->whereKey((int) $payment->loan_id)->lockForUpdate()->firstOrFail();

            $allocations = $payment->allocations()->lockForUpdate()->get();

            foreach ($allocations as $alloc) {
                $ins = LoanInstallments::query()->whereKey((int) $alloc->loan_installment_id)->lockForUpdate()->first();
                if (! $ins) {
                    continue;
                }

                $ins->principal_paid = max(0.0, round((float) $ins->principal_paid - (float) $alloc->principal_amount, 2));
                $ins->interest_paid = max(0.0, round((float) $ins->interest_paid - (float) $alloc->interest_amount, 2));
                $ins->fees_paid = max(0.0, round((float) $ins->fees_paid - (float) $alloc->fee_amount, 2));
                $ins->penalty_paid = max(0.0, round((float) $ins->penalty_paid - (float) $alloc->penalty_amount, 2));

                $total = (float) $alloc->principal_amount + (float) $alloc->interest_amount + (float) $alloc->fee_amount + (float) $alloc->penalty_amount;
                $ins->amount_paid = max(0.0, round((float) $ins->amount_paid - $total, 2));
                $ins->outstanding_amount = round(max(0.0, (float) $ins->total_due - (float) $ins->amount_paid), 2);

                if ((float) $ins->outstanding_amount <= 0.0) {
                    $ins->status = 'paid';
                } elseif ((float) $ins->amount_paid > 0.0) {
                    $ins->status = 'partial';
                } else {
                    $ins->status = 'pending';
                    $ins->paid_date = null;
                }

                $ins->save();
            }

            $summary = app(LoanAccountEngine::class)->getLoanAccountSummary($loan);
            $loan->outstanding_balance = (float) ($summary['total_balance'] ?? null);
            $loan->next_installment_amount = (float) ($summary['next_installment']['total_due'] ?? null);

            $hasOutstanding = LoanInstallments::query()
                ->where('loan_id', (int) $loan->id)
                ->where('is_active', true)
                ->where('outstanding_amount', '>', 0)
                ->exists();

            $loan->status = $hasOutstanding ? 'partially_paid' : 'paid_off';
            if ((float) $loan->outstanding_balance > 0) {
                $loan->status = 'partially_paid';
            }
            $loan->save();

            $payment->status = 'reversed';
            $payment->save();

            $principal = (float) $allocations->sum('principal_amount');
            $interest = (float) $allocations->sum('interest_amount');
            $fee = (float) $allocations->sum('fee_amount');
            $penalty = (float) $allocations->sum('penalty_amount');

            app(\App\Services\Loans\Ledger\LoanTransactionLedger::class)->recordRepayment(
                $loan,
                -1 * (float) $payment->amount,
                -1 * $principal,
                -1 * $interest,
                -1 * $penalty,
                -1 * $fee,
                (int) $payment->id
            );

            app(\App\Services\Accounting\JournalPostingEngine::class)->postLoanJournalEntryReversalForPayment((int) $payment->id);
        });

        return redirect()->back()->with('success', 'Payment reversed successfully.');
    }

    public function handleWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('AzamPay loan repayment webhook received', $payload);

            $externalRef = $payload['externalreference'] ?? null;
            $utilityRef = $payload['utilityref'] ?? null;
            $status = $payload['transactionstatus'] ?? $payload['status'] ?? 'UNKNOWN';
            $amount = $payload['amount'] ?? 0;
            $phone = $payload['msisdn'] ?? null;
            $operator = $payload['operator'] ?? null;

            if (! $externalRef && ! $utilityRef) {
                Log::warning('AzamPay webhook missing external reference', $payload);

                return response()->json(['status' => 'error', 'message' => 'Missing external reference'], 400);
            }

            // First try to find by utilityRef (our original reference - LR-xxx)
            $payment = null;
            if ($utilityRef) {
                $payment = LoanPayments::query()
                    ->where('external_id', $utilityRef)
                    ->orWhere('transaction_reference', $utilityRef)
                    ->orWhere('reference_number', $utilityRef)
                    ->first();
            }

            // If not found, try by externalRef (AzamPay's transaction ID)
            if (! $payment && $externalRef) {
                $payment = LoanPayments::query()
                    ->where('external_id', $externalRef)
                    ->orWhere('transaction_reference', $externalRef)
                    ->first();
            }

            Log::info('AzamPay webhook payment search', [
                'external_ref' => $externalRef,
                'utility_ref' => $utilityRef,
                'found_payment_id' => $payment?->id,
                'payment_status' => $payment?->status,
                'payment_external_id' => $payment?->external_id,
                'payment_transaction_ref' => $payment?->transaction_reference,
            ]);

            if (! $payment) {
                Log::warning('Loan payment not found for external reference', ['external_ref' => $externalRef]);

                return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
            }

            $payment->loadMissing(['loan']);

            Log::info('AzamPay webhook status check', [
                'payment_id' => $payment->id,
                'current_status' => $payment->status,
                'webhook_status' => $status,
                'status_upper' => strtoupper($status),
                'is_success' => strtoupper($status) === 'SUCCESS' || $status === '200',
            ]);

            if (strtoupper($status) === 'SUCCESS' || $status === '200') {
                $this->paymentProcessor->confirmPendingPayment(
                    (int) $payment->id,
                    (float) $amount,
                    $phone,
                    $operator
                );
            } else {
                $payment->update(['status' => 'failed']);
                Log::warning('AzamPay payment failed', [
                    'payment_id' => $payment->id,
                    'status' => $status,
                ]);
            }

            Log::info('AzamPay webhook processed', [
                'payment_id' => $payment->id,
                'final_status' => $payment->status,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);
        } catch (\Exception $e) {
            Log::error('AzamPay loan repayment webhook failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    public function checkStatus(LoanPayments $payment): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        $payment->loadMissing(['loan']);

        if (! $payment->loan || (int) $payment->loan->subshop_id !== $subshopId) {
            abort(403);
        }

        if ((string) $payment->status !== 'pending') {
            return redirect()->back()->with('info', 'Payment status is already '.$payment->status);
        }

        return redirect()->back()->with('info', 'Payment is still pending. Please wait for AzamPay to send the payment confirmation. You can refresh this page to check for updates.');
    }
}
