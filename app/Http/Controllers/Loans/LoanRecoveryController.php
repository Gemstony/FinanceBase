<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreLoanRecoveryRequest;
use App\Models\BankAccounts;
use App\Models\Loans;
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

        return view('loans.recovery.create', [
            'loan' => $loan,
            'today' => Carbon::today()->toDateString(),
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(StoreLoanRecoveryRequest $request, Loans $loan): RedirectResponse
    {
        $data = $request->validated();

        try {
            $recoveryDate = Carbon::parse((string) $data['recovery_date'])->toDateString();
            
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
}
