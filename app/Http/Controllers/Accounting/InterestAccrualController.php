<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartsOfAccount;
use App\Models\InterestAccrualAccount;
use App\Models\SubShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InterestAccrualController extends Controller
{
    public function index(): View
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        $config = InterestAccrualAccount::forShop($subshop->shop_id);
        $isConfigured = $config !== null;

        // Get available accounts for configuration - scoped to shop level
        $assetAccounts = ChartsOfAccount::with('accountClass')
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn($acc) => (int) ($acc->accountClass?->code ?? 0) === 1);

        $revenueAccounts = ChartsOfAccount::with('accountClass')
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn($acc) => (int) ($acc->accountClass?->code ?? 0) === 4);

        return view('accounting.accounting_settings.interest_accrual_accounts', compact(
            'config',
            'isConfigured',
            'assetAccounts',
            'revenueAccounts'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'interest_receivable_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'interest_income_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $subshopId = (int) session('subshop_id');

            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            // Validate receivable account is Class 1 (Asset)
            $receivableAccount = ChartsOfAccount::query()
                ->whereKey($validated['interest_receivable_account_id'])
                ->with('accountClass')
                ->firstOrFail();

            if ((int) ($receivableAccount->accountClass?->code ?? 0) !== 1) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Interest receivable account must be an Asset account (Account Class 1).');
            }

            if (! in_array((int) $receivableAccount->subshop_id, $shopSubshopIds->toArray())) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected receivable account does not belong to this shop.');
            }

            if (!$receivableAccount->is_active) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected receivable account is not active.');
            }

            // Validate income account is Class 4 (Revenue)
            $incomeAccount = ChartsOfAccount::query()
                ->whereKey($validated['interest_income_account_id'])
                ->with('accountClass')
                ->firstOrFail();

            if ((int) ($incomeAccount->accountClass?->code ?? 0) !== 4) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Interest income account must be a Revenue account (Account Class 4).');
            }

            if (! in_array((int) $incomeAccount->subshop_id, $shopSubshopIds->toArray())) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected income account does not belong to this shop.');
            }

            if (!$incomeAccount->is_active) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected income account is not active.');
            }

            // Create or update configuration (use current subshop_id to store)
            InterestAccrualAccount::updateOrCreate(
                ['subshop_id' => $subshopId],
                [
                    'interest_receivable_account_id' => (int) $validated['interest_receivable_account_id'],
                    'interest_income_account_id' => (int) $validated['interest_income_account_id'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            Log::info('Interest accrual accounts configured', [
                'subshop_id' => $subshopId,
                'receivable_account_id' => $validated['interest_receivable_account_id'],
                'income_account_id' => $validated['interest_income_account_id'],
            ]);

            return redirect()->route('accounting.interest-accrual-accounts.index')
                ->with('success', 'Interest accrual accounts configured successfully.');

        } catch (\Exception $e) {
            Log::error('Interest accrual configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to configure interest accrual accounts: ' . $e->getMessage());
        }
    }

    public function destroy(): RedirectResponse
    {
        try {
            $subshopId = (int) session('subshop_id');

            $subshop = SubShop::findOrFail($subshopId);
            $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

            InterestAccrualAccount::whereIn('subshop_id', $shopSubshopIds)->delete();

            Log::info('Interest accrual accounts configuration deleted', [
                'shop_id' => $subshop->shop_id,
                'subshop_ids' => $shopSubshopIds->toArray(),
            ]);

            return redirect()->route('accounting.interest-accrual-accounts.index')
                ->with('success', 'Interest accrual accounts configuration removed successfully.');

        } catch (\Exception $e) {
            Log::error('Interest accrual configuration deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to remove interest accrual accounts configuration: ' . $e->getMessage());
        }
    }
}
