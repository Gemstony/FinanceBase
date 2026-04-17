<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LoanRestructures;
use App\Models\Loans;
use App\Models\SubShop;
use App\Services\Dashboard\FinancialDashboardService;
use Illuminate\Support\Facades\Schema;
use App\Services\Loans\Risk\PromiseToPayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialDashboardController extends Controller
{
    public function __construct(
        private readonly FinancialDashboardService $service,
        private readonly PromiseToPayService $promiseService
    ) {
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
            abort(403, 'No branch found for user');
        }

        $allSubshops = SubShop::where('shop_id', $shop->id)->orderBy('name')->get();
        $accessibleSubshopIds = $user->hasRole('Super Admin') || ($user->shop && $user->shop->id === $shop->id)
            ? $allSubshops->pluck('id')->all()
            : $user->subshops()->where('sub_shops.shop_id', $shop->id)->pluck('sub_shops.id')->all();

        $subshopId = $request->integer('subshop_id');
        if ($subshopId && !in_array($subshopId, $accessibleSubshopIds, true)) {
            abort(403, 'You do not have access to this branch');
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

        // Get today's pending promises count
        $promisesDueToday = $this->promiseService->getPromisesDueToday($accessibleSubshopIds);
        $pendingPromisesTodayCount = $promisesDueToday->count();

        // Get pending loan approvals count for current user
        $pendingApprovalsCount = $this->getPendingLoanApprovalsCount($user, $accessibleSubshopIds);

        // Get approved loans waiting for disbursement
        $pendingDisburseCount = $this->getPendingDisburseCount($accessibleSubshopIds);

        // Get pending restructure approvals count
        $pendingRestructureCount = $this->getPendingRestructureCount($accessibleSubshopIds);

        return view('dashboard.financial_dashboard', [
            'shop' => $shop,
            'subshops' => $allSubshops,
            'selectedSubshopId' => $subshopId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dashboardData' => $dashboardData,
            'pendingPromisesTodayCount' => $pendingPromisesTodayCount,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'pendingDisburseCount' => $pendingDisburseCount,
            'pendingRestructureCount' => $pendingRestructureCount,
        ]);
    }

    /**
     * Get pending loan approvals count for the current user.
     *
     * @param mixed $user
     * @param array $accessibleSubshopIds
     * @return int
     */
    private function getPendingLoanApprovalsCount($user, array $accessibleSubshopIds): int
    {
        if (empty($accessibleSubshopIds)) {
            return 0;
        }

        $userRoleIds = $user->roles->pluck('id')->map(fn ($v) => (string) $v)->all();
        $userRoleNames = $user->roles->pluck('name')->map(fn ($v) => (string) $v)->all();

        return Loans::query()
            ->whereIn('subshop_id', $accessibleSubshopIds)
            ->where('status', 'pending')
            ->where('requires_approval', true)
            ->whereExists(function ($q) use ($userRoleIds, $userRoleNames) {
                $q->selectRaw('1')
                    ->from('loan_approvals as la')
                    ->join('loan_product_approval_levels as lvl', 'lvl.id', '=', 'la.loan_product_approval_level_id')
                    ->whereColumn('la.loan_id', 'loans.id')
                    ->where('la.is_active', true)
                    ->where('la.status', 'pending')
                    ->whereRaw('la.level_order = (SELECT MIN(level_order) FROM loan_approvals WHERE loan_id = loans.id AND is_active = 1 AND status = \'pending\')')
                    ->where(function ($w) use ($userRoleIds, $userRoleNames) {
                        $w->whereIn('lvl.role_id', $userRoleIds);
                        if (!empty($userRoleNames)) {
                            $w->orWhereIn('lvl.role_id', $userRoleNames);
                        }
                    });
            })
            ->count();
    }

    /**
     * Get approved loans waiting for disbursement count.
     *
     * @param array $accessibleSubshopIds
     * @return int
     */
    private function getPendingDisburseCount(array $accessibleSubshopIds): int
    {
        if (empty($accessibleSubshopIds)) {
            return 0;
        }

        $query = Loans::query()
            ->whereIn('subshop_id', $accessibleSubshopIds)
            ->where('status', 'approved');

        if (Schema::hasTable('loan_disbursements')) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('loan_disbursements as ld')
                    ->whereColumn('ld.loan_id', 'loans.id');
            });
        }

        return $query->count();
    }

    /**
     * Get pending loan restructure approvals count.
     *
     * @param array $accessibleSubshopIds
     * @return int
     */
    private function getPendingRestructureCount(array $accessibleSubshopIds): int
    {
        if (empty($accessibleSubshopIds)) {
            return 0;
        }

        return LoanRestructures::query()
            ->where('is_active', true)
            ->where('status', 'pending')
            ->whereHas('loan', fn ($q) => $q->whereIn('subshop_id', $accessibleSubshopIds))
            ->count();
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
            return response()->json(['error' => 'No branch found'], 403);
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
