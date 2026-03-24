<?php

namespace App\Http\Controllers\Loans\Risk;

use App\Http\Controllers\Controller;
use App\Services\Loans\Risk\LoanDelinquencyEngine;
use App\Services\Loans\Risk\PortfolioRiskCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioRiskController extends Controller
{
    public function __construct(
        private readonly LoanDelinquencyEngine $delinquencyEngine,
        private readonly PortfolioRiskCalculator $portfolioRisk,
    ) {
    }

    /**
     * Display the portfolio risk dashboard.
     */
    public function dashboard(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $summary = $this->delinquencyEngine->getPortfolioRiskSummary($subshopId);

        // Count performing vs delinquent loans within the active portfolio only.
        $delinquentCount = $this->delinquencyEngine->getDelinquentLoans(1, $subshopId)->count();

        $performingCount = 0;
        $query = $this->portfolioRisk
            ->activeLoansQuery()
            ->select(['id']);

        if ($subshopId) {
            $query->where('subshop_id', $subshopId);
        }

        $query->chunkById(200, function ($loans) use (&$performingCount) {
            foreach ($loans as $loan) {
                if ($this->portfolioRisk->calculateLoanOutstanding($loan) <= 0) {
                    continue;
                }

                $hasOverdue = $loan->installments()
                    ->where('is_active', true)
                    ->where('status', 'overdue')
                    ->exists();

                if (!$hasOverdue) {
                    $performingCount++;
                }
            }
        });

        return view('risk.portfolio', compact('summary', 'delinquentCount', 'performingCount'));
    }

    /**
     * List all delinquent loans (1+ days overdue).
     */
    public function delinquentLoans(Request $request): View
    {
        $subshopId = (int) session('subshop_id');

        $loans = $this->delinquencyEngine->getDelinquentLoans(1, $subshopId);
        
        // Apply eager loading to avoid N+1
        $loans->load(['customer', 'loanGroup', 'loanProduct', 'latestDisbursement.processor']);

        // Manual sorting by days overdue if needed, or handle in view with DataTables
        // For simplicity, we'll let DataTables handle the UI sorting.

        return view('risk.delinquent', [
            'loans' => $loans,
            'title' => 'All Delinquent Loans',
            'days' => 1
        ]);
    }

    /**
     * List delinquent loans by specific PAR category.
     */
    public function delinquentByDays(int $days): View
    {
        $subshopId = (int) session('subshop_id');

        $loans = $this->delinquencyEngine->getDelinquentLoans($days, $subshopId);
        $loans->load(['customer', 'loanGroup', 'loanProduct', 'latestDisbursement.processor']);

        return view('risk.delinquent', [
            'loans' => $loans,
            'title' => "PAR{$days} Delinquent Loans",
            'days' => $days
        ]);
    }
}
