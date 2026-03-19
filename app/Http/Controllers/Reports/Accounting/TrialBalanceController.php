<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\TrialBalanceExport;
use App\Http\Controllers\Controller;
use App\Models\AccountClass;
use App\Models\JournalEntryLines;
use App\Models\SubShop;
use App\Services\Reports\Accounting\TrialBalanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class TrialBalanceController extends Controller
{
    public function __construct(private readonly TrialBalanceService $service)
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

        $asOf = $request->date('as_of') ?: Carbon::now()->endOfDay();

        $filters = [
            'as_of' => $asOf,
            'subshop_id' => $subshopId,
            'account_class_id' => $request->integer('account_class_id') ?: null,
            'hide_zero' => $request->boolean('hide_zero', false),
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $accountClasses = AccountClass::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $commonParams = array_filter([
            'as_of' => $asOf->toDateString(),
            'subshop_id' => $subshopId,
            'account_class_id' => $filters['account_class_id'],
            'hide_zero' => $filters['hide_zero'] ? 1 : 0,
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.trial_balance', [
            'asOf' => $asOf->toDateString(),
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'accountClasses' => $accountClasses,
            'hideZero' => (bool) $filters['hide_zero'],
            'report' => $report,
            'exportUrl' => route('reports.accounting.trial_balance.export', array_merge(['format' => 'xlsx'], $commonParams)),
            'pdfUrl' => route('reports.accounting.trial_balance.export', array_merge(['format' => 'pdf'], $commonParams)),
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

        $asOf = $request->date('as_of') ?: Carbon::now()->endOfDay();

        $filters = [
            'as_of' => $asOf,
            'subshop_id' => $subshopId,
            'account_class_id' => $request->integer('account_class_id') ?: null,
            'hide_zero' => $request->boolean('hide_zero', false),
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'trial-balance-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.trial_balance', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'asOf' => $asOf->toDateString(),
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new TrialBalanceExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function accountLines(int $accountId, Request $request)
    {
        $subshopId = $request->integer('subshop_id') ?: (int) session('subshop_id');
        $asOf = $request->date('as_of') ? Carbon::parse((string) $request->date('as_of')) : Carbon::now()->endOfDay();

        $lines = JournalEntryLines::query()
            ->with(['journalEntry'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($subshopId, $asOf) {
                $q->where('subshop_id', $subshopId)
                    ->whereDate('transaction_date', '<=', $asOf->toDateString());
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderByDesc('journal_entries.transaction_date')
            ->orderByDesc('journal_entry_lines.id')
            ->paginate(50)
            ->withQueryString();

        return view('reports.accounting.account_lines', [
            'lines' => $lines,
            'accountId' => $accountId,
            'asOf' => $asOf->toDateString(),
            'subshopId' => $subshopId,
        ]);
    }
}
