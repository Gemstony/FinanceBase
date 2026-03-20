<?php

namespace App\Http\Controllers\Reports\Accounting;

use App\Exports\Reports\Accounting\FeesReportExport;
use App\Models\SubShop;
use App\Services\Reports\Accounting\FeesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class FeesReportController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly FeesReportService $service,
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
            'fee_type_id' => $request->integer('fee_type_id'),
            'loan_product_id' => $request->integer('loan_product_id'),
            'status' => $request->query('status'),
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        // Get filter options
        $feeTypes = $this->service->getFeeTypes($accessibleSubshopIds);
        $loanProducts = $this->service->getLoanProducts($accessibleSubshopIds, $subshopId);

        $exportQuery = array_filter([
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
            'fee_type_id' => $filters['fee_type_id'],
            'loan_product_id' => $filters['loan_product_id'],
            'status' => $filters['status'],
            'per_page' => $filters['per_page'],
        ], fn ($v) => !is_null($v) && $v !== '');

        return view('reports.accounting.fees_report', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'feeTypes' => $feeTypes,
            'loanProducts' => $loanProducts,
            'filters' => $filters,
            'report' => $data,
            'exportUrl' => route('reports.accounting.fees.export', array_merge($exportQuery, ['format' => 'xlsx'])),
            'pdfUrl' => route('reports.accounting.fees.export', array_merge($exportQuery, ['format' => 'pdf'])),
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
            'fee_type_id' => $request->integer('fee_type_id'),
            'loan_product_id' => $request->integer('loan_product_id'),
            'status' => $request->query('status'),
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'fees-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.accounting.fees_report', [
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
        $export = new FeesReportExport($data, $dateFrom->toDateString(), $dateTo->toDateString(), $subshopName);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
