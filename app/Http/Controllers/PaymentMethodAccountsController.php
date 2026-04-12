<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChartsOfAccount;
use App\Models\PaymentMethodAccount;
use App\Models\SubShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentMethodAccountsController extends Controller
{
    private const ACCOUNT_CLASS_CODE_ASSETS = 1;
    private const ACCOUNT_CLASS_CODE_LIABILITIES = 2;

    private const PAYMENT_METHODS_ASSET_ACCOUNTS = [
        'cash',
        'bank',
        'azampay',
    ];

    private const PAYMENT_METHODS_LIABILITY_ACCOUNTS = [
        'savings',
        'customer_credit',
    ];

    public function index()
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.payment_method_accounts.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');

        $paymentMethodAccounts = PaymentMethodAccount::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->latest()
            ->get();

        $assetsAccounts = ChartsOfAccount::with('accountClass')
            ->whereIn('subshop_id', $shopSubshopIds)
            ->whereHas('accountClass', function ($query) {
                $query->where('code', self::ACCOUNT_CLASS_CODE_ASSETS);
            })
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name', 'subshop_id', 'account_class_id']);

        $liabilityAccounts = ChartsOfAccount::with('accountClass')
            ->whereIn('subshop_id', $shopSubshopIds)
            ->whereHas('accountClass', function ($query) {
                $query->where('code', self::ACCOUNT_CLASS_CODE_LIABILITIES);
            })
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name', 'subshop_id', 'account_class_id']);

        return view('accounting.accounting_settings.payment_method_accounts', compact(
            'subshop',
            'paymentMethodAccounts',
            'assetsAccounts',
            'liabilityAccounts'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        Log::info('Payment method account mapping store started', [
            'user_id' => Auth::id(),
            'subshop_id' => session('subshop_id'),
            'request_data' => $request->except(['_token']),
        ]);

        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            Log::warning('Invalid subshop ID in payment method account store', [
                'subshop_id' => $subshopId,
            ]);
            return redirect()->route('subshops.choose', ['intended' => route('accounting.payment_method_accounts.index')]);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'chart_of_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
        ]);

        Log::info('Payment method account validation passed', [
            'validated_data' => $validated,
        ]);

        $paymentMethod = trim(strtolower($validated['payment_method']));
        $chartOfAccountId = (int) $validated['chart_of_account_id'];

        Log::info('Processing payment method account mapping', [
            'payment_method' => $paymentMethod,
            'chart_of_account_id' => $chartOfAccountId,
            'subshop_id' => $subshopId,
        ]);

        try {
            $this->assertChartOfAccountAllowedForPaymentMethod($subshopId, $paymentMethod, $chartOfAccountId);
            Log::info('Payment method account validation passed');
        } catch (\Exception $e) {
            Log::error('Payment method account validation failed', [
                'error' => $e->getMessage(),
                'payment_method' => $paymentMethod,
                'chart_of_account_id' => $chartOfAccountId,
                'subshop_id' => $subshopId,
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }

        try {
            Log::info('Creating/updating payment method account mapping');
            
            DB::beginTransaction();

            $mapping = PaymentMethodAccount::updateOrCreate(
                [
                    'subshop_id' => $subshopId,
                    'payment_method' => $paymentMethod,
                ],
                [
                    'chart_of_account_id' => $chartOfAccountId,
                ]
            );

            Log::info('Payment method account mapping saved successfully', [
                'mapping_id' => $mapping->id,
                'payment_method' => $paymentMethod,
                'chart_of_account_id' => $chartOfAccountId,
                'subshop_id' => $subshopId,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Payment method mapping saved successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to save payment method account mapping', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_method' => $paymentMethod,
                'chart_of_account_id' => $chartOfAccountId,
                'subshop_id' => $subshopId,
            ]);
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to save mapping: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $subshopId = (int) session('subshop_id');
        if ($subshopId <= 0) {
            return redirect()->route('subshops.choose', ['intended' => route('accounting.payment_method_accounts.index')]);
        }

        $mapping = PaymentMethodAccount::findOrFail($id);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'chart_of_account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
        ]);

        $paymentMethod = trim(strtolower($validated['payment_method']));
        $chartOfAccountId = (int) $validated['chart_of_account_id'];

        try {
            $this->assertChartOfAccountAllowedForPaymentMethod($subshopId, $paymentMethod, $chartOfAccountId);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        try {
            DB::beginTransaction();

            $mapping->update([
                'subshop_id' => $subshopId,
                'payment_method' => $paymentMethod,
                'chart_of_account_id' => $chartOfAccountId,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Payment method mapping updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update mapping: ' . $e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $mapping = PaymentMethodAccount::findOrFail($id);
            $mapping->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment method mapping deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function assertChartOfAccountAllowedForPaymentMethod(int $subshopId, string $paymentMethod, int $chartOfAccountId): void
    {
        Log::info('Validating payment method account mapping', [
            'subshop_id' => $subshopId,
            'payment_method' => $paymentMethod,
            'chart_of_account_id' => $chartOfAccountId,
        ]);

        if ($subshopId <= 0) {
            Log::error('Invalid subshop_id provided', ['subshop_id' => $subshopId]);
            throw new InvalidArgumentException('subshop_id is required.');
        }
        if ($paymentMethod === '') {
            Log::error('Empty payment_method provided');
            throw new InvalidArgumentException('payment_method is required.');
        }
        if ($chartOfAccountId <= 0) {
            Log::error('Invalid chart_of_account_id provided', ['chart_of_account_id' => $chartOfAccountId]);
            throw new InvalidArgumentException('chart_of_account_id is required.');
        }

        $subshop = SubShop::findOrFail($subshopId);
        $shopSubshopIds = SubShop::where('shop_id', $subshop->shop_id)->pluck('id');
        
        Log::info('Shop subshop scope determined', [
            'shop_id' => $subshop->shop_id,
            'shop_subshop_ids' => $shopSubshopIds->toArray(),
        ]);

        $expectedClassCode = null;
        if (in_array($paymentMethod, self::PAYMENT_METHODS_ASSET_ACCOUNTS, true)) {
            $expectedClassCode = self::ACCOUNT_CLASS_CODE_ASSETS;
            Log::info('Payment method requires asset account', [
                'payment_method' => $paymentMethod,
                'expected_class_code' => $expectedClassCode,
            ]);
        }
        if (in_array($paymentMethod, self::PAYMENT_METHODS_LIABILITY_ACCOUNTS, true)) {
            $expectedClassCode = self::ACCOUNT_CLASS_CODE_LIABILITIES;
            Log::info('Payment method requires liability account', [
                'payment_method' => $paymentMethod,
                'expected_class_code' => $expectedClassCode,
            ]);
        }

        if ($expectedClassCode === null) {
            Log::error('Unsupported payment method', [
                'payment_method' => $paymentMethod,
                'supported_asset_methods' => self::PAYMENT_METHODS_ASSET_ACCOUNTS,
                'supported_liability_methods' => self::PAYMENT_METHODS_LIABILITY_ACCOUNTS,
            ]);
            throw new InvalidArgumentException("Unsupported payment method '{$paymentMethod}'.");
        }

        $coa = ChartsOfAccount::with('accountClass')
            ->whereKey($chartOfAccountId)
            ->first();

        if (!$coa) {
            Log::error('Chart of account not found', ['chart_of_account_id' => $chartOfAccountId]);
            throw new InvalidArgumentException('Chart of account not found.');
        }

        Log::info('Chart of account loaded', [
            'chart_of_account_id' => $coa->id,
            'account_name' => $coa->account_name,
            'account_code' => $coa->account_code,
            'subshop_id' => $coa->subshop_id,
            'account_class_code' => $coa->accountClass?->code,
            'account_class_name' => $coa->accountClass?->name,
            'is_active' => $coa->is_active,
        ]);

        if (!in_array((int) $coa->subshop_id, $shopSubshopIds->map(fn ($v) => (int) $v)->toArray(), true)) {
            Log::error('Chart of account not accessible for shop', [
                'account_subshop_id' => $coa->subshop_id,
                'allowed_subshop_ids' => $shopSubshopIds->toArray(),
            ]);
            throw new InvalidArgumentException('Selected chart of account is not accessible for your shop.');
        }

        $actualClassCode = (int) ($coa->accountClass?->code ?? 0);
        if ($actualClassCode !== (int) $expectedClassCode) {
            Log::error('Account class mismatch', [
                'actual_class_code' => $actualClassCode,
                'expected_class_code' => $expectedClassCode,
                'payment_method' => $paymentMethod,
                'account_class_name' => $coa->accountClass?->name,
            ]);
            throw new InvalidArgumentException('Selected chart of account class is not allowed for this payment method.');
        }

        if (!(bool) ($coa->is_active ?? false)) {
            Log::error('Chart of account is inactive', [
                'chart_of_account_id' => $chartOfAccountId,
                'account_name' => $coa->account_name,
            ]);
            throw new InvalidArgumentException('Selected chart of account is inactive.');
        }

        Log::info('Payment method account validation passed successfully', [
            'payment_method' => $paymentMethod,
            'chart_of_account_id' => $chartOfAccountId,
            'validated_class_code' => $actualClassCode,
        ]);
    }
}
