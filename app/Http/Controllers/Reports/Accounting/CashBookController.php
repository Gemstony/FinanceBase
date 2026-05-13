<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\CashBookExport;
use App\Http\Controllers\Controller;
use App\Models\BankAccounts;
use App\Models\ChartsOfAccount;
use App\Models\JournalEntries;
use App\Models\SubShop;
use App\Services\Reports\Accounting\CashBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class CashBookController extends Controller
{
    public function __construct(private readonly CashBookService $service)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) {
            abort(403, 'No shop found for user');
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }

        $bankLinkedAccountIds = BankAccounts::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', 1)
            ->pluck('chart_of_account_id')
            ->filter()
            ->values()
            ->all();

        $cashAccountId = $request->integer('cash_account_id');

        $dateFrom = $request->date('from_date') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('to_date') ?: Carbon::now()->endOfDay();

        $filters = [
            'cash_account_id' => $cashAccountId ?: 0,
            'from_date' => $dateFrom,
            'to_date' => $dateTo,
            'subshop_id' => $subshopId,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'reference_search' => $request->filled('reference') ? (string) $request->input('reference') : null,
            'per_page' => $request->integer('per_page') ?: 50,
            'page' => $request->integer('page') ?: null,
        ];

        $report = $cashAccountId ? $this->service->build($filters, $accessibleSubshopIds) : null;

        $cashAccounts = ChartsOfAccount::query()
            ->join('account_classes as ac', 'ac.id', '=', 'charts_of_accounts.account_class_id')
            ->where('ac.code', '1')  // Assets class
            ->whereIn('charts_of_accounts.subshop_id', $accessibleSubshopIds)
            ->where('charts_of_accounts.is_active', 1)
            ->orderBy('charts_of_accounts.account_name')
            ->get(['charts_of_accounts.id', 'charts_of_accounts.account_code', 'charts_of_accounts.account_name']);

        $referenceTypes = JournalEntries::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->distinct()
            ->orderBy('reference_type')
            ->pluck('reference_type')
            ->filter()
            ->values();

        $commonParams = array_filter([
            'cash_account_id' => $cashAccountId,
            'from_date' => $dateFrom->toDateString(),
            'to_date' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
            'reference_type' => $filters['reference_type'],
            'reference' => $filters['reference_search'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.cash_book', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'cashAccounts' => $cashAccounts,
            'selectedCashAccountId' => $cashAccountId,
            'referenceTypes' => $referenceTypes,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'report' => $report,
            'exportUrl' => $cashAccountId ? route('reports.accounting.cash_book.export', array_merge(['format' => 'xlsx'], $commonParams)) : null,
            'pdfUrl' => $cashAccountId ? route('reports.accounting.cash_book.export', array_merge(['format' => 'pdf'], $commonParams)) : null,
        ]);
    }

    public function export(Request $request, string $format = 'xlsx')
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) {
            abort(403, 'No shop found for user');
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this subshop');
        }

        $bankLinkedAccountIds = BankAccounts::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', 1)
            ->pluck('chart_of_account_id')
            ->filter()
            ->values()
            ->all();

        $cashAccountId = $request->integer('cash_account_id');
        if (!$cashAccountId) {
            abort(422, 'Cash/Bank account is required');
        }

        if (!in_array($cashAccountId, $bankLinkedAccountIds, true)) {
            abort(422, 'Invalid cash/bank account selection');
        }

        $from = $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null;
        $to = $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null;

        if (!$from || !$to) {
            abort(422, 'Date range is required');
        }

        $filters = [
            'cash_account_id' => (int) $cashAccountId,
            'from_date' => $from,
            'to_date' => $to,
            'subshop_id' => $subshopId,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'reference_search' => $request->filled('reference') ? (string) $request->input('reference') : null,
        ];

        $report = $this->service->export($filters, $accessibleSubshopIds);

        $filenameBase = 'cash-book-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.cash_book', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new CashBookExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
