<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PurchaseReturnsExport implements FromView
{
    public function __construct(public $rows, public $subshop, public $summary)
    {
    }

    public function view(): View
    {
        return view('exports.purchase_returns_excel', [
            'rows' => $this->rows,
            'subshop' => $this->subshop,
            'summary' => $this->summary,
        ]);
    }
}
