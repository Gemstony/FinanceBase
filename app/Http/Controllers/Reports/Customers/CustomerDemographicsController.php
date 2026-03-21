<?php

namespace App\Http\Controllers\Reports\Customers;

use App\Exports\Reports\Customers\CustomerDemographicsExport;
use App\Models\SubShop;
use App\Services\Reports\Customers\CustomerDemographicsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class CustomerDemographicsController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly CustomerDemographicsService $service,
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
            'from_date' => $request->string('from_date')->toString(),
            'to_date' => $request->string('to_date')->toString(),
            'gender' => $request->query('gender'),
            'region' => $request->query('region'),
            'category' => $request->query('category'),
            'is_active' => $request->query('is_active'),
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        // Get filter options
        $subshops = $this->service->getSubshops($accessibleSubshopIds);
        $regions = $this->service->getUniqueRegions($accessibleSubshopIds);
        $categories = $this->service->getUniqueCategories($accessibleSubshopIds);

        $exportQuery = array_filter([
            'subshop_id' => $subshopId,
            'from_date' => $filters['from_date'],
            'to_date' => $filters['to_date'],
            'gender' => $filters['gender'],
            'region' => $filters['region'],
            'category' => $filters['category'],
            'is_active' => $filters['is_active'],
        ], fn ($v) => !is_null($v) && $v !== '');

        return view('reports.customers.customer_demographics', [
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'filters' => $filters,
            'report' => $data,
            'exportUrl' => route('reports.customers.customer_demographics.export', array_merge($exportQuery, ['format' => 'xlsx'])),
            'pdfUrl' => route('reports.customers.customer_demographics.export', array_merge($exportQuery, ['format' => 'pdf'])),
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
            'from_date' => $request->string('from_date')->toString(),
            'to_date' => $request->string('to_date')->toString(),
            'gender' => $request->query('gender'),
            'region' => $request->query('region'),
            'category' => $request->query('category'),
            'is_active' => $request->query('is_active'),
        ];

        $data = $this->service->build($filters, $accessibleSubshopIds);

        $filenameBase = 'customer-demographics-report-' . now()->format('Y-m-d-His');

        if (strtolower($format) === 'pdf') {
            $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
            $shopLogoPath = $shop->logo ? public_path('storage/' . ltrim((string) $shop->logo, '/')) : null;

            $pdf = Pdf::loadView('reports.pdf.customers.customer_demographics', [
                'report' => $data,
                'subshopName' => $subshopName,
                'shop' => $shop,
                'shopLogoPath' => $shopLogoPath,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'filters' => $filters,
            ]);

            return $pdf->download($filenameBase . '.pdf');
        }

        $subshopName = $subshopId ? (optional($allSubshops->firstWhere('id', $subshopId))->name) : 'All Branches';
        $export = new CustomerDemographicsExport($data, $subshopName, $filters);
        $ext = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        return Excel::download(
            $export,
            $filenameBase . '.' . $ext,
            strtolower($format) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Drill-down: Get customers by region
     */
    public function byRegion(Request $request, string $region)
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

        $customers = $this->service->getCustomersByRegion($region, $accessibleSubshopIds);

        return view('reports.customers.demographics_region_detail', [
            'region' => $region,
            'customers' => $customers,
            'subshops' => $allSubshops,
        ]);
    }

    /**
     * Drill-down: Get customers by gender
     */
    public function byGender(Request $request, string $gender)
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

        $customers = $this->service->getCustomersByGender($gender, $accessibleSubshopIds);

        return view('reports.customers.demographics_gender_detail', [
            'gender' => $gender,
            'customers' => $customers,
            'subshops' => $allSubshops,
        ]);
    }
}
