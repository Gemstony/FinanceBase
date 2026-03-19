<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SubShop;

class AccountingReportsController extends Controller
{
    public function index()
    {
        $subshopId = session('subshop_id');

        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('reports.accounting_reports.index')]);
        }

        $subshop = SubShop::findOrFail($subshopId);

        return view('reports.accounting_reports', compact('subshop'));
    }
}
