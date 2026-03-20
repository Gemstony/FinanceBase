<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SubShop;
use App\Services\Dashboard\FinancialDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialDashboardController extends Controller
{
    public function __construct(private readonly FinancialDashboardService $service)
    {
    }

    /**
     * Display the financial dashboard.
     */
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

        // Handle date range from request or session
        if ($request->boolean('clear_filters')) {
            session()->forget(['fd_from_date', 'fd_to_date']);
        }
        
        $dateFrom = $request->query('from_date', session('fd_from_date'));
        $dateTo = $request->query('to_date', session('fd_to_date'));
        
        if ($dateFrom && $dateTo) {
            session(['fd_from_date' => $dateFrom, 'fd_to_date' => $dateTo]);
        } else {
            // Default to last 12 months
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        }

        $filters = [
            'from_date' => Carbon::parse($dateFrom)->startOfDay(),
            'to_date' => Carbon::parse($dateTo)->endOfDay(),
            'subshop_id' => $subshopId,
        ];

        $dashboardData = $this->service->build($filters, $accessibleSubshopIds);

        return view('dashboard.financial_dashboard', [
            'shop' => $shop,
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dashboardData' => $dashboardData,
        ]);
    }

    /**
     * Get dashboard data as JSON (for AJAX refresh).
     */
    public function data(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $shop = $user->shop ?: optional($user->subshops()->first())->shop;
        if (!$shop) {
            return response()->json(['error' => 'No shop found'], 403);
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        
        $dateFrom = $request->query('from_date', Carbon::now()->subMonths(11)->startOfMonth()->toDateString());
        $dateTo = $request->query('to_date', Carbon::now()->endOfMonth()->toDateString());

        $filters = [
            'from_date' => Carbon::parse($dateFrom)->startOfDay(),
            'to_date' => Carbon::parse($dateTo)->endOfDay(),
            'subshop_id' => $subshopId,
        ];

        $dashboardData = $this->service->build($filters, $accessibleSubshopIds);

        return response()->json($dashboardData);
    }
}
