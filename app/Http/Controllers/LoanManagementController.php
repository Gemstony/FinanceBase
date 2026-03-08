<?php

namespace App\Http\Controllers;

use App\Models\Loans;
use App\Models\SubShop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanManagementController extends Controller
{
    public function index(): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $pendingLoansCount = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('status', 'pending')
            ->count();

        $approvedLoansQuery = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('status', 'approved');

        if (Schema::hasTable('loan_disbursements')) {
            $approvedLoansQuery->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('loan_disbursements as ld')
                    ->whereColumn('ld.loan_id', 'loans.id');
            });
        }

        $approvedLoansCount = $approvedLoansQuery->count();

        $userRoleIds = $user->roles->pluck('id')->map(fn ($v) => (string) $v)->all();
        $userRoleNames = $user->roles->pluck('name')->map(fn ($v) => (string) $v)->all();

        $pendingApprovalsCount = Loans::query()
            ->where('subshop_id', $subshopId)
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

        return view('loans.loan_management', compact('subshop', 'pendingLoansCount', 'approvedLoansCount', 'pendingApprovalsCount'));
    }
}
