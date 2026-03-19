<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\LoanArrearsExport;
use App\Models\Customers;
use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Reports\LoanArrearsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LoanArrearsReportController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly LoanArrearsReportService $service,
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

        $asAtDate = $request->date('as_at_date');
        if (!$asAtDate) {
            $asAtDate = Carbon::today()->endOfDay();
        }

        $filters = [
            'as_at_date' => $asAtDate,
            'subshop_id' => $subshopId,
            'loan_product_id' => $request->integer('loan_product_id'),
            'loan_officer_id' => $request->integer('loan_officer_id'),
            'loan_status' => $request->query('loan_status'),
            'dpd_min' => $request->integer('dpd_min') ?: null,
            'dpd_max' => $request->integer('dpd_max') ?: null,
            'customer_id' => $request->integer('customer_id'),
            'loan_id' => $request->integer('loan_id') ?: null,
            'per_page' => $request->integer('per_page') ?: 25,
            'page' => $request->integer('page') ?: 1,
            'installments_page' => $request->integer('installments_page') ?: 1,
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

        $customer = null;
        if (!empty($filters['customer_id'])) {
            $custQ = Customers::query()->where('id', (int) $filters['customer_id']);
            if ($subshopId) {
                $custQ->where('subshop_id', $subshopId);
            } else {
                $custQ->whereIn('subshop_id', $accessibleSubshopIds ?: [-1]);
            }
            $customer = $custQ->first(['id', 'name']);
        }

        $exportQuery = array_filter([
            'as_at_date' => $asAtDate->toDateString(),
            'subshop_id' => $subshopId,
            'loan_product_id' => $filters['loan_product_id'],
            'loan_officer_id' => $filters['loan_officer_id'],
            'loan_status' => $filters['loan_status'],
            'dpd_min' => $filters['dpd_min'],
            'dpd_max' => $filters['dpd_max'],
            'customer_id' => $filters['customer_id'],
            'loan_id' => $filters['loan_id'],
        ], fn ($v) => !is_null($v) && $v !== '');

        return view('reports.loans.loan_arrears', [
            'asAtDate' => $asAtDate->toDateString(),
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'loanProducts' => $loanProducts,
            'officers' => $officers,
            'customer' => $customer,
            'filters' => $filters,
            'report' => $report,
            'exportUrl' => route('reports.loan_arrears.export', array_merge($exportQuery, ['format' => 'xlsx'])),
            'pdfUrl' => route('reports.loan_arrears.export', array_merge($exportQuery, ['format' => 'pdf'])),
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

        $asAtDate = $request->date('as_at_date');
        if (!$asAtDate) {
            abort(422, 'As At Date is required');
        }

        $filters = [
            'as_at_date' => $asAtDate,
            'subshop_id' => $subshopId,
            'loan_product_id' => $request->integer('loan_product_id'),
            'loan_officer_id' => $request->integer('loan_officer_id'),
            'loan_status' => $request->query('loan_status'),
            'dpd_min' => $request->integer('dpd_min') ?: null,
            'dpd_max' => $request->integer('dpd_max') ?: null,
            'customer_id' => $request->integer('customer_id'),
            'loan_id' => $request->integer('loan_id') ?: null,
            'per_page' => 100000,
            'page' => 1,
            'installments_page' => 1,
        ];

        $report = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'loan-arrears-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.loans.loan_arrears', [
                'report' => $report,
                'asAtDate' => $asAtDate->toDateString(),
                'subshopName' => $subshopName,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $export = new LoanArrearsExport($report);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
