<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Loans;
use App\Models\SubShop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RejectedLoansController extends Controller
{
    /**
     * Display a listing of rejected loans.
     */
    public function index(Request $request): View
    {
        $subshopId = (int) session('subshop_id');
        $subshop = SubShop::findOrFail($subshopId);

        $query = Loans::query()
            ->where('subshop_id', $subshopId)
            ->where('status', 'rejected')
            ->with(['loanProduct', 'customer', 'loanGroup'])
            ->orderByDesc('updated_at');

        // Simple search
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('loan_code', 'like', "%{$q}%")
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$q}%"))
                  ->orWhereHas('loanGroup', fn($g) => $g->where('name', 'like', "%{$q}%"));
            });
        }

        $loans = $query->get();

        return view('loans.rejected.index', compact('subshop', 'loans'));
    }
}
