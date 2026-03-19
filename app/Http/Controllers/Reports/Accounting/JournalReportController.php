<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\JournalReportExport;
use App\Http\Controllers\Controller;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Reports\Accounting\JournalReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class JournalReportController extends Controller
{
    public function __construct(private readonly JournalReportService $service)
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
            'reference' => $request->filled('reference') ? (string) $request->input('reference') : null,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'created_by' => $request->integer('created_by') ?: null,
            'per_page' => $request->integer('per_page') ?: 15,
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $referenceTypes = \App\Models\JournalEntries::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->select('reference_type')
            ->distinct()
            ->orderBy('reference_type')
            ->pluck('reference_type')
            ->filter(fn ($v) => (string) $v !== '')
            ->values();

        $creatorIds = \App\Models\JournalEntries::query()
            ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1])
            ->select('created_by')
            ->distinct()
            ->pluck('created_by')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $creators = empty($creatorIds)
            ? collect()
            : User::query()->whereIn('id', $creatorIds)->orderBy('name')->get(['id', 'name']);

        $commonParams = array_filter([
            'from_date' => $dateFrom->toDateString(),
            'to_date' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
            'reference' => $filters['reference'],
            'reference_type' => $filters['reference_type'],
            'created_by' => $filters['created_by'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.journal_report', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'referenceTypes' => $referenceTypes,
            'creators' => $creators,
            'filters' => $filters,
            'report' => $report,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'exportUrl' => route('reports.accounting.journal_report.export', array_merge(['format' => 'xlsx'], $commonParams)),
            'pdfUrl' => route('reports.accounting.journal_report.export', array_merge(['format' => 'pdf'], $commonParams)),
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
            'reference' => $request->filled('reference') ? (string) $request->input('reference') : null,
            'reference_type' => $request->filled('reference_type') ? (string) $request->input('reference_type') : null,
            'created_by' => $request->integer('created_by') ?: null,
        ];

        $report = $this->service->export($filters, $accessibleSubshopIds);

        $filenameBase = 'journal-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.journal_report', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new JournalReportExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
