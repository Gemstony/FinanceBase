<?php

namespace App\Http\Controllers\Payments;

use App\Exports\Payments\PaymentTransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\PaymentTransaction;
use App\Models\SubShop;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Get the current shop ID from session.
     */
    protected function getShopId(): int
    {
        $subshopId = session('subshop_id');
        if (!$subshopId) {
            abort(403, 'No subshop selected');
        }

        $subshop = SubShop::findOrFail($subshopId);
        return $subshop->shop_id;
    }

    /**
     * Display payment configurations.
     */
    public function configs(Request $request)
    {
        $shopId = $this->getShopId();

        $configs = PaymentConfig::where('shop_id', $shopId)
            ->orderBy('provider')
            ->get();

        return view('payments.configs.index', compact('configs'));
    }

    /**
     * Show form for creating payment config.
     */
    public function createConfig()
    {
        $this->getShopId(); // Ensure subshop is selected
        return view('payments.configs.create');
    }

    /**
     * Store payment configuration.
     */
    public function storeConfig(Request $request)
    {
        $shopId = $this->getShopId();

        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:mpesa,airtel,tigo',
            'api_url' => 'required|url',
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'shortcode' => 'nullable|string',
            'passkey' => 'nullable|string',
            'environment' => 'required|in:sandbox,live',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check if provider already exists for this shop
        $existingConfig = PaymentConfig::where('shop_id', $shopId)
            ->where('provider', $request->provider)
            ->first();

        if ($existingConfig) {
            return redirect()->back()
                ->with('error', 'Provider configuration already exists for this shop.')
                ->withInput();
        }

        PaymentConfig::create([
            'shop_id' => $shopId,
            'provider' => $request->provider,
            'api_url' => $request->api_url,
            'api_key' => $request->api_key,
            'secret_key' => $request->secret_key,
            'shortcode' => $request->shortcode,
            'passkey' => $request->passkey,
            'environment' => $request->environment,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
        ]);

        return redirect()->route('payments.configs')
            ->with('success', 'Payment configuration created successfully.');
    }

    /**
     * Show form for editing payment config.
     */
    public function editConfig(Request $request, $id)
    {
        $shopId = $this->getShopId();

        $config = PaymentConfig::where('shop_id', $shopId)
            ->where('id', $id)
            ->firstOrFail();

        return view('payments.configs.edit', compact('config'));
    }

    /**
     * Update payment configuration.
     */
    public function updateConfig(Request $request, $id)
    {
        $shopId = $this->getShopId();

        $validator = Validator::make($request->all(), [
            'api_url' => 'required|url',
            'api_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'shortcode' => 'nullable|string',
            'passkey' => 'nullable|string',
            'environment' => 'required|in:sandbox,live',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $config = PaymentConfig::where('shop_id', $shopId)
            ->where('id', $id)
            ->firstOrFail();

        $updateData = [
            'api_url' => $request->api_url,
            'shortcode' => $request->shortcode,
            'environment' => $request->environment,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
        ];

        // Only update credentials if provided
        if ($request->filled('api_key')) {
            $updateData['api_key'] = $request->api_key;
        }

        if ($request->filled('secret_key')) {
            $updateData['secret_key'] = $request->secret_key;
        }

        if ($request->filled('passkey')) {
            $updateData['passkey'] = $request->passkey;
        }

        $config->update($updateData);

        return redirect()->route('payments.configs')
            ->with('success', 'Payment configuration updated successfully.');
    }

    /**
     * Delete payment configuration.
     */
    public function deleteConfig(Request $request, $id)
    {
        $shopId = $this->getShopId();

        $config = PaymentConfig::where('shop_id', $shopId)
            ->where('id', $id)
            ->firstOrFail();

        $config->delete();

        return redirect()->route('payments.configs')
            ->with('success', 'Payment configuration deleted successfully.');
    }

    /**
     * Set default payment configuration.
     */
    public function setDefaultConfig(Request $request, $id)
    {
        $shopId = $this->getShopId();

        $config = PaymentConfig::where('shop_id', $shopId)
            ->where('id', $id)
            ->firstOrFail();

        $config->setAsDefault();

        return redirect()->route('payments.configs')
            ->with('success', 'Default payment configuration updated.');
    }

    /**
     * Display transactions.
     */
    public function transactions(Request $request)
    {
        $shopId = $this->getShopId();
        $subshopId = session('subshop_id');

        $query = PaymentTransaction::where('shop_id', $shopId);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('loan_id')) {
            $query->where('loan_id', $request->loan_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('payments.transactions.index', compact('transactions'));
    }

    /**
     * Show transaction details.
     */
    public function showTransaction(Request $request, $id)
    {
        $shopId = $this->getShopId();

        $transaction = PaymentTransaction::where('shop_id', $shopId)
            ->where('id', $id)
            ->with(['logs', 'customer', 'loan'])
            ->firstOrFail();

        return view('payments.transactions.show', compact('transaction'));
    }

    /**
     * Initiate a payment.
     */
    public function initiatePayment(Request $request)
    {
        $shopId = $this->getShopId();
        $subshopId = session('subshop_id');

        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'loan_id' => 'nullable|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'phone' => 'required|string',
            'channel' => 'required|in:stk,c2b,b2c',
            'provider' => 'nullable|in:mpesa,airtel,tigo',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $transaction = $this->paymentManager->initiatePayment([
                'shop_id' => $shopId,
                'subshop_id' => $subshopId,
                'customer_id' => $request->customer_id,
                'loan_id' => $request->loan_id,
                'amount' => $request->amount,
                'phone' => $request->phone,
                'channel' => $request->channel,
                'provider' => $request->provider,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully.',
                'transaction' => [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export transactions to Excel.
     */
    public function export(Request $request)
    {
        $shopId = $this->getShopId();

        $filters = [
            'status' => $request->status,
            'provider' => $request->provider,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        return Excel::download(
            new PaymentTransactionsExport($shopId, $filters),
            'payment-transactions-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Get transaction statistics.
     */
    public function stats(Request $request)
    {
        $shopId = $this->getShopId();

        $stats = [
            'total_transactions' => PaymentTransaction::where('shop_id', $shopId)->count(),
            'successful_transactions' => PaymentTransaction::where('shop_id', $shopId)->where('status', 'success')->count(),
            'failed_transactions' => PaymentTransaction::where('shop_id', $shopId)->where('status', 'failed')->count(),
            'pending_transactions' => PaymentTransaction::where('shop_id', $shopId)->whereIn('status', ['initiated', 'pending'])->count(),
            'total_amount' => PaymentTransaction::where('shop_id', $shopId)->where('status', 'success')->sum('amount'),
            'by_provider' => PaymentTransaction::where('shop_id', $shopId)
                ->selectRaw('provider, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('provider')
                ->get(),
            'by_status' => PaymentTransaction::where('shop_id', $shopId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
        ];

        return response()->json($stats);
    }
}
