<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\SubShop;
use App\Models\Vouchers;
use App\Models\VoucherLines;
use App\Models\User;
use App\Services\Accounting\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class VoucherController extends Controller
{
    public function __construct(private readonly VoucherService $service)
    {
    }

    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $baseQuery = Vouchers::query()
            ->with(['bankAccount', 'creator'])
            ->where('subshop_id', $subshopId)
            ->orderByDesc('id');

        if ($request->filled('voucher_type')) {
            $baseQuery->where('voucher_type', (string) $request->input('voucher_type'));
        }

        if ($request->filled('payment_method')) {
            $baseQuery->where('payment_method', (string) $request->input('payment_method'));
        }

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('voucher_date', '>=', (string) $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $baseQuery->whereDate('voucher_date', '<=', (string) $request->input('date_to'));
        }

        $summaryTotalCount = (clone $baseQuery)->count();
        $summaryTotalAmount = (float) (clone $baseQuery)->sum('total_amount');

        $summaryReceiptCount = (clone $baseQuery)->where('voucher_type', 'receipt')->count();
        $summaryReceiptAmount = (float) (clone $baseQuery)->where('voucher_type', 'receipt')->sum('total_amount');

        $summaryPaymentCount = (clone $baseQuery)->where('voucher_type', 'payment')->count();
        $summaryPaymentAmount = (float) (clone $baseQuery)->where('voucher_type', 'payment')->sum('total_amount');

        $vouchers = $baseQuery->paginate(20)->withQueryString();

        return view('accounting.vouchers.index', compact(
            'vouchers',
            'summaryTotalCount',
            'summaryTotalAmount',
            'summaryReceiptCount',
            'summaryReceiptAmount',
            'summaryPaymentCount',
            'summaryPaymentAmount',
        ));
    }

    public function create(): View
    {
        $subshopId = (int) session('subshop_id');

        $subshop = SubShop::findOrFail($subshopId);
        $shopId = $subshop->shop_id;

        // Get all subshop IDs under this shop for validation
        $shopSubshopIds = SubShop::where('shop_id', $shopId)->pluck('id');

        $accounts = ChartsOfAccount::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', 1)
            ->orderBy('account_name')
            ->get(['id', 'account_code', 'account_name']);

        $bankAccounts = BankAccounts::query()
            ->whereIn('subshop_id', $shopSubshopIds)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->get(['id', 'account_name', 'bank_name', 'account_number']);

        return view('accounting.vouchers.create', compact('accounts', 'bankAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'voucher_type' => ['required', 'in:receipt,payment'],
            'voucher_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:charts_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $voucherType = (string) $validated['voucher_type'];
            $voucherDate = Carbon::parse((string) $validated['voucher_date']);

            $lines = [];
            foreach ($validated['lines'] as $line) {
                $lines[] = [
                    'account_id' => (int) $line['account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'description' => $line['description'] ?? null,
                ];
            }

            $amount = 0.0;
            foreach ($lines as $line) {
                $amount += (float) $line['debit'];
            }
            $amount = round($amount, 2);

            $payload = [
                'reference_type' => 'manual_voucher',
                'reference_id' => null,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'] ?? null,
                'bank_account_id' => !empty($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null,
                'voucher_date' => $voucherDate,
                'description' => $validated['description'] ?? null,
                'source_type' => 'manual',
                'created_by' => auth()->id(),
                'subshop_id' => (int) session('subshop_id'),
                'lines' => $lines,
            ];

            $voucher = $voucherType === 'receipt'
                ? $this->service->createReceiptVoucher($payload)
                : $this->service->createPaymentVoucher($payload);

            return redirect()->route('accounting.vouchers.show', (int) $voucher->id)
                ->with('success', 'Voucher created successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $msg = config('app.debug') ? ($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()) : 'Failed to create voucher.';
            return back()->withInput()->with('error', $msg);
        }
    }

    public function show(int $id): View
    {
        $subshopId = (int) session('subshop_id');

        $voucher = Vouchers::query()
            ->with(['bankAccount', 'creator', 'approver', 'lines.account'])
            ->where('subshop_id', $subshopId)
            ->whereKey($id)
            ->firstOrFail();

        $totalDebit = (float) $voucher->lines->sum('debit');
        $totalCredit = (float) $voucher->lines->sum('credit');

        return view('accounting.vouchers.show', compact('voucher', 'totalDebit', 'totalCredit'));
    }
}
