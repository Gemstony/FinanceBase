<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class SalesReturnsExport implements FromView
{
    public function __construct(public $rows, public $subshop, public $summary)
    {
    }

    public function view(): View
    {
        return view('exports.sales_returns_excel', [
            'rows' => $this->rows,
            'subshop' => $this->subshop,
            'summary' => $this->summary,
        ]);
    }
}
