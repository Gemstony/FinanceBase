<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\LoanRepaymentExport;
use App\Models\Customers;
use App\Models\LoanProducts;
use App\Models\SubShop;
use App\Models\User;
use App\Services\Reports\LoanRepaymentReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LoanRepaymentReportController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly LoanRepaymentReportService $service,
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
            'loan_product_id' => $request->integer('loan_product_id'),
            'loan_officer_id' => $request->integer('loan_officer_id'),
            'payment_method' => $request->query('payment_method'),
            'loan_status' => $request->query('loan_status'),
            'customer_id' => $request->integer('customer_id'),
            'per_page' => $request->integer('per_page') ?: 15,
            'page' => $request->integer('page') ?: 1,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

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

        $paymentMethods = [
            'cash',
            'bank',
            'mobile_money',
            'cheque',
            'other',
        ];

        $customerSubshopIds = $subshopId ? [$subshopId] : ($accessibleSubshopIds ?: [-1]);
        $customers = Customers::query()
            ->active()
            ->whereIn('subshop_id', $customerSubshopIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $exportQuery = array_filter([
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'subshop_id' => $subshopId,
            'loan_product_id' => $filters['loan_product_id'],
            'loan_officer_id' => $filters['loan_officer_id'],
            'payment_method' => $filters['payment_method'],
            'loan_status' => $filters['loan_status'],
            'customer_id' => $filters['customer_id'],
        ], fn ($v) => !is_null($v) && $v !== '');

        return view('reports.loan_repayment', [
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'loanProducts' => $loanProducts,
            'officers' => $officers,
            'paymentMethods' => $paymentMethods,
            'customers' => $customers,
            'filters' => $filters,
            'report' => $data,
            'exportUrl' => route('reports.loan_repayment.export', array_merge($exportQuery, ['format' => 'xlsx'])),
            'pdfUrl' => route('reports.loan_repayment.export', array_merge($exportQuery, ['format' => 'pdf'])),
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
            'loan_product_id' => $request->integer('loan_product_id'),
            'loan_officer_id' => $request->integer('loan_officer_id'),
            'payment_method' => $request->query('payment_method'),
            'loan_status' => $request->query('loan_status'),
            'customer_id' => $request->integer('customer_id'),
            'per_page' => 100000,
            'page' => 1,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'loan-repayment-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : null;
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.loan_repayment', [
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

        $export = new LoanRepaymentExport($data);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
