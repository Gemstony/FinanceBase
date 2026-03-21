<?php

namespace App\Http\Controllers\Reports\Customers;

use App\Exports\Reports\Customers\CustomerListExport;
use App\Models\SubShop;
use App\Services\Reports\Customers\CustomerListService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class CustomerListController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly CustomerListService $service,
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

        $filters = [
            'subshop_id' => $subshopId,
            'search' => $request->string('search')->toString(),
            'customer_status' => $request->query('customer_status'),
            'loan_status' => $request->query('loan_status'),
            'loan_product_id' => $request->integer('loan_product_id'),
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        // Get filter options
        $loanProducts = $this->service->getLoanProducts($accessibleSubshopIds, $subshopId);
        $subshops = $this->service->getSubshops($accessibleSubshopIds);

        $exportQuery = array_filter([
            'subshop_id' => $subshopId,
            'search' => $filters['search'],
            'customer_status' => $filters['customer_status'],
            'loan_status' => $filters['loan_status'],
            'loan_product_id' => $filters['loan_product_id'],
            'per_page' => $filters['per_page'],
        ], fn ($v) => !is_null($v) && $v !== '');

        return view('reports.customers.customer_list', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'loanProducts' => $loanProducts,
            'filters' => $filters,
            'report' => $data,
            'exportUrl' => route('reports.customers.customer_list.export', array_merge($exportQuery, ['format' => 'xlsx'])),
            'pdfUrl' => route('reports.customers.customer_list.export', array_merge($exportQuery, ['format' => 'pdf'])),
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
            'subshop_id' => $subshopId,
            'search' => $request->string('search')->toString(),
            'customer_status' => $request->query('customer_status'),
            'loan_status' => $request->query('loan_status'),
            'loan_product_id' => $request->integer('loan_product_id'),
            'per_page' => $request->integer('per_page') ?: 25,
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'customer-list-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.customers.customer_list', [
                'report' => $data,
                'subshopName' => $subshopName,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
        $export = new CustomerListExport($data, $subshopName);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }
}