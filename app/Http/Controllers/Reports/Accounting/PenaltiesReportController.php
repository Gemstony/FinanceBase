<?php

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\PenaltiesReportExport;
use App\Models\SubShop;
use App\Services\Reports\Accounting\PenaltiesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class PenaltiesReportController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly PenaltiesReportService $service,
    ) {
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

        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'subshop_id' => $subshopId,
            'penalty_type_id' => $request->integer('penalty_type_id'),
            'loan_product_id' => $request->integer('loan_product_id'),
            'status' => $request->query('status'),
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        // Get filter options
        $penaltyTypes = $this->service->getPenaltyTypes($accessibleSubshopIds);
        $loanProducts = $this->service->getLoanProducts($accessibleSubshopIds, $subshopId);

        $exportQuery = array_filter([
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
            'penalty_type_id' => $filters['penalty_type_id'],
            'loan_product_id' => $filters['loan_product_id'],
            'status' => $filters['status'],
            'per_page' => $filters['per_page'],
        ], fn ($v) => !is_null($v) && $v !== '');

        return view('reports.accounting.penalties_report', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'penaltyTypes' => $penaltyTypes,
            'loanProducts' => $loanProducts,
            'filters' => $filters,
            'report' => $data,
            'exportUrl' => route('reports.accounting.penalties.export', array_merge($exportQuery, ['format' => 'xlsx'])),
            'pdfUrl' => route('reports.accounting.penalties.export', array_merge($exportQuery, ['format' => 'pdf'])),
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

        $dateFrom = $request->date('date_from') ?: Carbon::now()->subDays(29)->startOfDay();
        $dateTo = $request->date('date_to') ?: Carbon::now()->endOfDay();

        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'subshop_id' => $subshopId,
            'penalty_type_id' => $request->integer('penalty_type_id'),
            'loan_product_id' => $request->integer('loan_product_id'),
            'status' => $request->query('status'),
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'penalties-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.penalties_report', [
                'report' => $data,
                'dateFrom' => $dateFrom->toDateString(),
                'dateTo' => $dateTo->toDateString(),
                'subshopName' => $subshopName,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
        $export = new PenaltiesReportExport($data, $dateFrom->toDateString(), $dateTo->toDateString(), $subshopName);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}