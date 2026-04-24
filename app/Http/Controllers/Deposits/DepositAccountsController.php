<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deposits;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\CustomerDepositLiabilityAccount;
use App\Models\Customers;
use App\Models\DepositAccount;
use App\Models\DepositProduct;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Deposits\DepositAccountService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class DepositAccountsController extends Controller
{
    public function __construct(private readonly DepositAccountService $service)
    {
    }

    public function destroy(int $deposit_account, Request $request): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            return back()->with('error', 'Active branch context is required.');
        }

        try {
            DB::transaction(function () use ($deposit_account, $subshopId) {
                $account = DepositAccount::query()
                    ->whereKey($deposit_account)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $account->subshop_id !== $subshopId) {
                    abort(403);
                }

                if (round((float) $account->balance, 2) !== 0.0) {
                    throw new InvalidArgumentException('Only zero-balance accounts can be deleted.');
                }

                if ($account->depositTransactions()->exists()) {
                    throw new InvalidArgumentException('Account cannot be deleted because it has a transaction history.');
                }

                $account->delete();
            });

            return redirect()->route('deposits.index')->with('success', 'Deposit account deleted successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to delete deposit account.';
            return back()->with('error', $msg);
        }
    }

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $query = DepositAccount::query()
            ->with(['customer', 'depositProduct'])
            ->where('subshop_id', $subshopId)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('borrower')) {
            $borrower = (string) $request->string('borrower');
            $query->whereHas('customer', function ($q) use ($borrower) {
                $q->where('name', 'like', '%' . $borrower . '%');
            });
        }

        if ($request->filled('product')) {
            $query->whereHas('depositProduct', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->string('product') . '%');
            });
        }

        $summaryTotalAccounts = (clone $query)->count();
        $summaryTotalBalance = (float) (clone $query)->sum('balance');
        $summaryActiveAccounts = (clone $query)->where('status', 'active')->count();
        $summaryFrozenAccounts = (clone $query)->where('status', 'frozen')->count();
        $summaryDormantAccounts = (clone $query)->where('status', 'dormant')->count();
        $summaryClosedAccounts = (clone $query)->where('status', 'closed')->count();

        $accounts = $query->paginate(20)->withQueryString();

        return view('customer_deposits.index', compact(
            'accounts',
            'summaryTotalAccounts',
            'summaryTotalBalance',
            'summaryActiveAccounts',
            'summaryFrozenAccounts',
            'summaryDormantAccounts',
            'summaryClosedAccounts',
        ));
    }

    public function show(Customers $customer, Request $request): View
    {
        $subshopId = (int) session('subshop_id');


        // Get shop-level context
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        if (!$shopSubshopIds->contains($customer->subshop_id)) {
            abort(403);
        }

        $accounts = $this->service->getCustomerAccounts((int) $customer->id)
            ->paginate(20)
            ->withQueryString();

        // Get shop-level deposit products (accessible by all subshops under the same shop)
        $depositProducts = DepositProduct::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $activeLoans = Loans::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('customer_id', (int) $customer->id)
            ->whereIn('status', ['disbursed', 'partially_paid'])
            ->where('outstanding_balance', '>', 0)
            ->get(['id', 'loan_code', 'outstanding_balance']);

        $bankAccounts = BankAccounts::query()
            ->where('subshop_id', $subshopId)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get();

        // Get shop-level configuration (shared across all subshops under the same shop)
        $liabilityConfig = CustomerDepositLiabilityAccount::forShop($shopId);
        $liabilityConfigured = $liabilityConfig !== null;

        return view('customer_deposits.show', compact('customer', 'accounts', 'depositProducts', 'activeLoans', 'bankAccounts', 'liabilityConfig', 'liabilityConfigured'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $subshopId = (int) session('subshop_id');

        // Get shop-level deposit products (accessible by all subshops under the same shop)
        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $depositProducts = DepositProduct::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        if ($depositProducts->isEmpty()) {
            return back()->with('error', 'No active deposit products found. Create a deposit product first.');
        }

        $customers = Customers::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('customer_deposits.create', compact('depositProducts', 'customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'deposit_product_id' => ['required', 'integer', 'exists:deposit_products,id'],
            'account_number' => ['nullable', 'string', 'max:50', 'unique:deposit_accounts,account_number'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $this->service->createAccount(
                    (int) $validated['customer_id'],
                    (int) $validated['deposit_product_id'],
                    $validated['account_number'] ?? null
                );
            });

            return redirect()->route('deposits.index')->with('success', 'Deposit account created successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to create deposit account.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function deposit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_account_id' => ['required', 'integer', 'exists:deposit_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array((string) $validated['payment_method'], ['bank_transfer', 'mobile_money'], true) && empty($validated['bank_account_id'])) {
            return redirect()->back()->with('error', 'Please select a bank account for this payment method.');
        }

        try {
            DB::transaction(function () use ($validated) {
                $account = DepositAccount::query()->findOrFail((int) $validated['deposit_account_id']);
                $this->service->deposit(
                    $account,
                    (float) $validated['amount'],
                    (string) $validated['payment_method'],
                    $validated['bank_account_id'] ? (int) $validated['bank_account_id'] : null,
                    $validated['reference'] ?? null,
                    $validated['notes'] ?? null
                );
            });

            return redirect()->back()->with('success', 'Deposit recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Deposit failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to record deposit.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_account_id' => ['required', 'integer', 'exists:deposit_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array((string) $validated['payment_method'], ['bank_transfer', 'mobile_money'], true) && empty($validated['bank_account_id'])) {
            return redirect()->back()->with('error', 'Please select a bank account for this payment method.');
        }

        try {
            DB::transaction(function () use ($validated) {
                $account = DepositAccount::query()->findOrFail((int) $validated['deposit_account_id']);
                $this->service->withdraw(
                    $account,
                    (float) $validated['amount'],
                    (string) $validated['payment_method'],
                    $validated['bank_account_id'] ? (int) $validated['bank_account_id'] : null,
                    $validated['reference'] ?? null,
                    $validated['notes'] ?? null
                );
            });

            return redirect()->back()->with('success', 'Withdrawal recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Withdrawal failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to record withdrawal.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function transfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_account_id' => ['required', 'integer', 'exists:deposit_accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:deposit_accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $from = DepositAccount::query()->findOrFail((int) $validated['from_account_id']);
                $to = DepositAccount::query()->findOrFail((int) $validated['to_account_id']);
                $this->service->transfer(
                    $from,
                    $to,
                    (float) $validated['amount'],
                    $validated['reference'] ?? null,
                    $validated['notes'] ?? null
                );
            });

            return redirect()->back()->with('success', 'Transfer completed successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to complete transfer.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function payLoan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deposit_account_id' => ['required', 'integer', 'exists:deposit_accounts,id'],
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $account = DepositAccount::query()->findOrFail((int) $validated['deposit_account_id']);
                $loan = Loans::query()->findOrFail((int) $validated['loan_id']);
                $this->service->payLoanInstallment(
                    $account,
                    $loan,
                    (float) $validated['amount'],
                    $validated['reference'] ?? null,
                    $validated['notes'] ?? null
                );
            });

            return redirect()->back()->with('success', 'Loan installment paid from savings successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to pay loan from savings.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function transactions(int $deposit_account, Request $request): View
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            abort(403);
        }

        $account = DepositAccount::query()
            ->whereKey($deposit_account)
            ->where('subshop_id', $subshopId)
            ->firstOrFail();

        $transactions = $account->depositTransactions()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('customer_deposits.transactions', compact('account', 'transactions'));
    }

    public function configureLiabilityAccount(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'chart_of_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);

            $subshopId = (int) session('subshop_id');
            $subshop = SubShop::findOrFail($subshopId);
            $shopId = $subshop->shop_id;

            // Get all subshop IDs under this shop for validation
            $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

            $chartAccount = ChartsOfAccount::query()->whereKey($validated['chart_of_account_id'])->firstOrFail();

            if ((int) $chartAccount->accountClass->code !== 2) {
                return redirect()->back()
                    ->with('error', 'Selected account must be a liability account (Account Class 2).');
            }

            // Validate account belongs to any subshop under the same shop (shop-level scope)
            if (!$shopSubshopIds->contains($chartAccount->subshop_id)) {
                return redirect()->back()
                    ->with('error', 'Selected account does not belong to this shop.');
            }

            if (!$chartAccount->is_active) {
                return redirect()->back()
                    ->with('error', 'Selected account is not active.');
            }

            // Create or update liability account configuration at shop level
            CustomerDepositLiabilityAccount::updateOrCreate(
                ['shop_id' => $shopId],
                [
                    'chart_of_account_id' => (int) $validated['chart_of_account_id'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return redirect()->back()
                ->with('success', 'Customer deposits liability account configured successfully for all branches.');

        } catch (\Exception $e) {
            Log::error('Deposit liability account configuration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to configure liability account: ' . $e->getMessage());
        }
    }
}
