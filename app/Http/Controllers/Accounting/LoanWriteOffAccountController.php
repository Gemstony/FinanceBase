<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartsOfAccount;
use App\Models\LoanWriteOffAccount;
use App\Models\SubShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoanWriteOffAccountController extends Controller
{
    /**
     * Display the loan write-off account configuration for the current subshop.
     */
    public function index(): View|RedirectResponse
    {
        $subshopId = session('subshop_id');

        if (! $subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.loan-write-off-accounts.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        // Get current configuration
        $config = LoanWriteOffAccount::getBySubshop($subshopId);

        // Get available GL accounts - use same pattern as InterestAccrualController
        $expenseAccounts = ChartsOfAccount::with('accountClass')
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->get()
            ->filter(fn($acc) => (int) ($acc->accountClass?->code ?? 0) === 5); // Class 5 = Expense

        $incomeAccounts = ChartsOfAccount::with('accountClass')
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->get()
            ->filter(fn($acc) => (int) ($acc->accountClass?->code ?? 0) === 4); // Class 4 = Revenue

        return view('accounting.accounting_settings.loan_write_off_accounts', [
            'config' => $config,
            'subshop' => $subshop,
            'expenseAccounts' => $expenseAccounts,
            'incomeAccounts' => $incomeAccounts,
        ]);
    }

    /**
     * Store or update the loan write-off account configuration.
     */
    public function store(Request $request): RedirectResponse
    {
        $subshopId = session('subshop_id');

        if (! $subshopId) {
            return redirect()->route('subshops.choose');
        }

        $validated = $request->validate([
            'write_off_expense_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'recovery_income_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $expenseAccountId = (int) $validated['write_off_expense_account_id'];
        $incomeAccountId = (int) $validated['recovery_income_account_id'];

        // Validate that the expense account belongs to this subshop and is Class 5 (Expense)
        $expenseAccount = ChartsOfAccount::with('accountClass')
            ->where('id', $expenseAccountId)
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->first();

        if ($expenseAccount && (int) ($expenseAccount->accountClass?->code ?? 0) !== 5) {
            return back()
                ->withInput()
                ->with('error', 'The selected write-off expense account must be a Class 5 (Expense) account.');
        }

        if (! $expenseAccount) {
            return back()
                ->withInput()
                ->with('error', 'The selected write-off expense account is invalid. It must be an active Class 5 (Expense) account belonging to your branch.');
        }

        // Validate that the income account belongs to this subshop and is Class 4 (Revenue)
        $incomeAccount = ChartsOfAccount::with('accountClass')
            ->where('id', $incomeAccountId)
            ->where('subshop_id', $subshopId)
            ->where('is_active', true)
            ->first();

        if ($incomeAccount && (int) ($incomeAccount->accountClass?->code ?? 0) !== 4) {
            return back()
                ->withInput()
                ->with('error', 'The selected recovery income account must be a Class 4 (Revenue) account.');
        }

        if (! $incomeAccount) {
            return back()
                ->withInput()
                ->with('error', 'The selected recovery income account is invalid. It must be an active Class 4 (Revenue) account belonging to your branch.');
        }

        try {
            $config = LoanWriteOffAccount::updateOrCreate(
                ['subshop_id' => $subshopId],
                [
                    'write_off_expense_account_id' => $expenseAccountId,
                    'recovery_income_account_id' => $incomeAccountId,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            Log::info('Loan write-off accounts configured', [
                'subshop_id' => $subshopId,
                'write_off_expense_account_id' => $expenseAccountId,
                'recovery_income_account_id' => $incomeAccountId,
                'config_id' => $config->id,
            ]);

            return redirect()
                ->route('accounting.loan-write-off-accounts.index')
                ->with('success', 'Loan write-off accounts configured successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to configure loan write-off accounts', [
                'subshop_id' => $subshopId,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to save configuration: ' . $e->getMessage());
        }
    }

    /**
     * Remove the loan write-off account configuration.
     */
    public function destroy(): RedirectResponse
    {
        $subshopId = session('subshop_id');

        if (! $subshopId) {
            return redirect()->route('subshops.choose');
        }

        try {
            LoanWriteOffAccount::where('subshop_id', $subshopId)->delete();

            Log::info('Loan write-off accounts configuration removed', [
                'subshop_id' => $subshopId,
            ]);

            return redirect()
                ->route('accounting.loan-write-off-accounts.index')
                ->with('success', 'Loan write-off accounts configuration removed successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to remove loan write-off accounts configuration', [
                'subshop_id' => $subshopId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to remove configuration: ' . $e->getMessage());
        }
    }
}
