<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ParReportExport;
use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Reports\ParReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ParReportController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly ParReportService $service,
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

        $asAtDate = $request->date('as_at_date') ?: Carbon::now()->endOfDay();

        $filters = [
            'as_at_date' => $asAtDate,
            'subshop_id' => $subshopId,
            'loan_product_id' => $request->integer('loan_product_id'),
            'loan_officer_id' => $request->integer('loan_officer_id'),
            'loan_status' => $request->query('loan_status'),
            'dpd_min' => $request->integer('dpd_min') ?: null,
            'dpd_max' => $request->integer('dpd_max') ?: null,
            'page' => $request->integer('page') ?: 1,
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $productQ = LoanProducts::query()->orderBy('name');
        if ($subshopId) {
            $productQ->where('subshop_id', $subshopId);
        } else {
            $productQ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1]);
        }
        $loanProducts = $productQ->get(['id', 'name']);

        $officerSubshopIds = $subshopId ? [$subshopId] : ($accessibleSubshopIds ?: [-1]);
        $officers = User::query()
            ->where(function ($q) use ($shop, $officerSubshopIds) {
                $q->whereHas('shop', function ($sq) use ($shop) {
                    $sq->where('id', $shop->id);
                })->orWhereHas('subshops', function ($sq) use ($shop, $officerSubshopIds) {
                    $sq->where('sub_shops.shop_id', $shop->id)
                        ->whereIn('sub_shops.id', $officerSubshopIds)
                        ->where('subshop_user.is_active', true);
                });
            })
            ->orderBy('name')
            ->distinct()
            ->get(['id', 'name']);

        $commonParams = array_filter([
            'as_at_date' => $asAtDate->toDateString(),
            'subshop_id' => $subshopId,
            'loan_product_id' => $filters['loan_product_id'],
            'loan_officer_id' => $filters['loan_officer_id'],
            'loan_status' => $filters['loan_status'],
            'dpd_min' => $filters['dpd_min'],
            'dpd_max' => $filters['dpd_max'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('reports.par', [
            'asAtDate' => $asAtDate->toDateString(),
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'loanProducts' => $loanProducts,
            'officers' => $officers,
            'filters' => $filters,
            'report' => $report,
            'exportUrl' => route('reports.par.export', array_merge(['format' => 'xlsx'], $commonParams)),
            'pdfUrl' => route('reports.par.export', array_merge(['format' => 'pdf'], $commonParams)),
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

        $asAtDate = $request->date('as_at_date') ?: Carbon::now()->endOfDay();

        $filters = [
            'as_at_date' => $asAtDate,
            'subshop_id' => $subshopId,
            'loan_product_id' => $request->integer('loan_product_id'),
            'loan_officer_id' => $request->integer('loan_officer_id'),
            'loan_status' => $request->query('loan_status'),
            'dpd_min' => $request->integer('dpd_min') ?: null,
            'dpd_max' => $request->integer('dpd_max') ?: null,
            'per_page' => 200,
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'par-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.par', [
                'report' => $report,
                'asAtDate' => $asAtDate->toDateString(),
                'subshopName' => $subshopName,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new ParReportExport($report);

        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
