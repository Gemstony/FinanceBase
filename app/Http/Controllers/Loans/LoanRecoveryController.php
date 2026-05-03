<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreLoanRecoveryRequest;
use App\Models\BankAccounts;
use App\Models\Loans;
use App\Models\PaymentMethod;
use App\Models\SubShop;
use App\Services\Loans\WriteOff\LoanRecoveryProcessor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanRecoveryController extends Controller
{
    public function __construct(
        private readonly LoanRecoveryProcessor $recoveryProcessor
    ) {
    }

    public function create(Loans $loan): View
    {
        $subshopId = (int) session('subshop_id');
        if ((int) $loan->subshop_id !== $subshopId) {
            abort(403);
        }

        if (Str::lower((string) $loan->status) !== 'written_off') {
            abort(403, 'Recovery payments are only allowed for written-off loans.');
        }
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $bankAccounts = BankAccounts::query()
            ->whereIn('subshop_id',  $shopSubshopIds)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number']);

        // Get payment methods for recovery
        $globalPaymentMethods = PaymentMethod::query()
            ->where('shop_id', $shopId)
            ->where('is_repayment_method', true)
            ->orderBy('name')
            ->get();

        // Calculate outstanding balances
        $outstandingBalances = $this->calculateOutstandingBalances($loan);

        return view('loans.recovery.create', [
            'loan' => $loan,
            'today' => Carbon::today()->toDateString(),
            'bankAccounts' => $bankAccounts,
            'globalPaymentMethods' => $globalPaymentMethods,
            'outstandingBalances' => $outstandingBalances,
        ]);
    }

    public function store(StoreLoanRecoveryRequest $request, Loans $loan): RedirectResponse
    {
        $data = $request->validated();

        try {
            $recoveryDate = Carbon::parse((string) $data['recovery_date'])->toDateString();
            
            // Validate against over-recovery
            $outstandingBalances = $this->calculateOutstandingBalances($loan);
            if ($data['amount'] > $outstandingBalances['total']) {
                return back()
                    ->withInput()
                    ->with('error', "Recovery amount ({$data['amount']}) exceeds outstanding balance ({$outstandingBalances['total']})");
            }
            
            // Handle bank account validation
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $bankAccountId = !empty($data['bank_account_id']) ? (int) $data['bank_account_id'] : null;
            $requiresBank = !in_array($paymentMethod, ['cash', 'customer_credit', 'savings'], true);
            
            if ($requiresBank && !$bankAccountId) {
                return back()
                    ->withInput()
                    ->with('error', 'Bank account is required for this payment method.');
            }

            $subshopId = (int) session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopId = $subshop->shop_id;

            // Get all subshop IDs under this shop for validation
            $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

            // Validate bank account belongs to same subshop
            if ($bankAccountId) {
                $bankAccount = BankAccounts::query()->find($bankAccountId);
                if ($bankAccount && !in_array((int) $bankAccount->subshop_id, $shopSubshopIds->toArray(), true)) {
                    return back()
                        ->withInput()
                        ->with('error', 'Selected bank account does not belong to this branch.');
                }
            }

            $this->recoveryProcessor->processAutoSplit(
                loan: $loan,
                recoveryDate: $recoveryDate,
                amount: (float) $data['amount'],
                notes: $data['notes'] ?? null,
                recordedBy: (int) (auth()->id() ?? 0),
                bankAccountId: $bankAccountId,
                paymentMethod: $paymentMethod,
                transactionReference: $data['reference_number'] ?? null
            );

            return redirect()
                ->route('writeoffs.index')
                ->with('success', 'Recovery recorded successfully.');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Calculate outstanding balances for a written-off loan
     */
    private function calculateOutstandingBalances(Loans $loan): array
    {
        $writeoff = $loan->writeoffs()->orderByDesc('writeoff_date')->first();
        if (!$writeoff) {
            return [
                'penalties' => 0,
                'fees' => 0,
                'interest' => 0,
                'principal' => 0,
                'total' => 0,
            ];
        }

        // Get already recovered amounts
        $alreadyRecovered = \App\Models\LoanWriteoffRecoveries::query()
            ->where('writeoff_id', (int) $writeoff->id)
            ->selectRaw(
                'COALESCE(SUM(recovered_penalties),0) AS penalties, '
                . 'COALESCE(SUM(recovered_fees),0) AS fees, '
                . 'COALESCE(SUM(recovered_interest),0) AS interest, '
                . 'COALESCE(SUM(recovered_principal),0) AS principal'
            )
            ->first();

        $remainingPenalties = round(max(0.0, (float) $writeoff->penalties_written_off - (float) ($alreadyRecovered->penalties ?? 0)), 2);
        $remainingFees = round(max(0.0, (float) $writeoff->fees_written_off - (float) ($alreadyRecovered->fees ?? 0)), 2);
        $remainingInterest = round(max(0.0, (float) $writeoff->interest_written_off - (float) ($alreadyRecovered->interest ?? 0)), 2);
        $remainingPrincipal = round(max(0.0, (float) $writeoff->principal_written_off - (float) ($alreadyRecovered->principal ?? 0)), 2);

        return [
            'penalties' => $remainingPenalties,
            'fees' => $remainingFees,
            'interest' => $remainingInterest,
            'principal' => $remainingPrincipal,
            'total' => round($remainingPenalties + $remainingFees + $remainingInterest + $remainingPrincipal, 2),
        ];
    }
}
