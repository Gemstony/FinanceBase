<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\LoanWriteoffs;
use App\Models\SubShop;
use App\Services\Loans\WriteOff\WriteOffManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WriteOffManagementController extends Controller
{
    public function __construct(
        private readonly WriteOffManagementService $service
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('writeoffs.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);

        $filters = $request->only([
            'date_from',
            'date_to',
            'borrower',
            'amount_min',
            'amount_max',
        ]);

        $writeoffs = $this->service->paginateWriteOffs($filters, $subshopId);

        return view('loans.writeoff.index', [
            'writeoffs' => $writeoffs,
            'filters' => $filters,
            'subshop' => $subshop,
        ]);
    }

    public function show(LoanWriteoffs $writeoff): View|RedirectResponse
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('writeoffs.show', $writeoff)]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);

        // Verify the write-off belongs to the current subshop
        if ($writeoff->loan && $writeoff->loan->subshop_id != $subshopId) {
            abort(403, 'Access denied: This write-off does not belong to your current branch.');
        }

        $data = $this->service->getWriteOffDetails($writeoff);

        return view('loans.writeoff.show', array_merge($data, ['subshop' => $subshop]));
    }

    public function recoveries(LoanWriteoffs $writeoff): View|RedirectResponse
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('writeoffs.recoveries', $writeoff)]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);

        // Verify the write-off belongs to the current subshop
        if ($writeoff->loan && $writeoff->loan->subshop_id != $subshopId) {
            abort(403, 'Access denied: This write-off does not belong to your current branch.');
        }

        $data = $this->service->getWriteOffRecoveries($writeoff);

        return view('loans.writeoff.recoveries', array_merge($data, ['subshop' => $subshop]));
    }
}
