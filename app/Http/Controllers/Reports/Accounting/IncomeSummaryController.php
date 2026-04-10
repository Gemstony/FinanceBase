<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\IncomeSummaryExport;
use App\Http\Controllers\Controller;
use App\Models\AccountGroups;
use App\Models\ChartsOfAccount;
use App\Models\SubShop;
use App\Services\Reports\Accounting\IncomeSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class IncomeSummaryController extends Controller
{
    public function __construct(private readonly IncomeSummaryService $service)
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

        $dateFrom = $request->date('from_date') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('to_date') ?: Carbon::now()->endOfDay();

        $filters = [
            'from_date' => $dateFrom,
            'to_date' => $dateTo,
            'subshop_id' => $subshopId,
            'account_group_id' => $request->integer('account_group_id') ?: null,
            'income_account_id' => $request->integer('income_account_id') ?: null,
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $accountGroups = AccountGroups::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $incomeAccounts = ChartsOfAccount::query()
            ->join('account_classes as ac', 'ac.id', '=', 'charts_of_accounts.account_class_id')
            ->whereIn('charts_of_accounts.subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('charts_of_accounts.is_active', 1)
            ->where(function ($w) {
                $w->whereRaw("UPPER(ac.code) LIKE '4%'")
                ->whereRaw("UPPER(ac.name) = 'INCOME'");
            })
            ->orderBy('charts_of_accounts.account_name')
            ->get([
                'charts_of_accounts.id',
                'charts_of_accounts.account_code',
                'charts_of_accounts.account_name'
            ]);

        $commonParams = array_filter([
            'from_date' => $dateFrom->toDateString(),
            'to_date' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
            'account_group_id' => $filters['account_group_id'],
            'income_account_id' => $filters['income_account_id'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.income_summary', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'accountGroups' => $accountGroups,
            'selectedAccountGroupId' => $filters['account_group_id'],
            'incomeAccounts' => $incomeAccounts,
            'selectedIncomeAccountId' => $filters['income_account_id'],
            'report' => $report,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'exportUrl' => route('reports.accounting.income_summary.export', array_merge(['format' => 'xlsx'], $commonParams)),
            'pdfUrl' => route('reports.accounting.income_summary.export', array_merge(['format' => 'pdf'], $commonParams)),
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

        $from = $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null;
        $to = $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null;

        if (!$from || !$to) {
            abort(422, 'Date range is required');
        }

        $filters = [
            'from_date' => $from,
            'to_date' => $to,
            'subshop_id' => $subshopId,
            'account_group_id' => $request->integer('account_group_id') ?: null,
            'income_account_id' => $request->integer('income_account_id') ?: null,
        ];

        $report = $this->service->export($filters, $accessibleSubshopIds);

        $filenameBase = 'income-summary-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.income_summary', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new IncomeSummaryExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
