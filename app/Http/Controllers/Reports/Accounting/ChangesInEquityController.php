<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\ChangesInEquityExport;
use App\Http\Controllers\Controller;
use App\Models\SubShop;
use App\Services\Reports\Accounting\ChangesInEquityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ChangesInEquityController extends Controller
{
    public function __construct(private readonly ChangesInEquityService $service)
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
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $commonParams = array_filter([
            'from_date' => $dateFrom->toDateString(),
            'to_date' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.accounting.changes_in_equity', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'report' => $report,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'exportUrl' => $report ? route('reports.accounting.changes_in_equity.export', array_merge(['format' => 'xlsx'], $commonParams)) : null,
            'pdfUrl' => $report ? route('reports.accounting.changes_in_equity.export', array_merge(['format' => 'pdf'], $commonParams)) : null,
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

        $filters = [
            'from_date' => $request->date('from_date') ? Carbon::parse((string) $request->date('from_date')) : null,
            'to_date' => $request->date('to_date') ? Carbon::parse((string) $request->date('to_date')) : null,
            'subshop_id' => $subshopId,
        ];

        if (!$filters['from_date'] && !$filters['to_date']) {
            abort(422, 'Date range is required');
        }

        $report = $this->service->export($filters, $accessibleSubshopIds);

        $filenameBase = 'changes-in-equity-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.changes_in_equity', [
                'report' => $report,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'subshopName' => $subshopName,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new ChangesInEquityExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
