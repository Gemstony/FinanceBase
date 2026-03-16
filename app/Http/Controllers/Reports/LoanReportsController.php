<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SubShop;

class LoanReportsController extends Controller
{
    public function index()
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('reports.loan_reports.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        return view('reports.loan_reports', compact('subshop'));
    }
}
